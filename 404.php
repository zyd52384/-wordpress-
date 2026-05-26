<?php
/**
 * 404 页
 */
get_header();
vdp_page_shell_start(['has_sidebar' => false]);
?>

<div class="error-404 text-center" style="padding:80px 20px;">
    <h1 class="error-code" style="font-size:120px;font-weight:700;color:var(--theme-color, #2196F3);margin:0;">404</h1>
    <p class="error-message" style="font-size:24px;margin:20px 0 8px;">页面未找到</p>
    <p style="color:#999;margin-bottom:32px;">您访问的页面不存在或已被删除</p>
    <a href="<?php echo esc_url(home_url('/')); ?>" class="vdp-btn-primary">返回首页</a>
</div>

<?php
vdp_page_shell_end();
get_footer();
