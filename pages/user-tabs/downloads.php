<?php
/**
 * 用户中心 - 下载记录
 */

if (!defined('ABSPATH')) exit;

global $wpdb;
$user_id = get_current_user_id();

// 查询已支付的资源订单
$rows = $wpdb->get_results($wpdb->prepare(
    "SELECT post_id, MAX(created_at) AS last_paid
     FROM {$wpdb->prefix}vdp_orders
     WHERE user_id = %d AND status = 1 AND post_id > 0
     GROUP BY post_id
     ORDER BY last_paid DESC LIMIT 100",
    $user_id
));

$is_vip = vdp_is_vip_user($user_id);
?>

<div class="vdp-panel">
    <h2 class="vdp-panel-title">下载记录</h2>

    <?php if ($is_vip) : ?>
        <div class="vdp-vip-tip">
            <?php echo vdp_get_vip_badge($user_id); ?>
            您是会员，可无限下载所有资源
        </div>
    <?php endif; ?>

    <?php if (empty($rows)) : ?>
        <div class="vdp-empty">暂无下载记录</div>
    <?php else : ?>
        <ul class="vdp-doc-list">
            <?php foreach ($rows as $row) :
                $post = get_post($row->post_id);
                if (!$post || $post->post_status !== 'publish') continue;
                $info = vdp_get_doc_file_info($row->post_id);
            ?>
                <li class="vdp-doc-item">
                    <?php echo vdp_get_format_badge($info['ext']); ?>
                    <a href="<?php echo esc_url(get_permalink($row->post_id)); ?>" class="vdp-doc-title"><?php echo esc_html($post->post_title); ?></a>
                    <span class="vdp-doc-meta">
                        购买时间：<?php echo esc_html($row->last_paid); ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
