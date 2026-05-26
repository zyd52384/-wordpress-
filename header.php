<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header">
    <nav class="navbar navbar-default navbar-fixed-top">
        <div class="container">
            <div class="navbar-header">
                <button type="button" class="navbar-toggle vdp-mobile-drawer-toggle" aria-label="菜单">
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="<?php echo esc_url(home_url('/')); ?>">
                    <?php vdp_render_site_logo(); ?>
                    <?php if ($subtitle = trim((string) vdp_opt('site_subtitle', ''))) : ?>
                        <span class="vdp-site-subtitle"><?php echo esc_html($subtitle); ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <div class="collapse navbar-collapse" id="main-nav">
                <?php
                wp_nav_menu([
                    'theme_location' => 'primary',
                    'container'      => false,
                    'menu_class'     => 'nav navbar-nav',
                    'fallback_cb'    => false,
                ]);
                ?>

                <div class="nav navbar-nav navbar-right">
                    <li class="vdp-search-trigger-wrap">
                        <a href="javascript:;" class="vdp-search-trigger" title="搜索文档" aria-label="搜索">
                            <i class="fa fa-search"></i>
                        </a>
                    </li>
                    <?php if (vdp_opt('enable_dark_mode', true)) : ?>
                        <li class="vdp-theme-toggle-wrap">
                            <a href="javascript:;" class="vdp-theme-toggle" title="切换暗色 / 亮色">
                                <i class="fa fa-moon-o vdp-theme-icon-dark"></i>
                                <i class="fa fa-sun-o vdp-theme-icon-light"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if (is_user_logged_in()) : ?>
                        <li class="dropdown user-menu">
                            <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                                <?php echo get_avatar(get_current_user_id(), 32); ?>
                                <span class="username"><?php echo esc_html(wp_get_current_user()->display_name); ?></span>
                                <?php echo vdp_get_vip_badge(get_current_user_id()); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a href="<?php echo esc_url(vdp_get_user_center_url()); ?>"><i class="fa fa-id-card-o"></i> 用户中心</a></li>
                                <li><a href="<?php echo esc_url(vdp_get_user_center_url('orders')); ?>"><i class="fa fa-list-alt"></i> 我的订单</a></li>
                                <li><a href="<?php echo esc_url(vdp_get_user_center_url('vip')); ?>"><i class="fa fa-diamond"></i> 会员中心</a></li>
                                <?php if (vdp_opt('partner_enabled', false)) : ?>
                                    <li><a href="<?php echo esc_url(vdp_get_user_center_url('partner')); ?>" class="vdp-menu-partner"><i class="fa fa-share-alt"></i> 我要分销 <span class="vdp-menu-tag">赚佣金</span></a></li>
                                <?php endif; ?>
                                <?php if (current_user_can('manage_options')) : ?>
                                    <li class="divider"></li>
                                    <li><a href="<?php echo admin_url(); ?>"><i class="fa fa-cog"></i> 后台管理</a></li>
                                <?php endif; ?>
                                <li class="divider"></li>
                                <li><a href="javascript:;" class="vdp-signout-btn"><i class="fa fa-sign-out"></i> 退出登录</a></li>
                            </ul>
                        </li>
                    <?php else : ?>
                        <li><a href="<?php echo esc_url(home_url('/?vdp_auth=signin')); ?>" class="btn-login">登录</a></li>
                        <?php if (vdp_signup_allowed()) : ?>
                            <li><a href="<?php echo esc_url(home_url('/?vdp_auth=signup')); ?>" class="btn-signup">注册</a></li>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
</header>

<?php
// 吸顶大搜索弹层（参考 jhpt123 main-search）
$_search_cats = get_categories(['hide_empty' => true, 'number' => 8]);
?>
<div class="vdp-search-overlay" id="vdp-search-overlay" aria-hidden="true">
    <div class="vdp-search-overlay-inner">
        <button type="button" class="vdp-search-close" aria-label="关闭"><i class="fa fa-times"></i></button>
        <form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>" class="vdp-search-form">
            <div class="vdp-search-input-wrap">
                <i class="fa fa-search"></i>
                <input type="text" name="s" class="vdp-search-input" placeholder="搜索文档、资料、范文…" autocomplete="off">
                <button type="submit" class="vdp-search-submit">搜索</button>
            </div>
        </form>
        <?php if (!empty($_search_cats)) : ?>
            <div class="vdp-search-hot">
                <span class="vdp-search-hot-label">热门：</span>
                <?php foreach ($_search_cats as $_c) : ?>
                    <a href="<?php echo esc_url(get_category_link($_c)); ?>"><?php echo esc_html($_c->name); ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php // 移动端左侧抽屉 ?>
<div class="vdp-mobile-drawer" id="vdp-mobile-drawer" aria-hidden="true">
    <div class="vdp-drawer-mask"></div>
    <aside class="vdp-drawer-panel">
        <div class="vdp-drawer-user">
            <?php if (is_user_logged_in()) :
                $_u = wp_get_current_user(); ?>
                <a href="<?php echo esc_url(vdp_get_user_center_url()); ?>" class="vdp-drawer-user-link">
                    <?php echo get_avatar(get_current_user_id(), 48); ?>
                    <div class="vdp-drawer-user-meta">
                        <strong><?php echo esc_html($_u->display_name); ?></strong>
                        <span><?php echo vdp_get_vip_badge(get_current_user_id()) ?: '普通用户'; ?></span>
                    </div>
                </a>
            <?php else : ?>
                <div class="vdp-drawer-user-guest">
                    <a href="<?php echo esc_url(home_url('/?vdp_auth=signin')); ?>" class="btn-login">登录</a>
                    <?php if (vdp_signup_allowed()) : ?>
                        <a href="<?php echo esc_url(home_url('/?vdp_auth=signup')); ?>" class="btn-signup">注册</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="vdp-drawer-nav">
            <?php
            wp_nav_menu([
                'theme_location' => 'primary',
                'container'      => false,
                'menu_class'     => 'vdp-drawer-menu',
                'fallback_cb'    => false,
                'depth'          => 2,
            ]);
            ?>
        </div>
    </aside>
</div>

<?php // 移动端底部 Tab ?>
<nav class="vdp-mobile-tabbar" aria-label="移动导航">
    <a href="<?php echo esc_url(home_url('/')); ?>" class="vdp-tabbar-item<?php echo (is_home() || is_front_page()) ? ' active' : ''; ?>">
        <i class="fa fa-home"></i><span>首页</span>
    </a>
    <a href="javascript:;" class="vdp-tabbar-item vdp-tabbar-cats" data-target="#vdp-mobile-drawer">
        <i class="fa fa-th-large"></i><span>分类</span>
    </a>
    <a href="javascript:;" class="vdp-tabbar-item vdp-tabbar-search" data-target="#vdp-search-overlay">
        <i class="fa fa-search"></i><span>搜索</span>
    </a>
    <?php if (vdp_opt('partner_enabled', false)) : ?>
        <a href="<?php echo esc_url(vdp_get_user_center_url('partner')); ?>" class="vdp-tabbar-item vdp-tabbar-partner">
            <i class="fa fa-share-alt"></i><span>分销</span>
        </a>
    <?php endif; ?>
    <a href="<?php echo esc_url(vdp_get_user_center_url()); ?>" class="vdp-tabbar-item">
        <i class="fa fa-user-o"></i><span>我的</span>
    </a>
</nav>

<main class="site-main">
