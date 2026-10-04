<?php

namespace GioCompress;

if ( ! defined( 'ABSPATH' ) ) exit;

class RetroOptimizer {

    const NONCE_ACTION = 'giocompress_retro_optimize';
    const FREE_DAILY_LIMIT = 5;

    public function __construct() {
        add_action('admin_notices', [$this, 'show_notice']);
        add_action('wp_ajax_giocompress_get_attachments', [$this, 'ajax_get_attachments']);
        add_action('wp_ajax_giocompress_optimize_attachment', [$this, 'ajax_optimize_single']);
    }

    public function optimize_page() {
        $paged_raw = filter_input(INPUT_GET, 'paged', FILTER_SANITIZE_NUMBER_INT);
        $paged = max(1, (int) wp_unslash($paged_raw ?? 1));
        $per_page = 10;
        $offset   = ($paged - 1) * $per_page;
        $nonce = wp_create_nonce(self::NONCE_ACTION);

        $db          = Plugin::instance()->db;
        $attachments = $db->get_not_optimized_attachments($per_page, $offset);
        $total       = $db->count_not_optimized_attachments();
        $total_pages = (int) ceil($total / $per_page);
        $has_reached_free_limit = $this->has_reached_free_limit();
        ?>
        <div class="wrap">
            <?php if ($has_reached_free_limit): ?>
            <div class="notice notice-warning">
                <p><?php
                    /* translators: %d: number of images that can be optimized per day in the free version */
                    echo esc_html(sprintf(__('You have reached the free daily limit of %d image optimizations. Upgrade to Pro to continue optimizing without limits.', 'giocompress'), $this->get_daily_limit()));
                ?></p>
                <p><a href="<?php echo esc_url( GIOCOMPRESS_SITE_URL ); ?>" target="_blank" rel="noopener noreferrer" style="font-weight:bold;">
                    <?php esc_html_e('Go Pro and optimize all your images without limits', 'giocompress'); ?>
                </a></p>
            </div>
            <?php endif; ?>
            <h1><?php esc_html_e('Optimize Existing Images', 'giocompress'); ?></h1>

            <form id="giocompress-form">
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <?php if (!empty($attachments)) : ?>
                                <th style="width:2%;"><input type="checkbox" id="select-all"<?php if ($has_reached_free_limit) : ?> disabled<?php endif; ?>></th>
                            <?php endif; ?>
                            <th><?php esc_html_e('Image', 'giocompress'); ?></th>
                            <th><?php esc_html_e('Filename', 'giocompress'); ?></th>
                            <th><?php esc_html_e('Size', 'giocompress'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($attachments)) : ?>
                            <tr>
                                <td colspan="4">
                                    <div class="notice notice-info">
                                        <p><?php esc_html_e('There are no images left to optimize.', 'giocompress'); ?></p>
                                    </div>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($attachments as $att):
                                $file = get_attached_file($att->ID);
                                $size = ($file && file_exists($file)) ? size_format(filesize($file)) : '—';
                                ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_ids[]" value="<?php echo esc_attr($att->ID); ?>"<?php if ($has_reached_free_limit) : ?> disabled<?php endif; ?>></td>
                                    <td><?php echo wp_get_attachment_image($att->ID, [80, 80], true); ?></td>
                                    <td><?php echo esc_html($file ? basename($file) : ''); ?></td>
                                    <td><?php echo esc_html($size); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <p class="submit">
                    <button
                        type="button"
                        id="optimize-selected"
                        class="button button-primary"
                        <?php disabled(empty($attachments) || $has_reached_free_limit); ?>
                    >
                        <?php esc_html_e('Optimize Selected', 'giocompress'); ?>
                    </button>
                    <button
                        type="button"
                        id="optimize-all"
                        class="button"
                        <?php disabled(empty($attachments) || $has_reached_free_limit); ?>
                    >
                        <?php esc_html_e('Optimize All', 'giocompress'); ?>
                    </button>
                </p>
            </form>

            <div id="giocompress-progress-wrapper" style="display:none;">
                <div>
                    <div
                        id="giocompress-progress-bar"
                        aria-live="polite"
                        role="status"
                    >0%</div>
                </div>
            </div>

            <?php
            if ($total_pages > 1) {
                ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                    <?php
                    $base_url = remove_query_arg('paged');
                    $pagination = paginate_links([
                        'base'      => esc_url(add_query_arg('paged', '%#%', $base_url)),
                        'format'    => '',
                        'prev_text' => __('«', 'giocompress'),
                        'next_text' => __('»', 'giocompress'),
                        'total'     => $total_pages,
                        'current'   => $paged,
                    ]);

                    echo wp_kses_post($pagination);
                    ?>
                    </div>
                </div>
            <?php
            }
            ?>

            <script>
            const GioCompress = {
                ajaxurl: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
                nonce: <?php echo wp_json_encode($nonce); ?>,
                doneUrl: <?php echo wp_json_encode(admin_url('admin.php?page=giocompress-optimize')); ?>,
                selectMessage: <?php echo wp_json_encode(__('Please select at least one image.', 'giocompress')); ?>
            };

            document.getElementById('select-all')?.addEventListener('change', function() {
                document.querySelectorAll('input[name="selected_ids[]"]').forEach(el => el.checked = this.checked);
            });

            const setUIState = (isDisabled) => {
                document.getElementById('optimize-selected').disabled = isDisabled;
                document.getElementById('optimize-all').disabled = isDisabled;
                const selectAll = document.getElementById('select-all');
                if (selectAll) selectAll.disabled = isDisabled;
                document.querySelectorAll('input[name="selected_ids[]"]').forEach(el => el.disabled = isDisabled);
            };

            const post = (action, params = {}) => {
                const body = new FormData();
                body.append('action', action);
                body.append('nonce', GioCompress.nonce);
                Object.keys(params).forEach(key => body.append(key, params[key]));
                return fetch(GioCompress.ajaxurl, { method: 'POST', credentials: 'same-origin', body })
                    .then(res => res.json());
            };

            const runOptimization = ids => {
                const progressBar = document.getElementById('giocompress-progress-bar');
                const wrapper = document.getElementById('giocompress-progress-wrapper');
                const total = ids.length;
                let processed = 0;
                let optimized = 0;
                let failed = 0;

                setUIState(true);
                wrapper.style.display = 'block';

                const finish = (limitReached = false) => {
                    const url = new URL(GioCompress.doneUrl);
                    url.searchParams.set('optimized', optimized);
                    url.searchParams.set('failed', failed);
                    if (limitReached) url.searchParams.set('limit', 1);
                    url.searchParams.set('_wpnonce', GioCompress.nonce);
                    setTimeout(() => { location.href = url.toString(); }, 800);
                };

                const next = () => {
                    if (ids.length === 0) {
                        finish();
                        return;
                    }

                    const id = ids.shift();
                    post('giocompress_optimize_attachment', { id })
                        .then(res => {
                            if (res && res.success) {
                                optimized++;
                                return true;
                            }
                            failed++;
                            return !(res && res.data && res.data.code === 'limit');
                        })
                        .catch(() => {
                            failed++;
                            return true;
                        })
                        .then(proceed => {
                            processed++;
                            const percent = Math.round((processed / total) * 100);
                            progressBar.style.width = percent + '%';
                            progressBar.textContent = percent + '%';
                            if (proceed) {
                                next();
                            } else {
                                failed--; // The image that hit the limit was not attempted.
                                finish(true);
                            }
                        });
                };

                next();
            };

            document.getElementById('optimize-selected').addEventListener('click', function() {
                const ids = Array.from(document.querySelectorAll('input[name="selected_ids[]"]:checked')).map(i => i.value);
                if (ids.length > 0) {
                    runOptimization(ids);
                } else {
                    alert(GioCompress.selectMessage);
                }
            });

            document.getElementById('optimize-all').addEventListener('click', function() {
                setUIState(true);
                post('giocompress_get_attachments')
                    .then(res => {
                        const ids = res && res.success ? res.data.ids : [];
                        if (ids.length > 0) {
                            runOptimization(ids);
                        } else {
                            setUIState(false);
                        }
                    })
                    .catch(() => setUIState(false));
            });
            </script>

        </div>
        <?php
    }

    public function ajax_get_attachments() {
        if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false) || !current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized request', 'giocompress')], 403);
        }

        $attachments = Plugin::instance()->db->get_not_optimized_attachments();
        $ids = array_map(fn($a) => (int) $a->ID, $attachments);

        // The free version only sends what can still be optimized today.
        if (!apply_filters('giocompress/is_valid_license', false)) {
            $ids = array_slice($ids, 0, $this->get_remaining_optimizations());
        }

        wp_send_json_success(['ids' => $ids]);
    }

    public function ajax_optimize_single() {
        if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false) || !current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized request', 'giocompress')], 403);
        }
        if ($this->has_reached_free_limit()) {
            wp_send_json_error([
                'code'    => 'limit',
                'message' => __('You have reached the optimization limit for the free version. Please upgrade to Pro.', 'giocompress'),
            ]);
        }

        $id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;
        if (!$id || get_post_type($id) !== 'attachment') {
            wp_send_json_error(['message' => 'Invalid ID']);
        }

        if ($this->optimize_attachment($id)) {
            wp_send_json_success();
        }

        wp_send_json_error(['message' => __('The image could not be optimized.', 'giocompress')]);
    }

    private function optimize_attachment($attachment_id) {
        $plugin = Plugin::instance();
        $result = $plugin->optimizer->optimize_existing((int) $attachment_id);

        if (!$result) {
            return false;
        }

        $plugin->db->add_meta((int) $attachment_id, [
            'original_file'     => $result['original_file'],
            'original_size'     => $result['original_size'],
            'optimized_size'    => $result['optimized_size'],
            'optimization_date' => current_time('mysql'),
            'source'            => DB::SOURCE_RETRO,
        ]);

        // Existing posts still point to the old JPG/PNG files.
        $plugin->content_urls->register_replaced_files((int) $attachment_id, $result['files']);
        $plugin->content_urls->replace_in_content($result['files']);

        return true;
    }

    public function show_notice() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce verified below.
        if (!isset($_GET['page'], $_GET['optimized']) || $_GET['page'] !== 'giocompress-optimize') {
            return;
        }
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (empty($nonce) || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return;
        }

        $count  = absint($_GET['optimized']);
        $failed = isset($_GET['failed']) ? absint($_GET['failed']) : 0;
        $limit  = !empty($_GET['limit']);
        // phpcs:enable
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php
                echo esc_html(
                    sprintf(
                    // translators: %d: number of optimized images
                    _n('%d image optimized.', '%d images optimized.', $count, 'giocompress'),
                    $count
                    )
                );
            ?></p>
        </div>
        <?php if ($failed > 0) : ?>
        <div class="notice notice-error is-dismissible">
            <p><?php
                echo esc_html(
                    sprintf(
                    // translators: %d: number of images that could not be optimized
                    _n('%d image could not be optimized.', '%d images could not be optimized.', $failed, 'giocompress'),
                    $failed
                    )
                );
            ?></p>
        </div>
        <?php endif; ?>
        <?php if ($limit) : ?>
        <div class="notice notice-warning is-dismissible">
            <p><?php esc_html_e('You have reached the optimization limit for the free version. Please upgrade to Pro.', 'giocompress'); ?></p>
        </div>
        <?php endif;
    }

    private function get_daily_limit(): int {
        return max(0, (int) apply_filters('giocompress_daily_limit', self::FREE_DAILY_LIMIT));
    }

    private function get_remaining_optimizations(): int {
        return max(0, $this->get_daily_limit() - Plugin::instance()->db->daily_optimizations());
    }

    private function has_reached_free_limit(): bool {
        if (apply_filters('giocompress/is_valid_license', false)) {
            return false;
        }
        return $this->get_remaining_optimizations() <= 0;
    }

}
