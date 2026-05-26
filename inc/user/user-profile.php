<?php
/**
 * 用户个人资料管理
 */

if (!defined('ABSPATH')) exit;

/**
 * 用户元数据字段定义（用于个人资料编辑）
 */
function vdp_user_profile_fields() {
    return [
        'nickname'    => ['label' => '昵称',     'type' => 'text',     'required' => true],
        'description' => ['label' => '个人简介', 'type' => 'textarea', 'required' => false],
        'gender'      => ['label' => '性别',     'type' => 'select',   'options' => ['' => '保密', 'male' => '男', 'female' => '女']],
        'qq'          => ['label' => 'QQ',       'type' => 'text',     'required' => false],
        'weixin'      => ['label' => '微信',     'type' => 'text',     'required' => false],
    ];
}

/**
 * AJAX: 保存用户资料
 */
function vdp_ajax_save_profile() {
    check_ajax_referer('vdp_user_nonce');

    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error('请先登录');
    }

    $fields = vdp_user_profile_fields();
    $update_data = ['ID' => $user_id];

    foreach ($fields as $key => $field) {
        $value = isset($_POST[$key]) ? wp_unslash($_POST[$key]) : '';

        if (!empty($field['required']) && empty($value)) {
            wp_send_json_error($field['label'] . '不能为空');
        }

        if ($key === 'nickname') {
            $update_data['display_name'] = sanitize_text_field($value);
            $update_data['nickname']     = sanitize_text_field($value);
        } elseif ($key === 'description') {
            $update_data['description'] = wp_kses_post($value);
        } else {
            update_user_meta($user_id, $key, sanitize_text_field($value));
        }
    }

    $result = wp_update_user($update_data);

    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }

    wp_send_json_success('资料已更新');
}
add_action('wp_ajax_vdp_save_profile', 'vdp_ajax_save_profile');

/**
 * AJAX: 修改密码
 */
function vdp_ajax_change_password() {
    check_ajax_referer('vdp_user_nonce');

    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error('请先登录');
    }

    $old_pwd = $_POST['old_password'] ?? '';
    $new_pwd = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$old_pwd || !$new_pwd) {
        wp_send_json_error('请填写完整');
    }
    if ($new_pwd !== $confirm) {
        wp_send_json_error('两次输入的新密码不一致');
    }
    if (strlen($new_pwd) < 6) {
        wp_send_json_error('密码至少 6 位');
    }

    $user = wp_get_current_user();
    if (!wp_check_password($old_pwd, $user->user_pass, $user_id)) {
        wp_send_json_error('原密码错误');
    }

    wp_set_password($new_pwd, $user_id);
    wp_send_json_success('密码已修改，请重新登录');
}
add_action('wp_ajax_vdp_change_password', 'vdp_ajax_change_password');
