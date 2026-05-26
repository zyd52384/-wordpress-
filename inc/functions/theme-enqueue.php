<?php
/**
 * 资源加载
 */

if (!defined('ABSPATH')) exit;

add_action('wp_enqueue_scripts', function () {
    // CSS
    wp_enqueue_style('bootstrap', VDP_THEME_URI . '/assets/css/bootstrap.min.css', [], '3.4.1');
    wp_enqueue_style('font-awesome', VDP_THEME_URI . '/assets/css/font-awesome.min.css', [], '4.7.0');
    wp_enqueue_style('vdp-main', VDP_THEME_URI . '/assets/css/main.css', ['bootstrap'], VDP_THEME_VERSION);
    wp_enqueue_style('vdp-user', VDP_THEME_URI . '/assets/css/user.css', ['vdp-main'], VDP_THEME_VERSION);

    // 加载 dashicons（用户中心图标用）
    wp_enqueue_style('dashicons');

    // 注册 Swiper（按需加载：widget 调用时才 enqueue）
    wp_register_style('swiper', 'https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css', [], '10.0.0');
    wp_register_script('swiper', 'https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js', [], '10.0.0', true);

    // JS
    wp_enqueue_script('bootstrap-js', VDP_THEME_URI . '/assets/js/bootstrap.min.js', ['jquery'], '3.4.1', true);
    wp_enqueue_script('vdp-main', VDP_THEME_URI . '/assets/js/main.js', ['jquery', 'bootstrap-js'], VDP_THEME_VERSION, true);
    wp_enqueue_script('vdp-user', VDP_THEME_URI . '/assets/js/user.js', ['jquery'], VDP_THEME_VERSION, true);

    wp_localize_script('vdp-main', 'vdp_ajax', [
        'url'   => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('vdp_user_nonce'),
    ]);
});

/**
 * 在 <head> 早期注入：根据 localStorage 即时隐藏被关闭的小工具，避免渲染闪烁
 */
add_action('wp_head', function () {
    ?>
    <script>(function(){try{var ids=JSON.parse(localStorage.getItem('vdp_dismissed_widgets')||'[]');if(!ids||!ids.length)return;var s=document.createElement('style');s.id='vdp-dismissed-style';s.textContent=ids.map(function(id){return '#'+CSS.escape(id)+'[data-closable="true"]{display:none!important;}';}).join('');document.head.appendChild(s);}catch(e){}})();</script>
    <?php
}, 1);

/**
 * 在 <head> 早期注入：根据 localStorage 即时应用暗色模式，避免亮→暗闪烁
 */
add_action('wp_head', function () {
    if (!vdp_opt('enable_dark_mode', true)) return;
    ?>
    <script>(function(){try{var v=localStorage.getItem('vdp_theme_mode');var prefersDark=window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches;var dark=(v==='dark')||(!v&&prefersDark);if(dark){document.documentElement.classList.add('dark-theme');}}catch(e){}})();</script>
    <?php
}, 1);

add_action('admin_enqueue_scripts', function ($hook) {
    wp_enqueue_style('vdp-admin', VDP_THEME_URI . '/assets/css/admin.css', [], VDP_THEME_VERSION);

    if (strpos($hook, 'vdp-') !== false) {
        wp_enqueue_media();
        wp_enqueue_script('vdp-admin', VDP_THEME_URI . '/assets/js/admin.js', ['jquery'], VDP_THEME_VERSION, true);
        wp_localize_script('vdp-admin', 'vdp_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('vdp_admin_nonce'),
        ]);
    }
});
