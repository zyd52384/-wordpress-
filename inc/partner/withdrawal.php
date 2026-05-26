<?php
/**
 * 合伙人 - 提现状态机
 *
 * 状态：pending → approved → paid（管理员手动）；或 pending → rejected
 * 渠道：wechat / alipay / balance（后两者不需账号信息）
 * 频率：每周 1 次（CSF partner_weekly_limit 可调）
 */

if (!defined('ABSPATH')) exit;

/**
 * 申请提现
 *
 * @return array|WP_Error
 */
function vdp_partner_create_withdrawal($user_id, $amount, $channel, $account_name = '', $account_no = '') {
    if (!$user_id) return new WP_Error('not_login', '请先登录');
    if (!vdp_opt('partner_enabled', false)) return new WP_Error('disabled', '分销功能未启用');

    $amount = round((float) $amount, 2);
    $min = (float) vdp_opt('partner_min_withdraw', 50);
    if ($amount < $min) return new WP_Error('too_small', '最低提现金额 ' . $min . ' 元');

    $balance = VDP_Partner::get_balance($user_id);
    if ($amount > $balance + 0.001) return new WP_Error('insufficient', '余额不足');

    $allowed = (array) vdp_opt('partner_withdraw_channels', ['wechat', 'alipay', 'balance']);
    if (!in_array($channel, $allowed, true)) return new WP_Error('bad_channel', '不支持的提现渠道');

    // 频率限制
    $weekly_limit = max(1, (int) vdp_opt('partner_weekly_limit', 1));
    global $wpdb;
    $since = date('Y-m-d H:i:s', current_time('timestamp') - 7 * DAY_IN_SECONDS);
    $count_recent = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}vdp_withdrawals
         WHERE user_id=%d AND created_at>=%s AND status<>'rejected'",
        $user_id, $since
    ));
    if ($count_recent >= $weekly_limit) {
        return new WP_Error('too_frequent', '每 7 天最多申请 ' . $weekly_limit . ' 次提现');

    }

    if ($channel === 'wechat' || $channel === 'alipay') {
        if (!$account_name || !$account_no) {
            return new WP_Error('account_required', '请填写收款人姓名和账号');
        }
    }

    $masked = vdp_partner_mask_account($account_no);

    $wpdb->insert($wpdb->prefix . 'vdp_withdrawals', [
        'user_id'        => $user_id,
        'amount'         => $amount,
        'channel'        => $channel,
        'account_name'   => sanitize_text_field($account_name),
        'account_no'     => sanitize_text_field($account_no),
        'account_masked' => $masked,
        'status'         => 'pending',
        'created_at'     => current_time('mysql'),
    ]);

    // 立即冻结余额
    VDP_Partner::inc_meta($user_id, 'vdp_partner_balance', -$amount);

    // 记忆收款信息（方便下次填）
    if ($channel === 'wechat' || $channel === 'alipay') {
        update_user_meta($user_id, 'vdp_partner_realname', sanitize_text_field($account_name));
    }

    // 余额抵扣会员：直接开会员，立即标记为 paid
    if ($channel === 'balance') {
        $wd_id = $wpdb->insert_id;
        vdp_partner_balance_to_membership($user_id, $amount, $wd_id);
    }

    return ['ok' => true, 'id' => $wpdb->insert_id];
}

function vdp_partner_mask_account($no) {
    $len = strlen($no);
    if ($len <= 4) return str_repeat('*', $len);
    return substr($no, 0, 2) . str_repeat('*', max(0, $len - 6)) . substr($no, -4);
}

/**
 * 余额抵扣会员：按金额匹配最接近的套餐
 */
function vdp_partner_balance_to_membership($user_id, $amount, $wd_id) {
    if (!class_exists('VDP_Member')) return;
    $products = VDP_Member::get_products();

    // 找出 price <= amount 的最大档
    $matched = null;
    foreach ($products as $key => $p) {
        if ($p['price'] <= $amount && (!$matched || $p['price'] > $matched['price'])) {
            $matched = $p + ['key' => $key];
        }
    }
    if (!$matched) {
        // 没有匹配的：把余额退回（避免吃钱）
        VDP_Partner::inc_meta($user_id, 'vdp_partner_balance', $amount);
        global $wpdb;
        $wpdb->update($wpdb->prefix . 'vdp_withdrawals',
            ['status' => 'rejected', 'admin_note' => '没有可抵扣的会员套餐'],
            ['id' => $wd_id]
        );
        return;
    }

    // 直接发会员（绕过订单）
    global $wpdb;
    $start = current_time('mysql');
    $end   = ($matched['days'] > 0)
        ? date('Y-m-d H:i:s', current_time('timestamp') + $matched['days'] * DAY_IN_SECONDS)
        : '2099-12-31 23:59:59';

    $wpdb->insert($wpdb->prefix . 'vdp_memberships', [
        'user_id'    => $user_id,
        'level'      => $matched['key'],
        'order_num'  => 'WD' . $wd_id,
        'start_date' => $start,
        'end_date'   => $end,
        'status'     => 1,
        'created_at' => $start,
    ]);

    $wpdb->update($wpdb->prefix . 'vdp_withdrawals', [
        'status'     => 'paid',
        'paid_at'    => $start,
        'admin_note' => '已抵扣 ' . $matched['name'],
    ], ['id' => $wd_id]);
}

/**
 * 列表
 */
function vdp_partner_get_withdrawals($user_id, $limit = 20) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}vdp_withdrawals WHERE user_id=%d ORDER BY id DESC LIMIT %d",
        $user_id, $limit
    ));
}

/**
 * 管理员：审批
 */
function vdp_partner_approve_withdrawal($id, $action, $note = '', $voucher = '') {
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}vdp_withdrawals WHERE id=%d", $id));
    if (!$row || $row->status !== 'pending') return false;

    if ($action === 'approve') {
        $wpdb->update($wpdb->prefix . 'vdp_withdrawals',
            ['status' => 'approved', 'admin_note' => $note],
            ['id' => $id]
        );
        return true;
    }
    if ($action === 'paid') {
        $wpdb->update($wpdb->prefix . 'vdp_withdrawals', [
            'status'      => 'paid',
            'paid_at'     => current_time('mysql'),
            'admin_note'  => $note,
            'voucher_url' => esc_url_raw($voucher),
        ], ['id' => $id]);
        return true;
    }
    if ($action === 'reject') {
        $wpdb->update($wpdb->prefix . 'vdp_withdrawals',
            ['status' => 'rejected', 'admin_note' => $note],
            ['id' => $id]
        );
        // 退回余额
        VDP_Partner::inc_meta($row->user_id, 'vdp_partner_balance', (float) $row->amount);
        return true;
    }
    return false;
}
