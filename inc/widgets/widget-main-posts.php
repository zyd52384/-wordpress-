<?php
/**
 * 借鉴子比 - 文章列表 (新)
 *  完整复刻功能：
 *   - 加载方式：直接 / AJAX 懒加载
 *   - 分类 / 专题（标签）筛选，支持 -1,-2 排除语法
 *   - 商品类型筛选：免费 / 付费下载 / VIP 免费
 *   - 发布时间限制（最近 N 天）
 *   - 排序：发布时间 / 修改时间 / 评论数 / 浏览数 / 下载数 / 价格 / 随机
 *   - 升序 / 降序
 *   - 三种样式：list（默认列表）/ card（卡片网格）/ mini（迷你列表，可选缩略图/编号/meta）
 *   - 显示数量 4–30
 *   - 翻页：不翻页 / AJAX 追加 / 数字翻页
 */
if (!defined('ABSPATH')) exit;

class VDP_Widget_MainPosts extends VDP_Widget {

    public function __construct() {
        parent::__construct(
            'vdp_widget_main_posts',
            '【文库】文章列表(新)',
            ['description' => '通过分类、专题、排序等筛选显示文章列表，支持列表/卡片/迷你三种样式 + AJAX 翻页']
        );
    }

    protected function fields() {
        return [
            'load_mode' => [
                'type'    => 'select',
                'label'   => '加载方式',
                'default' => 'detail',
                'options' => [
                    'detail' => '直接加载',
                    'ajax'   => 'AJAX 懒加载（提高首屏性能）',
                ],
            ],
            'cat' => [
                'type'    => 'text',
                'label'   => '分类限制（ID，逗号分隔，支持 -1 排除）',
                'default' => '',
            ],
            'topics' => [
                'type'    => 'text',
                'label'   => '专题/标签限制（标签 ID，逗号分隔，支持 -1 排除）',
                'default' => '',
            ],
            'pay_type' => [
                'type'    => 'select',
                'label'   => '商品类型筛选',
                'default' => '',
                'options' => [
                    ''       => '不筛选',
                    'free'   => '仅免费',
                    'paid'   => '仅付费下载',
                    'vip'    => '仅 VIP 免费',
                ],
            ],
            'limit_day' => [
                'type'    => 'number',
                'label'   => '发布时间限制（最近 N 天，0=不限）',
                'default' => 0,
            ],
            'orderby' => [
                'type'    => 'select',
                'label'   => '排序方式',
                'default' => 'date',
                'options' => [
                    'date'      => '发布时间',
                    'modified'  => '修改时间',
                    'comment'   => '评论数',
                    'views'     => '浏览数',
                    'downloads' => '下载数',
                    'price'     => '价格',
                    'rand'      => '随机',
                ],
            ],
            'order' => [
                'type'    => 'select',
                'label'   => '排序方向',
                'default' => 'desc',
                'options' => [
                    'desc' => '降序',
                    'asc'  => '升序',
                ],
            ],
            'style' => [
                'type'    => 'select',
                'label'   => '列表样式',
                'default' => 'list',
                'options' => [
                    'list' => '列表样式（横版大图+摘要）',
                    'card' => '卡片样式（网格）',
                    'mini' => 'mini 列表（紧凑，适合侧栏）',
                ],
            ],
            'mini_show_thumb' => [
                'type'    => 'checkbox',
                'label'   => '【mini】显示缩略图',
                'default' => 1,
            ],
            'mini_show_number' => [
                'type'    => 'checkbox',
                'label'   => '【mini】显示编号',
                'default' => 0,
            ],
            'mini_show_meta' => [
                'type'    => 'checkbox',
                'label'   => '【mini】显示作者/时间/浏览',
                'default' => 1,
            ],
            'count' => [
                'type'    => 'number',
                'label'   => '每页显示数量（4-30）',
                'default' => 12,
            ],
            'paginate' => [
                'type'    => 'select',
                'label'   => '翻页方式',
                'default' => '',
                'options' => [
                    ''       => '不翻页',
                    'ajax'   => 'AJAX 追加（"加载更多"按钮）',
                    'number' => '数字翻页',
                ],
            ],
        ];
    }

    protected function render($args, $instance) {
        $style = $instance['style'] ?: 'list';
        $instance['count'] = max(4, min(30, (int)$instance['count'] ?: 12));
        $widget_id = $args['widget_id'];
        ?>
        <div class="vdp-main-posts style-<?php echo esc_attr($style); ?>"
             data-widget-id="<?php echo esc_attr($widget_id); ?>"
             data-instance="<?php echo esc_attr(wp_json_encode($instance)); ?>">
            <?php
            if (($instance['load_mode'] ?? 'detail') === 'ajax') {
                self::render_placeholder($instance);
            } else {
                self::render_list($instance, 1);
            }
            ?>
        </div>
        <?php
    }

    /** 占位骨架（AJAX 懒加载时首次显示） */
    public static function render_placeholder($instance) {
        $style = $instance['style'] ?: 'list';
        $count = max(1, (int)$instance['count']);
        echo '<div class="vdp-main-posts-loading">';
        for ($i = 0; $i < $count; $i++) {
            if ($style === 'mini') {
                echo '<div class="vdp-skel-mini"><span class="vdp-skel-thumb"></span><span class="vdp-skel-line"></span></div>';
            } elseif ($style === 'card') {
                echo '<div class="vdp-skel-card"><div class="vdp-skel-img"></div><div class="vdp-skel-line"></div></div>';
            } else {
                echo '<div class="vdp-skel-list"><div class="vdp-skel-img"></div><div class="vdp-skel-body"><div class="vdp-skel-line"></div><div class="vdp-skel-line short"></div></div></div>';
            }
        }
        echo '</div>';
    }

    /** 构建 WP_Query 参数 */
    public static function build_query_args($instance, $paged = 1) {
        $args = [
            'post_status'         => 'publish',
            'post_type'           => 'post',
            'posts_per_page'      => max(1, (int)$instance['count']),
            'paged'               => max(1, (int)$paged),
            'ignore_sticky_posts' => 1,
        ];

        // 分类
        if (!empty($instance['cat'])) {
            $ids = self::parse_ids($instance['cat']);
            $in  = []; $out = [];
            foreach ($ids as $id) { $id < 0 ? $out[] = abs($id) : $in[] = $id; }
            if ($in)  $args['category__in']     = $in;
            if ($out) $args['category__not_in'] = $out;
        }

        // 专题（标签）
        if (!empty($instance['topics'])) {
            $ids = self::parse_ids($instance['topics']);
            $in  = []; $out = [];
            foreach ($ids as $id) { $id < 0 ? $out[] = abs($id) : $in[] = $id; }
            if ($in)  $args['tag__in']     = $in;
            if ($out) $args['tag__not_in'] = $out;
        }

        // 商品类型筛选（pay_modo: 0=免费 / 非0=付费；pay_limit>0 = VIP 免费）
        if (!empty($instance['pay_type'])) {
            $mq = ['relation' => 'AND'];
            switch ($instance['pay_type']) {
                case 'free':
                    $mq[] = ['relation' => 'OR',
                        ['key' => 'pay_modo', 'compare' => 'NOT EXISTS'],
                        ['key' => 'pay_modo', 'value' => '0', 'compare' => '='],
                    ];
                    break;
                case 'paid':
                    $mq[] = ['key' => 'pay_modo', 'value' => '0', 'compare' => '!='];
                    $mq[] = ['key' => 'pay_modo', 'compare' => 'EXISTS'];
                    break;
                case 'vip':
                    $mq[] = ['key' => 'pay_limit', 'value' => '0', 'compare' => '>', 'type' => 'NUMERIC'];
                    break;
            }
            if (count($mq) > 1) $args['meta_query'] = $mq;
        }

        // 时间限制
        if (!empty($instance['limit_day']) && (int)$instance['limit_day'] > 0) {
            $args['date_query'] = [[
                'after'     => date('Y-m-d', strtotime('-' . (int)$instance['limit_day'] . ' days')),
                'inclusive' => true,
            ]];
        }

        // 排序
        $order   = (isset($instance['order']) && $instance['order'] === 'asc') ? 'ASC' : 'DESC';
        $orderby = $instance['orderby'] ?: 'date';
        $args['order'] = $order;
        switch ($orderby) {
            case 'modified': $args['orderby'] = 'modified'; break;
            case 'comment':  $args['orderby'] = 'comment_count'; break;
            case 'rand':     $args['orderby'] = 'rand'; break;
            case 'views':
                $args['meta_key'] = 'views'; $args['orderby'] = 'meta_value_num'; break;
            case 'downloads':
                $args['meta_key'] = 'vdp_downloads'; $args['orderby'] = 'meta_value_num'; break;
            case 'price':
                $args['meta_key'] = 'pay_price'; $args['orderby'] = 'meta_value_num'; break;
            default:         $args['orderby'] = 'date'; break;
        }

        if (empty($instance['paginate'])) {
            $args['no_found_rows'] = true;
        }
        return $args;
    }

    private static function parse_ids($str) {
        $arr = preg_split('/[,，\s]+/', (string)$str);
        $arr = array_filter(array_map('intval', $arr));
        return $arr;
    }

    /** 渲染单页列表（含分页 HTML） */
    public static function render_list($instance, $paged = 1) {
        $args  = self::build_query_args($instance, $paged);
        $query = new WP_Query($args);
        $style = $instance['style'] ?: 'list';

        if (!$query->have_posts()) {
            echo '<div class="vdp-empty">暂无内容</div>';
            return;
        }

        $wrap_open  = $style === 'card' ? '<div class="vdp-main-posts-grid">' : '<div class="vdp-main-posts-' . esc_attr($style) . '">';
        $wrap_close = '</div>';
        echo $wrap_open;

        $i = ($paged - 1) * (int)$instance['count'];
        while ($query->have_posts()) { $query->the_post(); $i++;
            if ($style === 'mini') {
                self::item_mini($instance, $i);
            } elseif ($style === 'card') {
                self::item_card();
            } else {
                self::item_list();
            }
        }
        wp_reset_postdata();
        echo $wrap_close;

        // 翻页
        $paginate = $instance['paginate'] ?? '';
        if ($paginate && $query->max_num_pages > 1) {
            if ($paginate === 'ajax' && $paged < $query->max_num_pages) {
                echo '<div class="vdp-main-posts-more"><button type="button" class="vdp-load-more-btn" data-next="' . ($paged + 1) . '">加载更多</button></div>';
            } elseif ($paginate === 'number') {
                echo '<div class="vdp-main-posts-pages">';
                for ($p = 1; $p <= $query->max_num_pages; $p++) {
                    $cls = $p === $paged ? ' is-active' : '';
                    echo '<a href="javascript:;" class="vdp-page' . $cls . '" data-page="' . $p . '">' . $p . '</a>';
                }
                echo '</div>';
            }
        }
    }

    /* ---------- 三种样式的单项渲染 ---------- */

    private static function item_list() {
        $pid   = get_the_ID();
        $thumb = function_exists('vdp_get_doc_thumbnail') ? vdp_get_doc_thumbnail($pid, 1, 'medium') : get_the_post_thumbnail_url($pid, 'medium');
        $views = (int) get_post_meta($pid, 'views', true);
        $dl    = (int) get_post_meta($pid, 'vdp_downloads', true);
        $info  = function_exists('vdp_get_doc_info') ? vdp_get_doc_info($pid) : ['is_free' => true, 'price' => 0];
        ?>
        <article class="vdp-mp-list-item">
            <a class="vdp-mp-list-thumb" href="<?php the_permalink(); ?>">
                <?php if ($thumb): ?><img src="<?php echo esc_url($thumb); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy"><?php endif; ?>
            </a>
            <div class="vdp-mp-list-body">
                <h3 class="vdp-mp-list-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                <div class="vdp-mp-list-excerpt"><?php echo esc_html(wp_trim_words(get_the_excerpt(), 36)); ?></div>
                <div class="vdp-mp-list-meta">
                    <span><i class="fa fa-clock-o"></i> <?php echo esc_html(human_time_diff(get_the_time('U'), current_time('timestamp'))); ?>前</span>
                    <span><i class="fa fa-eye"></i> <?php echo $views; ?></span>
                    <span><i class="fa fa-download"></i> <?php echo $dl; ?></span>
                    <span><i class="fa fa-comment-o"></i> <?php echo get_comments_number(); ?></span>
                    <?php if (!empty($info['is_free'])): ?>
                        <span class="vdp-tag vdp-tag-free">免费</span>
                    <?php elseif (!empty($info['vip_limit'])): ?>
                        <span class="vdp-tag vdp-tag-vip">VIP</span>
                    <?php else: ?>
                        <span class="vdp-tag vdp-tag-price">¥<?php echo number_format((float)$info['price'], 2); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </article>
        <?php
    }

    private static function item_card() {
        $pid   = get_the_ID();
        $thumb = function_exists('vdp_get_doc_thumbnail') ? vdp_get_doc_thumbnail($pid, 1, 'medium') : get_the_post_thumbnail_url($pid, 'medium');
        $views = (int) get_post_meta($pid, 'views', true);
        $info  = function_exists('vdp_get_doc_info') ? vdp_get_doc_info($pid) : ['is_free' => true, 'price' => 0];
        ?>
        <a class="vdp-mp-card" href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
            <div class="vdp-mp-card-thumb">
                <?php if ($thumb): ?><img src="<?php echo esc_url($thumb); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy">
                <?php else: ?><span class="vdp-mp-card-noimg"><i class="fa fa-file-text-o"></i></span><?php endif; ?>
                <?php if (!empty($info['is_free'])): ?>
                    <span class="vdp-mp-card-tag vdp-tag-free">免费</span>
                <?php elseif (!empty($info['vip_limit'])): ?>
                    <span class="vdp-mp-card-tag vdp-tag-vip">VIP</span>
                <?php else: ?>
                    <span class="vdp-mp-card-tag vdp-tag-price">¥<?php echo number_format((float)$info['price'], 2); ?></span>
                <?php endif; ?>
            </div>
            <div class="vdp-mp-card-title"><?php the_title(); ?></div>
            <div class="vdp-mp-card-meta">
                <span><i class="fa fa-eye"></i> <?php echo $views; ?></span>
                <span><i class="fa fa-comment-o"></i> <?php echo get_comments_number(); ?></span>
            </div>
        </a>
        <?php
    }

    private static function item_mini($instance, $number) {
        $pid   = get_the_ID();
        $thumb = !empty($instance['mini_show_thumb']) ? (function_exists('vdp_get_doc_thumbnail') ? vdp_get_doc_thumbnail($pid, 1, 'small') : get_the_post_thumbnail_url($pid, 'thumbnail')) : '';
        $views = (int) get_post_meta($pid, 'views', true);
        $show_number = !empty($instance['mini_show_number']);
        $show_meta   = !empty($instance['mini_show_meta']);
        $rank_cls    = $number <= 3 ? ' is-top' : '';
        ?>
        <div class="vdp-mp-mini">
            <?php if ($show_number): ?>
                <span class="vdp-mp-mini-num<?php echo $rank_cls; ?>"><?php echo $number; ?></span>
            <?php endif; ?>
            <?php if ($thumb): ?>
                <a class="vdp-mp-mini-thumb" href="<?php the_permalink(); ?>"><img src="<?php echo esc_url($thumb); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy"></a>
            <?php endif; ?>
            <div class="vdp-mp-mini-body">
                <a class="vdp-mp-mini-title" href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>"><?php the_title(); ?></a>
                <?php if ($show_meta): ?>
                    <div class="vdp-mp-mini-meta">
                        <span><?php the_author(); ?></span>
                        <span><?php echo esc_html(human_time_diff(get_the_time('U'), current_time('timestamp'))); ?>前</span>
                        <span><i class="fa fa-eye"></i> <?php echo $views; ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

/* ====================== AJAX 端点 ====================== */
add_action('wp_ajax_vdp_main_posts',        'vdp_main_posts_ajax');
add_action('wp_ajax_nopriv_vdp_main_posts', 'vdp_main_posts_ajax');
function vdp_main_posts_ajax() {
    $raw = isset($_POST['instance']) ? wp_unslash($_POST['instance']) : '';
    $instance = json_decode($raw, true);
    if (!is_array($instance)) wp_send_json_error('参数错误');
    $paged = max(1, (int) ($_POST['paged'] ?? 1));

    ob_start();
    VDP_Widget_MainPosts::render_list($instance, $paged);
    $html = ob_get_clean();

    wp_send_json_success(['html' => $html, 'paged' => $paged]);
}

/* ====================== 前端 JS ====================== */
add_action('wp_footer', function () { ?>
<script>
(function(){
    if (window.__vdpMainPostsBound) return; window.__vdpMainPostsBound = true;
    var ajaxUrl = (window.vdp_ajax && vdp_ajax.url) || (window.ajaxurl) || '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';

    function loadPage(box, paged, replace){
        var instance = box.getAttribute('data-instance');
        var btn = box.querySelector('.vdp-load-more-btn');
        if (btn) btn.disabled = true, btn.textContent = '加载中…';
        var fd = new FormData();
        fd.append('action', 'vdp_main_posts');
        fd.append('instance', instance);
        fd.append('paged', paged);
        fetch(ajaxUrl, {method:'POST', credentials:'same-origin', body: fd})
          .then(function(r){return r.json();})
          .then(function(res){
              if (!res || !res.success) { if (btn) btn.disabled=false, btn.textContent='加载更多'; return; }
              if (replace) {
                  box.innerHTML = res.data.html;
              } else {
                  var wrapper = box.querySelector('.vdp-main-posts-list,.vdp-main-posts-mini,.vdp-main-posts-grid');
                  var temp = document.createElement('div');
                  temp.innerHTML = res.data.html;
                  var newWrapper = temp.querySelector('.vdp-main-posts-list,.vdp-main-posts-mini,.vdp-main-posts-grid');
                  if (wrapper && newWrapper) {
                      Array.from(newWrapper.children).forEach(function(c){ wrapper.appendChild(c); });
                  }
                  // 移除旧的 more 按钮，注入新的
                  var oldMore = box.querySelector('.vdp-main-posts-more'); if (oldMore) oldMore.remove();
                  var newMore = temp.querySelector('.vdp-main-posts-more'); if (newMore) box.appendChild(newMore);
              }
          })
          .catch(function(){ if (btn) btn.disabled=false, btn.textContent='加载更多'; });
    }

    // 首次懒加载
    document.querySelectorAll('.vdp-main-posts').forEach(function(box){
        if (box.querySelector('.vdp-main-posts-loading')) {
            loadPage(box, 1, true);
        }
    });

    // 事件委托
    document.addEventListener('click', function(e){
        var more = e.target.closest('.vdp-load-more-btn');
        if (more) {
            var box = more.closest('.vdp-main-posts');
            var next = parseInt(more.getAttribute('data-next')||'2', 10);
            loadPage(box, next, false);
            return;
        }
        var page = e.target.closest('.vdp-page');
        if (page) {
            var box2 = page.closest('.vdp-main-posts');
            var p = parseInt(page.getAttribute('data-page')||'1', 10);
            loadPage(box2, p, true);
        }
    });
})();
</script>
<?php }, 99);
