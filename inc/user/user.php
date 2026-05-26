<?php
/**
 * 用户系统主文件
 * 借鉴子比主题的用户中心路由和元数据访问机制
 */

if (!defined('ABSPATH')) exit;

/**
 * 获取用户中心 URL
 * 路由格式：/user/[type]?tab=[tab]
 */
function vdp_get_user_center_url($type = null, $tab = null) {
    $slug = trim(vdp_opt('user_center_slug', 'user'));
    if (!$slug) $slug = 'user';

    if (get_option('permalink_structure')) {
        $url = home_url($slug . ($type ? '/' . $type : ''));
    } else {
        $url = add_query_arg('user_center', $type ? $type : '1', home_url());
    }

    if ($tab) {
        $url = add_query_arg('tab', $tab, $url);
    }
    return $url;
}

/**
 * 获取用户中心链接 HTML
 */
function vdp_get_user_center_link($class = '', $text = '用户中心') {
    return '<a rel="nofollow" href="' . vdp_get_user_center_url() . '" class="' . esc_attr($class) . '">' . esc_html($text) . '</a>';
}

/**
 * 注册用户中心 query_var
 */
function vdp_add_user_center_query_vars($vars) {
    if (!is_admin()) {
        $vars[] = 'user_center';
    }
    return $vars;
}
add_filter('query_vars', 'vdp_add_user_center_query_vars');

/**
 * 用户中心重写规则
 */
function vdp_user_center_rewrite_rules($wp_rewrite) {
    if (!get_option('permalink_structure')) return;

    $slug = trim(vdp_opt('user_center_slug', 'user'));
    if (!$slug) $slug = 'user';

    $new_rules = [
        $slug . '$'             => 'index.php?user_center=1',
        $slug . '/([A-Za-z]+)$' => 'index.php?user_center=$matches[1]',
    ];
    $wp_rewrite->rules = $new_rules + $wp_rewrite->rules;
}
add_action('generate_rewrite_rules', 'vdp_user_center_rewrite_rules');

/**
 * 加载用户中心模板
 */
function vdp_user_center_load_template() {
    $user_center = get_query_var('user_center');
    if (!$user_center) return;

    global $wp_query;
    $wp_query->is_home = false;
    $wp_query->is_404  = false;

    $template = get_template_directory() . '/pages/user-center.php';
    if (file_exists($template)) {
        load_template($template);
        exit;
    }
}
add_action('template_redirect', 'vdp_user_center_load_template', 5);

/**
 * 加载登录注册页（?vdp_auth=signin/signup）
 */
function vdp_load_auth_template() {
    if (empty($_GET['vdp_auth'])) return;

    $template = get_template_directory() . '/pages/user-auth.php';
    if (file_exists($template)) {
        load_template($template);
        exit;
    }
}
add_action('template_redirect', 'vdp_load_auth_template', 4);

/**
 * 加载会员开通页（?vdp_page=membership 或 /membership）
 */
function vdp_load_membership_template() {
    $vdp_page = isset($_GET['vdp_page']) ? sanitize_key($_GET['vdp_page']) : '';
    if ($vdp_page !== 'membership') return;

    $template = get_template_directory() . '/pages/membership.php';
    if (file_exists($template)) {
        load_template($template);
        exit;
    }
}
add_action('template_redirect', 'vdp_load_membership_template', 4);

/**
 * 获取会员开通页 URL
 */
function vdp_get_membership_url() {
    return add_query_arg('vdp_page', 'membership', home_url('/'));
}

/**
 * 是否关闭注册功能
 */
function vdp_is_close_signup() {
    if (vdp_opt('close_signup')) return true;
    return !get_option('users_can_register');
}

/**
 * 是否关闭登录功能
 */
function vdp_is_close_signin() {
    return (bool) vdp_opt('close_signin');
}

/**
 * 获取用户头像 URL（带本地 SVG 兜底，Gravatar 加载失败时使用）
 */
function vdp_get_avatar_url($user_id, $size = 96) {
    return get_avatar_url($user_id, ['size' => $size]);
}

/**
 * 生成本地 data:URI SVG 兜底头像（首字 + 由 UID 哈希决定的稳定背景色）
 * 国内 Gravatar 不稳定时作为 onerror 兜底
 */
function vdp_get_local_avatar($user_id, $size = 128) {
    $palette = ['#5B8DEF', '#27AE60', '#E67E22', '#9B59B6', '#16A085', '#E74C3C', '#2C3E50', '#F39C12', '#1ABC9C', '#34495E'];
    if ($user_id) {
        $user = get_userdata($user_id);
        $name = $user ? ($user->display_name ?: $user->user_login) : '';
    } else {
        $name = '';
    }
    $char = $name !== '' ? mb_substr($name, 0, 1, 'UTF-8') : 'U';
    $bg = $palette[abs(crc32((string)$user_id)) % count($palette)];

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . intval($size) . '" height="' . intval($size) . '" viewBox="0 0 128 128">'
         . '<rect width="128" height="128" fill="' . $bg . '"/>'
         . '<text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" '
         . 'font-family="PingFang SC,Microsoft YaHei,sans-serif" font-size="64" fill="#fff" font-weight="600">'
         . htmlspecialchars($char, ENT_QUOTES, 'UTF-8') . '</text></svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

/**
 * 获取用户显示名
 */
function vdp_get_user_name($user_id) {
    if (!$user_id) return '游客';
    $user = get_userdata($user_id);
    if (!$user) return '未知用户';
    return $user->display_name ?: $user->user_login;
}

/**
 * 用户登录时记录最后登录时间和 IP
 */
function vdp_user_login_meta($user_login, $user) {
    update_user_meta($user->ID, 'last_login', current_time('mysql'));
    $ip = vdp_get_client_ip();
    if ($ip) {
        update_user_meta($user->ID, 'last_login_ip', $ip);
    }
}
add_action('wp_login', 'vdp_user_login_meta', 10, 2);

/**
 * 用户注册时记录 IP
 */
function vdp_user_register_meta($user_id) {
    $ip = vdp_get_client_ip();
    if ($ip) {
        update_user_meta($user_id, 'register_ip', $ip);
    }
}
add_action('user_register', 'vdp_user_register_meta');

/**
 * 获取访问者 IP
 */
function vdp_get_client_ip() {
    $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = explode(',', $_SERVER[$key])[0];
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '';
}

/**
 * 输出登录注册按钮
 */
function vdp_get_signin_button($class = 'vdp-btn-primary') {
    if (is_user_logged_in() || vdp_is_close_signin()) return '';

    $html = '<a href="javascript:;" class="vdp-signin-loader ' . esc_attr($class) . '">登录</a>';
    if (!vdp_is_close_signup()) {
        $html .= ' <a href="javascript:;" class="vdp-signup-loader ' . esc_attr($class) . '">注册</a>';
    }
    return $html;
}

/**
 * 获取用户下载次数
 */
function vdp_get_user_downloads_count($user_id = 0) {
    if (!$user_id) $user_id = get_current_user_id();
    if (!$user_id) return 0;

    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}vdp_orders WHERE user_id = %d AND status = 1 AND post_id > 0",
        $user_id
    ));
}

/**
 * 用户中心菜单项
 */
function vdp_get_user_center_menus() {
    $menus = [
        ''         => ['name' => '我的资料',   'icon' => 'dashicons-id'],
        'orders'   => ['name' => '我的订单',   'icon' => 'dashicons-cart'],
        'downloads'=> ['name' => '下载记录',   'icon' => 'dashicons-download'],
        'vip'      => ['name' => '会员中心',   'icon' => 'dashicons-star-filled'],
        'settings' => ['name' => '账号设置',   'icon' => 'dashicons-admin-users'],
    ];
    return apply_filters('vdp_user_center_menus', $menus);
}
