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
     * Add bulk attributes column to media library
     */
    public function add_alt_column($columns) {
        // Only show in list view
        if (!$this->is_list_view()) {
            return $columns;
        }
        
        $columns['pdm_bulk_attributes'] = __('Bulk Attributes', 'pdm-bulk-alt');
        return $columns;
    }
    
    /**
     * Display bulk attributes column content
     */
    public function display_alt_column($column_name, $post_id) {
        if ($column_name === 'pdm_bulk_attributes') {
            $attachment = get_post($post_id);
            
            // Only show for image attachments
            if (wp_attachment_is_image($post_id)) {
                // Get current values
                $alt_text = get_post_meta($post_id, '_wp_attachment_image_alt', true);
                $title = $attachment->post_title;
                $caption = $attachment->post_excerpt;
                
                echo '<div class="pdm-bulk-attributes-wrapper" data-attachment-id="' . esc_attr($post_id) . '">';
                
                // Magnify Icon at top
                $image_url = wp_get_attachment_image_url($post_id, 'large');
                if ($image_url) {
                    echo '<div class="pdm-magnify-row-top">';
                    echo '<div class="pdm-magnify-trigger" data-image-url="' . esc_url($image_url) . '">';
                    echo '<span class="dashicons dashicons-search" title="' . esc_attr__('Preview Image', 'pdm-bulk-alt') . '"></span>';
                    echo '</div>';
                    echo '</div>';
                }
                
                // Title Field
                echo '<div class="pdm-attribute-row">';
                echo '<label class="pdm-attribute-label">Title (optional):</label>';
                echo '<input type="text" class="pdm-attribute-input" ';
                echo 'value="' . esc_attr($title) . '" ';
                echo 'data-field-type="title" ';
                echo 'placeholder="Enter title...">';
                echo '</div>';
                
                // Alt Text Field
                echo '<div class="pdm-attribute-row">';
                echo '<label class="pdm-attribute-label">Alt Text (required):</label>';
                echo '<input type="text" class="pdm-attribute-input" ';
                echo 'value="' . esc_attr($alt_text) . '" ';
                echo 'data-field-type="alt" ';
                echo 'placeholder="Enter alt text..." required>';
                echo '</div>';
                
                // Caption Field
                echo '<div class="pdm-attribute-row">';
                echo '<label class="pdm-attribute-label">Caption (optional):</label>';
                echo '<textarea class="pdm-attribute-input pdm-textarea" ';
                echo 'data-field-type="caption" ';
                echo 'rows="3" ';
                echo 'placeholder="Enter caption...">' . esc_textarea($caption) . '</textarea>';
                echo '</div>';
                
                // Single Save Button at bottom
                echo '<div class="pdm-save-row">';
                echo '<button type="button" class="pdm-save-all-attributes button-primary">';
                echo esc_html__('Save All', 'pdm-bulk-alt');
                echo '</button>';
                echo '<span class="pdm-save-status"></span>';
                echo '</div>';
                
                echo '</div>';
            } else {
                echo '<span class="pdm-not-image">' . esc_html__('Not an image', 'pdm-bulk-alt') . '</span>';
            }
        }
    }
    
    /**
     * Make bulk attributes column sortable
     */
    public function make_alt_column_sortable($columns) {
        $columns['pdm_bulk_attributes'] = 'pdm_bulk_attributes';
        return $columns;
    }
    
    /**
     * Handle AJAX request to update all attributes
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
        
        // Verify attachment exists and is an image
        if (!wp_attachment_is_image($attachment_id)) {
            wp_send_json_error(__('Invalid attachment', 'pdm-bulk-alt'));
        }
        
        // Get all field values
        $alt_text = sanitize_textarea_field($_POST['alt_text']);
        $title = sanitize_text_field($_POST['title']);
        $caption = sanitize_textarea_field($_POST['caption']);
        
        // Validate required fields
        if (empty(trim($alt_text))) {
            wp_send_json_error(__('Alt text is required and cannot be empty', 'pdm-bulk-alt'));
        }
        
        $results = array();
        $success_count = 0;
        $error_count = 0;
        
        // Update alt text
        if (isset($_POST['alt_text'])) {
            $alt_result = update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
            if ($alt_result !== false) {
                $results['alt'] = 'success';
                $success_count++;
            } else {
                $results['alt'] = 'error';
                $error_count++;
            }
        }
        
        // Update title
        if (isset($_POST['title'])) {
            $title_result = wp_update_post(array(
                'ID' => $attachment_id,
                'post_title' => $title
            ));
            if ($title_result && !is_wp_error($title_result)) {
                $results['title'] = 'success';
                $success_count++;
            } else {
                $results['title'] = 'error';
                $error_count++;
            }
        }
        
        // Update caption
        if (isset($_POST['caption'])) {
            $caption_result = wp_update_post(array(
                'ID' => $attachment_id,
                'post_excerpt' => $caption
            ));
            if ($caption_result && !is_wp_error($caption_result)) {
                $results['caption'] = 'success';
                $success_count++;
            } else {
                $results['caption'] = 'error';
                $error_count++;
            }
        }
        
        // Prepare response message
        if ($error_count === 0) {
            $message = sprintf(__('All attributes updated successfully (%d fields)', 'pdm-bulk-alt'), $success_count);
            wp_send_json_success(array(
                'message' => $message,
                'results' => $results
            ));
        } else if ($success_count > 0) {
            $message = sprintf(__('Partially updated: %d succeeded, %d failed', 'pdm-bulk-alt'), $success_count, $error_count);
            wp_send_json_success(array(
                'message' => $message,
                'results' => $results,
                'partial' => true
            ));
        } else {
            wp_send_json_error(__('Failed to update any attributes', 'pdm-bulk-alt'));
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
        if ($column_name === 'pdm_bulk_attributes' && $post_type === 'attachment') {
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
