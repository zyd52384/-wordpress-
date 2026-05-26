<?php
/**
 * OAuth 第三方登录主入口
 * 仅实现微信扫码登录（公众号 OAuth2.0）
 */

if (!defined('ABSPATH')) exit;

/**
 * 获取 OAuth 配置
 */
function vdp_oauth_config($provider = 'weixin') {
    $mode = vdp_opt('oauth_weixin_mode', 'mp');
    $configs = [
        'weixin' => [
            'enabled'   => (bool) vdp_opt('oauth_weixin_enabled', false),
            'appid'     => trim((string) vdp_opt('oauth_weixin_appid', '')),
            'appsecret' => trim((string) vdp_opt('oauth_weixin_appsecret', '')),
            'mode'      => $mode,
        ],
    ];
    return $provider ? ($configs[$provider] ?? []) : $configs;
}

/**
 * 是否启用某 OAuth 提供商
 */
function vdp_oauth_is_enabled($provider) {
    $cfg = vdp_oauth_config($provider);
    return !empty($cfg['enabled']) && !empty($cfg['appid']) && !empty($cfg['appsecret']);
}

/**
 * 获取启用的 OAuth 提供商列表
 */
function vdp_oauth_get_enabled() {
    $providers = [
        'weixin' => ['name' => '微信', 'icon' => 'fa-weixin', 'color' => '#1aad19'],
    ];
    $out = [];
    foreach ($providers as $key => $info) {
        if (vdp_oauth_is_enabled($key)) {
            $out[$key] = $info;
        }
    }
    return $out;
}

/**
 * 生成授权登录 URL
 */
function vdp_oauth_login_url($provider = 'weixin', $redirect = '') {
    if (!vdp_oauth_is_enabled($provider)) return '';
    return add_query_arg([
        'vdp_oauth' => $provider,
        'redirect'  => urlencode($redirect ?: home_url()),
    ], home_url('/'));
}

/**
 * 获取社交登录按钮 HTML
 */
function vdp_get_social_login_buttons() {
    $providers = vdp_oauth_get_enabled();
    if (empty($providers)) return '';

    $current_url = is_ssl() ? 'https://' : 'http://';
    $current_url .= ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');

    $html = '<div class="vdp-social-login">';
    $html .= '<div class="vdp-social-separator">第三方账号登录</div>';
    $html .= '<div class="vdp-social-buttons">';
    foreach ($providers as $key => $info) {
        $url = vdp_oauth_login_url($key, $current_url);
        $html .= sprintf(
            '<a href="%s" class="vdp-social-btn vdp-social-%s" title="%s登录" style="background:%s">
                <i class="fa %s"></i>
            </a>',
            esc_url($url),
            esc_attr($key),
            esc_attr($info['name']),
            esc_attr($info['color']),
            esc_attr($info['icon'])
        );
    }
    $html .= '</div></div>';
    return $html;
}

/**
 * 路由分发
 * ?vdp_wx_scan={id}  — 微信扫码后打开的页面（跳转 OAuth）
 * ?vdp_wx_qrcode     — 生成二维码（AJAX）
 * ?vdp_wx_poll       — 轮询扫码状态（AJAX）
 * ?vdp_oauth=weixin  — OAuth 跳转（open 模式 / H5 模式）
 * ?vdp_oauth_callback=weixin / weixin_h5 / weixin_scan — OAuth 回调
 */
function vdp_oauth_dispatch() {
    // 微信扫码后在微信内打开的页面 → 跳转 OAuth
    if (isset($_GET['vdp_wx_scan'])) {
        vdp_mp_scan_redirect();
        exit;
    }

    // 生成二维码（AJAX）
    if (isset($_GET['vdp_wx_qrcode'])) {
        $result = vdp_mp_create_qrcode();
        wp_send_json($result);
    }

    // 轮询扫码状态（AJAX）
    if (isset($_GET['vdp_wx_poll'])) {
        vdp_mp_poll_scene();
    }

    // OAuth 跳转
    if (!empty($_GET['vdp_oauth'])) {
        $provider = sanitize_key($_GET['vdp_oauth']);
        if ($provider === 'weixin') {
            $mode = vdp_opt('oauth_weixin_mode', 'mp');
            if ($mode === 'open') {
                vdp_weixin_oauth_redirect();
            } else {
                $in_wechat = strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'MicroMessenger') !== false;
                if ($in_wechat) {
                    vdp_mp_h5_redirect();
                } else {
                    wp_send_json(['error' => 'PC 端请使用扫码登录']);
                }
            }
        }
        exit;
    }

    // OAuth 回调
    if (!empty($_GET['vdp_oauth_callback'])) {
        $provider = sanitize_key($_GET['vdp_oauth_callback']);
        if ($provider === 'weixin') {
            vdp_weixin_oauth_callback();
        } elseif ($provider === 'weixin_h5') {
            vdp_mp_h5_callback();
        } elseif ($provider === 'weixin_scan') {
            vdp_mp_scan_callback();
        }
        exit;
    }
}
add_action('init', 'vdp_oauth_dispatch', 5);

/**
 * 通过 openid 查找或创建用户
 */
function vdp_oauth_find_or_create_user($provider, $openid, $userinfo = []) {
    $meta_key = 'oauth_' . $provider . '_openid';

    $users = get_users([
        'meta_key'    => $meta_key,
        'meta_value'  => $openid,
        'number'      => 1,
        'fields'      => 'ID',
    ]);
    if (!empty($users)) {
        return (int) $users[0];
    }

    // 创建新用户
    $nickname = !empty($userinfo['nickname']) ? sanitize_text_field($userinfo['nickname']) : '微信用户';
    $username = $provider . '_' . substr(md5($openid), 0, 12);

    // 防止重名
    $i = 0;
    $base = $username;
    while (username_exists($username)) {
        $i++;
        $username = $base . $i;
    }

    $user_id = wp_create_user($username, wp_generate_password(16, true), '');
    if (is_wp_error($user_id)) return 0;

    wp_update_user([
        'ID'           => $user_id,
        'display_name' => $nickname,
        'nickname'     => $nickname,
    ]);

    update_user_meta($user_id, $meta_key, $openid);
    if (!empty($userinfo['headimgurl'])) {
        update_user_meta($user_id, 'oauth_avatar', esc_url_raw($userinfo['headimgurl']));
    }
    if (!empty($userinfo['unionid'])) {
        update_user_meta($user_id, 'oauth_' . $provider . '_unionid', $userinfo['unionid']);
    }

    return $user_id;
}

/**
 * 完成登录流程
 */
function vdp_oauth_login_user($user_id, $redirect = '') {
    if (!$user_id) return;

    wp_set_auth_cookie($user_id, true);
    wp_set_current_user($user_id);
    $user = get_user_by('ID', $user_id);
    do_action('wp_login', $user->user_login, $user);

    wp_safe_redirect($redirect ?: vdp_get_user_center_url());
    exit;
}
