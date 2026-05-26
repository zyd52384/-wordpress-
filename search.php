<?php
/**
 * 搜索结果页
 */
get_header();
vdp_page_shell_start();
vdp_render_breadcrumb();
?>

<div class="search-header">
    <h1 class="search-title">搜索: <?php echo esc_html(get_search_query()); ?></h1>
    <p class="search-count">找到 <?php echo $wp_query->found_posts; ?> 个结果</p>
</div>

<div class="posts-grid posts-grid-cols-<?php echo esc_attr(vdp_opt('posts_per_row', '3')); ?>">
    <?php
    if (have_posts()) :
        while (have_posts()) : the_post();
            get_template_part('template/content', 'doc');
        endwhile;
    else :
        echo '<div class="no-results"><p>未找到相关文档，请尝试其他关键词</p></div>';
    endif;
    ?>
</div>

<div class="pagination-wrap">
    <?php the_posts_pagination([
        'prev_text' => '&laquo;',
        'next_text' => '&raquo;',
    ]); ?>
</div>

<?php
vdp_page_shell_end();
get_footer();
