<?php
/**
 * 分类页模板
 */
get_header();
vdp_page_shell_start();
vdp_render_breadcrumb();
?>

<div class="category-header">
    <h1 class="category-title"><?php single_cat_title(); ?></h1>
    <?php if (category_description()) : ?>
        <p class="category-desc"><?php echo category_description(); ?></p>
    <?php endif; ?>
</div>

<div class="posts-grid posts-grid-cols-<?php echo esc_attr(vdp_opt('posts_per_row', '3')); ?>">
    <?php
    if (have_posts()) :
        while (have_posts()) : the_post();
            get_template_part('template/content', 'doc');
        endwhile;
    else :
        echo '<div class="no-results"><p>该分类暂无文档</p></div>';
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
