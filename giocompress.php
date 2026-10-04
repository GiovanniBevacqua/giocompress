<?php
/**
 * Plugin Name:       GioCompress
 * Plugin URI:        https://giosuite.com/giocompress
 * Description:       Automatically convert and optimize your images to WebP for a faster website. Upgrade to GioCompress Pro for more formats, advanced features, and unlimited optimizations.
 * Version:           1.4.0
 * Requires at least: 6.2
 * Tested up to: 7.1
 * Requires PHP:      7.4
 * Author:            Giovanni Bevacqua
 * Author URI:        https://www.linkedin.com/in/giovanni-bevacqua/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       giocompress
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Constants
define( 'GIOCOMPRESS_PLUGIN_FILE', __FILE__ );
define( 'GIOCOMPRESS_VERSION', '1.4.0' );
define( 'GIOCOMPRESS_DIR', plugin_dir_path( __FILE__ ) );
define( 'GIOCOMPRESS_URL', plugin_dir_url( __FILE__ ) );
define( 'GIOCOMPRESS_SITE_URL', 'https://giosuite.com/giocompress' );

// Autoload
spl_autoload_register(function($class) {
    $base = 'GioCompress\\';
    $len = strlen($base);
    if (strncmp($base, $class, $len) !== 0) return;

    $relative_class = substr($class, $len);
    $file = GIOCOMPRESS_DIR . 'includes/' . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// Default options
register_activation_hook(__FILE__, function () {
    if (get_option('giocompress_format') === false) {
        add_option('giocompress_format', 'webp');
    }

    // Pro settings: stored here so they already have a value when Pro is activated.
    if (get_option('giocompress_quality') === false) {
        add_option('giocompress_quality', GioCompress\Optimizer::DEFAULT_QUALITY);
    }

    if (get_option('giocompress_max_width') === false) {
        add_option('giocompress_max_width', 1200);
    }

    if (get_option('giocompress_preserve_original') === false) {
        add_option('giocompress_preserve_original', 0); // 0 = false
    }

    if (get_option('giocompress_lazy_loading') === false) {
        add_option('giocompress_lazy_loading', 0); // 0 = false
    }

    if (get_option('giocompress_auto_alt_text') === false) {
        add_option('giocompress_auto_alt_text', 0); // 0 = false
    }

    if (get_option('giocompress_api_key') === false) {
        add_option('giocompress_api_key', '');
    }
    $db_manager = new GioCompress\DB();
    $db_manager->create_table();
});

// Init plugin
add_action( 'init', function() {
    GioCompress\Plugin::instance();
});
