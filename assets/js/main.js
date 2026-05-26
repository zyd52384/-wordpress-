/**
 * 虚拟资料自动赚钱机 - 前端主脚本
 */
(function($) {
    'use strict';

    // 图片懒加载
    function initLazyLoad() {
        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    var img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy-load');
                    img.classList.add('loaded');
                    observer.unobserve(img);
                }
            });
        }, { rootMargin: '200px' });

        document.querySelectorAll('.lazy-load').forEach(function(img) {
            observer.observe(img);
        });
    }

    // 支付功能
    function initPayment() {
        var pollTimer = null;

        $(document).on('click', '.btn-pay', function() {
            var btn = $(this);
            var postId = btn.data('post-id');
            var payType = btn.data('pay-type');

            if (!postId) return;

            btn.prop('disabled', true).text('请求中...');

            $.ajax({
                url: vdp_ajax.url,
                type: 'POST',
                data: {
                    action: 'vdp_buy_doc',
                    post_id: postId,
                    pay_type: payType,
                    _ajax_nonce: vdp_ajax.nonce
                },
                success: function(res) {
                    btn.prop('disabled', false).html(
                        payType === 'wechat'
                            ? '<i class="fa fa-wechat"></i> 微信支付'
                            : '<i class="fa fa-credit-card"></i> 支付宝'
                    );

                    if (res.success) {
                        if (res.data.paid) {
                            location.reload();
                            return;
                        }
                        if (res.data.url_qrcode) {
                            showPayModal(res.data.url_qrcode, res.data.order_num, payType);
                        } else if (res.data.url) {
                            location.href = res.data.url;
                        }
                    } else {
                        alert(res.data || '支付请求失败');
                    }
                },
                error: function() {
                    btn.prop('disabled', false);
                    alert('网络错误，请重试');
                }
            });
        });

        // 记录下载次数
        $(document).on('click', '.btn-download', function() {
            var postId = $(this).data('post-id');
            if (!postId) return;
            $.post(vdp_ajax.url, {
                action: 'vdp_record_download',
                post_id: postId,
                _ajax_nonce: vdp_ajax.nonce
            });
        });

        function showPayModal(qrcodeUrl, orderNum, payType) {
            var title = payType === 'wechat' ? '微信扫码支付' : '支付宝扫码支付';
            var modal = $(
                '<div class="modal fade" id="payModal" tabindex="-1">' +
                '  <div class="modal-dialog modal-sm">' +
                '    <div class="modal-content">' +
                '      <div class="modal-header">' +
                '        <button type="button" class="close" data-dismiss="modal">&times;</button>' +
                '        <h4 class="modal-title">' + title + '</h4>' +
                '      </div>' +
                '      <div class="modal-body text-center">' +
                '        <img src="' + qrcodeUrl + '" class="qrcode-img" style="max-width:200px">' +
                '        <p class="text-muted" style="margin-top:10px">请使用' + (payType === 'wechat' ? '微信' : '支付宝') + '扫码支付</p>' +
                '        <p class="pay-status">等待支付...</p>' +
                '      </div>' +
                '    </div>' +
                '  </div>' +
                '</div>'
            );

            $('body').append(modal);
            modal.modal('show');

            // 轮询订单状态
            pollTimer = setInterval(function() {
                $.post(vdp_ajax.url, {
                    action: 'vdp_check_order',
                    order_num: orderNum,
                    _ajax_nonce: vdp_ajax.nonce
                }, function(res) {
                    if (res.success && res.data.paid) {
                        clearInterval(pollTimer);
                        modal.find('.pay-status').html('<span style="color:green">支付成功！正在刷新...</span>');
                        setTimeout(function() { location.reload(); }, 1500);
                    }
                });
            }, 3000);

            modal.on('hidden.bs.modal', function() {
                clearInterval(pollTimer);
                modal.remove();
            });
        }
    }

    // 可关闭小工具：localStorage 持久化（key = vdp_dismissed_widgets）
    var DISMISS_KEY = 'vdp_dismissed_widgets';

    function getDismissed() {
        try {
            var raw = localStorage.getItem(DISMISS_KEY);
            return raw ? JSON.parse(raw) : [];
        } catch (e) { return []; }
    }
    function saveDismissed(list) {
        try { localStorage.setItem(DISMISS_KEY, JSON.stringify(list)); } catch (e) {}
    }

    function applyDismissed() {
        var ids = getDismissed();
        if (!ids.length) return;
        ids.forEach(function(id) {
            var el = document.getElementById(id);
            if (el && el.getAttribute('data-closable') === 'true') {
                el.style.display = 'none';
            }
        });
    }

    function initClosableWidgets() {
        applyDismissed();

        $(document).on('click', '.vdp-widget--closable .vdp-widget-close', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $w = $(this).closest('[data-closable="true"]');
            var id = $w.attr('id');
            if (!id) { $w.slideUp(200); return; }

            var ids = getDismissed();
            if (ids.indexOf(id) === -1) {
                ids.push(id);
                saveDismissed(ids);
            }
            $w.slideUp(200, function() { $(this).css('display', 'none'); });
        });
    }

    // 排行榜 TAB 切换
    function initRankTabs() {
        $(document).on('click', '.vdp-rank-tab-nav > li', function() {
            var $li = $(this);
            var $wrap = $li.closest('.vdp-rank-tabs');
            var target = $li.data('target');
            $wrap.find('.vdp-rank-tab-nav > li').removeClass('active');
            $li.addClass('active');
            $wrap.find('.vdp-rank-list').removeClass('active');
            $wrap.find('#' + target).addClass('active');
        });
    }

    // 滚动公告（垂直循环）
    function initNoticeBars() {
        document.querySelectorAll('.vdp-notice-bar').forEach(function(bar) {
            var items = bar.querySelectorAll('.vdp-notice-item');
            if (items.length === 0) return;
            items[0].classList.add('is-active');
            if (items.length < 2) return;

            var interval = parseInt(bar.dataset.interval, 10) || 4000;
            var idx = 0;

            setInterval(function() {
                var prev = items[idx];
                idx = (idx + 1) % items.length;
                var next = items[idx];

                prev.classList.remove('is-active');
                prev.classList.add('is-leaving');
                next.classList.add('is-active');

                setTimeout(function() {
                    prev.classList.remove('is-leaving');
                }, 500);
            }, interval);
        });
    }

    // 一言切换
    function initYiyan() {
        var hitokotoUrl = 'https://v1.hitokoto.cn/?encode=json';

        function pickLocalRandom(items, exclude) {
            if (items.length === 0) return null;
            if (items.length === 1) return items[0];
            var picked;
            var attempts = 0;
            do {
                picked = items[Math.floor(Math.random() * items.length)];
                attempts++;
            } while (picked.text === exclude && attempts < 5);
            return picked;
        }

        function applyText($wrap, text, from) {
            $wrap.addClass('is-fading');
            setTimeout(function() {
                $wrap.find('.vdp-yiyan-text').text(text || '');
                $wrap.find('.vdp-yiyan-from').text(from ? '— ' + from : '');
                $wrap.removeClass('is-fading');
            }, 300);
        }

        function refreshOne($wrap) {
            var source = $wrap.data('source');
            var current = $wrap.find('.vdp-yiyan-text').text();

            if (source === 'hitokoto') {
                $.getJSON(hitokotoUrl).done(function(res) {
                    if (res && res.hitokoto) {
                        applyText($wrap, res.hitokoto, res.from || '');
                    }
                });
            } else {
                var raw = $wrap.attr('data-items') || '[]';
                var items;
                try { items = JSON.parse(raw); } catch (e) { items = []; }
                var picked = pickLocalRandom(items, current);
                if (picked) applyText($wrap, picked.text, picked.from);
            }
        }

        $(document).on('click', '.vdp-yiyan-refresh', function() {
            refreshOne($(this).closest('.vdp-yiyan'));
        });

        $('.vdp-yiyan').each(function() {
            var $w = $(this);
            // 远程模式：首次自动加载
            if ($w.data('source') === 'hitokoto') refreshOne($w);
            // 自动刷新
            var sec = parseInt($w.attr('data-refresh'), 10);
            if (sec && sec > 0) {
                setInterval(function() { refreshOne($w); }, sec * 1000);
            }
        });
    }

    // Swiper 轮播初始化（异步等待 Swiper 加载完成）
    function initSwiperSliders() {
        var sliders = document.querySelectorAll('.vdp-slider');
        if (!sliders.length) return;

        function doInit() {
            sliders.forEach(function(el) {
                if (el.dataset.swiperInit === '1') return;
                el.dataset.swiperInit = '1';

                var cfg = {};
                try { cfg = JSON.parse(el.dataset.config || '{}'); } catch (e) { cfg = {}; }

                var opts = {
                    loop: true,
                    effect: cfg.effect === 'fade' ? 'fade' : 'slide',
                    fadeEffect: { crossFade: true },
                    speed: 600,
                };
                if (cfg.autoplay && cfg.autoplay > 0) {
                    opts.autoplay = {
                        delay: cfg.autoplay,
                        disableOnInteraction: false,
                        pauseOnMouseEnter: !!cfg.pauseOnHover,
                    };
                }
                if (cfg.showPagination) {
                    opts.pagination = {
                        el: el.querySelector('.swiper-pagination'),
                        clickable: true,
                    };
                }
                if (cfg.showArrows) {
                    opts.navigation = {
                        nextEl: el.querySelector('.swiper-button-next'),
                        prevEl: el.querySelector('.swiper-button-prev'),
                    };
                }

                new Swiper(el, opts);
            });
        }

        var tries = 0;
        (function waitForSwiper() {
            if (typeof Swiper !== 'undefined') {
                doInit();
            } else if (tries++ < 50) {
                setTimeout(waitForSwiper, 100);
            }
        })();
    }

    // 暗色模式切换
    function initThemeToggle() {
        function isDark() {
            return document.documentElement.classList.contains('dark-theme');
        }
        function applyMode(dark) {
            var html = document.documentElement;
            if (dark) html.classList.add('dark-theme');
            else html.classList.remove('dark-theme');
            try { localStorage.setItem('vdp_theme_mode', dark ? 'dark' : 'light'); } catch (e) {}
        }
        $(document).on('click', '.vdp-theme-toggle', function(e) {
            e.preventDefault();
            applyMode(!isDark());
        });
    }

    // 吸顶大搜索 / 移动端抽屉
    function initHeaderUI() {
        var $overlay = $('#vdp-search-overlay');
        var $drawer  = $('#vdp-mobile-drawer');

        function openOverlay() {
            $overlay.addClass('is-open').attr('aria-hidden', 'false');
            $('body').addClass('vdp-no-scroll');
            setTimeout(function(){ $overlay.find('.vdp-search-input').trigger('focus'); }, 200);
        }
        function closeOverlay() {
            $overlay.removeClass('is-open').attr('aria-hidden', 'true');
            $('body').removeClass('vdp-no-scroll');
        }
        function openDrawer() {
            $drawer.addClass('is-open').attr('aria-hidden', 'false');
            $('body').addClass('vdp-no-scroll');
        }
        function closeDrawer() {
            $drawer.removeClass('is-open').attr('aria-hidden', 'true');
            $('body').removeClass('vdp-no-scroll');
        }

        $(document).on('click', '.vdp-search-trigger, .vdp-tabbar-search', function(e){
            e.preventDefault(); openOverlay();
        });
        $(document).on('click', '.vdp-search-close', function(e){
            e.preventDefault(); closeOverlay();
        });
        $(document).on('click', '.vdp-search-overlay', function(e){
            if (e.target === this) closeOverlay();
        });

        $(document).on('click', '.vdp-mobile-drawer-toggle, .vdp-tabbar-cats', function(e){
            e.preventDefault(); openDrawer();
        });
        $(document).on('click', '.vdp-drawer-mask', function(e){
            e.preventDefault(); closeDrawer();
        });

        $(document).on('keydown', function(e){
            if (e.key === 'Escape' || e.keyCode === 27) {
                if ($overlay.hasClass('is-open')) closeOverlay();
                if ($drawer.hasClass('is-open')) closeDrawer();
            }
        });
    }

    // 分享下拉
    function initShareDropdown() {
        $(document).on('click', '.vdp-share-btn', function(e){
            e.preventDefault();
            e.stopPropagation();
            var $dd = $(this).closest('.vdp-share-dropdown');
            $('.vdp-share-dropdown').not($dd).removeClass('is-open').find('.vdp-share-wechat-qr').remove();
            $dd.toggleClass('is-open');
            $dd.find('.vdp-share-wechat-qr').remove();
        });
        $(document).on('click', function(e){
            if (!$(e.target).closest('.vdp-share-dropdown').length) {
                $('.vdp-share-dropdown').removeClass('is-open').find('.vdp-share-wechat-qr').remove();
            }
        });

        $(document).on('click', '.vdp-share-copy', function(e){
            e.preventDefault();
            var url = $(this).data('url') || window.location.href;
            var copy = function(text) {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    return navigator.clipboard.writeText(text);
                }
                var $tmp = $('<textarea></textarea>').val(text).appendTo('body').select();
                try { document.execCommand('copy'); } catch (e) {}
                $tmp.remove();
                return Promise.resolve();
            };
            copy(url).then(function(){
                var $tip = $('<div class="vdp-toast">链接已复制</div>').appendTo('body');
                setTimeout(function(){ $tip.addClass('is-show'); }, 10);
                setTimeout(function(){ $tip.removeClass('is-show'); setTimeout(function(){ $tip.remove(); }, 300); }, 1800);
            });
        });

        $(document).on('click', '.vdp-share-wechat', function(e){
            e.preventDefault();
            e.stopPropagation();
            var $dd = $(this).closest('.vdp-share-dropdown');
            var url = $(this).data('url') || window.location.href;
            $dd.find('.vdp-share-wechat-qr').remove();
            var qrSrc = 'https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=' + encodeURIComponent(url);
            var $qr = $(
                '<div class="vdp-share-wechat-qr">' +
                    '<img src="' + qrSrc + '" alt="微信扫码">' +
                    '<div class="vdp-share-wechat-qr-tip">微信扫一扫分享</div>' +
                '</div>'
            );
            $dd.append($qr);
        });
    }

    // 初始化
    $(document).ready(function() {
        initLazyLoad();
        initPayment();
        initClosableWidgets();
        initRankTabs();
        initNoticeBars();
        initYiyan();
        initSwiperSliders();
        initThemeToggle();
        initHeaderUI();
        initShareDropdown();
    });

})(jQuery);
