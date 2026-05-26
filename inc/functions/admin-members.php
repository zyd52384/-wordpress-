<?php
/**
 * 后台会员管理页面
 * 显示所有注册用户 + VIP 状态 + 分类搜索
 */

if (!defined('ABSPATH')) exit;

function vdp_render_members_page() {
    global $wpdb;
    $mem_table = $wpdb->prefix . 'vdp_memberships';
    $products  = VDP_Member::get_products();

    // 处理手动添加/延期
    if (isset($_POST['vdp_add_member']) && wp_verify_nonce($_POST['_wpnonce'], 'vdp_member_action')) {
        $user_input = trim($_POST['user_id']);
        $level = sanitize_text_field($_POST['level']);
        $extra_days = intval($_POST['extra_days']);

        $user_id = is_numeric($user_input) ? intval($user_input) : 0;
        if (!$user_id) {
            $user = get_user_by('login', $user_input);
            $user_id = $user ? $user->ID : 0;
        }

        if ($user_id && $level && $extra_days >= 0) {
            $member = new VDP_Member();
            $member->activate_membership($user_id, $level, $extra_days, 'manual_' . time());
            echo '<div class="notice notice-success"><p>会员已添加/续期成功！</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>参数错误：请检查用户ID/用户名和等级</p></div>';
        }
    }

    // 筛选参数
    $filter   = sanitize_key($_GET['filter'] ?? 'all');
    $search   = sanitize_text_field($_GET['s'] ?? '');
    $paged    = max(1, intval($_GET['paged'] ?? 1));
    $per_page = 30;
    $offset   = ($paged - 1) * $per_page;

    // 构建查询
    $where = "WHERE 1=1";
    $join  = "";

    if ($filter === 'vip') {
        $join = "LEFT JOIN {$mem_table} m ON m.user_id = u.ID";
        $where .= " AND m.status = 'active' AND m.end_date >= NOW()";
    } elseif ($filter === 'expired') {
        $join = "LEFT JOIN {$mem_table} m ON m.user_id = u.ID";
        $where .= " AND (m.status = 'expired' OR (m.status = 'active' AND m.end_date < NOW()))";
    } elseif ($filter === 'normal') {
        $join = "LEFT JOIN {$mem_table} m ON m.user_id = u.ID AND m.status = 'active' AND m.end_date >= NOW()";
        $where .= " AND m.user_id IS NULL";
    } else {
        $join = "LEFT JOIN {$mem_table} m ON m.user_id = u.ID AND m.status = 'active' AND m.end_date >= NOW()";
    }

    if ($search) {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where .= $wpdb->prepare(
            " AND (u.ID = %d OR u.user_login LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s)",
            intval($search), $like, $like, $like
        );
    }

    $total = $wpdb->get_var("SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u {$join} {$where}");
    $users = $wpdb->get_results(
        "SELECT u.ID, u.user_login, u.user_email, u.display_name, u.user_registered,
                m.level AS vip_level, m.end_date AS vip_end
         FROM {$wpdb->users} u {$join} {$where}
         GROUP BY u.ID
         ORDER BY u.user_registered DESC
         LIMIT {$offset}, {$per_page}"
    );

    $total_pages = ceil($total / $per_page);

    // 统计
    $count_all     = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
    $count_vip     = $wpdb->get_var("SELECT COUNT(*) FROM {$mem_table} WHERE status = 'active' AND end_date >= NOW()");
    $count_expired = $wpdb->get_var("SELECT COUNT(*) FROM {$mem_table} WHERE status = 'expired' OR (status = 'active' AND end_date < NOW())");
    $count_normal  = $count_all - $count_vip;

    $rel_table = $wpdb->prefix . 'vdp_partner_relations';
    ?>
    <div class="wrap">
        <h1>会员管理</h1>

        <!-- 手动添加/续期 -->
        <div class="vdp-admin-box" style="background:#fff;padding:15px 20px;margin:15px 0;border:1px solid #ccd0d4;">
            <h3 style="margin-top:0;">手动添加/续期</h3>
            <form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                <?php wp_nonce_field('vdp_member_action'); ?>
                <label>用户ID/用户名<br><input type="text" name="user_id" required style="width:140px;"></label>
                <label>等级<br>
                    <select name="level">
                        <?php foreach ($products as $key => $p) : ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($p['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>天数<br><input type="number" name="extra_days" value="30" min="0" style="width:80px;"></label>
                <button type="submit" name="vdp_add_member" class="button button-primary">添加/续期</button>
            </form>
        </div>

        <!-- 筛选 Tabs -->
        <ul class="subsubsub" style="margin-bottom:10px;">
            <li><a href="<?php echo esc_url(add_query_arg(['filter'=>'all','paged'=>1,'s'=>$search])); ?>" class="<?php echo $filter==='all'?'current':''; ?>">全部 <span class="count">(<?php echo $count_all; ?>)</span></a> |</li>
            <li><a href="<?php echo esc_url(add_query_arg(['filter'=>'vip','paged'=>1,'s'=>$search])); ?>" class="<?php echo $filter==='vip'?'current':''; ?>">VIP会员 <span class="count">(<?php echo $count_vip; ?>)</span></a> |</li>
            <li><a href="<?php echo esc_url(add_query_arg(['filter'=>'normal','paged'=>1,'s'=>$search])); ?>" class="<?php echo $filter==='normal'?'current':''; ?>">普通用户 <span class="count">(<?php echo $count_normal; ?>)</span></a> |</li>
            <li><a href="<?php echo esc_url(add_query_arg(['filter'=>'expired','paged'=>1,'s'=>$search])); ?>" class="<?php echo $filter==='expired'?'current':''; ?>">已过期 <span class="count">(<?php echo $count_expired; ?>)</span></a></li>
        </ul>

        <!-- 搜索 -->
        <form method="get" style="float:right;margin-top:-30px;">
            <input type="hidden" name="page" value="vdp-members">
            <input type="hidden" name="filter" value="<?php echo esc_attr($filter); ?>">
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="搜索ID/用户名/邮箱">
            <button type="submit" class="button">搜索</button>
        </form>

        <table class="wp-list-table widefat fixed striped" style="margin-top:10px;">
            <thead>
                <tr>
                    <th style="width:50px;">ID</th>
                    <th>用户</th>
                    <th>邮箱</th>
                    <th style="width:100px;">VIP等级</th>
                    <th style="width:120px;">VIP到期</th>
                    <th style="width:140px;">注册时间</th>
                    <th style="width:100px;">来源</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$users) : ?>
                <tr><td colspan="7">没有找到用户。</td></tr>
            <?php else : foreach ($users as $u) :
                $avatar = get_avatar($u->ID, 32);
                $oauth  = get_user_meta($u->ID, 'vdp_oauth_weixin_openid', true);
                $ref    = $wpdb->get_var($wpdb->prepare("SELECT referrer_l1 FROM {$rel_table} WHERE user_id = %d", $u->ID));
            ?>
                <tr>
                    <td><?php echo $u->ID; ?></td>
                    <td><?php echo $avatar; ?> <?php echo esc_html($u->display_name ?: $u->user_login); ?></td>
                    <td><?php echo esc_html($u->user_email); ?></td>
                    <td>
                        <?php if ($u->vip_level) : ?>
                            <span style="color:#e6a817;font-weight:bold;"><?php echo esc_html($products[$u->vip_level]['name'] ?? $u->vip_level); ?></span>
                        <?php else : ?>
                            <span style="color:#999;">普通</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($u->vip_end) : ?>
                            <?php echo ($u->vip_end >= '2099-01-01') ? '永久' : esc_html(date('Y-m-d', strtotime($u->vip_end))); ?>
                        <?php else : ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html(date('Y-m-d H:i', strtotime($u->user_registered))); ?></td>
                    <td>
                        <?php if ($oauth) : ?><span title="微信登录"><i class="dashicons dashicons-wechat" style="color:#1aad19;"></i></span><?php endif; ?>
                        <?php if ($ref) : ?><span title="推荐人ID:<?php echo $ref; ?>"><i class="dashicons dashicons-groups"></i></span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>

        <!-- 分页 -->
        <?php if ($total_pages > 1) : ?>
            <div class="tablenav bottom"><div class="tablenav-pages">
                <?php
                $base_url = add_query_arg(['filter' => $filter, 's' => $search, 'page' => 'vdp-members']);
                for ($i = 1; $i <= $total_pages; $i++) {
                    $cls = ($i === $paged) ? 'class="button button-primary"' : 'class="button"';
                    echo '<a ' . $cls . ' href="' . esc_url(add_query_arg('paged', $i, $base_url)) . '">' . $i . '</a> ';
                }
                ?>
            </div></div>
        <?php endif; ?>
    </div>
    <?php
}
