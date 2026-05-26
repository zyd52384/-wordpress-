<?php
/**
 * 小工具基础类（参考子比 Zib_CFSwidget 设计）
 *
 * 子类只需声明 fields() 与 render() 即可获得：
 *   - 标题 + 副标题 + 标题右侧链接（more_but / more_but_url）
 *   - 显示规则（PC/移动端）
 *   - 按 ID 显示或隐藏（在文章/页面）
 *   - 侧栏随动（sidebar_affix）
 */

if (!defined('ABSPATH')) exit;

abstract class VDP_Widget extends WP_Widget {

    /** 子类声明字段：[name => ['type' => 'text|number|textarea|select|checkbox', 'label' => ..., 'default' => ..., 'desc' => ..., 'options' => ...]] */
    abstract protected function fields();

    /** 子类渲染前端内容 */
    abstract protected function render($args, $instance);

    /** 通用字段（所有子类自动继承） */
    protected function common_fields() {
        return [
            'title' => [
                'type'    => 'text',
                'label'   => '模块标题（留空不显示）',
                'default' => '',
            ],
            'subtitle' => [
                'type'    => 'text',
                'label'   => '副标题（显示在标题右侧）',
                'default' => '',
            ],
            'more_but' => [
                'type'    => 'text',
                'label'   => '更多按钮文字',
                'default' => '',
                'desc'    => '不为空时，标题右侧显示"更多"链接',
            ],
            'more_but_url' => [
                'type'    => 'text',
                'label'   => '更多按钮链接',
                'default' => '',
            ],
            'show_type' => [
                'type'    => 'select',
                'label'   => '显示规则',
                'default' => 'all',
                'options' => [
                    'all'     => 'PC/移动端均显示',
                    'only_pc' => '仅 PC 端显示',
                    'only_sm' => '仅移动端显示',
                ],
            ],
            'show_id_type' => [
                'type'    => 'select',
                'label'   => '按 ID 显示/隐藏',
                'default' => '',
                'options' => [
                    ''     => '不限制',
                    'show' => '仅在以下 ID 中显示',
                    'hide' => '在以下 ID 中隐藏',
                ],
            ],
            'show_ids' => [
                'type'    => 'text',
                'label'   => 'ID 列表',
                'default' => '',
                'desc'    => '多个用英文逗号分隔，如：1,2,3',
            ],
            'sidebar_affix' => [
                'type'    => 'checkbox',
                'label'   => '侧栏随动（仅在侧边栏中有效）',
                'default' => 0,
            ],
            'closable' => [
                'type'    => 'checkbox',
                'label'   => '允许访客关闭（右上角 × 按钮，关闭后浏览器记住）',
                'default' => 0,
            ],
        ];
    }

    /** 合并通用字段 + 子类字段（子类覆盖通用，例如自定义 title 默认值） */
    protected function all_fields() {
        return array_merge($this->common_fields(), $this->fields());
    }

    /** 是否应在当前请求中显示（PC/移动 + ID 限制） */
    protected function is_visible($instance) {
        $type = !empty($instance['show_type']) ? $instance['show_type'] : 'all';
        $is_mobile = wp_is_mobile();

        if ($type === 'only_pc' && $is_mobile) return false;
        if ($type === 'only_sm' && !$is_mobile) return false;

        if (!empty($instance['show_id_type']) && !empty($instance['show_ids'])) {
            if (is_singular()) {
                $cur_id  = get_the_ID();
                $ids_arr = preg_split('/[,，\s]+/', $instance['show_ids']);
                $ids_arr = array_filter(array_map('intval', $ids_arr));

                if ($instance['show_id_type'] === 'show' && !in_array($cur_id, $ids_arr, true)) return false;
                if ($instance['show_id_type'] === 'hide' && in_array($cur_id, $ids_arr, true)) return false;
            }
        }
        return true;
    }

    /** 显示规则附加 class */
    protected function show_class($instance) {
        $type = !empty($instance['show_type']) ? $instance['show_type'] : 'all';
        if ($type === 'only_pc') return 'hidden-xs';
        if ($type === 'only_sm') return 'visible-xs-block';
        return '';
    }

    /** 渲染标题 + 副标题 + 更多按钮（+ 可选的关闭按钮） */
    protected function render_title($instance, $skip_close = false) {
        $title    = !empty($instance['title']) ? $instance['title'] : '';
        $closable = !empty($instance['closable']) && !$skip_close;

        // 没标题且不可关闭：完全不输出 header
        if (!$title && !$closable) return '';

        $subtitle = '';
        if ($title && !empty($instance['subtitle'])) {
            $subtitle = '<small class="vdp-widget-subtitle">' . esc_html($instance['subtitle']) . '</small>';
        }

        $more = '';
        if ($title && !empty($instance['more_but']) && !empty($instance['more_but_url'])) {
            $more = '<a href="' . esc_url($instance['more_but_url']) . '" class="vdp-widget-more">'
                  . esc_html($instance['more_but']) . ' <i class="fa fa-angle-right"></i></a>';
        }

        $close = $closable
            ? '<button type="button" class="vdp-widget-close" aria-label="关闭" title="关闭">&times;</button>'
            : '';

        $title_html = $title
            ? '<h3 class="vdp-widget-title">' . esc_html($title) . $subtitle . '</h3>'
            : '<span class="vdp-widget-title vdp-widget-title-empty"></span>';

        return '<div class="vdp-widget-header">' . $title_html . $more . $close . '</div>';
    }

    public function widget($args, $instance) {
        $instance = $this->merge_defaults($instance);

        if (!$this->is_visible($instance)) return;

        // 注入 affix、show_class、closable
        // 若侧栏过滤器已经注入了 data-closable，则跳过本类的 closable 注入避免双 ×
        $already_closable = !empty($args['before_widget']) && strpos($args['before_widget'], 'data-closable') !== false;

        $extra_classes = [];
        $sc = trim($this->show_class($instance));
        if ($sc) $extra_classes[] = $sc;
        if (!empty($instance['closable']) && !$already_closable) $extra_classes[] = 'vdp-widget--closable';

        $affix    = !empty($instance['sidebar_affix']) ? ' data-affix="true"' : '';
        $closable = (!empty($instance['closable']) && !$already_closable) ? ' data-closable="true"' : '';

        if ($extra_classes) {
            $args['before_widget'] = preg_replace(
                '/class="([^"]*)"/',
                'class="$1 ' . implode(' ', $extra_classes) . '"',
                $args['before_widget'],
                1
            );
        }
        if ($affix || $closable) {
            $args['before_widget'] = preg_replace(
                '/<div /',
                '<div' . $affix . $closable . ' ',
                $args['before_widget'],
                1
            );
        }

        echo $args['before_widget'];
        echo $this->render_title($instance, $already_closable);
        echo '<div class="vdp-widget-body">';
        $this->render($args, $instance);
        echo '</div>';
        echo $args['after_widget'];
    }

    public function form($instance) {
        $instance = $this->merge_defaults($instance);
        $child_fields = $this->fields();

        // 子类业务字段在前
        foreach ($child_fields as $name => $field) {
            $this->render_field($name, $field, $instance);
        }
        echo '<hr style="margin:14px 0;border:0;border-top:1px dashed #ddd;">';
        echo '<p style="font-weight:600;color:#666;">通用设置</p>';
        // 通用字段（跳过子类已声明的同名字段）
        foreach ($this->common_fields() as $name => $field) {
            if (isset($child_fields[$name])) continue;
            $this->render_field($name, $field, $instance);
        }
    }

    public function update($new_instance, $old_instance) {
        $clean = [];
        foreach ($this->all_fields() as $name => $field) {
            $value = isset($new_instance[$name]) ? $new_instance[$name] : '';
            switch ($field['type']) {
                case 'number':
                    $clean[$name] = (int) $value;
                    break;
                case 'checkbox':
                    $clean[$name] = !empty($value) ? 1 : 0;
                    break;
                case 'textarea':
                    $clean[$name] = wp_kses_post($value);
                    break;
                default:
                    $clean[$name] = sanitize_text_field($value);
            }
        }
        return $clean;
    }

    protected function merge_defaults($instance) {
        $defaults = [];
        foreach ($this->all_fields() as $name => $field) {
            $defaults[$name] = isset($field['default']) ? $field['default'] : '';
        }
        return wp_parse_args((array) $instance, $defaults);
    }

    protected function render_field($name, $field, $instance) {
        $id    = $this->get_field_id($name);
        $fname = $this->get_field_name($name);
        $value = isset($instance[$name]) ? $instance[$name] : '';
        $type  = isset($field['type']) ? $field['type'] : 'text';

        echo '<p>';
        if (!empty($field['label'])) {
            echo '<label for="' . esc_attr($id) . '">' . esc_html($field['label']) . '</label>';
        }

        switch ($type) {
            case 'textarea':
                echo '<textarea class="widefat" id="' . esc_attr($id) . '" name="' . esc_attr($fname) . '" rows="4">' . esc_textarea($value) . '</textarea>';
                break;
            case 'number':
                echo '<input class="widefat" type="number" id="' . esc_attr($id) . '" name="' . esc_attr($fname) . '" value="' . esc_attr($value) . '">';
                break;
            case 'select':
                $options = !empty($field['options']) ? $field['options'] : [];
                echo '<select class="widefat" id="' . esc_attr($id) . '" name="' . esc_attr($fname) . '">';
                foreach ($options as $v => $label) {
                    echo '<option value="' . esc_attr($v) . '"' . selected($value, $v, false) . '>' . esc_html($label) . '</option>';
                }
                echo '</select>';
                break;
            case 'checkbox':
                echo '<input type="checkbox" id="' . esc_attr($id) . '" name="' . esc_attr($fname) . '" value="1"' . checked($value, 1, false) . '>';
                break;
            default:
                echo '<input class="widefat" type="text" id="' . esc_attr($id) . '" name="' . esc_attr($fname) . '" value="' . esc_attr($value) . '">';
        }

        if (!empty($field['desc'])) {
            echo '<small style="color:#999;display:block;margin-top:4px;">' . wp_kses_post($field['desc']) . '</small>';
        }
        echo '</p>';
    }
}
