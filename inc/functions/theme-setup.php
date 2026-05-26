<?php
/**
 * 主题基础设置
 */

if (!defined('ABSPATH')) exit;

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption']);
    add_theme_support('custom-logo');
    add_theme_support('automatic-feed-links');

    set_post_thumbnail_size(480, 320, true);

    register_nav_menus([
        'primary'   => '顶部主导航',
        'footer'    => '页脚导航',
        'mobile'    => '移动端导航',
    ]);
});

add_action('widgets_init', function () {
    $defaults = [
        'before_widget' => '<div id="%1$s" class="vdp-widget %2$s mb20">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ];

    // 全局坑位（所有页面通用）
    $global = [
        'all_top_fluid'      => ['name' => '所有页面 - 顶部全宽度', 'desc' => '显示在所有页面 main 容器之外、最顶部的整屏宽度位置'],
        'all_bottom_fluid'   => ['name' => '所有页面 - 底部全宽度', 'desc' => '显示在所有页面 main 容器之外、最底部的整屏宽度位置'],
        'all_sidebar_top'    => ['name' => '所有页面 - 侧栏顶部',   'desc' => '所有有侧边栏的页面，侧栏最顶部'],
        'all_sidebar_bottom' => ['name' => '所有页面 - 侧栏底部',   'desc' => '所有有侧边栏的页面，侧栏最底部'],
        'all_footer'         => ['name' => '所有页面 - 页脚区',     'desc' => '所有页面页脚区域内部'],
    ];

    // 页面 × 位置 矩阵
    $pages = [
        'home'   => '首页',
        'single' => '文档详情页',
        'cat'    => '分类页',
        'tag'    => '标签页',
        'search' => '搜索页',
    ];
    $poss = [
        'top_fluid'      => '顶部全宽度',
        'top_content'    => '主内容上方',
        'bottom_content' => '主内容下方',
        'bottom_fluid'   => '底部全宽度',
        'sidebar'        => '侧边栏',
    ];

    foreach ($global as $id => $info) {
        register_sidebar(array_merge($defaults, [
            'name'        => $info['name'],
            'id'          => $id,
            'description' => $info['desc'],
        ]));
    }

    foreach ($pages as $pkey => $pname) {
        foreach ($poss as $skey => $sname) {
            register_sidebar(array_merge($defaults, [
                'name'        => $pname . ' - ' . $sname,
                'id'          => $pkey . '_' . $skey,
                'description' => '显示在 ' . $pname . ' 的 ' . $sname,
            ]));
        }
    }

    // 整屏页面（用户中心、消息中心）
    $full_pages = [
        'user' => '用户中心',
        'auth' => '登录注册页',
    ];
    foreach ($full_pages as $key => $value) {
        register_sidebar(array_merge($defaults, [
            'name' => $value . ' - 顶部全宽度',
            'id'   => $key . '_top_fluid',
        ]));
        register_sidebar(array_merge($defaults, [
            'name' => $value . ' - 底部全宽度',
            'id'   => $key . '_bottom_fluid',
        ]));
    }
    register_sidebar(array_merge($defaults, [
        'name' => '用户中心 - 侧栏顶部',
        'id'   => 'user_sidebar_top',
    ]));
    register_sidebar(array_merge($defaults, [
        'name' => '用户中心 - 侧栏底部',
        'id'   => 'user_sidebar_bottom',
    ]));

    // 移动端弹出菜单底部
    register_sidebar(array_merge($defaults, [
        'name' => '移动端 - 弹出菜单底部',
        'id'   => 'mobile_nav_fluid',
    ]));

    // 单个 page 的自定义坑位（按 page id）
    if (function_exists('vdp_register_per_page_sidebars')) {
        vdp_register_per_page_sidebars($defaults);
    }
});

// 数据库表创建/升级
add_action('after_switch_theme', 'vdp_create_tables');
add_action('init', 'vdp_maybe_upgrade_db');

/**
 * 给侧栏内的所有小工具（包括 WP 核心的"近期文章/评论/归档/分类"等）
 * 注入"关闭"按钮 + data-closable 属性，让访客可以一键收起。
 * 关闭状态由前端 localStorage 持久化（key=vdp_dismissed_widgets）。
 */
add_filter('dynamic_sidebar_params', function ($params) {
    if (empty($params[0]['id'])) return $params;

    // 仅在含 "sidebar" 的注册区域生效（侧栏顶部/底部、各页面 sidebar、用户中心 sidebar 等）
    if (strpos($params[0]['id'], 'sidebar') === false) return $params;

    $bw = isset($params[0]['before_widget']) ? $params[0]['before_widget'] : '';
    if (!$bw || strpos($bw, 'data-closable') !== false) return $params;

    // 加 vdp-widget--closable 类
    $bw = preg_replace('/class="([^"]*)"/', 'class="$1 vdp-widget--closable"', $bw, 1);
    // 加 data-closable 属性
    $bw = preg_replace('/<div /', '<div data-closable="true" ', $bw, 1);
    // 在容器内最前面插入关闭按钮
    $bw .= '<button type="button" class="vdp-widget-close vdp-widget-close-floating" aria-label="关闭" title="关闭">&times;</button>';

    $params[0]['before_widget'] = $bw;
    return $params;
});

function vdp_maybe_upgrade_db() {
    $current = get_option('vdp_db_version');
    if ($current !== VDP_DB_VERSION) {
        vdp_create_tables();
    }
}

function vdp_create_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    $sql_orders = "CREATE TABLE {$wpdb->prefix}vdp_orders (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        order_num varchar(64) NOT NULL,
        post_id bigint(20) unsigned DEFAULT 0,
        user_id bigint(20) unsigned DEFAULT 0,
        product_type varchar(32) DEFAULT 'doc',
        pay_price decimal(10,2) DEFAULT 0.00,
        pay_type varchar(20) DEFAULT '',
        status tinyint(1) DEFAULT 0,
        trade_no varchar(128) DEFAULT '',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        paid_at datetime DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY order_num (order_num),
        KEY user_id (user_id),
        KEY post_id (post_id)
    ) $charset;";

    $sql_members = "CREATE TABLE {$wpdb->prefix}vdp_memberships (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        level varchar(32) DEFAULT '',
        order_num varchar(64) DEFAULT '',
        start_date datetime DEFAULT CURRENT_TIMESTAMP,
        end_date datetime DEFAULT NULL,
        status tinyint(1) DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY level (level)
    ) $charset;";

    $sql_wx_scene = "CREATE TABLE {$wpdb->prefix}vdp_wx_scene (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        scene_id int unsigned NOT NULL,
        openid varchar(64) DEFAULT '',
        user_id bigint(20) unsigned DEFAULT 0,
        status tinyint(1) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        expires_at datetime DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY scene_id (scene_id),
        KEY status (status)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql_orders);
    dbDelta($sql_members);
    dbDelta($sql_wx_scene);

    update_option('vdp_db_version', VDP_DB_VERSION);
}
