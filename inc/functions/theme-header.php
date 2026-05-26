<?php
/**
 * 页眉渲染辅助
 * - 接入 CSF 字段：site_logo / site_logo_dark / allow_signup（注册开关）
 */

if (!defined('ABSPATH')) exit;

/**
 * 输出站点 LOGO（自动适配 CSF 上传 / WP Customize / 文字回退）
 */
function vdp_render_site_logo() {
    $logo      = vdp_opt('site_logo', '');
    $logo_dark = vdp_opt('site_logo_dark', '');

    $logo_url = '';
    if (!empty($logo)) {
        $logo_url = is_array($logo) ? ($logo['url'] ?? '') : $logo;
    }
    $logo_dark_url = '';
    if (!empty($logo_dark)) {
        $logo_dark_url = is_array($logo_dark) ? ($logo_dark['url'] ?? '') : $logo_dark;
    }

    if ($logo_url) {
        echo '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr(get_bloginfo('name')) . '" class="vdp-site-logo vdp-site-logo-light">';
        if ($logo_dark_url) {
            echo '<img src="' . esc_url($logo_dark_url) . '" alt="' . esc_attr(get_bloginfo('name')) . '" class="vdp-site-logo vdp-site-logo-dark">';
        }
        return;
    }

    if (has_custom_logo()) {
        the_custom_logo();
        return;
    }

    echo '<span class="vdp-site-name">' . esc_html(get_bloginfo('name')) . '</span>';
}

/**
 * 注册开关：以主题选项 allow_signup 为唯一来源
 * 不再要求 WP 核心「任何人都可以注册」一并勾选，避免两边设置打架
 */
function vdp_signup_allowed() {
    if (vdp_opt('close_signup')) return false;
    return (bool) vdp_opt('allow_signup', true);
}

/**
 * 让主题的注册开关同步到 WP 核心 users_can_register
 * 这样 wp-login.php?action=register 等核心入口也会跟随主题设置
 */
add_filter('pre_option_users_can_register', function ($pre) {
    return vdp_signup_allowed() ? 1 : 0;
});
