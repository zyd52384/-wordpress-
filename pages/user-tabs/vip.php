<?php
/**
 * 用户中心 - 会员中心
 */

if (!defined('ABSPATH')) exit;

$user_id = get_current_user_id();
$member  = VDP_Member::has_active_membership($user_id);
$products = VDP_Member::get_products();
?>

<div class="vdp-panel">
    <h2 class="vdp-panel-title">会员中心</h2>

    <?php if ($member) : ?>
        <div class="vdp-vip-status">
            <?php echo vdp_get_vip_badge($user_id); ?>
            <div class="vdp-vip-status-info">
                <div class="vdp-vip-level"><?php
                    $level_name = isset($products[$member['level']]) ? $products[$member['level']]['name'] : $member['level'];
                    echo esc_html($level_name);
                ?></div>
                <div class="vdp-vip-end">
                    到期时间：<?php echo $member['end_date'] >= '2099-01-01' ? '<strong>永久</strong>' : esc_html($member['end_date']); ?>
                    <?php if ($member['end_date'] < '2099-01-01') : ?>
                        <span class="vdp-vip-days">（剩余 <?php echo (int) $member['remaining_days']; ?> 天）</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php else : ?>
        <div class="vdp-vip-empty">您还不是会员，开通后即可享受无限下载等会员特权</div>
    <?php endif; ?>

    <div class="vdp-vip-products">
        <h3>选择套餐</h3>
        <div class="vdp-vip-cards">
            <?php foreach ($products as $key => $p) : ?>
                <div class="vdp-vip-card" data-level="<?php echo esc_attr($key); ?>" data-price="<?php echo esc_attr($p['price']); ?>">
                    <div class="vdp-vip-card-name"><?php echo esc_html($p['name']); ?></div>
                    <div class="vdp-vip-card-price">¥<?php echo number_format($p['price'], 2); ?></div>
                    <div class="vdp-vip-card-desc"><?php echo esc_html($p['desc']); ?></div>
                    <button type="button" class="vdp-btn-buy-vip" data-level="<?php echo esc_attr($key); ?>">立即开通</button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="vdp-vip-rights">
        <h3>会员特权</h3>
        <ul>
            <li>✅ 站内所有付费文档免费下载</li>
            <li>✅ 高速下载通道</li>
            <li>✅ 会员专属客服</li>
            <li>✅ 优先享受新功能</li>
        </ul>
    </div>
</div>

<!-- 支付二维码弹窗 -->
<div id="vdp-pay-qr-modal" class="vdp-modal" style="display:none;">
    <div class="vdp-modal-box">
        <span class="vdp-modal-close">&times;</span>
        <h3>请扫码支付</h3>
        <div class="vdp-pay-amount">¥<span id="vdp-pay-amount-text">0.00</span></div>
        <img id="vdp-pay-qr-img" src="" alt="支付二维码" style="width:200px;height:200px;">
        <div class="vdp-pay-tip">支付成功后会员将自动开通</div>
        <div id="vdp-pay-status">等待支付...</div>
    </div>
</div>
