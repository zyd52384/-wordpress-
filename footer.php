</main>

<footer class="site-footer">
    <div class="container">
        <?php if (is_active_sidebar('footer_widgets') || is_active_sidebar('all_footer')) : ?>
            <div class="footer-widgets row">
                <?php
                if (is_active_sidebar('all_footer')) dynamic_sidebar('all_footer');
                if (is_active_sidebar('footer_widgets')) dynamic_sidebar('footer_widgets');
                ?>
            </div>
        <?php endif; ?>

        <div class="footer-bottom">
            <div class="row">
                <div class="col-sm-7">
                    <?php if (function_exists('vdp_render_footer_meta')) vdp_render_footer_meta(); ?>
                </div>
                <div class="col-sm-5 text-right">
                    <?php
                    wp_nav_menu([
                        'theme_location' => 'footer',
                        'container'      => false,
                        'menu_class'     => 'footer-nav',
                        'fallback_cb'    => false,
                        'depth'          => 1,
                    ]);
                    ?>
                </div>
            </div>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
