<p align="center">
  <img src=".wordpress-org/banner-1544x500.png" alt="GioCompress — automatic WebP image optimization for WordPress">
</p>

<h1 align="center">GioCompress</h1>

<p align="center">
  Automatic WebP conversion, smart lazy loading and missing alt text for WordPress — processed entirely on your server.
</p>

<p align="center">
  <a href="https://wordpress.org/plugins/giocompress/"><img src="https://img.shields.io/wordpress/plugin/v/giocompress?label=WordPress.org&logo=wordpress" alt="WordPress.org version"></a>
  <a href="https://wordpress.org/plugins/giocompress/"><img src="https://img.shields.io/wordpress/plugin/installs/giocompress" alt="Active installs"></a>
  <a href="https://wordpress.org/plugins/giocompress/"><img src="https://img.shields.io/wordpress/plugin/tested/giocompress" alt="Tested up to"></a>
  <img src="https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white" alt="PHP 7.4+">
  <a href="https://github.com/GiovanniBevacqua/giocompress/actions/workflows/php-lint.yml"><img src="https://github.com/GiovanniBevacqua/giocompress/actions/workflows/php-lint.yml/badge.svg" alt="PHP Lint"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-GPLv2%2B-blue" alt="License: GPLv2 or later"></a>
</p>

---

## About

Heavy JPG and PNG images are one of the most common causes of slow WordPress sites. **GioCompress** converts every
uploaded image to WebP automatically, lazy-loads images without hurting your LCP and fills in missing `alt` attributes
for better SEO and accessibility.

All processing happens **on your own server** with GD or Imagick: no external API, no account, no images sent to third parties.

## Features

- **Automatic WebP conversion on upload** — every new image and all its generated sizes are converted.
- **Optimize existing images** — convert your current media library (5 images per day in the free version).
- **Safe conversion of existing images** — old JPG/PNG URLs in posts, excerpts and post meta (including page builder data) are updated, and requests for the old files are redirected (301) to the converted file.
- **Correct orientation** — smartphone photos keep the right orientation (EXIF orientation is applied) and their color profile.
- **No overwrites** — uploading a second `photo.jpg` never replaces an existing `photo.webp`.
- **Smart lazy loading** — adds `loading="lazy"` to images anywhere on the page, skipping the first images and those marked `fetchpriority="high"` to protect your LCP.
- **Missing alt text** — generates `alt` attributes for images that have none or an empty one, from the media title, caption, file name or parent post.
- **Works with any theme or builder** — Gutenberg, Elementor, Divi and others.

## Requirements

- WordPress 6.2 or later
- PHP 7.4 or later
- The **GD** extension with WebP support, or **Imagick** (recommended)

## Installation

**From WordPress.org (recommended)**

1. In your WordPress admin, go to **Plugins → Add New** and search for **GioCompress**.
2. Click **Install Now**, then **Activate**.
3. Open **GioCompress → Settings** in the admin menu to review the options.

New uploads are optimized automatically. To convert existing images, use **Optimize Existing Images** in the settings.

**From GitHub**

Download the latest zip from the [Releases](https://github.com/GiovanniBevacqua/giocompress/releases) page and upload it from
**Plugins → Add New → Upload Plugin**, or clone the repository into `wp-content/plugins/`:

```bash
git clone https://github.com/GiovanniBevacqua/giocompress.git wp-content/plugins/giocompress
```

> In the free version the original JPG/PNG file is replaced by the WebP version. Back up your media library before
> optimizing existing images.

## For developers

| Hook | Type | Description |
| --- | --- | --- |
| `giocompress_optimization_params` | filter | Parameters used for each conversion (format, quality, …). |
| `giocompress_exif_orientation` | filter | EXIF orientation applied to an image. Return `1` to keep the image as stored. |
| `giocompress/replace_urls_in_meta` | filter | Whether old image URLs are also replaced in post meta when existing images are converted. Default `true`. |

```php
// Do not touch post meta when replacing URLs of converted images.
add_filter( 'giocompress/replace_urls_in_meta', '__return_false' );

// Keep images exactly as stored, ignoring the EXIF orientation.
add_filter( 'giocompress_exif_orientation', function () {
    return 1;
} );
```

## GioCompress Pro

[GioCompress Pro](https://giosuite.com/giocompress) adds unlimited optimization of existing images, AVIF support,
custom quality, lossy/lossless modes, automatic resizing, EXIF/IPTC removal, the option to keep original files,
detailed reports and orphan cleanup. The Pro add-on is distributed separately and is not part of this repository.

## Contributing

Contributions are welcome! Please read [CONTRIBUTING.md](CONTRIBUTING.md) before opening an issue or a pull request.
For usage questions, use the [WordPress.org support forum](https://wordpress.org/support/plugin/giocompress/).

To report a security vulnerability, please follow [SECURITY.md](SECURITY.md) and **do not** open a public issue.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

GioCompress is free software, released under the [GNU General Public License v2.0 or later](LICENSE).
The bundled [Chart.js](https://www.chartjs.org/) library is released under the MIT License (see `assets/js/LICENSE.txt`).

Made by [Giovanni Bevacqua](https://www.linkedin.com/in/giovanni-bevacqua/) · [giosuite.com](https://giosuite.com)
