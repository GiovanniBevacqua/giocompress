<?php

namespace GioCompress;

if ( ! defined( 'ABSPATH' ) ) exit;

class License {

    public function __construct() {
        add_action('admin_init', [ $this, 'register_settings' ]);
        add_filter('giocompress/is_valid_license', [ $this, 'is_valid' ]);
        add_action('giocompress/lite_notice', [ $this, 'lite_notice' ]);
        add_action('giocompress/api_key_invalid_notice', [ $this, 'api_key_invalid_notice' ]);
    }
    
    public function register_settings() {
        register_setting('giocompress_license', 'giocompress_api_key', [
            'sanitize_callback' => 'sanitize_text_field'
        ]);
    }

    public function license_page() {
        ?>
        <div class="wrap">
            <?php do_action('giocompress/lite_notice'); ?>
            <?php do_action('giocompress/api_key_invalid_notice'); ?>
            <h1><?php esc_html_e('GioCompress License', 'giocompress'); ?></h1>
            <form method="post" action="options.php">
                <?php
                    settings_fields('giocompress_license');
                    do_settings_sections('giocompress_license');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('API Key', 'giocompress'); ?></th>
                        <td>
                            <input type="text" name="giocompress_api_key" class="regular-text" value="<?php echo esc_attr(get_option('giocompress_api_key')); ?>" />
                            <p class="description">
                                <?php if ( !apply_filters('giocompress/is_valid_license', false) ) :
                                    esc_html_e('Enter your API key to unlock Pro features.', 'giocompress');
                                    else : 
                                    esc_html_e('Your API key.', 'giocompress');
                                    endif; ?>
                            </p>
                            <?php do_action('giocompress/license_description'); ?>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <?php if ( !apply_filters('giocompress/is_valid_license', false) ) : ?>
            <div class="giocompress-table-wrapper">
                <table class="giocompress-comparison">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Feature', 'giocompress'); ?></th>
                            <th><?php esc_html_e('Free', 'giocompress'); ?></th>
                            <th><?php esc_html_e('Pro', 'giocompress'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php esc_html_e('Automatic compression on upload', 'giocompress'); ?></td>
                            <td class="giocompress-check">✔️</td>
                            <td class="giocompress-check">✔️</td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Conversion to WebP', 'giocompress'); ?></td>
                            <td class="giocompress-check">✔️</td>
                            <td class="giocompress-check">✔️</td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Optimization of scaled images', 'giocompress'); ?></td>
                            <td class="giocompress-check">✔️</td>
                            <td class="giocompress-check">✔️</td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Delete original file', 'giocompress'); ?></td>
                            <td class="giocompress-check">✔️</td>
                            <td class="giocompress-check">✔️</td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Preserve original file', 'giocompress'); ?></td>
                            <td class="giocompress-cross">❌</td>
                            <td class="giocompress-check">✔️</td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Optimize existing images (5/day)', 'giocompress'); ?></td>
                            <td class="giocompress-check">✔️</td>
                            <td class="giocompress-check">✔️ <?php esc_html_e('(unlimited)', 'giocompress'); ?></td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Customizable quality', 'giocompress'); ?></td>
                            <td class="giocompress-cross">❌</td>
                            <td class="giocompress-check">✔️</td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Automatic resizing of large images', 'giocompress'); ?></td>
                            <td class="giocompress-cross">❌</td>
                            <td class="giocompress-check">✔️</td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('AVIF format support', 'giocompress'); ?></td>
                            <td class="giocompress-cross">❌</td>
                            <td class="giocompress-check">✔️</td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Remove EXIF/IPTC data (with Imagick if installed on server)', 'giocompress'); ?></td>
                            <td class="giocompress-cross">❌</td>
                            <td class="giocompress-check">✔️</td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Statistics and space saved', 'giocompress'); ?></td>
                            <td class="giocompress-cross">❌</td>
                            <td class="giocompress-check">✔️</td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Orphan data cleanup', 'giocompress'); ?></td>
                            <td class="giocompress-cross">❌</td>
                            <td class="giocompress-check">✔️</td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Lazy loading', 'giocompress'); ?> (SEO)</td>
                            <td class="giocompress-check">✔️</td>
                            <td class="giocompress-check">✔️</td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Generate missing alt text', 'giocompress'); ?> (SEO)</td>
                            <td class="giocompress-check">✔️</td>
                            <td class="giocompress-check">✔️</td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Email support', 'giocompress'); ?></td>
                            <td class="giocompress-cross">❌</td>
                            <td class="giocompress-check">✔️</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="submit">
                <a href="<?php echo esc_url(GIOCOMPRESS_SITE_URL); ?>" target="_blank" class="button button-primary"><?php esc_html_e('Learn more about GioCompress Pro', 'giocompress'); ?></a>
            </p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function is_valid(): bool {
        return false;
    }

    public function lite_notice() {
        if ( apply_filters('giocompress/is_valid_license', false) ) :
            return;
        endif;
        ?>
        <div class="notice notice-warning update-nag inline">
            <p><strong><?php esc_html_e( 'You\'re currently using GioCompress Lite.', 'giocompress' ); ?></strong></p>
            <p><?php esc_html_e( 'Enjoy automatic WebP image optimization for enhanced website performance. The upcoming Pro version will unlock advanced features like AVIF support, customizable quality, and unlimited optimizations.', 'giocompress' ); ?></p>
            <p><?php esc_html_e( 'Stay tuned for the official launch and upgrade to Pro to unlock full capabilities with your API key.', 'giocompress' ); ?></p>
        </div>
        <?php
    }

    public function api_key_invalid_notice() {
        $is_valid = apply_filters('giocompress/is_valid_license', false);
        $has_expired = apply_filters('giocompress/has_expired_license', false);

        if ( !$is_valid && get_option('giocompress_api_key') ) :
            $data = apply_filters('giocompress/get_license_data', []);
        ?>
            <div class="notice notice-error update-nag inline">
                <p><strong><?php if ( $has_expired ) : esc_html_e( 'License Key Has Expired.', 'giocompress' ); else : esc_html_e( 'License Key Invalid', 'giocompress' ); endif; ?></strong></p>
                <p>
                    <?php if ( $has_expired ) : ?>
                        <?php esc_html_e( 'Your license key has expired. Please renew your license to continue.', 'giocompress' ); ?>
                    </p>
                    <p>
                        <?php
                        $product_id = !empty($data['platform_id']) ? $data['platform_id'] : ($data['platform_plugin_id'] ?? '');
                        $order_id = !empty($data['platform_order_id']) ? $data['platform_order_id'] : '';
                        $renewal_link = GIOCOMPRESS_SITE_URL . '/checkout/?add-to-cart=' . $product_id . '&is_renewal=' . $order_id;
                        ?>
                        <a href="<?php echo esc_url($renewal_link); ?>" target="_blank" class="button button-primary">
                            <?php esc_html_e('Renew License', 'giocompress'); ?>
                        </a>
                    <?php else: ?>
                        <?php esc_html_e( 'Your license key is invalid. Please check your key in the plugin settings or contact our support team if the issue persists.', 'giocompress' ); ?>
                    <?php endif; ?>
                </p>
            </div>
        <?php
        endif;
    }
    
    
}