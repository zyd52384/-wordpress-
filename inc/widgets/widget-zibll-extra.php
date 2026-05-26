<?php
/**
 * 借鉴子比的 3 个实用小工具
 *  1. 搜索框（vdp_widget_search）
 *  2. 文章目录树（vdp_widget_post_toc，仅 single 显示，依赖 JS 抓 h2/h3）
 *  3. 单行文章列表（vdp_widget_oneline_posts，横向滚动）
 */
if (!defined('ABSPATH')) exit;

/* ==================================================================
   1. 搜索框
   ================================================================== */
class VDP_Widget_Search extends VDP_Widget {

    public function __construct() {
        parent::__construct(
            'vdp_widget_search',
            '【文库】搜索框',
            ['description' => '侧栏放置搜索框，支持热门关键词']
        );
    }

    protected function fields() {
        return [
            'placeholder' => [
                'type'    => 'text',
                'label'   => '输入框占位文字',
                'default' => '搜索文档…',
            ],
            'show_keywords' => [
                'type'    => 'checkbox',
                'label'   => '显示热门关键词',
                'default' => 1,
            ],
            'keywords_title' => [
                'type'    => 'text',
                'label'   => '热门关键词标题',
                'default' => '热门搜索',
            ],
            'keywords' => [
                'type'    => 'textarea',
                'label'   => '热门关键词（逗号或换行分隔，留空则取热门标签）',
                'default' => '',
            ],
            'keyword_limit' => [
                'type'    => 'number',
                'label'   => '关键词最大数量',
                'default' => 8,
            ],
        ];
    }

    protected function render($args, $instance) {
        $ph = esc_attr($instance['placeholder'] ?: '搜索文档…');
        ?>
        <div class="vdp-widget-search">
            <form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>" class="vdp-search-form">
                <input type="search" name="s" class="vdp-search-input" placeholder="<?php echo $ph; ?>" value="<?php echo esc_attr(get_search_query()); ?>">
                <button type="submit" class="vdp-search-btn" aria-label="搜索"><i class="fa fa-search"></i></button>
            </form>
            <?php if (!empty($instance['show_keywords'])):
                $words = [];
                if (!empty($instance['keywords'])) {
                    $words = preg_split('/[,，\s\n]+/', $instance['keywords']);
                    $words = array_filter(array_map('trim', $words));
                } else {
                    $tags = get_tags([
                        'orderby'    => 'count',
                        'order'      => 'DESC',
                        'number'     => max(1, (int)$instance['keyword_limit']),
                        'hide_empty' => true,
                    ]);
                    foreach ($tags as $t) $words[] = $t->name;
                }
                $words = array_slice($words, 0, max(1, (int)$instance['keyword_limit']));
                if ($words): ?>
                    <div class="vdp-search-keywords">
                        <span class="vdp-search-keywords-title"><?php echo esc_html($instance['keywords_title']); ?>：</span>
                        <?php foreach ($words as $w):
                            $url = add_query_arg('s', urlencode($w), home_url('/')); ?>
                            <a href="<?php echo esc_url($url); ?>" class="vdp-search-keyword"><?php echo esc_html($w); ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif;
            endif; ?>
        </div>
        <?php
    }
}

/* ==================================================================
   2. 文章目录树（仅在 single 显示）
   ================================================================== */
class VDP_Widget_PostToc extends VDP_Widget {

    public function __construct() {
        parent::__construct(
            'vdp_widget_post_toc',
            '【文库】文章目录',
            ['description' => '自动提取正文中的 h2/h3 生成目录，仅在文章/页面显示，标题数 ≥3 才显示']
        );
    }

    protected function fields() {
        return [
            'min_count' => [
                'type'    => 'number',
                'label'   => '最少标题数（少于则不显示）',
                'default' => 3,
            ],
            'levels' => [
                'type'    => 'text',
                'label'   => '抓取的标题层级',
                'default' => 'h2,h3',
                'desc'    => '逗号分隔，如 h2,h3,h4',
            ],
        ];
    }

    protected function render($args, $instance) {
        if (!is_singular()) {
            // 非详情页不显示（连标题也不输出）
            echo '<p class="vdp-widget-empty">仅在文章/页面显示</p>';
            return;
        }
        $min    = max(1, (int)$instance['min_count']);
        $levels = esc_attr($instance['levels'] ?: 'h2,h3');
        ?>
        <div class="vdp-post-toc" data-target=".vdp-doc-content,.entry-content,article" data-levels="<?php echo $levels; ?>" data-min="<?php echo $min; ?>">
            <div class="vdp-post-toc-list"></div>
        </div>
        <script>
        (function(){
            if (window.__vdpTocInited) return; window.__vdpTocInited = true;
            document.addEventListener('DOMContentLoaded', function(){
                document.querySelectorAll('.vdp-post-toc').forEach(function(box){
                    var sel = box.getAttribute('data-target');
                    var levels = (box.getAttribute('data-levels')||'h2,h3').split(',').map(function(s){return s.trim();}).filter(Boolean);
                    var min = parseInt(box.getAttribute('data-min')||'3', 10);
                    var content = document.querySelector(sel);
                    if (!content) { box.closest('.widget,.vdp-widget') && (box.closest('.widget,.vdp-widget').style.display='none'); return; }
                    var nodes = content.querySelectorAll(levels.join(','));
                    if (!nodes.length || nodes.length < min) {
                        var w = box.closest('.widget,.vdp-widget'); if (w) w.style.display='none'; return;
                    }
                    var list = box.querySelector('.vdp-post-toc-list');
                    var html = '';
                    nodes.forEach(function(n, i){
                        if (!n.id) n.id = 'vdp-toc-' + i;
                        var lv = n.tagName.toLowerCase();
                        html += '<a class="vdp-toc-item vdp-toc-' + lv + '" href="#' + n.id + '">' + n.textContent.trim() + '</a>';
                    });
                    list.innerHTML = html;
                    list.addEventListener('click', function(e){
                        var a = e.target.closest('.vdp-toc-item'); if (!a) return;
                        e.preventDefault();
                        var id = a.getAttribute('href').slice(1);
                        var el = document.getElementById(id);
                        if (el) { window.scrollTo({top: el.getBoundingClientRect().top + window.pageYOffset - 80, behavior:'smooth'}); }
                    });
                });
            });
        })();
        </script>
        <?php
    }
}

/* ==================================================================
   3. 单行文章列表（横向滚动）
   ================================================================== */
class VDP_Widget_OnelinePosts extends VDP_Widget {

    public function __construct() {
        parent::__construct(
            'vdp_widget_oneline_posts',
            '【文库】单行文章列表',
            ['description' => '一行展示多篇文章，自动横向滚动，节省侧栏空间']
        );
    }

    protected function fields() {
        return [
            'limit' => [
                'type'    => 'number',
                'label'   => '显示数量',
                'default' => 8,
            ],
            'orderby' => [
                'type'    => 'select',
                'label'   => '排序方式',
                'default' => 'views',
                'options' => [
                    'views'     => '浏览最多',
                    'downloads' => '下载最多',
                    'comment'   => '评论最多',
                    'date'      => '最新发布',
                    'rand'      => '随机',
                ],
            ],
            'limit_day' => [
                'type'    => 'number',
                'label'   => '限制最近 N 天（0 = 不限）',
                'default' => 0,
            ],
            'cat' => [
                'type'    => 'text',
                'label'   => '分类 ID（逗号分隔，留空 = 全部）',
                'default' => '',
            ],
            'autoplay' => [
                'type'    => 'checkbox',
                'label'   => '自动滚动',
                'default' => 1,
            ],
            'interval' => [
                'type'    => 'number',
                'label'   => '滚动间隔（毫秒）',
                'default' => 3000,
            ],
        ];
    }

    protected function render($args, $instance) {
        $orderby = $instance['orderby'] ?: 'views';
        $q_args = [
            'post_status'         => 'publish',
            'post_type'           => 'post',
            'posts_per_page'      => max(1, (int)$instance['limit']),
            'ignore_sticky_posts' => 1,
            'no_found_rows'       => true,
        ];
        if (!empty($instance['cat'])) {
            $q_args['cat'] = str_replace('，', ',', $instance['cat']);
        }
        if ($orderby === 'views' || $orderby === 'downloads') {
            $q_args['meta_key'] = $orderby === 'views' ? 'views' : 'vdp_downloads';
            $q_args['orderby']  = 'meta_value_num';
            $q_args['order']    = 'DESC';
        } elseif ($orderby === 'comment') {
            $q_args['orderby'] = 'comment_count';
            $q_args['order']   = 'DESC';
        } elseif ($orderby === 'rand') {
            $q_args['orderby'] = 'rand';
        } else {
            $q_args['orderby'] = 'date';
            $q_args['order']   = 'DESC';
        }
        if (!empty($instance['limit_day']) && (int)$instance['limit_day'] > 0) {
            $q_args['date_query'] = [[
                'after'     => date('Y-m-d', strtotime('-' . (int)$instance['limit_day'] . ' days')),
                'inclusive' => true,
            ]];
        }

        $q = new WP_Query($q_args);
        if (!$q->have_posts()) {
            echo '<p class="vdp-widget-empty">暂无文章</p>';
            return;
        }
        $autoplay = !empty($instance['autoplay']) ? '1' : '0';
        $itv      = max(1000, (int)$instance['interval']);
        ?>
        <div class="vdp-oneline-posts" data-autoplay="<?php echo $autoplay; ?>" data-interval="<?php echo $itv; ?>">
            <div class="vdp-oneline-track">
                <?php while ($q->have_posts()): $q->the_post();
                    $thumb = get_the_post_thumbnail_url(get_the_ID(), 'thumbnail');
                    if (!$thumb) {
                        $info  = function_exists('vdp_get_doc_info') ? vdp_get_doc_info(get_the_ID()) : [];
                        $thumb = function_exists('vdp_get_doc_thumbnail') ? vdp_get_doc_thumbnail(get_the_ID(), 1, 'small') : '';
                    }
                    $views = (int) get_post_meta(get_the_ID(), 'views', true);
                ?>
                    <a class="vdp-oneline-item" href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
                        <?php if ($thumb): ?>
                            <span class="vdp-oneline-thumb" style="background-image:url('<?php echo esc_url($thumb); ?>')"></span>
                        <?php else: ?>
                            <span class="vdp-oneline-thumb vdp-oneline-thumb-empty"><i class="fa fa-file-text-o"></i></span>
                        <?php endif; ?>
                        <span class="vdp-oneline-title"><?php the_title(); ?></span>
                        <?php if ($views): ?>
                            <span class="vdp-oneline-meta"><i class="fa fa-eye"></i> <?php echo $views; ?></span>
                        <?php endif; ?>
                    </a>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
        </div>
        <script>
        (function(){
            document.querySelectorAll('.vdp-oneline-posts').forEach(function(box){
                if (box.__vdpInited) return; box.__vdpInited = true;
                if (box.getAttribute('data-autoplay') !== '1') return;
                var track = box.querySelector('.vdp-oneline-track');
                if (!track) return;
                var itv = parseInt(box.getAttribute('data-interval')||'3000', 10);
                var step = 1, paused = false;
                box.addEventListener('mouseenter', function(){ paused = true; });
                box.addEventListener('mouseleave', function(){ paused = false; });
                setInterval(function(){
                    if (paused) return;
                    if (track.scrollLeft + box.clientWidth >= track.scrollWidth - 2) {
                        track.scrollTo({left: 0, behavior:'smooth'});
                    } else {
                        track.scrollBy({left: Math.max(120, Math.floor(box.clientWidth * 0.6)), behavior:'smooth'});
                    }
                }, itv);
            });
        })();
        </script>
        <?php
    }
}
