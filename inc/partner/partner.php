<?php
/**
 * 合伙人分销系统 - 入口
 *
 * 设计要点（V1，与用户确认）：
 *   - 两层级 L1/L2，比例后台可调
 *   - 绑定：cookie 30 天 + 注册/首单永久绑定，先到先得不改绑
 *   - 冷冻期 7 天后自动结算（WP Cron）
 *   - 退款：pending 自动 cancelled；settled 仅标记 refund_flagged=1
 *   - 同 user_id / 同 openid / 同 IP 屏蔽自购计佣
 *   - 同 IP 关系仅 flagged=1 不拦截
 *   - 提现 V1：手动审核（微信 + 支付宝 + 余额抵扣会员）
 */

if (!defined('ABSPATH')) exit;

define('VDP_PARTNER_DB_VERSION', '1.0.0');
define('VDP_PARTNER_COOKIE', 'vdp_ref');

/**
 * 选项快捷取值
 */
function vdp_partner_opt($key, $default = '') {
    return vdp_opt($key, $default);
}

class VDP_Partner {

    /** 准入校验 */
    public static function is_eligible($user_id) {
        if (!$user_id || !vdp_opt('partner_enabled', false)) return false;

        $mode = vdp_opt('partner_eligibility', 'auto');
        if ($mode === 'auto')   return true;
        if ($mode === 'vip_only') return VDP_Member::has_active_membership($user_id) !== false;
        if ($mode === 'review') {
            return get_user_meta($user_id, 'vdp_partner_approved', true) ? true : false;
        }
        return false;
    }

    /** 是否已经加入分销（开通 = 拿到邀请码） */
    public static function is_joined($user_id) {
        return (bool) get_user_meta($user_id, 'vdp_invite_code', true);
    }

    /** 建表 / 升级 */
    public static function install() {
        if (get_option('vdp_partner_db_version') === VDP_PARTNER_DB_VERSION) return;

        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql_rel = "CREATE TABLE {$wpdb->prefix}vdp_partner_relations (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            referrer_l1 bigint(20) unsigned DEFAULT 0,
            referrer_l2 bigint(20) unsigned DEFAULT 0,
            bound_at datetime DEFAULT CURRENT_TIMESTAMP,
            bound_source varchar(20) DEFAULT 'register',
            bound_ip varchar(45) DEFAULT '',
            flagged tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            KEY referrer_l1 (referrer_l1),
            KEY referrer_l2 (referrer_l2)
        ) $charset;";

        $sql_com = "CREATE TABLE {$wpdb->prefix}vdp_commissions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            order_num varchar(64) NOT NULL,
            beneficiary_id bigint(20) unsigned NOT NULL,
            buyer_id bigint(20) unsigned NOT NULL,
            level tinyint(1) NOT NULL,
            source_amount decimal(10,2) DEFAULT 0.00,
            rate decimal(5,4) DEFAULT 0.0000,
            amount decimal(10,2) DEFAULT 0.00,
            status varchar(16) DEFAULT 'pending',
            flagged tinyint(1) DEFAULT 0,
            refund_flagged tinyint(1) DEFAULT 0,
            frozen_until datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            settled_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY beneficiary_status (beneficiary_id, status),
            KEY order_id (order_id),
            KEY order_num (order_num)
        ) $charset;";

        $sql_wd = "CREATE TABLE {$wpdb->prefix}vdp_withdrawals (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            amount decimal(10,2) NOT NULL,
            channel varchar(20) NOT NULL,
            account_name varchar(64) DEFAULT '',
            account_no varchar(128) DEFAULT '',
            account_masked varchar(64) DEFAULT '',
            status varchar(16) DEFAULT 'pending',
            admin_note text,
            voucher_url varchar(255) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            paid_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY user_status (user_id, status),
            KEY status (status)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_rel);
        dbDelta($sql_com);
        dbDelta($sql_wd);

        // 给 vdp_orders 加 commission_status 列
        $col = $wpdb->get_var("SHOW COLUMNS FROM {$wpdb->prefix}vdp_orders LIKE 'commission_status'");
        if (!$col) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}vdp_orders ADD COLUMN commission_status tinyint(1) DEFAULT 0");
        }

        update_option('vdp_partner_db_version', VDP_PARTNER_DB_VERSION);
    }

    /** 生成 8 位邀请码 */
    public static function generate_invite_code($user_id) {
        $existing = get_user_meta($user_id, 'vdp_invite_code', true);
        if ($existing) return $existing;

        global $wpdb;
        do {
            $code = strtoupper(substr(md5($user_id . microtime(true) . wp_generate_password(8, false)), 0, 8));
            $taken = $wpdb->get_var($wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key='vdp_invite_code' AND meta_value=%s LIMIT 1",
                $code
            ));
        } while ($taken);

        update_user_meta($user_id, 'vdp_invite_code', $code);
        return $code;
    }

    /** 通过邀请码查 user_id */
    public static function find_user_by_code($code) {
        if (!$code) return 0;
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key='vdp_invite_code' AND meta_value=%s LIMIT 1",
            $code
        ));
    }

    /** 获取邀请链接 */
    public static function get_invite_url($user_id) {
        $code = self::generate_invite_code($user_id);
        return add_query_arg('ref', $code, home_url('/'));
    }

    /** 余额 / 累计 / 待结算 */
    public static function get_balance($user_id)        { return (float) get_user_meta($user_id, 'vdp_partner_balance', true); }
    public static function get_pending($user_id)        { return (float) get_user_meta($user_id, 'vdp_partner_pending', true); }
    public static function get_total_earned($user_id)   { return (float) get_user_meta($user_id, 'vdp_partner_total_earned', true); }

    public static function inc_meta($user_id, $key, $delta) {
        $v = (float) get_user_meta($user_id, $key, true);
        update_user_meta($user_id, $key, round($v + $delta, 2));
    }
}

add_action('after_switch_theme', ['VDP_Partner', 'install']);
add_action('admin_init', function() {
    if (get_option('vdp_partner_db_version') !== VDP_PARTNER_DB_VERSION) {
        VDP_Partner::install();
    }
});

// 加载子模块
vdp_require('partner/relations.php');
vdp_require('partner/commission.php');
vdp_require('partner/withdrawal.php');
vdp_require('partner/partner-center.php');
vdp_require('partner/ajax.php');
if (is_admin()) {
    vdp_require('partner/admin.php');
}

/**
 * 在用户中心菜单里加入「合伙人中心」
 */
add_filter('vdp_user_center_menus', function($menus) {
    if (!vdp_opt('partner_enabled', false)) return $menus;
    $menus['partner'] = ['name' => '合伙人中心', 'icon' => 'dashicons-groups'];
    return $menus;
});
