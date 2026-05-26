<?php
/**
 * 文章展示类小工具（复刻子比典型版式）
 *   - VDP_Widget_DocsSlider     文档轮播 Banner（基于 Bootstrap 3 carousel）
 *   - VDP_Widget_DocsGrid       文档网格大图（2/3/4 列）
 *   - VDP_Widget_CategoryTabs   多分类 TAB 切换列表
 *   - VDP_Widget_FocusList      焦点图 + 文档列表（子比经典左大图右列表）
 */

if (!defined('ABSPATH')) exit;

/**
 * 公共：根据排序参数构造 WP_Query args
 */
function vdp_widget_build_query($order_by, $count, $cat_slug = '', $extra = []) {
    $args = [
        'post_type'      => 'post',
        'posts_per_page' => max(1, (int) $count),
        'no_found_rows'  => true,
        'ignore_sticky_posts' => 1,
    ];
    switch ($order_by) {
        case 'downloads':
            $args['meta_key'] = 'vdp_downloads';
            $args['orderby']  = 'meta_value_num';
            $args['order']    = 'DESC';
            break;
        case 'views':
            $args['meta_key'] = 'views';
            $args['orderby']  = 'meta_value_num';
            $args['order']    = 'DESC';
            break;
        case 'comment':
            $args['orderby'] = 'comment_count';
            $args['order']   = 'DESC';
            break;
        case 'rand':
            $args['orderby'] = 'rand';
            break;
        default:
            $args['orderby'] = 'date';
            $args['order']   = 'DESC';
    }
    if (!empty($cat_slug)) {
        $args['category_name'] = $cat_slug;
    }
    return array_merge($args, $extra);
}

/* ============ 1. 文档轮播 Banner ============ */
class VDP_Widget_DocsSlider extends VDP_Widget {

    public function __construct() {
        parent::__construct(
            'vdp_widget_docs_slider',
            '【文库】文档轮播 Banner',
            ['description' => '大图轮播，可指定文章 ID 或按分类自动取最新']
        );
    }

    protected function fields() {
        return [
            'title'       => ['type' => 'text',     'label' => '标题', 'default' => ''],
            'mode'        => [
                'type' => 'select', 'label' => '取数方式', 'default' => 'auto',
                'options' => [
                    'auto'   => '按分类自动取最新',
                    'manual' => '手动指定文章 ID',
                ],
            ],
            'category'    => ['type' => 'text',     'label' => '分类 slug（仅自动模式）', 'default' => ''],
            'count'       => ['type' => 'number',   'label' => '数量（仅自动模式）',     'default' => 5],
            'manual_ids'  => ['type' => 'textarea', 'label' => '文章 ID（仅手动模式，每行一个或逗号分隔）', 'default' => ''],
            'height'      => ['type' => 'number',   'label' => '图片高度 px（0=自适应）', 'default' => 200],
            'interval'    => ['type' => 'number',   'label' => '自动播放间隔（毫秒，0=不自动）', 'default' => 4000],
            'effect'      => [
                'type' => 'select', 'label' => '切换动画', 'default' => 'slide',
                'options' => [
                    'slide' => '滑动',
                    'fade'  => '淡入淡出',
                ],
            ],
            'pause_on_hover' => [
                'type' => 'checkbox', 'label' => '悬停暂停',
                'default' => 1,
            ],
            'show_pagination' => [
                'type' => 'checkbox', 'label' => '显示底部小圆点',
                'default' => 1,
            ],
            'show_arrows' => [
                'type' => 'checkbox', 'label' => '显示左右箭头',
                'default' => 1,
            ],
        ];
    }

    protected function render($args, $instance) {
        $posts = $this->fetch_posts($instance);
        if (empty($posts)) {
            echo '<p class="vdp-widget-empty">暂无内容</p>';
            return;
        }

        // 按需加载 Swiper
        wp_enqueue_style('swiper');
        wp_enqueue_script('swiper');

        $cid = 'vdp-slider-' . $args['widget_id'];
        $h   = max(0, (int) $instance['height']);
        $interval = max(0, (int) $instance['interval']);
        $img_style = $h > 0 ? ' style="height:' . $h . 'px;object-fit:cover;"' : '';

        $config = [
            'autoplay'       => $interval > 0 ? $interval : 0,
            'pauseOnHover'   => !empty($instance['pause_on_hover']) ? 1 : 0,
            'effect'         => $instance['effect'] === 'fade' ? 'fade' : 'slide',
            'showPagination' => !empty($instance['show_pagination']) ? 1 : 0,
            'showArrows'     => !empty($instance['show_arrows']) ? 1 : 0,
        ];
        ?>
        <div class="vdp-slider-wrap">
            <div id="<?php echo esc_attr($cid); ?>" class="swiper vdp-slider"
                 data-config='<?php echo esc_attr(wp_json_encode($config)); ?>'>
                <div class="swiper-wrapper">
                    <?php foreach ($posts as $p) :
                        $thumb = vdp_get_doc_thumbnail($p->ID, 1, 'medium');
                    ?>
                        <div class="swiper-slide">
                            <a href="<?php echo esc_url(get_permalink($p->ID)); ?>" class="vdp-slider-link">
                                <?php if ($thumb) : ?>
                                    <img src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr(get_the_title($p->ID)); ?>"<?php echo $img_style; ?>>
                                <?php else : ?>
                                    <div class="vdp-slider-placeholder"<?php echo $img_style; ?>>
                                        <i class="fa fa-file-text-o"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="vdp-slider-caption">
                                    <?php echo esc_html(get_the_title($p->ID)); ?>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($config['showPagination']) : ?>
                    <div class="swiper-pagination"></div>
                <?php endif; ?>
                <?php if ($config['showArrows']) : ?>
                    <div class="swiper-button-prev vdp-slider-arrow"></div>
                    <div class="swiper-button-next vdp-slider-arrow"></div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function fetch_posts($instance) {
        if ($instance['mode'] === 'manual' && !empty($instance['manual_ids'])) {
            $ids = preg_split('/[\s,，]+/', $instance['manual_ids']);
            $ids = array_filter(array_map('intval', $ids));
            if (empty($ids)) return [];
            $q = new WP_Query([
                'post_type' => 'post',
                'post__in'  => $ids,
                'orderby'   => 'post__in',
                'posts_per_page' => count($ids),
                'no_found_rows' => true,
            ]);
        } else {
            $q = new WP_Query(vdp_widget_build_query('date', $instance['count'], $instance['category']));
        }
        return $q->posts;
    }
}

/* ============ 2. 文档网格大图 ============ */
class VDP_Widget_DocsGrid extends VDP_Widget {

    public function __construct() {
        parent::__construct(
            'vdp_widget_docs_grid',
            '【文库】文档网格（大图）',
            ['description' => '2/3/4 列网格，每个文档卡显示首页缩略图']
        );
    }

    protected function fields() {
        return [
            'title'    => ['type' => 'text',   'label' => '标题',   'default' => '推荐文档'],
            'order_by' => [
                'type' => 'select', 'label' => '排序方式', 'default' => 'date',
                'options' => [
                    'date'      => '最新发布',
                    'downloads' => '下载最多',
                    'views'     => '浏览最多',
                    'comment'   => '评论最多',
                    'rand'      => '随机',
                ],
            ],
            'cols'     => [
                'type' => 'select', 'label' => '列数', 'default' => '3',
                'options' => ['2' => '2 列', '3' => '3 列', '4' => '4 列'],
            ],
            'count'    => ['type' => 'number', 'label' => '显示数量', 'default' => 6],
            'category' => ['type' => 'text',   'label' => '指定分类 slug（可空）', 'default' => ''],
            'show_meta'=> ['type' => 'checkbox','label' => '显示下载量/日期', 'default' => 1],
        ];
    }

    protected function render($args, $instance) {
        $q = new WP_Query(vdp_widget_build_query(
            $instance['order_by'], $instance['count'], $instance['category']
        ));
        if (!$q->have_posts()) {
            echo '<p class="vdp-widget-empty">暂无内容</p>';
            return;
        }

        $cols  = in_array($instance['cols'], ['2','3','4']) ? (int) $instance['cols'] : 3;
        $col_class = 'vdp-grid-col-' . $cols;
        $show_meta = !empty($instance['show_meta']);

        echo '<div class="vdp-docs-grid ' . esc_attr($col_class) . '">';
        while ($q->have_posts()) : $q->the_post();
            $info  = vdp_get_doc_file_info(get_the_ID());
            $thumb = vdp_get_doc_thumbnail(get_the_ID(), 1, 'medium');
        ?>
            <div class="vdp-grid-item">
                <a href="<?php the_permalink(); ?>" class="vdp-grid-thumb">
                    <?php if ($thumb) : ?>
                        <img src="<?php echo esc_url($thumb); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy">
                    <?php else : ?>
                        <div class="vdp-grid-placeholder"><i class="fa fa-file-text-o"></i></div>
                    <?php endif; ?>
                    <?php if ($info['ext']) : ?>
                        <span class="vdp-grid-ext"><?php echo vdp_get_format_badge($info['ext']); ?></span>
                    <?php endif; ?>
                    <?php if (floatval($info['price']) > 0) : ?>
                        <span class="vdp-grid-price">&yen;<?php echo $info['price']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?php the_permalink(); ?>" class="vdp-grid-title"><?php the_title(); ?></a>
                <?php if ($show_meta) : ?>
                    <div class="vdp-grid-meta">
                        <?php if ($info['downloads']) : ?>
                            <span><i class="fa fa-download"></i> <?php echo (int) $info['downloads']; ?></span>
                        <?php endif; ?>
                        <span><i class="fa fa-clock-o"></i> <?php echo get_the_date('m-d'); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php
        endwhile;
        echo '</div>';
        wp_reset_postdata();
    }
}

/* ============ 3. 多分类 TAB 切换列表 ============ */
class VDP_Widget_CategoryTabs extends VDP_Widget {

    public function __construct() {
        parent::__construct(
            'vdp_widget_category_tabs',
            '【文库】多分类 TAB 切换',
            ['description' => '多个分类的标签切换列表，每个 TAB 显示该分类最新文档']
        );
    }

    protected function fields() {
        return [
            'title'    => ['type' => 'text',     'label' => '标题', 'default' => ''],
            'cat_ids'  => [
                'type' => 'text',
                'label' => '分类 ID 列表（逗号分隔，留空=自动取热门分类）',
                'default' => '',
            ],
            'tab_count'=> ['type' => 'number', 'label' => '自动模式下 TAB 数量', 'default' => 4],
            'count'    => ['type' => 'number', 'label' => '每个 TAB 显示数量', 'default' => 6],
            'show_thumb' => ['type' => 'checkbox', 'label' => '显示缩略图', 'default' => 1],
        ];
    }

    protected function render($args, $instance) {
        $cats = $this->fetch_cats($instance);
        if (empty($cats)) {
            echo '<p class="vdp-widget-empty">暂无分类</p>';
            return;
        }

        $count = max(1, (int) $instance['count']);
        $tab_id = 'vdp-cattabs-' . $args['widget_id'];
        $show_thumb = !empty($instance['show_thumb']);
        ?>
        <div class="vdp-rank-tabs vdp-cat-tabs" id="<?php echo esc_attr($tab_id); ?>">
            <ul class="vdp-rank-tab-nav">
                <?php $first = true; foreach ($cats as $c) : ?>
                    <li class="<?php echo $first ? 'active' : ''; ?>"
                        data-target="<?php echo esc_attr($tab_id . '-' . $c->term_id); ?>">
                        <?php echo esc_html($c->name); ?>
                    </li>
                <?php $first = false; endforeach; ?>
            </ul>
            <div class="vdp-rank-tab-content">
                <?php $first = true; foreach ($cats as $c) :
                    $q = new WP_Query([
                        'post_type'      => 'post',
                        'posts_per_page' => $count,
                        'cat'            => $c->term_id,
                        'no_found_rows'  => true,
                    ]);
                ?>
                    <ul class="vdp-rank-list <?php echo $first ? 'active' : ''; ?>"
                        id="<?php echo esc_attr($tab_id . '-' . $c->term_id); ?>">
                        <?php
                        if (!$q->have_posts()) {
                            echo '<li class="vdp-rank-empty">该分类暂无内容</li>';
                        } else {
                            while ($q->have_posts()) : $q->the_post();
                                $info  = vdp_get_doc_file_info(get_the_ID());
                                $thumb = $show_thumb ? vdp_get_doc_thumbnail(get_the_ID(), 1, 'thumbnail') : '';
                        ?>
                            <li class="vdp-widget-doc-item">
                                <?php if ($show_thumb) : ?>
                                    <a href="<?php the_permalink(); ?>" class="vdp-widget-doc-thumb">
                                        <?php if ($thumb) : ?>
                                            <img src="<?php echo esc_url($thumb); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy">
                                        <?php else : ?>
                                            <?php echo vdp_get_format_badge($info['ext']); ?>
                                        <?php endif; ?>
                                    </a>
                                <?php endif; ?>
                                <div class="vdp-widget-doc-body">
                                    <a href="<?php the_permalink(); ?>" class="vdp-widget-doc-title"><?php the_title(); ?></a>
                                    <div class="vdp-widget-doc-meta">
                                        <?php if ($info['downloads']) : ?>
                                            <span><i class="fa fa-download"></i> <?php echo (int) $info['downloads']; ?></span>
                                        <?php endif; ?>
                                        <span><i class="fa fa-clock-o"></i> <?php echo get_the_date('m-d'); ?></span>
                                    </div>
                                </div>
                            </li>
                        <?php
                            endwhile;
                            wp_reset_postdata();
                        }
                        ?>
                    </ul>
                <?php $first = false; endforeach; ?>
            </div>
        </div>
        <?php
    }

    private function fetch_cats($instance) {
        if (!empty($instance['cat_ids'])) {
            $ids = preg_split('/[,，\s]+/', $instance['cat_ids']);
            $ids = array_filter(array_map('intval', $ids));
            if (empty($ids)) return [];
            $cats = [];
            foreach ($ids as $id) {
                $t = get_term($id, 'category');
                if ($t && !is_wp_error($t)) $cats[] = $t;
            }
            return $cats;
        }
        return get_categories([
            'hide_empty' => true,
            'number'     => max(1, (int) $instance['tab_count']),
            'orderby'    => 'count',
            'order'      => 'DESC',
        ]);
    }
}

/* ============ 4. 焦点图 + 列表（子比经典左大图右列表） ============ */
class VDP_Widget_FocusList extends VDP_Widget {

    public function __construct() {
        parent::__construct(
            'vdp_widget_focus_list',
            '【文库】焦点图 + 列表',
            ['description' => '左侧焦点大图 + 右侧文档列表，子比经典版式']
        );
    }

    protected function fields() {
        return [
            'title'    => ['type' => 'text',   'label' => '标题', 'default' => ''],
            'order_by' => [
                'type' => 'select', 'label' => '排序方式', 'default' => 'date',
                'options' => [
                    'date'      => '最新发布',
                    'downloads' => '下载最多',
                    'comment'   => '评论最多',
                    'rand'      => '随机',
                ],
            ],
            'category' => ['type' => 'text',   'label' => '分类 slug（可空）', 'default' => ''],
            'focus_count' => ['type' => 'number', 'label' => '左侧焦点图数量', 'default' => 1],
            'list_count'  => ['type' => 'number', 'label' => '右侧列表数量',   'default' => 5],
        ];
    }

    protected function render($args, $instance) {
        $focus_n = max(1, (int) $instance['focus_count']);
        $list_n  = max(1, (int) $instance['list_count']);
        $total   = $focus_n + $list_n;

        $q = new WP_Query(vdp_widget_build_query(
            $instance['order_by'], $total, $instance['category']
        ));
        if (!$q->have_posts()) {
            echo '<p class="vdp-widget-empty">暂无内容</p>';
            return;
        }

        $posts = $q->posts;
        wp_reset_postdata();

        $focus_posts = array_slice($posts, 0, $focus_n);
        $list_posts  = array_slice($posts, $focus_n);
        ?>
        <div class="vdp-focus-list">
            <div class="vdp-focus-left">
                <?php foreach ($focus_posts as $p) :
                    $thumb = vdp_get_doc_thumbnail($p->ID, 1, 'medium');
                    $info  = vdp_get_doc_file_info($p->ID);
                ?>
                    <a href="<?php echo esc_url(get_permalink($p->ID)); ?>" class="vdp-focus-card">
                        <div class="vdp-focus-thumb">
                            <?php if ($thumb) : ?>
                                <img src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr(get_the_title($p->ID)); ?>" loading="lazy">
                            <?php else : ?>
                                <div class="vdp-grid-placeholder"><i class="fa fa-file-text-o"></i></div>
                            <?php endif; ?>
                            <?php if (floatval($info['price']) > 0) : ?>
                                <span class="vdp-grid-price">&yen;<?php echo $info['price']; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="vdp-focus-title"><?php echo esc_html(get_the_title($p->ID)); ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
            <ul class="vdp-focus-list-right">
                <?php foreach ($list_posts as $i => $p) :
                    $info = vdp_get_doc_file_info($p->ID);
                    $rank = $i + 1;
                    $rank_class = $rank <= 3 ? 'top-' . $rank : 'normal';
                ?>
                    <li class="vdp-focus-list-item">
                        <span class="vdp-rank-num <?php echo esc_attr($rank_class); ?>"><?php echo $rank; ?></span>
                        <a href="<?php echo esc_url(get_permalink($p->ID)); ?>" class="vdp-focus-list-title">
                            <?php echo esc_html(get_the_title($p->ID)); ?>
                        </a>
                        <span class="vdp-focus-list-count"><i class="fa fa-download"></i> <?php echo (int) $info['downloads']; ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }
}

/* ============ 5. 文章列表（多样式 + 多分类筛选 — 子比经典万能小工具复刻） ============
 * 一个小工具搞定 5 种展示样式：
 *   simple     纯标题列表（紧凑）
 *   standard   左缩略图 + 右标题元信息（默认，最常用）
 *   card       上大图下标题（卡片式）
 *   grid       多列网格（2/3/4 列）
 *   wide       宽幅横排卡（左大图右标题摘要）
 */
class VDP_Widget_PostList extends VDP_Widget {

    public function __construct() {
        parent::__construct(
            'vdp_widget_post_list',
            '【文库】文章列表（多样式）',
            ['description' => '一个小工具支持 5 种样式 + 多分类限定 + 时间范围筛选，可放在任意位置']
        );
    }

    protected function fields() {
        return [
            'title' => ['type' => 'text', 'label' => '标题', 'default' => ''],
            'style' => [
                'type' => 'select', 'label' => '展示样式', 'default' => 'standard',
                'options' => [
                    'simple'   => '① 纯标题列表（紧凑）',
                    'standard' => '② 缩略图 + 标题（默认）',
                    'card'     => '③ 卡片（上图下标题）',
                    'grid'     => '④ 多列网格',
                    'wide'     => '⑤ 宽幅横排卡',
                    'mini'     => '⑥ 双列迷你（小图 + 双行标题）',
                ],
            ],
            'cols' => [
                'type' => 'select', 'label' => '网格列数（仅 ④ 网格样式）', 'default' => '3',
                'options' => ['2' => '2 列', '3' => '3 列', '4' => '4 列'],
            ],
            'order_by' => [
                'type' => 'select', 'label' => '排序方式', 'default' => 'date',
                'options' => [
                    'date'      => '最新发布',
                    'modified'  => '最近更新',
                    'downloads' => '下载最多',
                    'views'     => '浏览最多',
                    'comment'   => '评论最多',
                    'rand'      => '随机',
                ],
            ],
            'time_range' => [
                'type' => 'select', 'label' => '时间范围', 'default' => '0',
                'options' => [
                    '0'   => '不限',
                    '1'   => '1 天内',
                    '7'   => '7 天内',
                    '30'  => '30 天内',
                    '90'  => '90 天内',
                    '365' => '1 年内',
                ],
            ],
            'count' => ['type' => 'number', 'label' => '显示数量', 'default' => 6],
            'cat_ids' => [
                'type' => 'text',
                'label' => '限定分类 ID（多个用逗号，可在分类管理页 URL 中查看 ID）',
                'default' => '',
            ],
            'include_children' => [
                'type' => 'checkbox', 'label' => '包含子分类', 'default' => 1,
            ],
            'exclude_ids' => [
                'type' => 'text', 'label' => '排除文章 ID（逗号分隔）', 'default' => '',
            ],
            'show_thumb' => [
                'type' => 'checkbox', 'label' => '显示缩略图', 'default' => 1,
            ],
            'show_meta' => [
                'type' => 'checkbox', 'label' => '显示元信息（下载/日期）', 'default' => 1,
            ],
            'show_excerpt' => [
                'type' => 'checkbox', 'label' => '显示摘要（仅 ⑤ 宽幅样式）', 'default' => 0,
            ],
            'excerpt_length' => [
                'type' => 'number', 'label' => '摘要字数', 'default' => 60,
            ],
            'show_cat_badge' => [
                'type' => 'checkbox', 'label' => '显示分类徽章（仅 ② ③ ⑤ 样式）', 'default' => 0,
            ],
        ];
    }

    protected function render($args, $instance) {
        $q = new WP_Query($this->build_query($instance));
        if (!$q->have_posts()) {
            echo '<p class="vdp-widget-empty">暂无内容</p>';
            return;
        }

        $style = in_array($instance['style'], ['simple','standard','card','grid','wide','mini'], true)
            ? $instance['style'] : 'standard';

        switch ($style) {
            case 'simple':   $this->render_simple($q, $instance);   break;
            case 'card':     $this->render_card($q, $instance);     break;
            case 'grid':     $this->render_grid($q, $instance);     break;
            case 'wide':     $this->render_wide($q, $instance);     break;
            case 'mini':     $this->render_mini($q, $instance);     break;
            default:         $this->render_standard($q, $instance); break;
        }
        wp_reset_postdata();
    }

    private function build_query($instance) {
        $args = [
            'post_type'           => 'post',
            'posts_per_page'      => max(1, (int) $instance['count']),
            'no_found_rows'       => true,
            'ignore_sticky_posts' => 1,
        ];

        if (!empty($instance['cat_ids'])) {
            $ids = preg_split('/[,，\s]+/', $instance['cat_ids']);
            $ids = array_filter(array_map('intval', $ids));
            if ($ids) {
                $args['tax_query'] = [
                    [
                        'taxonomy'         => 'category',
                        'field'            => 'term_id',
                        'terms'            => $ids,
                        'include_children' => !empty($instance['include_children']),
                    ],
                ];
            }
        }

        if (!empty($instance['exclude_ids'])) {
            $ex = preg_split('/[,，\s]+/', $instance['exclude_ids']);
            $args['post__not_in'] = array_filter(array_map('intval', $ex));
        }

        $days = (int) $instance['time_range'];
        if ($days > 0) {
            $args['date_query'] = [['after' => $days . ' days ago']];
        }

        switch ($instance['order_by']) {
            case 'modified':
                $args['orderby'] = 'modified'; $args['order'] = 'DESC'; break;
            case 'downloads':
                $args['meta_key'] = 'vdp_downloads';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = 'DESC'; break;
            case 'views':
                $args['meta_key'] = 'views';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = 'DESC'; break;
            case 'comment':
                $args['orderby'] = 'comment_count'; $args['order'] = 'DESC'; break;
            case 'rand':
                $args['orderby'] = 'rand'; break;
            default:
                $args['orderby'] = 'date'; $args['order'] = 'DESC';
        }
        return $args;
    }

    /* ---------- 样式 ①：纯标题 ---------- */
    private function render_simple($q, $instance) {
        $show_meta = !empty($instance['show_meta']);
        echo '<ul class="vdp-postlist vdp-postlist-simple">';
        while ($q->have_posts()) : $q->the_post(); ?>
            <li>
                <a href="<?php the_permalink(); ?>" class="vdp-postlist-title"><?php the_title(); ?></a>
                <?php if ($show_meta) : ?>
                    <span class="vdp-postlist-meta"><?php echo get_the_date('m-d'); ?></span>
                <?php endif; ?>
            </li>
        <?php endwhile;
        echo '</ul>';
    }

    /* ---------- 样式 ②：标准（左缩略图 + 右文字） ---------- */
    private function render_standard($q, $instance) {
        $show_thumb = !empty($instance['show_thumb']);
        $show_meta  = !empty($instance['show_meta']);
        $show_cat   = !empty($instance['show_cat_badge']);
        echo '<ul class="vdp-postlist vdp-postlist-standard">';
        while ($q->have_posts()) : $q->the_post();
            $info  = vdp_get_doc_file_info(get_the_ID());
            $thumb = $show_thumb ? vdp_get_doc_thumbnail(get_the_ID(), 1, 'thumbnail') : '';
        ?>
            <li class="vdp-widget-doc-item">
                <?php if ($show_thumb) : ?>
                    <a href="<?php the_permalink(); ?>" class="vdp-widget-doc-thumb">
                        <?php if ($thumb) : ?>
                            <img src="<?php echo esc_url($thumb); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy">
                        <?php else : ?>
                            <?php echo vdp_get_format_badge($info['ext']); ?>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
                <div class="vdp-widget-doc-body">
                    <?php if ($show_cat) echo $this->cat_badge(get_the_ID()); ?>
                    <a href="<?php the_permalink(); ?>" class="vdp-widget-doc-title"><?php the_title(); ?></a>
                    <?php if ($show_meta) : ?>
                        <div class="vdp-widget-doc-meta">
                            <?php if ($info['downloads']) : ?>
                                <span><i class="fa fa-download"></i> <?php echo (int) $info['downloads']; ?></span>
                            <?php endif; ?>
                            <span><i class="fa fa-clock-o"></i> <?php echo get_the_date('m-d'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </li>
        <?php endwhile;
        echo '</ul>';
    }

    /* ---------- 样式 ③：卡片（上图下标题） ---------- */
    private function render_card($q, $instance) {
        $show_meta = !empty($instance['show_meta']);
        $show_cat  = !empty($instance['show_cat_badge']);
        echo '<div class="vdp-postlist-cards">';
        while ($q->have_posts()) : $q->the_post();
            $info  = vdp_get_doc_file_info(get_the_ID());
            $thumb = vdp_get_doc_thumbnail(get_the_ID(), 1, 'medium');
        ?>
            <div class="vdp-postlist-card">
                <a href="<?php the_permalink(); ?>" class="vdp-grid-thumb">
                    <?php if ($thumb) : ?>
                        <img src="<?php echo esc_url($thumb); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy">
                    <?php else : ?>
                        <div class="vdp-grid-placeholder"><i class="fa fa-file-text-o"></i></div>
                    <?php endif; ?>
                    <?php if ($show_cat) echo '<span class="vdp-postlist-cat-overlay">' . $this->cat_badge(get_the_ID(), false) . '</span>'; ?>
                    <?php if (floatval($info['price']) > 0) : ?>
                        <span class="vdp-grid-price">&yen;<?php echo $info['price']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?php the_permalink(); ?>" class="vdp-grid-title"><?php the_title(); ?></a>
                <?php if ($show_meta) : ?>
                    <div class="vdp-grid-meta">
                        <span><i class="fa fa-download"></i> <?php echo (int) $info['downloads']; ?></span>
                        <span><i class="fa fa-clock-o"></i> <?php echo get_the_date('m-d'); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endwhile;
        echo '</div>';
    }

    /* ---------- 样式 ④：多列网格 ---------- */
    private function render_grid($q, $instance) {
        $cols = in_array($instance['cols'], ['2','3','4'], true) ? (int) $instance['cols'] : 3;
        $show_meta = !empty($instance['show_meta']);
        echo '<div class="vdp-docs-grid vdp-grid-col-' . $cols . '">';
        while ($q->have_posts()) : $q->the_post();
            $info  = vdp_get_doc_file_info(get_the_ID());
            $thumb = vdp_get_doc_thumbnail(get_the_ID(), 1, 'medium');
        ?>
            <div class="vdp-grid-item">
                <a href="<?php the_permalink(); ?>" class="vdp-grid-thumb">
                    <?php if ($thumb) : ?>
                        <img src="<?php echo esc_url($thumb); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy">
                    <?php else : ?>
                        <div class="vdp-grid-placeholder"><i class="fa fa-file-text-o"></i></div>
                    <?php endif; ?>
                    <?php if ($info['ext']) : ?>
                        <span class="vdp-grid-ext"><?php echo vdp_get_format_badge($info['ext']); ?></span>
                    <?php endif; ?>
                    <?php if (floatval($info['price']) > 0) : ?>
                        <span class="vdp-grid-price">&yen;<?php echo $info['price']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?php the_permalink(); ?>" class="vdp-grid-title"><?php the_title(); ?></a>
                <?php if ($show_meta) : ?>
                    <div class="vdp-grid-meta">
                        <?php if ($info['downloads']) : ?>
                            <span><i class="fa fa-download"></i> <?php echo (int) $info['downloads']; ?></span>
                        <?php endif; ?>
                        <span><i class="fa fa-clock-o"></i> <?php echo get_the_date('m-d'); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endwhile;
        echo '</div>';
    }

    /* ---------- 样式 ⑤：宽幅横排卡（左大图右文字 + 摘要） ---------- */
    private function render_wide($q, $instance) {
        $show_meta    = !empty($instance['show_meta']);
        $show_cat     = !empty($instance['show_cat_badge']);
        $show_excerpt = !empty($instance['show_excerpt']);
        $excerpt_len  = max(20, (int) $instance['excerpt_length']);
        echo '<div class="vdp-postlist-wide">';
        while ($q->have_posts()) : $q->the_post();
            $info  = vdp_get_doc_file_info(get_the_ID());
            $thumb = vdp_get_doc_thumbnail(get_the_ID(), 1, 'medium');
        ?>
            <div class="vdp-postlist-wide-item">
                <a href="<?php the_permalink(); ?>" class="vdp-postlist-wide-thumb">
                    <?php if ($thumb) : ?>
                        <img src="<?php echo esc_url($thumb); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy">
                    <?php else : ?>
                        <div class="vdp-grid-placeholder"><i class="fa fa-file-text-o"></i></div>
                    <?php endif; ?>
                </a>
                <div class="vdp-postlist-wide-body">
                    <?php if ($show_cat) echo $this->cat_badge(get_the_ID()); ?>
                    <a href="<?php the_permalink(); ?>" class="vdp-postlist-wide-title"><?php the_title(); ?></a>
                    <?php if ($show_excerpt) : ?>
                        <div class="vdp-postlist-wide-excerpt">
                            <?php
                            $ex = get_the_excerpt();
                            if (!$ex) $ex = wp_strip_all_tags(get_the_content());
                            echo esc_html(mb_substr($ex, 0, $excerpt_len)) . '...';
                            ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($show_meta) : ?>
                        <div class="vdp-postlist-wide-meta">
                            <span><i class="fa fa-clock-o"></i> <?php echo get_the_date('Y-m-d'); ?></span>
                            <?php if ($info['downloads']) : ?>
                                <span><i class="fa fa-download"></i> <?php echo (int) $info['downloads']; ?></span>
                            <?php endif; ?>
                            <?php if ($info['size']) : ?>
                                <span><?php echo vdp_format_file_size($info['size']); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile;
        echo '</div>';
    }

    /* ---------- 样式 ⑥：迷你（双列，参考子比 posts-mini） ---------- */
    private function render_mini($q, $instance) {
        $show_meta = !empty($instance['show_meta']);
        echo '<div class="vdp-postlist-mini">';
        while ($q->have_posts()) : $q->the_post();
            $info  = vdp_get_doc_file_info(get_the_ID());
            $thumb = vdp_get_doc_thumbnail(get_the_ID(), 1, 'thumbnail');
        ?>
            <div class="vdp-postlist-mini-item">
                <a href="<?php the_permalink(); ?>" class="vdp-postlist-mini-thumb hover-zoom-img-sm">
                    <?php if ($thumb) : ?>
                        <img src="<?php echo esc_url($thumb); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy">
                    <?php else : ?>
                        <div class="vdp-grid-placeholder"><i class="fa fa-file-text-o"></i></div>
                    <?php endif; ?>
                </a>
                <div class="vdp-postlist-mini-body">
                    <a href="<?php the_permalink(); ?>" class="vdp-postlist-mini-title"><?php the_title(); ?></a>
                    <?php if ($show_meta) : ?>
                        <div class="vdp-postlist-mini-meta">
                            <?php if ($info['downloads']) : ?>
                                <span><i class="fa fa-download"></i> <?php echo (int) $info['downloads']; ?></span>
                            <?php endif; ?>
                            <span><?php echo get_the_date('m-d'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile;
        echo '</div>';
    }

    /** 获取首个分类徽章 */
    private function cat_badge($post_id, $with_link = true) {
        $cats = get_the_category($post_id);
        if (empty($cats)) return '';
        $c = $cats[0];
        if ($with_link) {
            return '<a href="' . esc_url(get_category_link($c->term_id)) . '" class="vdp-postlist-cat">'
                 . esc_html($c->name) . '</a>';
        }
        return '<span class="vdp-postlist-cat">' . esc_html($c->name) . '</span>';
    }
}
