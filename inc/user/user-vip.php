<?php
/**
 * 用户 VIP 等级（基于 user meta）
 * 区别于 VDP_Member 表，这里是简易标记式 VIP
 */

if (!defined('ABSPATH')) exit;

/**
 * 获取用户 VIP 等级
 * 0 = 普通  1 = 月度/年度  2 = 终身
 */
function vdp_get_user_vip_level($user_id = 0) {
    if (!$user_id) $user_id = get_current_user_id();
    if (!$user_id) return 0;

    $member = VDP_Member::has_active_membership($user_id);
    if (!$member) return 0;

    if (in_array($member['level'], ['lifetime'])) return 2;
    return 1;
}

/**
 * 获取用户 VIP 到期时间
 */
function vdp_get_user_vip_expires($user_id = 0) {
    if (!$user_id) $user_id = get_current_user_id();
    if (!$user_id) return '';

    $member = VDP_Member::has_active_membership($user_id);
    if (!$member) return '';

    if ($member['end_date'] >= '2099-01-01') return '永久';
    return $member['end_date'];
}

/**
 * 获取 VIP 徽章 HTML
 */
function vdp_get_vip_badge($user_id = 0) {
    $level = vdp_get_user_vip_level($user_id);
    if (!$level) return '';

    $colors = [1 => '#f39c12', 2 => '#e74c3c'];
    $names  = [1 => 'VIP', 2 => 'SVIP'];

    return sprintf(
        '<span class="vdp-vip-badge" style="background:%s">%s</span>',
        esc_attr($colors[$level]),
        esc_html($names[$level])
    );
}

/**
 * 是否为 VIP 用户
 */
function vdp_is_vip_user($user_id = 0) {
    return vdp_get_user_vip_level($user_id) > 0;
}

/**
 * 获取购买会员的 URL
 *
 * 未登录或 footer/卡片入口：返回独立 /?vdp_page=membership 页面，对外可分享。
 * 用户中心内部依然使用 vdp_get_user_center_url('vip')。
 */
function vdp_get_buy_vip_url() {
    if (function_exists('vdp_get_membership_url')) {
        return vdp_get_membership_url();
    }
    return vdp_get_user_center_url('vip');
}
