<?php
/**
 * Built-in Media Library health checks, beginning with their shared base class.
 *
 * @package MediaLibraryHealthCheck
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The built-in, stateless checks are intentionally kept together as one registry unit.
// phpcs:disable WordPress.Files.FileName.InvalidClassFileName, Generic.Files.OneObjectStructurePerFile.MultipleFound

/**
 * Provides default availability behavior for checks.
 */
abstract class MLHC_Abstract_Check implements MLHC_Check {
	/** Category identifier for WordPress data findings. */
	protected const CATEGORY_WORDPRESS = 'wordpress';

	/**
	 * Determines whether the check is available.
	 *
	 * @return bool Whether the check is available.
	 */
	public function is_available(): bool {
		return true;
	}

	/**
	 * Gets the reason the check is unavailable.
	 *
	 * @return string Unavailability reason.
	 */
	public function get_unavailable_reason(): string {
		return '';
	}

	/**
	 * Creates a finding.
	 *
	 * @param string $category Category identifier.
	 * @param string $severity Severity identifier.
	 * @param string $label    Short label.
	 * @param string $message  Explanation.
	 * @return MLHC_Check_Result Finding.
	 */
	protected function result( string $category, string $severity, string $label, string $message ): MLHC_Check_Result {
		return new MLHC_Check_Result( $this->get_id(), $category, $severity, $label, $message );
	}
}

/** Checks whether an attachment's original file exists and is readable. */
final class MLHC_Missing_File_Check extends MLHC_Abstract_Check {
	/**
	 * Gets the check identifier.
	 *
	 * @return string Check identifier.
	 */
	public function get_id(): string {
		return 'missing_file';
	}

	/**
	 * Checks whether the file is readable.
	 *
	 * @param MLHC_Attachment_Context $context Attachment context.
	 * @return MLHC_Check_Result[] Findings.
	 */
	public function run( MLHC_Attachment_Context $context ): array {
		if ( $context->file_readable ) {
			return array();
		}

		return array(
			$this->result(
				'files',
				'error',
				__( 'File missing or unreadable', 'media-library-health-check' ),
				__( 'The attachment points to a file that does not exist or cannot be read.', 'media-library-health-check' )
			),
		);
	}
}

/** Checks filename portability and common naming problems. */
final class MLHC_Filename_Check extends MLHC_Abstract_Check {
	/**
	 * Gets the check identifier.
	 *
	 * @return string Check identifier.
	 */
	public function get_id(): string {
		return 'filename';
	}

	/**
	 * Checks filename portability.
	 *
	 * @param MLHC_Attachment_Context $context Attachment context.
	 * @return MLHC_Check_Result[] Findings.
	 */
	public function run( MLHC_Attachment_Context $context ): array {
		$results  = array();
		$filename = $context->filename;

		if ( '' === $filename ) {
			return $results;
		}

		if ( preg_match( '/[äöüÄÖÜß]/u', $filename ) ) {
			$results[] = $this->result(
				'files',
				'warning',
				__( 'Umlaut in filename', 'media-library-health-check' ),
				__( 'The filename contains an umlaut or ß, which can reduce portability between systems.', 'media-library-health-check' )
			);
		}

		if ( preg_match( '/\s/u', $filename ) ) {
			$results[] = $this->result(
				'files',
				'warning',
				__( 'Whitespace in filename', 'media-library-health-check' ),
				__( 'The filename contains whitespace.', 'media-library-health-check' )
			);
		}

		if ( preg_match( '/[\x00-\x1F\x7F]/', $filename ) ) {
			$results[] = $this->result(
				'files',
				'error',
				__( 'Control character in filename', 'media-library-health-check' ),
				__( 'The filename contains a control character and may be unsafe to process.', 'media-library-health-check' )
			);
		}

		if ( preg_match( '/\.[a-z0-9]{2,5}\.[a-z0-9]{2,5}$/i', $filename ) ) {
			$results[] = $this->result(
				'files',
				'warning',
				__( 'Possible double extension', 'media-library-health-check' ),
				__( 'The filename appears to contain two file extensions.', 'media-library-health-check' )
			);
		}

		if ( strlen( $filename ) > 180 ) {
			$results[] = $this->result(
				'files',
				'warning',
				__( 'Very long filename', 'media-library-health-check' ),
				__( 'The filename is longer than 180 bytes and may exceed filesystem limits after WordPress adds size suffixes.', 'media-library-health-check' )
			);
		}

		return $results;
	}
}

/** Checks whether a filename uses canonical NFC Unicode normalization. */
final class MLHC_Unicode_Normalization_Check extends MLHC_Abstract_Check {
	/**
	 * Gets the check identifier.
	 *
	 * @return string Check identifier.
	 */
	public function get_id(): string {
		return 'unicode_normalization';
	}

	/**
	 * Determines whether PHP intl normalization is available.
	 *
	 * @return bool Whether PHP intl normalization is available.
	 */
	public function is_available(): bool {
		return class_exists( 'Normalizer' );
	}

	/**
	 * Gets the unavailable-check explanation.
	 *
	 * @return string Unavailability reason.
	 */
	public function get_unavailable_reason(): string {
		return __( 'Unicode normalization requires the PHP intl extension.', 'media-library-health-check' );
	}

	/**
	 * Checks NFC normalization.
	 *
	 * @param MLHC_Attachment_Context $context Attachment context.
	 * @return MLHC_Check_Result[] Findings.
	 */
	public function run( MLHC_Attachment_Context $context ): array {
		if ( '' === $context->filename || Normalizer::isNormalized( $context->filename, Normalizer::FORM_C ) ) {
			return array();
		}

		return array(
			$this->result(
				'files',
				'warning',
				__( 'Filename is not NFC-normalized', 'media-library-health-check' ),
				__( 'The filename uses a decomposed or otherwise non-canonical Unicode representation.', 'media-library-health-check' )
			),
		);
	}
}

/** Checks the extension, stored MIME type, and detected image type. */
final class MLHC_File_Type_Check extends MLHC_Abstract_Check {
	/**
	 * Gets the check identifier.
	 *
	 * @return string Check identifier.
	 */
	public function get_id(): string {
		return 'file_type';
	}

	/**
	 * Checks stored and detected file types.
	 *
	 * @param MLHC_Attachment_Context $context Attachment context.
	 * @return MLHC_Check_Result[] Findings.
	 */
	public function run( MLHC_Attachment_Context $context ): array {
		if ( ! $context->is_image || ! $context->file_readable ) {
			return array();
		}

		if ( '' === $context->actual_mime ) {
			return array(
				$this->result(
					'images',
					'error',
					__( 'Unreadable image', 'media-library-health-check' ),
					__( 'WordPress identifies this attachment as an image, but its image type could not be detected.', 'media-library-health-check' )
				),
			);
		}

		$results            = array();
		$allowed_extensions = self::extensions_for_mime( $context->actual_mime );

		if ( ! empty( $allowed_extensions ) && ! in_array( $context->extension, $allowed_extensions, true ) ) {
			$results[] = $this->result(
				'files',
				'error',
				__( 'File extension does not match content', 'media-library-health-check' ),
				sprintf(
					/* translators: 1: detected MIME type, 2: comma-separated file extensions. */
					__( 'Detected as %1$s; expected extension: %2$s.', 'media-library-health-check' ),
					$context->actual_mime,
					implode( ', ', $allowed_extensions )
				)
			);
		}

		if ( $context->actual_mime !== $context->post->post_mime_type ) {
			$results[] = $this->result(
				self::CATEGORY_WORDPRESS,
				'error',
				__( 'Stored MIME type does not match content', 'media-library-health-check' ),
				sprintf(
					/* translators: 1: stored MIME type, 2: detected MIME type. */
					__( 'WordPress stores %1$s, but the file was detected as %2$s.', 'media-library-health-check' ),
					$context->post->post_mime_type,
					$context->actual_mime
				)
			);
		}

		return $results;
	}

	/**
	 * Returns registered extensions for a MIME type.
	 *
	 * @param string $mime MIME type.
	 * @return string[] Extensions.
	 */
	private static function extensions_for_mime( string $mime ): array {
		$extensions = array();
		foreach ( wp_get_mime_types() as $pattern => $registered_mime ) {
			if ( $mime === $registered_mime ) {
				$extensions = array_merge( $extensions, explode( '|', $pattern ) );
			}
		}

		return array_values( array_unique( array_map( 'strtolower', $extensions ) ) );
	}
}

/** Checks active image dimensions, pixel count, and compressed file size. */
final class MLHC_Image_Size_Check extends MLHC_Abstract_Check {
	private const LARGE_EDGE       = 2560;
	private const LARGE_MEGAPIXELS = 20;
	private const LARGE_FILE_BYTES = 10485760;

	/**
	 * Gets the check identifier.
	 *
	 * @return string Check identifier.
	 */
	public function get_id(): string {
		return 'image_size';
	}

	/**
	 * Checks image dimensions and file size.
	 *
	 * @param MLHC_Attachment_Context $context Attachment context.
	 * @return MLHC_Check_Result[] Findings.
	 */
	public function run( MLHC_Attachment_Context $context ): array {
		if ( ! $context->is_image || ! is_array( $context->image_info ) ) {
			return array();
		}

		$results    = array();
		$width      = $context->get_width();
		$height     = $context->get_height();
		$megapixels = ( $width * $height ) / 1000000;

		if ( ! $context->has_preserved_original && max( $width, $height ) > self::LARGE_EDGE ) {
			$results[] = $this->result(
				'images',
				'warning',
				__( 'Active image exceeds 2560 pixels', 'media-library-health-check' ),
				sprintf(
					/* translators: 1: image width, 2: image height. */
					__( 'The active full-size image is %1$s × %2$s pixels. It may predate WordPress 5.3 image scaling, or scaling may have been disabled.', 'media-library-health-check' ),
					number_format_i18n( $width ),
					number_format_i18n( $height )
				)
			);
		}

		if ( $megapixels > self::LARGE_MEGAPIXELS ) {
			$results[] = $this->result(
				'images',
				'warning',
				__( 'Very high pixel count', 'media-library-health-check' ),
				sprintf(
					/* translators: %s: megapixel count. */
					__( 'The image contains %s megapixels and may require substantial memory to process.', 'media-library-health-check' ),
					number_format_i18n( $megapixels, 1 )
				)
			);
		}

		if ( $context->file_size > self::LARGE_FILE_BYTES ) {
			$results[] = $this->result(
				'files',
				'warning',
				__( 'Large file', 'media-library-health-check' ),
				sprintf(
					/* translators: %s: human-readable file size. */
					__( 'The active full-size file is %s.', 'media-library-health-check' ),
					size_format( $context->file_size, 1 )
				)
			);
		}

		return $results;
	}
}

/** Checks for large upload originals preserved alongside scaled images. */
final class MLHC_Preserved_Original_Check extends MLHC_Abstract_Check {
	private const LARGE_EDGE = 2560;

	/**
	 * Gets the check identifier.
	 *
	 * @return string Check identifier.
	 */
	public function get_id(): string {
		return 'preserved_original';
	}

	/**
	 * Checks whether WordPress retained a large upload original.
	 *
	 * @param MLHC_Attachment_Context $context Attachment context.
	 * @return MLHC_Check_Result[] Findings.
	 */
	public function run( MLHC_Attachment_Context $context ): array {
		if ( ! $context->has_preserved_original || ! is_array( $context->original_image_info ) ) {
			return array();
		}

		$width  = $context->get_original_width();
		$height = $context->get_original_height();
		if ( max( $width, $height ) <= self::LARGE_EDGE ) {
			return array();
		}

		return array(
			$this->result(
				'images',
				'warning',
				__( 'Large upload original is preserved', 'media-library-health-check' ),
				sprintf(
					/* translators: 1: original filename, 2: image width, 3: image height, 4: human-readable file size. */
					__( 'WordPress preserved %1$s (%2$s × %3$s pixels, %4$s) alongside the scaled image. If it is no longer needed, this file consumes additional media and backup storage.', 'media-library-health-check' ),
					wp_basename( $context->original_file ),
					number_format_i18n( $width ),
					number_format_i18n( $height ),
					size_format( $context->original_file_size, 1 )
				)
			),
		);
	}
}

/** Checks image metadata, generated sizes, and original image references. */
final class MLHC_Metadata_Check extends MLHC_Abstract_Check {
	/**
	 * Gets the check identifier.
	 *
	 * @return string Check identifier.
	 */
	public function get_id(): string {
		return 'metadata';
	}

	/**
	 * Checks WordPress image metadata and referenced files.
	 *
	 * @param MLHC_Attachment_Context $context Attachment context.
	 * @return MLHC_Check_Result[] Findings.
	 */
	public function run( MLHC_Attachment_Context $context ): array {
		if ( ! $context->is_image ) {
			return array();
		}

		if ( ! is_array( $context->metadata ) || empty( $context->metadata['file'] ) ) {
			return array(
				$this->result(
					self::CATEGORY_WORDPRESS,
					'error',
					__( 'Attachment metadata missing', 'media-library-health-check' ),
					__( 'WordPress image metadata is missing or does not contain a file reference.', 'media-library-health-check' )
				),
			);
		}

		$results   = array();
		$directory = dirname( $context->file );
		$sizes     = isset( $context->metadata['sizes'] ) && is_array( $context->metadata['sizes'] ) ? $context->metadata['sizes'] : array();
		$missing   = array();

		foreach ( $sizes as $size_name => $size_data ) {
			if ( ! is_array( $size_data ) || empty( $size_data['file'] ) || ! is_file( $directory . '/' . basename( (string) $size_data['file'] ) ) ) {
				$missing[] = (string) $size_name;
			}
		}

		if ( ! empty( $missing ) ) {
			$results[] = $this->result(
				self::CATEGORY_WORDPRESS,
				'error',
				__( 'Generated image sizes missing', 'media-library-health-check' ),
				sprintf(
					/* translators: %s: comma-separated image size names. */
					__( 'Missing files for these registered sizes: %s.', 'media-library-health-check' ),
					implode( ', ', $missing )
				)
			);
		}

		if ( $context->has_preserved_original ) {
			if ( ! is_file( $context->original_file ) ) {
				$results[] = $this->result(
					self::CATEGORY_WORDPRESS,
					'error',
					__( 'Original image missing', 'media-library-health-check' ),
					__( 'WordPress metadata references an original image that is not present on disk.', 'media-library-health-check' )
				);
			}
		}

		return $results;
	}
}

/** Checks whether comments are enabled for an attachment. */
final class MLHC_Open_Comments_Check extends MLHC_Abstract_Check {
	/**
	 * Gets the check identifier.
	 *
	 * @return string Check identifier.
	 */
	public function get_id(): string {
		return 'open_comments';
	}

	/**
	 * Checks attachment comment status.
	 *
	 * @param MLHC_Attachment_Context $context Attachment context.
	 * @return MLHC_Check_Result[] Findings.
	 */
	public function run( MLHC_Attachment_Context $context ): array {
		if ( 'open' !== $context->post->comment_status ) {
			return array();
		}

		return array(
			$this->result(
				self::CATEGORY_WORDPRESS,
				'warning',
				__( 'Comments are open', 'media-library-health-check' ),
				__( 'Visitors may be able to submit comments on this attachment.', 'media-library-health-check' )
			),
		);
	}
}

/** Detects JPEG CMYK/YCCK data without requiring Imagick. */
final class MLHC_Cmyk_Check extends MLHC_Abstract_Check {
	/**
	 * Gets the check identifier.
	 *
	 * @return string Check identifier.
	 */
	public function get_id(): string {
		return 'cmyk';
	}

	/**
	 * Checks JPEG component count for likely CMYK or YCCK data.
	 *
	 * @param MLHC_Attachment_Context $context Attachment context.
	 * @return MLHC_Check_Result[] Findings.
	 */
	public function run( MLHC_Attachment_Context $context ): array {
		if ( 'image/jpeg' !== $context->actual_mime || ! $context->file_readable ) {
			return array();
		}

		if ( 4 !== self::get_jpeg_component_count( $context->file ) ) {
			return array();
		}

		return array(
			$this->result(
				'images',
				'warning',
				__( 'CMYK or YCCK JPEG', 'media-library-health-check' ),
				__( 'The JPEG contains four color components and is likely encoded as CMYK or YCCK.', 'media-library-health-check' )
			),
		);
	}

	/**
	 * Reads JPEG segments until a Start of Frame marker is found.
	 *
	 * @param string $file JPEG path.
	 * @return int Number of color components or zero.
	 */
	private static function get_jpeg_component_count( string $file ): int {
		// Direct streaming is required to stop after the JPEG header instead of loading a large image.
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fread, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		$handle = fopen( $file, 'rb' );
		if ( false === $handle || "\xFF\xD8" !== fread( $handle, 2 ) ) {
			if ( is_resource( $handle ) ) {
				fclose( $handle );
			}
			return 0;
		}

		$sof_markers = array( 0xC0, 0xC1, 0xC2, 0xC3, 0xC5, 0xC6, 0xC7, 0xC9, 0xCA, 0xCB, 0xCD, 0xCE, 0xCF );
		$components  = 0;

		while ( ! feof( $handle ) ) {
			$byte = fread( $handle, 1 );
			if ( "\xFF" !== $byte ) {
				continue;
			}

			do {
				$marker_byte = fread( $handle, 1 );
			} while ( "\xFF" === $marker_byte );

			if ( false === $marker_byte || '' === $marker_byte ) {
				break;
			}

			$marker = ord( $marker_byte );
			if ( 0xD9 === $marker || 0xDA === $marker ) {
				break;
			}
			if ( 0xD0 <= $marker && 0xD7 >= $marker ) {
				continue;
			}

			$length_data = fread( $handle, 2 );
			if ( 2 !== strlen( $length_data ) ) {
				break;
			}
			$length = unpack( 'nlength', $length_data );
			$length = isset( $length['length'] ) ? (int) $length['length'] : 0;
			if ( $length < 2 ) {
				break;
			}

			if ( in_array( $marker, $sof_markers, true ) ) {
				$sof = fread( $handle, min( 6, $length - 2 ) );
				if ( 6 === strlen( $sof ) ) {
					$components = ord( $sof[5] );
				}
				break;
			}

			fseek( $handle, $length - 2, SEEK_CUR );
		}

		fclose( $handle );
		// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fread, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $components;
	}
}

/** Detects animated GIF and WebP originals. */
final class MLHC_Animated_Image_Check extends MLHC_Abstract_Check {
	/**
	 * Gets the check identifier.
	 *
	 * @return string Check identifier.
	 */
	public function get_id(): string {
		return 'animated_image';
	}

	/**
	 * Checks for animation markers in GIF and WebP images.
	 *
	 * @param MLHC_Attachment_Context $context Attachment context.
	 * @return MLHC_Check_Result[] Findings.
	 */
	public function run( MLHC_Attachment_Context $context ): array {
		if ( ! $context->file_readable || ! in_array( $context->actual_mime, array( 'image/gif', 'image/webp' ), true ) ) {
			return array();
		}

		// The local read is capped at 2 MB and does not perform an HTTP request.
		$data = file_get_contents( $context->file, false, null, 0, 2097152 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $data ) {
			return array();
		}

		$is_animated = 'image/gif' === $context->actual_mime
			? preg_match_all( '/\x00\x21\xF9\x04.{4}\x00[\x2C\x21]/s', $data ) > 1
			: false !== strpos( $data, 'ANIM' );

		if ( ! $is_animated ) {
			return array();
		}

		return array(
			$this->result(
				'images',
				'info',
				__( 'Animated image', 'media-library-health-check' ),
				__( 'The original file is animated; generated image sizes may not preserve its animation.', 'media-library-health-check' )
			),
		);
	}
}
