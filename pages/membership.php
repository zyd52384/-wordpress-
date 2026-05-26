<?php
/**
 * Template Name: 会员开通页
 *
 * 独立会员套餐展示与购买页面，公开可访问。
 * 支持两种入口：
 *   1. WP 后台「页面」分配该模板
 *   2. 路由 ?vdp_page=membership（由 inc/user/user.php 处理）
 */

if (!defined('ABSPATH')) exit;

$current_user_id = get_current_user_id();
$is_logged_in    = is_user_logged_in();
$member          = $is_logged_in ? VDP_Member::has_active_membership($current_user_id) : false;
$products        = VDP_Member::get_products();
$vip_enabled     = VDP_Member::is_enabled();

get_header(); ?>

<?php if (is_active_sidebar('user_top_fluid')) : ?>
    <div class="container fluid-widget vdp-fluid-top">
        <?php dynamic_sidebar('user_top_fluid'); ?>
    </div>
<?php endif; ?>

<div class="vdp-membership-page container" style="margin-top:30px;margin-bottom:30px;">

    <?php if (!$vip_enabled) : ?>
        <div class="vdp-membership-disabled">
            <h2>会员系统暂未开放</h2>
            <p>请稍候关注，或联系站长了解最新进展。</p>
            <a href="<?php echo esc_url(home_url('/')); ?>" class="vdp-btn-primary">返回首页</a>
        </div>
    <?php else : ?>

    <!-- 头图 / 介绍区 -->
    <div class="vdp-membership-hero">
        <h1 class="vdp-membership-title">开通会员，畅享全站资源</h1>
        <p class="vdp-membership-subtitle">海量精品文档无限下载，告别零散付费的烦恼</p>
    </div>

    <?php if ($member) : ?>
        <div class="vdp-membership-current">
            <div class="vdp-membership-current-badge">
                <?php echo vdp_get_vip_badge($current_user_id); ?>
            </div>
            <div class="vdp-membership-current-info">
                <div class="vdp-membership-current-name">
                    您已是 <?php
                        $level_name = isset($products[$member['level']]) ? $products[$member['level']]['name'] : $member['level'];
                        echo esc_html($level_name);
                    ?>
                </div>
                <div class="vdp-membership-current-end">
                    到期时间：<?php echo $member['end_date'] >= '2099-01-01' ? '<strong>永久</strong>' : esc_html($member['end_date']); ?>
                    <?php if ($member['end_date'] < '2099-01-01') : ?>
                        <span class="vdp-membership-current-days">（剩余 <?php echo (int) $member['remaining_days']; ?> 天）</span>
                    <?php endif; ?>
                </div>
            </div>
            <a href="<?php echo esc_url(vdp_get_user_center_url('vip')); ?>" class="vdp-btn-secondary">查看详情</a>
        </div>
    <?php endif; ?>

    <!-- 套餐卡片 -->
    <div class="vdp-membership-cards">
        <?php
        $idx = 0;
        foreach ($products as $key => $p) :
            $is_recommended = ($key === 'yearly');
            $idx++;
        ?>
            <div class="vdp-membership-card <?php echo $is_recommended ? 'is-recommended' : ''; ?>"
                 data-level="<?php echo esc_attr($key); ?>"
                 data-price="<?php echo esc_attr($p['price']); ?>">
                <?php if ($is_recommended) : ?>
                    <div class="vdp-membership-card-tag">最受欢迎</div>
                <?php endif; ?>
                <div class="vdp-membership-card-name"><?php echo esc_html($p['name']); ?></div>
                <div class="vdp-membership-card-price">
                    <span class="vdp-membership-card-currency">¥</span>
                    <span class="vdp-membership-card-amount"><?php echo number_format($p['price'], 0); ?></span>
                    <?php if ((float) $p['price'] != (int) $p['price']) : ?>
                        <span class="vdp-membership-card-decimal">.<?php echo substr(number_format($p['price'], 2), -2); ?></span>
                    <?php endif; ?>
                </div>
                <div class="vdp-membership-card-desc"><?php echo esc_html($p['desc']); ?></div>

                <?php if ($is_logged_in) : ?>
                    <button type="button" class="vdp-btn-buy-vip vdp-membership-card-btn" data-level="<?php echo esc_attr($key); ?>">
                        立即开通
                    </button>
                <?php else : ?>
                    <a href="<?php echo esc_url(home_url('/?vdp_auth=signin&redirect=' . urlencode((isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/')))); ?>"
                       class="vdp-membership-card-btn vdp-membership-card-btn-login">
                        登录后开通
                    </a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- 会员特权 -->
    <div class="vdp-membership-rights">
        <h2 class="vdp-membership-section-title">会员特权</h2>
        <div class="vdp-membership-rights-grid">
            <div class="vdp-membership-right-item">
                <div class="vdp-membership-right-icon"><i class="fa fa-download"></i></div>
                <div class="vdp-membership-right-name">无限下载</div>
                <div class="vdp-membership-right-desc">站内所有付费文档免费下</div>
            </div>
            <div class="vdp-membership-right-item">
                <div class="vdp-membership-right-icon"><i class="fa fa-bolt"></i></div>
                <div class="vdp-membership-right-name">高速通道</div>
                <div class="vdp-membership-right-desc">优先调度，下载更稳更快</div>
            </div>
            <div class="vdp-membership-right-item">
                <div class="vdp-membership-right-icon"><i class="fa fa-headphones"></i></div>
                <div class="vdp-membership-right-name">专属客服</div>
                <div class="vdp-membership-right-desc">问题反馈，优先响应</div>
            </div>
            <div class="vdp-membership-right-item">
                <div class="vdp-membership-right-icon"><i class="fa fa-star"></i></div>
                <div class="vdp-membership-right-name">尝鲜特权</div>
                <div class="vdp-membership-right-desc">新功能优先体验</div>
            </div>
        </div>
    </div>

    <!-- FAQ -->
    <div class="vdp-membership-faq">
        <h2 class="vdp-membership-section-title">常见问题</h2>
        <div class="vdp-membership-faq-list">
            <details class="vdp-membership-faq-item">
                <summary>开通后多久生效？</summary>
                <p>支付成功后会员立即开通，扫码完成后页面会自动刷新。</p>
            </details>
            <details class="vdp-membership-faq-item">
                <summary>到期后还能续费吗？</summary>
                <p>可以，续费后到期时间会在原基础上累加，永久会员一次开通终身有效。</p>
            </details>
            <details class="vdp-membership-faq-item">
                <summary>支持哪些支付方式？</summary>
                <p>目前支持微信扫码与支付宝（取决于站点配置），结算页面会自动适配。</p>
            </details>
            <details class="vdp-membership-faq-item">
                <summary>遇到问题如何联系？</summary>
                <p>在用户中心可看到客服入口，或直接通过站点页脚联系站长。</p>
            </details>
        </div>
    </div>

    <?php endif; ?>
</div>

<!-- 支付二维码弹窗（与 user-tabs/vip.php 复用 user.js 的 vdp-pay-qr-modal） -->
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

<?php if (is_active_sidebar('user_bottom_fluid')) : ?>
    <div class="container fluid-widget vdp-fluid-bottom">
        <?php dynamic_sidebar('user_bottom_fluid'); ?>
    </div>
<?php endif; ?>

<?php get_footer(); ?>
