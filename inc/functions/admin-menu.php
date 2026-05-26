<?php
/**
 * 后台管理菜单
 */

if (!defined('ABSPATH')) exit;

/**
 * 注册后台管理菜单
 */
function vdp_register_admin_menu() {
    add_menu_page(
        '虚拟文库',
        '虚拟文库',
        'manage_options',
        'vdp-dashboard',
        'vdp_render_dashboard_page',
        'dashicons-media-document',
        30
    );

    add_submenu_page(
        'vdp-dashboard',
        '仪表盘',
        '仪表盘',
        'manage_options',
        'vdp-dashboard',
        'vdp_render_dashboard_page'
    );

    add_submenu_page(
        'vdp-dashboard',
        '批量上传',
        '批量上传',
        'manage_options',
        'vdp-upload',
        'vdp_render_upload_page'
    );

    add_submenu_page(
        'vdp-dashboard',
        '已发布管理',
        '已发布管理',
        'manage_options',
        'vdp-posts',
        'vdp_render_posts_page'
    );

    add_submenu_page(
        'vdp-dashboard',
        '会员管理',
        '会员管理',
        'manage_options',
        'vdp-members',
        'vdp_render_members_page'
    );

    add_submenu_page(
        'vdp-dashboard',
        '社交登录',
        '社交登录',
        'manage_options',
        'vdp-oauth',
        'vdp_render_oauth_page'
    );

    add_submenu_page(
        'vdp-dashboard',
        '联系开发者',
        '联系开发者',
        'manage_options',
        'vdp-contact',
        'vdp_render_contact_page'
    );
}
add_action('admin_menu', 'vdp_register_admin_menu');
