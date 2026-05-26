<?php
/**
 * 文档详情页
 */
get_header();
vdp_page_shell_start();
vdp_render_breadcrumb();

while (have_posts()) : the_post();
    $doc = vdp_get_doc_file_info();
?>
    <article class="doc-single card">
        <div class="card-body">
            <h1 class="doc-title"><?php the_title(); ?></h1>

            <?php if (vdp_opt('partner_enabled', false)) : ?>
                <?php
                $share_url = is_user_logged_in()
                    ? esc_url(vdp_get_user_center_url('partner'))
                    : esc_url(home_url('/?vdp_auth=signin&redirect=' . urlencode(get_permalink())));
                ?>
                <a href="<?php echo $share_url; ?>" class="vdp-coffee-share" title="分享链接给好友，TA 购买你赚佣金">
                    <i class="fa fa-coffee"></i>
                    <span class="vdp-coffee-share-text">分享给好友 · 赚咖啡钱</span>
                    <i class="fa fa-angle-right vdp-coffee-share-arrow"></i>
                </a>
            <?php endif; ?>

            <div class="doc-meta">
                <?php if ($doc['ext']) : ?>
                    <?php echo vdp_get_format_badge($doc['ext']); ?>
                <?php endif; ?>
                <span class="meta-item"><i class="fa fa-clock-o"></i> <?php echo get_the_date(); ?></span>
                <span class="meta-item"><i class="fa fa-download"></i> <?php echo $doc['downloads']; ?> 次下载</span>
                <?php if ($doc['size']) : ?>
                    <span class="meta-item"><i class="fa fa-file-o"></i> <?php echo vdp_format_file_size($doc['size']); ?></span>
                <?php endif; ?>
                <span class="meta-item"><i class="fa fa-eye"></i> <?php echo get_post_meta(get_the_ID(), 'views', true) ?: 0; ?> 次浏览</span>
            </div>

            <?php if ($doc['pages']) : ?>
                <div class="doc-preview">
                    <h3 class="section-title">文档预览</h3>
                    <div class="preview-images">
                        <?php
                        $preview_limit = (int) vdp_opt('preview_pages', 3);
                        if ($preview_limit < 1) $preview_limit = 3;
                        $preview_count = min(intval($doc['pages']), $preview_limit);
                        $file_url = $doc['url'];

                        for ($i = 1; $i <= $preview_count; $i++) :
                            $preview_url = $file_url . '?ci-process=doc-preview&page=' . $i . '&dstType=png';
                        ?>
                            <div class="preview-page">
                                <img data-src="<?php echo esc_url($preview_url); ?>"
                                     class="lazy-load"
                                     alt="第<?php echo $i; ?>页预览">
                                <span class="page-num"><?php echo $i; ?>/<?php echo $preview_count; ?></span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="doc-content">
                <?php the_content(); ?>
            </div>
        </div>

        <div class="doc-download-section card">
            <div class="card-body">
                <?php
                $price = floatval($doc['price']);
                $user_id = get_current_user_id();
                $can_download = false;

                if (!empty($doc['is_free']) || $price <= 0) {
                    $can_download = true;
                } elseif ($user_id && VDP_Member::can_download(get_the_ID(), $user_id)) {
                    $can_download = true;
                }
                ?>

                <?php if ($can_download) : ?>
                    <div class="download-free">
                        <a href="<?php echo esc_url($doc['url']); ?>"
                           class="btn btn-primary btn-lg btn-download"
                           target="_blank"
                           rel="nofollow"
                           data-post-id="<?php the_ID(); ?>">
                            <i class="fa fa-download"></i> 下载文档
                        </a>
                    </div>
                <?php elseif (!$user_id) : ?>
                    <div class="download-paid">
                        <div class="price-info">
                            <span class="price">&yen;<?php echo number_format($price, 2); ?></span>
                            <span class="price-tip">登录后可购买并下载</span>
                        </div>
                        <a href="<?php echo esc_url(home_url('/?vdp_auth=signin&redirect=' . urlencode(get_permalink()))); ?>" class="btn btn-primary btn-lg">登录购买</a>
                    </div>
                <?php else : ?>
                    <div class="download-paid">
                        <div class="price-info">
                            <span class="price">&yen;<?php echo number_format($price, 2); ?></span>
                            <span class="price-tip">购买后可下载完整文档</span>
                        </div>
                        <div class="pay-buttons">
                            <button class="btn btn-success btn-pay" data-post-id="<?php the_ID(); ?>" data-pay-type="wechat">
                                <i class="fa fa-wechat"></i> 微信支付
                            </button>
                            <button class="btn btn-primary btn-pay" data-post-id="<?php the_ID(); ?>" data-pay-type="alipay">
                                <i class="fa fa-credit-card"></i> 支付宝
                            </button>
                        </div>
                        <?php if (VDP_Member::is_enabled()) : ?>
                            <p class="vip-tip">开通会员可免费下载全站文档 <a href="<?php echo esc_url(vdp_get_buy_vip_url()); ?>">了解会员</a></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php
        // 作者卡 + 分享下拉（参考子比 user-card.author + share-button）
        $author_id = get_the_author_meta('ID');
        ?>
        <div class="doc-share-author card">
            <div class="card-body">
                <div class="doc-author-card">
                    <a href="<?php echo esc_url(get_author_posts_url($author_id)); ?>" class="author-avatar">
                        <?php echo get_avatar($author_id, 56); ?>
                    </a>
                    <div class="author-meta">
                        <a href="<?php echo esc_url(get_author_posts_url($author_id)); ?>" class="author-name">
                            <?php echo esc_html(get_the_author_meta('display_name', $author_id)); ?>
                            <?php if (function_exists('vdp_get_vip_badge')) echo vdp_get_vip_badge($author_id); ?>
                        </a>
                        <div class="author-stats">
                            <span><i class="fa fa-file-text-o"></i> <?php echo (int) count_user_posts($author_id, 'post'); ?> 篇文档</span>
                            <span><i class="fa fa-clock-o"></i> 注册 <?php echo human_time_diff(strtotime(get_the_author_meta('user_registered', $author_id)), current_time('U')); ?></span>
                        </div>
                        <?php $bio = get_the_author_meta('description', $author_id); ?>
                        <?php if ($bio) : ?>
                            <div class="author-bio"><?php echo esc_html(wp_trim_words($bio, 30, '…')); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="doc-share">
                    <span class="share-label">分享：</span>
                    <div class="vdp-share-dropdown">
                        <button type="button" class="vdp-share-btn" aria-haspopup="true" aria-expanded="false">
                            <i class="fa fa-share-alt"></i> 分享文档
                        </button>
                        <ul class="vdp-share-menu" role="menu">
                            <li><a href="javascript:;" class="vdp-share-copy" data-url="<?php echo esc_url(get_permalink()); ?>"><i class="fa fa-link"></i> 复制链接</a></li>
                            <li><a href="javascript:;" class="vdp-share-wechat" data-url="<?php echo esc_url(get_permalink()); ?>"><i class="fa fa-wechat"></i> 微信</a></li>
                            <li><a href="https://service.weibo.com/share/share.php?url=<?php echo urlencode(get_permalink()); ?>&title=<?php echo urlencode(get_the_title()); ?>" target="_blank" rel="nofollow noopener"><i class="fa fa-weibo"></i> 微博</a></li>
                            <li><a href="https://connect.qq.com/widget/shareqq/index.html?url=<?php echo urlencode(get_permalink()); ?>&title=<?php echo urlencode(get_the_title()); ?>" target="_blank" rel="nofollow noopener"><i class="fa fa-qq"></i> QQ</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <?php
        // 相关推荐：优先使用「文档详情页 - 主内容下方」侧栏区放置的小工具
        // 若该区块未配置任何小工具，则回退到旧的硬编码相关推荐
        if (!is_active_sidebar('single_bottom_content') && vdp_opt('enable_related', true)) {
            vdp_render_related_docs(get_the_ID());
        }
        ?>

        <?php if (vdp_opt('enable_comments', true) && comments_open()) : ?>
            <div class="doc-comments card">
                <div class="card-body">
                    <?php comments_template(); ?>
                </div>
            </div>
        <?php endif; ?>
    </article>

<?php
endwhile;

vdp_page_shell_end();
get_footer();
