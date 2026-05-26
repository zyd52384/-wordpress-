<?php
/**
 * 单页面（WordPress page）
 *
 * 支持每个 page 自定义启用哪些小工具坑位：
 * - 编辑该 page 时，在右侧 metabox 勾选启用的坑位
 * - 该 page 启用 sidebar 时才会显示侧边栏，否则全宽
 */
get_header();

$page_id = get_queried_object_id();
$reg_enabled = get_post_meta($page_id, 'widgets_register', true);

vdp_page_shell_start();
?>

<article class="article page-article card">
    <div class="card-body">
        <?php if (!is_front_page()) : ?>
            <h1 class="page-title"><?php the_title(); ?></h1>
        <?php endif; ?>

        <div class="wp-posts-content">
            <?php
            while (have_posts()) : the_post();
                the_content();
                wp_link_pages([
                    'before' => '<p class="post-nav-links">',
                    'after'  => '</p>',
                ]);
            endwhile;
            ?>
        </div>
    </div>
</article>

<?php if (comments_open()) : ?>
    <div class="page-comments card">
        <div class="card-body">
            <?php comments_template(); ?>
        </div>
    </div>
<?php endif; ?>

<?php
vdp_page_shell_end();
get_footer();
