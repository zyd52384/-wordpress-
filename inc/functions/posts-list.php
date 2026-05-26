<?php
/**
 * 列表页 / 通用辅助：面包屑 / 相关推荐 / 默认头像
 * - 接入 CSF 字段：enable_breadcrumb / enable_related / default_avatar
 */

if (!defined('ABSPATH')) exit;

/**
 * 面包屑导航：当 CSF 开关 enable_breadcrumb 开启时输出
 * 在分类 / 归档 / 标签 / 搜索 / 文档详情 / 单页面输出
 */
function vdp_render_breadcrumb() {
    if (!vdp_opt('enable_breadcrumb', true)) return;
    if (is_home() || is_front_page()) return;

    $crumbs = [
        '<a href="' . esc_url(home_url('/')) . '"><i class="fa fa-home"></i> 首页</a>',
    ];

    if (is_category()) {
        $term = get_queried_object();
        if ($term && !empty($term->parent)) {
            foreach (array_reverse(get_ancestors($term->term_id, 'category')) as $aid) {
                $a = get_term($aid, 'category');
                if ($a && !is_wp_error($a)) {
                    $crumbs[] = '<a href="' . esc_url(get_term_link($a)) . '">' . esc_html($a->name) . '</a>';
                }
            }
        }
        $crumbs[] = '<span>' . esc_html(single_cat_title('', false)) . '</span>';
    } elseif (is_tag()) {
        $crumbs[] = '<span>标签：' . esc_html(single_tag_title('', false)) . '</span>';
    } elseif (is_search()) {
        $crumbs[] = '<span>搜索：' . esc_html(get_search_query()) . '</span>';
    } elseif (is_author()) {
        $crumbs[] = '<span>作者：' . esc_html(get_the_author()) . '</span>';
    } elseif (is_date()) {
        $crumbs[] = '<span>' . esc_html(get_the_archive_title()) . '</span>';
    } elseif (is_singular('post')) {
        $cats = get_the_category();
        if (!empty($cats)) {
            $cat = $cats[0];
            $crumbs[] = '<a href="' . esc_url(get_category_link($cat)) . '">' . esc_html($cat->name) . '</a>';
        }
        $crumbs[] = '<span>' . esc_html(get_the_title()) . '</span>';
    } elseif (is_singular('page')) {
        $crumbs[] = '<span>' . esc_html(get_the_title()) . '</span>';
    } elseif (is_404()) {
        $crumbs[] = '<span>404</span>';
    }

    echo '<nav class="vdp-breadcrumb"><div class="vdp-breadcrumb-inner">' . implode('<i class="fa fa-angle-right sep"></i>', $crumbs) . '</div></nav>';
}

/**
 * 相关推荐：取同分类下最近 6 篇
 */
function vdp_render_related_docs($post_id, $limit = 6) {
    if (!vdp_opt('enable_related', true)) return;

    $cats = wp_get_post_categories($post_id);
    if (empty($cats)) return;

    $q = new WP_Query([
        'post_type'      => 'post',
        'posts_per_page' => (int) $limit,
        'post__not_in'   => [(int) $post_id],
        'category__in'   => $cats,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'no_found_rows'  => true,
    ]);

    if (!$q->have_posts()) {
        wp_reset_postdata();
        return;
    }

    echo '<div class="doc-related card"><div class="card-body">';
    echo '<h3 class="section-title">相关推荐</h3>';
    echo '<div class="posts-grid posts-grid-cols-3">';
    while ($q->have_posts()) {
        $q->the_post();
        get_template_part('template/content', 'doc');
    }
    echo '</div></div></div>';
    wp_reset_postdata();
}

/**
 * 默认头像：CSF 上传的 default_avatar 优先于 WP 默认头像
 */
add_filter('get_avatar_data', function ($args, $id_or_email) {
    if (!empty($args['url']) && strpos((string) $args['url'], 'gravatar.com/avatar') === false) {
        return $args;
    }

    $custom = vdp_opt('default_avatar', '');
    $url = is_array($custom) ? ($custom['url'] ?? '') : $custom;
    if (!$url) return $args;

    $args['url']   = $url;
    $args['found_avatar'] = true;
    return $args;
}, 20, 2);
