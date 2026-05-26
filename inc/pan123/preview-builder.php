<?php
/**
 * 本地预览图生成器（LibreOffice + Imagick）
 *
 * 流程：
 *   办公文件 (doc/docx/ppt/pptx/xls/xlsx) → LibreOffice 转 PDF → Imagick 抽前 N 页 → webp
 *   PDF 文件直接交给 Imagick
 *
 * 输出目录：wp-content/uploads/vdp-previews/{post_id}/page-{n}.webp
 *
 * 依赖检测：未装 LibreOffice 或 Imagick 时返回 WP_Error，调用方应降级
 */

if (!defined('ABSPATH')) exit;

class VDP_Preview_Builder {

    /** 服务器是否具备本地预览图生成条件 */
    public function check_environment() {
        $missing = [];

        if (!extension_loaded('imagick')) {
            $missing[] = 'PHP imagick 扩展';
        }
        if (!class_exists('Imagick')) {
            $missing[] = 'Imagick 类';
        }

        $bin = $this->libreoffice_bin();
        if (!$this->bin_exists($bin)) {
            $missing[] = 'LibreOffice 可执行文件 (' . $bin . ')';
        }

        if (!empty($missing)) {
            return new WP_Error('vdp_preview_env_missing', '缺少：' . implode('、', $missing));
        }
        return true;
    }

    public function libreoffice_bin() {
        $bin = vdp_opt('libreoffice_bin', '/usr/bin/libreoffice');
        return trim($bin) ?: '/usr/bin/libreoffice';
    }

    private function bin_exists($bin) {
        if (file_exists($bin) && is_executable($bin)) return true;
        // Windows / 没绝对路径时尝试 which
        $cmd = (stripos(PHP_OS, 'WIN') === 0) ? "where " : "command -v ";
        $out = @shell_exec($cmd . escapeshellarg($bin) . ' 2>/dev/null');
        return !empty(trim((string) $out));
    }

    /**
     * 给文章生成预览图
     *
     * @param int    $post_id
     * @param string $local_file  服务器本地文件副本路径（PDF / Office / 图片）
     * @param array  $args        ['pages' => N]
     * @return array|WP_Error  ['count' => N, 'urls' => [..]]
     */
    public function build_for_post($post_id, $local_file, $args = []) {
        $env = $this->check_environment();
        if (is_wp_error($env)) {
            update_post_meta($post_id, '_vdp_preview_status', 'failed');
            update_post_meta($post_id, '_vdp_preview_error', $env->get_error_message());
            return $env;
        }

        if (!file_exists($local_file)) {
            return new WP_Error('vdp_preview_no_file', '本地文件不存在');
        }

        update_post_meta($post_id, '_vdp_preview_status', 'pending');

        $args = wp_parse_args($args, [
            'pages' => max(1, (int) vdp_opt('preview_pages_count', 3)),
        ]);

        $ext = strtolower(pathinfo($local_file, PATHINFO_EXTENSION));
        $pdf_path = $local_file;
        $tmp_pdf  = '';

        // Office → PDF
        $office_exts = ['doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'odt', 'odp', 'ods', 'rtf'];
        if (in_array($ext, $office_exts, true)) {
            $tmp_pdf = $this->office_to_pdf($local_file);
            if (is_wp_error($tmp_pdf)) {
                update_post_meta($post_id, '_vdp_preview_status', 'failed');
                update_post_meta($post_id, '_vdp_preview_error', $tmp_pdf->get_error_message());
                return $tmp_pdf;
            }
            $pdf_path = $tmp_pdf;
        } elseif ($ext !== 'pdf') {
            update_post_meta($post_id, '_vdp_preview_status', 'unsupported');
            return new WP_Error('vdp_preview_unsupported', '不支持的文件类型: ' . $ext);
        }

        // PDF → webp
        $r = $this->pdf_to_webp($pdf_path, $post_id, $args['pages']);

        // 清理临时 PDF
        if ($tmp_pdf && file_exists($tmp_pdf)) {
            @unlink($tmp_pdf);
        }

        if (is_wp_error($r)) {
            update_post_meta($post_id, '_vdp_preview_status', 'failed');
            update_post_meta($post_id, '_vdp_preview_error', $r->get_error_message());
            return $r;
        }

        update_post_meta($post_id, '_vdp_preview_status', 'done');
        update_post_meta($post_id, '_vdp_preview_pages', $r['count']);
        update_post_meta($post_id, '_vdp_preview_urls', $r['urls']);
        delete_post_meta($post_id, '_vdp_preview_error');

        return $r;
    }

    /**
     * Office → PDF（写入系统临时目录，调用方负责删）
     */
    private function office_to_pdf($office_path) {
        $bin = $this->libreoffice_bin();
        $tmp_dir = trailingslashit(get_temp_dir()) . 'vdp-libre-' . wp_generate_password(8, false);
        if (!wp_mkdir_p($tmp_dir)) {
            return new WP_Error('vdp_preview_mkdir', '无法创建临时目录');
        }

        // --headless 无 GUI；--convert-to pdf 转 PDF；--outdir 指定输出目录
        $cmd = sprintf(
            '%s --headless --convert-to pdf --outdir %s %s 2>&1',
            escapeshellcmd($bin),
            escapeshellarg($tmp_dir),
            escapeshellarg($office_path)
        );

        $output = [];
        $code   = 0;
        @exec($cmd, $output, $code);

        $base = pathinfo($office_path, PATHINFO_FILENAME);
        $pdf  = trailingslashit($tmp_dir) . $base . '.pdf';

        if ($code !== 0 || !file_exists($pdf)) {
            $this->rrmdir($tmp_dir);
            return new WP_Error(
                'vdp_preview_libreoffice_failed',
                'LibreOffice 转换失败 (code ' . $code . '): ' . implode("\n", $output)
            );
        }

        // 把生成的 PDF 移到临时父目录，再删空目录
        $final = trailingslashit(get_temp_dir()) . 'vdp-' . wp_generate_password(10, false) . '.pdf';
        @rename($pdf, $final);
        $this->rrmdir($tmp_dir);
        return $final;
    }

    /**
     * PDF → webp（前 N 页）
     */
    private function pdf_to_webp($pdf_path, $post_id, $pages) {
        $upload_dir = wp_upload_dir();
        $out_dir = trailingslashit($upload_dir['basedir']) . 'vdp-previews/' . intval($post_id);
        $out_url = trailingslashit($upload_dir['baseurl']) . 'vdp-previews/' . intval($post_id);

        if (!wp_mkdir_p($out_dir)) {
            return new WP_Error('vdp_preview_mkdir', '无法创建预览图目录');
        }

        // 清掉旧图（重新生成场景）
        foreach (glob(trailingslashit($out_dir) . '*.webp') ?: [] as $old) {
            @unlink($old);
        }

        $urls = [];
        try {
            for ($i = 0; $i < $pages; $i++) {
                $im = new Imagick();
                $im->setResolution(150, 150);
                // 第 i 页（Imagick 从 0 开始计数）
                if (!@$im->readImage($pdf_path . '[' . $i . ']')) {
                    $im->clear();
                    break; // 已超出页数
                }
                $im->setImageFormat('webp');
                $im->setImageCompressionQuality(82);
                $im->setBackgroundColor('white');
                $im->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
                $im->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);

                $page_no = $i + 1;
                $file = trailingslashit($out_dir) . 'page-' . $page_no . '.webp';
                $im->writeImage($file);
                $im->clear();
                $im->destroy();

                $urls[] = trailingslashit($out_url) . 'page-' . $page_no . '.webp?v=' . time();
            }
        } catch (Exception $e) {
            return new WP_Error('vdp_preview_imagick', 'Imagick 异常: ' . $e->getMessage());
        }

        if (empty($urls)) {
            return new WP_Error('vdp_preview_no_pages', '未能生成任何预览图');
        }

        return ['count' => count($urls), 'urls' => $urls];
    }

    private function rrmdir($dir) {
        if (!is_dir($dir)) return;
        $items = @scandir($dir) ?: [];
        foreach ($items as $f) {
            if ($f === '.' || $f === '..') continue;
            $p = $dir . DIRECTORY_SEPARATOR . $f;
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}

function vdp_preview_builder() {
    static $instance = null;
    if ($instance === null) {
        $instance = new VDP_Preview_Builder();
    }
    return $instance;
}

/**
 * 异步生成入口（WP Cron 调用）
 */
add_action('vdp_build_preview_async', 'vdp_build_preview_async_handler', 10, 2);
function vdp_build_preview_async_handler($post_id, $local_file) {
    if (!$post_id || !$local_file) return;
    $r = vdp_preview_builder()->build_for_post($post_id, $local_file);
    // 临时副本生成完成可以删
    if (file_exists($local_file) && strpos($local_file, get_temp_dir()) === 0) {
        @unlink($local_file);
    }
    return $r;
}

/**
 * 上传后调度（同步 / 异步）
 */
function vdp_schedule_preview_build($post_id, $local_file, $async = true) {
    if ($async) {
        wp_schedule_single_event(time() + 5, 'vdp_build_preview_async', [$post_id, $local_file]);
        return true;
    }
    return vdp_preview_builder()->build_for_post($post_id, $local_file);
}
