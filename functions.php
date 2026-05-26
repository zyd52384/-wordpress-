<?php
/**
 * 虚拟资料自动赚钱机 - 主题入口
 */

if (!defined('ABSPATH')) exit;

define('VDP_THEME_VERSION', '1.0.0');
define('VDP_DB_VERSION', '1.0.2');
define('VDP_THEME_DIR', get_template_directory());
define('VDP_THEME_URI', get_template_directory_uri());
define('VDP_THEME_INC', VDP_THEME_DIR . '/inc');

require_once VDP_THEME_INC . '/inc.php';
