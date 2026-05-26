<?php
/**
 * 复刻子比的几个标志性小工具：
 *   - VDP_Widget_Notice         滚动公告（多条文字 + 图标，垂直滚动）
 *   - VDP_Widget_LinksList      友情链接（list / card / image / bigcard）
 *   - VDP_Widget_Yiyan          一言（每日鸡汤 / 人生格言）
 *   - VDP_Widget_IconCard       图标卡片网格（纯文字 / 文字+背景图）
 *   - VDP_Widget_GraphicCover   图文/视频封面卡片（大图 + 标题 + 副标题）
 *   - VDP_Widget_NewComment     最近评论
 */

if (!defined('ABSPATH')) exit;

/* ==================================================================
   工具函数：解析多行配置（每行一项，| 分隔字段）
   ================================================================== */
if (!function_exists('vdp_parse_lines')) {
    function vdp_parse_lines($text, $field_keys) {
        $lines = preg_split("/\r\n|\n|\r/", trim((string) $text));
        $items = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) continue;
            $parts = array_map('trim', explode('|', $line));
            $item  = [];
            foreach ($field_keys as $i => $k) {
                $item[$k] = isset($parts[$i]) ? $parts[$i] : '';
            }
            $items[] = $item;
        }
        return $items;
    }
}

/* ==================================================================
   1. 滚动公告
   ================================================================== */
class VDP_Widget_Notice extends VDP_Widget {

    public function __construct() {
        parent::__construct(
            'vdp_widget_notice',
            '【文库】滚动公告',
            ['description' => '复刻子比滚动公告：多条消息垂直滚动展示']
        );
    }

    protected function fields() {
        return [
            'items' => [
                'type'    => 'textarea',
                'label'   => '公告条目（每行一条，格式：标题|FA图标|链接）',
                'default' => "欢迎来到虚拟文库|fa-bullhorn|\n海量文档每日更新|fa-cloud-download|\n开通会员享免费下载|fa-vip|",
                'desc'    => '链接可留空（不可点击）。图标使用 Font Awesome class，如 fa-home / fa-fire',
            ],
            'color' => [
                'type'    => 'select',
                'label'   => '配色',
                'default' => 'c-blue',
                'options' => [
                    'c-blue'   => '透明蓝',
                    'c-red'    => '透明红',
                    'c-yellow' => '透明黄',
                    'c-green'  => '透明绿',
                    'c-purple' => '透明紫',
                    'b-theme'  => '主题色实底',
                    'b-red'    => '红色实底',
                    'b-blue'   => '蓝色实底',
                    'b-green'  => '绿色实底',
                ],
            ],
            'radius' => [
                'type'    => 'checkbox',
                'label'   => '两端圆形',
                'default' => 0,
            ],
            'blank' => [
                'type'    => 'checkbox',
                'label'   => '链接新窗口打开',
                'default' => 1,
            ],
            'interval' => [
                'type'    => 'number',
                'label'   => '滚动间隔（毫秒）',
                'default' => 4000,
            ],
        ];
    }

    protected function render($args, $instance) {
        $items = vdp_parse_lines($instance['items'], ['title', 'icon', 'href']);
        if (empty($items)) {
            echo '<p class="vdp-widget-empty">未配置公告</p>';
            return;
        }
        $blank = !empty($instance['blank']) ? ' target="_blank" rel="noopener"' : '';
        $cls   = esc_attr($instance['color']) . (!empty($instance['radius']) ? ' radius' : ' radius8');
        $itv   = max(1500, (int) $instance['interval']);
        ?>
        <div class="vdp-notice-bar <?php echo $cls; ?>" data-interval="<?php echo $itv; ?>">
            <div class="vdp-notice-track">
                <?php foreach ($items as $it) :
                    if ($it['title'] === '') continue;
                    $icon = $it['icon'] ? '<i class="vdp-notice-icon fa ' . esc_attr($it['icon']) . '"></i>' : '';
                    $a_open = $it['href'] ? '<a' . $blank . ' href="' . esc_url($it['href']) . '">' : '<span>';
                    $a_close = $it['href'] ? '</a>' : '</span>';
                ?>
                    <div class="vdp-notice-item">
                        <?php echo $a_open; ?>
                            <?php echo $icon; ?>
                            <span class="vdp-notice-text"><?php echo esc_html($it['title']); ?></span>
                        <?php echo $a_close; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}

/* ==================================================================
   2. 友情链接（多样式）
   ================================================================== */
class VDP_Widget_LinksList extends VDP_Widget {

    public function __construct() {
        parent::__construct(
            'vdp_widget_links_list',
            '【文库】链接列表',
            ['description' => '复刻子比链接列表：list / card / image / bigcard 四种样式']
        );
    }

    protected function fields() {
        return [
            'style' => [
                'type'    => 'select',
                'label'   => '样式',
                'default' => 'list',
                'options' => [
                    'list'    => '列表（小图 + 标题）',
                    'card'    => '卡片（小图 + 标题 + 描述）',
                    'image'   => '图片（仅图标）',
                    'bigcard' => '大卡片（大图 + 标题 + 描述）',
                ],
            ],
            'cols' => [
                'type'    => 'select',
                'label'   => '每行列数',
                'default' => '3',
                'options' => ['2' => '2 列', '3' => '3 列', '4' => '4 列', '5' => '5 列'],
            ],
            'items' => [
                'type'    => 'textarea',
                'label'   => '链接（每行一条，格式：标题|图标URL|链接|描述）',
                'default' => "WordPress|https://s.w.org/style/images/about/WordPress-logotype-standard.png|https://wordpress.org|开源博客系统\n",
                'desc'    => '描述仅在 card / bigcard 样式显示',
            ],
            'blank' => [
                'type'    => 'checkbox',
                'label'   => '新窗口打开',
                'default' => 1,
            ],
        ];
    }

    protected function render($args, $instance) {
        $items = vdp_parse_lines($instance['items'], ['title', 'icon', 'href', 'desc']);
        if (empty($items)) {
            echo '<p class="vdp-widget-empty">请配置链接</p>';
            return;
        }
        $style = $instance['style'];
        $cols  = max(2, (int) $instance['cols']);
        $blank = !empty($instance['blank']) ? ' target="_blank" rel="noopener"' : '';
        ?>
        <div class="vdp-links vdp-links--<?php echo esc_attr($style); ?> vdp-links--cols-<?php echo $cols; ?>">
            <?php foreach ($items as $it) :
                if ($it['title'] === '' && $it['icon'] === '') continue;
                $href = $it['href'] ?: 'javascript:;';
                $img  = $it['icon'];
                $desc = $it['desc'];
            ?>
                <a class="vdp-link-item" href="<?php echo esc_url($href); ?>"<?php echo $blank; ?>>
                    <?php if ($style === 'image') : ?>
                        <span class="vdp-link-img-wrap">
                            <?php if ($img) : ?>
                                <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($it['title']); ?>" loading="lazy">
                            <?php else : ?>
                                <span class="vdp-link-text-fallback"><?php echo esc_html(mb_substr($it['title'], 0, 1)); ?></span>
                            <?php endif; ?>
                        </span>
                    <?php elseif ($style === 'bigcard') : ?>
                        <span class="vdp-link-bigcard">
                            <?php if ($img) : ?>
                                <span class="vdp-link-bg" style="background-image:url('<?php echo esc_url($img); ?>')"></span>
                            <?php endif; ?>
                            <span class="vdp-link-mask"></span>
                            <span class="vdp-link-meta">
                                <span class="vdp-link-title"><?php echo esc_html($it['title']); ?></span>
                                <?php if ($desc) : ?><span class="vdp-link-desc"><?php echo esc_html($desc); ?></span><?php endif; ?>
                            </span>
                        </span>
                    <?php else : /* list / card */ ?>
                        <?php if ($img) : ?>
                            <span class="vdp-link-icon">
                                <img src="<?php echo esc_url($img); ?>" alt="" loading="lazy">
                            </span>
                        <?php else : ?>
                            <span class="vdp-link-icon vdp-link-icon-placeholder">
                                <?php echo esc_html(mb_substr($it['title'], 0, 1)); ?>
                            </span>
                        <?php endif; ?>
                        <span class="vdp-link-meta">
                            <span class="vdp-link-title"><?php echo esc_html($it['title']); ?></span>
                            <?php if ($style === 'card' && $desc) : ?>
                                <span class="vdp-link-desc"><?php echo esc_html($desc); ?></span>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php
    }
}
/* ==================================================================
   3. 一言（每日鸡汤）
   ================================================================== */
class VDP_Widget_Yiyan extends VDP_Widget {

    public function __construct() {
        parent::__construct(
            'vdp_widget_yiyan',
            '【文库】一言',
            ['description' => '每日鸡汤 / 名人名言 / 励志短句，支持远程 API 或本地文案']
        );
    }

    protected function fields() {
        return [
            'source' => [
                'type'    => 'select',
                'label'   => '数据来源',
                'default' => 'local',
                'options' => [
                    'local'  => '本地文案（随机一条）',
                    'hitokoto' => 'Hitokoto API（远程）',
                ],
            ],
            'items' => [
                'type'    => 'textarea',
                'label'   => '本地文案（每行一句，支持 内容||出处）',
                'default' => "知识就是力量。||培根\n书是人类进步的阶梯。||高尔基\n纸上得来终觉浅，绝知此事要躬行。||陆游",
            ],
            'auto_refresh' => [
                'type'    => 'checkbox',
                'label'   => '自动换一条（每 30 秒）',
                'default' => 0,
            ],
            'show_quote_icon' => [
                'type'    => 'checkbox',
                'label'   => '显示引号装饰',
                'default' => 1,
            ],
        ];
    }

    protected function render($args, $instance) {
        $items  = vdp_parse_lines($instance['items'], ['text', 'from']);
        if (empty($items) && $instance['source'] === 'local') {
            echo '<p class="vdp-widget-empty">请配置一言文案</p>';
            return;
        }

        $auto = !empty($instance['auto_refresh']) ? ' data-refresh="30"' : '';
        $source = esc_attr($instance['source']);

        $payload = ($instance['source'] === 'local')
            ? wp_json_encode(array_values($items))
            : '[]';
        ?>
        <div class="vdp-yiyan" data-source="<?php echo $source; ?>"<?php echo $auto; ?>
             data-items='<?php echo esc_attr($payload); ?>'>
            <?php if (!empty($instance['show_quote_icon'])) : ?>
                <i class="vdp-yiyan-quote fa fa-quote-left"></i>
            <?php endif; ?>
            <div class="vdp-yiyan-text"><?php
                if (!empty($items)) {
                    echo esc_html($items[0]['text']);
                } else {
                    echo '加载中...';
                }
            ?></div>
            <div class="vdp-yiyan-from"><?php
                if (!empty($items) && !empty($items[0]['from'])) {
                    echo '— ' . esc_html($items[0]['from']);
                }
            ?></div>
            <button type="button" class="vdp-yiyan-refresh" title="换一条">
                <i class="fa fa-refresh"></i>
            </button>
        </div>
        <?php
    }
}

/* ==================================================================
   4. 图标卡片网格（zib_widget_ui_icon_card / icon_cover_card 合并版）
   ================================================================== */
class VDP_Widget_IconCard extends VDP_Widget {

    public function __construct() {
        parent::__construct(
            'vdp_widget_icon_card',
            '【文库】图标卡片',
            ['description' => '复刻子比图标卡片，支持纯图标 / 带封面图两种']
        );
    }

    protected function fields() {
        return [
            'style' => [
                'type'    => 'select',
                'label'   => '样式',
                'default' => 'icon',
                'options' => [
                    'icon'  => '纯图标卡片（图标 + 标题 + 描述）',
                    'cover' => '封面图卡片（背景图 + 标题）',
                ],
            ],
            'cols' => [
                'type'    => 'select',
                'label'   => '每行列数',
                'default' => '3',
                'options' => ['2' => '2 列', '3' => '3 列', '4' => '4 列', '6' => '6 列'],
            ],
            'items' => [
                'type'    => 'textarea',
                'label'   => '卡片项（每行：标题|图标class或封面URL|链接|描述|颜色）',
                'default' => "教程|fa-graduation-cap||入门到精通|#42a5f5\n下载|fa-download||海量素材|#66bb6a\n会员|fa-vip||专享特权|#ff7043",
                'desc'    => '图标卡片用 Font Awesome class（如 fa-home），封面图卡片用图片 URL。颜色仅图标卡片有效',
            ],
            'blank' => [
                'type'    => 'checkbox',
                'label'   => '新窗口打开',
                'default' => 0,
            ],
        ];
    }

    protected function render($args, $instance) {
        $items = vdp_parse_lines($instance['items'], ['title', 'icon', 'href', 'desc', 'color']);
        if (empty($items)) {
            echo '<p class="vdp-widget-empty">请配置卡片项</p>';
            return;
        }
        $style = $instance['style'];
        $cols  = (int) $instance['cols'];
        $blank = !empty($instance['blank']) ? ' target="_blank" rel="noopener"' : '';
        ?>
        <div class="vdp-icon-cards vdp-icon-cards--<?php echo esc_attr($style); ?> vdp-icon-cards--cols-<?php echo $cols; ?>">
            <?php foreach ($items as $it) :
                if ($it['title'] === '' && $it['icon'] === '') continue;
                $href = $it['href'] ?: 'javascript:;';
                if ($style === 'cover') :
                    $bg = $it['icon'] ? ' style="background-image:url(\'' . esc_url($it['icon']) . '\')"' : '';
                ?>
                    <a class="vdp-icon-card vdp-icon-card--cover" href="<?php echo esc_url($href); ?>"<?php echo $blank; ?>>
                        <span class="vdp-icon-card-bg"<?php echo $bg; ?>></span>
                        <span class="vdp-icon-card-mask"></span>
                        <span class="vdp-icon-card-body">
                            <span class="vdp-icon-card-title"><?php echo esc_html($it['title']); ?></span>
                            <?php if ($it['desc']) : ?>
                                <span class="vdp-icon-card-desc"><?php echo esc_html($it['desc']); ?></span>
                            <?php endif; ?>
                        </span>
                    </a>
                <?php else :
                    $color = $it['color'] ?: 'var(--theme-color)';
                    $tint  = ' style="color:' . esc_attr($color) . ';--this-bg:' . esc_attr($color) . ';"';
                ?>
                    <a class="vdp-icon-card vdp-icon-card--icon" href="<?php echo esc_url($href); ?>"<?php echo $blank; ?>>
                        <span class="vdp-icon-card-icon"<?php echo $tint; ?>>
                            <i class="fa <?php echo esc_attr($it['icon'] ?: 'fa-circle'); ?>"></i>
                        </span>
                        <span class="vdp-icon-card-title"><?php echo esc_html($it['title']); ?></span>
                        <?php if ($it['desc']) : ?>
                            <span class="vdp-icon-card-desc"><?php echo esc_html($it['desc']); ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif;
            endforeach; ?>
        </div>
        <?php
    }
}

/* ==================================================================
   5. 图文/视频封面卡片（zib_widget_ui_graphic_cover）
   ================================================================== */
class VDP_Widget_GraphicCover extends VDP_Widget {

    public function __construct() {
        parent::__construct(
            'vdp_widget_graphic_cover',
            '【文库】图文封面卡片',
            ['description' => '复刻子比图文/视频封面：大图 + 标题 + 副标题，可点击跳转']
        );
    }

    protected function fields() {
        return [
            'image' => [
                'type'    => 'text',
                'label'   => '封面图 URL',
                'default' => '',
            ],
            'video' => [
                'type'    => 'text',
                'label'   => '视频 URL（可选，填写则前置播放）',
                'default' => '',
                'desc'    => '支持 mp4 直链；填写后会显示视频，封面图作为 poster',
            ],
            'title' => [
                'type'    => 'text',
                'label'   => '主标题',
                'default' => '',
            ],
            'subtitle' => [
                'type'    => 'text',
                'label'   => '副标题',
                'default' => '',
            ],
            'href' => [
                'type'    => 'text',
                'label'   => '点击跳转链接',
                'default' => '',
            ],
            'btn_text' => [
                'type'    => 'text',
                'label'   => '按钮文字',
                'default' => '',
            ],
            'height' => [
                'type'    => 'select',
                'label'   => '高度',
                'default' => 'medium',
                'options' => [
                    'small'  => '矮（120px）',
                    'medium' => '中（180px）',
                    'large'  => '高（260px）',
                ],
            ],
            'blank' => [
                'type'    => 'checkbox',
                'label'   => '新窗口打开',
                'default' => 1,
            ],
        ];
    }

    protected function render($args, $instance) {
        $img   = trim($instance['image']);
        $video = trim($instance['video']);
        $title = trim($instance['title']);
        $sub   = trim($instance['subtitle']);
        $href  = trim($instance['href']);

        if (!$img && !$video && !$title) {
            echo '<p class="vdp-widget-empty">请填写图片或标题</p>';
            return;
        }

        $blank   = !empty($instance['blank']) ? ' target="_blank" rel="noopener"' : '';
        $h_class = 'vdp-cover-' . esc_attr($instance['height']);
        $tag     = $href ? 'a' : 'div';
        $href_at = $href ? ' href="' . esc_url($href) . '"' . $blank : '';
        ?>
        <<?php echo $tag; ?> class="vdp-graphic-cover <?php echo $h_class; ?>"<?php echo $href_at; ?>>
            <?php if ($video) : ?>
                <video class="vdp-cover-media"
                       <?php if ($img) echo 'poster="' . esc_url($img) . '"'; ?>
                       muted loop playsinline autoplay>
                    <source src="<?php echo esc_url($video); ?>" type="video/mp4">
                </video>
            <?php elseif ($img) : ?>
                <img class="vdp-cover-media" src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy">
            <?php endif; ?>
            <span class="vdp-cover-mask"></span>
            <div class="vdp-cover-meta">
                <?php if ($title) : ?>
                    <h4 class="vdp-cover-title"><?php echo esc_html($title); ?></h4>
                <?php endif; ?>
                <?php if ($sub) : ?>
                    <p class="vdp-cover-sub"><?php echo esc_html($sub); ?></p>
                <?php endif; ?>
                <?php if (!empty($instance['btn_text'])) : ?>
                    <span class="vdp-cover-btn"><?php echo esc_html($instance['btn_text']); ?> <i class="fa fa-arrow-right"></i></span>
                <?php endif; ?>
            </div>
        </<?php echo $tag; ?>>
        <?php
    }
}

/* ==================================================================
   6. 最近评论
   ================================================================== */
class VDP_Widget_NewComment extends VDP_Widget {

    public function __construct() {
        parent::__construct(
            'vdp_widget_new_comment',
            '【文库】最近评论',
            ['description' => '复刻子比最近评论：头像 + 用户名 + 评论摘要 + 文章标题']
        );
    }

    protected function fields() {
        return [
            'count' => [
                'type'    => 'number',
                'label'   => '显示数量',
                'default' => 6,
            ],
            'excerpt_length' => [
                'type'    => 'number',
                'label'   => '评论摘要字数',
                'default' => 40,
            ],
            'show_avatar' => [
                'type'    => 'checkbox',
                'label'   => '显示头像',
                'default' => 1,
            ],
            'show_post' => [
                'type'    => 'checkbox',
                'label'   => '显示来源文章标题',
                'default' => 1,
            ],
        ];
    }

    protected function render($args, $instance) {
        $count = max(1, (int) $instance['count']);
        $cmts  = get_comments([
            'number'  => $count,
            'status'  => 'approve',
            'type'    => 'comment',
            'orderby' => 'comment_date_gmt',
            'order'   => 'DESC',
            'post_status' => 'publish',
        ]);

        if (empty($cmts)) {
            echo '<p class="vdp-widget-empty">暂无评论</p>';
            return;
        }

        $excerpt_len = max(8, (int) $instance['excerpt_length']);
        echo '<ul class="vdp-new-comments">';
        foreach ($cmts as $c) {
            $name = $c->comment_author ?: '匿名';
            $url  = get_comment_link($c);
            $text = wp_strip_all_tags($c->comment_content);
            if (mb_strlen($text) > $excerpt_len) {
                $text = mb_substr($text, 0, $excerpt_len) . '…';
            }
            $post_title = get_the_title($c->comment_post_ID);
            ?>
            <li class="vdp-new-comment-item">
                <?php if (!empty($instance['show_avatar'])) : ?>
                    <a href="<?php echo esc_url($url); ?>" class="vdp-new-comment-avatar">
                        <?php echo get_avatar($c, 36); ?>
                    </a>
                <?php endif; ?>
                <div class="vdp-new-comment-body">
                    <div class="vdp-new-comment-meta">
                        <span class="vdp-new-comment-name"><?php echo esc_html($name); ?></span>
                        <span class="vdp-new-comment-time"><?php echo esc_html(human_time_diff(strtotime($c->comment_date_gmt), current_time('U'))); ?>前</span>
                    </div>
                    <a class="vdp-new-comment-text" href="<?php echo esc_url($url); ?>"><?php echo esc_html($text); ?></a>
                    <?php if (!empty($instance['show_post']) && $post_title) : ?>
                        <a class="vdp-new-comment-post" href="<?php echo esc_url(get_permalink($c->comment_post_ID)); ?>">
                            <i class="fa fa-file-text-o"></i> <?php echo esc_html($post_title); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </li>
            <?php
        }
        echo '</ul>';
    }
}
/* ==================================================================
   7. 用户排行榜（zib_widget_ui_user_ranking 升级版）
   下载/积分/评论三种维度
   ================================================================== */
class VDP_Widget_UserRanking extends VDP_Widget {

    public function __construct() {
        parent::__construct(
            'vdp_widget_user_ranking',
            '【文库】用户排行榜',
            ['description' => '按下载/积分/评论数维度展示活跃用户']
        );
    }

    protected function fields() {
        return [
            'rank_type' => [
                'type'    => 'select',
                'label'   => '排行依据',
                'default' => 'downloads',
                'options' => [
                    'downloads' => '下载次数',
                    'points'    => '积分（user_points / vdp_points）',
                    'comments'  => '评论数',
                    'register'  => '最新注册',
                ],
            ],
            'count' => [
                'type'    => 'number',
                'label'   => '显示数量',
                'default' => 10,
            ],
            'days' => [
                'type'    => 'number',
                'label'   => '统计周期（天，0 = 全部时间）',
                'default' => 30,
                'desc'    => '仅 评论 维度生效',
            ],
            'show_count' => [
                'type'    => 'checkbox',
                'label'   => '显示数值',
                'default' => 1,
            ],
            'show_rank_icon' => [
                'type'    => 'checkbox',
                'label'   => '前 3 名显示奖牌图标',
                'default' => 1,
            ],
        ];
    }

    protected function render($args, $instance) {
        global $wpdb;
        $type   = $instance['rank_type'];
        $limit  = max(1, (int) $instance['count']);
        $days   = max(0, (int) $instance['days']);

        $rows = [];
        $unit = '';

        if ($type === 'comments') {
            $where_date = $days > 0
                ? $wpdb->prepare("AND comment_date_gmt >= %s", gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS))
                : '';
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT user_id, COUNT(*) AS num
                 FROM {$wpdb->comments}
                 WHERE comment_approved = '1' AND user_id > 0 {$where_date}
                 GROUP BY user_id ORDER BY num DESC LIMIT %d",
                $limit
            ));
            $unit = '条评论';
        } elseif ($type === 'register') {
            $users = get_users([
                'number'  => $limit,
                'orderby' => 'registered',
                'order'   => 'DESC',
                'fields'  => ['ID'],
            ]);
            foreach ($users as $u) {
                $rows[] = (object)['user_id' => $u->ID, 'num' => 0];
            }
        } else {
            $meta_key = $type === 'points' ? 'vdp_points' : 'vdp_downloads_count';
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT user_id, CAST(meta_value AS UNSIGNED) AS num
                 FROM {$wpdb->usermeta}
                 WHERE meta_key = %s AND CAST(meta_value AS UNSIGNED) > 0
                 ORDER BY num DESC LIMIT %d",
                $meta_key, $limit
            ));
            $unit = $type === 'points' ? '积分' : '次下载';
        }

        if (empty($rows)) {
            echo '<p class="vdp-widget-empty">暂无数据</p>';
            return;
        }

        $show_icon  = !empty($instance['show_rank_icon']);
        $show_count = !empty($instance['show_count']) && $type !== 'register';
        ?>
        <ol class="vdp-user-ranking">
            <?php $i = 0; foreach ($rows as $r) :
                $u = get_userdata($r->user_id);
                if (!$u) continue;
                $i++;
                $rank_class = $i <= 3 ? 'top-' . $i : 'normal';
                $author_url = get_author_posts_url($u->ID);
            ?>
                <li class="vdp-rank-user <?php echo $rank_class; ?>">
                    <span class="vdp-rank-num">
                        <?php if ($show_icon && $i <= 3) : ?>
                            <i class="fa fa-trophy"></i>
                        <?php else : ?>
                            <?php echo $i; ?>
                        <?php endif; ?>
                    </span>
                    <a href="<?php echo esc_url($author_url); ?>" class="vdp-rank-avatar">
                        <?php echo get_avatar($u->ID, 40); ?>
                    </a>
                    <div class="vdp-rank-body">
                        <a href="<?php echo esc_url($author_url); ?>" class="vdp-rank-name">
                            <?php echo esc_html($u->display_name); ?>
                        </a>
                        <?php if ($show_count) : ?>
                            <span class="vdp-rank-count"><?php echo (int) $r->num; ?> <?php echo $unit; ?></span>
                        <?php elseif ($type === 'register') : ?>
                            <span class="vdp-rank-count"><?php echo esc_html(human_time_diff(strtotime($u->user_registered), current_time('U'))); ?>前注册</span>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
        <?php
    }
}


/**
 * 横滚标签栏（参考子比 term-aggregation）：一行多色彩分类胶囊，可横向滚动
 */
class VDP_Widget_TermAggregation extends VDP_Widget {

    public function __construct() {
        parent::__construct(
            'vdp_term_aggregation',
            '[文库] 横滚分类聚合',
            ['description' => '一行可横向滚动的多色彩分类胶囊（参考子比 term-aggregation）']
        );
    }

    protected function fields() {
        return [
            'taxonomy' => [
                'type'    => 'select',
                'label'   => '分类法',
                'default' => 'category',
                'options' => [
                    'category' => '文章分类',
                    'post_tag' => '标签',
                ],
            ],
            'count' => [
                'type'    => 'number',
                'label'   => '显示数量（0=全部）',
                'default' => 20,
            ],
            'orderby' => [
                'type'    => 'select',
                'label'   => '排序',
                'default' => 'count',
                'options' => [
                    'count' => '文章数（多→少）',
                    'name'  => '名称',
                    'id'    => 'ID',
                ],
            ],
            'show_count' => [
                'type'    => 'checkbox',
                'label'   => '显示文章数',
                'default' => 1,
            ],
            'colorful' => [
                'type'    => 'checkbox',
                'label'   => '彩色背景（按 ID 循环）',
                'default' => 1,
            ],
        ];
    }

    protected function render($args, $instance) {
        $tax     = !empty($instance['taxonomy']) ? $instance['taxonomy'] : 'category';
        $count   = (int) ($instance['count'] ?? 20);
        $orderby = !empty($instance['orderby']) ? $instance['orderby'] : 'count';

        $terms = get_terms([
            'taxonomy'   => $tax,
            'hide_empty' => true,
            'number'     => $count > 0 ? $count : 0,
            'orderby'    => $orderby,
            'order'      => $orderby === 'count' ? 'DESC' : 'ASC',
        ]);
        if (is_wp_error($terms) || empty($terms)) {
            echo '<p class="vdp-empty">暂无分类</p>';
            return;
        }

        $colorful   = !empty($instance['colorful']);
        $show_count = !empty($instance['show_count']);
        $palette    = ['#FF6B6B','#4ECDC4','#FFB400','#5DADE2','#A569BD','#48C9B0','#F39C12','#2ECC71','#E74C3C','#3498DB'];
        ?>
        <div class="vdp-term-agg<?php echo $colorful ? ' is-colorful' : ''; ?>">
            <div class="vdp-term-agg-track">
                <?php foreach ($terms as $i => $t) :
                    $bg = $colorful ? $palette[$t->term_id % count($palette)] : '';
                    $style = $bg ? ' style="background:' . esc_attr($bg) . '"' : '';
                    ?>
                    <a href="<?php echo esc_url(get_term_link($t)); ?>" class="vdp-term-pill"<?php echo $style; ?>>
                        <span class="vdp-term-name"><?php echo esc_html($t->name); ?></span>
                        <?php if ($show_count) : ?>
                            <span class="vdp-term-count"><?php echo (int) $t->count; ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}
