<?php

namespace GioCompress;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Keeps existing content working after existing images are converted:
 *  - replaces the old file URLs in post content, excerpts and post meta;
 *  - redirects (301) requests for replaced JPG/PNG files to the converted file, for URLs that
 *    could not be replaced (options, external links, search engines, other plugins' tables).
 */
class ContentUrls {

    const MAP_META        = '_giocompress_replaced_files';
    // One meta key per replaced file (prefix + md5 of its path): the redirect lookup uses the indexed meta_key only.
    const OLD_FILE_PREFIX = '_giocompress_old_';

    public function __construct() {
        // Priority 1: before redirect_canonical(), which would guess a different URL for the 404.
        add_action('template_redirect', [$this, 'redirect_replaced_file'], 1);
    }

    /**
     * @param int   $attachment_id
     * @param array $files Old path => new path, relative to the uploads directory.
     */
    public function register_replaced_files(int $attachment_id, array $files) {
        $map = get_post_meta($attachment_id, self::MAP_META, true);
        $map = is_array($map) ? $map : [];

        foreach ($files as $old => $new) {
            if ($old === $new) continue;
            $map[$old] = $new;
            update_post_meta($attachment_id, self::OLD_FILE_PREFIX . md5($old), '1');
        }

        update_post_meta($attachment_id, self::MAP_META, $map);
    }

    /**
     * @param array $files Old path => new path, relative to the uploads directory.
     * @return int Number of updated rows.
     */
    public function replace_in_content(array $files): int {
        global $wpdb;

        $files = array_filter($files, function ($new, $old) {
            return $old !== '' && $new !== '' && $old !== $new;
        }, ARRAY_FILTER_USE_BOTH);

        if (empty($files)) {
            return 0;
        }

        $uploads_path = untrailingslashit((string) wp_parse_url(wp_get_upload_dir()['baseurl'], PHP_URL_PATH));

        // Plain URLs, plus JSON-escaped ones used by page builders ("\/wp-content\/uploads\/...").
        $replacements = [];
        foreach ($files as $old => $new) {
            $old_url = $uploads_path . '/' . ltrim($old, '/');
            $new_url = $uploads_path . '/' . ltrim($new, '/');
            $replacements[$old_url] = $new_url;
            $replacements[str_replace('/', '\\/', $old_url)] = str_replace('/', '\\/', $new_url);
        }

        // All the files of an attachment share the same prefix ("2024/01/photo"): rows are selected with
        // one LIKE on it (plain and JSON-escaped) and the exact replacement is done in PHP.
        $prefix = $uploads_path . '/' . ltrim($this->common_prefix(array_keys($files)), '/');
        $likes  = [
            '%' . $wpdb->esc_like($prefix) . '%',
            '%' . $wpdb->esc_like(str_replace('/', '\\/', $prefix)) . '%',
        ];

        $updated = $this->replace_in_posts($replacements, $likes);

        if (apply_filters('giocompress/replace_urls_in_meta', true)) {
            $updated += $this->replace_in_postmeta($replacements, $likes);
        }

        return $updated;
    }

    private function common_prefix(array $paths) {
        $prefix = (string) array_shift($paths);
        foreach ($paths as $path) {
            $length = min(strlen($prefix), strlen($path));
            $i = 0;
            while ($i < $length && $prefix[$i] === $path[$i]) {
                $i++;
            }
            $prefix = substr($prefix, 0, $i);
        }
        return $prefix;
    }

    /**
     * Replaces only whole file names: "photo.jpg" must not match inside "photo.jpg-backup".
     */
    private function replace_string($value, array $replacements) {
        foreach ($replacements as $old => $new) {
            if (strpos($value, $old) === false) continue;
            $value = preg_replace('/' . preg_quote($old, '/') . '(?![A-Za-z0-9_-])/', addcslashes($new, '\\$'), $value);
        }
        return $value;
    }

    private function replace_in_posts(array $replacements, array $likes): int {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_content, post_excerpt FROM %i
            WHERE post_type NOT IN ('revision', 'attachment')
            AND (post_content LIKE %s OR post_content LIKE %s OR post_excerpt LIKE %s OR post_excerpt LIKE %s)",
            $wpdb->posts,
            $likes[0],
            $likes[1],
            $likes[0],
            $likes[1]
        ));

        $updated = 0;
        foreach ((array) $rows as $row) {
            $content = $this->replace_string($row->post_content, $replacements);
            $excerpt = $this->replace_string($row->post_excerpt, $replacements);

            if ($content === $row->post_content && $excerpt === $row->post_excerpt) continue;

            // Direct update: wp_update_post() would create revisions, run content filters and change the modified date.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->update($wpdb->posts, ['post_content' => $content, 'post_excerpt' => $excerpt], ['ID' => $row->ID], ['%s', '%s'], ['%d']);
            clean_post_cache((int) $row->ID);
            $updated++;
        }

        return $updated;
    }

    private function replace_in_postmeta(array $replacements, array $likes): int {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Runs once per optimized image, from the admin.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_id, meta_value FROM %i
            WHERE meta_key NOT IN (%s, %s, %s)
            AND (meta_value LIKE %s OR meta_value LIKE %s)",
            $wpdb->postmeta,
            '_wp_attached_file',
            '_wp_attachment_metadata',
            self::MAP_META,
            $likes[0],
            $likes[1]
        ));

        $updated = 0;
        foreach ((array) $rows as $row) {
            $value = $row->meta_value;

            if (is_serialized($value)) {
                // Objects are never unserialized: rows containing them are left untouched.
                $data = @unserialize($value, ['allowed_classes' => false]); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
                if ($data === false && $value !== 'b:0;') continue;
                if ($this->contains_object($data)) continue;

                $new_value = $this->replace_recursive($data, $replacements);
                if ($new_value === $data) continue;
            } else {
                $new_value = $this->replace_string($value, $replacements);
                if ($new_value === $value) continue;
            }

            // Serializes arrays again and clears the meta cache.
            if (update_metadata_by_mid('post', (int) $row->meta_id, $new_value)) {
                $updated++;
            }
        }

        return $updated;
    }

    private function contains_object($data) {
        if (is_object($data)) return true;
        if (is_array($data)) {
            foreach ($data as $item) {
                if ($this->contains_object($item)) return true;
            }
        }
        return false;
    }

    private function replace_recursive($data, array $replacements) {
        if (is_string($data)) {
            return $this->replace_string($data, $replacements);
        }
        if (is_array($data)) {
            foreach ($data as $key => $item) {
                $data[$key] = $this->replace_recursive($item, $replacements);
            }
        }
        return $data;
    }

    /**
     * Works when missing upload files are routed to WordPress (Apache with the standard .htaccess,
     * or Nginx with a try_files fallback to index.php for the uploads directory).
     */
    public function redirect_replaced_file() {
        if (!is_404() || empty($_SERVER['REQUEST_URI'])) {
            return;
        }

        $path = rawurldecode((string) wp_parse_url(esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])), PHP_URL_PATH));
        if (!preg_match('/\.(jpe?g|png)$/i', $path)) {
            return;
        }

        $upload_dir   = wp_get_upload_dir();
        $uploads_path = trailingslashit((string) wp_parse_url($upload_dir['baseurl'], PHP_URL_PATH));
        if (strpos($path, $uploads_path) !== 0) {
            return;
        }

        $relative = substr($path, strlen($uploads_path));
        if ($relative === '' || strpos($relative, '..') !== false) {
            return;
        }

        $new_relative = $this->find_replacement($relative);

        // Files converted by versions before 1.4.0 kept the same name with the new extension.
        if (!$new_relative) {
            foreach (['webp', 'avif'] as $ext) {
                $candidate = preg_replace('/\.(jpe?g|png)$/i', '.' . $ext, $relative);
                if (file_exists(trailingslashit($upload_dir['basedir']) . $candidate)) {
                    $new_relative = $candidate;
                    break;
                }
            }
        }

        if (!$new_relative || !file_exists(trailingslashit($upload_dir['basedir']) . $new_relative)) {
            return;
        }

        wp_safe_redirect(trailingslashit($upload_dir['baseurl']) . $new_relative, 301, 'GioCompress');
        exit;
    }

    private function find_replacement($relative) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Indexed meta_key lookup, only for 404 image requests.
        $attachment_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM %i WHERE meta_key = %s LIMIT 1",
            $wpdb->postmeta,
            self::OLD_FILE_PREFIX . md5($relative)
        ));

        if (!$attachment_id) {
            return '';
        }

        $map = get_post_meta($attachment_id, self::MAP_META, true);
        return is_array($map) && !empty($map[$relative]) ? (string) $map[$relative] : '';
    }
}
