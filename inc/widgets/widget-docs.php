<?php
/**
 * 文档相关小工具
 */

if (!defined('ABSPATH')) exit;

/**
 * 最新文档 / 热门下载 / 随机推荐
 * 通过 order_by 字段切换
 */
class VDP_Widget_Docs extends VDP_Widget {

    public function __construct() {
        parent::__construct(
            'vdp_widget_docs',
            '【文库】文档列表',
            ['description' => '展示文库文档列表，可按最新/热门/随机排序']
        );
    }

    protected function fields() {
        return [
            'title' => [
                'type'    => 'text',
                'label'   => '标题',
                'default' => '最新文档',
            ],
            'order_by' => [
                'type'    => 'select',
                'label'   => '排序方式',
                'options' => [
                    'date'        => '最新发布',
                    'downloads'   => '下载最多',
                    'comment'     => '评论最多',
                    'rand'        => '随机推荐',
                ],
                'default' => 'date',
            ],
            'count' => [
                'type'    => 'number',
                'label'   => '显示数量',
                'default' => 5,
            ],
            'show_thumb' => [
                'type'    => 'checkbox',
                'label'   => '显示缩略图',
                'default' => 1,
            ],
            'category' => [
                'type'    => 'text',
                'label'   => '指定分类（slug，多个用逗号）',
                'default' => '',
                'desc'    => '留空则显示全部',
            ],
        ];
    }

    protected function render($args, $instance) {
        $query_args = [
            'post_type'      => 'post',
            'posts_per_page' => max(1, (int) $instance['count']),
            'no_found_rows'  => true,
            'meta_query'     => [
                ['key' => 'posts_zibpay', 'compare' => 'EXISTS'],
            ],
        ];

        switch ($instance['order_by']) {
            case 'downloads':
                $query_args['meta_key'] = 'vdp_downloads';
                $query_args['orderby']  = 'meta_value_num';
                $query_args['order']    = 'DESC';
                break;
            case 'comment':
                $query_args['orderby'] = 'comment_count';
                $query_args['order']   = 'DESC';
                break;
            case 'rand':
                $query_args['orderby'] = 'rand';
                break;
            default:
                $query_args['orderby'] = 'date';
                $query_args['order']   = 'DESC';
        }

        if (!empty($instance['category'])) {
            $query_args['category_name'] = $instance['category'];
        }

        $q = new WP_Query($query_args);
        if (!$q->have_posts()) {
            echo '<p class="vdp-widget-empty">暂无内容</p>';
            return;
        }

        echo '<ul class="vdp-widget-docs">';
        while ($q->have_posts()) : $q->the_post();
            $info = vdp_get_doc_file_info(get_the_ID());
        ?>
            <li class="vdp-widget-doc-item">
                <?php if (!empty($instance['show_thumb'])) :
                    $thumb = vdp_get_doc_thumbnail(get_the_ID(), 1, 'thumbnail');
                ?>
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
                        <span><i class="fa fa-clock-o"></i> <?php echo human_time_diff(get_the_time('U')); ?>前</span>
                    </div>
                </div>
            </li>
        <?php
        endwhile;
        echo '</ul>';
        wp_reset_postdata();
    }
}

/**
 * 分类导航
 */
class VDP_Widget_Categories extends VDP_Widget {

    public function __construct() {
        parent::__construct(
            'vdp_widget_categories',
            '【文库】分类导航',
            ['description' => '显示分类列表，附带文档数量']
        );
    }

    protected function fields() {
        return [
            'title'     => ['type' => 'text', 'label' => '标题', 'default' => '文档分类'],
            'count'     => ['type' => 'number', 'label' => '显示数量（0=全部）', 'default' => 10],
            'hide_empty'=> ['type' => 'checkbox', 'label' => '隐藏空分类', 'default' => 1],
            'show_count'=> ['type' => 'checkbox', 'label' => '显示文档数', 'default' => 1],
        ];
    }

    protected function render($args, $instance) {
        $cats = get_categories([
            'hide_empty' => !empty($instance['hide_empty']),
            'number'     => max(0, (int) $instance['count']),
            'orderby'    => 'count',
            'order'      => 'DESC',
        ]);

        if (empty($cats)) {
            echo '<p class="vdp-widget-empty">暂无分类</p>';
            return;
        }

        echo '<ul class="vdp-widget-categories">';
        foreach ($cats as $cat) {
            echo '<li><a href="' . esc_url(get_category_link($cat->term_id)) . '">';
            echo '<span class="cat-name">' . esc_html($cat->name) . '</span>';
            if (!empty($instance['show_count'])) {
                echo '<span class="cat-count">' . (int) $cat->count . '</span>';
            }
            echo '</a></li>';
        }
        echo '</ul>';
    }
}

/**
 * 站点统计小工具
 *
 * 支持设置「起始日期」（计算运营天数）以及每项的「计算基数」
 * 基数会叠加在真实数据之上，便于站点冷启动期展示更体面的数字
 */
class VDP_Widget_Stats extends VDP_Widget {

    public function __construct() {
        parent::__construct(
            'vdp_widget_stats',
            '【文库】站点统计',
            ['description' => '运营天数 / 文档数 / 用户数 / 会员数 / 下载数，可设置基数']
        );
    }

    protected function fields() {
        return [
            'title' => ['type' => 'text', 'label' => '标题', 'default' => '站点统计'],
            'start_date' => [
                'type'    => 'text',
                'label'   => '运营起始日期',
                'default' => '',
                'desc'    => '格式：YYYY-MM-DD，留空则不显示运营天数',
            ],
            'show_days'      => ['type' => 'checkbox', 'label' => '显示运营天数', 'default' => 1],
            'show_docs'      => ['type' => 'checkbox', 'label' => '显示文档总数', 'default' => 1],
            'show_users'     => ['type' => 'checkbox', 'label' => '显示注册用户', 'default' => 1],
            'show_members'   => ['type' => 'checkbox', 'label' => '显示会员数量', 'default' => 1],
            'show_downloads' => ['type' => 'checkbox', 'label' => '显示下载次数', 'default' => 1],

            'base_docs'      => ['type' => 'number', 'label' => '文档数 计算基数',  'default' => 0],
            'base_users'     => ['type' => 'number', 'label' => '用户数 计算基数',  'default' => 0],
            'base_members'   => ['type' => 'number', 'label' => '会员数 计算基数',  'default' => 0],
            'base_downloads' => ['type' => 'number', 'label' => '下载数 计算基数',  'default' => 0],

            'columns' => [
                'type'    => 'select',
                'label'   => '列数',
                'default' => '2',
                'options' => ['1' => '单列', '2' => '两列', '3' => '三列'],
            ],
        ];
    }

    protected function render($args, $instance) {
        global $wpdb;

        $rows = [];

        if (!empty($instance['show_days']) && !empty($instance['start_date'])) {
            $start = strtotime($instance['start_date']);
            if ($start) {
                $days = max(0, floor((current_time('timestamp') - $start) / DAY_IN_SECONDS));
                $rows[] = ['num' => $days, 'label' => '运营天数', 'icon' => 'fa-calendar-o', 'color' => '#5dade2'];
            }
        }

        if (!empty($instance['show_docs'])) {
            $real = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = 'posts_zibpay' AND meta_value LIKE '%vdp_file_name%'"
            );
            $rows[] = [
                'num'   => $real + (int) $instance['base_docs'],
                'label' => '文档总数',
                'icon'  => 'fa-file-text-o',
                'color' => '#42b883',
            ];
        }

        if (!empty($instance['show_users'])) {
            $real = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
            $rows[] = [
                'num'   => $real + (int) $instance['base_users'],
                'label' => '注册用户',
                'icon'  => 'fa-users',
                'color' => '#9b59b6',
            ];
        }

        if (!empty($instance['show_members'])) {
            $real = (int) $wpdb->get_var(
                "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}vdp_memberships WHERE status = 1"
            );
            $rows[] = [
                'num'   => $real + (int) $instance['base_members'],
                'label' => '会员数量',
                'icon'  => 'fa-diamond',
                'color' => '#f39c12',
            ];
        }

        if (!empty($instance['show_downloads'])) {
            $real = (int) $wpdb->get_var(
                "SELECT SUM(meta_value+0) FROM {$wpdb->postmeta} WHERE meta_key = 'vdp_downloads'"
            );
            $rows[] = [
                'num'   => $real + (int) $instance['base_downloads'],
                'label' => '下载次数',
                'icon'  => 'fa-download',
                'color' => '#e74c3c',
            ];
        }

        if (empty($rows)) return;

        $cols = isset($instance['columns']) ? (int) $instance['columns'] : 2;
        if ($cols < 1 || $cols > 3) $cols = 2;

        echo '<div class="vdp-widget-stats vdp-stats-cols-' . $cols . '">';
        foreach ($rows as $r) {
            $display = $this->format_compact_number((int) $r['num']);
            $color   = isset($r['color']) ? $r['color'] : '#5dade2';
            ?>
            <div class="vdp-stat-row" style="--stat-color:<?php echo esc_attr($color); ?>;">
                <span class="vdp-stat-icon-wrap"><i class="fa <?php echo esc_attr($r['icon']); ?>"></i></span>
                <span class="vdp-stat-info">
                    <span class="vdp-stat-num" title="<?php echo number_format((float) $r['num']); ?>"><?php echo esc_html($display); ?></span>
                    <span class="vdp-stat-label"><?php echo esc_html($r['label']); ?></span>
                </span>
            </div>
            <?php
        }
        echo '</div>';
    }

    /**
     * 大数字简写：1234 → 1.2K，12345 → 1.2万，1234567 → 123.5万
     */
    private function format_compact_number($n) {
        $n = (float) $n;
        if ($n < 10000) return number_format($n);
        if ($n < 100000000) return rtrim(rtrim(number_format($n / 10000, 1), '0'), '.') . '万';
        return rtrim(rtrim(number_format($n / 100000000, 2), '0'), '.') . '亿';
    }
}
