<?php
/**
 * 主题选项面板定义
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('CSF')) return;

$prefix = 'vdp_options';

/* ============================================
   1. 站点基础
   ============================================ */
CSF::createSection($prefix, [
    'title'  => '站点基础',
    'icon'   => 'fa fa-cog',
    'fields' => [
        [
            'id'      => 'site_logo',
            'type'    => 'media',
            'title'   => '站点 Logo',
            'library' => 'image',
        ],
        [
            'id'      => 'site_logo_dark',
            'type'    => 'media',
            'title'   => '暗色 Logo',
            'subtitle'=> '暗色模式下使用的 Logo',
            'library' => 'image',
        ],
        [
            'id'      => 'site_favicon',
            'type'    => 'media',
            'title'   => '站点 Favicon',
            'library' => 'image',
        ],
        [
            'id'    => 'site_subtitle',
            'type'  => 'text',
            'title' => '站点副标题',
        ],
        [
            'id'     => 'site_keywords',
            'type'   => 'textarea',
            'title'  => '站点关键词',
            'rows'   => 2,
            'desc'   => '用于首页 meta keywords，多个用英文逗号分隔',
        ],
        [
            'id'     => 'site_description',
            'type'   => 'textarea',
            'title'  => '站点描述',
            'rows'   => 3,
        ],
    ],
]);

/* ============================================
   2. 主题样式
   ============================================ */
CSF::createSection($prefix, [
    'title'  => '主题样式',
    'icon'   => 'fa fa-paint-brush',
    'fields' => [
        [
            'id'      => 'theme_color',
            'type'    => 'color',
            'title'   => '主题色',
            'default' => '#2196F3',
        ],
        [
            'id'      => 'theme_color_hover',
            'type'    => 'color',
            'title'   => '主题色（悬停）',
            'default' => '#1976D2',
        ],
        [
            'id'      => 'main_radius',
            'type'    => 'text',
            'title'   => '圆角大小',
            'default' => '8px',
        ],
        [
            'id'      => 'enable_dark_mode',
            'type'    => 'switcher',
            'title'   => '暗色模式',
            'desc'    => '允许用户切换暗色模式',
            'default' => true,
        ],
    ],
]);

/* ============================================
   3. 列表页设置
   ============================================ */
CSF::createSection($prefix, [
    'title'  => '列表页',
    'icon'   => 'fa fa-list',
    'fields' => [
        [
            'id'      => 'home_layout',
            'type'    => 'image_select',
            'title'   => '首页布局',
            'options' => [
                'grid' => VDP_THEME_URI . '/inc/codestar-framework/assets/images/align-1.png',
                'list' => VDP_THEME_URI . '/inc/codestar-framework/assets/images/align-2.png',
            ],
            'default' => 'grid',
        ],
        [
            'id'      => 'posts_per_page',
            'type'    => 'number',
            'title'   => '每页显示数量',
            'default' => 12,
        ],
        [
            'id'      => 'posts_per_row',
            'type'    => 'select',
            'title'   => '一行显示几个',
            'options' => [
                '2' => '2 个 / 行',
                '3' => '3 个 / 行',
                '4' => '4 个 / 行',
            ],
            'default' => '3',
            'desc'    => '首页 / 分类 / 归档 / 搜索结果列表的每行卡片数（小屏幕会自动减少）',
        ],
        [
            'id'      => 'show_excerpt',
            'type'    => 'switcher',
            'title'   => '显示摘要',
            'default' => true,
        ],
    ],
]);

/* ============================================
   4. 文档详情
   ============================================ */
CSF::createSection($prefix, [
    'title'  => '文档详情',
    'icon'   => 'fa fa-file-text',
    'fields' => [
        [
            'id'      => 'preview_pages',
            'type'    => 'number',
            'title'   => '默认免费预览页数',
            'default' => 3,
        ],
        [
            'id'      => 'enable_related',
            'type'    => 'switcher',
            'title'   => '显示相关推荐',
            'default' => true,
        ],
        [
            'id'      => 'enable_comments',
            'type'    => 'switcher',
            'title'   => '允许评论',
            'default' => true,
        ],
    ],
]);

/* ============================================
   5. 用户与会员
   ============================================ */
CSF::createSection($prefix, [
    'title'  => '用户与会员',
    'icon'   => 'fa fa-user',
    'fields' => [
        [
            'id'      => 'allow_signup',
            'type'    => 'switcher',
            'title'   => '允许注册',
            'default' => true,
        ],
        [
            'id'      => 'default_avatar',
            'type'    => 'media',
            'title'   => '默认头像',
            'library' => 'image',
        ],
        [
            'type'    => 'subheading',
            'content' => 'VIP 套餐',
        ],
        [
            'id'      => 'vip_enabled',
            'type'    => 'switcher',
            'title'   => '启用会员系统',
            'desc'    => '关闭后会员中心、付费会员入口将隐藏',
            'default' => true,
        ],
        [
            'id'      => 'vip_monthly_price',
            'type'    => 'number',
            'title'   => '月度会员价格',
            'default' => 19,
            'after'   => '<span class="text-muted">元 / 月</span>',
        ],
        [
            'id'      => 'vip_monthly_days',
            'type'    => 'number',
            'title'   => '月度会员天数',
            'default' => 30,
            'after'   => '<span class="text-muted">天</span>',
        ],
        [
            'id'      => 'vip_yearly_price',
            'type'    => 'number',
            'title'   => '年度会员价格',
            'default' => 99,
            'after'   => '<span class="text-muted">元 / 年</span>',
        ],
        [
            'id'      => 'vip_yearly_days',
            'type'    => 'number',
            'title'   => '年度会员天数',
            'default' => 365,
            'after'   => '<span class="text-muted">天</span>',
        ],
        [
            'id'      => 'vip_lifetime_price',
            'type'    => 'number',
            'title'   => '永久会员价格',
            'default' => 299,
            'after'   => '<span class="text-muted">元</span>',
        ],
    ],
]);

/* ============================================
   6. 支付配置
   ============================================ */
CSF::createSection($prefix, [
    'title'  => '支付配置',
    'icon'   => 'fa fa-credit-card',
    'fields' => [
        [
            'id'      => 'pay_enabled',
            'type'    => 'switcher',
            'title'   => '启用支付',
            'default' => true,
        ],
        [
            'type'    => 'subheading',
            'content' => '虎皮椒 V3',
        ],
        [
            'id'    => 'xunhupay_appid',
            'type'  => 'text',
            'title' => 'AppID',
        ],
        [
            'id'    => 'xunhupay_appsecret',
            'type'  => 'text',
            'title' => 'AppSecret',
        ],
        [
            'id'      => 'xunhupay_methods',
            'type'    => 'checkbox',
            'title'   => '支持的支付方式',
            'options' => [
                'wechat' => '微信',
                'alipay' => '支付宝',
            ],
            'default' => ['wechat', 'alipay'],
        ],
    ],
]);

/* ============================================
   6.1 社交登录（OAuth）
   ============================================ */
CSF::createSection($prefix, [
    'title'  => '社交登录',
    'icon'   => 'fa fa-weixin',
    'fields' => [
        [
            'type'    => 'subheading',
            'content' => '微信登录配置',
        ],
        [
            'id'      => 'oauth_weixin_enabled',
            'type'    => 'switcher',
            'title'   => '启用微信登录',
            'default' => false,
        ],
        [
            'id'      => 'oauth_weixin_mode',
            'type'    => 'button_set',
            'title'   => '登录模式',
            'options' => [
                'mp'   => '公众号（推荐）',
                'open' => '开放平台',
            ],
            'default' => 'mp',
            'desc'    => '公众号模式：使用已认证服务号 OAuth，PC 扫码 + H5 自动登录，不需要用户关注公众号<br>开放平台模式：需在 open.weixin.qq.com 创建网站应用（300元认证费）',
        ],
        [
            'id'    => 'oauth_weixin_appid',
            'type'  => 'text',
            'title' => 'AppID',
            'desc'  => '公众号模式填服务号 AppID；开放平台模式填网站应用 AppID',
        ],
        [
            'id'    => 'oauth_weixin_appsecret',
            'type'  => 'text',
            'title' => 'AppSecret',
        ],
        [
            'type'       => 'subheading',
            'content'    => '公众号模式配置指引',
            'dependency' => ['oauth_weixin_mode', '==', 'mp'],
        ],
        [
            'type'       => 'submessage',
            'style'      => 'info',
            'content'    => '<strong>公众号后台配置步骤：</strong><br>'
                . '1. 登录 mp.weixin.qq.com → 设置与开发 → 公众号设置 → 功能设置<br>'
                . '2. 「网页授权域名」填写：<code>' . esc_html(parse_url(home_url(), PHP_URL_HOST)) . '</code><br>'
                . '3. 下载验证文件放到网站根目录<br>'
                . '4. 设置与开发 → 基本配置 → IP 白名单添加服务器 IP<br><br>'
                . '<strong>工作原理：</strong>PC 端显示二维码 → 用户微信扫码 → 微信内打开授权页 → 授权后 PC 自动登录<br>'
                . '无需用户关注公众号，无需配置服务器消息回调',
            'dependency' => ['oauth_weixin_mode', '==', 'mp'],
        ],
        [
            'type'       => 'subheading',
            'content'    => '开放平台模式回调地址',
            'dependency' => ['oauth_weixin_mode', '==', 'open'],
        ],
        [
            'type'       => 'submessage',
            'style'      => 'info',
            'content'    => '回调域名：<code>' . esc_html(parse_url(home_url(), PHP_URL_HOST)) . '</code><br>完整回调地址：<code>' . esc_html(home_url('/?vdp_oauth_callback=weixin')) . '</code>',
            'dependency' => ['oauth_weixin_mode', '==', 'open'],
        ],
    ],
]);

/* ============================================
   7. 存储设置（COS / 123 网盘 双引擎）
   ============================================ */
CSF::createSection($prefix, [
    'title'  => '存储设置',
    'icon'   => 'fa fa-cloud',
    'fields' => [
        [
            'id'      => 'storage_engine',
            'type'    => 'button_set',
            'title'   => '默认存储引擎',
            'options' => [
                'cos'    => '腾讯云 COS',
                'pan123' => '123 网盘',
            ],
            'default' => 'cos',
            'desc'    => '新上传的文件默认使用此引擎；可在单篇文章中覆盖。',
        ],

        // ============ 腾讯云 COS ============
        [
            'type'    => 'subheading',
            'content' => '腾讯云 COS',
        ],
        [
            'id'      => 'cos_enabled',
            'type'    => 'switcher',
            'title'   => '启用 COS',
            'default' => false,
        ],
        [
            'id'    => 'cos_secret_id',
            'type'  => 'text',
            'title' => 'SecretId',
        ],
        [
            'id'    => 'cos_secret_key',
            'type'  => 'text',
            'title' => 'SecretKey',
        ],
        [
            'id'    => 'cos_bucket',
            'type'  => 'text',
            'title' => 'Bucket',
        ],
        [
            'id'      => 'cos_region',
            'type'    => 'select',
            'title'   => 'Region',
            'options' => [
                'ap-beijing'    => '北京',
                'ap-shanghai'   => '上海',
                'ap-guangzhou'  => '广州',
                'ap-chengdu'    => '成都',
                'ap-hongkong'   => '香港',
            ],
            'default' => 'ap-beijing',
        ],
        [
            'id'    => 'cos_domain',
            'type'  => 'text',
            'title' => '加速域名',
            'desc'  => '可选，留空使用默认域名',
        ],
        [
            'id'      => 'cos_signed_url_expire',
            'type'    => 'number',
            'title'   => '签名链接有效期',
            'default' => 600,
            'after'   => '<span class="text-muted">秒</span>',
        ],

        // ============ 123 网盘开放平台 ============
        [
            'type'    => 'subheading',
            'content' => '123 网盘开放平台',
        ],
        [
            'type'    => 'submessage',
            'style'   => 'warning',
            'content' => '【待官方开通】123 开放平台目前暂不支持个人开发者接入，需企业资质（ICP 备案 + 营业执照）发邮件 bd@123pan.com 申请。在拿到 clientID / clientSecret 之前，本节配置不生效。',
        ],
        [
            'id'      => 'pan123_enabled',
            'type'    => 'switcher',
            'title'   => '启用 123 网盘',
            'default' => false,
        ],
        [
            'id'    => 'pan123_client_id',
            'type'  => 'text',
            'title' => 'Client ID',
            'desc'  => '在 123 开放平台后台获取',
        ],
        [
            'id'    => 'pan123_client_secret',
            'type'  => 'text',
            'title' => 'Client Secret',
        ],
        [
            'id'      => 'pan123_root_dir',
            'type'    => 'text',
            'title'   => '根目录 ID',
            'default' => '0',
            'desc'    => '上传到的网盘目录 ID，根目录填 0',
        ],
        [
            'id'      => 'pan123_link_expire',
            'type'    => 'number',
            'title'   => '下载直链有效期',
            'default' => 3600,
            'after'   => '<span class="text-muted">秒</span>',
        ],

        // ============ 预览图引擎 ============
        [
            'type'    => 'subheading',
            'content' => '预览图引擎',
        ],
        [
            'id'      => 'preview_engine',
            'type'    => 'button_set',
            'title'   => '预览图生成方式',
            'options' => [
                'auto'   => '跟随存储（推荐）',
                'cos_ci' => '腾讯云 CI',
                'local'  => '本地服务器（LibreOffice）',
                'none'   => '不生成（用占位图）',
            ],
            'default' => 'auto',
            'desc'    => '"跟随存储"：COS 走 CI、123 网盘走本地；选择"本地"需要服务器装 LibreOffice + Imagick。',
        ],
        [
            'id'      => 'preview_pages_count',
            'type'    => 'number',
            'title'   => '预览图页数',
            'default' => 3,
            'desc'    => '每个文档生成多少页预览图',
        ],
        [
            'id'      => 'libreoffice_bin',
            'type'    => 'text',
            'title'   => 'LibreOffice 路径',
            'default' => '/usr/bin/libreoffice',
            'desc'    => '本地预览图引擎使用，命令行可执行文件路径',
        ],
    ],
]);

/* ============================================
   8. AI 摘要（DeepSeek）
   ============================================ */
CSF::createSection($prefix, [
    'title'  => 'AI 摘要',
    'icon'   => 'fa fa-magic',
    'fields' => [
        [
            'id'      => 'ai_summary_enabled',
            'type'    => 'switcher',
            'title'   => '启用 AI 摘要',
            'desc'    => '上传文档时自动调用 DeepSeek 生成 SEO 摘要',
            'default' => false,
        ],
        [
            'id'    => 'deepseek_api_key',
            'type'  => 'text',
            'title' => 'DeepSeek API Key',
            'desc'  => '在 platform.deepseek.com 获取',
        ],
        [
            'id'      => 'deepseek_api_base',
            'type'    => 'text',
            'title'   => 'API Base URL',
            'default' => 'https://api.deepseek.com',
        ],
        [
            'id'      => 'deepseek_model',
            'type'    => 'select',
            'title'   => '模型',
            'options' => [
                'deepseek-chat'     => 'deepseek-chat（通用）',
                'deepseek-reasoner' => 'deepseek-reasoner（推理增强）',
            ],
            'default' => 'deepseek-chat',
        ],
        [
            'id'      => 'ai_summary_length',
            'type'    => 'number',
            'title'   => '摘要长度',
            'default' => 180,
            'after'   => '<span class="text-muted">字</span>',
            'desc'    => '建议 150-200 字，便于 SEO 收录',
        ],
        [
            'id'      => 'ai_summary_overwrite',
            'type'    => 'switcher',
            'title'   => '覆盖已有摘要',
            'desc'    => '关闭：仅在文章 excerpt 为空时生成；开启：始终覆盖',
            'default' => false,
        ],
        [
            'id'      => 'ai_summary_async',
            'type'    => 'switcher',
            'title'   => '异步生成',
            'desc'    => '推荐开启：上传成功后通过 WP Cron 异步生成，避免阻塞上传',
            'default' => true,
        ],
    ],
]);

/* ============================================
   8.5 分销（合伙人）
   ============================================ */
CSF::createSection($prefix, [
    'title'  => '分销',
    'icon'   => 'fa fa-share-alt',
    'fields' => [
        [
            'id'      => 'partner_enabled',
            'type'    => 'switcher',
            'title'   => '启用合伙人计划',
            'default' => false,
        ],
        [
            'id'      => 'partner_eligibility',
            'type'    => 'button_set',
            'title'   => '准入方式',
            'options' => [
                'auto'     => '自动开通',
                'review'   => '申请审核',
                'vip_only' => '仅 VIP',
            ],
            'default' => 'auto',
        ],
        [
            'id'      => 'partner_rate_l1',
            'type'    => 'number',
            'title'   => 'L1 佣金比例 (%)',
            'default' => 20,
        ],
        [
            'id'      => 'partner_rate_l2',
            'type'    => 'number',
            'title'   => 'L2 佣金比例 (%)',
            'default' => 5,
        ],
        [
            'id'      => 'partner_rate_vip',
            'type'    => 'number',
            'title'   => 'VIP 升级版 L1 比例 (%)',
            'subtitle'=> '当 L1 推荐人本身是 VIP 时使用，需大于 L1 才生效',
            'default' => 25,
        ],
        [
            'id'      => 'partner_freeze_days',
            'type'    => 'number',
            'title'   => '冷冻期（天）',
            'default' => 7,
        ],
        [
            'id'      => 'partner_min_withdraw',
            'type'    => 'number',
            'title'   => '起提金额（元）',
            'default' => 50,
        ],
        [
            'id'      => 'partner_cookie_days',
            'type'    => 'number',
            'title'   => 'Cookie 有效天数',
            'default' => 30,
        ],
        [
            'id'      => 'partner_self_purchase',
            'type'    => 'switcher',
            'title'   => '允许自购计佣',
            'subtitle'=> '关闭时同 user_id / 同 IP 将屏蔽佣金',
            'default' => false,
        ],
        [
            'id'      => 'partner_withdraw_channels',
            'type'    => 'checkbox',
            'title'   => '提现渠道',
            'options' => [
                'wechat'  => '微信',
                'alipay'  => '支付宝',
                'balance' => '抵扣会员',
            ],
            'default' => ['wechat', 'alipay', 'balance'],
        ],
        [
            'id'      => 'partner_weekly_limit',
            'type'    => 'number',
            'title'   => '每 7 天最多提现次数',
            'default' => 1,
        ],
    ],
]);

/* ============================================
   9. SEO
   ============================================ */
CSF::createSection($prefix, [
    'title'  => 'SEO',
    'icon'   => 'fa fa-search',
    'fields' => [
        [
            'id'      => 'enable_breadcrumb',
            'type'    => 'switcher',
            'title'   => '面包屑导航',
            'default' => true,
        ],
        [
            'id'    => 'home_title_format',
            'type'  => 'text',
            'title' => '首页标题格式',
            'desc'  => '可用变量：{site_name}、{tagline}',
            'default' => '{site_name} - {tagline}',
        ],
        [
            'id'    => 'baidu_push_token',
            'type'  => 'text',
            'title' => '百度推送 Token',
        ],
        [
            'id'    => 'custom_head_code',
            'type'  => 'textarea',
            'title' => '自定义 head 代码',
            'rows'  => 5,
            'desc'  => '插入到 <head> 末尾，用于第三方 SDK、验证文件等',
        ],
        [
            'id'    => 'custom_footer_code',
            'type'  => 'textarea',
            'title' => '自定义 footer 代码',
            'rows'  => 5,
            'desc'  => '插入到 </body> 前',
        ],
        [
            'type'    => 'subheading',
            'content' => '统计代码',
        ],
        [
            'id'    => 'stat_code_head',
            'type'  => 'textarea',
            'title' => '统计代码（head）',
            'rows'  => 5,
            'desc'  => '百度统计 / Google Analytics 等需要放在 <head> 内的代码片段；输出在 <head> 末尾',
        ],
        [
            'id'    => 'stat_code_footer',
            'type'  => 'textarea',
            'title' => '统计代码（footer）',
            'rows'  => 5,
            'desc'  => '需要放在 </body> 前的统计 / 监测脚本',
        ],
    ],
]);

/* ============================================
   10. 页脚信息
   ============================================ */
CSF::createSection($prefix, [
    'title'  => '页脚',
    'icon'   => 'fa fa-paragraph',
    'fields' => [
        [
            'id'    => 'footer_copyright',
            'type'  => 'textarea',
            'title' => '版权信息',
            'rows'  => 2,
            'default' => '© ' . date('Y') . ' All rights reserved.',
        ],
        [
            'id'    => 'footer_icp',
            'type'  => 'text',
            'title' => 'ICP 备案号',
        ],
        [
            'id'    => 'footer_police',
            'type'  => 'text',
            'title' => '公安备案号',
        ],
    ],
]);
