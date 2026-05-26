<?php
/**
 * <head> 输出：favicon、主题色 CSS 变量、SEO meta、自定义代码、统计代码
 */

if (!defined('ABSPATH')) exit;

/**
 * 主题色 / 圆角 → CSS 变量（最高优先级覆盖 main.css）
 */
add_action('wp_head', function () {
    $color  = vdp_opt('theme_color', '#2196F3');
    $hover  = vdp_opt('theme_color_hover', '#1976D2');
    $radius = vdp_opt('main_radius', '8px');

    // hex → rgba 软透明（用于 focus 阴影 / 浅底）
    $rgb = function ($hex) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        if (strlen($hex) !== 6) return '33,150,243';
        return hexdec(substr($hex, 0, 2)) . ',' . hexdec(substr($hex, 2, 2)) . ',' . hexdec(substr($hex, 4, 2));
    };
    $rgb_str = $rgb($color);
    ?>
    <style id="vdp-csf-vars">
    :root{
        --theme-color: <?php echo esc_html($color); ?>;
        --theme-color-hover: <?php echo esc_html($hover); ?>;
        --theme-color-light: rgba(<?php echo esc_html($rgb_str); ?>,.12);
        --focus-color: <?php echo esc_html($color); ?>;
        --focus-color-hover: <?php echo esc_html($hover); ?>;
        --focus-shadow-color: rgba(<?php echo esc_html($rgb_str); ?>,.4);
        --focus-color-opacity1: rgba(<?php echo esc_html($rgb_str); ?>,.08);
        --focus-color-opacity2: rgba(<?php echo esc_html($rgb_str); ?>,.16);
        --main-radius: <?php echo esc_html($radius); ?>;
    }
    </style>
    <?php
}, 5);

/**
 * Favicon
 */
add_action('wp_head', function () {
    $favicon = vdp_opt('site_favicon', '');
    if (empty($favicon)) return;
    $url = is_array($favicon) ? ($favicon['url'] ?? '') : $favicon;
    if (!$url) return;
    echo '<link rel="icon" href="' . esc_url($url) . '">' . "\n";
    echo '<link rel="shortcut icon" href="' . esc_url($url) . '">' . "\n";
}, 6);

/**
 * SEO meta：keywords / description（仅首页）
 */
add_action('wp_head', function () {
    if (is_home() || is_front_page()) {
        $kw   = trim((string) vdp_opt('site_keywords', ''));
        $desc = trim((string) vdp_opt('site_description', ''));
        if ($kw)   echo '<meta name="keywords" content="' . esc_attr($kw) . '">' . "\n";
        if ($desc) echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
    } elseif (is_singular()) {
        $excerpt = get_the_excerpt(get_queried_object_id());
        if ($excerpt) {
            echo '<meta name="description" content="' . esc_attr(wp_trim_words(wp_strip_all_tags($excerpt), 60, '')) . '">' . "\n";
        }
    }
}, 7);

/**
 * 首页标题格式：{site_name} / {tagline}
 */
add_filter('document_title_parts', function ($parts) {
    if (!is_home() && !is_front_page()) return $parts;

    $fmt = (string) vdp_opt('home_title_format', '');
    if (!$fmt) return $parts;

    $title = strtr($fmt, [
        '{site_name}' => get_bloginfo('name'),
        '{tagline}'   => get_bloginfo('description'),
    ]);
    return ['title' => $title];
});

/**
 * 自定义 head 代码 + 统计代码（head 段）
 */
add_action('wp_head', function () {
    $head = (string) vdp_opt('custom_head_code', '');
    if ($head !== '') echo $head . "\n";

    $stat = (string) vdp_opt('stat_code_head', '');
    if ($stat !== '') echo $stat . "\n";
}, 99);

/**
 * 自定义 footer 代码 + 统计代码（footer 段，body 末尾）
 */
add_action('wp_footer', function () {
    $foot = (string) vdp_opt('custom_footer_code', '');
    if ($foot !== '') echo $foot . "\n";

    $stat = (string) vdp_opt('stat_code_footer', '');
    if ($stat !== '') echo $stat . "\n";
}, 99);

/**
 * 百度推送：仅文档详情页提交
 */
add_action('wp_footer', function () {
    if (!is_singular('post')) return;
    $token = trim((string) vdp_opt('baidu_push_token', ''));
    if (!$token) return;
    $site = parse_url(home_url(), PHP_URL_HOST);
    ?>
    <script>
    (function(){var bp=document.createElement('script');var curProtocol=window.location.protocol.split(':')[0];
    if(curProtocol==='https'){bp.src='https://zz.bdstatic.com/linksubmit/push.js';}
    else{bp.src='http://push.zhanzhang.baidu.com/push.js';}
    var s=document.getElementsByTagName('script')[0];s.parentNode.insertBefore(bp,s);})();
    </script>
    <?php
}, 100);
