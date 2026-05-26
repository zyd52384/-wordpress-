<?php
/**
 * 合伙人 - 用户中心 Tab 渲染辅助
 *
 * 实际渲染在 pages/user-tabs/partner.php，这里只放共用函数
 */

if (!defined('ABSPATH')) exit;

function vdp_partner_render_join_box($user_id) {
    $mode = vdp_opt('partner_eligibility', 'auto');

    if ($mode === 'review' && !get_user_meta($user_id, 'vdp_partner_approved', true)) {
        $applied = get_user_meta($user_id, 'vdp_partner_applied', true);
        if ($applied) {
            echo '<div class="vdp-alert vdp-alert-info">您的合伙人申请已提交，等待管理员审核。</div>';
        } else {
            echo '<div class="vdp-partner-join">
                <h3>申请成为合伙人</h3>
                <p>提交申请后，管理员审核通过即可获得专属邀请链接。</p>
                <button class="btn btn-primary vdp-partner-apply">申请加入</button>
            </div>';
        }
        return;
    }

    if ($mode === 'vip_only' && VDP_Member::has_active_membership($user_id) === false) {
        echo '<div class="vdp-alert vdp-alert-warning">合伙人计划仅向 VIP 会员开放，<a href="' . esc_url(vdp_get_buy_vip_url()) . '">立即开通</a>。</div>';
        return;
    }

    // 自动 / 已审核通过 / VIP → 一键开通
    echo '<div class="vdp-partner-join">
        <h3>加入合伙人计划</h3>
        <p>L1 佣金 ' . esc_html(vdp_opt('partner_rate_l1', 20)) . '%，L2 佣金 ' . esc_html(vdp_opt('partner_rate_l2', 5)) . '%。</p>
        <button class="btn btn-primary vdp-partner-join-btn">立即加入</button>
    </div>';
}
