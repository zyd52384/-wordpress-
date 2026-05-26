<?php
/**
 * 小工具注册入口
 */

if (!defined('ABSPATH')) exit;

// 加载小工具类
$widget_files = [
    'widget-docs.php',
    'widget-user.php',
    'widget-extra.php',
    'widget-posts.php',
    'widget-zibll.php',
    'widget-zibll-extra.php',
    'widget-main-posts.php',
    'widget-hero.php',
    'widget-single.php',
];

foreach ($widget_files as $file) {
    $path = get_template_directory() . '/inc/widgets/' . $file;
    if (file_exists($path)) {
        require_once $path;
    }
}

/**
 * 注册主题小工具
 */
function vdp_register_widgets() {
    register_widget('VDP_Widget_Docs');
    register_widget('VDP_Widget_Categories');
    register_widget('VDP_Widget_Stats');
    register_widget('VDP_Widget_UserCard');
    register_widget('VDP_Widget_VipPromo');
    register_widget('VDP_Widget_HotRankTabs');
    register_widget('VDP_Widget_TagCloud');
    register_widget('VDP_Widget_AdCard');
    register_widget('VDP_Widget_CommentRank');
    register_widget('VDP_Widget_DocsSlider');
    register_widget('VDP_Widget_DocsGrid');
    register_widget('VDP_Widget_CategoryTabs');
    register_widget('VDP_Widget_FocusList');
    register_widget('VDP_Widget_PostList');

    // 子比风格扩展小工具
    register_widget('VDP_Widget_Notice');
    register_widget('VDP_Widget_LinksList');
    register_widget('VDP_Widget_Yiyan');
    register_widget('VDP_Widget_IconCard');
    register_widget('VDP_Widget_GraphicCover');
    register_widget('VDP_Widget_NewComment');
    register_widget('VDP_Widget_UserRanking');
    register_widget('VDP_Widget_TermAggregation');

    // 子比借鉴：搜索框 / 文章目录 / 单行文章列表
    register_widget('VDP_Widget_Search');
    register_widget('VDP_Widget_PostToc');
    register_widget('VDP_Widget_OnelinePosts');

    // 子比借鉴：文章列表(新) - 完整复刻
    register_widget('VDP_Widget_MainPosts');

    // 首页大屏 Hero
    register_widget('VDP_Widget_Hero');

    // 文章页专用：相关推荐 + 上下篇导航
    register_widget('VDP_Widget_RelatedDocs');
    register_widget('VDP_Widget_AdjacentPosts');
}
add_action('widgets_init', 'vdp_register_widgets');
