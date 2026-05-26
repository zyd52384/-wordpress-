<?php
/**
 * 用户相关小工具
 */

if (!defined('ABSPATH')) exit;

/**
 * 用户卡片：登录态显示用户信息，未登录显示登录注册按钮 + 社交登录
 */
class VDP_Widget_UserCard extends VDP_Widget {

    public function __construct() {
        parent::__construct(
            'vdp_widget_user_card',
            '【文库】用户卡片',
            ['description' => '登录显示用户信息，未登录显示登录/注册/微信快捷登录']
        );
    }

    protected function fields() {
        return [
            'title' => ['type' => 'text', 'label' => '标题（建议留空）', 'default' => ''],
            'cover' => [
                'type'    => 'text',
                'label'   => '顶部封面图 URL',
                'default' => '',
                'desc'    => '为空时显示默认渐变背景',
            ],
            'guest_text' => [
                'type'    => 'text',
                'label'   => '未登录欢迎语',
                'default' => 'HI！请登录',
            ],
            'enable_social' => [
                'type'    => 'checkbox',
                'label'   => '显示社交账号登录',
                'default' => 1,
            ],
        ];
    }

    public function widget($args, $instance) {
        if (isset($args['before_widget']) && strpos($args['before_widget'], 'class="') !== false) {
            $args['before_widget'] = preg_replace(
                '/class="([^"]*)"/',
                'class="$1 vdp-widget--user-card"',
                $args['before_widget'],
                1
            );
        }
        parent::widget($args, $instance);
    }

    protected function render($args, $instance) {
        $cover = !empty($instance['cover']) ? esc_url($instance['cover']) : '';
        $cover_style = $cover
            ? 'background-image:url(' . $cover . ');'
            : '';

        if (is_user_logged_in()) {
            $this->render_logged_in($cover_style);
        } else {
            $this->render_guest($cover_style, $instance);
        }
    }

    private function render_logged_in($cover_style) {
        $user_id = get_current_user_id();
        $user    = wp_get_current_user();
        $member  = class_exists('VDP_Member') ? VDP_Member::has_active_membership($user_id) : false;
        $avatar  = get_avatar_url($user_id, ['size' => 160]);
        $fallback = vdp_get_local_avatar($user_id, 160);
        $stats   = $this->get_stats($user_id);
        ?>
        <div class="vdp-uc">
            <div class="vdp-uc-cover" style="<?php echo esc_attr($cover_style); ?>"></div>
            <div class="vdp-uc-avatar">
                <img src="<?php echo esc_url($avatar); ?>" alt="avatar"
                     data-fallback="<?php echo esc_attr($fallback); ?>"
                     onerror="this.onerror=null;this.src=this.dataset.fallback;">
            </div>
            <div class="vdp-uc-body">
                <div class="vdp-uc-name">
                    <?php echo esc_html($user->display_name ?: $user->user_login); ?>
                    <?php if (function_exists('vdp_get_vip_badge')) echo vdp_get_vip_badge($user_id); ?>
                </div>
                <?php if ($member) : ?>
                    <?php
                    $is_lifetime = $member['end_date'] >= '2099-01-01';
                    $expire = $is_lifetime ? '永久有效' : '到期 ' . esc_html(date('Y-m-d', strtotime($member['end_date'])));
                    ?>
                    <div class="vdp-uc-vip-info"><i class="fa fa-id-badge"></i> <?php echo $expire; ?></div>
                <?php else : ?>
                    <a href="<?php echo esc_url(vdp_get_buy_vip_url()); ?>" class="vdp-uc-vip-buy">
                        <i class="fa fa-diamond"></i> 开通会员
                    </a>
                <?php endif; ?>

                <?php if (vdp_opt('partner_enabled', false)) : ?>
                    <a href="<?php echo esc_url(vdp_get_user_center_url('partner')); ?>" class="vdp-uc-partner-btn">
                        <i class="fa fa-share-alt"></i> 我要分销 <span class="vdp-uc-partner-tag">赚佣金</span>
                    </a>
                <?php endif; ?>

                <div class="vdp-uc-stats">
                    <a href="<?php echo esc_url(vdp_get_user_center_url('downloads')); ?>">
                        <strong><?php echo intval($stats['downloads']); ?></strong>
                        <span>下载</span>
                    </a>
                    <a href="<?php echo esc_url(vdp_get_user_center_url('orders')); ?>">
                        <strong>￥<?php echo number_format($stats['spent'], 0); ?></strong>
                        <span>消费</span>
                    </a>
                    <a href="<?php echo esc_url(vdp_get_user_center_url()); ?>">
                        <strong><i class="fa fa-cog"></i></strong>
                        <span>设置</span>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_guest($cover_style, $instance) {
        $cur = is_ssl() ? 'https://' : 'http://';
        $cur .= ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');

        $signin_url = home_url('/?vdp_auth=signin&redirect=' . urlencode($cur));
        $signup_url = home_url('/?vdp_auth=signup&redirect=' . urlencode($cur));
        $welcome    = !empty($instance['guest_text']) ? $instance['guest_text'] : 'HI！请登录';

        $weixin_enabled = function_exists('vdp_oauth_is_enabled') && vdp_oauth_is_enabled('weixin');
        $weixin_mode = vdp_opt('oauth_weixin_mode', 'mp');
        if ($weixin_enabled && $weixin_mode === 'mp') {
            $weixin_url = home_url('/?vdp_auth=signin&redirect=' . urlencode($cur));
        } elseif ($weixin_enabled) {
            $weixin_url = vdp_oauth_login_url('weixin', $cur);
        } else {
            $weixin_url = $signin_url;
        }
        $weixin_title = $weixin_enabled ? '微信登录' : '微信登录尚未配置，将跳转到账号登录';
        ?>
        <div class="vdp-uc vdp-uc-guest">
            <div class="vdp-uc-cover" style="<?php echo esc_attr($cover_style); ?>"></div>
            <div class="vdp-uc-avatar vdp-uc-avatar-default">
                <i class="fa fa-user"></i>
            </div>
            <div class="vdp-uc-body">
                <div class="vdp-uc-welcome"><?php echo esc_html($welcome); ?></div>

                <div class="vdp-uc-actions">
                    <a href="<?php echo esc_url($signin_url); ?>" class="vdp-uc-btn vdp-uc-btn-signin">
                        <i class="fa fa-sign-in"></i> 登录
                    </a>
                    <?php if (!vdp_is_close_signup()) : ?>
                        <a href="<?php echo esc_url($signup_url); ?>" class="vdp-uc-btn vdp-uc-btn-signup">
                            <i class="fa fa-user-plus"></i> 注册
                        </a>
                    <?php endif; ?>
                </div>

                <div class="vdp-uc-divider"><span>社交账号登录</span></div>
                <div class="vdp-uc-social">
                    <a href="<?php echo esc_url($weixin_url); ?>"
                       class="vdp-uc-social-btn vdp-uc-social-weixin<?php echo $weixin_enabled ? '' : ' vdp-uc-social-disabled'; ?>"
                       title="<?php echo esc_attr($weixin_title); ?>">
                        <i class="fa fa-weixin"></i> 微信登录
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    private function get_stats($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'vdp_orders';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(pay_price),0) AS total
             FROM $table WHERE user_id = %d AND status = 1",
            $user_id
        ));
        return [
            'downloads' => $row ? intval($row->cnt) : 0,
            'spent'     => $row ? floatval($row->total) : 0,
        ];
    }
}

/**
 * VIP 会员小工具
 */
class VDP_Widget_VipPromo extends VDP_Widget {

    public function __construct() {
        parent::__construct(
            'vdp_widget_vip_promo',
            '【文库】会员推广',
            ['description' => '展示会员套餐，吸引开通']
        );
    }

    protected function fields() {
        return [
            'title' => ['type' => 'text', 'label' => '标题', 'default' => '开通会员'],
            'body_subtitle' => ['type' => 'text', 'label' => '卡片副标题', 'default' => '解锁全站资源'],
        ];
    }

    protected function render($args, $instance) {
        if (!VDP_Member::is_enabled()) return;

        $products = VDP_Member::get_products();
        $cheapest = null;
        foreach ($products as $key => $p) {
            if ($cheapest === null || $p['price'] < $cheapest['price']) {
                $cheapest = $p;
            }
        }
        ?>
        <div class="vdp-widget-vip-promo">
            <div class="vdp-vip-subtitle"><?php echo esc_html($instance['body_subtitle']); ?></div>
            <div class="vdp-vip-perks">
                <div>✅ 全站资源免费下</div>
                <div>✅ 高速下载通道</div>
                <div>✅ 专属客服</div>
            </div>
            <?php if ($cheapest) : ?>
                <div class="vdp-vip-from">¥<?php echo number_format($cheapest['price'], 2); ?> 起</div>
            <?php endif; ?>
            <a href="<?php echo esc_url(vdp_get_buy_vip_url()); ?>" class="vdp-vip-btn">立即开通</a>
        </div>
        <?php
    }
}
