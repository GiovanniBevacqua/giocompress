<?php

namespace GioCompress;

if ( ! defined( 'ABSPATH' ) ) exit;

class Reports {
    private $per_page = 10;

    public function __construct() {
        add_filter('giocompress_report_per_page', fn() => $this->per_page);
        add_filter('giocompress/report/global_summary', [$this, 'dummy_summary']);
        add_filter('giocompress/report/total_attachments', [$this, 'total_attachments']);
        add_filter('giocompress/report/paginated_attachments', [$this, 'paginated_attachments'], 10, 3);
        add_filter('giocompress_get_filename', [$this, 'attachment_filename'], 10, 1);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'giocompress_page_giocompress-report') {
            return;
        }
        wp_enqueue_script(
            'chartjs',
            GIOCOMPRESS_URL . 'assets/js/chart.js',
            [],
            '4.4.0',
            true
        );
    }

    public function report_page() {
        $paged_raw = filter_input(INPUT_GET, 'paged', FILTER_SANITIZE_NUMBER_INT);
        $paged = max(1, (int) wp_unslash($paged_raw ?? 1));
        $per_page = apply_filters('giocompress_report_per_page', $this->per_page);
        $offset = ($paged - 1) * $per_page;

        if (!apply_filters('giocompress/is_valid_license', false)) {
            echo '<div class="notice notice-info"><p>' .
                esc_html__('This is a preview of the Optimization Report available in GioCompress Pro. The data below is for demonstration only. Upgrade to Pro to see real statistics about your optimized media.', 'giocompress') .
                '</p></div>';
        }

        $summary = apply_filters('giocompress/report/global_summary', (object) [
            'global_original' => 12345678,
            'global_optimized' => 4567890,
        ]);

        $global_original = (int) $summary->global_original;
        $global_optimized = (int) $summary->global_optimized;

        $global_saved = $global_original - $global_optimized;
        $global_saved_percent = $global_original > 0 ? round(($global_saved / $global_original) * 100, 2) : 0;

        $attachments = apply_filters('giocompress/report/paginated_attachments', [], $per_page, $offset);

        $total_attachments = apply_filters('giocompress/report/total_attachments', count($attachments));

        $page_original = 0;
        $page_optimized = 0;

        foreach ($attachments as $attachment) {
            $original = (int) $attachment->original_size;
            $optimized = (int) $attachment->optimized_size;
            if ($original > 0 && $optimized > 0) {
                $page_original += $original;
                $page_optimized += $optimized;
            }
        }

        $page_saved = $page_original - $page_optimized;
        $page_saved_percent = $page_original > 0 ? round(($page_saved / $page_original) * 100, 2) : 0;

        ?>
        <div class="wrap">
            <?php if ($this->has_negative_optimizations($attachments)) : ?>
                <div class="notice notice-warning">
                    <p><?php echo esc_html__('Some optimized images are larger than their originals. This can happen if the original image was already highly optimized, or due to characteristics of specific formats (e.g., converting PNGs to WebP with low compression).', 'giocompress'); ?></p>
                </div>
            <?php endif; ?>
            <h1><?php echo esc_html__('Optimization Report', 'giocompress'); ?></h1>

            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e('File', 'giocompress'); ?></th>
                        <th><?php esc_html_e('Original Size', 'giocompress'); ?></th>
                        <th><?php esc_html_e('Optimized Size', 'giocompress'); ?></th>
                        <th><?php esc_html_e('Saved (%)', 'giocompress'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $total_original = 0;
                $total_optimized = 0;
                foreach ($attachments as $attachment) :
                    $attachment_id = $attachment->attachment_id;
                    $basename = apply_filters('giocompress_get_filename', $attachment);
                    $original = $attachment->original_size;
                    $optimized = $attachment->optimized_size;

                    if ($original > 0 && $optimized > 0) :
                        $saved_percent = round(100 - (($optimized / $original) * 100), 1);
                        $total_original += $original;
                        $total_optimized += $optimized;

                        $row_class = $optimized > $original ? 'giocompress-warning-row' : '';

                        ?>
                        <tr class="<?php echo esc_attr($row_class); ?>">
                            <td><?php echo esc_html($basename); ?></td>
                            <td><?php echo esc_html( size_format($original, 2) ); ?></td>
                            <td><?php echo esc_html( size_format($optimized, 2) ); ?></td>
                            <td><?php echo esc_html($saved_percent . '%'); ?></td>
                        </tr>
                        <?php
                    endif;
                endforeach;
                ?>
                </tbody>
            </table>

            <?php
            $page_saved = $page_original - $page_optimized;
            $page_saved_percent = $page_original > 0 ? round(($page_saved / $page_original) * 100, 2) : 0;

            // Pagination
            $total_pages = ceil($total_attachments / $per_page);
            if ($total_pages > 1) :
                $page_links = paginate_links([
                    'base'      => add_query_arg('paged', '%#%'),
                    'format'    => '',
                    'prev_text' => __('« Previous', 'giocompress'),
                    'next_text' => __('Next »', 'giocompress'),
                    'total'     => $total_pages,
                    'current'   => $paged,
                ]);
                ?>
                <div class="tablenav">
                    <div class="tablenav-pages"><?php echo wp_kses_post($page_links); ?></div>
                </div>
            <?php endif; ?>

            <h2><?php esc_html_e('Memory Savings', 'giocompress'); ?></h2>

            <div class="giocompress-grid col-2">
                <div class="giocompress-card">
                    <p>
                        <?php
                        echo esc_html(
                            sprintf(
                                // translators: 1: original size, 2: optimized size, 3: saved size, 4: saved percent
                                __('Global total: Original %1$s — Optimized %2$s — Saved %3$s (%4$s%%)', 'giocompress'),
                                size_format($global_original, 2),
                                size_format($global_optimized, 2),
                                size_format($global_saved, 2),
                                $global_saved_percent
                            )
                        );
                        ?>
                    </p>
                    <h3><?php esc_html_e('Global Optimization Chart', 'giocompress'); ?></h3>
                    <canvas class="giocompress-chart --global" width="400" height="200"></canvas>
                </div>
                <div class="giocompress-card">
                    <p>
                        <?php
                        echo esc_html(
                            sprintf(
                                // translators: 1: original size, 2: optimized size, 3: saved size, 4: saved percent
                                __('Page total: Original %1$s — Optimized %2$s — Saved %3$s (%4$s%%)', 'giocompress'),
                                size_format($page_original, 2),
                                size_format($page_optimized, 2),
                                size_format($page_saved, 2),
                                $page_saved_percent
                            )
                        );
                        ?>
                    </p>
                    <h3><?php esc_html_e('Current Page Optimization Chart', 'giocompress'); ?></h3>
                    <canvas class="giocompress-chart --filtered" width="400" height="200"></canvas>
                </div>
            </div>

            <script>
                <?php
                $label_optimized = __('Optimized', 'giocompress');
                $label_saved = __('Saved', 'giocompress');
                ?>
                document.addEventListener('DOMContentLoaded', function () {
                    const ctxGlobal = document.querySelector(".giocompress-chart.--global").getContext("2d");
                    new Chart(ctxGlobal, {
                        type: "pie",
                        data: {
                            labels: ["<?php echo esc_js($label_optimized); ?>", "<?php echo esc_js($label_saved); ?>"],
                            datasets: [{
                                data: [<?php echo esc_html($global_optimized); ?>, <?php echo esc_html($global_saved); ?>],
                                backgroundColor: ["#36a2eb", "#4caf50"]
                            }]
                        },
                        options: {
                            plugins: {
                                legend: { position: "bottom" }
                            }
                        }
                    });

                    const ctxPage = document.querySelector(".giocompress-chart.--filtered").getContext("2d");
                    new Chart(ctxPage, {
                        type: "pie",
                        data: {
                            labels: ["<?php echo esc_js($label_optimized); ?>", "<?php echo esc_js($label_saved); ?>"],
                            datasets: [{
                                data: [<?php echo esc_html($page_optimized); ?>, <?php echo esc_html($page_saved); ?>],
                                backgroundColor: ["#ff9800", "#8bc34a"]
                            }]
                        },
                        options: {
                            plugins: {
                                legend: { position: "bottom" }
                            }
                        }
                    });
                });
            </script>
        </div>
        <?php
    }

    private function has_negative_optimizations(array $attachments): bool {
        foreach ($attachments as $attachment) :
            $original = (int) $attachment->original_size;
            $optimized = (int) $attachment->optimized_size;
            if ($original > 0 && $optimized > $original) :
                return true;
            endif;
        endforeach;
        return false;
    }

    public function dummy_summary($default) {
        if (apply_filters('giocompress/is_valid_license', false)) {
            return $default;
        }
        $file = GIOCOMPRESS_DIR . 'assets/data/reports.json';
        if (file_exists($file)) {
            $json = file_get_contents($file);
            $data = json_decode($json);

            if (is_array($data) && !empty($data)) {
                return (object) [
                    'global_original'  => array_sum(array_map(fn($item) => (int) ($item->original_size ?? 0), $data)),
                    'global_optimized' => array_sum(array_map(fn($item) => (int) ($item->optimized_size ?? 0), $data)),
                ];
            }
        }
        return $default;
    }

    public function total_attachments($default) {
        if (apply_filters('giocompress/is_valid_license', false)) {
            return $default;
        }
        $file = GIOCOMPRESS_DIR . 'assets/data/reports.json';
        if (file_exists($file)) {
            $json = file_get_contents($file);
            $data = json_decode($json);
            return is_array($data) ? count($data) : $default;
        }
        return $default;
    }

    public function paginated_attachments($default, $per_page, $offset) {
        if (apply_filters('giocompress/is_valid_license', false)) {
            return $default;
        }
        $file = GIOCOMPRESS_DIR . 'assets/data/reports.json';
        if (file_exists($file)) {
            $json = file_get_contents($file);
            $data = json_decode($json);
            if (!is_array($data)) {
                return $default;
            }
            $page_items = array_slice($data, $offset, $per_page);
            return array_map(function ($item) {
                return (object) [
                    'attachment_id'   => (int) $item->attachment_id,
                    'original_size'   => (int) $item->original_size,
                    'optimized_size'  => (int) $item->optimized_size,
                    'filename'        => 'demo-' . $item->attachment_id . '.jpg',

                ];
            }, $page_items);
        }
        return $default;
    }

    public function attachment_filename($attachment) {
        if (apply_filters('giocompress/is_valid_license', false)) {
            $file = get_attached_file($attachment->attachment_id);
            return $file ? basename($file) : '#' . (int) $attachment->attachment_id;
        }
        return $attachment->filename ?? 'unknown.jpg';
    }

}