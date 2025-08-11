<?php
/**
 * Scanner functionality for PDM Bulk Alt plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class PDM_Bulk_Alt_Scanner {
    
    public function __construct() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Handle AJAX requests
        add_action('wp_ajax_pdm_scan_content', array($this, 'ajax_scan_content'));
        add_action('wp_ajax_pdm_update_images', array($this, 'ajax_update_images'));
        
        // Enqueue scanner assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scanner_assets'));
    }
    
    /**
     * Add admin menu under Media
     */
    public function add_admin_menu() {
        add_media_page(
            __('Set Alt Tags', 'pdm-bulk-alt'),
            __('Set Alt Tags', 'pdm-bulk-alt'),
            'manage_options',
            'pdm-set-alt-tags',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Display admin page
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Set Alt Tags', 'pdm-bulk-alt'); ?></h1>
            <p><?php esc_html_e('Scan your website and automatically sync all alt tags with values from your media library. This will update existing alt tags and add missing ones to ensure consistency across your site.', 'pdm-bulk-alt'); ?></p>
            
            <div class="pdm-scanner-container">
                <div class="pdm-scanner-controls">
                    <button id="pdm-start-scan" class="button button-primary button-large">
                        <?php esc_html_e('Start Scanning', 'pdm-bulk-alt'); ?>
                    </button>
                    <button id="pdm-stop-scan" class="button button-secondary" style="display: none;">
                        <?php esc_html_e('Stop Scanning', 'pdm-bulk-alt'); ?>
                    </button>
                </div>
                
                <div class="pdm-progress-container" style="display: none;">
                    <div class="pdm-progress-info">
                        <span id="pdm-progress-text"><?php esc_html_e('Preparing scan...', 'pdm-bulk-alt'); ?></span>
                        <span id="pdm-progress-percent">0%</span>
                    </div>
                    <div class="pdm-progress-bar">
                        <div class="pdm-progress-fill"></div>
                    </div>
                    <div class="pdm-scan-stats">
                        <span id="pdm-stats-scanned">0</span> <?php esc_html_e('scanned', 'pdm-bulk-alt'); ?> |
                        <span id="pdm-stats-found">0</span> <?php esc_html_e('images found', 'pdm-bulk-alt'); ?> |
                        <span id="pdm-stats-updated">0</span> <?php esc_html_e('alt tags added', 'pdm-bulk-alt'); ?>
                    </div>
                </div>
                
                <div id="pdm-scan-results" class="pdm-results-container" style="display: none;">
                    <h2><?php esc_html_e('Scan Results', 'pdm-bulk-alt'); ?></h2>
                    <div id="pdm-results-table"></div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Scan content for images
     */
    public function ajax_scan_content() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'pdm_scanner_nonce')) {
            wp_die(__('Security check failed', 'pdm-bulk-alt'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied', 'pdm-bulk-alt'));
        }
        
        $batch_size = 5; // Smaller batches for better performance
        $offset = intval($_POST['offset']) ?: 0;
        
        // Clear previous scan data on first run
        if ($offset === 0) {
            delete_option('pdm_bulk_alt_scan_results');
        }
        
        // Get all post types including custom ones
        $post_types = get_post_types(array('public' => true), 'names');
        $post_types = array_merge($post_types, array('page', 'post', 'attachment'));
        $post_types = array_unique($post_types);
        
        // Get posts using WP_Query for better control
        $query_args = array(
            'post_type' => $post_types,
            'post_status' => array('publish', 'private'),
            'posts_per_page' => $batch_size,
            'offset' => $offset,
            'orderby' => 'ID',
            'order' => 'ASC',
            'no_found_rows' => false
        );
        
        $query = new WP_Query($query_args);
        $posts = $query->posts;
        
        $results = array();
        $images_found = 0;
        $images_updated = 0;
        
        foreach ($posts as $post) {
            $post_content = $post->post_content;
            
            // Check if Divi theme is active
            $is_divi_theme = $this->is_divi_theme_active();
            $is_divi_post = get_post_meta($post->ID, '_et_pb_use_builder', true) === 'on';
            
            // Get content from page builders and meta fields
            $meta_content = $this->get_page_builder_content($post->ID);
            $full_content = $post_content . ' ' . $meta_content;
            
            $post_images = array();
            $updated_content = $post_content;
            $content_changed = false;
            
            // Handle Divi shortcodes if Divi theme is active
            if ($is_divi_theme && $is_divi_post) {
                error_log("PDM Debug: Processing Divi post ID: " . $post->ID);
                $divi_results = $this->process_divi_shortcodes($post_content, $post->ID);
                if ($divi_results['content_changed']) {
                    $updated_content = $divi_results['updated_content'];
                    $content_changed = true;
                    $post_images = array_merge($post_images, $divi_results['images']);
                    $images_found += count($divi_results['images']);
                    $images_updated += count(array_filter($divi_results['images'], function($img) { return $img['success']; }));
                }
            }
            
            // Also process regular HTML img tags
            preg_match_all('/<img[^>]*>/i', $full_content, $matches);
            
            if (!empty($matches[0])) {
                foreach ($matches[0] as $img_tag) {
                    $image_data = $this->parse_image_tag($img_tag);
                    
                    if ($image_data) {
                        // Debug: Let's check what we're finding
                        error_log("PDM Debug: Post ID: " . $post->ID . " (" . $post->post_type . ") - HTML Image found - src: " . $image_data['src'] . ", has_alt: " . ($image_data['has_alt'] ? 'true' : 'false') . ", alt_value: '" . $image_data['alt_value'] . "'");
                        
                        // Always process images to sync with media library (removed the !$image_data['has_alt'] condition)
                        $images_found++;
                        
                        // Try to get attachment ID and alt text
                        $attachment_id = $this->get_attachment_id_from_url($image_data['src']);
                        
                        if ($attachment_id) {
                            $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
                            error_log("PDM Debug: Found attachment ID: " . $attachment_id . ", alt text: '" . $alt_text . "'");
                            
                            // Only update if media library has alt text and it's different from current
                            if (!empty($alt_text) && $alt_text !== $image_data['alt_value']) {
                                // Update the image tag in content
                                $new_img_tag = $this->add_alt_to_image_tag($img_tag, $alt_text);
                                
                                if ($new_img_tag !== $img_tag) {
                                    $updated_content = str_replace($img_tag, $new_img_tag, $updated_content);
                                    $content_changed = true;
                                    $images_updated++;
                                    
                                    error_log("PDM Debug: Updated HTML image tag successfully");
                                    
                                    $post_images[] = array(
                                        'src' => $image_data['src'],
                                        'alt_added' => $alt_text,
                                        'success' => true,
                                        'original_alt' => $image_data['alt_value'],
                                        'type' => 'html'
                                    );
                                } else {
                                    error_log("PDM Debug: Failed to update HTML image tag");
                                    $post_images[] = array(
                                        'src' => $image_data['src'],
                                        'alt_added' => '',
                                        'success' => false,
                                        'reason' => __('Could not update image tag', 'pdm-bulk-alt'),
                                        'original_alt' => $image_data['alt_value'],
                                        'type' => 'html'
                                    );
                                }
                            } else if (empty($alt_text)) {
                                error_log("PDM Debug: No alt text in media library for attachment ID: " . $attachment_id);
                                $post_images[] = array(
                                    'src' => $image_data['src'],
                                    'alt_added' => '',
                                    'success' => false,
                                    'reason' => __('No alt text in media library', 'pdm-bulk-alt'),
                                    'original_alt' => $image_data['alt_value'],
                                    'type' => 'html'
                                );
                            } else {
                                // Alt text matches - no update needed
                                error_log("PDM Debug: Alt text already matches media library");
                                $post_images[] = array(
                                    'src' => $image_data['src'],
                                    'alt_added' => $alt_text,
                                    'success' => true,
                                    'original_alt' => $image_data['alt_value'],
                                    'type' => 'html',
                                    'already_synced' => true
                                );
                            }
                        } else {
                            error_log("PDM Debug: Could not find attachment ID for: " . $image_data['src']);
                            $post_images[] = array(
                                'src' => $image_data['src'],
                                'alt_added' => '',
                                'success' => false,
                                'reason' => __('Image not found in media library', 'pdm-bulk-alt'),
                                'original_alt' => $image_data['alt_value'],
                                'type' => 'html'
                            );
                        }
                    }
                }
            }
            
            // Update post content if changes were made
            if ($content_changed && !empty($post_images)) {
                $update_result = wp_update_post(array(
                    'ID' => $post->ID,
                    'post_content' => $updated_content
                ));
                
                if ($is_divi_post && $update_result) {
                    // For Divi, also clear the cache
                    if (function_exists('et_core_page_resource_remove_all')) {
                        et_core_page_resource_remove_all($post->ID, 'all');
                    }
                    
                    // Update Divi's old content backup as well
                    update_post_meta($post->ID, '_et_pb_old_content', $updated_content);
                    
                    // Clear Divi static CSS cache
                    if (class_exists('ET_Core_PageResource')) {
                        ET_Core_PageResource::remove_static_resources('all', $post->ID);
                    }
                    
                    // Trigger Divi cache clearing hooks
                    do_action('et_builder_cache_purge_request', $post->ID);
                }
                
                error_log("PDM Debug: Post content updated for post ID: " . $post->ID . ", Divi: " . ($is_divi_post ? 'yes' : 'no'));
                
                $results[] = array(
                    'post_id' => $post->ID,
                    'post_title' => $post->post_title,
                    'post_type' => $post->post_type,
                    'post_url' => get_permalink($post->ID),
                    'images' => $post_images,
                    'updated_count' => count(array_filter($post_images, function($img) { return $img['success']; })),
                    'is_divi' => $is_divi_post
                );
            }
        }
        
        // Get total count for progress
        $total_query = new WP_Query(array(
            'post_type' => $post_types,
            'post_status' => array('publish', 'private'),
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
        $total_count = $total_query->found_posts;
        
        wp_send_json_success(array(
            'results' => $results,
            'images_found' => $images_found,
            'images_updated' => $images_updated,
            'processed' => count($posts),
            'offset' => $offset + $batch_size,
            'total' => $total_count,
            'has_more' => count($posts) === $batch_size
        ));
    }
    
    /**
     * Add alt attribute to image tag
     */
    private function add_alt_to_image_tag($img_tag, $alt_text) {
        // Escape the alt text for HTML attribute
        $escaped_alt = esc_attr($alt_text);
        
        // Check if alt attribute already exists (even if empty)
        if (preg_match('/alt\s*=\s*["\'][^"\']*["\']?/i', $img_tag)) {
            // Replace existing alt attribute (including empty ones)
            $new_tag = preg_replace('/alt\s*=\s*["\'][^"\']*["\']?/i', 'alt="' . $escaped_alt . '"', $img_tag);
        } else {
            // Add alt attribute - try to place it after src for better readability
            if (preg_match('/<img([^>]*)(src\s*=\s*["\'][^"\']+["\']?)([^>]*>)/i', $img_tag, $matches)) {
                $new_tag = '<img' . $matches[1] . $matches[2] . ' alt="' . $escaped_alt . '"' . $matches[3];
            } else {
                // Fallback: add right after <img
                $new_tag = str_replace('<img', '<img alt="' . $escaped_alt . '"', $img_tag);
            }
        }
        
        return $new_tag;
    }
    
    /**
     * Update images with alt tags - kept for backward compatibility but not used
     */
    public function ajax_update_images() {
        wp_send_json_success(array(
            'message' => 'This function is no longer needed as updates happen during scanning'
        ));
    }
    
    /**
     * Get content from page builders
     */
    private function get_page_builder_content($post_id) {
        $content = '';
        
        // Elementor
        $elementor_data = get_post_meta($post_id, '_elementor_data', true);
        if ($elementor_data) {
            if (is_string($elementor_data)) {
                $content .= ' ' . $elementor_data;
            } else {
                $content .= ' ' . maybe_serialize($elementor_data);
            }
        }
        
        // Divi Builder - Multiple approaches for different Divi versions
        $divi_enabled = get_post_meta($post_id, '_et_pb_use_builder', true);
        if ($divi_enabled === 'on') {
            // Method 1: Old content backup
            $divi_content = get_post_meta($post_id, '_et_pb_old_content', true);
            if ($divi_content) {
                $content .= ' ' . $divi_content;
            }
            
            // Method 2: Built content (current Divi)
            $divi_built_content = get_post_meta($post_id, '_et_pb_built_for_post_type', true);
            if ($divi_built_content) {
                // Get the actual post content as Divi stores it there
                $post_content = get_post_field('post_content', $post_id);
                $content .= ' ' . $post_content;
            }
        }
        
        // Divi Theme Builder
        $divi_layout_id = get_post_meta($post_id, '_et_pb_page_layout', true);
        if ($divi_layout_id) {
            $layout_content = get_post_field('post_content', $divi_layout_id);
            if ($layout_content) {
                $content .= ' ' . $layout_content;
            }
        }
        
        // Beaver Builder
        $bb_enabled = get_post_meta($post_id, '_fl_builder_enabled', true);
        if ($bb_enabled) {
            $bb_data = get_post_meta($post_id, '_fl_builder_data', true);
            if ($bb_data) {
                $content .= ' ' . maybe_serialize($bb_data);
            }
        }
        
        // WPBakery (Visual Composer)
        $wpb_content = get_post_meta($post_id, '_wpb_shortcodes_custom_css', true);
        if ($wpb_content) {
            $content .= ' ' . $wpb_content;
        }
        
        // Oxygen Builder
        $oxygen_content = get_post_meta($post_id, 'ct_builder_shortcodes', true);
        if ($oxygen_content) {
            $content .= ' ' . $oxygen_content;
        }
        
        // Thrive Architect
        $thrive_content = get_post_meta($post_id, 'tve_updated_post', true);
        if ($thrive_content) {
            $content .= ' ' . $thrive_content;
        }
        
        // Gutenberg blocks (extract from post content)
        if (has_blocks($post_id)) {
            $post_content = get_post_field('post_content', $post_id);
            $blocks = parse_blocks($post_content);
            foreach ($blocks as $block) {
                if (isset($block['innerHTML'])) {
                    $content .= ' ' . $block['innerHTML'];
                }
                if (isset($block['attrs']) && is_array($block['attrs'])) {
                    $content .= ' ' . maybe_serialize($block['attrs']);
                }
            }
        }
        
        // Check for any other meta fields that might contain serialized content with images
        $all_meta = get_post_meta($post_id);
        foreach ($all_meta as $key => $values) {
            // Skip known fields we've already checked
            if (in_array($key, array('_elementor_data', '_et_pb_use_builder', '_et_pb_old_content', '_fl_builder_enabled', '_fl_builder_data', '_wpb_shortcodes_custom_css', 'ct_builder_shortcodes', 'tve_updated_post'))) {
                continue;
            }
            
            foreach ($values as $value) {
                // Look for serialized data or shortcodes that might contain images
                if (is_string($value) && (strpos($value, '<img') !== false || strpos($value, '[') !== false)) {
                    $content .= ' ' . $value;
                }
            }
        }
        
        return $content;
    }
    
    /**
     * Parse image tag and extract information
     */
    private function parse_image_tag($img_tag) {
        // Extract src
        preg_match('/src\s*=\s*["\']([^"\']+)["\']/i', $img_tag, $src_matches);
        
        if (!isset($src_matches[1])) {
            return false;
        }
        
        // Check if alt attribute exists and get its value
        $has_meaningful_alt = false;
        $alt_value = '';
        
        if (preg_match('/alt\s*=\s*["\']([^"\']*)["\']?/i', $img_tag, $alt_matches)) {
            $alt_value = trim($alt_matches[1]);
            // Only consider it as having meaningful alt if it's not empty
            $has_meaningful_alt = !empty($alt_value);
        }
        
        // We want to update images that either:
        // 1. Have no alt attribute at all
        // 2. Have an empty alt attribute (alt="" or alt='')
        
        return array(
            'src' => $src_matches[1],
            'has_alt' => $has_meaningful_alt, // This will be false for empty alt tags
            'alt_value' => $alt_value,
            'original_tag' => $img_tag
        );
    }
    
    /**
     * Get attachment ID from image URL
     */
    private function get_attachment_id_from_url($url) {
        // Clean the URL
        $url = trim($url);
        
        // Remove protocol and domain variations
        $upload_dir = wp_upload_dir();
        $base_url = $upload_dir['baseurl'];
        
        // Try with current domain
        $attachment_id = attachment_url_to_postid($url);
        
        if (!$attachment_id) {
            // Remove domain from URL and try relative path
            $relative_url = str_replace(array(home_url(), site_url(), $base_url), '', $url);
            $relative_url = ltrim($relative_url, '/');
            
            // Try with upload base URL
            $full_url = $base_url . '/' . $relative_url;
            $attachment_id = attachment_url_to_postid($full_url);
        }
        
        if (!$attachment_id) {
            // Try to handle resized images (e.g., image-150x150.jpg)
            $url_parts = pathinfo($url);
            if (isset($url_parts['filename']) && preg_match('/-\d+x\d+$/', $url_parts['filename'])) {
                $original_filename = preg_replace('/-\d+x\d+$/', '', $url_parts['filename']);
                $original_url = $url_parts['dirname'] . '/' . $original_filename . '.' . $url_parts['extension'];
                $attachment_id = attachment_url_to_postid($original_url);
                
                // If still not found, try with base URL
                if (!$attachment_id) {
                    $relative_original = str_replace(array(home_url(), site_url(), $base_url), '', $original_url);
                    $relative_original = ltrim($relative_original, '/');
                    $full_original_url = $base_url . '/' . $relative_original;
                    $attachment_id = attachment_url_to_postid($full_original_url);
                }
            }
        }
        
        if (!$attachment_id) {
            // Last resort: search by filename
            $filename = basename($url);
            global $wpdb;
            
            $attachment = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s",
                '%' . $wpdb->esc_like($filename)
            ));
            
            if ($attachment) {
                $attachment_id = $attachment;
            }
        }
        
        return $attachment_id;
    }
    
    /**
     * Check if Divi theme is active
     */
    private function is_divi_theme_active() {
        $theme = wp_get_theme();
        $parent_theme = $theme->get('Template');
        $current_theme = $theme->get_stylesheet();
        
        // Check if current theme or parent theme is Divi
        return (
            $current_theme === 'Divi' || 
            $parent_theme === 'Divi' || 
            strpos($current_theme, 'divi') !== false || 
            strpos($parent_theme, 'divi') !== false ||
            function_exists('et_setup_theme') ||
            defined('ET_BUILDER_VERSION')
        );
    }
    
    /**
     * Process Divi shortcodes and update alt tags
     */
    private function process_divi_shortcodes($content, $post_id) {
        $updated_content = $content;
        $content_changed = false;
        $images = array();
        
        // Find all et_pb_image shortcodes
        preg_match_all('/\[et_pb_image[^\]]*\]/i', $content, $matches);
        
        if (!empty($matches[0])) {
            foreach ($matches[0] as $shortcode) {
                error_log("PDM Debug: Found Divi shortcode: " . $shortcode);
                
                // Parse shortcode attributes
                $shortcode_data = $this->parse_divi_image_shortcode($shortcode);
                
                if ($shortcode_data && !empty($shortcode_data['src'])) {
                    // Always process images to sync with media library (removed the needs_alt condition)
                    error_log("PDM Debug: Divi image - src: " . $shortcode_data['src'] . ", current alt: '" . $shortcode_data['alt'] . "'");
                    
                    // Try to get attachment ID and alt text
                    $attachment_id = $this->get_attachment_id_from_url($shortcode_data['src']);
                    
                    if ($attachment_id) {
                        $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
                        
                        error_log("PDM Debug: Divi - Found attachment ID: " . $attachment_id . ", alt text: '" . $alt_text . "'");
                        
                        // Only update if media library has alt text and it's different from current
                        if (!empty($alt_text) && $alt_text !== $shortcode_data['alt']) {
                            // Update the shortcode with alt text
                            $new_shortcode = $this->add_alt_to_divi_shortcode($shortcode, $alt_text);
                            
                            if ($new_shortcode !== $shortcode) {
                                $updated_content = str_replace($shortcode, $new_shortcode, $updated_content);
                                $content_changed = true;
                                
                                error_log("PDM Debug: Updated Divi shortcode successfully");
                                
                                $images[] = array(
                                    'src' => $shortcode_data['src'],
                                    'alt_added' => $alt_text,
                                    'success' => true,
                                    'original_alt' => $shortcode_data['alt'],
                                    'type' => 'divi'
                                );
                            } else {
                                error_log("PDM Debug: Failed to update Divi shortcode");
                                $images[] = array(
                                    'src' => $shortcode_data['src'],
                                    'alt_added' => '',
                                    'success' => false,
                                    'reason' => __('Could not update Divi shortcode', 'pdm-bulk-alt'),
                                    'original_alt' => $shortcode_data['alt'],
                                    'type' => 'divi'
                                );
                            }
                        } else if (empty($alt_text)) {
                            error_log("PDM Debug: No alt text in media library for Divi image");
                            $images[] = array(
                                'src' => $shortcode_data['src'],
                                'alt_added' => '',
                                'success' => false,
                                'reason' => __('No alt text in media library', 'pdm-bulk-alt'),
                                'original_alt' => $shortcode_data['alt'],
                                'type' => 'divi'
                            );
                        } else {
                            // Alt text matches - no update needed
                            error_log("PDM Debug: Divi alt text already matches media library");
                            $images[] = array(
                                'src' => $shortcode_data['src'],
                                'alt_added' => $alt_text,
                                'success' => true,
                                'original_alt' => $shortcode_data['alt'],
                                'type' => 'divi',
                                'already_synced' => true
                            );
                        }
                    } else {
                        error_log("PDM Debug: Could not find attachment ID for Divi image: " . $shortcode_data['src']);
                        $images[] = array(
                            'src' => $shortcode_data['src'],
                            'alt_added' => '',
                            'success' => false,
                            'reason' => __('Image not found in media library', 'pdm-bulk-alt'),
                            'original_alt' => $shortcode_data['alt'],
                            'type' => 'divi'
                        );
                    }
                }
            }
        }
        
        return array(
            'updated_content' => $updated_content,
            'content_changed' => $content_changed,
            'images' => $images
        );
    }
    
    /**
     * Parse Divi image shortcode attributes
     */
    private function parse_divi_image_shortcode($shortcode) {
        $data = array(
            'src' => '',
            'alt' => ''
        );
        
        // Extract src attribute
        if (preg_match('/src=["\']([^"\']+)["\']?/i', $shortcode, $src_matches)) {
            $data['src'] = $src_matches[1];
        }
        
        // Extract alt attribute
        if (preg_match('/alt=["\']([^"\']*)["\']?/i', $shortcode, $alt_matches)) {
            $data['alt'] = $alt_matches[1];
        }
        
        return $data;
    }
    
    /**
     * Add alt attribute to Divi shortcode
     */
    private function add_alt_to_divi_shortcode($shortcode, $alt_text) {
        $escaped_alt = esc_attr($alt_text);
        
        // Check if alt attribute already exists
        if (preg_match('/alt=["\'][^"\']*["\']?/i', $shortcode)) {
            // Replace existing alt attribute
            $new_shortcode = preg_replace('/alt=["\'][^"\']*["\']?/i', 'alt="' . $escaped_alt . '"', $shortcode);
        } else {
            // Add alt attribute - place it after src for better readability
            if (preg_match('/(src=["\'][^"\']+["\']?)/', $shortcode, $matches)) {
                $new_shortcode = str_replace($matches[1], $matches[1] . ' alt="' . $escaped_alt . '"', $shortcode);
            } else {
                // Fallback: add before closing bracket
                $new_shortcode = str_replace(']', ' alt="' . $escaped_alt . '"]', $shortcode);
            }
        }
        
        return $new_shortcode;
    }
    
    /**
     * Enqueue scanner assets
     */
    public function enqueue_scanner_assets($hook) {
        if ($hook !== 'media_page_pdm-set-alt-tags') {
            return;
        }
        
        wp_enqueue_style(
            'pdm-scanner-admin',
            PDM_BULK_ALT_PLUGIN_URL . 'assets/css/scanner.css',
            array(),
            PDM_BULK_ALT_VERSION
        );
        
        wp_enqueue_script(
            'pdm-scanner-admin',
            PDM_BULK_ALT_PLUGIN_URL . 'assets/js/scanner.js',
            array('jquery'),
            PDM_BULK_ALT_VERSION,
            true
        );
        
        wp_localize_script('pdm-scanner-admin', 'pdmScanner', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pdm_scanner_nonce'),
            'messages' => array(
                'scanning' => __('Scanning...', 'pdm-bulk-alt'),
                'updating' => __('Updating images...', 'pdm-bulk-alt'),
                'completed' => __('Scan completed!', 'pdm-bulk-alt'),
                'stopped' => __('Scan stopped', 'pdm-bulk-alt'),
                'error' => __('An error occurred', 'pdm-bulk-alt')
            )
        ));
    }
}
