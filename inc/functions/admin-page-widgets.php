<?php
/**
 * 单页面（page）小工具坑位 metabox
 *
 * 复刻子比的：
 *   widgets_register           = 是否启用 (1/'')
 *   widgets_register_container = 启用的位置数组 (top_fluid/top_content/bottom_content/bottom_fluid/sidebar)
 *
 * 启用并保存后，theme-setup 会动态注册 page_{position}_{page_id} sidebar
 */

if (!defined('ABSPATH')) exit;

add_action('add_meta_boxes', function () {
    add_meta_box(
        'vdp_page_widgets',
        '页面小工具坑位',
        'vdp_render_page_widgets_metabox',
        'page',
        'side',
        'default'
    );
});

function vdp_render_page_widgets_metabox($post) {
    wp_nonce_field('vdp_page_widgets_save', 'vdp_page_widgets_nonce');

    $enabled   = (int) get_post_meta($post->ID, 'widgets_register', true);
    $container = (array) get_post_meta($post->ID, 'widgets_register_container', true);

    $positions = [
        'top_fluid'      => '顶部全宽度',
        'top_content'    => '主内容上方',
        'bottom_content' => '主内容下方',
        'bottom_fluid'   => '底部全宽度',
        'sidebar'        => '侧边栏',
    ];
    ?>
    <p>
        <label>
            <input type="checkbox" name="widgets_register" value="1" <?php checked($enabled, 1); ?>>
            为此页面启用自定义小工具坑位
        </label>
    </p>
    <p style="color:#666;margin:8px 0;">勾选要启用的位置（保存后可在「外观 → 小工具」找到）：</p>
    <ul style="margin:0 0 0 16px;">
        <?php foreach ($positions as $key => $label) : ?>
            <li>
                <label>
                    <input type="checkbox" name="widgets_register_container[]"
                           value="<?php echo esc_attr($key); ?>"
                           <?php checked(in_array($key, $container, true)); ?>>
                    <?php echo esc_html($label); ?>
                </label>
            </li>
        <?php endforeach; ?>
    </ul>
    <p style="color:#999;font-size:12px;margin-top:10px;">提示：勾选侧边栏后，该页面才会显示侧栏。</p>
    <?php
}

add_action('save_post_page', function ($post_id) {
    if (!isset($_POST['vdp_page_widgets_nonce'])) return;
    if (!wp_verify_nonce($_POST['vdp_page_widgets_nonce'], 'vdp_page_widgets_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_page', $post_id)) return;

    $enabled = !empty($_POST['widgets_register']) ? 1 : 0;
    update_post_meta($post_id, 'widgets_register', $enabled);

    $container = isset($_POST['widgets_register_container']) ? (array) $_POST['widgets_register_container'] : [];
    $allowed = ['top_fluid', 'top_content', 'bottom_content', 'bottom_fluid', 'sidebar'];
    $container = array_values(array_intersect($container, $allowed));
    update_post_meta($post_id, 'widgets_register_container', $container);
});
