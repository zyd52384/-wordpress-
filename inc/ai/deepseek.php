<?php
/**
 * DeepSeek 摘要生成
 *
 * 调用 DeepSeek chat/completions 接口为文档生成 SEO 友好摘要。
 *
 * 依赖：API Key 在主题选项中配置（vdp_options.deepseek_api_key）
 *
 * 状态值：
 *   _vdp_ai_summary_status: pending / done / failed
 *   _vdp_ai_summary_at:     时间戳
 *   _vdp_ai_summary_error:  失败原因
 */

if (!defined('ABSPATH')) exit;

class VDP_DeepSeek {

    public function api_base()  { return rtrim((string) vdp_opt('deepseek_api_base', 'https://api.deepseek.com'), '/'); }
    public function api_key()   { return trim((string) vdp_opt('deepseek_api_key', '')); }
    public function model()     { return vdp_opt('deepseek_model', 'deepseek-chat'); }
    public function length()    { return max(80, (int) vdp_opt('ai_summary_length', 180)); }
    public function enabled()   { return !empty(vdp_opt('ai_summary_enabled')) && !empty($this->api_key()); }

    /**
     * chat/completions 调用
     *
     * @param array  $messages 对话数组
     * @param array  $opts     覆盖默认参数
     * @return string|WP_Error 返回 assistant 内容
     */
    public function chat($messages, $opts = []) {
        $key = $this->api_key();
        if (empty($key)) {
            return new WP_Error('deepseek_no_key', 'DeepSeek API Key 未配置');
        }

        $payload = array_merge([
            'model'       => $this->model(),
            'messages'    => $messages,
            'temperature' => 0.7,
            'max_tokens'  => 800,
            'stream'      => false,
        ], $opts);

        $resp = wp_remote_post($this->api_base() . '/chat/completions', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $key,
            ],
            'body'      => wp_json_encode($payload),
            'timeout'   => 60,
            'sslverify' => true,
        ]);

        if (is_wp_error($resp)) return $resp;

        $code = wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);
        $json = json_decode($body, true);

        if ($code !== 200) {
            $msg = isset($json['error']['message']) ? $json['error']['message'] : substr($body, 0, 300);
            return new WP_Error('deepseek_http_' . $code, 'DeepSeek 接口错误 HTTP ' . $code . ': ' . $msg);
        }
        $content = isset($json['choices'][0]['message']['content']) ? $json['choices'][0]['message']['content'] : '';
        if (empty($content)) {
            return new WP_Error('deepseek_empty', 'DeepSeek 返回空内容');
        }
        return trim($content);
    }

    /**
     * 为一个文档文件生成 SEO 摘要
     *
     * @param string $title       文章标题
     * @param string $filename    文件名
     * @param string $extra_text  附加上下文（如 PDF 抽取的前几页文本）
     * @param string $category    分类名（用于兜底）
     * @return string|WP_Error
     */
    public function summarize($title, $filename = '', $extra_text = '', $category = '') {
        $length = $this->length();

        // 上下文截断：超过 4000 字会显著推高 token 成本
        if (mb_strlen($extra_text) > 4000) {
            $extra_text = mb_substr($extra_text, 0, 4000);
        }

        $sys = '你是一名 SEO 文案专家，擅长为虚拟文档资料站撰写吸引点击且利于搜索收录的中文摘要。'
             . '要求：自然流畅、含核心关键词、不堆砌、不夸张、不出现"本文/这份资料"等空洞表述；'
             . '直接陈述资料的内容、覆盖范围与读者收益。';

        $user_parts = ['请根据以下信息撰写一段 ' . $length . ' 字左右的资料介绍摘要：'];
        $user_parts[] = '【标题】' . $title;
        if ($filename)   $user_parts[] = '【文件名】' . $filename;
        if ($category)   $user_parts[] = '【分类】' . $category;
        if ($extra_text) {
            $user_parts[] = '【资料前几页节选】' . "\n" . $extra_text;
        } else {
            $user_parts[] = '（无法提取正文，请基于标题与文件名合理推断内容方向，避免编造具体数字/章节）';
        }
        $user_parts[] = '只输出摘要正文，不要加引号、标题、Markdown 标记或解释说明。';

        return $this->chat([
            ['role' => 'system', 'content' => $sys],
            ['role' => 'user',   'content' => implode("\n", $user_parts)],
        ], [
            'temperature' => 0.7,
            'max_tokens'  => max(400, intval($length * 4)),
        ]);
    }

    /**
     * 抽取 PDF 前 N 页文本（有则返回字符串，没装解析器返回空串）
     */
    public function extract_pdf_text($pdf_path, $max_pages = 3) {
        if (!file_exists($pdf_path)) return '';

        // 1. 优先 pdftotext 命令（poppler-utils）
        $bin = trim((string) @shell_exec('command -v pdftotext 2>/dev/null'));
        if (!empty($bin)) {
            $cmd = sprintf('%s -l %d -layout %s - 2>/dev/null',
                escapeshellcmd($bin), (int) $max_pages, escapeshellarg($pdf_path));
            $out = (string) @shell_exec($cmd);
            return trim($out);
        }

        // 2. fallback：Smalot\PdfParser 若已 composer require
        if (class_exists('\Smalot\PdfParser\Parser')) {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($pdf_path);
                $pages = $pdf->getPages();
                $text = '';
                $i = 0;
                foreach ($pages as $page) {
                    if ($i++ >= $max_pages) break;
                    $text .= $page->getText() . "\n";
                }
                return trim($text);
            } catch (Exception $e) { /* ignore */ }
        }
        return '';
    }
}

function vdp_deepseek() {
    static $instance = null;
    if ($instance === null) {
        $instance = new VDP_DeepSeek();
    }
    return $instance;
}

/* =========================================================
   高层封装：为某篇文章生成摘要
   ========================================================= */

/**
 * 为文章生成 AI 摘要（同步执行 + 重试 2 次指数退避）
 *
 * @param int    $post_id
 * @param string $local_file 可选，本地文件副本路径，用于抽取 PDF 文本
 * @return true|WP_Error
 */
function vdp_generate_post_summary($post_id, $local_file = '') {
    $post_id = (int) $post_id;
    $post = get_post($post_id);
    if (!$post) return new WP_Error('vdp_ai_no_post', '文章不存在');

    $client = vdp_deepseek();
    if (!$client->enabled()) {
        return new WP_Error('vdp_ai_disabled', 'AI 摘要未启用或未配置 API Key');
    }

    // 是否覆盖已有 excerpt
    if (empty(vdp_opt('ai_summary_overwrite')) && !empty($post->post_excerpt)) {
        return new WP_Error('vdp_ai_skip_existing', '已有摘要且未开启覆盖');
    }

    update_post_meta($post_id, '_vdp_ai_summary_status', 'pending');

    // 准备上下文
    $info = function_exists('vdp_get_doc_file_info') ? vdp_get_doc_file_info($post_id) : [];
    $filename = !empty($info['name']) ? $info['name'] : '';

    $extra_text = '';
    if ($local_file && file_exists($local_file)) {
        $ext = strtolower(pathinfo($local_file, PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            $extra_text = $client->extract_pdf_text($local_file, 3);
        }
    }

    // 分类
    $cats = wp_get_post_categories($post_id, ['fields' => 'names']);
    $category = !empty($cats) ? implode('、', $cats) : '';

    // 重试 2 次（含首次共 3 次），指数退避
    $last_err = null;
    for ($attempt = 0; $attempt < 3; $attempt++) {
        if ($attempt > 0) {
            sleep(min(8, pow(2, $attempt)));
        }
        $r = $client->summarize($post->post_title, $filename, $extra_text, $category);
        if (!is_wp_error($r)) {
            wp_update_post([
                'ID'           => $post_id,
                'post_excerpt' => $r,
            ]);
            update_post_meta($post_id, '_vdp_ai_summary_status', 'done');
            update_post_meta($post_id, '_vdp_ai_summary_at', time());
            delete_post_meta($post_id, '_vdp_ai_summary_error');
            return true;
        }
        $last_err = $r;
    }

    update_post_meta($post_id, '_vdp_ai_summary_status', 'failed');
    update_post_meta($post_id, '_vdp_ai_summary_error', $last_err ? $last_err->get_error_message() : '未知错误');
    return $last_err ?: new WP_Error('vdp_ai_failed', 'DeepSeek 调用失败');
}

/**
 * 异步生成入口（WP Cron）
 */
add_action('vdp_generate_summary_async', 'vdp_generate_summary_async_handler', 10, 2);
function vdp_generate_summary_async_handler($post_id, $local_file = '') {
    vdp_generate_post_summary($post_id, $local_file);
    if ($local_file && file_exists($local_file) && strpos($local_file, get_temp_dir()) === 0) {
        @unlink($local_file);
    }
}

/**
 * 上传后调度（同步 / 异步）
 */
function vdp_schedule_summary_generation($post_id, $local_file = '', $async = null) {
    if ($async === null) {
        $async = !empty(vdp_opt('ai_summary_async', true));
    }
    if ($async) {
        wp_schedule_single_event(time() + 10, 'vdp_generate_summary_async', [$post_id, $local_file]);
        return true;
    }
    return vdp_generate_post_summary($post_id, $local_file);
}
