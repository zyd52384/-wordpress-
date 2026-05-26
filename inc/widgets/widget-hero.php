<?php
/**
 * 首页大屏 Hero 小工具
 *  - 渐变背景 + 装饰图形
 *  - 主副标题
 *  - 大搜索框 + 热词
 *  - 底部 4 张统计/入口卡片
 */
if (!defined('ABSPATH')) exit;

class VDP_Widget_Hero extends VDP_Widget {

    public function __construct() {
        parent::__construct(
            'vdp_widget_hero',
            '【文库】首页大屏 Hero',
            ['description' => '顶部超大屏：渐变背景 + 大搜索框 + 热词 + 4 张统计卡片']
        );
    }

    protected function fields() {
        return [
            'title'        => ['type'=>'text', 'label'=>'主标题', 'default'=>'加入教务会员 涵盖老师所用资料'],
            'subtitle'     => ['type'=>'text', 'label'=>'副标题', 'default'=>'所有文档无限免费下载'],
            'placeholder'  => ['type'=>'text', 'label'=>'搜索框占位文字', 'default'=>'输入关键词，多个关键词用空格隔开'],
            'hot_words'    => ['type'=>'text', 'label'=>'热门关键词（逗号分隔）', 'default'=>'总结,开学第一课,班主任,评语,国旗下'],
            'bg_start'     => ['type'=>'text', 'label'=>'背景渐变-起始色（无图时使用）', 'default'=>'#1bb89a'],
            'bg_end'       => ['type'=>'text', 'label'=>'背景渐变-结束色（无图时使用）', 'default'=>'#34c8a4'],

            // 幻灯片背景图（每行一个 URL，最多 5 张）
            'slides'       => ['type'=>'textarea', 'label'=>'幻灯片背景图 URL（每行一个，留空则使用渐变背景）', 'default'=>''],
            'autoplay'     => ['type'=>'checkbox', 'label'=>'自动轮播', 'default'=>1],
            'interval'     => ['type'=>'number', 'label'=>'轮播间隔(秒)', 'default'=>5],

            'show_decor'   => ['type'=>'checkbox', 'label'=>'显示装饰图形', 'default'=>1],
            'height'       => ['type'=>'number', 'label'=>'高度(px)', 'default'=>520],

            // 4 张卡片
            'card1_icon'   => ['type'=>'text', 'label'=>'卡片1 图标(fa-)', 'default'=>'fa-crown'],
            'card1_title'  => ['type'=>'text', 'label'=>'卡片1 标题',     'default'=>'开通VIP会员'],
            'card1_tag'    => ['type'=>'text', 'label'=>'卡片1 角标',     'default'=>'HOT'],
            'card1_desc'   => ['type'=>'text', 'label'=>'卡片1 描述',     'default'=>'20000+加入了会员'],
            'card1_link'   => ['type'=>'text', 'label'=>'卡片1 链接',     'default'=>'#'],

            'card2_icon'   => ['type'=>'text', 'label'=>'卡片2 图标(fa-)', 'default'=>'fa-heart'],
            'card2_title'  => ['type'=>'text', 'label'=>'卡片2 标题',     'default'=>'原创作品'],
            'card2_tag'    => ['type'=>'text', 'label'=>'卡片2 角标',     'default'=>'TOP'],
            'card2_desc'   => ['type'=>'text', 'label'=>'卡片2 描述',     'default'=>'专业团队老师精心整理制作'],
            'card2_link'   => ['type'=>'text', 'label'=>'卡片2 链接',     'default'=>'#'],

            'card3_icon'   => ['type'=>'text', 'label'=>'卡片3 图标(fa-)', 'default'=>'fa-database'],
            'card3_title'  => ['type'=>'text', 'label'=>'卡片3 标题',     'default'=>'资源总数'],
            'card3_tag'    => ['type'=>'text', 'label'=>'卡片3 角标',     'default'=>'NEW'],
            'card3_desc'   => ['type'=>'text', 'label'=>'卡片3 描述',     'default'=>'超十万份材料每日更新'],
            'card3_link'   => ['type'=>'text', 'label'=>'卡片3 链接',     'default'=>'#'],

            'card4_icon'   => ['type'=>'text', 'label'=>'卡片4 图标(fa-)', 'default'=>'fa-bolt'],
            'card4_title'  => ['type'=>'text', 'label'=>'卡片4 标题',     'default'=>'限时优惠'],
            'card4_tag'    => ['type'=>'text', 'label'=>'卡片4 角标',     'default'=>'GO'],
            'card4_desc'   => ['type'=>'text', 'label'=>'卡片4 描述',     'default'=>'限时优惠火热进行中'],
            'card4_link'   => ['type'=>'text', 'label'=>'卡片4 链接',     'default'=>'#'],
        ];
    }

    protected function render($args, $instance) {
        $title    = $instance['title']    ?: '加入会员 享受全部资料';
        $subtitle = $instance['subtitle'] ?: '';
        $ph       = esc_attr($instance['placeholder'] ?: '输入关键词搜索');
        $bg1      = esc_attr($instance['bg_start'] ?: '#1bb89a');
        $bg2      = esc_attr($instance['bg_end']   ?: '#34c8a4');
        $h        = max(300, (int)($instance['height'] ?: 520));
        $words    = array_filter(array_map('trim', preg_split('/[,，\s]+/', (string)$instance['hot_words'])));
        $action   = esc_url(home_url('/'));

        $slides = array_filter(array_map('trim', preg_split('/[\r\n]+/', (string)($instance['slides'] ?? ''))));
        $slides = array_values(array_filter($slides, function($u){ return filter_var($u, FILTER_VALIDATE_URL); }));
        $autoplay = !empty($instance['autoplay']) ? 1 : 0;
        $interval = max(2, (int)($instance['interval'] ?: 5)) * 1000;
        $uid = 'vdp-hero-' . wp_rand(1000, 9999);
        ?>
        <section id="<?php echo esc_attr($uid); ?>" class="vdp-hero" style="--vdp-hero-bg1:<?php echo $bg1; ?>;--vdp-hero-bg2:<?php echo $bg2; ?>;min-height:<?php echo $h; ?>px;" data-autoplay="<?php echo $autoplay; ?>" data-interval="<?php echo (int)$interval; ?>">
            <div class="vdp-hero-slides" aria-hidden="true">
                <?php if ($slides): foreach ($slides as $i => $url): ?>
                    <div class="vdp-hero-slide<?php echo $i === 0 ? ' is-active' : ''; ?>" style="background-image:url('<?php echo esc_url($url); ?>');"></div>
                <?php endforeach; endif; ?>
            </div>

            <?php if (!empty($instance['show_decor'])): ?>
            <div class="vdp-hero-decor" aria-hidden="true">
                <span class="vdp-hero-circle c1"></span>
                <span class="vdp-hero-circle c2"></span>
                <span class="vdp-hero-circle c3"></span>
                <span class="vdp-hero-bar b1"></span>
                <span class="vdp-hero-bar b2"></span>
                <span class="vdp-hero-bar b3"></span>
                <span class="vdp-hero-bar b4"></span>
            </div>
            <?php endif; ?>

            <div class="vdp-hero-inner">
                <h1 class="vdp-hero-title"><?php echo esc_html($title); ?></h1>
                <?php if ($subtitle): ?><p class="vdp-hero-subtitle"><?php echo esc_html($subtitle); ?></p><?php endif; ?>

                <form class="vdp-hero-search" method="get" action="<?php echo $action; ?>" role="search">
                    <input type="search" name="s" class="vdp-hero-search-input" placeholder="<?php echo $ph; ?>" autocomplete="off">
                    <button type="submit" class="vdp-hero-search-btn" aria-label="搜索"><i class="fa fa-search"></i></button>
                </form>

                <?php if ($words): ?>
                <div class="vdp-hero-hotwords">
                    <?php foreach ($words as $w):
                        $url = add_query_arg('s', urlencode($w), home_url('/')); ?>
                        <a href="<?php echo esc_url($url); ?>" class="vdp-hero-hotword"><?php echo esc_html($w); ?></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if (count($slides) > 1): ?>
            <div class="vdp-hero-dots" role="tablist">
                <?php foreach ($slides as $i => $u): ?>
                    <button type="button" class="vdp-hero-dot<?php echo $i === 0 ? ' is-active' : ''; ?>" data-index="<?php echo $i; ?>" aria-label="切换到第 <?php echo $i + 1; ?> 张"></button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="vdp-hero-cards">
                <?php for ($i = 1; $i <= 4; $i++):
                    $title_i = $instance["card{$i}_title"] ?? '';
                    if (!$title_i) continue;
                    $icon = $instance["card{$i}_icon"] ?? '';
                    $tag  = $instance["card{$i}_tag"]   ?? '';
                    $desc = $instance["card{$i}_desc"]  ?? '';
                    $link = $instance["card{$i}_link"]  ?? '#';
                ?>
                <a class="vdp-hero-card vdp-hero-card-<?php echo $i; ?>" href="<?php echo esc_url($link); ?>">
                    <span class="vdp-hero-card-icon"><i class="fa <?php echo esc_attr($icon); ?>"></i></span>
                    <span class="vdp-hero-card-body">
                        <span class="vdp-hero-card-title">
                            <?php echo esc_html($title_i); ?>
                            <?php if ($tag): ?><em class="vdp-hero-card-tag"><?php echo esc_html($tag); ?></em><?php endif; ?>
                        </span>
                        <span class="vdp-hero-card-desc"><?php echo esc_html($desc); ?></span>
                    </span>
                </a>
                <?php endfor; ?>
            </div>
        </section>
        <?php if (count($slides) > 1): ?>
        <script>
        (function(){
            var root = document.getElementById('<?php echo esc_js($uid); ?>');
            if (!root) return;
            var slides = root.querySelectorAll('.vdp-hero-slide');
            var dots   = root.querySelectorAll('.vdp-hero-dot');
            var idx = 0, total = slides.length, timer = null;
            var interval = parseInt(root.getAttribute('data-interval'), 10) || 5000;
            var autoplay = root.getAttribute('data-autoplay') === '1';
            function go(n){
                idx = (n + total) % total;
                slides.forEach(function(s,i){ s.classList.toggle('is-active', i === idx); });
                dots.forEach(function(d,i){ d.classList.toggle('is-active', i === idx); });
            }
            function start(){ if (autoplay && total > 1) timer = setInterval(function(){ go(idx + 1); }, interval); }
            function stop(){ if (timer) { clearInterval(timer); timer = null; } }
            dots.forEach(function(d){
                d.addEventListener('click', function(){ stop(); go(parseInt(this.getAttribute('data-index'),10) || 0); start(); });
            });
            root.addEventListener('mouseenter', stop);
            root.addEventListener('mouseleave', start);
            start();
        })();
        </script>
        <?php endif; ?>
        <?php
    }
}
