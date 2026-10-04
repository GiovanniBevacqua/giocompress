<?php

namespace GioCompress;

if ( ! defined( 'ABSPATH' ) ) exit;

class SEO {

    private $alt_cache = [];
    private $image_index = 0;

    public function __construct() {
        if (get_option('giocompress_auto_alt_text', 0) || get_option('giocompress_lazy_loading', 0)) {
            add_action('template_redirect', [$this, 'start_output_buffer'], 0);
            add_action('shutdown', [$this, 'end_output_buffer'], 0);
        }
    }

    public function start_output_buffer() {
        if (is_feed() || is_embed()) {
            return;
        }
        ob_start([$this, 'process_global_image_attributes']);
    }

    public function end_output_buffer() {
        $ob_handlers = ob_list_handlers();
        $current_ob_level = ob_get_level();

        if ($current_ob_level > 0 && isset($ob_handlers[$current_ob_level - 1]) && $ob_handlers[$current_ob_level - 1] === get_class($this) . '::process_global_image_attributes') {
            ob_end_flush();
        }
    }

    public function process_global_image_attributes($buffer) {
        $auto_alt = (bool) get_option('giocompress_auto_alt_text', 0);
        $lazy     = (bool) get_option('giocompress_lazy_loading', 0);

        // The first images are usually above the fold (logo, hero): lazy loading them delays the LCP.
        // Same threshold used by WordPress core for content images (filterable with wp_omit_loading_attr_threshold).
        $lazy_threshold = max(0, (int) wp_omit_loading_attr_threshold());
        $this->image_index = 0;

        $modified_buffer = preg_replace_callback(
            '/<img(?<attributes>[^>]+)>/i',
            function( $matches ) use ( $auto_alt, $lazy, $lazy_threshold ) {
                $img_tag = $matches[0];
                $attributes_string = $matches['attributes'];
                $modified_img_tag = $img_tag;
                $this->image_index++;

                // alt="" is what WordPress prints when no alt text is set, so it is treated as missing.
                if ($auto_alt) {
                    preg_match('/\salt\s*=\s*("|\')(?<alt_value>[^"\']*)("|\')/i', $attributes_string, $alt_matches);

                    if (!isset($alt_matches['alt_value']) || trim($alt_matches['alt_value']) === '') {
                        $alt_text = $this->get_alt_text($attributes_string);

                        if ($alt_text !== '') {
                            if (isset($alt_matches['alt_value'])) {
                                $modified_img_tag = preg_replace(
                                    '/\salt\s*=\s*("|\')([^"\']*)("|\')/i',
                                    ' alt="' . esc_attr($alt_text) . '"',
                                    $modified_img_tag,
                                    1
                                );
                            } else {
                                $modified_img_tag = preg_replace('/^<img/i', '<img alt="' . esc_attr($alt_text) . '"', $modified_img_tag);
                            }
                        }
                    }
                }

                if ($lazy
                    && $this->image_index > $lazy_threshold
                    && !preg_match('/\sloading\s*=/i', $modified_img_tag)
                    && !preg_match('/\sfetchpriority\s*=\s*("|\')?high/i', $modified_img_tag)
                ) {
                    $modified_img_tag = preg_replace('/^<img/i', '<img loading="lazy"', $modified_img_tag);
                }

                return $modified_img_tag;
            },
            $buffer
        );

        return $modified_buffer === null ? $buffer : $modified_buffer;
    }

    private function get_alt_text($attributes_string) {
        preg_match('/src\s*=\s*("|\')(?<src>[^"\']*)("|\')/i', $attributes_string, $src_matches);
        $img_url = isset($src_matches['src']) ? $src_matches['src'] : '';

        if (empty($img_url)) {
            return '';
        }

        if (!isset($this->alt_cache[$img_url])) {
            $this->alt_cache[$img_url] = $this->find_attachment_alt($this->get_attachment_id($attributes_string, $img_url));
        }
        $alt_text = $this->alt_cache[$img_url];

        if ($alt_text === '') {
            $current_post_id = get_queried_object_id();
            if ($current_post_id && is_singular() && get_post_type($current_post_id) !== 'attachment') {
                $alt_text = sanitize_text_field(get_the_title($current_post_id));
            }
        }

        if ($alt_text === '') {
            $path = (string) wp_parse_url($img_url, PHP_URL_PATH);
            $filename_without_ext = pathinfo(basename($path), PATHINFO_FILENAME);
            $filename_without_ext = preg_replace('/-(?:\d+x\d+|scaled|\d+)$/', '', $filename_without_ext);
            if (!empty($filename_without_ext)) {
                $alt_text = ucwords(str_replace(['-', '_'], ' ', $filename_without_ext));
            }
        }

        if ($alt_text === '') {
            $alt_text = __('Image', 'giocompress');
        }

        return $alt_text;
    }

    private function get_attachment_id($attributes_string, $img_url) {
        // WordPress adds the attachment ID as a class to images inserted in the editor.
        if (preg_match('/\bwp-image-(\d+)\b/', $attributes_string, $class_matches)) {
            return (int) $class_matches[1];
        }

        $attachment_id = attachment_url_to_postid($img_url);

        // attachment_url_to_postid() only matches the full-size file, not sub-sizes like -300x200.
        if (!$attachment_id) {
            $full_url = preg_replace('/-(?:\d+x\d+|scaled)(\.[a-z0-9]+)(\?.*)?$/i', '$1', $img_url);
            if ($full_url !== $img_url) {
                $attachment_id = attachment_url_to_postid($full_url);
            }
        }

        return (int) $attachment_id;
    }

    private function find_attachment_alt($attachment_id) {
        if (!$attachment_id) {
            return '';
        }

        $candidates = [
            get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
            get_the_title($attachment_id),
            wp_get_attachment_caption($attachment_id),
            get_post_field('post_content', $attachment_id),
        ];

        foreach ($candidates as $candidate) {
            $candidate = sanitize_text_field((string) $candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }
}
