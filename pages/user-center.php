<?php
/**
 * 用户中心页面模板
 * 借鉴子比主题的左菜单 + 右内容布局
 */

if (!defined('ABSPATH')) exit;

// 未登录跳转登录页
if (!is_user_logged_in()) {
    $auth_url = home_url('/?vdp_auth=signin&redirect=' . urlencode(vdp_get_user_center_url()));
    wp_safe_redirect($auth_url);
    exit;
}

$current_user_id = get_current_user_id();
$current_user    = wp_get_current_user();
$type            = get_query_var('user_center');
if ($type === '1' || !$type) $type = '';

$menus = vdp_get_user_center_menus();

get_header(); ?>

<?php if (is_active_sidebar('user_top_fluid')) : ?>
    <div class="container fluid-widget vdp-fluid-top">
        <?php dynamic_sidebar('user_top_fluid'); ?>
    </div>
<?php endif; ?>

<div class="vdp-user-center container" style="margin-top:30px;margin-bottom:30px;">
    <div class="row">
        <!-- 左侧菜单 -->
        <div class="col-md-3">
            <?php if (is_active_sidebar('user_sidebar_top')) dynamic_sidebar('user_sidebar_top'); ?>

            <div class="vdp-user-card">
                <div class="vdp-user-avatar-wrap">
                    <?php echo get_avatar($current_user_id, 80, '', '', ['class' => 'vdp-user-avatar']); ?>
                </div>
                <div class="vdp-user-name"><?php echo esc_html($current_user->display_name); ?></div>
                <div class="vdp-user-vip">
                    <?php
                    $badge = vdp_get_vip_badge($current_user_id);
                    if ($badge) {
                        echo $badge;
                        $expires = vdp_get_user_vip_expires($current_user_id);
                        echo '<span class="vdp-vip-expires">到期：' . esc_html($expires) . '</span>';
                    } else {
                        echo '<a href="' . esc_url(vdp_get_buy_vip_url()) . '" class="vdp-btn-vip">开通会员</a>';
                    }
                    ?>
                </div>
            </div>

            <ul class="vdp-user-menu">
                <?php foreach ($menus as $key => $menu) : ?>
                    <li class="<?php echo $type === $key ? 'active' : ''; ?>">
                        <a href="<?php echo esc_url(vdp_get_user_center_url($key)); ?>">
                            <span class="dashicons <?php echo esc_attr($menu['icon']); ?>"></span>
                            <?php echo esc_html($menu['name']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                <li>
                    <a href="javascript:;" class="vdp-signout-btn">
                        <span class="dashicons dashicons-exit"></span>退出登录
                    </a>
                </li>
            </ul>

            <?php if (is_active_sidebar('user_sidebar_bottom')) dynamic_sidebar('user_sidebar_bottom'); ?>
        </div>

        <!-- 右侧内容 -->
        <div class="col-md-9">
            <div class="vdp-user-content">
                <?php
                $tab_file = get_template_directory() . '/pages/user-tabs/' . ($type ?: 'profile') . '.php';
                if (file_exists($tab_file)) {
                    include $tab_file;
                } else {
                    include get_template_directory() . '/pages/user-tabs/profile.php';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<?php if (is_active_sidebar('user_bottom_fluid')) : ?>
    <div class="container fluid-widget vdp-fluid-bottom">
        <?php dynamic_sidebar('user_bottom_fluid'); ?>
    </div>
<?php endif; ?>

<?php get_footer(); ?>
