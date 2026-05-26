<?php
/**
 * 文章浏览数 / 下载数随机初始化（<1000）
 * meta_key: views（浏览）/ vdp_downloads（下载）
 */
if (!defined('ABSPATH')) exit;

function vdp_init_fake_stats($post_id) {
    if (get_post_type($post_id) !== 'post') return;
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;

    if (get_post_meta($post_id, 'views', true) === '') {
        update_post_meta($post_id, 'views', mt_rand(50, 999));
    }
    if (get_post_meta($post_id, 'vdp_downloads', true) === '') {
        update_post_meta($post_id, 'vdp_downloads', mt_rand(10, 999));
    }
}
add_action('save_post', 'vdp_init_fake_stats', 20);

// 存量回填（一次性，按主题版本标记）
add_action('admin_init', function () {
    if (get_option('vdp_fake_stats_backfilled') === '1') return;
    $ids = get_posts([
        'post_type'      => 'post',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);
    foreach ($ids as $id) {
        vdp_init_fake_stats($id);
    }
    update_option('vdp_fake_stats_backfilled', '1', false);
});
