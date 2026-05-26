<?php
/**
 * 后台 - 社交登录设置（已迁移到主题选项面板）
 */

if (!defined('ABSPATH')) exit;

function vdp_render_oauth_page() {
    $csf_url      = admin_url('admin.php?page=vdp-theme-options');
    $cfg          = vdp_oauth_config('weixin');
    $is_enabled   = vdp_oauth_is_enabled('weixin');
    $mode         = vdp_opt('oauth_weixin_mode', 'mp');
    $mode_label   = $mode === 'mp' ? '公众号模式' : '开放平台模式';
    ?>
    <div class="wrap">
        <h1>社交登录设置</h1>

        <div class="notice notice-info">
            <p><strong>配置位置已统一迁移：</strong></p>
            <p>微信登录参数请前往
                <a href="<?php echo esc_url($csf_url); ?>"><strong>主题选项 → 社交登录</strong></a> 配置。</p>
        </div>

        <h2 class="title">当前状态</h2>
        <table class="form-table">
            <tr>
                <th scope="row">微信登录</th>
                <td>
                    <?php if ($is_enabled): ?>
                        <span style="color:#46b450;font-weight:600;">● 已启用（<?php echo esc_html($mode_label); ?>）</span>
                    <?php elseif (!empty($cfg['enabled'])): ?>
                        <span style="color:#dc3232;font-weight:600;">● 已开启但缺少 AppID / AppSecret</span>
                    <?php else: ?>
                        <span style="color:#888;">○ 未启用</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row">AppID</th>
                <td><code><?php echo $cfg['appid'] ? esc_html($cfg['appid']) : '<em style="color:#888;">未填写</em>'; ?></code></td>
            </tr>
            <?php if ($mode === 'mp') : ?>
            <tr>
                <th scope="row">网页授权域名</th>
                <td>
                    <code><?php echo esc_html(parse_url(home_url(), PHP_URL_HOST)); ?></code>
                    <p class="description">填入公众号后台「公众号设置 → 功能设置 → 网页授权域名」</p>
                </td>
            </tr>
            <?php else : ?>
            <tr>
                <th scope="row">回调域名</th>
                <td>
                    <code><?php echo esc_html(parse_url(home_url(), PHP_URL_HOST)); ?></code>
                    <p class="description">填入微信开放平台「授权回调域」</p>
                </td>
            </tr>
            <?php endif; ?>
        </table>

        <p class="submit">
            <a href="<?php echo esc_url($csf_url); ?>" class="button button-primary">前往主题选项配置</a>
        </p>
    </div>
    <?php
}
