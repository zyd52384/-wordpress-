<?php
/**
 * 首页模板
 */
get_header();
vdp_page_shell_start();
?>

<div class="vdp-home-tabs doc-filter-bar">
    <div class="filter-tabs">
        <a href="?orderby=date" class="filter-tab <?php echo (!isset($_GET['orderby']) || $_GET['orderby'] === 'date') ? 'active' : ''; ?>">最新</a>
        <a href="?orderby=downloads" class="filter-tab <?php echo (isset($_GET['orderby']) && $_GET['orderby'] === 'downloads') ? 'active' : ''; ?>">热门</a>
    </div>
    <div class="filter-categories">
        <?php
        $cats = get_categories(['hide_empty' => true, 'number' => 10]);
        foreach ($cats as $cat) {
            printf(
                '<a href="%s" class="badge-category">%s</a>',
                esc_url(get_category_link($cat->term_id)),
                esc_html($cat->name)
            );
        }
        ?>
    </div>
</div>

<?php
$home_layout = vdp_opt('home_layout', 'grid');
$wrap_class  = $home_layout === 'list'
    ? 'posts-list'
    : 'posts-grid posts-grid-cols-' . esc_attr(vdp_opt('posts_per_row', '3'));
$tpl_slug    = $home_layout === 'list' ? 'list' : 'doc';
?>
<div class="<?php echo $wrap_class; ?>">
    <?php
    $paged = get_query_var('paged') ? get_query_var('paged') : 1;
    $args = [
        'post_type'      => 'post',
        'posts_per_page' => (int) vdp_opt('posts_per_page', 12),
        'paged'          => $paged,
    ];

    if (isset($_GET['orderby']) && $_GET['orderby'] === 'downloads') {
        $args['meta_key'] = 'vdp_downloads';
        $args['orderby']  = 'meta_value_num';
        $args['order']    = 'DESC';
    }

    $query = new WP_Query($args);

    if ($query->have_posts()) :
        while ($query->have_posts()) : $query->the_post();
            get_template_part('template/content', $tpl_slug);
        endwhile;
    else :
        echo '<div class="no-results"><p>暂无文档</p></div>';
    endif;
    ?>
</div>

<div class="pagination-wrap">
    <?php
    echo paginate_links([
        'total'   => $query->max_num_pages,
        'current' => $paged,
        'prev_text' => '&laquo;',
        'next_text' => '&raquo;',
    ]);
    wp_reset_postdata();
    ?>
</div>

<?php
vdp_page_shell_end();
get_footer();
