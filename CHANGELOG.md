# Changelog

All notable changes to GioCompress are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses [Semantic Versioning](https://semver.org/).

## [1.4.0]

### Fixed
- Optimizing existing images no longer breaks images already inserted in posts. Old JPG/PNG URLs are replaced in post content, excerpts and post meta (including page builder JSON), and requests for the old files are redirected (301) to the converted file. The redirect also repairs images converted by previous versions.
- Uploading an image with the same name as an existing one (e.g. a second `photo.jpg`) no longer overwrites the previously converted `photo.webp`.
- Photos taken with smartphones are no longer rotated after conversion (EXIF orientation is applied). The color profile is kept.
- Image sizes sharing the same file (e.g. two 150x150 sizes) are all converted, with no broken thumbnails.
- Attachment metadata, dimensions and MIME type are updated correctly after converting existing images.
- The free daily limit allowed 6 optimizations instead of 5 and counted images converted on upload.
- "Optimize All" counted failed images as optimized and could stall on network errors; the result notice was never shown.
- The renewal button pointed to a development URL.

### Security
- Removed an unprotected bulk optimization endpoint; AJAX requests now require a nonce and use POST.

### Changed
- Lazy loading skips the first images of the page and images marked with `fetchpriority="high"`, to avoid slowing down the LCP.
- Missing alt text lookup is faster and recognizes resized image URLs.
- The preserved original is deleted together with its attachment.
- Bundled translation files removed; translations are provided by translate.wordpress.org (and by GioCompress Pro).
- Quality, resizing and original preservation are applied only with an active Pro license, as described in the feature comparison.

## [1.0.0]

### Added
- Initial release.
- Automatic WebP optimization on upload, including scaled image sizes.
- Smart lazy loading.
- Automatic generation of missing or empty alt text.
- Option to delete original files.

[1.4.0]: https://github.com/GiovanniBevacqua/giocompress/releases/tag/v1.4.0
[1.0.0]: https://wordpress.org/plugins/giocompress/
