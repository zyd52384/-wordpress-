<?php
/**
 * 通用归档页（标签、作者、日期等）
 */
get_header();
vdp_page_shell_start();
vdp_render_breadcrumb();
?>

<div class="archive-header">
    <h1 class="archive-title"><?php the_archive_title(); ?></h1>
    <?php if (get_the_archive_description()) : ?>
        <div class="archive-desc"><?php the_archive_description(); ?></div>
    <?php endif; ?>
</div>

<div class="posts-grid posts-grid-cols-<?php echo esc_attr(vdp_opt('posts_per_row', '3')); ?>">
    <?php
    if (have_posts()) :
        while (have_posts()) : the_post();
            get_template_part('template/content', 'doc');
        endwhile;
    else :
        echo '<div class="no-results"><p>暂无内容</p></div>';
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
