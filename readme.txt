=== Media Library Health Check ===
Contributors: openai
Tags: media, images, health check, metadata, audit
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 2.1.0
License: GPLv2 or later

Audits the WordPress Media Library for file, image, and metadata problems without changing data.

== Description ==

Media Library Health Check adds a read-only audit page under Tools > Media Health Check.

The batch-based scan checks:

* Missing or unreadable attachment files.
* Filename portability, umlauts, whitespace, double extensions, and Unicode NFC normalization.
* Image extension, detected MIME type, and the MIME type stored by WordPress.
* Active full-size images above 2560 pixels, including older uploads that predate WordPress scaling.
* Large upload originals preserved alongside scaled images, including their additional storage usage.
* Images above 20 megapixels and active full-size files above 10 MB.
* CMYK or YCCK JPEG files without requiring Imagick.
* Animated GIF and WebP files.
* Missing image metadata, generated image sizes, and original image files.
* Open attachment comments.

The scanner uses keyset pagination and loads shared attachment data once per item. It never changes files or database records.

== Installation ==

1. Upload the plugin directory to `/wp-content/plugins/` or install its ZIP file.
2. Activate the plugin.
3. Open Tools > Media Health Check.
4. Select Start health check.

== Frequently Asked Questions ==

= Does the plugin repair findings? =

No. Detection and repair are intentionally separated. Version 2.0 is read-only.

= Why can the Unicode check be unavailable? =

The Unicode NFC check requires the PHP intl extension. The scan clearly reports when that check cannot run.

== Filters ==

The batch size can be changed with the `mlhc_batch_size` filter. Values are constrained to 1–200; the default is 20.

Additional checks can be registered with the `mlhc_checks` filter by providing objects that implement `MLHC_Check`.

== Changelog ==

= 2.1.0 =

* Split the 2560-pixel audit into active oversized images and preserved upload originals.
* Added original dimensions and storage usage to preserved-original findings.

= 2.0.3 =

* Split contextual help into Overview, Errors, Warnings, and Recommendations tabs.
* Documented the severity of every built-in check.

= 2.0.2 =

* Moved the check overview to the native WordPress contextual Help tab.
* Removed the non-interactive scan coverage labels from the page.

= 2.0.1 =

* Removed uppercase filename recommendations to avoid excessive low-value findings.
* Removed empty alternative text findings because accessibility requirements depend on each usage context.

= 2.0.0 =

* Renamed the plugin to Media Library Health Check.
* Added extensible checks across Files, Images, and WordPress.
* Added severity filters and accessible progress reporting.
