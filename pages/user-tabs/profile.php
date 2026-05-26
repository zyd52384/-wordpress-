<?php
/**
 * 用户中心 - 我的资料
 */

if (!defined('ABSPATH')) exit;

$user = wp_get_current_user();
$fields = vdp_user_profile_fields();
?>

<div class="vdp-panel">
    <h2 class="vdp-panel-title">我的资料</h2>
    <form id="vdp-profile-form" class="vdp-form">
        <?php wp_nonce_field('vdp_user_nonce', '_ajax_nonce'); ?>
        <input type="hidden" name="action" value="vdp_save_profile">

        <div class="vdp-form-row">
            <label>用户名</label>
            <input type="text" value="<?php echo esc_attr($user->user_login); ?>" disabled>
            <small class="vdp-form-hint">用户名不可修改</small>
        </div>

        <div class="vdp-form-row">
            <label>邮箱</label>
            <input type="text" value="<?php echo esc_attr($user->user_email); ?>" disabled>
        </div>

        <?php foreach ($fields as $key => $field) :
            if ($key === 'nickname') {
                $value = $user->display_name;
            } elseif ($key === 'description') {
                $value = $user->description;
            } else {
                $value = get_user_meta($user->ID, $key, true);
            }
        ?>
            <div class="vdp-form-row">
                <label><?php echo esc_html($field['label']); ?><?php if (!empty($field['required'])) echo ' <span class="required">*</span>'; ?></label>
                <?php if ($field['type'] === 'textarea') : ?>
                    <textarea name="<?php echo esc_attr($key); ?>" rows="3"><?php echo esc_textarea($value); ?></textarea>
                <?php elseif ($field['type'] === 'select') : ?>
                    <select name="<?php echo esc_attr($key); ?>">
                        <?php foreach ($field['options'] as $opt_v => $opt_l) : ?>
                            <option value="<?php echo esc_attr($opt_v); ?>" <?php selected($value, $opt_v); ?>><?php echo esc_html($opt_l); ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else : ?>
                    <input type="text" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div class="vdp-form-row">
            <button type="submit" class="vdp-btn-primary">保存修改</button>
        </div>
    </form>
</div>
