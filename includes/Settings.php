<?php

namespace GioCompress;

if ( ! defined( 'ABSPATH' ) ) exit;

class Settings {

    public function __construct() {
        add_action('admin_init', [ $this, 'register_settings' ]);
    }

    public function register_settings() {
        register_setting('giocompress_settings', 'giocompress_format', [
            'sanitize_callback' => function($format) {
                return in_array($format, Optimizer::get_supported_formats(), true) ? $format : 'webp';
            },
        ]);
        register_setting('giocompress_settings', 'giocompress_lazy_loading', [
            'sanitize_callback' => 'rest_sanitize_boolean',
        ]);
        register_setting('giocompress_settings', 'giocompress_auto_alt_text', [
            'sanitize_callback' => 'rest_sanitize_boolean',
        ]);
    }

    public function settings_page() {
        ?>
        <div class="wrap">
            <?php do_action('giocompress/lite_notice'); ?>
            <h1><?php esc_html_e('GioCompress Settings', 'giocompress'); ?></h1>
            <?php settings_errors('giocompress_messages'); ?>

            <form method="post" action="options.php">
                <?php
                    settings_fields('giocompress_settings');
                    do_settings_sections('giocompress_settings');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Output Format', 'giocompress'); ?></th>
                        <td>
                            <select name="giocompress_format"<?php if ( !apply_filters('giocompress/is_valid_license', false) ) : ?> disabled<?php endif; ?>>
                                <option value="webp"<?php if (get_option('giocompress_format') === 'webp') : ?> selected<?php endif; ?>>WebP</option>
                                <?php do_action('giocompress_settings_format_options'); ?>
                            </select>
                            <?php if ( !apply_filters('giocompress/is_valid_license', false) ) : ?>
                            <p class="description"><?php esc_html_e('Only WebP is available in the free version.', 'giocompress'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <?php do_action('giocompress_settings_pro'); ?>

                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Lazy Loading', 'giocompress'); ?></th>
                        <td>
                            <input type="checkbox" name="giocompress_lazy_loading" value="1" <?php checked(get_option('giocompress_lazy_loading')); ?> />
                            <p class="description"><?php esc_html_e('When enabled, adds loading="lazy" to all <img> tags in post content to improve performance by deferring image loading.', 'giocompress'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Generate Missing Alt Text', 'giocompress'); ?></th>
                        <td>
                            <input type="checkbox" name="giocompress_auto_alt_text" value="1" <?php checked(get_option('giocompress_auto_alt_text')); ?> />
                            <p class="description"><?php esc_html_e('Automatically generates descriptive alt text for images that are missing this attribute, improving SEO and accessibility.', 'giocompress'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <?php do_action('giocompress_settings_orphaned_cleanup_form'); ?>
        </div>
        <?php
    }

}