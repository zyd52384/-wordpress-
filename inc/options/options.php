<?php
/**
 * Codestar Framework - 主题选项初始化
 */

if (!defined('ABSPATH')) exit;

// CSF 配置
add_action('csf_loaded', 'vdp_csf_setup');
function vdp_csf_setup() {
    if (!class_exists('CSF')) return;

    $prefix = 'vdp_options';

    CSF::createOptions($prefix, [
        'menu_title'      => '主题设置',
        'menu_slug'       => 'vdp-theme-options',
        'menu_type'       => 'submenu',
        'menu_parent'     => 'vdp-dashboard',
        'framework_title' => '虚拟文库主题设置',
        'framework_class' => 'vdp-options',
        'theme'           => 'light',
        'show_bar_menu'   => false,
        'show_search'     => true,
        'show_reset_all'  => true,
        'show_reset_section' => true,
        'show_all_options'   => false,
        'save_defaults'   => true,
        'ajax_save'       => true,
    ]);

    require_once VDP_THEME_INC . '/options/admin-options.php';
}
