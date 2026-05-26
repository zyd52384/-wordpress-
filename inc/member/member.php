<?php
/**
 * 会员系统
 */

if (!defined('ABSPATH')) exit;

class VDP_Member {

    public static function get_products() {
        return [
            'monthly' => [
                'name'  => '月度会员',
                'price' => (float) vdp_opt('vip_monthly_price', 19),
                'days'  => (int) vdp_opt('vip_monthly_days', 30),
                'desc'  => '30 天无限下载',
            ],
            'yearly' => [
                'name'  => '年度会员',
                'price' => (float) vdp_opt('vip_yearly_price', 99),
                'days'  => (int) vdp_opt('vip_yearly_days', 365),
                'desc'  => '一年无限下载，最划算',
            ],
            'lifetime' => [
                'name'  => '终身会员',
                'price' => (float) vdp_opt('vip_lifetime_price', 299),
                'days'  => 0,
                'desc'  => '永久无限下载',
            ],
        ];
    }

    public static function is_enabled() {
        return (bool) vdp_opt('vip_enabled', true);
    }

    public static function has_active_membership($user_id = 0) {
        if (!$user_id) $user_id = get_current_user_id();
        if (!$user_id) return false;

        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}vdp_memberships WHERE user_id = %d AND status = 1 ORDER BY end_date DESC LIMIT 1",
            $user_id
        ));

        if (!$row) return false;

        $is_lifetime = ($row->end_date >= '2099-01-01');

        if (!$is_lifetime && $row->end_date <= current_time('mysql')) {
            $wpdb->update($wpdb->prefix . 'vdp_memberships', ['status' => 0], ['id' => $row->id]);
            return false;
        }

        return [
            'id'             => intval($row->id),
            'level'          => $row->level,
            'start_date'     => $row->start_date,
            'end_date'       => $row->end_date,
            'remaining_days' => $is_lifetime ? 99999 : ceil((strtotime($row->end_date) - current_time('timestamp')) / 86400),
        ];
    }

    public static function can_download($post_id, $user_id = 0) {
        if (!$user_id) $user_id = get_current_user_id();
        if (!$user_id) return false;

        if (VDP_Pay::has_paid($post_id, $user_id)) return true;
        if (self::is_enabled() && self::has_active_membership($user_id)) return true;

        return false;
    }

    public static function activate_membership($user_id, $level, $extra_days = null, $order_num = '') {
        $products = self::get_products();
        if (!isset($products[$level])) return false;

        $product = $products[$level];
        $days = is_null($extra_days) ? intval($product['days']) : intval($extra_days);
        $is_lifetime = ($days <= 0);

        global $wpdb;
        $table = $wpdb->prefix . 'vdp_memberships';

        if (!$is_lifetime) {
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE user_id = %d AND level = %s AND status = 1 ORDER BY end_date DESC LIMIT 1",
                $user_id, $level
            ));

            if ($existing && strtotime($existing->end_date) > current_time('timestamp')) {
                $new_end = date('Y-m-d H:i:s', strtotime($existing->end_date) + $days * 86400);
                $wpdb->update($table, ['end_date' => $new_end, 'order_num' => $order_num], ['id' => $existing->id]);
                return $existing->id;
            }
        }

        $now = current_time('mysql');
        $end = $is_lifetime ? '2099-12-31 23:59:59' : date('Y-m-d H:i:s', current_time('timestamp') + $days * 86400);

        $wpdb->insert($table, [
            'user_id'    => $user_id,
            'level'      => $level,
            'order_num'  => $order_num,
            'start_date' => $now,
            'end_date'   => $end,
            'status'     => 1,
        ]);

        return $wpdb->insert_id;
    }

    public static function activate_from_order($order_num) {
        if (strpos($order_num, 'MVP') !== 0) return false;

        global $wpdb;
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}vdp_orders WHERE order_num = %s AND status = 1",
            $order_num
        ));
        if (!$order || $order->post_id != 0) return false;

        $products = self::get_products();
        foreach ($products as $key => $product) {
            if (abs(floatval($product['price']) - floatval($order->pay_price)) < 0.01) {
                self::activate_membership($order->user_id, $key, $order_num);
                return true;
            }
        }
        return false;
    }

    public static function ajax_buy_membership() {
        $user_id = get_current_user_id();
        if (!$user_id) wp_send_json_error('请先登录');

        $level    = sanitize_text_field($_POST['level'] ?? '');
        $pay_type = sanitize_text_field($_POST['pay_type'] ?? 'wechat');

        $products = self::get_products();
        if (!isset($products[$level])) wp_send_json_error('会员等级不存在');

        $product = $products[$level];
        $price = floatval($product['price']);

        $order_num = VDP_Pay::create_order(0, $user_id, $price, $pay_type, 'member');
        $result = VDP_Pay::initiate_pay($order_num, $price, $product['name'], $pay_type);
        if (!empty($result['error'])) wp_send_json_error($result['error']);

        wp_send_json_success([
            'url_qrcode' => $result['url_qrcode'] ?? '',
            'url'        => $result['url'] ?? '',
            'order_num'  => $order_num,
        ]);
    }

    public static function ajax_check_membership() {
        $user_id = get_current_user_id();
        if (!$user_id) wp_send_json_error('未登录');

        $member = self::has_active_membership($user_id);
        wp_send_json_success($member ? array_merge(['active' => true], $member) : ['active' => false]);
    }
}

// AJAX
add_action('wp_ajax_vdp_buy_membership', ['VDP_Member', 'ajax_buy_membership']);
add_action('wp_ajax_vdp_check_membership', ['VDP_Member', 'ajax_check_membership']);
