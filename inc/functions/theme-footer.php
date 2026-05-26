<?php
/**
 * 页脚渲染辅助
 * - 接入 CSF 字段：footer_copyright / footer_icp / footer_police
 */

if (!defined('ABSPATH')) exit;

/**
 * 输出页脚版权 / 备案信息
 */
function vdp_render_footer_meta() {
    $copyright = trim((string) vdp_opt('footer_copyright', '© ' . date('Y') . ' ' . get_bloginfo('name')));
    $icp       = trim((string) vdp_opt('footer_icp', ''));
    $police    = trim((string) vdp_opt('footer_police', ''));
    ?>
    <div class="vdp-footer-meta">
        <?php if ($copyright !== '') : ?>
            <p class="copyright"><?php echo wp_kses_post($copyright); ?></p>
        <?php endif; ?>

        <?php if ($icp !== '' || $police !== '') : ?>
            <p class="beian">
                <?php if ($icp !== '') : ?>
                    <a href="https://beian.miit.gov.cn/" target="_blank" rel="nofollow noopener"><?php echo esc_html($icp); ?></a>
                <?php endif; ?>
                <?php if ($icp !== '' && $police !== '') echo '<span class="sep"> | </span>'; ?>
                <?php if ($police !== '') : ?>
                    <a href="https://beian.mps.gov.cn/" target="_blank" rel="nofollow noopener">
                        <i class="fa fa-shield"></i> <?php echo esc_html($police); ?>
                    </a>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
    <?php
}
