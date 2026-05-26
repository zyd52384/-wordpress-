<?php
/**
 * 合伙人 - 佣金生成 / 冷冻结算 / 退款联动
 *
 * 流程：
 *   支付成功 (vdp_payment_success) → 首单 fallback 绑定 → 生成 pending 佣金（含 frozen_until）
 *   WP Cron 每日扫描 → frozen_until <= now 且 pending → 结算到 balance
 *   订单退款 (vdp_order_refunded) → pending 自动 cancelled；settled 标记 refund_flagged=1
 */

if (!defined('ABSPATH')) exit;

/**
 * 支付成功 → 首单 fallback 绑定 + 生成佣金
 */
add_action('vdp_payment_success', 'vdp_partner_on_payment_success', 10, 1);

function vdp_partner_on_payment_success($order_num) {
    if (!vdp_opt('partner_enabled', false)) return;

    global $wpdb;
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}vdp_orders WHERE order_num=%s",
        $order_num
    ));
    if (!$order) return;
    if ((int) $order->commission_status !== 0) return; // 幂等
    if (!$order->user_id) return;

    // 首单 fallback 绑定（用户在 cookie 期内但未注册时下单）
    $existing = vdp_partner_get_relation($order->user_id);
    if (!$existing && !empty($_COOKIE[VDP_PARTNER_COOKIE])) {
        vdp_partner_bind_relation($order->user_id, sanitize_text_field($_COOKIE[VDP_PARTNER_COOKIE]), 'first_order');
        setcookie(VDP_PARTNER_COOKIE, '', time() - 3600, COOKIEPATH ?: '/', COOKIE_DOMAIN);
        $existing = vdp_partner_get_relation($order->user_id);
    }

    if (!$existing || !$existing->referrer_l1) {
        // 无上级 → 标记已处理但不生成佣金
        $wpdb->update($wpdb->prefix . 'vdp_orders', ['commission_status' => 1], ['id' => $order->id]);
        return;
    }

    // 自购拦截：同 user / 同 IP（同 openid 略，留扩展点）
    if ($existing->referrer_l1 == $order->user_id) {
        $wpdb->update($wpdb->prefix . 'vdp_orders', ['commission_status' => 1], ['id' => $order->id]);
        return;
    }
    if (!vdp_opt('partner_self_purchase', false)) {
        $buyer_ip = vdp_get_client_ip();
        $ref_meta_ip = get_user_meta($existing->referrer_l1, 'last_login_ip', true);
        if ($buyer_ip && $ref_meta_ip && $buyer_ip === $ref_meta_ip) {
            $wpdb->update($wpdb->prefix . 'vdp_orders', ['commission_status' => 1], ['id' => $order->id]);
            return;
        }
    }

    $amount = (float) $order->pay_price;
    if ($amount <= 0) {
        $wpdb->update($wpdb->prefix . 'vdp_orders', ['commission_status' => 1], ['id' => $order->id]);
        return;
    }

    $freeze_days  = max(0, (int) vdp_opt('partner_freeze_days', 7));
    $frozen_until = date('Y-m-d H:i:s', current_time('timestamp') + $freeze_days * DAY_IN_SECONDS);

    // 比例：VIP 升级版（受益人是 VIP 时 L1 走 VIP 比例）
    $is_vip_l1 = VDP_Member::has_active_membership($existing->referrer_l1) !== false;
    $rate_l1_pct = $is_vip_l1
        ? (float) vdp_opt('partner_rate_vip', 25)
        : (float) vdp_opt('partner_rate_l1', 20);
    $rate_l2_pct = (float) vdp_opt('partner_rate_l2', 5);

    $rate_l1 = $rate_l1_pct / 100;
    $rate_l2 = $rate_l2_pct / 100;

    // L1 佣金
    vdp_partner_insert_commission([
        'order_id'       => $order->id,
        'order_num'      => $order->order_num,
        'beneficiary_id' => $existing->referrer_l1,
        'buyer_id'       => $order->user_id,
        'level'          => 1,
        'source_amount'  => $amount,
        'rate'           => $rate_l1,
        'amount'         => round($amount * $rate_l1, 2),
        'flagged'        => (int) $existing->flagged,
        'frozen_until'   => $frozen_until,
    ]);

    // L2 佣金（如有）
    if ($existing->referrer_l2 && $existing->referrer_l2 != $order->user_id) {
        vdp_partner_insert_commission([
            'order_id'       => $order->id,
            'order_num'      => $order->order_num,
            'beneficiary_id' => $existing->referrer_l2,
            'buyer_id'       => $order->user_id,
            'level'          => 2,
            'source_amount'  => $amount,
            'rate'           => $rate_l2,
            'amount'         => round($amount * $rate_l2, 2),
            'flagged'        => (int) $existing->flagged,
            'frozen_until'   => $frozen_until,
        ]);
    }

    $wpdb->update($wpdb->prefix . 'vdp_orders', ['commission_status' => 1], ['id' => $order->id]);
}

function vdp_partner_insert_commission($data) {
    global $wpdb;
    $wpdb->insert($wpdb->prefix . 'vdp_commissions', array_merge([
        'status'     => 'pending',
        'created_at' => current_time('mysql'),
    ], $data));

    // 加到受益人的 pending
    if (!empty($data['beneficiary_id']) && !empty($data['amount'])) {
        VDP_Partner::inc_meta($data['beneficiary_id'], 'vdp_partner_pending', (float) $data['amount']);
    }
}

/**
 * 每日扫描结算
 */
add_action('vdp_partner_settle_cron', 'vdp_partner_settle_due');

if (!wp_next_scheduled('vdp_partner_settle_cron')) {
    wp_schedule_event(time() + 60, 'hourly', 'vdp_partner_settle_cron');
}

function vdp_partner_settle_due() {
    global $wpdb;
    $now = current_time('mysql');
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}vdp_commissions WHERE status='pending' AND frozen_until <= %s LIMIT 500",
        $now
    ));
    foreach ($rows as $r) {
        $wpdb->update($wpdb->prefix . 'vdp_commissions',
            ['status' => 'settled', 'settled_at' => $now],
            ['id' => $r->id]
        );
        VDP_Partner::inc_meta($r->beneficiary_id, 'vdp_partner_pending', -1 * (float) $r->amount);
        VDP_Partner::inc_meta($r->beneficiary_id, 'vdp_partner_balance', (float) $r->amount);
        VDP_Partner::inc_meta($r->beneficiary_id, 'vdp_partner_total_earned', (float) $r->amount);

        // 标记订单 commission_status = 2
        $wpdb->update($wpdb->prefix . 'vdp_orders', ['commission_status' => 2], ['id' => $r->order_id]);
    }
}

/**
 * 退款联动
 */
add_action('vdp_order_refunded', 'vdp_partner_on_order_refunded', 10, 1);

function vdp_partner_on_order_refunded($order_num) {
    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}vdp_commissions WHERE order_num=%s",
        $order_num
    ));
    foreach ($rows as $r) {
        if ($r->status === 'pending') {
            $wpdb->update($wpdb->prefix . 'vdp_commissions',
                ['status' => 'cancelled'],
                ['id' => $r->id]
            );
            VDP_Partner::inc_meta($r->beneficiary_id, 'vdp_partner_pending', -1 * (float) $r->amount);
        } elseif ($r->status === 'settled' || $r->status === 'paid') {
            $wpdb->update($wpdb->prefix . 'vdp_commissions',
                ['refund_flagged' => 1],
                ['id' => $r->id]
            );
        }
    }
    // 订单 commission_status = 3（已退款撤销）
    $wpdb->update($wpdb->prefix . 'vdp_orders',
        ['commission_status' => 3],
        ['order_num' => $order_num]
    );
}

/**
 * 查询某用户的佣金列表
 */
function vdp_partner_get_commissions($user_id, $args = []) {
    global $wpdb;
    $args = wp_parse_args($args, ['status' => '', 'limit' => 20, 'offset' => 0]);
    $where = $wpdb->prepare("beneficiary_id=%d", $user_id);
    if ($args['status']) {
        $where .= $wpdb->prepare(" AND status=%s", $args['status']);
    }
    return $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}vdp_commissions WHERE $where ORDER BY id DESC LIMIT " .
        intval($args['offset']) . ", " . intval($args['limit'])
    );
}
