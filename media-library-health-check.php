<?php
/**
 * Plugin Name: Media Library Health Check
 * Description: Audits the WordPress Media Library for file, image, and metadata problems without changing any data.
 * Version: 2.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: OpenAI
 * License: GPL-2.0-or-later
 * Text Domain: media-library-health-check
 *
 * @package MediaLibraryHealthCheck
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MLHC_VERSION', '2.1.0' );
define( 'MLHC_PLUGIN_FILE', __FILE__ );

require_once __DIR__ . '/includes/interface-mlhc-check.php';
require_once __DIR__ . '/includes/class-mlhc-check-result.php';
require_once __DIR__ . '/includes/class-mlhc-attachment-context.php';
require_once __DIR__ . '/includes/class-mlhc-abstract-check.php';
require_once __DIR__ . '/includes/class-mlhc-plugin.php';

MLHC_Plugin::init();
