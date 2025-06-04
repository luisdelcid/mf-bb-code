<?php

if(!class_exists('MF_BB_Code')){
    class MF_BB_Code extends \__Base {

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return void
         */
        protected function loader(){
            if(!__is_plugin_active('bb-theme-builder/bb-theme-builder.php')){
                return; // Silence is golden.
            }
            add_action('add_meta_boxes', [$this, '_add_meta_boxes'], 2); // Runs after FLThemeBuilderLayoutAdminEdit::add_meta_boxes().
            add_action('admin_enqueue_scripts', [$this, '_admin_enqueue_scripts']);
            add_action('fl_builder_user_templates_admin_add_form', [$this, '_start'], 9); // Runs before FLThemeBuilderLayoutAdminAdd::render_fields().
            add_action('fl_builder_user_templates_admin_add_form', [$this, '_end'], 11); // Runs after FLThemeBuilderLayoutAdminAdd::render_fields().
            add_action('manage_fl-theme-layout_posts_custom_column', [$this, '_column_start'], 9, 2); // Runs before FLThemeBuilderLayoutAdminList::manage_column_content().
            add_action('manage_fl-theme-layout_posts_custom_column', [$this, '_column_end'], 11, 2); // Runs after FLThemeBuilderLayoutAdminList::manage_column_content().
            add_action('save_post', [$this, '_save'], 9); // Runs before FLThemeBuilderLayoutAdminEdit::save().
            add_action('template_redirect', [$this, '_template_redirect'], 9); // Runs before FLThemeBuilderLayoutRenderer::setup_part_hooks().
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return array
    	 */
        private function get_types(){
            $types = [
                'css' => [
                    'desc' => str_replace('&lt;style&gt;', 'style', __('CSS entered in the box below will be rendered within &lt;style&gt; tags.', 'fl-automator')),
                    'name' => _x('CSS Code', 'Customizer section title.', 'fl-automator'),
                ],
                'javascript' => [
                    'desc' => str_replace('&lt;script&gt;', 'script', __('JavaScript entered in the box below will be rendered within &lt;script&gt; tags.', 'fl-automator')),
                    'name' => _x('JavaScript Code', 'Customizer section title.', 'fl-automator'),
                ],
                'less' => [
                    'desc' => str_replace('CSS', 'Less', str_replace('&lt;style&gt;', 'style', __('CSS entered in the box below will be rendered within &lt;style&gt; tags.', 'fl-automator'))),
                    'name' => str_replace('CSS', 'Less', _x('CSS Code', 'Customizer section title.', 'fl-automator')),
                ],
                'head' => [
                    'desc' => str_replace('&lt;head&gt;', 'head', __('Code entered in the box below will be rendered within the page &lt;head&gt; tag.', 'fl-automator')),
                    'name' => x('Head Code', 'Customizer section title.', 'fl-automator'),
                ],
            ];
            return $types;
        }

        /**
    	 * @return bool
    	 */
        private function is_layout_supported($type = ''){
            $types = $this->get_types();
            return isset($types[$type]);
        }

        /**
    	 * @return string
    	 */
        private function replace_type($subject = '', $type = ''){
            if(!$this->is_layout_supported($type)){
                return $subject;
            }
            $types = $this->get_types();
            $search = ucwords($type);
            $replace = $types[$type]['name'];
            $subject = str_replace($search, $replace, $subject);
            return $subject;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return void
         */
        public function _add_meta_boxes(){
            global $post;
            $type = get_post_meta($post->ID, '_fl_theme_layout_type', true);
            if(!$this->is_layout_supported($type)){
                return;
            }
            remove_meta_box('fl-theme-builder-buttons', 'fl-theme-layout', 'normal');
            remove_meta_box('fl-theme-builder-settings', 'fl-theme-layout', 'normal');
            add_meta_box('fl-theme-builder-settings', __('Themer Layout Settings', 'bb-theme-builder'), [$this, '_settings_meta_box'], 'fl-theme-layout', 'normal', 'high');
            $types = $this->get_types();
            $name = $types[$type]['name'];
            $title = sprintf(_x('%s Settings', '%s stands for custom branded "Page Builder" name.', 'fl-builder'), $name);
            add_meta_box('fl-theme-builder-' . $this->str_slug('mb'), $title, [$this, '_meta_box'], 'fl-theme-layout', 'normal', 'high');
        }

        /**
         * @return void
         */
        public function _admin_enqueue_scripts($hook_suffix){
            $post_id = __is_post_edit('fl-theme-layout');
            if(!$post_id){
                return;
            }
            $type = get_post_meta($post_id, '_fl_theme_layout_type', true);
            if(!$this->is_layout_supported($type)){
                return;
            }
            $ace = __enqueue_ace();
            if(is_wp_error($ace)){
                return;
            }
            $handle = $this->str_slug();
            $file = plugin_dir_path(__FILE__) . 'extension.js';
            $l10n = [
                'is_mobile' => wp_is_mobile(),
            ];
            __local_enqueue_asset($handle, $file, [$ace], $l10n);
            __initialize($handle);
            $file = plugin_dir_path(__FILE__) . 'extension.css';
            __local_enqueue_asset($handle, $file);
        }

    	/**
    	 * @return void
    	 */
    	public function _column_end($column, $post_id){
            if('fl_type' !== $column){
                return;
            }
            $type = get_post_meta($post_id, '_fl_theme_layout_type', true);
            if(!$this->is_layout_supported($type)){
                return;
            }
            $output = ob_get_clean();
            echo $this->replace_type($output, $type);
    	}

    	/**
    	 * @return void
    	 */
    	public function _column_start($column, $post_id){
            if('fl_type' !== $column){
                return;
            }
            $type = get_post_meta($post_id, '_fl_theme_layout_type', true);
            if(!$this->is_layout_supported($type)){
                return;
            }
            ob_start();
    	}

    	/**
    	 * @return void
    	 */
    	public function _end(){
            $output = ob_get_clean();
            $html = __str_get_html($output); // Test for simple_html_dom.
            if(is_wp_error($html)){
                echo $output;
                return;
            }
            $select = $html->find('select[name^="fl-template"]', 0);
            if(is_null($select)){
                echo $output;
                return;
            }
            $select->innertext .= '<optgroup label="' . _x('Code', 'Customizer panel title.', 'fl-automator') . '">';
            $types = $this->get_types();
            foreach($types as $value => $type){
                $select->innertext .= '<option value="' . $value . '">' . $type['name'] . '</option>';
            }
            $select->innertext .= '</optgroup>';
            $output = $html->save();
            echo $output;
    	}

        /**
         * @return void
         */
        public function _meta_box(){
            global $post;
            $type = get_post_meta($post->ID, '_fl_theme_layout_type', true);
            if(!$this->is_layout_supported($type)){
                echo '<strong style="color: #a00;">' . __('Unsupported', 'bb-theme-builder') . '</strong>';
                return;
            }
            $mode = $type;
            if($mode === 'head'){
                $mode = 'html';
            }
            $types = $this->get_types();
            $setting_prefix = 'additional_' . $type;
            $setting_slug = 'additional-' . $type;
            $key = '_' . $this->str_prefix();
            $code = get_post_meta($post->ID, $key, true); ?>
            <table class="fl-mb-table widefat">
                <tr class="fl-mb-row">
                    <td  class="fl-mb-row-heading">
                        <label><?php _ex('Code', 'Customizer panel title.', 'fl-automator'); ?></label>
                        <i class="fl-mb-row-heading-help dashicons dashicons-editor-help" title="<?php esc_html_e($types[$type]['desc']); ?>"></i>
                    </td>
                    <td class="fl-mb-row-content">
                        <textarea id="<?php echo $this->str_slug(); ?>" data-mode="<?php echo $mode; ?>" name="<?php echo $this->str_prefix(); ?>" rows="12" style="width: 100%;"><?php echo $code; ?></textarea>
                    </td>
                </tr>
            </table><?php
        }

        /**
         * @return void
         */
        public function _save(){
            if(!FLBuilderUserAccess::current_user_can('theme_builder_editing')){
                return;
            }
            if(!isset($_POST['fl-theme-builder-nonce'])){
                return;
            }
            if(!wp_verify_nonce($_POST['fl-theme-builder-nonce'], 'fl-theme-builder')){
                return;
            }
            $key = '_' . $this->str_prefix();
            $post_id  = absint($_POST['post_ID']);
            $type = sanitize_text_field($_POST['fl-theme-layout-type']);
            if(!$this->is_layout_supported($type)){
                delete_post_meta($post_id, $key);
                return;
            }
            $code = trim($_POST[$this->str_prefix()]);
            update_post_meta($post_id, $key, $code);
        }

        /**
         * @return void
         */
        public function _settings_meta_box(){
            global $post;
            $type = get_post_meta($post->ID, '_fl_theme_layout_type', true);
            ob_start();
            \FLThemeBuilderLayoutAdminEdit::settings_meta_box();
            $output = ob_get_clean();
            if(!$this->is_layout_supported($type)){
                echo $output;
                return;
            }
            $html = __str_get_html($output); // Test for simple_html_dom.
            if(is_wp_error($html)){
                echo $output;
                return;
            }
            $table = $html->find('table.fl-theme-builder-settings-form', 0);
            if(is_null($table)){
                echo $output;
                return;
            }
            $row = $html->find('tr.fl-mb-row', 0);
            if(is_null($row)){
                echo $output;
                return;
            }
            $row->outertext = $this->replace_type($row->outertext, $type);
            $output = $html->save();
            echo $output;
        }

    	/**
    	 * @return void
    	 */
    	public function _start(){
            ob_start();
    	}

    	/**
    	 * @return void
    	 */
    	public function _template_redirect(){
            $css = \FLThemeBuilderLayoutData::get_current_page_layouts('css');
            $less = \FLThemeBuilderLayoutData::get_current_page_layouts('less');
            $head = \FLThemeBuilderLayoutData::get_current_page_layouts('head');
            if($css or $less or $head){
                add_action('wp_head', function() use ($css, $head, $less){
                    $code = '';
                    $key = '_' . $this->str_prefix();
                    if($css){
                        foreach($css as $part){
                            $code .= get_post_meta($part['id'], $key, true) . "\n";
                        }
                    }
                    if($less){
                        foreach($less as $part){
                            $code .= get_post_meta($part['id'], $key, true) . "\n";
                        }
                    }
                    $code = __minify_css($code);
                    echo '<style>' . $code . '</style>';
                    $code = '';
                    if($head){
                        foreach($head as $part){
                            $code .= get_post_meta($part['id'], $key, true) . "\n";
                        }
                    }
                    echo $code;
                }, 11);
            }
            $javascript = \FLThemeBuilderLayoutData::get_current_page_layouts('javascript');
            if($javascript){
                add_action('wp_print_footer_scripts', function() use ($javascript){
                    $code = '';
                    $key = '_' . $this->str_prefix();
                    foreach($javascript as $part){
                        $code .= get_post_meta($part['id'], $key, true) . "\n";
                    }
                    $code = __minify_js($code);
                    echo '<script>' . $code . '</script>';
                }, 11);
            }
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    }
}
