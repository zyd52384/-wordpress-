<?php
/**
 * Sidebar - 兼容入口（仅供 get_sidebar() 调用）
 *
 * 实际派发逻辑在 vdp_render_sidebar() 内，
 * 但这里保留这个文件以便其他第三方模板也能使用。
 */
if (!defined('ABSPATH')) exit;

if (function_exists('vdp_render_sidebar')) {
    vdp_render_sidebar();
}
