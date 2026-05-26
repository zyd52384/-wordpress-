<?php
/**
 * 文档付费 AJAX 处理
 */

if (!defined('ABSPATH')) exit;

/**
 * AJAX: 单篇文档下单支付
 */
function vdp_ajax_buy_doc() {
    check_ajax_referer('vdp_user_nonce');

    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error('请先登录');
    }

    $post_id  = (int) ($_POST['post_id'] ?? 0);
    $pay_type = sanitize_text_field($_POST['pay_type'] ?? 'wechat');

    if (!$post_id) wp_send_json_error('参数错误');

    $post = get_post($post_id);
    if (!$post || $post->post_status !== 'publish') {
        wp_send_json_error('文档不存在');
    }

    // 已购买则直接返回成功
    if (VDP_Pay::has_paid($post_id, $user_id)) {
        wp_send_json_success(['paid' => true, 'message' => '您已购买']);
    }

    // VIP 跳过支付
    if (VDP_Member::is_enabled() && VDP_Member::has_active_membership($user_id)) {
        wp_send_json_success(['paid' => true, 'message' => '会员可直接下载']);
    }

    $doc = vdp_get_doc_file_info($post_id);
    $price = (float) $doc['price'];

    if ($price <= 0) {
        wp_send_json_success(['paid' => true, 'message' => '免费资源']);
    }

    if (!VDP_Pay::is_configured()) {
        wp_send_json_error('支付未配置，请联系管理员');
    }
    if (!VDP_Pay::is_method_enabled($pay_type)) {
        wp_send_json_error('该支付方式未启用');
    }

    $order_num = VDP_Pay::create_order($post_id, $user_id, $price, $pay_type, 'doc');
    $result    = VDP_Pay::initiate_pay($order_num, $price, $post->post_title, $pay_type);

    if (!empty($result['error'])) {
        wp_send_json_error($result['error']);
    }

    wp_send_json_success([
        'order_num'  => $order_num,
        'price'      => $price,
        'url_qrcode' => $result['url_qrcode'] ?? '',
        'url'        => $result['url'] ?? '',
    ]);
}
add_action('wp_ajax_vdp_buy_doc', 'vdp_ajax_buy_doc');

/**
 * AJAX: 记录下载次数
 */
function vdp_ajax_record_download() {
    check_ajax_referer('vdp_user_nonce');

    $post_id = (int) ($_POST['post_id'] ?? 0);
    if (!$post_id) wp_send_json_error('参数错误');

    $count = (int) get_post_meta($post_id, 'vdp_downloads', true);
    update_post_meta($post_id, 'vdp_downloads', $count + 1);

    wp_send_json_success(['count' => $count + 1]);
}
add_action('wp_ajax_vdp_record_download', 'vdp_ajax_record_download');
add_action('wp_ajax_nopriv_vdp_record_download', 'vdp_ajax_record_download');
