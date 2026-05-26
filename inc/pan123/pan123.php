<?php
/**
 * 123 网盘开放平台客户端
 *
 * 官方文档：https://123yunpan.yuque.com/org-wiki-123yunpan-muaork/cr6ced
 *
 * 关键约束：
 *   - HTTPS + JSON + UTF-8，所有请求必带 Platform: open_platform
 *   - access_token 有效期 30 天；单 client_id 同时最多 3 个 token，超出会踢前面的下线
 *   - QPS 限制（部分）：access_token 10、file/list 10、file/delete 10、file/move 20
 *
 * 仍待官方文档确认的字段（标 [TODO-DOC]），需要从具体接口页核对：
 *   - access_token 入参 / 响应字段名
 *   - upload/v1/file/create 响应字段
 *   - 分片上传 URL / 完成上传 / 下载直链 三个接口路径与字段
 */

if (!defined('ABSPATH')) exit;

class VDP_Pan123 {

    const API_BASE = 'https://open-api.123pan.com';
    const TOKEN_TRANSIENT = 'vdp_pan123_access_token';
    const CHUNK_SIZE = 16 * 1024 * 1024; // 16MB（分片大小，需要按官方文档确认）

    /**
     * 接口路径
     *
     * 已确认的路径（来自开发须知 / QPS 表 / 各接口详情页）
     * 字段名仍待官方接口详情页核对的，标 [字段-TODO]
     *
     * QPS 限制（同 uid，每秒最大请求次数）：
     *   access_token 8、file/create 5、get_upload_url 20、list_upload_parts 20、upload_complete 20、
     *   upload_async_result 5、download_info 5、file/delete 1、file/trash 5、file/list 1、v2/file/list 15
     */
    private $endpoints = [
        'access_token'         => '/api/v1/access_token',                  // ✓ 路径+字段已对齐
        'upload_create'        => '/upload/v1/file/create',                // ✓ 路径；响应字段 [字段-TODO]
        'upload_url'           => '/upload/v1/file/get_upload_url',        // ✓ 路径；入参/响应 [字段-TODO]
        'upload_list'          => '/upload/v1/file/list_upload_parts',     // ✓ 路径；入参/响应 [字段-TODO]
        'upload_complete'      => '/upload/v1/file/upload_complete',       // ✓ 路径；可能异步
        'upload_async_result'  => '/upload/v1/file/upload_async_result',   // ✓ 路径（complete 异步时轮询用）
        'download_info'        => '/api/v1/file/download_info',            // ✓ 路径；入参/响应 [字段-TODO]
        'file_delete'          => '/api/v1/file/delete',                   // ✓ 路径；入参 [字段-TODO]，QPS 仅 1
        'file_trash'           => '/api/v1/file/trash',                    // ✓ 路径
        'file_info'            => '/api/v1/file/infos',                    // ✓ 路径（注意是 infos 复数）
        'file_list'            => '/api/v2/file/list',                     // ✓
        'file_mkdir'           => '/upload/v1/file/mkdir',                 // ✓
    ];

    private $client_id;
    private $client_secret;
    private $root_dir;

    public function __construct() {
        $this->client_id     = trim((string) vdp_opt('pan123_client_id', ''));
        $this->client_secret = trim((string) vdp_opt('pan123_client_secret', ''));
        $this->root_dir      = vdp_opt('pan123_root_dir', '0');
    }

    public function is_configured() {
        return !empty($this->client_id) && !empty($this->client_secret);
    }

    /* =========================================================
       Token 管理
       ========================================================= */

    /**
     * 获取 access_token（带 transient 缓存）
     *
     * 接口：POST /api/v1/access_token
     * 请求 body：{ clientID, clientSecret }
     * 响应 data：{ accessToken: string, expiredAt: ISO8601 字符串 }
     * 注意：此接口本身有 QPS 限制（10 QPS），且不需要 Authorization
     */
    public function get_access_token($force_refresh = false) {
        if (!$this->is_configured()) {
            return new WP_Error('pan123_not_configured', '123 网盘尚未配置');
        }

        if (!$force_refresh) {
            $cached = get_transient(self::TOKEN_TRANSIENT);
            if (!empty($cached)) return $cached;
        }

        $r = $this->raw_request('POST', $this->endpoints['access_token'], [
            'clientID'     => $this->client_id,
            'clientSecret' => $this->client_secret,
        ], false);

        if (is_wp_error($r)) return $r;

        // 错误码先解（raw_request 不解 code）
        if (isset($r['code']) && intval($r['code']) !== 0) {
            $msg = isset($r['message']) ? $r['message'] : '未知错误';
            return new WP_Error('pan123_token_failed', '获取 access_token 失败 [' . $r['code'] . ']: ' . $msg);
        }

        $token = isset($r['data']['accessToken']) ? $r['data']['accessToken'] : '';
        $exp   = isset($r['data']['expiredAt'])   ? $r['data']['expiredAt']   : '';

        if (empty($token)) {
            return new WP_Error('pan123_token_empty', 'access_token 响应为空: ' . wp_json_encode($r));
        }

        // expiredAt 是 ISO 8601 字符串，例 "2025-03-23T15:48:37+08:00"
        // 缓存留 1 天提前量；30 天有效期所以默认按 29 天兜底
        $ttl = 29 * DAY_IN_SECONDS;
        if ($exp) {
            $stamp = strtotime($exp);
            if ($stamp > time()) {
                $ttl = max(60, $stamp - time() - DAY_IN_SECONDS);
            }
        }
        set_transient(self::TOKEN_TRANSIENT, $token, $ttl);
        return $token;
    }

    /* =========================================================
       上传：完整链路（创建 → 分片 → 完成）
       ========================================================= */

    /**
     * 上传一个本地文件到 123 网盘
     *
     * @param string $local_path
     * @param string $filename
     * @param array  $args  ['post_id', 'md5', 'size', 'parent_id']
     * @return array|WP_Error  ['file_id', 'url', 'instant' => bool]
     */
    public function upload_file($local_path, $filename, $args = []) {
        if (!file_exists($local_path)) {
            return new WP_Error('pan123_no_file', '本地文件不存在');
        }

        $args = wp_parse_args($args, [
            'post_id'   => 0,
            'md5'       => '',
            'size'      => 0,
            'parent_id' => $this->root_dir,
        ]);

        $size = $args['size'] ?: filesize($local_path);
        $md5  = $args['md5'] ?: md5_file($local_path);

        // 1. 创建上传任务（同时尝试秒传）
        // 字段名按官方 curl 示例：parentFileID（注意大写 ID）/ filename / etag / size
        $create = $this->request('POST', $this->endpoints['upload_create'], [
            'parentFileID' => is_numeric($args['parent_id']) ? (int) $args['parent_id'] : $args['parent_id'],
            'filename'     => $filename,
            'etag'         => $md5,
            'size'         => $size,
        ]);
        if (is_wp_error($create)) return $create;

        $data = isset($create['data']) ? $create['data'] : [];

        // 秒传成功
        if (!empty($data['reuse']) || !empty($data['instant_complete'])) {
            $file_id = $this->dig($data, ['fileID', 'fileId', 'file_id']);
            return [
                'file_id' => (string) $file_id,
                'url'     => '',
                'instant' => true,
            ];
        }

        $preupload_id = $this->dig($data, ['preuploadID', 'preuploadId', 'preupload_id']);
        if (empty($preupload_id)) {
            return new WP_Error('pan123_upload_init_failed', '上传初始化失败：' . wp_json_encode($data));
        }

        // 2. 分片上传
        $upload_r = $this->upload_chunks($local_path, $preupload_id, $size);
        if (is_wp_error($upload_r)) return $upload_r;

        // 3. 完成上传
        // [TODO-DOC] 完成接口入参
        $complete = $this->request('POST', $this->endpoints['upload_complete'], [
            'preuploadID' => $preupload_id,
        ]);
        if (is_wp_error($complete)) return $complete;

        $file_id = $this->dig($complete, ['data.fileID', 'data.fileId', 'data.file_id']);
        if (empty($file_id)) {
            return new WP_Error('pan123_upload_complete_no_id', '完成上传未返回 fileID：' . wp_json_encode($complete));
        }

        return [
            'file_id' => (string) $file_id,
            'url'     => '',
            'instant' => false,
        ];
    }

    /**
     * 分片上传循环
     */
    private function upload_chunks($local_path, $preupload_id, $size) {
        $fp = fopen($local_path, 'rb');
        if (!$fp) return new WP_Error('pan123_open_fail', '无法打开本地文件');

        $chunk_size = self::CHUNK_SIZE;
        $total = (int) ceil($size / $chunk_size);

        for ($i = 0; $i < $total; $i++) {
            $part_no = $i + 1; // 123 网盘分片号一般从 1 起

            // 拿到本片的预签名上传 URL
            // [TODO-DOC] 参数名 sliceNo / partNumber 按文档定
            $url_resp = $this->request('POST', $this->endpoints['upload_url'], [
                'preuploadID' => $preupload_id,
                'sliceNo'     => $part_no,
            ]);
            if (is_wp_error($url_resp)) { fclose($fp); return $url_resp; }

            $put_url = $this->dig($url_resp, ['data.presignedURL', 'data.presigned_url', 'data.uploadUrl']);
            if (empty($put_url)) { fclose($fp); return new WP_Error('pan123_no_put_url', '获取分片上传 URL 失败'); }

            fseek($fp, $i * $chunk_size);
            $bytes = fread($fp, $chunk_size);

            $put = wp_remote_request($put_url, [
                'method'    => 'PUT',
                'body'      => $bytes,
                'timeout'   => 600,
                'sslverify' => false,
                'headers'   => ['Content-Length' => strlen($bytes)],
            ]);
            if (is_wp_error($put)) { fclose($fp); return $put; }

            $code = wp_remote_retrieve_response_code($put);
            if ($code !== 200 && $code !== 204) {
                fclose($fp);
                return new WP_Error('pan123_chunk_failed', '分片 ' . $part_no . ' 上传失败 HTTP ' . $code);
            }
        }
        fclose($fp);
        return true;
    }

    /* =========================================================
       下载直链
       ========================================================= */

    public function get_download_url($file_id, $expires = 3600) {
        $r = $this->request('POST', $this->endpoints['download_info'], [
            'fileID' => $file_id,
        ]);
        if (is_wp_error($r)) return $r;

        // [TODO-DOC] 响应字段名
        $url = $this->dig($r, ['data.downloadUrl', 'data.url', 'data.download_url']);
        if (empty($url)) {
            return new WP_Error('pan123_no_download_url', '获取下载链接失败：' . wp_json_encode($r));
        }
        return $url;
    }

    /* =========================================================
       删除
       ========================================================= */

    public function delete_file($file_id) {
        // [TODO-DOC] 字段确认：fileIDs 数组 还是 fileID 单个
        $r = $this->request('POST', $this->endpoints['file_delete'], [
            'fileIDs' => [is_numeric($file_id) ? (int) $file_id : (string) $file_id],
        ]);
        return !is_wp_error($r);
    }

    /* =========================================================
       低层请求封装
       ========================================================= */

    /**
     * 带 Authorization 的请求（自动刷 token 一次）
     *
     * 已知错误码：
     *   1     内部错误
     *   401   access_token 无效
     *   429   请求太频繁
     *   5066  文件不存在
     *   5113  流量超限
     */
    public function request($method, $path, $body = [], $retry_on_401 = true) {
        $token = $this->get_access_token();
        if (is_wp_error($token)) return $token;

        $r = $this->raw_request($method, $path, $body, true, $token);
        if (is_wp_error($r)) return $r;

        $code = isset($r['code']) ? intval($r['code']) : 0;

        // 401 token 失效自动刷一次
        if ($retry_on_401 && $code === 401) {
            delete_transient(self::TOKEN_TRANSIENT);
            $token = $this->get_access_token(true);
            if (is_wp_error($token)) return $token;
            $r = $this->raw_request($method, $path, $body, true, $token);
            if (is_wp_error($r)) return $r;
            $code = isset($r['code']) ? intval($r['code']) : 0;
        }

        if ($code !== 0) {
            $msg = isset($r['message']) ? $r['message'] : (isset($r['msg']) ? $r['msg'] : '未知错误');
            $err_key = 'pan123_api_' . $code;
            // 给已知错误码加上更易读的中文提示
            $known = [
                1    => '123 网盘内部错误',
                401  => 'access_token 失效',
                429  => '请求过于频繁，已被限流',
                5066 => '文件不存在',
                5113 => '流量超限',
            ];
            if (isset($known[$code])) $msg = $known[$code] . '：' . $msg;
            return new WP_Error($err_key, '[' . $code . '] ' . $msg);
        }
        return $r;
    }

    /**
     * 原始请求（不解包错误码）
     */
    private function raw_request($method, $path, $body = [], $with_token = true, $token = '') {
        $url = self::API_BASE . $path;
        $headers = [
            'Content-Type' => 'application/json',
            'Platform'     => 'open_platform', // [TODO-DOC] 部分接口要求该 header，按文档确认
        ];
        if ($with_token && $token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $args = [
            'method'    => strtoupper($method),
            'headers'   => $headers,
            'timeout'   => 30,
            'sslverify' => false,
        ];
        if (strtoupper($method) !== 'GET') {
            $args['body'] = wp_json_encode($body);
        } elseif (!empty($body)) {
            $url = add_query_arg($body, $url);
        }

        $resp = wp_remote_request($url, $args);
        if (is_wp_error($resp)) return $resp;

        $code = wp_remote_retrieve_response_code($resp);
        $raw  = wp_remote_retrieve_body($resp);
        $json = json_decode($raw, true);

        if ($code >= 500) {
            return new WP_Error('pan123_http_5xx', '123 网盘服务异常 HTTP ' . $code . ': ' . substr($raw, 0, 200));
        }
        if (!is_array($json)) {
            return new WP_Error('pan123_bad_json', '响应不是 JSON: ' . substr($raw, 0, 200));
        }
        return $json;
    }

    /**
     * 多路径深取（按候选 key 顺序取第一个非空值，支持 a.b.c）
     */
    private function dig($arr, $keys) {
        if (!is_array($arr)) return null;
        foreach ((array) $keys as $key) {
            $cur = $arr;
            $ok = true;
            foreach (explode('.', $key) as $seg) {
                if (is_array($cur) && array_key_exists($seg, $cur)) {
                    $cur = $cur[$seg];
                } else {
                    $ok = false;
                    break;
                }
            }
            if ($ok && $cur !== null && $cur !== '') return $cur;
        }
        return null;
    }

    /* =========================================================
       自检
       ========================================================= */
    public function test_connection() {
        if (!$this->is_configured()) {
            return new WP_Error('pan123_not_configured', '123 网盘尚未配置 Client ID / Secret');
        }
        $token = $this->get_access_token(true);
        if (is_wp_error($token)) return $token;
        return ['message' => '连接成功，access_token 已获取'];
    }
}

function vdp_pan123() {
    static $instance = null;
    if ($instance === null) {
        $instance = new VDP_Pan123();
    }
    return $instance;
}
