<?php
namespace GioCompress;

if ( ! defined( 'ABSPATH' ) ) exit;

class Plugin {
    protected static $instance = null;

    public $db;
    public $settings;
    public $optimizer;
    public $retro_optimizer;
    public $reports;
    public $license;
    public $seo;
    public $content_urls;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->db = new DB();
        $this->db->maybe_upgrade();
        $this->settings = new Settings();
        $this->optimizer = new Optimizer();
        $this->retro_optimizer = new RetroOptimizer();
        $this->reports = new Reports();
        $this->license = new License();
        $this->seo = new SEO();
        $this->content_urls = new ContentUrls();

        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_menu', [ $this, 'remove_main_submenu' ], 99 );
    }

    public static function enqueue_assets() {
        wp_enqueue_style(
            'giocompress-admin-style',
            plugin_dir_url(__FILE__) . '../assets/css/admin.css',
            [],
            filemtime(plugin_dir_path(__FILE__) . '../assets/css/admin.css')
        );
    }

    public function add_menu() {
        add_menu_page(
            __('GioCompress Settings', 'giocompress'), // Page title
            'GioCompress',                             // Menu title
            'manage_options',                          // Capability
            'giocompress',                             // Menu slug
            '',                                        // No callback
            'dashicons-format-image',                  // Icon (dashicon)
            60                                         // Position
        );

        // Submenu: Settings
        add_submenu_page(
            'giocompress',
            __('Settings', 'giocompress'),
            __('Settings', 'giocompress'),
            'manage_options',
            'giocompress-settings',
            [ $this->settings, 'settings_page' ]
        );

        // Submenu: License
        add_submenu_page(
            'giocompress',
            __('License', 'giocompress'),
            __('License', 'giocompress'),
            'manage_options',
            'giocompress-license',
            [ $this->license, 'license_page' ]
        );

        // Submenu: Reports
        add_submenu_page(
            'giocompress',
            __('Optimization Report', 'giocompress'),
            __('Report', 'giocompress'),
            'manage_options',
            'giocompress-report',
            [ $this->reports, 'report_page' ]
        );

        // Submenu: RetroOptimizer
        add_submenu_page(
            'giocompress',
            __('Optimize Existing Images', 'giocompress'),
            __('RetroOptimize', 'giocompress'),
            'manage_options',
            'giocompress-optimize',
            [ $this->retro_optimizer, 'optimize_page' ]
        );

    }

    static public function remove_main_submenu() {
        remove_submenu_page( 'giocompress', 'giocompress' );
    }
}
