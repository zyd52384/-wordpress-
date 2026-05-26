/**
 * 用户系统前端交互
 * 登录、注册、资料、修改密码、会员购买
 */
(function ($) {
    'use strict';

    var ajaxUrl = (window.vdp_ajax && vdp_ajax.url) || '/wp-admin/admin-ajax.php';

    // 简易消息提示
    function showMessage(msg, type) {
        type = type || 'info';
        var $box = $('<div class="vdp-flash vdp-flash-' + type + '">' + msg + '</div>');
        $('body').append($box);
        setTimeout(function () { $box.addClass('vdp-flash-show'); }, 10);
        setTimeout(function () {
            $box.removeClass('vdp-flash-show');
            setTimeout(function () { $box.remove(); }, 300);
        }, 2500);
    }

    function ajaxForm($form, onSuccess) {
        $form.on('submit', function (e) {
            e.preventDefault();
            var $btn = $form.find('button[type=submit]');
            var btnText = $btn.text();
            $btn.prop('disabled', true).text('处理中...');

            $.post(ajaxUrl, $form.serialize())
                .done(function (res) {
                    if (res && res.success) {
                        showMessage(res.data.message || res.data || '操作成功', 'success');
                        if (typeof onSuccess === 'function') onSuccess(res.data);
                        else if (res.data && res.data.redirect) {
                            setTimeout(function () { location.href = res.data.redirect; }, 600);
                        }
                    } else {
                        showMessage((res && res.data) || '操作失败', 'error');
                    }
                })
                .fail(function () { showMessage('网络错误', 'error'); })
                .always(function () { $btn.prop('disabled', false).text(btnText); });
        });
    }

    $(function () {
        // 登录注册
        ajaxForm($('#vdp-signin-form'));
        ajaxForm($('#vdp-signup-form'));

        // 用户资料
        ajaxForm($('#vdp-profile-form'), function () { /* keep on page */ });

        // 修改密码
        ajaxForm($('#vdp-password-form'), function () {
            setTimeout(function () { location.href = '/?vdp_auth=signin'; }, 800);
        });

        // 退出登录
        $(document).on('click', '.vdp-signout-btn', function (e) {
            e.preventDefault();
            $.post(ajaxUrl, { action: 'vdp_signout', _ajax_nonce: vdp_ajax.nonce }, function (res) {
                if (res && res.success) location.href = res.data.redirect || '/';
            });
        });

        // 触发登录页跳转
        $(document).on('click', '.vdp-signin-loader', function (e) {
            e.preventDefault();
            location.href = '/?vdp_auth=signin&redirect=' + encodeURIComponent(location.href);
        });
        $(document).on('click', '.vdp-signup-loader', function (e) {
            e.preventDefault();
            location.href = '/?vdp_auth=signup&redirect=' + encodeURIComponent(location.href);
        });

        // 购买 VIP
        $(document).on('click', '.vdp-btn-buy-vip', function () {
            var level = $(this).data('level');
            var $card = $(this).closest('.vdp-vip-card, .vdp-membership-card');
            var price = $card.data('price');
            var $btn  = $(this);
            var btnText = $btn.text();
            $btn.prop('disabled', true).text('请求中...');

            $.post(ajaxUrl, {
                action: 'vdp_buy_membership',
                level: level,
                pay_type: 'wechat',
                _ajax_nonce: vdp_ajax.nonce
            }).done(function (res) {
                if (res && res.success) {
                    $('#vdp-pay-amount-text').text(parseFloat(price).toFixed(2));
                    if (res.data.url_qrcode) {
                        $('#vdp-pay-qr-img').attr('src', res.data.url_qrcode);
                    } else if (res.data.url) {
                        location.href = res.data.url;
                        return;
                    }
                    $('#vdp-pay-qr-modal').show();
                    pollPayStatus(res.data.order_num);
                } else {
                    showMessage((res && res.data) || '下单失败', 'error');
                }
            }).fail(function () {
                showMessage('网络错误', 'error');
            }).always(function () {
                $btn.prop('disabled', false).text(btnText);
            });
        });

        // 关闭支付弹窗
        $(document).on('click', '#vdp-pay-qr-modal .vdp-modal-close', function () {
            $('#vdp-pay-qr-modal').hide();
        });
    });

    var payTimer = null;
    function pollPayStatus(orderNum) {
        if (!orderNum) return;
        clearInterval(payTimer);
        payTimer = setInterval(function () {
            $.post(ajaxUrl, {
                action: 'vdp_check_order',
                order_num: orderNum,
                _ajax_nonce: vdp_ajax.nonce
            }, function (res) {
                if (res && res.success && res.data && res.data.paid) {
                    clearInterval(payTimer);
                    $('#vdp-pay-status').text('支付成功！正在跳转...');
                    setTimeout(function () { location.reload(); }, 1200);
                }
            });
        }, 3000);
    }

})(jQuery);
