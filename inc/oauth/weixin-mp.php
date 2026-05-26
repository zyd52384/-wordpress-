<?php
/**
 * 微信公众号扫码登录（URL 二维码 + OAuth 方案）
 * 复刻 xigua_login 设计：不需要用户关注公众号即可登录
 *
 * 流程：
 * 1. PC 生成 scene_id → 生成指向本站的 URL 二维码
 * 2. 用户微信扫码 → 微信内打开 URL → 触发公众号 OAuth (snsapi_userinfo)
 * 3. OAuth 回调拿到 openid → 查找/创建用户 → 更新 scene 表 status=1, user_id
 * 4. PC 端 2s 轮询 → 发现 status=1 → set_auth_cookie → 登录成功
 */

if (!defined('ABSPATH')) exit;

/**
 * 获取公众号 access_token（带缓存，H5 OAuth 不需要此 token，但备用）
 */
function vdp_mp_get_access_token() {
    $cached = get_transient('vdp_mp_access_token');
    if ($cached) return $cached;

    $appid     = trim((string) vdp_opt('oauth_weixin_appid', ''));
    $appsecret = trim((string) vdp_opt('oauth_weixin_appsecret', ''));
    if (!$appid || !$appsecret) return '';

    $url = 'https://api.weixin.qq.com/cgi-bin/token?' . http_build_query([
        'grant_type' => 'client_credential',
        'appid'      => $appid,
        'secret'     => $appsecret,
    ]);

    $resp = wp_remote_get($url, ['timeout' => 15, 'sslverify' => false]);
    if (is_wp_error($resp)) return '';

    $data = json_decode(wp_remote_retrieve_body($resp), true);
    if (empty($data['access_token'])) return '';

    set_transient('vdp_mp_access_token', $data['access_token'], $data['expires_in'] - 200);
    return $data['access_token'];
}

/**
 * 生成 scene 并返回二维码图片 URL
 * 二维码内容是一个指向本站的 URL，用户微信扫码后在微信内打开触发 OAuth
 */
function vdp_mp_create_qrcode() {
    global $wpdb;
    $table = $wpdb->prefix . 'vdp_wx_scene';

    // 清理过期记录和旧二维码文件
    $expired = $wpdb->get_col("SELECT scene_id FROM {$table} WHERE expires_at < NOW()");
    if ($expired) {
        $cache_dir = WP_CONTENT_DIR . '/cache/vdp-qrcode/';
        foreach ($expired as $eid) {
            @unlink($cache_dir . $eid . '.png');
        }
        $wpdb->query("DELETE FROM {$table} WHERE expires_at < NOW()");
    }

    // 生成唯一 scene_id
    $scene_id = mt_rand(100000, 9999999);
    $wpdb->delete($table, ['scene_id' => $scene_id]);

    $expire = 300;
    $wpdb->insert($table, [
        'scene_id'   => $scene_id,
        'status'     => 0,
        'created_at' => current_time('mysql'),
        'expires_at' => date('Y-m-d H:i:s', time() + $expire),
    ]);

    // 二维码内容：指向本站的 OAuth 入口 URL，微信扫码后在微信内打开
    $scan_url = home_url('/?vdp_wx_scan=' . $scene_id);

    // 本地生成二维码 PNG
    $cache_dir = WP_CONTENT_DIR . '/cache/vdp-qrcode/';
    if (!is_dir($cache_dir)) {
        wp_mkdir_p($cache_dir);
    }
    $qr_file = $cache_dir . $scene_id . '.png';

    if (!class_exists('QRcode')) {
        require_once VDP_THEME_INC . '/lib/phpqrcode.php';
    }
    QRcode::png($scan_url, $qr_file, QR_ECLEVEL_L, 6, 2);

    $qrcode_url = content_url('/cache/vdp-qrcode/' . $scene_id . '.png');

    return [
        'scene_id'   => $scene_id,
        'qrcode_url' => $qrcode_url,
        'expire'     => $expire,
    ];
}

/**
 * 轮询扫码状态
 */
function vdp_mp_poll_scene() {
    global $wpdb;
    $table = $wpdb->prefix . 'vdp_wx_scene';

    $scene_id = intval($_GET['scene_id'] ?? 0);
    if (!$scene_id) {
        wp_send_json(['status' => 'error', 'msg' => '参数错误']);
    }

    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE scene_id = %d", $scene_id
    ));

    if (!$row || strtotime($row->expires_at) < time()) {
        wp_send_json(['status' => 'expired']);
    }

    if ($row->status == 1 && $row->user_id) {
        // 手机端已完成 OAuth，PC 端直接登录该用户
        $user_id = (int) $row->user_id;
        wp_set_auth_cookie($user_id, true);
        wp_set_current_user($user_id);
        $user = get_user_by('ID', $user_id);
        if ($user) {
            do_action('wp_login', $user->user_login, $user);
        }
        // 清理已用 scene 和二维码文件
        $wpdb->delete($table, ['scene_id' => $scene_id]);
        @unlink(WP_CONTENT_DIR . '/cache/vdp-qrcode/' . $scene_id . '.png');
        wp_send_json(['status' => 'ok', 'redirect' => vdp_get_user_center_url()]);
    }

    wp_send_json(['status' => 'waiting']);
}

/**
 * 微信扫码后打开的页面（在微信内浏览器中）
 * URL: ?vdp_wx_scan={scene_id}
 * 自动跳转到公众号 OAuth 授权
 */
function vdp_mp_scan_redirect() {
    $scene_id = intval($_GET['vdp_wx_scan'] ?? 0);
    if (!$scene_id) wp_die('无效的扫码链接');

    global $wpdb;
    $table = $wpdb->prefix . 'vdp_wx_scene';
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE scene_id = %d AND status = 0", $scene_id
    ));

    if (!$row || strtotime($row->expires_at) < time()) {
        wp_die('二维码已过期，请返回电脑端刷新重试');
    }

    $appid    = trim((string) vdp_opt('oauth_weixin_appid', ''));
    $callback = home_url('/?vdp_oauth_callback=weixin_scan');

    // 跟 xigua 一样手动拼接，scene_id 放 state 里传递
    $redirect_uri = urlencode($callback);
    $state = $scene_id;
    $auth_url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid={$appid}&redirect_uri={$redirect_uri}&response_type=code&scope=snsapi_base&state={$state}#wechat_redirect";

    wp_redirect($auth_url);
    exit;
}

/**
 * 微信 OAuth 回调（扫码登录场景）
 * URL: ?vdp_oauth_callback=weixin_scan&scene_id=xxx&code=xxx
 * 在微信内浏览器中执行，拿到 openid 后更新 scene 表，PC 端轮询即可感知
 */
function vdp_mp_scan_callback() {
    $scene_id  = intval($_GET['state'] ?? ($_GET['scene_id'] ?? 0));
    $code      = sanitize_text_field($_GET['code'] ?? '');
    $appid     = trim((string) vdp_opt('oauth_weixin_appid', ''));
    $appsecret = trim((string) vdp_opt('oauth_weixin_appsecret', ''));

    if (!$code || !$scene_id) wp_die('授权失败：参数不完整');

    // 用 code 换 access_token + openid
    $token_url = 'https://api.weixin.qq.com/sns/oauth2/access_token?' . http_build_query([
        'appid'      => $appid,
        'secret'     => $appsecret,
        'code'       => $code,
        'grant_type' => 'authorization_code',
    ]);

    $resp = wp_remote_get($token_url, ['timeout' => 15, 'sslverify' => false]);
    if (is_wp_error($resp)) wp_die('请求微信接口失败');

    $token_data = json_decode(wp_remote_retrieve_body($resp), true);
    if (empty($token_data['openid'])) {
        wp_die('微信授权失败：' . esc_html($token_data['errmsg'] ?? '未知错误'));
    }

    $openid       = $token_data['openid'];
    $access_token = $token_data['access_token'];

    // 拉取用户信息
    $userinfo_url = 'https://api.weixin.qq.com/sns/userinfo?' . http_build_query([
        'access_token' => $access_token,
        'openid'       => $openid,
        'lang'         => 'zh_CN',
    ]);
    $resp     = wp_remote_get($userinfo_url, ['timeout' => 15, 'sslverify' => false]);
    $userinfo = is_wp_error($resp) ? [] : json_decode(wp_remote_retrieve_body($resp), true);
    if (!is_array($userinfo)) $userinfo = [];

    // 查找或创建 WordPress 用户
    $user_id = vdp_oauth_find_or_create_user('weixin', $openid, $userinfo);
    if (!$user_id) wp_die('登录失败：无法创建用户');

    // 更新 scene 表，标记扫码成功
    global $wpdb;
    $table = $wpdb->prefix . 'vdp_wx_scene';
    $wpdb->update($table, [
        'openid'  => $openid,
        'user_id' => $user_id,
        'status'  => 1,
    ], ['scene_id' => $scene_id, 'status' => 0]);

    // 在微信内显示成功提示页
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>登录成功</title>
        <style>
            body { font-family: -apple-system, sans-serif; text-align: center; padding: 60px 20px; background: #f5f5f5; }
            .success-icon { font-size: 60px; color: #1aad19; margin-bottom: 16px; }
            .success-text { font-size: 18px; color: #333; margin-bottom: 8px; }
            .success-tip { font-size: 14px; color: #999; }
        </style>
    </head>
    <body>
        <div class="success-icon">&#10004;</div>
        <div class="success-text">登录成功</div>
        <div class="success-tip">请返回电脑端继续操作</div>
    </body>
    </html>
    <?php
    exit;
}

/**
 * H5 微信内浏览器直接登录（非扫码场景，直接在微信内打开登录页）
 */
function vdp_mp_h5_redirect() {
    $appid    = trim((string) vdp_opt('oauth_weixin_appid', ''));
    $redirect = !empty($_GET['redirect']) ? esc_url_raw(urldecode($_GET['redirect'])) : home_url();

    setcookie('vdp_oauth_redirect', $redirect, time() + 600, '/');

    $callback = home_url('/?vdp_oauth_callback=weixin_h5');
    $auth_url = 'https://open.weixin.qq.com/connect/oauth2/authorize?' . http_build_query([
        'appid'         => $appid,
        'redirect_uri'  => $callback,
        'response_type' => 'code',
        'scope'         => 'snsapi_userinfo',
        'state'         => wp_create_nonce('vdp_wx_h5'),
    ]) . '#wechat_redirect';

    wp_redirect($auth_url);
    exit;
}

/**
 * H5 OAuth 回调（直接在微信内打开登录页的场景）
 */
function vdp_mp_h5_callback() {
    $appid     = trim((string) vdp_opt('oauth_weixin_appid', ''));
    $appsecret = trim((string) vdp_opt('oauth_weixin_appsecret', ''));
    $code      = sanitize_text_field($_GET['code'] ?? '');

    if (!$code) wp_die('授权失败：缺少 code');

    $token_url = 'https://api.weixin.qq.com/sns/oauth2/access_token?' . http_build_query([
        'appid'      => $appid,
        'secret'     => $appsecret,
        'code'       => $code,
        'grant_type' => 'authorization_code',
    ]);

    $resp = wp_remote_get($token_url, ['timeout' => 15, 'sslverify' => false]);
    if (is_wp_error($resp)) wp_die('请求微信接口失败');

    $token_data = json_decode(wp_remote_retrieve_body($resp), true);
    if (empty($token_data['openid'])) {
        wp_die('微信授权失败：' . esc_html($token_data['errmsg'] ?? '未知错误'));
    }

    $openid       = $token_data['openid'];
    $access_token = $token_data['access_token'];

    $userinfo_url = 'https://api.weixin.qq.com/sns/userinfo?' . http_build_query([
        'access_token' => $access_token,
        'openid'       => $openid,
        'lang'         => 'zh_CN',
    ]);
    $resp     = wp_remote_get($userinfo_url, ['timeout' => 15, 'sslverify' => false]);
    $userinfo = is_wp_error($resp) ? [] : json_decode(wp_remote_retrieve_body($resp), true);
    if (!is_array($userinfo)) $userinfo = [];

    $user_id = vdp_oauth_find_or_create_user('weixin', $openid, $userinfo);
    if (!$user_id) wp_die('登录失败：无法创建用户');

    $redirect = $_COOKIE['vdp_oauth_redirect'] ?? '';
    setcookie('vdp_oauth_redirect', '', time() - 3600, '/');

    vdp_oauth_login_user($user_id, $redirect ?: vdp_get_user_center_url());
}
