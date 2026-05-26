<?php
/**
 * 腾讯云 COS 对象存储
 */

if (!defined('ABSPATH')) exit;

class VDP_COS {

    private $secret_id;
    private $secret_key;
    private $region;
    private $bucket;
    private $cdn_domain;
    private $preview_pages;

    public function __construct() {
        $settings = get_option('vdp_cos_settings', []);

        // 兼容 CSF 主题选项
        $opts = get_option('vdp_options', []);
        if (!empty($opts) && is_array($opts)) {
            $map = [
                'secret_id'  => 'cos_secret_id',
                'secret_key' => 'cos_secret_key',
                'region'     => 'cos_region',
                'bucket'     => 'cos_bucket',
                'cdn_domain' => 'cos_domain',
            ];
            foreach ($map as $k => $opt_k) {
                if (empty($settings[$k]) && !empty($opts[$opt_k])) {
                    $settings[$k] = $opts[$opt_k];
                }
            }
        }

        $this->secret_id     = isset($settings['secret_id']) ? trim($settings['secret_id']) : '';
        $this->secret_key    = isset($settings['secret_key']) ? trim($settings['secret_key']) : '';
        $this->region        = isset($settings['region']) ? trim($settings['region']) : 'ap-guangzhou';
        $this->bucket        = isset($settings['bucket']) ? trim($settings['bucket']) : '';
        $this->cdn_domain    = isset($settings['cdn_domain']) ? trim($settings['cdn_domain']) : '';
        $this->preview_pages = isset($settings['preview_pages']) ? intval($settings['preview_pages']) : 3;
    }

    public function is_configured() {
        return !empty($this->secret_id) && !empty($this->secret_key) && !empty($this->bucket);
    }

    public function get_config() {
        return [
            'secret_id'     => $this->secret_id,
            'secret_key'    => $this->secret_key,
            'region'        => $this->region,
            'bucket'        => $this->bucket,
            'cdn_domain'    => $this->cdn_domain,
            'preview_pages' => $this->preview_pages,
        ];
    }

    public function get_bucket_endpoint() {
        return $this->bucket . '.cos.' . $this->region . '.myqcloud.com';
    }

    private function url_encode_path($path) {
        $parts = explode('/', $path);
        $encoded = [];
        foreach ($parts as $part) {
            $encoded[] = rawurlencode($part);
        }
        return implode('/', $encoded);
    }

    private function build_authorization($method, $uri_path, $headers = [], $params = [], $expires = 3600) {
        $start_time = time();
        $end_time = $start_time + $expires;
        $key_time = $start_time . ';' . $end_time;

        $sign_key = hash_hmac('sha1', $key_time, $this->secret_key);

        $http_method = strtolower($method);
        $uri = '/' . ltrim($uri_path, '/');

        // URL 参数：key 转小写并 URL 编码，value URL 编码，按编码后 key 排序
        $param_pairs = [];
        foreach ($params as $k => $v) {
            $ek = strtolower(rawurlencode($k));
            $ev = rawurlencode($v);
            $param_pairs[$ek] = $ev;
        }
        ksort($param_pairs);
        $query_parts = [];
        foreach ($param_pairs as $ek => $ev) {
            $query_parts[] = $ek . '=' . $ev;
        }
        $query_string = implode('&', $query_parts);
        $param_list_str = implode(';', array_keys($param_pairs));

        // 必须包含 Host
        if (!isset($headers['Host'])) {
            $headers['Host'] = $this->get_bucket_endpoint();
        }
        // 头部：key 转小写并 URL 编码，value URL 编码，按编码后 key 排序
        $header_pairs = [];
        foreach ($headers as $k => $v) {
            $key = trim($k);
            $val = trim($v);
            if ($key === '' || $val === '') continue;
            $ek = strtolower(rawurlencode($key));
            $ev = rawurlencode($val);
            $header_pairs[$ek] = $ev;
        }
        ksort($header_pairs);
        $header_lines = [];
        foreach ($header_pairs as $ek => $ev) {
            $header_lines[] = $ek . '=' . $ev;
        }
        $header_string = implode('&', $header_lines);
        $header_list_str = implode(';', array_keys($header_pairs));

        $http_string = $http_method . "\n" . $uri . "\n" . $query_string . "\n" . $header_string . "\n";
        $sha1_http = sha1($http_string);
        $string_to_sign = "sha1\n" . $key_time . "\n" . $sha1_http . "\n";
        $signature = hash_hmac('sha1', $string_to_sign, $sign_key);

        return 'q-sign-algorithm=sha1'
            . '&q-ak=' . $this->secret_id
            . '&q-sign-time=' . $key_time
            . '&q-key-time=' . $key_time
            . '&q-header-list=' . $header_list_str
            . '&q-url-param-list=' . $param_list_str
            . '&q-signature=' . $signature;
    }

    public function upload_file($local_path, $cos_key, $content_type = '') {
        if (!$this->is_configured()) {
            return new WP_Error('cos_not_configured', 'COS 尚未配置');
        }
        if (!file_exists($local_path)) {
            return new WP_Error('file_not_found', '文件不存在: ' . $local_path);
        }

        $file_size = filesize($local_path);
        $fp = fopen($local_path, 'rb');
        if (!$fp) {
            return new WP_Error('file_open_error', '无法打开文件');
        }

        if (empty($content_type)) {
            $content_type = $this->get_mime_type($local_path);
        }

        $endpoint = $this->get_bucket_endpoint();
        $uri_path = '/' . ltrim($cos_key, '/');

        $headers = [
            'Host'         => $endpoint,
            'Content-Type' => $content_type,
        ];

        $authorization = $this->build_authorization('PUT', $uri_path, $headers);
        $url = 'https://' . $endpoint . $this->url_encode_path($uri_path);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_UPLOAD         => true,
            CURLOPT_INFILE         => $fp,
            CURLOPT_INFILESIZE     => $file_size,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_TIMEOUT        => 600,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => [
                'Host: ' . $endpoint,
                'Content-Type: ' . $content_type,
                'Authorization: ' . $authorization,
            ],
        ]);

        $raw_response = curl_exec($ch);
        $http_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $curl_error  = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if ($curl_error) {
            return new WP_Error('cos_curl_error', 'cURL 错误: ' . $curl_error);
        }
        if ($http_code !== 200) {
            $body = substr($raw_response, $header_size, 500);
            return new WP_Error('cos_upload_error', '上传失败 (HTTP ' . $http_code . '): ' . $body);
        }

        return [
            'url' => $this->get_file_url($cos_key),
            'key' => $cos_key,
        ];
    }

    public function delete_file($cos_key) {
        if (!$this->is_configured()) return false;

        $endpoint = $this->get_bucket_endpoint();
        $uri_path = '/' . ltrim($cos_key, '/');
        $headers = ['Host' => $endpoint];
        $authorization = $this->build_authorization('DELETE', $uri_path, $headers);
        $headers['Authorization'] = $authorization;

        $url = 'https://' . $endpoint . $this->url_encode_path($uri_path);
        $response = wp_remote_request($url, [
            'method'    => 'DELETE',
            'headers'   => $headers,
            'sslverify' => false,
        ]);

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 204;
    }

    public function get_file_url($cos_key) {
        $path = $this->url_encode_path('/' . ltrim($cos_key, '/'));
        if ($this->cdn_domain) {
            return 'https://' . rtrim($this->cdn_domain, '/') . $path;
        }
        return 'https://' . $this->get_bucket_endpoint() . $path;
    }

    public function get_private_file_url($cos_key, $expires = 3600) {
        $endpoint = $this->get_bucket_endpoint();
        $uri_path = '/' . ltrim($cos_key, '/');
        $headers = ['Host' => $endpoint];
        $authorization = $this->build_authorization('GET', $uri_path, $headers, [], $expires);
        $url = 'https://' . $endpoint . $this->url_encode_path($uri_path);
        return $url . '?auth=' . rawurlencode($authorization);
    }

    public function generate_cos_key($filename, $post_id = 0) {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $raw_name = pathinfo($filename, PATHINFO_FILENAME);
        $safe_name = preg_replace('/[^\p{Han}\w\-]/u', '_', $raw_name);
        $safe_name = preg_replace('/_+/', '_', $safe_name);
        $safe_name = trim($safe_name, '_');

        if (empty($safe_name)) {
            $safe_name = 'file_' . substr(md5($filename), 0, 8);
        }

        $date_path = date('Y/m');
        $unique_id = uniqid() . '_' . wp_rand(1000, 9999);

        if ($post_id) {
            return 'docs/' . $date_path . '/' . $post_id . '_' . $unique_id . '_' . $safe_name . '.' . $ext;
        }
        return 'docs/' . $date_path . '/' . $unique_id . '_' . $safe_name . '.' . $ext;
    }

    private function get_mime_type($file_path) {
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        $types = [
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'ppt'  => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'txt'  => 'text/plain',
            'zip'  => 'application/zip',
            'rar'  => 'application/vnd.rar',
            '7z'   => 'application/x-7z-compressed',
        ];
        return isset($types[$ext]) ? $types[$ext] : 'application/octet-stream';
    }

    public function test_connection() {
        if (!$this->is_configured()) {
            return new WP_Error('cos_not_configured', 'COS 尚未配置');
        }

        $endpoint = $this->get_bucket_endpoint();
        $headers = ['Host' => $endpoint];
        $params = ['max-keys' => '1'];
        $authorization = $this->build_authorization('GET', '/', $headers, $params);
        $headers['Authorization'] = $authorization;

        $url = 'https://' . $endpoint . '/?max-keys=1';
        $response = wp_remote_get($url, [
            'headers'   => $headers,
            'sslverify' => false,
            'timeout'   => 15,
        ]);

        if (is_wp_error($response)) return $response;

        $code = wp_remote_retrieve_response_code($response);
        if ($code === 200) {
            return ['message' => '连接成功'];
        }

        $body = wp_remote_retrieve_body($response);
        return new WP_Error('cos_test_failed', '连接失败 (HTTP ' . $code . '): ' . substr($body, 0, 300));
    }
}

function vdp_cos() {
    static $instance = null;
    if ($instance === null) {
        $instance = new VDP_COS();
    }
    return $instance;
}
