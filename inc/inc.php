<?php
/**
 * 模块加载器 - 按顺序加载所有主题模块
 */

if (!defined('ABSPATH')) exit;

function vdp_require($file) {
    $path = VDP_THEME_INC . '/' . $file;
    if (file_exists($path)) {
        require_once $path;
    }
}

// 1. 基础工具函数
vdp_require('dependent.php');

// 2. Codestar Framework（主题选项框架）
vdp_require('codestar-framework/codestar-framework.php');

// 3. 主题选项
vdp_require('options/options.php');

// 4. 小工具系统
vdp_require('widgets/widget-class.php');
vdp_require('widgets/widget-index.php');

// 5. 功能模块
vdp_require('functions/theme-setup.php');
vdp_require('functions/page-shell.php');
vdp_require('functions/theme-enqueue.php');
vdp_require('functions/theme-head.php');
vdp_require('functions/theme-header.php');
vdp_require('functions/theme-footer.php');
vdp_require('functions/posts-list.php');
vdp_require('functions/doc-single.php');
vdp_require('functions/category.php');
vdp_require('functions/search.php');
vdp_require('functions/svg-icons.php');
vdp_require('functions/fake-stats.php');

// 5.1 后台管理模块
vdp_require('functions/admin-menu.php');
vdp_require('functions/admin-dashboard.php');
vdp_require('functions/admin-upload.php');
vdp_require('functions/admin-posts.php');
vdp_require('functions/admin-members.php');
vdp_require('functions/admin-oauth.php');
vdp_require('functions/admin-contact.php');
vdp_require('functions/admin-page-widgets.php');

// 6. 用户系统
vdp_require('user/user.php');
vdp_require('user/user-vip.php');
vdp_require('user/user-profile.php');

// 7. OAuth 社交登录
vdp_require('oauth/oauth.php');
vdp_require('oauth/weixin.php');
vdp_require('oauth/weixin-mp.php');

// 8. 支付系统
vdp_require('pay/pay.php');
vdp_require('pay/orders.php');

// 9. 腾讯云 COS
vdp_require('cos/cos.php');

// 9.1 123 网盘开放平台
vdp_require('pan123/pan123.php');
vdp_require('pan123/preview-builder.php');

// 9.2 存储统一抽象层（依赖 cos / pan123 已加载）
vdp_require('storage/storage.php');

// 9.3 AI 摘要（DeepSeek）
vdp_require('ai/deepseek.php');

// 10. 会员系统
vdp_require('member/member.php');

// 10.1 合伙人分销
vdp_require('partner/partner.php');

// 11. AJAX 处理
vdp_require('action/ajax.php');
vdp_require('action/upload.php');
vdp_require('action/sign-register.php');
vdp_require('action/payment.php');

do_action('vdp_theme_loaded');
