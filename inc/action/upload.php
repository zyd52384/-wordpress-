<?php
/**
 * 后台上传 AJAX 处理
 *
 * 改造后的链路：
 *   $_FILES → 校验 → MD5 查重 → vdp_storage()->upload() → 写文章 → 调度预览图 → 调度 AI 摘要
 */

if (!defined('ABSPATH')) exit;

/**
 * 上传错误码 → 中文
 */
function vdp_upload_error_message($code) {
    $errors = [
        UPLOAD_ERR_INI_SIZE  => '文件超过服务器上传限制',
        UPLOAD_ERR_FORM_SIZE => '文件超过表单限制',
        UPLOAD_ERR_PARTIAL   => '文件仅部分上传',
        UPLOAD_ERR_NO_FILE   => '没有选择文件',
    ];
    return isset($errors[$code]) ? $errors[$code] : '上传错误 (#' . $code . ')';
}

/**
 * AJAX: 上传单个文件并创建文章
 */
function vdp_ajax_upload_file() {
    check_ajax_referer('vdp_admin_nonce', '_ajax_nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('权限不足');
    }

    // 连接测试模式
    if (isset($_POST['test_connection'])) {
        $engine = sanitize_text_field($_POST['test_connection']);
        if ($engine === 'pan123') {
            $r = vdp_pan123()->test_connection();
        } else {
            $r = vdp_cos()->test_connection();
        }
        if (is_wp_error($r)) wp_send_json_error($r->get_error_message());
        wp_send_json_success($r);
    }

    // 检查 PHP 上传限制是否命中
    if (empty($_FILES) || !isset($_FILES['file'])) {
        $err_msg = '未收到文件，请检查 PHP 上传限制 (upload_max_filesize='
            . ini_get('upload_max_filesize') . ', post_max_size=' . ini_get('post_max_size') . ')';
        error_log('[VDP] 上传失败: ' . $err_msg);
        wp_send_json_error($err_msg);
    }

    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error(vdp_upload_error_message($file['error']));
    }

    // 接收参数
    $category_id     = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    $pay_type        = isset($_POST['pay_type']) ? sanitize_text_field($_POST['pay_type']) : '0';
    $pay_price       = isset($_POST['pay_price']) ? floatval($_POST['pay_price']) : 0;
    $vip_limit       = isset($_POST['vip_limit']) ? intval($_POST['vip_limit']) : 0;
    $preview_pages   = isset($_POST['preview_pages']) ? intval($_POST['preview_pages']) : 0;
    $force_engine    = isset($_POST['storage_engine']) ? sanitize_text_field($_POST['storage_engine']) : '';

    $filename = $file['name'];
    $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $tmp_path = $file['tmp_name'];

    // 校验文件格式
    if (!isset(vdp_supported_formats()[$ext])) {
        $err = '不支持的文件格式: .' . $ext;
        error_log('[VDP] 上传格式拒绝: ' . $err . ' | 文件名: ' . $filename);
        wp_send_json_error($err);
    }

    $file_size = @filesize($tmp_path);
    if ($file_size === false) {
        error_log('[VDP] 无法读取上传的临时文件: ' . $tmp_path);
        wp_send_json_error('无法读取上传的文件，请检查服务器临时目录权限');
    }

    // MD5 查重
    $md5 = md5_file($tmp_path);
    if ($md5 === false) {
        wp_send_json_error('无法计算文件 MD5');
    }
    if ($existing = vdp_find_existing_by_md5($md5)) {
        wp_send_json_error([
            'duplicate' => true,
            'message'   => '文件已存在: ' . get_the_title($existing),
            'post_id'   => $existing,
        ]);
    }

    // 1. 调用存储抽象层上传
    $storage = vdp_storage();
    $upload_r = $storage->upload($tmp_path, $filename, [
        'engine' => $force_engine,
    ]);
    if (is_wp_error($upload_r)) {
        $err_msg = '上传失败: ' . $upload_r->get_error_message();
        error_log('[VDP] ' . $err_msg . ' | 文件名: ' . $filename);
        wp_send_json_error($err_msg);
    }

    $engine     = $upload_r['engine'];
    $storage_key = $upload_r['key'];
    $file_url   = !empty($upload_r['url']) ? $upload_r['url'] : '';

    // 2. 创建文章
    $post_title = sanitize_text_field(pathinfo($filename, PATHINFO_FILENAME)) ?: $filename;
    $is_free = ($pay_type === '0');

    $zibpay_meta = [
        'pay_modo'          => $is_free ? '0' : '1',
        'pay_type'          => $is_free ? 'no' : '2',
        'pay_price'         => $pay_price,
        'pay_original_price'=> 0,
        'pay_title'         => $is_free ? '免费下载' : '付费资源',
        'pay_doc'           => '虚拟资料文件：' . $filename,
        'pay_download'      => [['link' => $file_url, 'name' => '下载文件', 'more' => '']],
        'pay_limit'         => $vip_limit,
        'vip_1_price'       => 0,
        'vip_2_price'       => 0,
        'vdp_file_name'     => $filename,
        'vdp_file_size'     => $file_size,
        'vdp_file_ext'      => $ext,
        'vdp_cos_key'       => ($engine === 'cos') ? $storage_key : '',
        'vdp_file_md5'      => $md5,
        'vdp_preview_pages' => $preview_pages ?: max(1, (int) vdp_opt('preview_pages_count', 3)),
    ];

    $post_id = wp_insert_post([
        'post_title'    => $post_title,
        'post_content'  => '',
        'post_status'   => 'publish',
        'post_type'     => 'post',
        'post_category' => $category_id ? [$category_id] : [],
        'meta_input'    => [
            'posts_zibpay' => $zibpay_meta,
        ],
    ], true);

    if (is_wp_error($post_id)) {
        $storage->delete($engine, $storage_key);
        wp_send_json_error('创建文章失败: ' . $post_id->get_error_message());
    }

    // 3. 写新版存储 meta
    $storage->save_meta_to_post($post_id, $upload_r);

    // 4. 调度预览图生成（按 preview_engine 分流）
    vdp_dispatch_preview($post_id, $tmp_path, $engine, $ext);

    // 5. 调度 AI 摘要（如果启用）
    if (function_exists('vdp_schedule_summary_generation') && vdp_deepseek()->enabled()) {
        // 拷一份临时文件供异步任务用（PDF 文本抽取）
        $summary_tmp = '';
        if ($ext === 'pdf') {
            $summary_tmp = trailingslashit(get_temp_dir()) . 'vdp-ai-' . wp_generate_password(8, false) . '.pdf';
            @copy($tmp_path, $summary_tmp);
        }
        vdp_schedule_summary_generation($post_id, $summary_tmp);
    }

    wp_send_json_success([
        'post_id'    => $post_id,
        'post_title' => $post_title,
        'engine'     => $engine,
        'file_url'   => $file_url,
        'edit_link'  => get_edit_post_link($post_id, ''),
        'permalink'  => get_permalink($post_id),
    ]);
}
add_action('wp_ajax_vdp_upload_file', 'vdp_ajax_upload_file');

/* =========================================================
   重生成：预览图 / AI 摘要（后台手动触发）
   ========================================================= */

/**
 * AJAX: 为某篇文章重新生成预览图
 *
 * 仅当 _vdp_storage = cos 且 preview_engine != local 时走 CI 路线，
 * 其他情况走本地引擎；本地需要从存储拉文件副本到 tmp。
 */
function vdp_ajax_regen_preview() {
    check_ajax_referer('vdp_admin_nonce', '_ajax_nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('权限不足');

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!$post_id) wp_send_json_error('缺少 post_id');

    $info = vdp_get_doc_file_info($post_id);
    $ext  = strtolower($info['ext']);
    $strategy = vdp_opt('preview_engine', 'auto');
    $engine = get_post_meta($post_id, '_vdp_storage', true);
    if (!$engine) $engine = !empty($info['cos_key']) ? 'cos' : 'cos';

    // CI 路线
    if ($engine === 'cos' && in_array($strategy, ['auto', 'cos_ci'], true)) {
        $url = vdp_get_doc_thumbnail($post_id, 1, 'medium');
        if (empty($url)) wp_send_json_error('CI 缩略图 URL 生成失败');
        update_post_meta($post_id, 'thumbnail_url', $url);
        update_post_meta($post_id, '_vdp_preview_status', 'done');
        wp_send_json_success(['message' => '预览图已重新生成（CI）', 'url' => $url]);
    }

    // 本地路线：把文件下载到 tmp 再转
    $download_url = vdp_storage()->get_download_url($engine, get_post_meta($post_id, '_vdp_storage_key', true) ?: $info['cos_key']);
    if (is_wp_error($download_url)) wp_send_json_error('获取下载链接失败：' . $download_url->get_error_message());

    $tmp = trailingslashit(get_temp_dir()) . 'vdp-regen-' . wp_generate_password(10, false) . '.' . $ext;
    $resp = wp_remote_get($download_url, ['timeout' => 300, 'sslverify' => false, 'stream' => true, 'filename' => $tmp]);
    if (is_wp_error($resp) || !file_exists($tmp)) {
        wp_send_json_error('下载源文件失败');
    }

    $r = vdp_preview_builder()->build_for_post($post_id, $tmp);
    @unlink($tmp);
    if (is_wp_error($r)) wp_send_json_error($r->get_error_message());
    wp_send_json_success(['message' => '预览图已重新生成（本地）', 'count' => $r['count']]);
}
add_action('wp_ajax_vdp_regen_preview', 'vdp_ajax_regen_preview');

/**
 * AJAX: 为某篇文章重新生成 AI 摘要
 */
function vdp_ajax_regen_summary() {
    check_ajax_referer('vdp_admin_nonce', '_ajax_nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('权限不足');

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!$post_id) wp_send_json_error('缺少 post_id');

    if (!vdp_deepseek()->enabled()) {
        wp_send_json_error('AI 摘要未启用或未配置 API Key');
    }

    // 强制覆盖（手动触发场景）
    add_filter('pre_option_vdp_options', function ($v) {
        $opts = get_option('vdp_options', []);
        $opts['ai_summary_overwrite'] = true;
        return $opts;
    });

    $r = vdp_generate_post_summary($post_id);
    if (is_wp_error($r)) wp_send_json_error($r->get_error_message());

    $excerpt = get_post_field('post_excerpt', $post_id);
    wp_send_json_success(['message' => '摘要已重新生成', 'excerpt' => $excerpt]);
}
add_action('wp_ajax_vdp_regen_summary', 'vdp_ajax_regen_summary');

/**
 * 根据 preview_engine 选项决定预览图生成方式
 *
 *   auto    → COS 走 CI（即时 URL）；123 走本地异步
 *   cos_ci  → 强制走 CI（仅 COS 引擎有效）
 *   local   → 强制本地异步（任何引擎都可）
 *   none    → 不生成
 */
function vdp_dispatch_preview($post_id, $tmp_path, $engine, $ext) {
    $strategy = vdp_opt('preview_engine', 'auto');
    if ($strategy === 'none') {
        update_post_meta($post_id, '_vdp_preview_status', 'skipped');
        return;
    }

    $supported_for_local = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'odt', 'odp', 'ods', 'rtf'];

    $use_local = false;
    if ($strategy === 'local') {
        $use_local = true;
    } elseif ($strategy === 'cos_ci') {
        $use_local = false;
    } else {
        // auto：COS 用 CI，非 COS 用本地
        $use_local = ($engine !== 'cos');
    }

    if ($use_local) {
        if (!in_array($ext, $supported_for_local, true)) {
            update_post_meta($post_id, '_vdp_preview_status', 'unsupported');
            return;
        }
        // 异步任务需要稳定路径，把 tmp 拷贝到系统临时目录
        $async_tmp = trailingslashit(get_temp_dir()) . 'vdp-prv-' . wp_generate_password(10, false) . '.' . $ext;
        if (!@copy($tmp_path, $async_tmp)) {
            update_post_meta($post_id, '_vdp_preview_status', 'failed');
            update_post_meta($post_id, '_vdp_preview_error', '无法拷贝文件到临时目录');
            return;
        }
        if (function_exists('vdp_schedule_preview_build')) {
            vdp_schedule_preview_build($post_id, $async_tmp, true);
        }
        return;
    }

    // CI 路线：COS 上传成功后第一时间把缩略图 URL 直接写到 meta（即时可用）
    if ($engine === 'cos' && function_exists('vdp_get_doc_thumbnail')) {
        $url = vdp_get_doc_thumbnail($post_id, 1, 'medium');
        if (!empty($url)) {
            update_post_meta($post_id, 'thumbnail_url', $url);
            update_post_meta($post_id, '_vdp_preview_status', 'done');
        }
    }
}

/**
 * 通过 MD5 查找已存在的文章
 */
function vdp_find_existing_by_md5($md5) {
    global $wpdb;

    $posts = $wpdb->get_col($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta}
         WHERE meta_key = %s
         AND meta_value LIKE %s",
        'posts_zibpay',
        '%' . $wpdb->esc_like($md5) . '%'
    ));

    if (!empty($posts)) {
        foreach ($posts as $pid) {
            $meta = get_post_meta($pid, 'posts_zibpay', true);
            if (isset($meta['vdp_file_md5']) && $meta['vdp_file_md5'] === $md5) {
                return $pid;
            }
        }
    }

    return 0;
}
