<?php
/**
 * 后台「已发布管理」页面
 *
 * 列出所有已上传的文档类文章，附带：
 *   - 文件大小 / 价格 / 存储引擎 / 预览状态 / AI 摘要状态
 *   - 行内操作：重新生成预览图、重新生成 AI 摘要、编辑、查看
 *
 * 数据来源：posts_zibpay 数组 meta（含 vdp_file_name 字段视为文档）
 */

if (!defined('ABSPATH')) exit;

/**
 * 渲染已发布管理页面
 */
function vdp_render_posts_page() {
    $paged = max(1, isset($_GET['paged']) ? (int) $_GET['paged'] : 1);
    $per   = 20;
    $kw    = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
    $cat   = isset($_GET['cat']) ? (int) $_GET['cat'] : 0;
    $eng   = isset($_GET['engine']) ? sanitize_text_field($_GET['engine']) : '';

    $args = [
        'post_type'      => 'post',
        'post_status'    => ['publish', 'draft', 'private'],
        'posts_per_page' => $per,
        'paged'          => $paged,
        'meta_query'     => [
            [
                'key'     => 'posts_zibpay',
                'value'   => 'vdp_file_name',
                'compare' => 'LIKE',
            ],
        ],
    ];
    if ($kw)  $args['s']        = $kw;
    if ($cat) $args['cat']      = $cat;
    if ($eng) {
        $args['meta_query'][] = [
            'key'   => '_vdp_storage',
            'value' => $eng,
        ];
    }

    $q = new WP_Query($args);
    $total = (int) $q->found_posts;
    $pages = (int) $q->max_num_pages;
    $categories = get_categories(['hide_empty' => false]);
    ?>
    <div class="wrap vdp-posts-page">
        <h1 class="wp-heading-inline">已发布管理</h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=vdp-upload')); ?>" class="page-title-action">批量上传</a>
        <hr class="wp-header-end">

        <form method="get" class="vdp-posts-filter">
            <input type="hidden" name="page" value="vdp-posts">
            <input type="search" name="s" value="<?php echo esc_attr($kw); ?>" placeholder="搜索文档标题" class="regular-text">
            <select name="cat">
                <option value="0">— 全部分类 —</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?php echo (int) $c->term_id; ?>" <?php selected($cat, $c->term_id); ?>>
                        <?php echo esc_html($c->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="engine">
                <option value="">— 全部存储 —</option>
                <option value="cos" <?php selected($eng, 'cos'); ?>>腾讯云 COS</option>
                <option value="pan123" <?php selected($eng, 'pan123'); ?>>123 网盘</option>
            </select>
            <button class="button">筛选</button>
            <span class="vdp-posts-total">共 <?php echo $total; ?> 条</span>
        </form>

        <table class="wp-list-table widefat fixed striped vdp-posts-table">
            <thead>
                <tr>
                    <th class="col-title">标题</th>
                    <th class="col-cat">分类</th>
                    <th class="col-size">文件</th>
                    <th class="col-price">价格</th>
                    <th class="col-engine">存储</th>
                    <th class="col-preview">预览图</th>
                    <th class="col-summary">AI 摘要</th>
                    <th class="col-actions">操作</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($q->have_posts()): while ($q->have_posts()): $q->the_post(); $pid = get_the_ID(); ?>
                <?php
                $info     = vdp_get_doc_file_info($pid);
                $engine   = get_post_meta($pid, '_vdp_storage', true) ?: ($info['cos_key'] ? 'cos' : '');
                $p_status = get_post_meta($pid, '_vdp_preview_status', true) ?: 'pending';
                $s_status = get_post_meta($pid, '_vdp_ai_summary_status', true) ?: '';
                $cat_list = get_the_category($pid);
                $cat_text = !empty($cat_list) ? esc_html($cat_list[0]->name) : '—';
                ?>
                <tr data-post-id="<?php echo $pid; ?>">
                    <td>
                        <strong><a href="<?php echo esc_url(get_edit_post_link($pid)); ?>"><?php echo esc_html(get_the_title()); ?></a></strong>
                        <div class="row-actions">
                            <span><a href="<?php echo esc_url(get_permalink($pid)); ?>" target="_blank">查看</a> | </span>
                            <span><a href="<?php echo esc_url(get_edit_post_link($pid)); ?>">编辑</a></span>
                        </div>
                    </td>
                    <td><?php echo $cat_text; ?></td>
                    <td>
                        <span class="vdp-file-ext"><?php echo esc_html(strtoupper($info['ext'])); ?></span>
                        <span class="vdp-file-size"><?php echo $info['size'] ? esc_html(vdp_format_file_size($info['size'])) : '—'; ?></span>
                    </td>
                    <td>
                        <?php if ($info['is_free']): ?>
                            <span class="vdp-tag vdp-tag-free">免费</span>
                        <?php else: ?>
                            <span class="vdp-tag vdp-tag-paid">¥<?php echo number_format($info['price'], 2); ?></span>
                        <?php endif; ?>
                        <?php if ($info['vip_limit']): ?>
                            <span class="vdp-tag vdp-tag-vip">VIP<?php echo (int) $info['vip_limit']; ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo vdp_admin_engine_badge($engine); ?></td>
                    <td><?php echo vdp_admin_status_badge($p_status, 'preview'); ?></td>
                    <td><?php echo vdp_admin_status_badge($s_status, 'summary'); ?></td>
                    <td class="col-actions">
                        <button class="button button-small vdp-regen-preview" data-id="<?php echo $pid; ?>">重生预览</button>
                        <button class="button button-small vdp-regen-summary" data-id="<?php echo $pid; ?>">重生摘要</button>
                    </td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="8" class="vdp-empty">暂无已上传文档</td></tr>
            <?php endif; wp_reset_postdata(); ?>
            </tbody>
        </table>

        <?php if ($pages > 1): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                echo paginate_links([
                    'base'      => add_query_arg('paged', '%#%'),
                    'format'    => '',
                    'prev_text' => '‹',
                    'next_text' => '›',
                    'total'     => $pages,
                    'current'   => $paged,
                ]);
                ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * 状态徽章
 */
function vdp_admin_status_badge($status, $type = 'preview') {
    $map_preview = [
        'done'        => ['完成', 'green'],
        'pending'     => ['待生成', 'gray'],
        'failed'      => ['失败', 'red'],
        'unsupported' => ['不支持', 'gray'],
        'skipped'     => ['已跳过', 'gray'],
    ];
    $map_summary = [
        'done'    => ['完成', 'green'],
        'pending' => ['处理中', 'orange'],
        'failed'  => ['失败', 'red'],
        ''        => ['未生成', 'gray'],
    ];
    $map = $type === 'preview' ? $map_preview : $map_summary;
    if (!isset($map[$status])) {
        return '<span class="vdp-badge vdp-badge-gray">' . esc_html($status ?: '—') . '</span>';
    }
    list($label, $color) = $map[$status];
    return '<span class="vdp-badge vdp-badge-' . $color . '">' . esc_html($label) . '</span>';
}

/**
 * 存储引擎徽章
 */
function vdp_admin_engine_badge($engine) {
    if (!$engine) return '<span class="vdp-badge vdp-badge-gray">—</span>';
    $map = [
        'cos'    => ['COS', 'blue'],
        'pan123' => ['123', 'purple'],
    ];
    if (!isset($map[$engine])) {
        return '<span class="vdp-badge vdp-badge-gray">' . esc_html($engine) . '</span>';
    }
    list($label, $color) = $map[$engine];
    return '<span class="vdp-badge vdp-badge-' . $color . '">' . $label . '</span>';
}
