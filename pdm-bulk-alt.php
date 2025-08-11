<?php
/**
 * Plugin Name: PDM Bulk Alt
 * Description: Streamline your website's accessibility and SEO with powerful alt tag management. Edit alt tags directly from the media library list view and automatically scan your entire website to set missing alt tags from media library values. Supports all themes, page builders (Divi, Elementor, etc.), and post types with intelligent detection and bulk updates.
 * Version: 1.0.0
 * Author: Omar Faruk and PDM Team
 * Author URI: https://www.purelydigitalmarketing.com/
 * Text Domain: pdm-bulk-alt
 * Requires at least: 5.0
 * Tested up to: 6.3
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PDM_BULK_ALT_VERSION', '1.0.0');
define('PDM_BULK_ALT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PDM_BULK_ALT_PLUGIN_URL', plugin_dir_url(__FILE__));

// Main plugin class
class PDM_Bulk_Alt {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('pdm-bulk-alt', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Include required files
        $this->include_files();
        
        // Initialize components
        if (is_admin()) {
            new PDM_Bulk_Alt_Admin();
            new PDM_Bulk_Alt_Scanner();
        }
    }
    
    private function include_files() {
        require_once PDM_BULK_ALT_PLUGIN_DIR . 'includes/class-admin.php';
        require_once PDM_BULK_ALT_PLUGIN_DIR . 'includes/class-scanner.php';
    }
}

// Initialize the plugin
new PDM_Bulk_Alt();
