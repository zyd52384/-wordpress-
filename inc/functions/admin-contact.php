<?php
/**
 * 后台联系开发者页面
 */

if (!defined('ABSPATH')) exit;

/**
 * 渲染联系开发者页面
 */
function vdp_render_contact_page() {
    ?>
    <div class="wrap" style="max-width:800px;">
        <h1>联系开发者</h1>
        <div style="background:#fff;border:1px solid #e8e8e8;border-radius:12px;padding:32px;margin-top:20px;line-height:1.8;">
            <p style="font-size:16px;color:#333;">
                自己做虚拟产品多年，现在用AI手搓了这么个主题，我会根据虚拟产品项目的运营点，
                不断的完善这个主题，比如自动更新，自动转发小红书、公众号等功能，
                让它真正成为<strong>AI时代自动赚钱的机器</strong>。
            </p>
            <p style="font-size:16px;color:#333;">
                对虚拟产品感兴趣的朋友欢迎联系交流。
            </p>
            <div style="background:#f0f8ff;border-left:4px solid #1677ff;padding:16px 20px;margin:20px 0;border-radius:4px;">
                <div style="font-size:16px;font-weight:600;color:#1677ff;margin-bottom:8px;">
                    📱 微信：baomafenxiang520
                </div>
                <div style="font-size:14px;color:#666;">
                    备注：wordpress主题
                </div>
            </div>
        </div>
    </div>
    <?php
}
