<?php
/**
 * 用户中心 - 合伙人 Tab
 */
if (!defined('ABSPATH')) exit;

$uid = get_current_user_id();

if (!vdp_opt('partner_enabled', false)) {
    echo '<div class="vdp-alert vdp-alert-warning">合伙人计划暂未启用。</div>';
    return;
}

$nonce = wp_create_nonce('vdp_partner_nonce');

if (!VDP_Partner::is_joined($uid)) {
    echo '<div class="vdp-partner-tab" data-nonce="' . esc_attr($nonce) . '">';
    vdp_partner_render_join_box($uid);
    echo '</div>';
    ?>
    <script>
    jQuery(function($){
        var $tab = $('.vdp-partner-tab');
        var nonce = $tab.data('nonce');
        $tab.on('click', '.vdp-partner-join-btn', function(){
            var btn = $(this).prop('disabled', true).text('处理中...');
            $.post(vdp_ajax.url, { action:'vdp_partner_join', _ajax_nonce:nonce }, function(res){
                if (res.success) location.reload();
                else { alert(res.data || '加入失败'); btn.prop('disabled', false).text('立即加入'); }
            });
        });
        $tab.on('click', '.vdp-partner-apply', function(){
            var btn = $(this).prop('disabled', true);
            $.post(vdp_ajax.url, { action:'vdp_partner_apply', _ajax_nonce:nonce }, function(res){
                if (res.success) location.reload();
                else { alert(res.data || '申请失败'); btn.prop('disabled', false); }
            });
        });
    });
    </script>
    <?php
    return;
}

$balance     = VDP_Partner::get_balance($uid);
$pending     = VDP_Partner::get_pending($uid);
$total       = VDP_Partner::get_total_earned($uid);
$invite_url  = VDP_Partner::get_invite_url($uid);
$invite_code = get_user_meta($uid, 'vdp_invite_code', true);
$downlines   = vdp_partner_count_downlines($uid);
$tab         = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview';

$min_withdraw = (float) vdp_opt('partner_min_withdraw', 50);
$channels     = (array) vdp_opt('partner_withdraw_channels', ['wechat', 'alipay', 'balance']);

$base_url = vdp_get_user_center_url('partner');
?>
<div class="vdp-partner-tab" data-nonce="<?php echo esc_attr($nonce); ?>">
    <ul class="nav nav-tabs vdp-partner-subnav">
        <li class="<?php echo $tab === 'overview'    ? 'active' : ''; ?>"><a href="<?php echo esc_url(add_query_arg('tab', 'overview', $base_url)); ?>">概览</a></li>
        <li class="<?php echo $tab === 'commissions' ? 'active' : ''; ?>"><a href="<?php echo esc_url(add_query_arg('tab', 'commissions', $base_url)); ?>">佣金记录</a></li>
        <li class="<?php echo $tab === 'downlines'   ? 'active' : ''; ?>"><a href="<?php echo esc_url(add_query_arg('tab', 'downlines', $base_url)); ?>">推荐会员</a></li>
        <li class="<?php echo $tab === 'withdraw'    ? 'active' : ''; ?>"><a href="<?php echo esc_url(add_query_arg('tab', 'withdraw', $base_url)); ?>">提现</a></li>
    </ul>

    <?php if ($tab === 'commissions') : ?>
        <?php $list = vdp_partner_get_commissions($uid, ['limit' => 50]); ?>
        <table class="vdp-table">
            <thead><tr><th>时间</th><th>来源订单</th><th>层级</th><th>金额</th><th>状态</th></tr></thead>
            <tbody>
            <?php if (!$list) : ?>
                <tr><td colspan="5" class="text-muted text-center">暂无佣金记录</td></tr>
            <?php else: foreach ($list as $r) : ?>
                <tr>
                    <td><?php echo esc_html(mysql2date('Y-m-d H:i', $r->created_at)); ?></td>
                    <td><?php echo esc_html($r->order_num); ?></td>
                    <td>L<?php echo (int)$r->level; ?></td>
                    <td>¥<?php echo number_format($r->amount, 2); ?></td>
                    <td>
                        <?php
                        $map = ['pending'=>'冷冻中','settled'=>'可提现','paid'=>'已提现','cancelled'=>'已撤销'];
                        echo esc_html($map[$r->status] ?? $r->status);
                        if ($r->refund_flagged) echo ' <span class="vdp-tag vdp-tag-danger">退款标记</span>';
                        if ($r->flagged) echo ' <span class="vdp-tag">同IP</span>';
                        ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>

    <?php elseif ($tab === 'downlines') : ?>
        <?php
        global $wpdb;
        $rel_table = $wpdb->prefix . 'vdp_partner_relations';
        $dl_list = $wpdb->get_results($wpdb->prepare(
            "SELECT r.user_id, r.bound_at, r.bound_source, r.flagged, u.display_name, u.user_email, u.user_registered
             FROM {$rel_table} r
             LEFT JOIN {$wpdb->users} u ON u.ID = r.user_id
             WHERE r.referrer_l1 = %d
             ORDER BY r.bound_at DESC
             LIMIT 100",
            $uid
        ));
        ?>
        <div class="vdp-partner-downlines">
            <p class="text-muted">通过你的邀请链接注册的会员（L1 直推 <?php echo (int)$downlines['l1']; ?> 人）</p>
            <table class="vdp-table">
                <thead><tr><th>用户</th><th>注册时间</th><th>绑定时间</th><th>来源</th><th>备注</th></tr></thead>
                <tbody>
                <?php if (!$dl_list) : ?>
                    <tr><td colspan="5" class="text-muted text-center">暂无推荐会员</td></tr>
                <?php else: foreach ($dl_list as $dl) :
                    $src_map = ['register' => '注册绑定', 'login' => '登录绑定', 'order' => '下单绑定'];
                ?>
                    <tr>
                        <td>
                            <?php echo get_avatar($dl->user_id, 28); ?>
                            <?php echo esc_html($dl->display_name ?: '用户' . $dl->user_id); ?>
                        </td>
                        <td><?php echo esc_html(mysql2date('Y-m-d', $dl->user_registered)); ?></td>
                        <td><?php echo esc_html(mysql2date('Y-m-d', $dl->bound_at)); ?></td>
                        <td><?php echo esc_html($src_map[$dl->bound_source] ?? $dl->bound_source); ?></td>
                        <td><?php if ($dl->flagged) echo '<span class="vdp-tag">同IP</span>'; ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($tab === 'withdraw') : ?>
        <div class="vdp-partner-withdraw">
            <p>可提现余额：<strong>¥<?php echo number_format($balance, 2); ?></strong>
               &nbsp;|&nbsp; 最低提现 ¥<?php echo number_format($min_withdraw, 2); ?>
               &nbsp;|&nbsp; 每 7 天最多 <?php echo (int) vdp_opt('partner_weekly_limit', 1); ?> 次</p>

            <form class="vdp-withdraw-form">
                <p>
                    <label>提现金额</label>
                    <input type="number" name="amount" step="0.01" min="<?php echo esc_attr($min_withdraw); ?>" max="<?php echo esc_attr($balance); ?>" required>
                </p>
                <p>
                    <label>提现渠道</label>
                    <select name="channel" required>
                        <?php
                        $names = ['wechat'=>'微信','alipay'=>'支付宝','balance'=>'抵扣会员'];
                        foreach ($channels as $c) {
                            echo '<option value="' . esc_attr($c) . '">' . esc_html($names[$c] ?? $c) . '</option>';
                        }
                        ?>
                    </select>
                </p>
                <p class="vdp-account-fields">
                    <label>收款人姓名</label>
                    <input type="text" name="account_name" value="<?php echo esc_attr(get_user_meta($uid, 'vdp_partner_realname', true)); ?>">
                </p>
                <p class="vdp-account-fields">
                    <label>收款账号（微信号 / 支付宝账号）</label>
                    <input type="text" name="account_no">
                </p>
                <p>
                    <button type="submit" class="btn btn-primary">提交申请</button>
                </p>
            </form>

            <h4 style="margin-top:30px;">历史申请</h4>
            <?php $wds = vdp_partner_get_withdrawals($uid, 30); ?>
            <table class="vdp-table">
                <thead><tr><th>时间</th><th>金额</th><th>渠道</th><th>账号</th><th>状态</th><th>备注</th></tr></thead>
                <tbody>
                <?php if (!$wds) : ?>
                    <tr><td colspan="6" class="text-muted text-center">暂无提现记录</td></tr>
                <?php else: foreach ($wds as $w) :
                    $cmap = ['wechat'=>'微信','alipay'=>'支付宝','balance'=>'抵扣会员'];
                    $smap = ['pending'=>'待审核','approved'=>'已通过待打款','paid'=>'已打款','rejected'=>'已驳回'];
                ?>
                    <tr>
                        <td><?php echo esc_html(mysql2date('Y-m-d H:i', $w->created_at)); ?></td>
                        <td>¥<?php echo number_format($w->amount, 2); ?></td>
                        <td><?php echo esc_html($cmap[$w->channel] ?? $w->channel); ?></td>
                        <td><?php echo esc_html($w->account_masked); ?></td>
                        <td><?php echo esc_html($smap[$w->status] ?? $w->status); ?></td>
                        <td><?php echo esc_html($w->admin_note); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

    <?php else : // overview ?>
        <div class="vdp-partner-overview">
            <div class="vdp-partner-stats">
                <div class="vdp-stat-card"><div class="num">¥<?php echo number_format($balance, 2); ?></div><div class="label">可提现余额</div></div>
                <div class="vdp-stat-card"><div class="num">¥<?php echo number_format($pending, 2); ?></div><div class="label">冷冻中</div></div>
                <div class="vdp-stat-card"><div class="num">¥<?php echo number_format($total, 2); ?></div><div class="label">累计收益</div></div>
                <div class="vdp-stat-card"><div class="num"><?php echo (int)$downlines['l1']; ?> / <?php echo (int)$downlines['l2']; ?></div><div class="label">L1 / L2 下线</div></div>
            </div>

            <div class="vdp-partner-invite" style="margin-top:20px;">
                <h4>我的邀请链接</h4>
                <p>邀请码：<strong><?php echo esc_html($invite_code); ?></strong></p>
                <div class="input-group">
                    <input type="text" class="form-control" value="<?php echo esc_attr($invite_url); ?>" readonly id="vdp-invite-url">
                    <span class="input-group-btn">
                        <button class="btn btn-primary vdp-share-copy" data-url="<?php echo esc_attr($invite_url); ?>">复制链接</button>
                    </span>
                </div>
                <p class="text-muted" style="margin-top:10px;">
                    比例：L1 <?php echo esc_html(vdp_opt('partner_rate_l1', 20)); ?>%
                    / L2 <?php echo esc_html(vdp_opt('partner_rate_l2', 5)); ?>%
                    <?php if (vdp_opt('partner_rate_vip', 25) > vdp_opt('partner_rate_l1', 20)) : ?>
                        / VIP 升级版 <?php echo esc_html(vdp_opt('partner_rate_vip', 25)); ?>%
                    <?php endif; ?>
                    &nbsp;|&nbsp; 冷冻期 <?php echo esc_html(vdp_opt('partner_freeze_days', 7)); ?> 天
                </p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
jQuery(function($){
    var $tab = $('.vdp-partner-tab');
    var nonce = $tab.data('nonce');

    $tab.on('click', '.vdp-partner-join-btn', function(){
        var btn = $(this).prop('disabled', true).text('处理中...');
        $.post(vdp_ajax.url, { action:'vdp_partner_join', _ajax_nonce:nonce }, function(res){
            if (res.success) location.reload();
            else { alert(res.data || '加入失败'); btn.prop('disabled', false).text('立即加入'); }
        });
    });

    $tab.on('click', '.vdp-partner-apply', function(){
        var btn = $(this).prop('disabled', true);
        $.post(vdp_ajax.url, { action:'vdp_partner_apply', _ajax_nonce:nonce }, function(res){
            if (res.success) location.reload();
            else { alert(res.data || '申请失败'); btn.prop('disabled', false); }
        });
    });

    $tab.on('change', 'select[name=channel]', function(){
        var v = $(this).val();
        $tab.find('.vdp-account-fields').toggle(v === 'wechat' || v === 'alipay');
    }).find('select[name=channel]').trigger('change');

    $tab.on('submit', '.vdp-withdraw-form', function(e){
        e.preventDefault();
        var data = $(this).serializeArray().reduce(function(a,b){a[b.name]=b.value;return a;}, {});
        data.action = 'vdp_partner_withdraw';
        data._ajax_nonce = nonce;
        var $btn = $(this).find('button[type=submit]').prop('disabled', true).text('提交中...');
        $.post(vdp_ajax.url, data, function(res){
            if (res.success) { alert('申请已提交'); location.reload(); }
            else { alert(res.data || '提交失败'); $btn.prop('disabled', false).text('提交申请'); }
        });
    });
});
</script>
