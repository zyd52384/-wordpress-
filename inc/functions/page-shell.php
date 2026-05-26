<?php
/**
 * 页面骨架函数 - 复刻子比的模块化页面布局
 *
 * 所有页面统一结构：
 *   header
 *   ├── all_top_fluid (全宽通栏)
 *   ├── {page}_top_fluid (本页通栏)
 *   main.container
 *   ├── content-wrap
 *   │   └── content-layout
 *   │       ├── {page}_top_content (主内容上方小工具)
 *   │       ├── {主内容}
 *   │       └── {page}_bottom_content (主内容下方小工具)
 *   └── sidebar
 *       ├── all_sidebar_top
 *       ├── {page}_sidebar
 *       └── all_sidebar_bottom
 *   ├── {page}_bottom_fluid (本页通栏)
 *   ├── all_bottom_fluid (全宽通栏)
 *   footer
 */

if (!defined('ABSPATH')) exit;

/**
 * 当前页面 key（与 sidebar 注册的 {page}_ 前缀对应）
 */
function vdp_current_page_key() {
    if (is_home() || is_front_page()) return 'home';
    if (is_singular('post')) return 'single';
    if (is_category()) return 'cat';
    if (is_tag()) return 'tag';
    if (is_search()) return 'search';
    if (is_singular('page')) return 'page';
    if (is_author()) return 'author';
    return '';
}

/**
 * 当前是否需要显示侧边栏
 */
function vdp_is_show_sidebar() {
    if (wp_is_mobile()) return apply_filters('vdp_show_sidebar_mobile', false);

    $page = vdp_current_page_key();

    // 单页面（page）默认无侧边栏
    if ($page === 'page') {
        $page_id = get_queried_object_id();
        $reg = (array) get_post_meta($page_id, 'widgets_register_container', true);
        return in_array('sidebar', $reg, true);
    }

    return apply_filters('vdp_is_show_sidebar', true, $page);
}

/**
 * 输出指定 sidebar（带空检查与容器）
 */
function vdp_dynamic_sidebar($id) {
    if (is_active_sidebar($id)) {
        dynamic_sidebar($id);
    }
}

/**
 * 输出顶部通栏（fluid）：all_top_fluid + {page}_top_fluid
 */
function vdp_render_top_fluid() {
    $page = vdp_current_page_key();
    $id   = $page ? $page . '_top_fluid' : '';

    if (!is_active_sidebar('all_top_fluid') && (!$id || !is_active_sidebar($id))) {
        return;
    }
    echo '<div class="container fluid-widget vdp-fluid-top">';
    vdp_dynamic_sidebar('all_top_fluid');
    if ($id) vdp_dynamic_sidebar($id);
    // page 自定义坑位
    if ($page === 'page') {
        $pid = get_queried_object_id();
        $reg = (array) get_post_meta($pid, 'widgets_register_container', true);
        if (in_array('top_fluid', $reg, true)) {
            vdp_dynamic_sidebar('page_top_fluid_' . $pid);
        }
    }
    echo '</div>';
}

/**
 * 输出底部通栏（fluid）：{page}_bottom_fluid + all_bottom_fluid
 */
function vdp_render_bottom_fluid() {
    $page = vdp_current_page_key();
    $id   = $page ? $page . '_bottom_fluid' : '';

    if (!is_active_sidebar('all_bottom_fluid') && (!$id || !is_active_sidebar($id))) {
        return;
    }
    echo '<div class="container fluid-widget vdp-fluid-bottom">';
    if ($id) vdp_dynamic_sidebar($id);
    if ($page === 'page') {
        $pid = get_queried_object_id();
        $reg = (array) get_post_meta($pid, 'widgets_register_container', true);
        if (in_array('bottom_fluid', $reg, true)) {
            vdp_dynamic_sidebar('page_bottom_fluid_' . $pid);
        }
    }
    vdp_dynamic_sidebar('all_bottom_fluid');
    echo '</div>';
}

/**
 * 输出主内容上方小工具坑位
 */
function vdp_render_top_content() {
    $page = vdp_current_page_key();
    if ($page) vdp_dynamic_sidebar($page . '_top_content');

    if ($page === 'page') {
        $pid = get_queried_object_id();
        $reg = (array) get_post_meta($pid, 'widgets_register_container', true);
        if (in_array('top_content', $reg, true)) {
            vdp_dynamic_sidebar('page_top_content_' . $pid);
        }
    }
}

/**
 * 输出主内容下方小工具坑位
 */
function vdp_render_bottom_content() {
    $page = vdp_current_page_key();
    if ($page) vdp_dynamic_sidebar($page . '_bottom_content');

    if ($page === 'page') {
        $pid = get_queried_object_id();
        $reg = (array) get_post_meta($pid, 'widgets_register_container', true);
        if (in_array('bottom_content', $reg, true)) {
            vdp_dynamic_sidebar('page_bottom_content_' . $pid);
        }
    }
}

/**
 * 输出侧边栏（页面类型自动派发）
 */
function vdp_render_sidebar() {
    if (!vdp_is_show_sidebar()) return;

    $page = vdp_current_page_key();
    echo '<div class="vdp-sidebar col-md-4 col-lg-3">';
    vdp_dynamic_sidebar('all_sidebar_top');

    if ($page && $page !== 'page') {
        vdp_dynamic_sidebar($page . '_sidebar');
    } elseif ($page === 'page') {
        $pid = get_queried_object_id();
        $reg = (array) get_post_meta($pid, 'widgets_register_container', true);
        if (in_array('sidebar', $reg, true)) {
            vdp_dynamic_sidebar('page_sidebar_' . $pid);
        }
    }

    vdp_dynamic_sidebar('all_sidebar_bottom');
    echo '</div>';
}

/**
 * 页面骨架开头（在 get_header 之后调用）
 */
function vdp_page_shell_start($args = []) {
    $args = wp_parse_args($args, [
        'container_class' => 'container main-content',
        'has_sidebar'     => null, // null=自动判断
    ]);

    vdp_render_top_fluid();

    $has_sb = $args['has_sidebar'];
    if ($has_sb === null) $has_sb = vdp_is_show_sidebar();

    $main_class = $args['container_class'];
    $main_class .= $has_sb ? ' vdp-has-sidebar' : ' vdp-no-sidebar';

    echo '<main class="' . esc_attr($main_class) . '">';
    echo '<div class="row">';
    $col_class = $has_sb ? 'col-md-8 col-lg-9' : 'col-md-12';
    echo '<div class="vdp-content ' . esc_attr($col_class) . '">';
    echo '<div class="content-wrap"><div class="content-layout">';

    vdp_render_top_content();
}

/**
 * 页面骨架结尾（在 get_footer 之前调用）
 */
function vdp_page_shell_end() {
    vdp_render_bottom_content();

    echo '</div></div>'; // content-layout, content-wrap
    echo '</div>';        // vdp-content

    vdp_render_sidebar();

    echo '</div>';        // row
    echo '</main>';

    vdp_render_bottom_fluid();
}

/**
 * 注册"按 page-id 自定义"的坑位（仅当后台为该 page 勾选过对应位置才会注册）
 */
function vdp_register_per_page_sidebars($defaults) {
    global $wpdb;
    $sql = "SELECT p.ID, p.post_title, m.meta_value
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'widgets_register_container'
            INNER JOIN {$wpdb->postmeta} m2 ON m2.post_id = p.ID AND m2.meta_key = 'widgets_register' AND m2.meta_value = '1'
            WHERE p.post_type = 'page' AND p.post_status = 'publish'";
    $rows = $wpdb->get_results($sql);
    if (empty($rows)) return;

    $poss = [
        'top_fluid'      => '顶部全宽度',
        'top_content'    => '主内容上方',
        'bottom_content' => '主内容下方',
        'bottom_fluid'   => '底部全宽度',
        'sidebar'        => '侧边栏',
    ];

    foreach ($rows as $row) {
        $reg = maybe_unserialize($row->meta_value);
        if (!is_array($reg)) continue;
        $name = mb_substr($row->post_title, 0, 10);
        foreach ($reg as $key) {
            if (!isset($poss[$key])) continue;
            register_sidebar(array_merge($defaults, [
                'name' => '[页面: ' . $name . '] - ' . $poss[$key],
                'id'   => 'page_' . $key . '_' . $row->ID,
                'description' => '显示在 [' . $row->post_title . '] 的 ' . $poss[$key],
            ]));
        }
    }
}
