<?php
/**
 * 合伙人 - 关系绑定
 *
 * 绑定时机：
 *   - 访客带 ?ref=CODE → 写 cookie 30 天
 *   - 用户注册 → 读 cookie + IP，写 vdp_partner_relations，永久绑定
 *   - 用户首单支付成功 → 若尚未绑定关系，按 cookie 绑（成功后清 cookie）
 */

if (!defined('ABSPATH')) exit;

/**
 * 访客落地写 cookie
 */
add_action('init', function() {
    if (!vdp_opt('partner_enabled', false)) return;
    if (empty($_GET['ref'])) return;

    $code = sanitize_text_field($_GET['ref']);
    if (!$code || strlen($code) > 32) return;

    // 已登录用户带 ref 不写 cookie（防自己邀自己）
    if (is_user_logged_in()) return;

    $days = max(1, (int) vdp_opt('partner_cookie_days', 30));
    setcookie(VDP_PARTNER_COOKIE, $code, time() + $days * DAY_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
}, 1);

/**
 * 用户注册时绑定上下级关系
 */
add_action('user_register', function($user_id) {
    if (!vdp_opt('partner_enabled', false)) return;
    $code = !empty($_COOKIE[VDP_PARTNER_COOKIE]) ? sanitize_text_field($_COOKIE[VDP_PARTNER_COOKIE]) : '';
    if (!$code) return;
    vdp_partner_bind_relation($user_id, $code, 'register');

    // 清 cookie
    setcookie(VDP_PARTNER_COOKIE, '', time() - 3600, COOKIEPATH ?: '/', COOKIE_DOMAIN);
}, 20);

/**
 * 微信注册等其它注册入口可能在 user_register 之外，
 * 这里在用户登录后 fallback 一次（仅当还没绑定且 cookie 仍在）
 */
add_action('wp_login', function($login, $user) {
    if (!vdp_opt('partner_enabled', false)) return;
    if (!empty($_COOKIE[VDP_PARTNER_COOKIE])) {
        $existing = vdp_partner_get_relation($user->ID);
        if (!$existing) {
            vdp_partner_bind_relation($user->ID, sanitize_text_field($_COOKIE[VDP_PARTNER_COOKIE]), 'register');
        }
        setcookie(VDP_PARTNER_COOKIE, '', time() - 3600, COOKIEPATH ?: '/', COOKIE_DOMAIN);
    }
}, 20, 2);

/**
 * 绑定关系：先到先得，已绑则不动
 *
 * @return bool 是否成功新建
 */
function vdp_partner_bind_relation($user_id, $code, $source = 'register') {
    if (!$user_id || !$code) return false;

    global $wpdb;
    $table = $wpdb->prefix . 'vdp_partner_relations';

    // 已绑定 → 跳过
    $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE user_id=%d", $user_id));
    if ($existing) return false;

    $referrer = VDP_Partner::find_user_by_code($code);
    if (!$referrer || $referrer == $user_id) return false;

    // L2 = referrer 的 L1
    $l2 = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT referrer_l1 FROM $table WHERE user_id=%d", $referrer
    ));

    // 防环：L2 不能是自己
    if ($l2 == $user_id) $l2 = 0;

    $ip = vdp_get_client_ip();
    $flagged = 0;

    // 同 IP 标记（不拦截）
    if ($ip) {
        $same_ip = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE referrer_l1=%d AND bound_ip=%s LIMIT 1",
            $referrer, $ip
        ));
        if ($same_ip) $flagged = 1;
    }

    $wpdb->insert($table, [
        'user_id'      => $user_id,
        'referrer_l1'  => $referrer,
        'referrer_l2'  => $l2,
        'bound_at'     => current_time('mysql'),
        'bound_source' => $source,
        'bound_ip'     => $ip,
        'flagged'      => $flagged,
    ]);

    return true;
}

/**
 * 读关系
 */
function vdp_partner_get_relation($user_id) {
    if (!$user_id) return null;
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}vdp_partner_relations WHERE user_id=%d",
        $user_id
    ));
}

/**
 * 统计我的下线人数
 */
function vdp_partner_count_downlines($user_id) {
    global $wpdb;
    $t = $wpdb->prefix . 'vdp_partner_relations';
    return [
        'l1' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE referrer_l1=%d", $user_id)),
        'l2' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE referrer_l2=%d", $user_id)),
    ];
}
