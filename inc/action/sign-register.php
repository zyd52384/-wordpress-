<?php
/**
 * 登录 / 注册 / 找回密码 AJAX 处理
 * 借鉴子比的 AJAX 模态登录交互
 */

if (!defined('ABSPATH')) exit;

/**
 * AJAX: 用户登录
 */
function vdp_ajax_signin() {
    check_ajax_referer('vdp_user_nonce');

    if (vdp_is_close_signin()) {
        wp_send_json_error('登录功能已关闭');
    }

    $login    = sanitize_text_field($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

    if (!$login || !$password) {
        wp_send_json_error('请输入账号和密码');
    }

    $user = wp_authenticate($login, $password);

    if (is_wp_error($user)) {
        $code = $user->get_error_code();
        $msg = '账号或密码错误';
        if (in_array($code, ['invalid_username', 'invalid_email'])) {
            $msg = '账号不存在';
        } elseif ($code === 'incorrect_password') {
            $msg = '密码错误';
        }
        wp_send_json_error($msg);
    }

    wp_set_auth_cookie($user->ID, $remember);
    wp_set_current_user($user->ID);
    do_action('wp_login', $user->user_login, $user);

    $redirect = !empty($_POST['redirect']) ? esc_url_raw($_POST['redirect']) : home_url('/');
    wp_send_json_success([
        'message'  => '登录成功',
        'redirect' => $redirect,
    ]);
}
add_action('wp_ajax_nopriv_vdp_signin', 'vdp_ajax_signin');
add_action('wp_ajax_vdp_signin', 'vdp_ajax_signin');

/**
 * AJAX: 用户注册
 */
function vdp_ajax_signup() {
    check_ajax_referer('vdp_user_nonce');

    if (vdp_is_close_signup()) {
        wp_send_json_error('注册功能已关闭');
    }

    $username = sanitize_user($_POST['username'] ?? '', true);
    $email    = sanitize_email($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (!$username || !$email || !$password) {
        wp_send_json_error('请填写完整信息');
    }
    if (!validate_username($username)) {
        wp_send_json_error('用户名格式不正确');
    }
    if (strlen($username) < 3) {
        wp_send_json_error('用户名至少 3 位');
    }
    if (!is_email($email)) {
        wp_send_json_error('邮箱格式不正确');
    }
    if (username_exists($username)) {
        wp_send_json_error('用户名已被占用');
    }
    if (email_exists($email)) {
        wp_send_json_error('邮箱已被使用');
    }
    if ($password !== $confirm) {
        wp_send_json_error('两次密码不一致');
    }
    if (strlen($password) < 6) {
        wp_send_json_error('密码至少 6 位');
    }

    $user_id = wp_create_user($username, $password, $email);
    if (is_wp_error($user_id)) {
        wp_send_json_error($user_id->get_error_message());
    }

    wp_set_auth_cookie($user_id, true);
    wp_set_current_user($user_id);

    $user = get_user_by('ID', $user_id);
    do_action('wp_login', $user->user_login, $user);

    wp_send_json_success([
        'message'  => '注册成功',
        'redirect' => vdp_get_user_center_url(),
    ]);
}
add_action('wp_ajax_nopriv_vdp_signup', 'vdp_ajax_signup');

/**
 * AJAX: 退出登录
 */
function vdp_ajax_signout() {
    wp_logout();
    wp_send_json_success([
        'redirect' => home_url(),
    ]);
}
add_action('wp_ajax_vdp_signout', 'vdp_ajax_signout');
