<?php
/**
 * 用户中心 - 我的订单
 */

if (!defined('ABSPATH')) exit;

global $wpdb;
$user_id = get_current_user_id();
$orders = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}vdp_orders WHERE user_id = %d ORDER BY created_at DESC LIMIT 50",
    $user_id
));
?>

<div class="vdp-panel">
    <h2 class="vdp-panel-title">我的订单</h2>

    <?php if (empty($orders)) : ?>
        <div class="vdp-empty">暂无订单记录</div>
    <?php else : ?>
        <table class="vdp-table">
            <thead>
                <tr>
                    <th>订单号</th>
                    <th>商品</th>
                    <th>金额</th>
                    <th>支付方式</th>
                    <th>状态</th>
                    <th>时间</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $o) :
                $title = '会员开通';
                if ($o->post_id > 0) {
                    $title = get_the_title($o->post_id) ?: '已删除资源';
                }
                $status = ['未支付', '已支付', '已退款'][min((int)$o->status, 2)];
                $status_color = $o->status == 1 ? '#27ae60' : ($o->status == 2 ? '#f44336' : '#999');
            ?>
                <tr>
                    <td><small><?php echo esc_html($o->order_num); ?></small></td>
                    <td><?php echo esc_html($title); ?></td>
                    <td>¥<?php echo number_format($o->pay_price, 2); ?></td>
                    <td><?php echo esc_html($o->pay_type ?: '-'); ?></td>
                    <td style="color:<?php echo $status_color; ?>"><?php echo esc_html($status); ?></td>
                    <td><small><?php echo esc_html($o->created_at); ?></small></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
