# Media Library Health Check

[![Donate with PayPal](https://img.shields.io/badge/PayPal-Donate-yellow.svg)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=LCH9UVV7RKDFY)

Media Library Health Check is a read-only WordPress plugin that audits the Media Library for file, image, and metadata problems without changing files or database records.

## Features

The batch-based scan checks for:

- Missing or unreadable attachment files.
- Filename portability issues, including umlauts, whitespace, double extensions, long filenames, and Unicode normalization.
- File extensions and stored MIME types that do not match the detected image type.
- Oversized images, large files, and preserved upload originals.
- Images with very high pixel counts.
- CMYK or YCCK JPEG files.
- Animated GIF and WebP files.
- Missing attachment metadata, generated image sizes, and original image files.
- Open attachment comments.

Findings are grouped into errors, warnings, and recommendations. The scanner uses batch processing and does not modify any media or WordPress data.

## Installation

1. Download the repository as a ZIP file or clone it into the `wp-content/plugins` directory.
2. Activate **Media Library Health Check** in the WordPress admin area.
3. Open **Tools → Media Health Check**.
4. Select **Start health check**.

## License

This plugin is licensed under the [GNU General Public License v2.0 or later](https://www.gnu.org/licenses/gpl-2.0.html).

## Stay up to date

You need to have [Git Updater](https://github.com/afragen/git-updater) by Andy Fragen installed to keep the plugin up to date directly from GitHub.

## Tests

Install the development dependencies and run the WordPress Coding Standards checks with:

```sh
composer install
composer lint
```

Check the PHP syntax locally with:

```sh
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
```
