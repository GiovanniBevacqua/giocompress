<?php

namespace GioCompress;

if ( ! defined( 'ABSPATH' ) ) exit;

class Optimizer {
    const DEFAULT_QUALITY   = 95;
    const SOURCE_EXTENSIONS = ['jpg', 'jpeg', 'png'];

    private $originals = [];
    private $meta_sizes = [];

    public function __construct() {
        add_filter('wp_handle_upload', [$this, 'handle_upload'], 10, 2);
        add_action('add_attachment', [ $this, 'maybe_store_original_meta' ]);
        add_action('delete_attachment', [ $this, 'delete_attachment_data' ]);
    }

    /**
     * Output formats available on this site. Pro adds AVIF through the filter.
     */
    public static function get_supported_formats(): array {
        $formats = (array) apply_filters('giocompress_supported_formats', ['webp']);
        $formats = array_values(array_unique(array_merge(['webp'], array_map('strval', $formats))));
        return $formats;
    }

    /**
     * Effective conversion parameters. The Lite version always uses WebP, the default quality and
     * no resizing; Pro overrides them through the giocompress_optimization_params filter.
     */
    public function get_params(): array {
        $format = (string) get_option('giocompress_format', 'webp');

        $params = apply_filters('giocompress_optimization_params', [
            'format'    => $format,
            'quality'   => self::DEFAULT_QUALITY,
            'max_width' => 0,
        ]);

        $params = is_array($params) ? $params : [];

        return [
            'format'    => in_array($params['format'] ?? '', self::get_supported_formats(), true) ? $params['format'] : 'webp',
            'quality'   => min(100, max(1, (int) ($params['quality'] ?? self::DEFAULT_QUALITY))),
            'max_width' => max(0, (int) ($params['max_width'] ?? 0)),
        ];
    }

    public function preserve_original(): bool {
        return (bool) apply_filters('giocompress_preserve_original', false);
    }

    public function is_source_image($path): bool {
        return in_array(strtolower(pathinfo((string) $path, PATHINFO_EXTENSION)), self::SOURCE_EXTENSIONS, true);
    }

    // Handle the image after the upload is completed
    public function handle_upload($fileinfo, $context = 'upload') {
        if (!empty($fileinfo['error']) || empty($fileinfo['file'])) {
            return $fileinfo;
        }

        $file_path = $fileinfo['file'];

        // Only process JPG/PNG files
        if (!$this->is_source_image($file_path)) {
            return $fileinfo;
        }

        $original_size = file_exists($file_path) ? filesize($file_path) : 0;
        $new_file_path = $this->convert_to_new_file($file_path, $this->get_params());

        if (!$new_file_path) {
            return $fileinfo;
        }

        // Temp data add_attachment
        $this->meta_sizes[basename($new_file_path)] = [
            'original'  => $original_size,
            'optimized' => filesize($new_file_path),
        ];

        if ($this->preserve_original()) {
            $this->originals[basename($new_file_path)] = $file_path;
        } else {
            wp_delete_file($file_path);
        }

        $fileinfo['file'] = $new_file_path;
        if (!empty($fileinfo['url'])) {
            $fileinfo['url'] = substr($fileinfo['url'], 0, strrpos($fileinfo['url'], '/') + 1) . basename($new_file_path);
        }
        $fileinfo['type'] = wp_check_filetype($new_file_path)['type'];

        return $fileinfo;
    }

    /**
     * Converts $src into a new file next to it, with a name that does not collide with any existing
     * file (e.g. "photo.webp" left by a previous upload of another "photo.jpg").
     * Falls back to WebP when the selected format is not supported by the server.
     *
     * @return string|false Path of the new file.
     */
    public function convert_to_new_file($src, array $params) {
        $formats = array_unique([$params['format'], 'webp']);

        foreach ($formats as $format) {
            $dest = $this->get_target_path($src, $format);
            if ($this->convert_image($src, $dest, $format, $params['quality'], $params['max_width'])) {
                return $dest;
            }
            if (file_exists($dest)) {
                wp_delete_file($dest);
            }
        }

        return false;
    }

    private function get_target_path($src, $format) {
        $dir      = trailingslashit(dirname($src));
        $filename = pathinfo($src, PATHINFO_FILENAME);
        $name     = $filename . '.' . $format;

        // Sub-sizes ("-300x200") and scaled files never generate new sub-sizes: only the file itself must be free.
        // wp_unique_filename() would always append "-1" to these names.
        if (preg_match('/-(?:\d+x\d+|scaled|rotated)$/', $filename) && !file_exists($dir . $name)) {
            return $dir . $name;
        }

        // wp_unique_filename() also checks the sub-size names the new file will produce.
        return $dir . wp_unique_filename($dir, $name);
    }

    /**
     * EXIF orientation of a JPEG: the converted file has no EXIF, so the rotation must be applied.
     */
    private function get_orientation($src) {
        $orientation = 0;
        if (is_callable('exif_read_data') && in_array(strtolower(pathinfo($src, PATHINFO_EXTENSION)), ['jpg', 'jpeg'], true)) {
            $exif = @exif_read_data($src); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Corrupted EXIF must not break the upload.
            if (!empty($exif['Orientation'])) {
                $orientation = (int) $exif['Orientation'];
            }
        }
        // Return 1 to keep the image as stored.
        return (int) apply_filters('giocompress_exif_orientation', $orientation, $src);
    }

    private function imagick_orient(\Imagick $image, $orientation) {
        switch ($orientation) {
            case 2: $image->flopImage(); break;
            case 3: $image->rotateImage('none', 180); break;
            case 4: $image->flipImage(); break;
            case 5: $image->transposeImage(); break;
            case 6: $image->rotateImage('none', 90); break;
            case 7: $image->transverseImage(); break;
            case 8: $image->rotateImage('none', 270); break;
            default: return;
        }
        $image->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
    }

    private function gd_orient($image, $orientation) {
        switch ($orientation) {
            case 2: imageflip($image, IMG_FLIP_HORIZONTAL); break;
            case 3: imageflip($image, IMG_FLIP_BOTH); break;
            case 4: imageflip($image, IMG_FLIP_VERTICAL); break;
            case 5: $image = imagerotate($image, 90, 0); imageflip($image, IMG_FLIP_VERTICAL); break;
            case 6: $image = imagerotate($image, 270, 0); break;
            case 7: $image = imagerotate($image, 90, 0); imageflip($image, IMG_FLIP_HORIZONTAL); break;
            case 8: $image = imagerotate($image, 90, 0); break;
        }
        return $image;
    }

    private function imagick_supports($format) {
        try {
            return !empty(\Imagick::queryFormats(strtoupper($format)));
        } catch (\Exception $e) {
            return false;
        }
    }

    private function convert_image($src, $dest, $format, $quality, $max_width = 0) {
        $orientation = $this->get_orientation($src);

        // Use Imagick if available
        if (extension_loaded('imagick') && $this->imagick_supports($format)) {
            try {
                $image = new \Imagick($src);

                $this->imagick_orient($image, $orientation);

                if ($max_width > 0 && $image->getImageWidth() > $max_width) {
                    $image->resizeImage($max_width, 0, \Imagick::FILTER_LANCZOS, 1);
                }

                // Format
                $image->setImageFormat($format);

                // Compression
                $image->setImageCompressionQuality($quality);

                // Metadata is removed, but the color profile is kept to avoid color shifts.
                $profiles = $image->getImageProfiles('icc', true);
                $image->stripImage();
                if (!empty($profiles['icc'])) {
                    $image->profileImage('icc', $profiles['icc']);
                }

                if ($format === 'webp') {
                    $image->setOption('webp:method', '6');
                }

                // Save and clear
                $written = $image->writeImage($dest);
                $image->clear();
                $image->destroy();

                if ($written && file_exists($dest) && filesize($dest) > 0) {
                    return true;
                }
            } catch (\Exception $e) {
                // fallback GD
            }
        }

        // Fallback: GD
        $image_info = @getimagesize($src); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        if (!$image_info) {
            return false;
        }

        switch ($image_info['mime']) {
            case 'image/jpeg':
                $image = @imagecreatefromjpeg($src); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
                break;
            case 'image/png':
                $image = @imagecreatefrompng($src); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
                if ($image) {
                    imagepalettetotruecolor($image);
                    imagealphablending($image, false);
                    imagesavealpha($image, true);
                }
                break;
            default:
                return false;
        }

        if (!$image) {
            return false;
        }

        $image = $this->gd_orient($image, $orientation);

        if ($max_width > 0 && imagesx($image) > $max_width) {
            $new_width  = (int) $max_width;
            $new_height = max(1, (int) round(imagesy($image) * ($max_width / imagesx($image))));

            $resized = imagecreatetruecolor($new_width, $new_height);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $new_width, $new_height, imagesx($image), imagesy($image));

            $image = $resized;
        }

        $success = false;
        if ($format === 'webp' && function_exists('imagewebp')) {
            $success = imagewebp($image, $dest, $quality);
        } elseif ($format === 'avif' && function_exists('imageavif')) {
            $success = imageavif($image, $dest, $quality);
        }

        unset($image);

        return $success && file_exists($dest) && filesize($dest) > 0;
    }

    public function maybe_store_original_meta($post_id) {
        $file = get_attached_file($post_id);
        if ($file) {
            $this->store_meta_for_file($post_id, $file);
        }
    }

    private function store_meta_for_file(int $attachment_id, string $file): void {
        $basename = basename($file);
        if (empty($this->meta_sizes[$basename])) {
            return;
        }

        $sizes = $this->meta_sizes[$basename];
        Plugin::instance()->db->add_meta($attachment_id, [
            'original_file'  => $this->originals[$basename] ?? '',
            'original_size'  => $sizes['original'],
            'optimized_size' => $sizes['optimized'],
            'source'         => DB::SOURCE_UPLOAD,
        ]);

        unset($this->meta_sizes[$basename], $this->originals[$basename]);
    }

    /**
     * Converts an existing attachment: main file and every sub-size, then updates the attachment.
     *
     * @return array|false [original_size, optimized_size, original_file, files (old relative path => new relative path)]
     */
    public function optimize_existing(int $attachment_id) {
        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file) || !$this->is_source_image($file)) {
            return false;
        }

        $params   = $this->get_params();
        $preserve = $this->preserve_original();
        $metadata = wp_get_attachment_metadata($attachment_id);
        $metadata = is_array($metadata) ? $metadata : [];

        $original_size = filesize($file);
        $new_file      = $this->convert_to_new_file($file, $params);
        if (!$new_file) {
            return false;
        }

        // Source path => converted path. Several sizes can share the same file.
        $converted = [ $file => $new_file ];
        $dirname   = dirname($file);

        if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size_key => $size) {
                if (empty($size['file'])) {
                    continue;
                }

                $thumb_path = $dirname . '/' . $size['file'];

                if (!isset($converted[$thumb_path])) {
                    if (!$this->is_source_image($thumb_path) || !file_exists($thumb_path)) {
                        continue;
                    }
                    // Sub-sizes keep their exact dimensions.
                    $new_thumb = $this->convert_to_new_file($thumb_path, array_merge($params, ['max_width' => 0]));
                    if (!$new_thumb) {
                        continue;
                    }
                    $converted[$thumb_path] = $new_thumb;
                }

                $new_thumb = $converted[$thumb_path];
                $metadata['sizes'][$size_key]['file']      = basename($new_thumb);
                $metadata['sizes'][$size_key]['mime-type'] = wp_check_filetype($new_thumb)['type'];
                $metadata['sizes'][$size_key]['filesize']  = filesize($new_thumb);
            }
        }

        $metadata['file']     = _wp_relative_upload_path($new_file);
        $metadata['filesize'] = filesize($new_file);
        $dimensions = @getimagesize($new_file); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        if ($dimensions) {
            $metadata['width']  = (int) $dimensions[0];
            $metadata['height'] = (int) $dimensions[1];
        }

        update_attached_file($attachment_id, $new_file);
        wp_update_attachment_metadata($attachment_id, $metadata);

        wp_update_post([
            'ID'             => $attachment_id,
            'guid'           => esc_url_raw(wp_get_attachment_url($attachment_id)),
            'post_mime_type' => wp_check_filetype($new_file)['type'],
        ]);

        $files = [];
        foreach ($converted as $old_path => $new_path) {
            $files[_wp_relative_upload_path($old_path)] = _wp_relative_upload_path($new_path);

            // With "Preserve original" only the main original file is kept as a backup.
            if ($old_path === $file && $preserve) {
                continue;
            }
            wp_delete_file($old_path);
        }

        return [
            'original_size'  => $original_size,
            'optimized_size' => filesize($new_file),
            'original_file'  => $preserve ? $file : '',
            'files'          => $files,
        ];
    }

    /**
     * WordPress deletes the attachment files, but not the original kept as a backup.
     */
    public function delete_attachment_data($post_id) {
        $db  = Plugin::instance()->db;
        $row = $db->get_data((int) $post_id);
        if (!$row) {
            return;
        }

        $original = (string) $row->original_file;
        $basedir  = wp_normalize_path(trailingslashit(wp_get_upload_dir()['basedir']));
        if ($original !== '' && strpos(wp_normalize_path($original), $basedir) === 0 && file_exists($original)) {
            wp_delete_file($original);
        }

        $db->delete_meta((int) $post_id);
    }

}
