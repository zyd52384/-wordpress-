<?php
/**
 * 用户中心 - 账号设置（修改密码）
 */

if (!defined('ABSPATH')) exit;
?>

<div class="vdp-panel">
    <h2 class="vdp-panel-title">账号设置</h2>

    <h3>修改密码</h3>
    <form id="vdp-password-form" class="vdp-form" style="max-width:400px;">
        <?php wp_nonce_field('vdp_user_nonce', '_ajax_nonce'); ?>
        <input type="hidden" name="action" value="vdp_change_password">

        <div class="vdp-form-row">
            <label>当前密码</label>
            <input type="password" name="old_password" required>
        </div>
        <div class="vdp-form-row">
            <label>新密码</label>
            <input type="password" name="new_password" minlength="6" required>
        </div>
        <div class="vdp-form-row">
            <label>确认新密码</label>
            <input type="password" name="confirm_password" minlength="6" required>
        </div>
        <div class="vdp-form-row">
            <button type="submit" class="vdp-btn-primary">修改密码</button>
        </div>
    </form>
</div>
