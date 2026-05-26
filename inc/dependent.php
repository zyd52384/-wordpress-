<?php
/**
 * 基础工具函数
 */

if (!defined('ABSPATH')) exit;

/**
 * 获取主题选项
 *
 * 注意：当字段在 vdp_options 中存为空字符串 / null（CSF select/switcher 未选时常见情况），
 * 会回落到 $default，避免拼接出无效 CSS class（如 posts-grid-cols- 导致 grid 退化）。
 */
function vdp_opt($name, $default = '') {
    static $options = null;
    if ($options === null) {
        $options = get_option('vdp_options', []);
        if (!is_array($options)) $options = [];
    }
    if (!array_key_exists($name, $options)) return $default;
    $val = $options[$name];
    if ($val === '' || $val === null) return $default;
    return $val;
}

/**
 * 设置主题选项
 */
function vdp_set_opt($name, $value) {
    $options = get_option('vdp_options', []);
    $options[$name] = $value;
    update_option('vdp_options', $options);
}

/**
 * 支持的文档格式
 */
function vdp_supported_formats() {
    return [
        'pdf'  => ['name' => 'PDF', 'color' => '#e74c3c'],
        'doc'  => ['name' => 'DOC', 'color' => '#2b579a'],
        'docx' => ['name' => 'DOCX', 'color' => '#2b579a'],
        'ppt'  => ['name' => 'PPT', 'color' => '#d24726'],
        'pptx' => ['name' => 'PPTX', 'color' => '#d24726'],
        'xls'  => ['name' => 'XLS', 'color' => '#217346'],
        'xlsx' => ['name' => 'XLSX', 'color' => '#217346'],
        'txt'  => ['name' => 'TXT', 'color' => '#666'],
        'zip'  => ['name' => 'ZIP', 'color' => '#f39c12'],
        'rar'  => ['name' => 'RAR', 'color' => '#9b59b6'],
        '7z'   => ['name' => '7Z', 'color' => '#1abc9c'],
    ];
}

/**
 * 获取文件格式徽章 HTML
 */
function vdp_get_format_badge($ext) {
    $formats = vdp_supported_formats();
    $ext = strtolower($ext);
    if (!isset($formats[$ext])) {
        return '<span class="badge-format">FILE</span>';
    }
    $f = $formats[$ext];
    return sprintf(
        '<span class="badge-format" style="background:%s">%s</span>',
        esc_attr($f['color']),
        esc_html($f['name'])
    );
}

/**
 * 格式化文件大小
 */
function vdp_format_file_size($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' B';
}

/**
 * 判断是否为文档类文章
 */
function vdp_is_doc_post($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    $meta = get_post_meta($post_id, 'posts_zibpay', true);
    return !empty($meta) && !empty($meta['vdp_file_name']);
}

/**
 * 获取文档文件信息
 * 数据来源：posts_zibpay 数组 meta（与上传时一致）
 */
function vdp_get_doc_file_info($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    $meta = get_post_meta($post_id, 'posts_zibpay', true);
    if (!is_array($meta)) $meta = [];

    $download = isset($meta['pay_download'][0]) ? $meta['pay_download'][0] : [];

    return [
        'url'       => isset($download['link']) ? $download['link'] : '',
        'name'      => isset($meta['vdp_file_name']) ? $meta['vdp_file_name'] : '',
        'size'      => isset($meta['vdp_file_size']) ? (int) $meta['vdp_file_size'] : 0,
        'ext'       => isset($meta['vdp_file_ext']) ? $meta['vdp_file_ext'] : '',
        'cos_key'   => isset($meta['vdp_cos_key']) ? $meta['vdp_cos_key'] : '',
        'md5'       => isset($meta['vdp_file_md5']) ? $meta['vdp_file_md5'] : '',
        'pages'     => isset($meta['vdp_preview_pages']) ? (int) $meta['vdp_preview_pages'] : 3,
        'price'     => isset($meta['pay_price']) ? (float) $meta['pay_price'] : 0,
        'is_free'   => empty($meta['pay_modo']) || $meta['pay_modo'] === '0',
        'vip_limit' => isset($meta['pay_limit']) ? (int) $meta['pay_limit'] : 0,
        'downloads' => (int) get_post_meta($post_id, 'vdp_downloads', true),
    ];
}

/**
 * 兼容别名：vdp_get_doc_info()
 * 文章列表(新) 等小工具调用的是 vdp_get_doc_info，需返回相同结构
 */
if (!function_exists('vdp_get_doc_info')) {
    function vdp_get_doc_info($post_id = null) {
        return vdp_get_doc_file_info($post_id);
    }
}

/**
 * 获取文档首页预览图 URL
 *
 * 取图优先级：
 *   1. 文章特色图
 *   2. 本地生成的预览图（_vdp_preview_urls，由 LibreOffice + Imagick 生成）
 *   3. 腾讯云 CI doc-preview（仅当文件存于 COS 时）
 *   4. 空（前端落占位图）
 */
function vdp_get_doc_thumbnail($post_id = null, $page = 1, $size = 'medium') {
    if (!$post_id) $post_id = get_the_ID();
    $page = max(1, (int) $page);

    if (has_post_thumbnail($post_id)) {
        return get_the_post_thumbnail_url($post_id, $size);
    }

    // 本地预览图（_vdp_preview_urls 由本地生成器写入）
    $local_urls = get_post_meta($post_id, '_vdp_preview_urls', true);
    if (is_array($local_urls) && !empty($local_urls[$page - 1])) {
        return $local_urls[$page - 1];
    }

    $info = vdp_get_doc_file_info($post_id);
    if (empty($info['url']) || empty($info['ext'])) return '';

    // CI doc-preview：仅当文件实际存于 COS 时（_vdp_storage = cos 或 fallback：有 cos_key）
    $engine = get_post_meta($post_id, '_vdp_storage', true);
    $is_cos = ($engine === 'cos') || (empty($engine) && !empty($info['cos_key']));
    if (!$is_cos) return '';

    $supported = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt'];
    if (!in_array(strtolower($info['ext']), $supported, true)) return '';

    $sep = strpos($info['url'], '?') !== false ? '&' : '?';
    return $info['url'] . $sep . 'ci-process=doc-preview&page=' . $page . '&dstType=png';
}

/**
 * 安全获取 POST/GET 参数
 */
function vdp_input($key, $default = '', $method = 'POST') {
    $source = $method === 'GET' ? $_GET : $_POST;
    if (!isset($source[$key])) {
        return $default;
    }
    return sanitize_text_field(wp_unslash($source[$key]));
}
