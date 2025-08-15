<?php
/**
 * Admin functionality for PDM Bulk Alt plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class PDM_Bulk_Alt_Admin {
    
    public function __construct() {
        // Add alt text column to media list view
        add_filter('manage_media_columns', array($this, 'add_alt_column'));
        add_action('manage_media_custom_column', array($this, 'display_alt_column'), 10, 2);
        
        // Make alt column sortable
        add_filter('manage_upload_sortable_columns', array($this, 'make_alt_column_sortable'));
        
        // Handle AJAX requests for updating alt text
        add_action('wp_ajax_pdm_update_alt_text', array($this, 'ajax_update_alt_text'));
        
        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Add quick edit functionality
        add_action('quick_edit_custom_box', array($this, 'add_quick_edit_fields'), 10, 2);
        add_action('save_post', array($this, 'save_quick_edit_data'));
    }
    
    /**
     * Add alt text column to media library
     */
    public function add_alt_column($columns) {
        // Only show in list view
        if (!$this->is_list_view()) {
            return $columns;
        }
        
        $columns['pdm_alt_text'] = __('Alt Text', 'pdm-bulk-alt');
        $columns['pdm_title'] = __('Title', 'pdm-bulk-alt');
        $columns['pdm_caption'] = __('Caption', 'pdm-bulk-alt');
        $columns['pdm_description'] = __('Description', 'pdm-bulk-alt');
        return $columns;
    }
    
    /**
     * Display alt text column content
     */
    public function display_alt_column($column_name, $post_id) {
        if (in_array($column_name, ['pdm_alt_text', 'pdm_title', 'pdm_caption', 'pdm_description'])) {
            $attachment = get_post($post_id);
            
            // Only show for image attachments
            if (wp_attachment_is_image($post_id)) {
                // Get current values
                $values = array(
                    'pdm_alt_text' => get_post_meta($post_id, '_wp_attachment_image_alt', true),
                    'pdm_title' => $attachment->post_title,
                    'pdm_caption' => $attachment->post_excerpt,
                    'pdm_description' => $attachment->post_content
                );
                
                $current_value = $values[$column_name];
                $field_labels = array(
                    'pdm_alt_text' => __('Enter alt text...', 'pdm-bulk-alt'),
                    'pdm_title' => __('Enter title...', 'pdm-bulk-alt'),
                    'pdm_caption' => __('Enter caption...', 'pdm-bulk-alt'),
                    'pdm_description' => __('Enter description...', 'pdm-bulk-alt')
                );
                
                echo '<div class="pdm-alt-wrapper">';
                
                if ($column_name === 'pdm_description') {
                    // Use textarea for description
                    echo '<textarea class="pdm-alt-input pdm-description-input" ';
                    echo 'data-attachment-id="' . esc_attr($post_id) . '" ';
                    echo 'data-field-type="' . esc_attr($column_name) . '" ';
                    echo 'placeholder="' . esc_attr($field_labels[$column_name]) . '" ';
                    echo 'rows="2">' . esc_textarea($current_value) . '</textarea>';
                } else {
                    // Use input for alt, title, caption
                    echo '<input type="text" class="pdm-alt-input" ';
                    echo 'value="' . esc_attr($current_value) . '" ';
                    echo 'data-attachment-id="' . esc_attr($post_id) . '" ';
                    echo 'data-field-type="' . esc_attr($column_name) . '" ';
                    echo 'placeholder="' . esc_attr($field_labels[$column_name]) . '">';
                }
                
                echo '<button type="button" class="pdm-save-alt button-small" ';
                echo 'data-attachment-id="' . esc_attr($post_id) . '" ';
                echo 'data-field-type="' . esc_attr($column_name) . '">';
                echo esc_html__('Save', 'pdm-bulk-alt');
                echo '</button>';
                echo '<span class="pdm-alt-status"></span>';
                
                // Add magnify icon only for alt text column
                if ($column_name === 'pdm_alt_text') {
                    $image_url = wp_get_attachment_image_url($post_id, 'large');
                    if ($image_url) {
                        echo '<div class="pdm-magnify-trigger" data-image-url="' . esc_url($image_url) . '">';
                        echo '<span class="dashicons dashicons-search" title="' . esc_attr__('Click to preview', 'pdm-bulk-alt') . '"></span>';
                        echo '</div>';
                    }
                }
                
                echo '</div>';
            } else {
                echo '<span class="pdm-not-image">' . esc_html__('Not an image', 'pdm-bulk-alt') . '</span>';
            }
        }
    }
    
    /**
     * Make alt column sortable
     */
    public function make_alt_column_sortable($columns) {
        $columns['pdm_alt_text'] = 'pdm_alt_text';
        $columns['pdm_title'] = 'pdm_title';
        $columns['pdm_caption'] = 'pdm_caption';
        $columns['pdm_description'] = 'pdm_description';
        return $columns;
    }
    
    /**
     * Handle AJAX request to update alt text
     */
    public function ajax_update_alt_text() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'pdm_bulk_alt_nonce')) {
            wp_die(__('Security check failed', 'pdm-bulk-alt'));
        }
        
        // Check user permissions
        if (!current_user_can('edit_posts')) {
            wp_die(__('Permission denied', 'pdm-bulk-alt'));
        }
        
        $attachment_id = intval($_POST['attachment_id']);
        $field_type = sanitize_text_field($_POST['field_type']);
        $field_value = sanitize_textarea_field($_POST['alt_text']); // Keep same param name for compatibility
        
        // Verify attachment exists and is an image
        if (!wp_attachment_is_image($attachment_id)) {
            wp_send_json_error(__('Invalid attachment', 'pdm-bulk-alt'));
        }
        
        $result = false;
        
        // Update based on field type
        switch ($field_type) {
            case 'pdm_alt_text':
                $result = update_post_meta($attachment_id, '_wp_attachment_image_alt', $field_value);
                break;
                
            case 'pdm_title':
                $result = wp_update_post(array(
                    'ID' => $attachment_id,
                    'post_title' => $field_value
                ));
                break;
                
            case 'pdm_caption':
                $result = wp_update_post(array(
                    'ID' => $attachment_id,
                    'post_excerpt' => $field_value
                ));
                break;
                
            case 'pdm_description':
                $result = wp_update_post(array(
                    'ID' => $attachment_id,
                    'post_content' => $field_value
                ));
                break;
                
            default:
                wp_send_json_error(__('Invalid field type', 'pdm-bulk-alt'));
        }
        
        if ($result !== false) {
            $field_names = array(
                'pdm_alt_text' => __('Alt text', 'pdm-bulk-alt'),
                'pdm_title' => __('Title', 'pdm-bulk-alt'),
                'pdm_caption' => __('Caption', 'pdm-bulk-alt'),
                'pdm_description' => __('Description', 'pdm-bulk-alt')
            );
            
            $message = sprintf(__('%s updated successfully', 'pdm-bulk-alt'), $field_names[$field_type]);
            wp_send_json_success($message);
        } else {
            wp_send_json_error(__('Failed to update field', 'pdm-bulk-alt'));
        }
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_assets($hook) {
        // Only load on media library page
        if ($hook !== 'upload.php') {
            return;
        }
        
        // Enqueue styles
        wp_enqueue_style(
            'pdm-bulk-alt-admin',
            PDM_BULK_ALT_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            PDM_BULK_ALT_VERSION
        );
        
        // Enqueue scripts
        wp_enqueue_script(
            'pdm-bulk-alt-admin',
            PDM_BULK_ALT_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            PDM_BULK_ALT_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('pdm-bulk-alt-admin', 'pdmBulkAlt', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pdm_bulk_alt_nonce'),
            'messages' => array(
                'saving' => __('Saving...', 'pdm-bulk-alt'),
                'saved' => __('Saved!', 'pdm-bulk-alt'),
                'error' => __('Error saving', 'pdm-bulk-alt')
            )
        ));
    }
    
    /**
     * Add quick edit fields
     */
    public function add_quick_edit_fields($column_name, $post_type) {
        if ($column_name === 'pdm_alt_text' && $post_type === 'attachment') {
            ?>
            <fieldset class="inline-edit-col-right">
                <div class="inline-edit-col">
                    <label>
                        <span class="title"><?php esc_html_e('Alt Text', 'pdm-bulk-alt'); ?></span>
                        <span class="input-text-wrap">
                            <input type="text" name="pdm_alt_text" class="pdm-quick-edit-alt" value="">
                        </span>
                    </label>
                </div>
            </fieldset>
            <?php
        }
    }
    
    /**
     * Save quick edit data
     */
    public function save_quick_edit_data($post_id) {
        if (isset($_POST['pdm_alt_text']) && wp_attachment_is_image($post_id)) {
            $alt_text = sanitize_text_field($_POST['pdm_alt_text']);
            update_post_meta($post_id, '_wp_attachment_image_alt', $alt_text);
        }
    }
    
    /**
     * Check if current view is list view
     */
    private function is_list_view() {
        $mode = get_user_option('media_library_mode', get_current_user_id());
        return $mode === 'list' || (isset($_GET['mode']) && $_GET['mode'] === 'list');
    }
}
