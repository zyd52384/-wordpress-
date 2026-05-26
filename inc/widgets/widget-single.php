<?php
/**
 * 文章页专用小工具
 *   - VDP_Widget_RelatedDocs     相关推荐（多展示方式）
 *   - VDP_Widget_AdjacentPosts   上一篇 / 下一篇导航
 */

if (!defined('ABSPATH')) exit;

/**
 * 相关推荐：仅在文章详情页显示，支持多种展示方式
 */
class VDP_Widget_RelatedDocs extends VDP_Widget {

    public function __construct() {
        parent::__construct(
            'vdp_widget_related_docs',
            '【文库】相关推荐',
            ['description' => '文章详情页相关推荐，支持网格/列表/单行/封面卡多种样式']
        );
    }

    protected function fields() {
        return [
            'title' => ['type' => 'text', 'label' => '标题', 'default' => '相关推荐'],
            'style' => [
                'type'    => 'select',
                'label'   => '展示方式',
                'default' => 'grid',
                'options' => [
                    'grid'  => '网格大图（2-4 列封面）',
                    'list'  => '列表（缩略图 + 标题 + 摘要）',
                    'oneline' => '单行紧凑（标题 + 元信息）',
                    'card'  => '横向卡片（左封面右内容）',
                ],
            ],
            'cols' => [
                'type'    => 'select',
                'label'   => '网格列数（仅"网格大图"生效）',
                'default' => '3',
                'options' => ['2' => '2 列', '3' => '3 列', '4' => '4 列'],
            ],
            'count' => [
                'type'    => 'number',
                'label'   => '显示数量',
                'default' => 6,
            ],
            'source' => [
                'type'    => 'select',
                'label'   => '相关来源',
                'default' => 'category',
                'options' => [
                    'category' => '同分类',
                    'tag'      => '同标签',
                    'author'   => '同作者',
                    'random'   => '随机',
                ],
            ],
            'order_by' => [
                'type'    => 'select',
                'label'   => '排序',
                'default' => 'date',
                'options' => [
                    'date'      => '最新发布',
                    'downloads' => '下载最多',
                    'views'     => '浏览最多',
                    'comment'   => '评论最多',
                    'rand'      => '随机',
                ],
            ],
        ];
    }

    public function widget($args, $instance) {
        // 仅在文章详情页显示
        if (!is_singular('post')) return;
        parent::widget($args, $instance);
    }

    protected function render($args, $instance) {
        $post_id = get_the_ID();
        if (!$post_id) return;

        $count    = max(1, (int) $instance['count']);
        $source   = $instance['source'] ?: 'category';
        $order_by = $instance['order_by'] ?: 'date';
        $style    = $instance['style'] ?: 'grid';
        $cols     = max(2, min(4, (int) $instance['cols'] ?: 3));

        $q_args = [
            'post_type'           => 'post',
            'posts_per_page'      => $count,
            'post__not_in'        => [$post_id],
            'no_found_rows'       => true,
            'ignore_sticky_posts' => 1,
        ];

        switch ($source) {
            case 'tag':
                $tags = wp_get_post_tags($post_id, ['fields' => 'ids']);
                if (empty($tags)) return;
                $q_args['tag__in'] = $tags;
                break;
            case 'author':
                $q_args['author'] = (int) get_post_field('post_author', $post_id);
                break;
            case 'random':
                // 不加分类筛选
                break;
            case 'category':
            default:
                $cats = wp_get_post_categories($post_id);
                if (empty($cats)) return;
                $q_args['category__in'] = $cats;
                break;
        }

        switch ($order_by) {
            case 'downloads':
                $q_args['meta_key'] = 'vdp_downloads';
                $q_args['orderby']  = 'meta_value_num';
                $q_args['order']    = 'DESC';
                break;
            case 'views':
                $q_args['meta_key'] = 'views';
                $q_args['orderby']  = 'meta_value_num';
                $q_args['order']    = 'DESC';
                break;
            case 'comment':
                $q_args['orderby'] = 'comment_count';
                $q_args['order']   = 'DESC';
                break;
            case 'rand':
                $q_args['orderby'] = 'rand';
                break;
            case 'date':
            default:
                $q_args['orderby'] = 'date';
                $q_args['order']   = 'DESC';
        }

        $q = new WP_Query($q_args);
        if (!$q->have_posts()) {
            wp_reset_postdata();
            return;
        }

        echo '<div class="vdp-related vdp-related-' . esc_attr($style) . '">';

        if ($style === 'grid') {
            echo '<div class="posts-grid posts-grid-cols-' . $cols . '">';
            while ($q->have_posts()) {
                $q->the_post();
                get_template_part('template/content', 'doc');
            }
            echo '</div>';
        } elseif ($style === 'list') {
            echo '<ul class="vdp-related-list">';
            while ($q->have_posts()) {
                $q->the_post();
                $this->render_list_item();
            }
            echo '</ul>';
        } elseif ($style === 'oneline') {
            echo '<ul class="vdp-related-oneline">';
            while ($q->have_posts()) {
                $q->the_post();
                $this->render_oneline_item();
            }
            echo '</ul>';
        } elseif ($style === 'card') {
            echo '<div class="vdp-related-cards">';
            while ($q->have_posts()) {
                $q->the_post();
                $this->render_card_item();
            }
            echo '</div>';
        }

        echo '</div>';
        wp_reset_postdata();
    }

    private function render_list_item() {
        $doc   = vdp_get_doc_file_info();
        $thumb = vdp_get_doc_thumbnail(get_the_ID(), 1, 'medium');
        ?>
        <li class="vdp-related-list-item">
            <a href="<?php the_permalink(); ?>" class="vdp-rl-thumb">
                <?php if ($thumb) : ?>
                    <img src="<?php echo esc_url($thumb); ?>" loading="lazy" alt="<?php the_title_attribute(); ?>">
                <?php else : ?>
                    <span class="vdp-rl-thumb-fallback"><i class="fa fa-file-text-o"></i></span>
                <?php endif; ?>
            </a>
            <div class="vdp-rl-info">
                <h4 class="vdp-rl-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h4>
                <?php if (has_excerpt()) : ?>
                    <div class="vdp-rl-excerpt"><?php echo esc_html(wp_trim_words(get_the_excerpt(), 28, '…')); ?></div>
                <?php endif; ?>
                <div class="vdp-rl-meta">
                    <?php if (!empty($doc['ext'])) : ?>
                        <?php echo vdp_get_format_badge($doc['ext']); ?>
                    <?php endif; ?>
                    <span><i class="fa fa-download"></i> <?php echo intval($doc['downloads']); ?></span>
                    <span><i class="fa fa-clock-o"></i> <?php echo get_the_date('Y-m-d'); ?></span>
                </div>
            </div>
        </li>
        <?php
    }

    private function render_oneline_item() {
        $doc = vdp_get_doc_file_info();
        ?>
        <li class="vdp-related-oneline-item">
            <a href="<?php the_permalink(); ?>" class="vdp-ro-title"><?php the_title(); ?></a>
            <span class="vdp-ro-meta">
                <?php if (!empty($doc['ext'])) : ?>
                    <em><?php echo esc_html(strtoupper($doc['ext'])); ?></em>
                <?php endif; ?>
                <i class="fa fa-download"></i> <?php echo intval($doc['downloads']); ?>
                <i class="fa fa-clock-o"></i> <?php echo get_the_date('m-d'); ?>
            </span>
        </li>
        <?php
    }

    private function render_card_item() {
        $doc   = vdp_get_doc_file_info();
        $thumb = vdp_get_doc_thumbnail(get_the_ID(), 1, 'medium');
        ?>
        <a href="<?php the_permalink(); ?>" class="vdp-related-card">
            <div class="vdp-rc-thumb">
                <?php if ($thumb) : ?>
                    <img src="<?php echo esc_url($thumb); ?>" loading="lazy" alt="<?php the_title_attribute(); ?>">
                <?php else : ?>
                    <span class="vdp-rc-thumb-fallback"><i class="fa fa-file-text-o"></i></span>
                <?php endif; ?>
                <?php if (!empty($doc['ext'])) : ?>
                    <span class="vdp-rc-ext"><?php echo esc_html(strtoupper($doc['ext'])); ?></span>
                <?php endif; ?>
            </div>
            <div class="vdp-rc-info">
                <h4 class="vdp-rc-title"><?php the_title(); ?></h4>
                <div class="vdp-rc-meta">
                    <span><i class="fa fa-download"></i> <?php echo intval($doc['downloads']); ?></span>
                    <?php if (!empty($doc['size'])) : ?>
                        <span><?php echo vdp_format_file_size($doc['size']); ?></span>
                    <?php endif; ?>
                    <span><i class="fa fa-clock-o"></i> <?php echo get_the_date('m-d'); ?></span>
                </div>
            </div>
        </a>
        <?php
    }
}

/**
 * 上一篇 / 下一篇导航
 */
class VDP_Widget_AdjacentPosts extends VDP_Widget {

    public function __construct() {
        parent::__construct(
            'vdp_widget_adjacent_posts',
            '【文库】上一篇/下一篇',
            ['description' => '文章页底部「上一篇 / 下一篇」导航']
        );
    }

    protected function fields() {
        return [
            'title' => ['type' => 'text', 'label' => '标题（建议留空）', 'default' => ''],
            'in_same_term' => [
                'type'    => 'checkbox',
                'label'   => '仅在同分类下查找上下篇',
                'default' => 1,
            ],
            'show_thumb' => [
                'type'    => 'checkbox',
                'label'   => '显示缩略图',
                'default' => 1,
            ],
            'prev_label' => ['type' => 'text', 'label' => '上一篇文案', 'default' => '上一篇'],
            'next_label' => ['type' => 'text', 'label' => '下一篇文案', 'default' => '下一篇'],
        ];
    }

    public function widget($args, $instance) {
        if (!is_singular('post')) return;
        parent::widget($args, $instance);
    }

    protected function render($args, $instance) {
        $in_same = !empty($instance['in_same_term']);
        $show_thumb = !empty($instance['show_thumb']);
        $prev_label = $instance['prev_label'] ?: '上一篇';
        $next_label = $instance['next_label'] ?: '下一篇';

        $prev = get_previous_post($in_same);
        $next = get_next_post($in_same);

        if (!$prev && !$next) return;
        ?>
        <div class="vdp-adjacent">
            <?php $this->render_side($prev, 'prev', $prev_label, $show_thumb); ?>
            <?php $this->render_side($next, 'next', $next_label, $show_thumb); ?>
        </div>
        <?php
    }

    private function render_side($post, $side, $label, $show_thumb) {
        if (!$post) {
            echo '<div class="vdp-adj vdp-adj-' . esc_attr($side) . ' vdp-adj-empty">'
               . '<span class="vdp-adj-label">' . esc_html($label) . '</span>'
               . '<span class="vdp-adj-title">没有了</span></div>';
            return;
        }

        $thumb = $show_thumb ? vdp_get_doc_thumbnail($post->ID, 1, 'medium') : '';
        $arrow = $side === 'prev'
            ? '<i class="fa fa-angle-left"></i>'
            : '<i class="fa fa-angle-right"></i>';
        ?>
        <a href="<?php echo esc_url(get_permalink($post)); ?>" class="vdp-adj vdp-adj-<?php echo esc_attr($side); ?>">
            <?php if ($thumb) : ?>
                <span class="vdp-adj-thumb"><img src="<?php echo esc_url($thumb); ?>" loading="lazy" alt=""></span>
            <?php endif; ?>
            <span class="vdp-adj-text">
                <span class="vdp-adj-label">
                    <?php if ($side === 'prev') echo $arrow; ?>
                    <?php echo esc_html($label); ?>
                    <?php if ($side === 'next') echo $arrow; ?>
                </span>
                <span class="vdp-adj-title"><?php echo esc_html(get_the_title($post)); ?></span>
            </span>
        </a>
        <?php
    }
}
