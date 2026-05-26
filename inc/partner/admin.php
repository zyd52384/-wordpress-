<?php
/**
 * 合伙人 - 后台管理
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function() {
    add_submenu_page(
        'vdp-dashboard',
        '分销管理',
        '分销管理',
        'manage_options',
        'vdp-partner',
        'vdp_render_partner_admin_page'
    );
}, 20);

function vdp_render_partner_admin_page() {
    if (!current_user_can('manage_options')) return;

    // 处理审批操作
    if (!empty($_POST['vdp_partner_action']) && check_admin_referer('vdp_partner_admin')) {
        $act = sanitize_key($_POST['vdp_partner_action']);

        if ($act === 'approve_partner') {
            $uid = (int) $_POST['user_id'];
            update_user_meta($uid, 'vdp_partner_approved', current_time('mysql'));
            VDP_Partner::generate_invite_code($uid);
            echo '<div class="notice notice-success"><p>已审核通过</p></div>';
        }
        if ($act === 'reject_partner') {
            $uid = (int) $_POST['user_id'];
            delete_user_meta($uid, 'vdp_partner_applied');
            echo '<div class="notice notice-success"><p>已驳回</p></div>';
        }
        if (in_array($act, ['approve','paid','reject'], true)) {
            $id = (int) $_POST['wd_id'];
            $note = sanitize_textarea_field($_POST['note'] ?? '');
            $voucher = esc_url_raw($_POST['voucher'] ?? '');
            vdp_partner_approve_withdrawal($id, $act, $note, $voucher);
            echo '<div class="notice notice-success"><p>操作成功</p></div>';
        }
    }

    $tab = isset($_GET['vt']) ? sanitize_key($_GET['vt']) : 'overview';
    $base = admin_url('admin.php?page=vdp-partner');
    ?>
    <div class="wrap">
        <h1>分销管理</h1>
        <h2 class="nav-tab-wrapper">
            <a href="<?php echo esc_url($base); ?>" class="nav-tab <?php echo $tab==='overview'?'nav-tab-active':''; ?>">概览</a>
            <a href="<?php echo esc_url(add_query_arg('vt','partners',$base)); ?>" class="nav-tab <?php echo $tab==='partners'?'nav-tab-active':''; ?>">合伙人列表</a>
            <a href="<?php echo esc_url(add_query_arg('vt','applies',$base)); ?>" class="nav-tab <?php echo $tab==='applies'?'nav-tab-active':''; ?>">待审核申请</a>
            <a href="<?php echo esc_url(add_query_arg('vt','commissions',$base)); ?>" class="nav-tab <?php echo $tab==='commissions'?'nav-tab-active':''; ?>">佣金</a>
            <a href="<?php echo esc_url(add_query_arg('vt','withdrawals',$base)); ?>" class="nav-tab <?php echo $tab==='withdrawals'?'nav-tab-active':''; ?>">提现</a>
        </h2>

        <?php
        $method = 'vdp_partner_admin_tab_' . $tab;
        if (function_exists($method)) $method();
        else vdp_partner_admin_tab_overview();
        ?>
    </div>
    <?php
}

function vdp_partner_admin_tab_overview() {
    global $wpdb;
    $partner_count   = (int) $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key='vdp_invite_code'");
    $relations_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}vdp_partner_relations");
    $pending_amount  = (float) $wpdb->get_var("SELECT IFNULL(SUM(amount),0) FROM {$wpdb->prefix}vdp_commissions WHERE status='pending'");
    $settled_amount  = (float) $wpdb->get_var("SELECT IFNULL(SUM(amount),0) FROM {$wpdb->prefix}vdp_commissions WHERE status IN ('settled','paid')");
    $wd_pending      = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}vdp_withdrawals WHERE status='pending'");
    ?>
    <table class="widefat" style="margin-top:20px;">
        <tbody>
            <tr><td>已加入合伙人</td><td><strong><?php echo $partner_count; ?></strong> 人</td></tr>
            <tr><td>已建立的上下级关系</td><td><strong><?php echo $relations_count; ?></strong> 条</td></tr>
            <tr><td>冷冻中佣金</td><td>¥<?php echo number_format($pending_amount, 2); ?></td></tr>
            <tr><td>已结算佣金（含已提现）</td><td>¥<?php echo number_format($settled_amount, 2); ?></td></tr>
            <tr><td>待审核提现</td><td><strong><?php echo $wd_pending; ?></strong> 条</td></tr>
        </tbody>
    </table>
    <p style="margin-top:20px;">配置入口：<a href="<?php echo esc_url(admin_url('admin.php?page=vdp_options')); ?>">主题选项 → 分销</a></p>
    <?php
}

function vdp_partner_admin_tab_partners() {
    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT u.ID, u.user_login, u.display_name, um.meta_value AS code
         FROM {$wpdb->usermeta} um
         JOIN {$wpdb->users} u ON u.ID = um.user_id
         WHERE um.meta_key='vdp_invite_code'
         ORDER BY um.umeta_id DESC LIMIT 100"
    );
    ?>
    <table class="widefat striped" style="margin-top:20px;">
        <thead><tr><th>用户</th><th>邀请码</th><th>下线 L1/L2</th><th>余额</th><th>累计收益</th></tr></thead>
        <tbody>
        <?php if (!$rows): ?><tr><td colspan="5">暂无合伙人</td></tr><?php endif; ?>
        <?php foreach ($rows as $r):
            $dl = vdp_partner_count_downlines($r->ID);
        ?>
            <tr>
                <td><?php echo esc_html($r->display_name ?: $r->user_login); ?> (#<?php echo $r->ID; ?>)</td>
                <td><?php echo esc_html($r->code); ?></td>
                <td><?php echo $dl['l1']; ?> / <?php echo $dl['l2']; ?></td>
                <td>¥<?php echo number_format(VDP_Partner::get_balance($r->ID), 2); ?></td>
                <td>¥<?php echo number_format(VDP_Partner::get_total_earned($r->ID), 2); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function vdp_partner_admin_tab_applies() {
    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT u.ID, u.user_login, u.display_name, um.meta_value AS applied
         FROM {$wpdb->usermeta} um
         JOIN {$wpdb->users} u ON u.ID = um.user_id
         LEFT JOIN {$wpdb->usermeta} um2 ON um2.user_id = u.ID AND um2.meta_key='vdp_partner_approved'
         WHERE um.meta_key='vdp_partner_applied' AND um2.meta_value IS NULL
         ORDER BY um.umeta_id DESC"
    );
    ?>
    <table class="widefat striped" style="margin-top:20px;">
        <thead><tr><th>用户</th><th>申请时间</th><th>操作</th></tr></thead>
        <tbody>
        <?php if (!$rows): ?><tr><td colspan="3">暂无待审核申请</td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?php echo esc_html($r->display_name ?: $r->user_login); ?> (#<?php echo $r->ID; ?>)</td>
                <td><?php echo esc_html($r->applied); ?></td>
                <td>
                    <form method="post" style="display:inline">
                        <?php wp_nonce_field('vdp_partner_admin'); ?>
                        <input type="hidden" name="user_id" value="<?php echo $r->ID; ?>">
                        <button type="submit" name="vdp_partner_action" value="approve_partner" class="button button-primary">通过</button>
                        <button type="submit" name="vdp_partner_action" value="reject_partner" class="button">驳回</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function vdp_partner_admin_tab_commissions() {
    global $wpdb;
    $status = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
    $where  = $status ? $wpdb->prepare("WHERE status=%s", $status) : '';
    $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}vdp_commissions $where ORDER BY id DESC LIMIT 200");
    $base = admin_url('admin.php?page=vdp-partner&vt=commissions');
    ?>
    <p style="margin-top:15px;">
        筛选：
        <a href="<?php echo esc_url($base); ?>">全部</a> |
        <a href="<?php echo esc_url(add_query_arg('status','pending',$base)); ?>">冷冻中</a> |
        <a href="<?php echo esc_url(add_query_arg('status','settled',$base)); ?>">已结算</a> |
        <a href="<?php echo esc_url(add_query_arg('status','cancelled',$base)); ?>">已撤销</a>
    </p>
    <table class="widefat striped">
        <thead><tr><th>ID</th><th>订单</th><th>受益人</th><th>买家</th><th>层级</th><th>金额</th><th>比例</th><th>状态</th><th>解冻时间</th><th>标记</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?php echo $r->id; ?></td>
                <td><?php echo esc_html($r->order_num); ?></td>
                <td>#<?php echo $r->beneficiary_id; ?></td>
                <td>#<?php echo $r->buyer_id; ?></td>
                <td>L<?php echo $r->level; ?></td>
                <td>¥<?php echo number_format($r->amount, 2); ?></td>
                <td><?php echo number_format($r->rate * 100, 1); ?>%</td>
                <td><?php echo esc_html($r->status); ?></td>
                <td><?php echo esc_html($r->frozen_until); ?></td>
                <td><?php
                    if ($r->refund_flagged) echo '<span style="color:#d63638;">退款</span> ';
                    if ($r->flagged) echo '<span style="color:#dba617;">同IP</span>';
                ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function vdp_partner_admin_tab_withdrawals() {
    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT w.*, u.user_login, u.display_name
         FROM {$wpdb->prefix}vdp_withdrawals w
         LEFT JOIN {$wpdb->users} u ON u.ID = w.user_id
         ORDER BY w.id DESC LIMIT 200"
    );
    ?>
    <table class="widefat striped" style="margin-top:20px;">
        <thead><tr><th>ID</th><th>用户</th><th>金额</th><th>渠道</th><th>账号</th><th>状态</th><th>申请时间</th><th>备注</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?php echo $r->id; ?></td>
                <td><?php echo esc_html($r->display_name ?: $r->user_login); ?> (#<?php echo $r->user_id; ?>)</td>
                <td>¥<?php echo number_format($r->amount, 2); ?></td>
                <td><?php echo esc_html($r->channel); ?></td>
                <td title="<?php echo esc_attr($r->account_no); ?>">
                    <?php echo esc_html($r->account_name); ?><br>
                    <small><?php echo esc_html($r->account_no); ?></small>
                </td>
                <td><?php echo esc_html($r->status); ?></td>
                <td><?php echo esc_html($r->created_at); ?></td>
                <td><?php echo esc_html($r->admin_note); ?></td>
                <td>
                    <?php if ($r->status === 'pending'): ?>
                        <form method="post" style="display:inline">
                            <?php wp_nonce_field('vdp_partner_admin'); ?>
                            <input type="hidden" name="wd_id" value="<?php echo $r->id; ?>">
                            <input type="text" name="note" placeholder="备注" style="width:80px">
                            <button type="submit" name="vdp_partner_action" value="paid" class="button button-primary">已打款</button>
                            <button type="submit" name="vdp_partner_action" value="reject" class="button">驳回</button>
                        </form>
                    <?php elseif ($r->status === 'approved'): ?>
                        <form method="post" style="display:inline">
                            <?php wp_nonce_field('vdp_partner_admin'); ?>
                            <input type="hidden" name="wd_id" value="<?php echo $r->id; ?>">
                            <button type="submit" name="vdp_partner_action" value="paid" class="button button-primary">标记已打款</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}
