<?php
/**
 * 后台批量上传页面
 */

if (!defined('ABSPATH')) exit;

/**
 * 渲染上传页面
 */
function vdp_render_upload_page() {
    $cos = vdp_cos();
    if (!$cos->is_configured()) {
        echo '<div class="wrap"><h1>批量上传文档</h1>';
        echo '<div class="notice notice-error"><p>请先在「主题设置」中配置腾讯云 COS 参数。</p>';
        echo '<p><a href="' . admin_url('themes.php?page=vdp_theme_options') . '" class="button">前往设置</a></p></div></div>';
        return;
    }

    $cos_config = $cos->get_config();
    $categories = get_categories(['hide_empty' => false]);
    ?>
    <div class="wrap vdp-upload-page">
        <h1>批量上传文档</h1>

        <!-- 上传设置 -->
        <div class="vdp-upload-settings">
            <div class="vdp-upload-field">
                <label>选择分类</label>
                <select id="vdp-category-select">
                    <option value="">— 选择分类 —</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat->term_id; ?>"><?php echo esc_html($cat->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="vdp-upload-field">
                <label>付费设置</label>
                <div class="vdp-pay-fields">
                    <select id="vdp-pay-type">
                        <option value="0">免费下载</option>
                        <option value="1">付费下载</option>
                    </select>
                    <input type="number" id="vdp-pay-price" placeholder="价格 (元)" min="0" step="0.01" style="display:none;">
                </div>
            </div>

            <div class="vdp-upload-field">
                <label>VIP限制</label>
                <select id="vdp-vip-limit">
                    <option value="0">不限VIP</option>
                    <option value="1">VIP1及以上</option>
                    <option value="2">仅VIP2</option>
                </select>
            </div>

            <div class="vdp-upload-field">
                <label>文档预览页数</label>
                <input type="number" id="vdp-preview-pages" value="<?php echo esc_attr($cos_config['preview_pages']); ?>" min="0" max="20" step="1">
                <p class="vdp-field-desc">使用腾讯云数据万象生成前 N 页预览图，填 0 则不预览</p>
            </div>
        </div>

        <!-- 拖拽上传区域 -->
        <div class="vdp-dropzone" id="vdp-dropzone">
            <div class="vdp-dropzone-content">
                <span class="vdp-dropzone-icon">📁</span>
                <p>拖放文件到这里，或点击选择文件</p>
                <p class="vdp-dropzone-hint">支持 PDF、DOC、DOCX、PPT、PPTX、XLS、XLSX、TXT、ZIP、RAR、7Z</p>
                <p class="vdp-dropzone-hint">单个文件最大 <?php echo round(wp_max_upload_size() / 1024 / 1024); ?>MB</p>
            </div>
            <input type="file" id="vdp-file-input" multiple
                   accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip,.rar,.7z"
                   class="vdp-file-input-invisible">
        </div>

        <!-- 文件列表 -->
        <div id="vdp-file-list" class="vdp-file-list"></div>

        <!-- 操作按钮 -->
        <div class="vdp-actions" style="display:none;" id="vdp-actions">
            <button id="vdp-start-upload" class="button button-primary button-hero">
                开始上传并发布
            </button>
            <span class="vdp-upload-status" id="vdp-upload-status"></span>
        </div>

        <!-- 进度/结果 -->
        <div id="vdp-progress" class="vdp-progress-wrap" style="display:none;">
            <h3>处理进度</h3>
            <div class="vdp-progress-bar">
                <div class="vdp-progress-fill" id="vdp-progress-fill"></div>
            </div>
            <div class="vdp-progress-text" id="vdp-progress-text">准备中...</div>
            <div class="vdp-results" id="vdp-results"></div>
        </div>
    </div>
    <?php
}
