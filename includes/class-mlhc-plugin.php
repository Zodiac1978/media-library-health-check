<?php
/**
 * Main plugin controller.
 *
 * @package MediaLibraryHealthCheck
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the admin page and coordinates batch scans.
 */
final class MLHC_Plugin {
	private const PAGE_SLUG    = 'media-library-health-check';
	private const AJAX_ACTION  = 'mlhc_scan_media';
	private const NONCE_ACTION = 'mlhc_scan_media';
	private const BATCH_SIZE   = 20;

	/** Registers plugin hooks. */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_scan' ) );
	}

	/** Registers the health-check page in the Tools menu. */
	public static function register_page(): void {
		$hook_suffix = add_management_page(
			__( 'Media Library Health Check', 'media-library-health-check' ),
			__( 'Media Health Check', 'media-library-health-check' ),
			'upload_files',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);

		if ( $hook_suffix ) {
			add_action( 'load-' . $hook_suffix, array( __CLASS__, 'register_help_tab' ) );
		}
	}

	/** Registers contextual help tabs for the health-check page. */
	public static function register_help_tab(): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$screen->add_help_tab(
			array(
				'id'       => 'mlhc-overview',
				'title'    => __( 'Overview', 'media-library-health-check' ),
				'callback' => array( __CLASS__, 'render_help_overview' ),
			)
		);
		$screen->add_help_tab(
			array(
				'id'       => 'mlhc-errors',
				'title'    => __( 'Errors', 'media-library-health-check' ),
				'callback' => array( __CLASS__, 'render_help_errors' ),
			)
		);
		$screen->add_help_tab(
			array(
				'id'       => 'mlhc-warnings',
				'title'    => __( 'Warnings', 'media-library-health-check' ),
				'callback' => array( __CLASS__, 'render_help_warnings' ),
			)
		);
		$screen->add_help_tab(
			array(
				'id'       => 'mlhc-recommendations',
				'title'    => __( 'Recommendations', 'media-library-health-check' ),
				'callback' => array( __CLASS__, 'render_help_recommendations' ),
			)
		);
	}

	/** Renders the introductory contextual help tab. */
	public static function render_help_overview(): void {
		?>
		<h2><?php esc_html_e( 'Media Library Health Check', 'media-library-health-check' ); ?></h2>
		<p><?php esc_html_e( 'The scan reports potential problems without changing files or database records.', 'media-library-health-check' ); ?></p>
		<p><?php esc_html_e( 'Findings are grouped by urgency:', 'media-library-health-check' ); ?></p>
		<ul>
			<li><strong><?php esc_html_e( 'Errors', 'media-library-health-check' ); ?></strong> — <?php esc_html_e( 'Broken or inconsistent media data that should be investigated.', 'media-library-health-check' ); ?></li>
			<li><strong><?php esc_html_e( 'Warnings', 'media-library-health-check' ); ?></strong> — <?php esc_html_e( 'Potential compatibility, performance, or maintenance problems.', 'media-library-health-check' ); ?></li>
			<li><strong><?php esc_html_e( 'Recommendations', 'media-library-health-check' ); ?></strong> — <?php esc_html_e( 'Useful information that may not require action.', 'media-library-health-check' ); ?></li>
		</ul>
		<?php
	}

	/** Renders contextual help for error-level checks. */
	public static function render_help_errors(): void {
		?>
		<h2><?php esc_html_e( 'Error checks', 'media-library-health-check' ); ?></h2>
		<p><?php esc_html_e( 'Errors indicate missing, unreadable, or internally inconsistent media data.', 'media-library-health-check' ); ?></p>
		<ul>
			<li><?php esc_html_e( 'Missing or unreadable attachment files.', 'media-library-health-check' ); ?></li>
			<li><?php esc_html_e( 'Control characters in filenames.', 'media-library-health-check' ); ?></li>
			<li><?php esc_html_e( 'File extensions or stored MIME types that do not match the detected image type.', 'media-library-health-check' ); ?></li>
			<li><?php esc_html_e( 'Files that WordPress identifies as images but cannot read as images.', 'media-library-health-check' ); ?></li>
			<li><?php esc_html_e( 'Missing or incomplete attachment metadata.', 'media-library-health-check' ); ?></li>
			<li><?php esc_html_e( 'Missing generated image sizes or original images referenced by metadata.', 'media-library-health-check' ); ?></li>
		</ul>
		<?php
	}

	/** Renders contextual help for warning-level checks. */
	public static function render_help_warnings(): void {
		?>
		<h2><?php esc_html_e( 'Warning checks', 'media-library-health-check' ); ?></h2>
		<p><?php esc_html_e( 'Warnings identify media that may cause compatibility, performance, or maintenance problems.', 'media-library-health-check' ); ?></p>
		<ul>
			<li><?php esc_html_e( 'Umlauts, whitespace, possible double extensions, or filenames longer than 180 bytes.', 'media-library-health-check' ); ?></li>
			<li><?php esc_html_e( 'Filenames that are not NFC-normalized. This check requires the PHP intl extension.', 'media-library-health-check' ); ?></li>
			<li><?php esc_html_e( 'Image files larger than 10 MB.', 'media-library-health-check' ); ?></li>
			<li><?php esc_html_e( 'Active full-size images with an edge longer than 2560 pixels and no separately preserved upload original.', 'media-library-health-check' ); ?></li>
			<li><?php esc_html_e( 'Upload originals above 2560 pixels that WordPress preserves alongside scaled images, including their additional storage usage.', 'media-library-health-check' ); ?></li>
			<li><?php esc_html_e( 'Images containing more than 20 megapixels.', 'media-library-health-check' ); ?></li>
			<li><?php esc_html_e( 'JPEG files that are likely encoded as CMYK or YCCK.', 'media-library-health-check' ); ?></li>
			<li><?php esc_html_e( 'Attachments with comments enabled.', 'media-library-health-check' ); ?></li>
		</ul>
		<?php
	}

	/** Renders contextual help for recommendation-level checks. */
	public static function render_help_recommendations(): void {
		?>
		<h2><?php esc_html_e( 'Recommendation checks', 'media-library-health-check' ); ?></h2>
		<p><?php esc_html_e( 'Recommendations provide useful context but do not necessarily require a change.', 'media-library-health-check' ); ?></p>
		<ul>
			<li><?php esc_html_e( 'Animated GIF and WebP files whose generated sizes may not preserve animation.', 'media-library-health-check' ); ?></li>
		</ul>
		<?php
	}

	/**
	 * Enqueues page-specific styles, scripts, and translated data.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( 'tools_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		$plugin_url = plugin_dir_url( MLHC_PLUGIN_FILE );
		wp_enqueue_style( 'mlhc-admin', $plugin_url . 'assets/admin.css', array(), MLHC_VERSION );
		wp_enqueue_script( 'mlhc-admin', $plugin_url . 'assets/admin.js', array(), MLHC_VERSION, true );

		$data = array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'action'  => self::AJAX_ACTION,
			'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
			'i18n'    => array(
				'starting'        => __( 'Preparing scan…', 'media-library-health-check' ),
				'running'         => __( 'Scanning the Media Library…', 'media-library-health-check' ),
				'finished'        => __( 'Scan complete.', 'media-library-health-check' ),
				'stopped'         => __( 'Scan stopped.', 'media-library-health-check' ),
				'error'           => __( 'The scan stopped because of an error.', 'media-library-health-check' ),
				'healthy'         => __( 'No health issues were found.', 'media-library-health-check' ),
				'confirmRestart'  => __( 'The current scan results will be discarded. Start a new scan?', 'media-library-health-check' ),
				'runningButton'   => __( 'Scan in progress…', 'media-library-health-check' ),
				'restartButton'   => __( 'Run scan again', 'media-library-health-check' ),
				'unknownFile'     => __( '(unknown file)', 'media-library-health-check' ),
				'findingsOne'     => __( 'Scan complete: 1 finding.', 'media-library-health-check' ),
				/* translators: %s: Number of findings. */
				'findingsMany'    => __( 'Scan complete: %s findings.', 'media-library-health-check' ),
				'files'           => __( 'Files', 'media-library-health-check' ),
				'images'          => __( 'Images', 'media-library-health-check' ),
				'wordpress'       => __( 'WordPress', 'media-library-health-check' ),
				'errorSeverity'   => __( 'Error', 'media-library-health-check' ),
				'warningSeverity' => __( 'Warning', 'media-library-health-check' ),
				'infoSeverity'    => __( 'Recommendation', 'media-library-health-check' ),
				/* translators: 1: attachment ID, 2: filename. */
				'attachmentLabel' => __( 'Attachment #%1$s: %2$s', 'media-library-health-check' ),
			),
		);

		wp_add_inline_script( 'mlhc-admin', 'window.MLHCData = ' . wp_json_encode( $data ) . ';', 'before' );
	}

	/** Renders the Media Library Health Check page. */
	public static function render_page(): void {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'media-library-health-check' ) );
		}
		?>
		<div class="wrap mlhc-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Audit files, images, and WordPress metadata. The scan is read-only and does not change files or database records.', 'media-library-health-check' ); ?>
			</p>

			<div class="mlhc-actions">
				<button type="button" class="button button-primary" id="mlhc-start">
					<?php esc_html_e( 'Start health check', 'media-library-health-check' ); ?>
				</button>
				<button type="button" class="button" id="mlhc-stop" disabled>
					<?php esc_html_e( 'Stop', 'media-library-health-check' ); ?>
				</button>
			</div>

			<div id="mlhc-progress-panel" class="mlhc-panel" hidden>
				<div class="mlhc-progress-meta">
					<strong id="mlhc-status" aria-live="polite"></strong>
					<span id="mlhc-percent">0%</span>
				</div>
				<div class="mlhc-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="<?php esc_attr_e( 'Scan progress', 'media-library-health-check' ); ?>">
					<div id="mlhc-progress-bar" class="mlhc-progress-bar"></div>
				</div>
				<div class="mlhc-stats">
					<div><span><?php esc_html_e( 'Scanned', 'media-library-health-check' ); ?></span><strong id="mlhc-scanned">0</strong><small id="mlhc-total">/ 0</small></div>
					<div><span><?php esc_html_e( 'Healthy', 'media-library-health-check' ); ?></span><strong id="mlhc-healthy">0</strong></div>
					<div><span><?php esc_html_e( 'Errors', 'media-library-health-check' ); ?></span><strong id="mlhc-errors">0</strong></div>
					<div><span><?php esc_html_e( 'Warnings', 'media-library-health-check' ); ?></span><strong id="mlhc-warnings">0</strong></div>
					<div><span><?php esc_html_e( 'Recommendations', 'media-library-health-check' ); ?></span><strong id="mlhc-info">0</strong></div>
				</div>
			</div>

			<div id="mlhc-notice" class="notice inline" role="status" hidden><p></p></div>
			<div id="mlhc-unavailable" class="notice notice-info inline" hidden>
				<p><strong><?php esc_html_e( 'Checks unavailable on this server:', 'media-library-health-check' ); ?></strong></p>
				<ul></ul>
			</div>

			<section id="mlhc-results" hidden aria-labelledby="mlhc-results-heading">
				<h2 id="mlhc-results-heading"><?php esc_html_e( 'Findings', 'media-library-health-check' ); ?></h2>
				<div class="mlhc-filters" role="group" aria-label="<?php esc_attr_e( 'Filter findings', 'media-library-health-check' ); ?>">
					<button type="button" class="button is-active" data-filter="all" aria-pressed="true"><?php esc_html_e( 'All', 'media-library-health-check' ); ?> <span id="mlhc-filter-all">0</span></button>
					<button type="button" class="button" data-filter="error" aria-pressed="false"><?php esc_html_e( 'Errors', 'media-library-health-check' ); ?> <span id="mlhc-filter-error">0</span></button>
					<button type="button" class="button" data-filter="warning" aria-pressed="false"><?php esc_html_e( 'Warnings', 'media-library-health-check' ); ?> <span id="mlhc-filter-warning">0</span></button>
					<button type="button" class="button" data-filter="info" aria-pressed="false"><?php esc_html_e( 'Recommendations', 'media-library-health-check' ); ?> <span id="mlhc-filter-info">0</span></button>
				</div>
				<div class="mlhc-table-wrap">
					<table class="widefat striped">
						<caption class="screen-reader-text"><?php esc_html_e( 'Media Library health findings', 'media-library-health-check' ); ?></caption>
						<thead><tr>
							<th scope="col"><?php esc_html_e( 'Attachment', 'media-library-health-check' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Category', 'media-library-health-check' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Severity', 'media-library-health-check' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Finding', 'media-library-health-check' ); ?></th>
						</tr></thead>
						<tbody id="mlhc-results-body"></tbody>
					</table>
				</div>
				<p id="mlhc-no-filter-results" hidden><?php esc_html_e( 'No findings match this filter.', 'media-library-health-check' ); ?></p>
			</section>
		</div>
		<?php
	}

	/** Processes one attachment batch and sends its findings as JSON. */
	public static function ajax_scan(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to run this scan.', 'media-library-health-check' ) ), 403 );
		}

		$last_id = isset( $_POST['lastId'] ) && is_scalar( $_POST['lastId'] ) ? absint( wp_unslash( $_POST['lastId'] ) ) : 0;
		$total   = isset( $_POST['total'] ) && is_scalar( $_POST['total'] ) ? absint( wp_unslash( $_POST['total'] ) ) : 0;

		if ( 0 === $last_id || 0 === $total ) {
			$total = self::count_attachments();
		}

		/**
		 * Filters the number of attachments processed per AJAX request.
		 *
		 * Values are constrained to the range from 1 to 200.
		 *
		 * @param int $batch_size Number of attachments per request. Default 20.
		 */
		$batch_size = (int) apply_filters( 'mlhc_batch_size', self::BATCH_SIZE );
		$batch_size = max( 1, min( 200, $batch_size ) );
		$ids        = self::get_next_attachment_ids( $last_id, $batch_size );
		$checks     = self::get_checks();
		$items      = array();
		$healthy    = 0;

		foreach ( $ids as $attachment_id ) {
			$item = self::inspect_attachment( $attachment_id, $checks );
			if ( empty( $item['findings'] ) ) {
				++$healthy;
			} else {
				$items[] = $item;
			}
		}

		$new_last_id = empty( $ids ) ? $last_id : (int) end( $ids );

		wp_send_json_success(
			array(
				'total'       => $total,
				'processed'   => count( $ids ),
				'healthy'     => $healthy,
				'lastId'      => $new_last_id,
				'done'        => count( $ids ) < $batch_size,
				'items'       => $items,
				'unavailable' => 0 === $last_id ? self::get_unavailable_checks( $checks ) : array(),
			)
		);
	}

	/**
	 * Gets all registered checks.
	 *
	 * @return MLHC_Check[] Registered checks.
	 */
	private static function get_checks(): array {
		$checks = array(
			new MLHC_Missing_File_Check(),
			new MLHC_Filename_Check(),
			new MLHC_Unicode_Normalization_Check(),
			new MLHC_File_Type_Check(),
			new MLHC_Image_Size_Check(),
			new MLHC_Preserved_Original_Check(),
			new MLHC_Cmyk_Check(),
			new MLHC_Animated_Image_Check(),
			new MLHC_Metadata_Check(),
			new MLHC_Open_Comments_Check(),
		);

		/**
		 * Filters registered Media Library health checks.
		 *
		 * @param MLHC_Check[] $checks Registered check objects.
		 */
		return apply_filters( 'mlhc_checks', $checks );
	}

	/**
	 * Returns unavailable check explanations.
	 *
	 * @param MLHC_Check[] $checks Registered checks.
	 * @return array<int,array{id:string,reason:string}> Unavailable checks.
	 */
	private static function get_unavailable_checks( array $checks ): array {
		$unavailable = array();
		foreach ( $checks as $check ) {
			if ( $check instanceof MLHC_Check && ! $check->is_available() ) {
				$unavailable[] = array(
					'id'     => $check->get_id(),
					'reason' => $check->get_unavailable_reason(),
				);
			}
		}

		return $unavailable;
	}

	/**
	 * Runs all available checks for one attachment.
	 *
	 * @param int          $attachment_id Attachment post ID.
	 * @param MLHC_Check[] $checks        Registered checks.
	 * @return array{id:int,name:string,editUrl:string,mime:string,dimensions:string,fileSize:string,findings:array<int,array<string,string>>} Result item.
	 */
	private static function inspect_attachment( int $attachment_id, array $checks ): array {
		$context  = new MLHC_Attachment_Context( $attachment_id );
		$findings = array();

		foreach ( $checks as $check ) {
			if ( ! $check instanceof MLHC_Check || ! $check->is_available() ) {
				continue;
			}
			foreach ( $check->run( $context ) as $result ) {
				if ( $result instanceof MLHC_Check_Result ) {
					$findings[] = $result->to_array();
				}
			}
		}

		$width      = $context->get_width();
		$height     = $context->get_height();
		$dimensions = $width && $height ? sprintf( '%1$s × %2$s px', number_format_i18n( $width ), number_format_i18n( $height ) ) : '';

		return array(
			'id'         => $attachment_id,
			'name'       => $context->filename ? $context->filename : get_the_title( $attachment_id ),
			'editUrl'    => $context->edit_url,
			'mime'       => $context->post->post_mime_type,
			'dimensions' => $dimensions,
			'fileSize'   => $context->file_size ? size_format( $context->file_size, 1 ) : '',
			'findings'   => $findings,
		);
	}

	/** Counts non-trashed Media Library attachments. */
	private static function count_attachments(): int {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s AND post_status <> %s",
			'attachment',
			'trash'
		);

		// A fresh count is required for scan progress; caching would make the total stale.
		return (int) $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Gets the next attachment IDs using keyset pagination.
	 *
	 * @param int $last_id Last processed attachment ID.
	 * @param int $limit   Maximum number of IDs.
	 * @return int[] Attachment IDs.
	 */
	private static function get_next_attachment_ids( int $last_id, int $limit ): array {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts}
			WHERE post_type = %s
			AND post_status <> %s
			AND ID > %d
			ORDER BY ID ASC
			LIMIT %d",
			'attachment',
			'trash',
			$last_id,
			$limit
		);

		// Results are cursor-specific and intentionally not cached.
		return array_map( 'intval', $wpdb->get_col( $query ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
