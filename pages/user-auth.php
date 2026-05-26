<?php
/**
 * 登录/注册页面
 * 通过 ?vdp_auth=signin / signup 触发
 */

if (!defined('ABSPATH')) exit;

// 已登录直接跳转
if (is_user_logged_in()) {
    $redirect = !empty($_GET['redirect']) ? esc_url_raw($_GET['redirect']) : vdp_get_user_center_url();
    wp_safe_redirect($redirect);
    exit;
}

$mode = !empty($_GET['vdp_auth']) ? sanitize_key($_GET['vdp_auth']) : 'signin';
if (!in_array($mode, ['signin', 'signup'])) $mode = 'signin';

$redirect = !empty($_GET['redirect']) ? esc_url_raw($_GET['redirect']) : '';

get_header(); ?>

<?php if (is_active_sidebar('auth_top_fluid')) : ?>
    <div class="container fluid-widget vdp-fluid-top">
        <?php dynamic_sidebar('auth_top_fluid'); ?>
    </div>
<?php endif; ?>

<div class="vdp-auth-page container" style="max-width:420px;margin:60px auto;">
    <div class="vdp-auth-box">
        <div class="vdp-auth-tabs">
            <a href="<?php echo esc_url(add_query_arg(['vdp_auth' => 'signin', 'redirect' => $redirect], home_url('/'))); ?>"
               class="<?php echo $mode === 'signin' ? 'active' : ''; ?>">登录</a>
            <?php if (!vdp_is_close_signup()) : ?>
                <a href="<?php echo esc_url(add_query_arg(['vdp_auth' => 'signup', 'redirect' => $redirect], home_url('/'))); ?>"
                   class="<?php echo $mode === 'signup' ? 'active' : ''; ?>">注册</a>
            <?php endif; ?>
        </div>

        <?php
        // 微信登录区域
        $weixin_enabled = function_exists('vdp_oauth_is_enabled') && vdp_oauth_is_enabled('weixin');
        $weixin_mode    = vdp_opt('oauth_weixin_mode', 'mp');
        $current_url    = is_ssl() ? 'https://' : 'http://';
        $current_url   .= ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
        $in_wechat      = strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'MicroMessenger') !== false;
        ?>

        <?php if ($weixin_enabled && $weixin_mode === 'mp') : ?>
            <?php if ($in_wechat) : ?>
                <div class="vdp-auth-quick">
                    <a href="<?php echo esc_url(vdp_oauth_login_url('weixin', $redirect ?: $current_url)); ?>"
                       class="vdp-auth-quick-btn vdp-auth-quick-weixin">
                        <i class="fa fa-weixin"></i>
                        <span>微信一键登录</span>
                    </a>
                </div>
            <?php else : ?>
                <div class="vdp-auth-quick">
                    <button type="button" id="vdp-wx-qr-btn" class="vdp-auth-quick-btn vdp-auth-quick-weixin">
                        <i class="fa fa-weixin"></i>
                        <span>微信扫码登录</span>
                    </button>
                </div>
                <div id="vdp-wx-qr-box" class="vdp-wx-qr-box" style="display:none;">
                    <div class="vdp-wx-qr-inner">
                        <div class="vdp-wx-qr-loading"><i class="fa fa-spinner fa-spin"></i> 加载中...</div>
                        <img id="vdp-wx-qr-img" class="vdp-wx-qr-img" src="" alt="微信扫码登录" style="display:none;">
                        <div id="vdp-wx-qr-status" class="vdp-wx-qr-status"></div>
                        <p class="vdp-wx-qr-tip">请使用微信扫描二维码登录</p>
                        <button type="button" id="vdp-wx-qr-refresh" class="vdp-wx-qr-refresh" style="display:none;">
                            <i class="fa fa-refresh"></i> 刷新二维码
                        </button>
                    </div>
                </div>
                <script>
                (function(){
                    var btn = document.getElementById('vdp-wx-qr-btn');
                    var box = document.getElementById('vdp-wx-qr-box');
                    var img = document.getElementById('vdp-wx-qr-img');
                    var status = document.getElementById('vdp-wx-qr-status');
                    var refresh = document.getElementById('vdp-wx-qr-refresh');
                    var loading = box.querySelector('.vdp-wx-qr-loading');
                    var timer = null, sceneId = 0;

                    function loadQR() {
                        img.style.display = 'none';
                        loading.style.display = '';
                        status.textContent = '';
                        status.className = 'vdp-wx-qr-status';
                        refresh.style.display = 'none';
                        fetch('<?php echo esc_url(home_url('/?vdp_wx_qrcode=1')); ?>')
                            .then(function(r){ return r.json(); })
                            .then(function(data){
                                loading.style.display = 'none';
                                if (data.error) { status.textContent = data.error; refresh.style.display = ''; return; }
                                sceneId = data.scene_id;
                                img.src = data.qrcode_url;
                                img.style.display = '';
                                startPoll(data.expire || 300);
                            })
                            .catch(function(){ loading.style.display = 'none'; status.textContent = '网络错误'; refresh.style.display = ''; });
                    }

                    function startPoll(expire) {
                        var elapsed = 0;
                        timer = setInterval(function(){
                            elapsed += 2;
                            if (elapsed > expire) { clearInterval(timer); status.textContent = '二维码已过期'; refresh.style.display = ''; img.style.opacity = '0.3'; return; }
                            fetch('<?php echo esc_url(home_url('/?vdp_wx_poll=1')); ?>&scene_id=' + sceneId)
                                .then(function(r){ return r.json(); })
                                .then(function(data){
                                    if (data.status === 'ok') {
                                        clearInterval(timer);
                                        status.innerHTML = '<i class="fa fa-check-circle"></i> 登录成功';
                                        status.className = 'vdp-wx-qr-status vdp-wx-qr-success';
                                        setTimeout(function(){ location.href = data.redirect || '<?php echo esc_js($redirect ?: vdp_get_user_center_url()); ?>'; }, 600);
                                    } else if (data.status === 'expired') {
                                        clearInterval(timer); status.textContent = '二维码已过期'; refresh.style.display = ''; img.style.opacity = '0.3';
                                    }
                                });
                        }, 2000);
                    }

                    btn.addEventListener('click', function(){ box.style.display = ''; btn.style.display = 'none'; loadQR(); });
                    refresh.addEventListener('click', function(){ if(timer) clearInterval(timer); img.style.opacity = '1'; loadQR(); });
                })();
                </script>
            <?php endif; ?>
            <div class="vdp-auth-divider"><span>或使用账号</span></div>

        <?php elseif ($weixin_enabled && $weixin_mode === 'open') : ?>
            <div class="vdp-auth-quick">
                <a href="<?php echo esc_url(vdp_oauth_login_url('weixin', $redirect ?: $current_url)); ?>"
                   class="vdp-auth-quick-btn vdp-auth-quick-weixin">
                    <i class="fa fa-weixin"></i>
                    <span>微信一键登录</span>
                </a>
            </div>
            <div class="vdp-auth-divider"><span>或使用账号</span></div>

        <?php else : ?>
            <div class="vdp-auth-quick">
                <a href="javascript:;"
                   class="vdp-auth-quick-btn vdp-auth-quick-weixin is-disabled"
                   onclick="alert('微信登录尚未配置\n请到后台-社交登录中开启');return false;">
                    <i class="fa fa-weixin"></i>
                    <span>微信一键登录</span>
                </a>
            </div>
            <div class="vdp-auth-divider"><span>或使用账号</span></div>
        <?php endif; ?>

        <?php if ($mode === 'signin') : ?>
            <form id="vdp-signin-form" class="vdp-form">
                <?php wp_nonce_field('vdp_user_nonce', '_ajax_nonce'); ?>
                <input type="hidden" name="action" value="vdp_signin">
                <input type="hidden" name="redirect" value="<?php echo esc_attr($redirect); ?>">

                <div class="vdp-form-row">
                    <label>账号</label>
                    <input type="text" name="login" placeholder="用户名/邮箱" required>
                </div>
                <div class="vdp-form-row">
                    <label>密码</label>
                    <input type="password" name="password" required>
                </div>
                <div class="vdp-form-row">
                    <label class="vdp-checkbox">
                        <input type="checkbox" name="remember" value="1" checked> 记住我
                    </label>
                </div>
                <div class="vdp-form-row">
                    <button type="submit" class="vdp-btn-primary vdp-btn-block">登录</button>
                </div>
            </form>
        <?php else : ?>
            <form id="vdp-signup-form" class="vdp-form">
                <?php wp_nonce_field('vdp_user_nonce', '_ajax_nonce'); ?>
                <input type="hidden" name="action" value="vdp_signup">

                <div class="vdp-form-row">
                    <label>用户名</label>
                    <input type="text" name="username" minlength="3" required>
                </div>
                <div class="vdp-form-row">
                    <label>邮箱</label>
                    <input type="email" name="email" required>
                </div>
                <div class="vdp-form-row">
                    <label>密码</label>
                    <input type="password" name="password" minlength="6" required>
                </div>
                <div class="vdp-form-row">
                    <label>确认密码</label>
                    <input type="password" name="confirm_password" minlength="6" required>
                </div>
                <div class="vdp-form-row">
                    <button type="submit" class="vdp-btn-primary vdp-btn-block">立即注册</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if (is_active_sidebar('auth_bottom_fluid')) : ?>
    <div class="container fluid-widget vdp-fluid-bottom">
        <?php dynamic_sidebar('auth_bottom_fluid'); ?>
    </div>
<?php endif; ?>

<?php get_footer(); ?>
