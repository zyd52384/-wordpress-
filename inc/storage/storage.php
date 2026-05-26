<?php
/**
 * 存储统一入口
 *
 * 上层（上传 / 下载 / 删除）只面向 vdp_storage()，
 * 由本类根据后台配置 + 单篇覆盖路由到 COS / 123 网盘。
 *
 * 标准返回结构：
 *   upload  => ['engine' => 'cos|pan123', 'key' => 'xxx', 'url' => '...', 'size' => 0, 'md5' => '...']
 *   error   => WP_Error
 */

if (!defined('ABSPATH')) exit;

class VDP_Storage {

    const ENGINE_COS    = 'cos';
    const ENGINE_PAN123 = 'pan123';

    /** 当前默认引擎（来自主题选项） */
    public function default_engine() {
        $engine = vdp_opt('storage_engine', self::ENGINE_COS);
        return in_array($engine, [self::ENGINE_COS, self::ENGINE_PAN123], true) ? $engine : self::ENGINE_COS;
    }

    /** 该引擎是否可用（已启用 + 已配置） */
    public function is_engine_available($engine) {
        if ($engine === self::ENGINE_COS) {
            if (!vdp_opt('cos_enabled')) return false;
            return function_exists('vdp_cos') && vdp_cos()->is_configured();
        }
        if ($engine === self::ENGINE_PAN123) {
            if (!vdp_opt('pan123_enabled')) return false;
            return function_exists('vdp_pan123') && vdp_pan123()->is_configured();
        }
        return false;
    }

    /** 选择本次操作要用的引擎（指定 → 默认 → 任一可用） */
    public function pick_engine($preferred = '') {
        $candidates = array_filter([
            $preferred,
            $this->default_engine(),
            self::ENGINE_COS,
            self::ENGINE_PAN123,
        ]);
        $candidates = array_unique($candidates);
        foreach ($candidates as $engine) {
            if ($this->is_engine_available($engine)) {
                return $engine;
            }
        }
        return '';
    }

    /**
     * 上传文件
     *
     * @param string $local_path 服务器本地文件路径
     * @param string $filename   原始文件名（用于生成 key 和 content-type）
     * @param array  $args       可选：post_id、engine（强制使用某引擎）
     * @return array|WP_Error
     */
    public function upload($local_path, $filename, $args = []) {
        $args = wp_parse_args($args, [
            'post_id' => 0,
            'engine'  => '',
        ]);

        $engine = $this->pick_engine($args['engine']);
        if (!$engine) {
            return new WP_Error('vdp_storage_no_engine', '没有可用的存储引擎，请在主题设置中启用 COS 或 123 网盘');
        }

        if (!file_exists($local_path)) {
            return new WP_Error('vdp_storage_no_file', '本地文件不存在');
        }

        $size = filesize($local_path);
        $md5  = md5_file($local_path);

        if ($engine === self::ENGINE_COS) {
            $cos = vdp_cos();
            $key = $cos->generate_cos_key($filename, $args['post_id']);
            $r = $cos->upload_file($local_path, $key);
            if (is_wp_error($r)) return $r;
            return [
                'engine' => self::ENGINE_COS,
                'key'    => $r['key'],
                'url'    => $r['url'],
                'size'   => $size,
                'md5'    => $md5,
            ];
        }

        if ($engine === self::ENGINE_PAN123) {
            $pan = vdp_pan123();
            $r = $pan->upload_file($local_path, $filename, [
                'post_id' => $args['post_id'],
                'md5'     => $md5,
                'size'    => $size,
            ]);
            if (is_wp_error($r)) return $r;
            return [
                'engine' => self::ENGINE_PAN123,
                'key'    => $r['file_id'],
                'url'    => isset($r['url']) ? $r['url'] : '',
                'size'   => $size,
                'md5'    => $md5,
            ];
        }

        return new WP_Error('vdp_storage_unknown_engine', '未知的存储引擎: ' . $engine);
    }

    /**
     * 获取下载直链（私有/带签名）
     *
     * @param string $engine
     * @param string $key
     * @param int    $expires 秒
     * @return string|WP_Error
     */
    public function get_download_url($engine, $key, $expires = 0) {
        if ($engine === self::ENGINE_COS) {
            if (!$expires) $expires = (int) vdp_opt('cos_signed_url_expire', 600);
            return vdp_cos()->get_private_file_url($key, $expires);
        }
        if ($engine === self::ENGINE_PAN123) {
            if (!$expires) $expires = (int) vdp_opt('pan123_link_expire', 3600);
            return vdp_pan123()->get_download_url($key, $expires);
        }
        return new WP_Error('vdp_storage_unknown_engine', '未知存储引擎');
    }

    /**
     * 删除文件
     */
    public function delete($engine, $key) {
        if ($engine === self::ENGINE_COS) {
            return vdp_cos()->delete_file($key);
        }
        if ($engine === self::ENGINE_PAN123) {
            return vdp_pan123()->delete_file($key);
        }
        return false;
    }

    /**
     * 通过 post_id 读取存储元信息（meta）
     */
    public function meta_for_post($post_id) {
        $engine = get_post_meta($post_id, '_vdp_storage', true);
        $key    = get_post_meta($post_id, '_vdp_storage_key', true);

        if (!$engine || !$key) {
            $info = vdp_get_doc_file_info($post_id);
            if (!empty($info['cos_key'])) {
                $engine = self::ENGINE_COS;
                $key    = $info['cos_key'];
            }
        }

        return [
            'engine' => $engine ?: $this->default_engine(),
            'key'    => $key,
        ];
    }

    /**
     * 写入存储 meta 到文章
     */
    public function save_meta_to_post($post_id, $upload_result) {
        if (!$post_id || !is_array($upload_result)) return;
        update_post_meta($post_id, '_vdp_storage', $upload_result['engine']);
        update_post_meta($post_id, '_vdp_storage_key', $upload_result['key']);
        if (!empty($upload_result['size'])) update_post_meta($post_id, '_vdp_file_size', (int) $upload_result['size']);
        if (!empty($upload_result['md5']))  update_post_meta($post_id, '_vdp_file_md5', $upload_result['md5']);
    }
}

function vdp_storage() {
    static $instance = null;
    if ($instance === null) {
        $instance = new VDP_Storage();
    }
    return $instance;
}
