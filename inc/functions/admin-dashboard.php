<?php
/**
 * 后台仪表盘页面
 */

if (!defined('ABSPATH')) exit;

/**
 * 渲染仪表盘页面
 */
function vdp_render_dashboard_page() {
    $cos = vdp_cos();
    $configured = $cos->is_configured();
    $doc_count = vdp_get_doc_post_count();
    ?>
    <div class="wrap vdp-dashboard">
        <h1>虚拟资料自动赚钱机</h1>
        <p class="vdp-subtitle">基于腾讯云 COS + 数据万象的虚拟文档文库系统</p>

        <div class="vdp-stats">
            <div class="vdp-stat-card">
                <div class="vdp-stat-number"><?php echo $doc_count; ?></div>
                <div class="vdp-stat-label">文档总数</div>
            </div>
            <div class="vdp-stat-card">
                <div class="vdp-stat-number"><?php echo $configured ? '✅' : '❌'; ?></div>
                <div class="vdp-stat-label">COS 配置状态</div>
            </div>
        </div>

        <div class="vdp-quick-actions">
            <h2>快速操作</h2>
            <a href="<?php echo admin_url('admin.php?page=vdp-upload'); ?>" class="button button-primary button-hero">
                批量上传文档
            </a>
            <a href="<?php echo admin_url('themes.php?page=vdp_theme_options'); ?>" class="button button-hero">
                主题设置
            </a>
        </div>

        <div class="vdp-setup-checklist">
            <h2>使用步骤</h2>
            <ol>
                <li class="<?php echo $configured ? 'vdp-done' : ''; ?>">
                    在主题设置中配置腾讯云 COS 参数
                </li>
                <li>
                    在 WordPress 后台创建「文库资料」分类（或使用现有分类）
                </li>
                <li>
                    到批量上传页面，选择文件开始上传
                </li>
            </ol>
        </div>
    </div>
    <?php
}

/**
 * 获取文库文章数量
 */
function vdp_get_doc_post_count() {
    global $wpdb;
    return (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->postmeta}
         WHERE meta_key = 'posts_zibpay'
         AND meta_value LIKE '%vdp_file_name%'"
    );
}
