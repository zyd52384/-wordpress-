<?php
$doc   = vdp_get_doc_file_info();
$thumb = vdp_get_doc_thumbnail(get_the_ID(), 1, 'medium');
$show_excerpt = (bool) vdp_opt('show_excerpt', true);
?>
<div class="posts-item">
    <div class="card doc-card">
        <a href="<?php the_permalink(); ?>" class="card-link">
            <div class="item-thumbnail">
                <?php if ($thumb) : ?>
                    <img src="<?php echo esc_url($thumb); ?>"
                         class="card-img"
                         loading="lazy"
                         alt="<?php the_title_attribute(); ?>"
                         onerror="this.onerror=null;this.classList.add('img-fallback');this.replaceWith(Object.assign(document.createElement('div'),{className:'card-img placeholder-img',innerHTML:'<i class=\'fa fa-file-text-o\'></i>'}));">
                <?php else : ?>
                    <div class="card-img placeholder-img">
                        <i class="fa fa-file-text-o"></i>
                    </div>
                <?php endif; ?>

                <?php if ($doc['ext']) : ?>
                    <span class="format-badge"><?php echo vdp_get_format_badge($doc['ext']); ?></span>
                <?php endif; ?>

                <?php if (!empty($doc['is_free'])) : ?>
                    <span class="price-badge free">免费</span>
                <?php elseif (!empty($doc['vip_limit'])) : ?>
                    <span class="price-badge vip"><i class="fa fa-diamond"></i> VIP</span>
                <?php else : ?>
                    <span class="price-badge">&yen;<?php echo number_format(floatval($doc['price']), 2); ?></span>
                <?php endif; ?>
            </div>

            <div class="item-body">
                <h3 class="item-heading"><?php the_title(); ?></h3>
                <?php if ($show_excerpt && has_excerpt()) : ?>
                    <div class="item-excerpt"><?php echo esc_html(wp_trim_words(get_the_excerpt(), 30, '…')); ?></div>
                <?php endif; ?>
                <div class="item-meta">
                    <span><i class="fa fa-download"></i> <?php echo $doc['downloads']; ?></span>
                    <span><i class="fa fa-clock-o"></i> <?php echo get_the_date('m-d'); ?></span>
                    <?php if ($doc['size']) : ?>
                        <span><?php echo vdp_format_file_size($doc['size']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </a>
    </div>
</div>
