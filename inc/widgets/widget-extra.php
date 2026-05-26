<?php
/**
 * 复刻子比的几个常用小工具：
 *   - VDP_Widget_HotRankTabs    日/周/月 文档排行（TAB 切换）
 *   - VDP_Widget_TagCloud       热门标签云
 *   - VDP_Widget_AdCard         图文卡片广告（公告/联系/友链通用）
 *   - VDP_Widget_CommentRank    评论达人榜（按近期评论数）
 */

if (!defined('ABSPATH')) exit;

/* ========== 1. 排行榜 TAB（日/周/月） ========== */
class VDP_Widget_HotRankTabs extends VDP_Widget {

    public function __construct() {
        parent::__construct(
            'vdp_widget_hot_rank_tabs',
            '【文库】排行榜（日/周/月）',
            ['description' => '按日/周/月切换的下载排行榜']
        );
    }

    protected function fields() {
        return [
            'title' => ['type' => 'text', 'label' => '标题', 'default' => '排行榜'],
            'count' => ['type' => 'number', 'label' => '每个 TAB 数量', 'default' => 8],
            'show_thumb' => ['type' => 'checkbox', 'label' => '显示缩略图', 'default' => 1],
        ];
    }

    protected function render($args, $instance) {
        $count = max(1, (int) $instance['count']);
        $tabs  = [
            'day'   => ['label' => '日榜', 'days' => 1],
            'week'  => ['label' => '周榜', 'days' => 7],
            'month' => ['label' => '月榜', 'days' => 30],
        ];
        $tab_id = 'vdp-rank-' . $args['widget_id'];
        ?>
        <div class="vdp-rank-tabs" id="<?php echo esc_attr($tab_id); ?>">
            <ul class="vdp-rank-tab-nav">
                <?php $first = true; foreach ($tabs as $key => $cfg) : ?>
                    <li class="<?php echo $first ? 'active' : ''; ?>"
                        data-target="<?php echo esc_attr($tab_id . '-' . $key); ?>">
                        <?php echo esc_html($cfg['label']); ?>
                    </li>
                <?php $first = false; endforeach; ?>
            </ul>
            <div class="vdp-rank-tab-content">
                <?php $first = true; foreach ($tabs as $key => $cfg) :
                    $q = new WP_Query([
                        'post_type'      => 'post',
                        'posts_per_page' => $count,
                        'no_found_rows'  => true,
                        'meta_key'       => 'vdp_downloads',
                        'orderby'        => 'meta_value_num',
                        'order'          => 'DESC',
                        'date_query'     => [
                            ['after' => $cfg['days'] . ' days ago'],
                        ],
                    ]);
                ?>
                    <ul class="vdp-rank-list <?php echo $first ? 'active' : ''; ?>"
                        id="<?php echo esc_attr($tab_id . '-' . $key); ?>">
                        <?php
                        if (!$q->have_posts()) {
                            echo '<li class="vdp-rank-empty">暂无内容</li>';
                        } else {
                            $i = 0;
                            while ($q->have_posts()) : $q->the_post(); $i++;
                                $info  = vdp_get_doc_file_info(get_the_ID());
                                $thumb = !empty($instance['show_thumb']) ? vdp_get_doc_thumbnail(get_the_ID(), 1, 'thumbnail') : '';
                                $rank_class = $i <= 3 ? 'top-' . $i : 'normal';
                        ?>
                            <li class="vdp-rank-item">
                                <span class="vdp-rank-num <?php echo esc_attr($rank_class); ?>"><?php echo $i; ?></span>
                                <?php if (!empty($instance['show_thumb'])) : ?>
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
                                        <span><i class="fa fa-download"></i> <?php echo (int) $info['downloads']; ?></span>
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
}

/* ========== 2. 热门标签云 ========== */
class VDP_Widget_TagCloud extends VDP_Widget {

    public function __construct() {
        parent::__construct(
            'vdp_widget_tag_cloud',
            '【文库】热门标签',
            ['description' => '按使用次数展示标签云']
        );
    }

    protected function fields() {
        return [
            'title'   => ['type' => 'text', 'label' => '标题', 'default' => '热门标签'],
            'count'   => ['type' => 'number', 'label' => '显示数量', 'default' => 30],
            'colored' => ['type' => 'checkbox', 'label' => '彩色标签（按权重渐变）', 'default' => 1],
        ];
    }

    protected function render($args, $instance) {
        $tags = get_terms([
            'taxonomy'   => 'post_tag',
            'orderby'    => 'count',
            'order'      => 'DESC',
            'number'     => max(1, (int) $instance['count']),
            'hide_empty' => true,
        ]);

        if (is_wp_error($tags) || empty($tags)) {
            echo '<p class="vdp-widget-empty">暂无标签</p>';
            return;
        }

        $colored = !empty($instance['colored']);
        $palette = ['#2196F3', '#4CAF50', '#FF9800', '#9C27B0', '#F44336', '#00BCD4', '#795548'];

        echo '<div class="vdp-tag-cloud">';
        foreach ($tags as $i => $t) {
            $style = $colored
                ? ' style="background:' . esc_attr($palette[$i % count($palette)]) . ';color:#fff;"'
                : '';
            echo '<a href="' . esc_url(get_term_link($t)) . '" class="vdp-tag"' . $style . '>'
               . esc_html($t->name) . '<sup>' . (int) $t->count . '</sup></a>';
        }
        echo '</div>';
    }
}

/* ========== 3. 图文卡片（公告/广告/友情链接通用） ========== */
class VDP_Widget_AdCard extends VDP_Widget {

    public function __construct() {
        parent::__construct(
            'vdp_widget_ad_card',
            '【文库】图文卡片',
            ['description' => '一张图 + 一段文字 + 跳转链接，可做广告、公告、联系卡片']
        );
    }

    protected function fields() {
        return [
            'title'    => ['type' => 'text',     'label' => '标题',           'default' => ''],
            'image'    => ['type' => 'text',     'label' => '图片地址（URL）','default' => ''],
            'desc'     => ['type' => 'textarea', 'label' => '文案（可含 HTML）', 'default' => ''],
            'btn_text' => ['type' => 'text',     'label' => '按钮文字',        'default' => ''],
            'btn_url'  => ['type' => 'text',     'label' => '按钮链接',        'default' => ''],
            'btn_blank'=> ['type' => 'checkbox', 'label' => '在新标签页打开',  'default' => 1],
        ];
    }

    protected function render($args, $instance) {
        $img      = trim($instance['image']);
        $desc     = trim($instance['desc']);
        $btn_text = trim($instance['btn_text']);
        $btn_url  = trim($instance['btn_url']);
        $blank    = !empty($instance['btn_blank']) ? ' target="_blank" rel="noopener"' : '';

        if (!$img && !$desc && !$btn_text) {
            echo '<p class="vdp-widget-empty">请在小工具配置中填写内容</p>';
            return;
        }
        ?>
        <div class="vdp-ad-card">
            <?php if ($img) : ?>
                <?php if ($btn_url) : ?>
                    <a href="<?php echo esc_url($btn_url); ?>"<?php echo $blank; ?> class="vdp-ad-img-wrap">
                        <img src="<?php echo esc_url($img); ?>" alt="" loading="lazy">
                    </a>
                <?php else : ?>
                    <div class="vdp-ad-img-wrap">
                        <img src="<?php echo esc_url($img); ?>" alt="" loading="lazy">
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($desc) : ?>
                <div class="vdp-ad-desc"><?php echo wp_kses_post($desc); ?></div>
            <?php endif; ?>

            <?php if ($btn_text && $btn_url) : ?>
                <a href="<?php echo esc_url($btn_url); ?>"<?php echo $blank; ?> class="vdp-ad-btn"><?php echo esc_html($btn_text); ?></a>
            <?php endif; ?>
        </div>
        <?php
    }
}

/* ========== 4. 评论达人榜 ========== */
class VDP_Widget_CommentRank extends VDP_Widget {

    public function __construct() {
        parent::__construct(
            'vdp_widget_comment_rank',
            '【文库】评论达人榜',
            ['description' => '按近期评论数量排行的活跃用户榜']
        );
    }

    protected function fields() {
        return [
            'title' => ['type' => 'text',   'label' => '标题',     'default' => '评论达人'],
            'days'  => ['type' => 'number', 'label' => '统计周期（天）', 'default' => 30],
            'count' => ['type' => 'number', 'label' => '显示数量', 'default' => 6],
        ];
    }

    protected function render($args, $instance) {
        global $wpdb;
        $days  = max(1, (int) $instance['days']);
        $limit = max(1, (int) $instance['count']);

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, comment_author, comment_author_email, COUNT(*) AS cnt
             FROM {$wpdb->comments}
             WHERE comment_approved = '1'
               AND user_id > 0
               AND comment_date_gmt >= %s
             GROUP BY user_id
             ORDER BY cnt DESC
             LIMIT %d",
            gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS),
            $limit
        ));

        if (empty($rows)) {
            echo '<p class="vdp-widget-empty">暂无评论数据</p>';
            return;
        }

        echo '<ul class="vdp-comment-rank">';
        foreach ($rows as $r) {
            $user = get_userdata($r->user_id);
            if (!$user) continue;
            echo '<li class="vdp-comment-rank-item">';
            echo '<a href="' . esc_url(get_author_posts_url($r->user_id)) . '" class="vdp-comment-rank-avatar">'
               . get_avatar($r->user_id, 36) . '</a>';
            echo '<div class="vdp-comment-rank-body">';
            echo '<a href="' . esc_url(get_author_posts_url($r->user_id)) . '" class="vdp-comment-rank-name">'
               . esc_html($user->display_name) . '</a>';
            echo '<span class="vdp-comment-rank-count">' . (int) $r->cnt . ' 条评论</span>';
            echo '</div>';
            echo '</li>';
        }
        echo '</ul>';
    }
}
