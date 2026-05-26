<?php
/**
 * 合伙人 - AJAX 接口
 */

if (!defined('ABSPATH')) exit;

/** 加入计划 */
add_action('wp_ajax_vdp_partner_join', function() {
    check_ajax_referer('vdp_partner_nonce', '_ajax_nonce');
    $uid = get_current_user_id();
    if (!$uid) wp_send_json_error('请先登录');

    if (!VDP_Partner::is_eligible($uid)) wp_send_json_error('当前不符合加入条件');

    $code = VDP_Partner::generate_invite_code($uid);
    update_user_meta($uid, 'vdp_partner_joined_at', current_time('mysql'));
    wp_send_json_success([
        'code'        => $code,
        'invite_url'  => VDP_Partner::get_invite_url($uid),
    ]);
});

/** 申请审核（review 模式） */
add_action('wp_ajax_vdp_partner_apply', function() {
    check_ajax_referer('vdp_partner_nonce', '_ajax_nonce');
    $uid = get_current_user_id();
    if (!$uid) wp_send_json_error('请先登录');
    update_user_meta($uid, 'vdp_partner_applied', current_time('mysql'));
    wp_send_json_success('已提交申请');
});

/** 提现申请 */
add_action('wp_ajax_vdp_partner_withdraw', function() {
    check_ajax_referer('vdp_partner_nonce', '_ajax_nonce');
    $uid = get_current_user_id();
    if (!$uid) wp_send_json_error('请先登录');

    $amount  = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $channel = isset($_POST['channel']) ? sanitize_key($_POST['channel']) : '';
    $name    = isset($_POST['account_name']) ? sanitize_text_field(wp_unslash($_POST['account_name'])) : '';
    $no      = isset($_POST['account_no']) ? sanitize_text_field(wp_unslash($_POST['account_no'])) : '';

    $res = vdp_partner_create_withdrawal($uid, $amount, $channel, $name, $no);
    if (is_wp_error($res)) wp_send_json_error($res->get_error_message());
    wp_send_json_success($res);
});
