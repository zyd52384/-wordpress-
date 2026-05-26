/**
 * 后台上传页面 JS
 */
(function($) {
    'use strict';

    var files = [];
    var uploading = false;

    $(document).ready(function() {
        initUploadPage();
        initPostsPage();
    });

    /* =========================================================
       已发布管理：重生预览图 / 重生 AI 摘要
       ========================================================= */
    function initPostsPage() {
        var $table = $('.vdp-posts-table');
        if (!$table.length) return;

        $table.on('click', '.vdp-regen-preview', function() {
            var $btn = $(this);
            var pid  = $btn.data('id');
            if ($btn.prop('disabled')) return;
            $btn.prop('disabled', true).text('生成中…');

            $.post(vdp_admin.ajax_url, {
                action: 'vdp_regen_preview',
                _ajax_nonce: vdp_admin.nonce,
                post_id: pid
            }).done(function(res) {
                if (res.success) {
                    setBadge($btn.closest('tr'), 'preview', 'done', '完成');
                    notify($btn, '✓ 已重生', 'ok');
                } else {
                    setBadge($btn.closest('tr'), 'preview', 'failed', '失败');
                    notify($btn, '✗ ' + (res.data || '失败'), 'err');
                }
            }).fail(function() {
                notify($btn, '✗ 网络错误', 'err');
            }).always(function() {
                $btn.prop('disabled', false).text('重生预览');
            });
        });

        $table.on('click', '.vdp-regen-summary', function() {
            var $btn = $(this);
            var pid  = $btn.data('id');
            if ($btn.prop('disabled')) return;
            $btn.prop('disabled', true).text('生成中…');

            $.post(vdp_admin.ajax_url, {
                action: 'vdp_regen_summary',
                _ajax_nonce: vdp_admin.nonce,
                post_id: pid
            }).done(function(res) {
                if (res.success) {
                    setBadge($btn.closest('tr'), 'summary', 'done', '完成');
                    notify($btn, '✓ 已重生', 'ok');
                } else {
                    setBadge($btn.closest('tr'), 'summary', 'failed', '失败');
                    notify($btn, '✗ ' + (res.data || '失败'), 'err');
                }
            }).fail(function() {
                notify($btn, '✗ 网络错误', 'err');
            }).always(function() {
                $btn.prop('disabled', false).text('重生摘要');
            });
        });
    }

    function setBadge($row, type, status, label) {
        var colorMap = {
            'done': 'green', 'failed': 'red', 'pending': 'orange', 'unsupported': 'gray', 'skipped': 'gray'
        };
        var color = colorMap[status] || 'gray';
        var $cell = $row.find(type === 'preview' ? '.col-preview' : '.col-summary');
        $cell.html('<span class="vdp-badge vdp-badge-' + color + '">' + label + '</span>');
    }

    function notify($btn, msg, kind) {
        var $tip = $('<span class="vdp-flash vdp-flash-' + (kind === 'err' ? 'err' : 'ok') + '">' + msg + '</span>');
        $btn.after($tip);
        setTimeout(function() { $tip.fadeOut(400, function() { $tip.remove(); }); }, 2200);
    }

    function initUploadPage() {
        var $dropzone = $('#vdp-dropzone');
        var $fileInput = $('#vdp-file-input');
        var $fileList = $('#vdp-file-list');
        var $actions = $('#vdp-actions');
        var $payType = $('#vdp-pay-type');
        var $payPrice = $('#vdp-pay-price');

        // 付费类型切换
        $payType.on('change', function() {
            if ($(this).val() === '1') {
                $payPrice.show();
            } else {
                $payPrice.hide();
            }
        });

        // 点击上传区
        $dropzone.on('click', function(e) {
            if (e.target === $fileInput[0]) return;
            $fileInput.click();
        });

        // 文件选择
        $fileInput.on('change', function() {
            handleFiles(this.files);
        });

        // 拖拽事件
        $dropzone.on('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('drag-over');
        });

        $dropzone.on('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drag-over');
        });

        $dropzone.on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drag-over');
            handleFiles(e.originalEvent.dataTransfer.files);
        });

        // 开始上传
        $('#vdp-start-upload').on('click', function() {
            if (uploading) return;
            startUpload();
        });

        // 移除文件
        $fileList.on('click', '.vdp-file-remove', function() {
            var index = $(this).data('index');
            files.splice(index, 1);
            renderFileList();
        });
    }

    function handleFiles(fileList) {
        for (var i = 0; i < fileList.length; i++) {
            files.push(fileList[i]);
        }
        renderFileList();
    }

    function renderFileList() {
        var $fileList = $('#vdp-file-list');
        var $actions = $('#vdp-actions');

        if (files.length === 0) {
            $fileList.empty();
            $actions.hide();
            return;
        }

        var html = '';
        for (var i = 0; i < files.length; i++) {
            var file = files[i];
            var size = formatFileSize(file.size);
            html += '<div class="vdp-file-item">';
            html += '<div><span class="vdp-file-name">' + escapeHtml(file.name) + '</span>';
            html += '<span class="vdp-file-size">' + size + '</span></div>';
            html += '<span class="vdp-file-remove" data-index="' + i + '">×</span>';
            html += '</div>';
        }

        $fileList.html(html);
        $actions.show();
    }

    function startUpload() {
        if (files.length === 0) {
            alert('请先选择文件');
            return;
        }

        var categoryId = $('#vdp-category-select').val();
        if (!categoryId) {
            alert('请选择分类');
            return;
        }

        var payType = $('#vdp-pay-type').val();
        var payPrice = $('#vdp-pay-price').val();
        var vipLimit = $('#vdp-vip-limit').val();
        var previewPages = $('#vdp-preview-pages').val();

        if (payType === '1' && (!payPrice || payPrice <= 0)) {
            alert('请输入价格');
            return;
        }

        uploading = true;
        $('#vdp-start-upload').prop('disabled', true).text('上传中...');
        $('#vdp-progress').show();
        $('#vdp-results').empty();

        uploadNext(0, categoryId, payType, payPrice, vipLimit, previewPages);
    }

    function uploadNext(index, categoryId, payType, payPrice, vipLimit, previewPages) {
        if (index >= files.length) {
            uploading = false;
            $('#vdp-start-upload').prop('disabled', false).text('开始上传并发布');
            $('#vdp-progress-text').text('全部完成！');
            $('#vdp-progress-fill').css('width', '100%');
            return;
        }

        var file = files[index];
        var progress = Math.round((index / files.length) * 100);
        $('#vdp-progress-fill').css('width', progress + '%');
        $('#vdp-progress-text').text('正在上传: ' + file.name + ' (' + (index + 1) + '/' + files.length + ')');

        var formData = new FormData();
        formData.append('action', 'vdp_upload_file');
        formData.append('_ajax_nonce', vdp_admin.nonce);
        formData.append('file', file);
        formData.append('category_id', categoryId);
        formData.append('pay_type', payType);
        formData.append('pay_price', payPrice);
        formData.append('vip_limit', vipLimit);
        formData.append('preview_pages', previewPages);

        $.ajax({
            url: vdp_admin.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                if (res.success) {
                    addResult('success', '✅ ' + file.name + ' - 上传成功');
                } else {
                    addResult('error', '❌ ' + file.name + ' - ' + (res.data || '上传失败'));
                }
            },
            error: function() {
                addResult('error', '❌ ' + file.name + ' - 网络错误');
            },
            complete: function() {
                uploadNext(index + 1, categoryId, payType, payPrice, vipLimit, previewPages);
            }
        });
    }

    function addResult(type, message) {
        var html = '<div class="vdp-result-item ' + type + '">' + escapeHtml(message) + '</div>';
        $('#vdp-results').append(html);
    }

    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB';
        return (bytes / 1024 / 1024).toFixed(2) + ' MB';
    }

    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

})(jQuery);
