<?php
/**
 * 虎皮椒 V3 支付
 */

if (!defined('ABSPATH')) exit;

class VDP_Pay {

    const API_URL = 'https://api.xunhupay.com/payment/do.html';

    public static function get_config() {
        // 优先读 CSF 主题选项（vdp_options.xunhupay_*），向后兼容旧的 vdp_pay_settings
        return [
            'enabled'   => (bool) vdp_opt('pay_enabled', true),
            'appid'     => trim((string) vdp_opt('xunhupay_appid', '')),
            'appsecret' => trim((string) vdp_opt('xunhupay_appsecret', '')),
            'methods'   => (array) vdp_opt('xunhupay_methods', ['wechat', 'alipay']),
        ];
    }

    public static function is_configured() {
        $config = self::get_config();
        return !empty($config['enabled']) && !empty($config['appid']) && !empty($config['appsecret']);
    }

    public static function is_method_enabled($pay_type) {
        $config = self::get_config();
        return in_array($pay_type, $config['methods'], true);
    }

    public static function generate_order_num($prefix = 'VDP') {
        return $prefix . date('YmdHis') . mt_rand(1000, 9999);
    }

    public static function create_order($post_id, $user_id, $price, $pay_type = 'wechat', $product_type = 'doc') {
        global $wpdb;
        $order_num = self::generate_order_num($product_type === 'member' ? 'MVP' : 'VDP');

        $wpdb->insert($wpdb->prefix . 'vdp_orders', [
            'order_num'    => $order_num,
            'post_id'      => intval($post_id),
            'user_id'      => intval($user_id),
            'product_type' => $product_type,
            'pay_price'    => floatval($price),
            'pay_type'     => $pay_type,
            'status'       => 0,
            'created_at'   => current_time('mysql'),
        ]);

        return $order_num;
    }

    public static function has_paid($post_id, $user_id) {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}vdp_orders WHERE post_id = %d AND user_id = %d AND status = 1",
            $post_id, $user_id
        ));
    }

    public static function initiate_pay($order_num, $price, $title, $pay_type = 'wechat') {
        $config = self::get_config();
        if (!self::is_configured()) {
            return ['error' => '支付未配置'];
        }

        $data = [
            'version'        => '1.0',
            'appid'          => $config['appid'],
            'trade_order_id' => $order_num,
            'total_fee'      => $price,
            'title'          => mb_substr($title, 0, 30),
            'time'           => time(),
            'notify_url'     => home_url('?vdp_pay_notify=1'),
            'return_url'     => home_url('?vdp_pay_return=1'),
            'callback_url'   => home_url('?vdp_pay_return=1'),
            'nonce_str'      => substr(md5(uniqid()), 0, 16),
            'plugins'        => 'vdp_xunhupay_' . $pay_type,
            'wap_url'        => home_url(),
            'wap_name'       => get_bloginfo('name'),
        ];

        $data['hash'] = self::generate_hash($data, $config['appsecret']);

        $response = wp_remote_post(self::API_URL, [
            'body'      => $data,
            'timeout'   => 30,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            return ['error' => '请求支付接口失败'];
        }

        $result = json_decode(wp_remote_retrieve_body($response), true);
        if (!$result || !empty($result['errcode'])) {
            return ['error' => ($result['errmsg'] ?? '支付接口返回异常')];
        }

        return [
            'url_qrcode' => $result['url_qrcode'] ?? '',
            'url'        => $result['url'] ?? '',
            'order_id'   => $result['order_id'] ?? '',
        ];
    }

    private static function generate_hash($data, $appsecret) {
        ksort($data);
        $arg = '';
        $i = 0;
        foreach ($data as $key => $val) {
            if ($key === 'hash' || $val === '' || is_null($val)) continue;
            $arg .= ($i > 0 ? '&' : '') . $key . '=' . $val;
            $i++;
        }
        return md5($arg . $appsecret);
    }

    public static function handle_notify() {
        if (empty($_POST['hash']) || empty($_POST['trade_order_id'])) return;

        $config = self::get_config();
        $data = array_map('stripslashes', $_POST);

        $hash = self::generate_hash($data, $config['appsecret']);
        if ($data['hash'] !== $hash) {
            echo 'failed';
            exit;
        }

        if ($data['status'] === 'OD') {
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'vdp_orders',
                [
                    'status'   => 1,
                    'trade_no' => $data['transaction_id'] ?? '',
                    'paid_at'  => current_time('mysql'),
                ],
                ['order_num' => $data['trade_order_id']],
                ['%d', '%s', '%s'],
                ['%s']
            );

            VDP_Member::activate_from_order($data['trade_order_id']);

            do_action('vdp_payment_success', $data['trade_order_id']);

            echo 'success';
            exit;
        }

        echo 'failed';
        exit;
    }

    public static function handle_return() {
        $order_num = isset($_GET['trade_order_id']) ? sanitize_text_field($_GET['trade_order_id']) : '';
        if (!$order_num) return;

        global $wpdb;
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}vdp_orders WHERE order_num = %s",
            $order_num
        ));

        if ($order && $order->post_id) {
            wp_redirect(get_permalink($order->post_id));
            exit;
        }
        wp_redirect(home_url());
        exit;
    }

    public static function ajax_check_order() {
        $order_num = sanitize_text_field($_POST['order_num'] ?? '');
        if (!$order_num) wp_send_json_error('参数错误');

        global $wpdb;
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}vdp_orders WHERE order_num = %s",
            $order_num
        ));

        if (!$order) wp_send_json_error('订单不存在');

        wp_send_json_success([
            'status' => intval($order->status),
            'paid'   => $order->status == 1,
        ]);
    }
}

// 支付回调路由
add_action('init', function () {
    if (isset($_GET['vdp_pay_notify'])) {
        VDP_Pay::handle_notify();
    }
    if (isset($_GET['vdp_pay_return'])) {
        VDP_Pay::handle_return();
    }
});

// AJAX
add_action('wp_ajax_vdp_check_order', ['VDP_Pay', 'ajax_check_order']);
add_action('wp_ajax_nopriv_vdp_check_order', ['VDP_Pay', 'ajax_check_order']);
