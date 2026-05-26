<?php
/**
 * 微信开放平台扫码登录（snsapi_login）
 * 文档：https://developers.weixin.qq.com/doc/oplatform/Website_App/WeChat_Login/Wechat_Login.html
 */

if (!defined('ABSPATH')) exit;

/**
 * 跳转到微信授权页（PC 扫码）
 */
function vdp_weixin_oauth_redirect() {
    $cfg = vdp_oauth_config('weixin');
    if (empty($cfg['appid'])) {
        wp_die('微信登录未配置');
    }

    $redirect = !empty($_GET['redirect']) ? esc_url_raw(urldecode($_GET['redirect'])) : home_url();
    $state = wp_create_nonce('vdp_weixin_oauth_' . $redirect);

    // 写 cookie 保存 redirect（避免 state 反查麻烦）
    setcookie('vdp_oauth_redirect', $redirect, time() + 600, '/');

    $callback = home_url('/?vdp_oauth_callback=weixin');

    $auth_url = 'https://open.weixin.qq.com/connect/qrconnect?'
        . http_build_query([
            'appid'         => $cfg['appid'],
            'redirect_uri'  => $callback,
            'response_type' => 'code',
            'scope'         => 'snsapi_login',
            'state'         => $state,
        ])
        . '#wechat_redirect';

    wp_redirect($auth_url);
    exit;
}

/**
 * 微信回调处理
 */
function vdp_weixin_oauth_callback() {
    $cfg = vdp_oauth_config('weixin');

    $code  = sanitize_text_field($_GET['code'] ?? '');
    $state = sanitize_text_field($_GET['state'] ?? '');

    if (!$code) {
        wp_die('授权失败：缺少 code 参数');
    }

    // 1. 用 code 换 access_token + openid
    $token_url = 'https://api.weixin.qq.com/sns/oauth2/access_token?' . http_build_query([
        'appid'      => $cfg['appid'],
        'secret'     => $cfg['appsecret'],
        'code'       => $code,
        'grant_type' => 'authorization_code',
    ]);

    $resp = wp_remote_get($token_url, ['timeout' => 15, 'sslverify' => false]);
    if (is_wp_error($resp)) wp_die('请求微信接口失败：' . $resp->get_error_message());

    $token_data = json_decode(wp_remote_retrieve_body($resp), true);
    if (empty($token_data['access_token']) || empty($token_data['openid'])) {
        wp_die('微信授权失败：' . esc_html($token_data['errmsg'] ?? '未知错误'));
    }

    $access_token = $token_data['access_token'];
    $openid       = $token_data['openid'];

    // 2. 拉取用户信息
    $userinfo_url = 'https://api.weixin.qq.com/sns/userinfo?' . http_build_query([
        'access_token' => $access_token,
        'openid'       => $openid,
        'lang'         => 'zh_CN',
    ]);

    $resp = wp_remote_get($userinfo_url, ['timeout' => 15, 'sslverify' => false]);
    $userinfo = is_wp_error($resp) ? [] : json_decode(wp_remote_retrieve_body($resp), true);
    if (!is_array($userinfo)) $userinfo = [];

    // 3. 查找/创建用户并登录
    $user_id = vdp_oauth_find_or_create_user('weixin', $openid, $userinfo);
    if (!$user_id) {
        wp_die('登录失败：无法创建用户');
    }

    // 4. 跳转
    $redirect = $_COOKIE['vdp_oauth_redirect'] ?? '';
    setcookie('vdp_oauth_redirect', '', time() - 3600, '/');

    vdp_oauth_login_user($user_id, $redirect ?: vdp_get_user_center_url());
}
