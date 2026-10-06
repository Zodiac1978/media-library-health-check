<?php
/**
 * Shared attachment data used by all checks.
 *
 * @package MediaLibraryHealthCheck
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads expensive attachment properties once per scan pass.
 */
final class MLHC_Attachment_Context {
	/**
	 * Attachment post ID.
	 *
	 * @var int
	 */
	public $id;

	/**
	 * Attachment post object.
	 *
	 * @var WP_Post
	 */
	public $post;

	/**
	 * Absolute attachment file path.
	 *
	 * @var string
	 */
	public $file;

	/**
	 * Attachment filename.
	 *
	 * @var string
	 */
	public $filename;

	/**
	 * Lowercase filename extension.
	 *
	 * @var string
	 */
	public $extension;

	/**
	 * Whether the attachment file exists.
	 *
	 * @var bool
	 */
	public $file_exists;

	/**
	 * Whether the attachment file is readable.
	 *
	 * @var bool
	 */
	public $file_readable;

	/**
	 * File size in bytes.
	 *
	 * @var int
	 */
	public $file_size;

	/**
	 * Whether WordPress identifies the attachment as an image.
	 *
	 * @var bool
	 */
	public $is_image;

	/**
	 * MIME type detected from the image contents.
	 *
	 * @var string
	 */
	public $actual_mime;

	/**
	 * Image header data or false.
	 *
	 * @var array<int|string,mixed>|false
	 */
	public $image_info;

	/**
	 * WordPress attachment metadata or false.
	 *
	 * @var array<string,mixed>|false
	 */
	public $metadata;

	/**
	 * Whether WordPress metadata references a preserved upload original.
	 *
	 * @var bool
	 */
	public $has_preserved_original;

	/**
	 * Absolute path to the preserved upload original.
	 *
	 * @var string
	 */
	public $original_file;

	/**
	 * Whether the preserved upload original exists and is readable.
	 *
	 * @var bool
	 */
	public $original_file_readable;

	/**
	 * Preserved upload original size in bytes.
	 *
	 * @var int
	 */
	public $original_file_size;

	/**
	 * Preserved upload original image header data or false.
	 *
	 * @var array<int|string,mixed>|false
	 */
	public $original_image_info;

	/**
	 * Attachment edit URL.
	 *
	 * @var string
	 */
	public $edit_url;

	/**
	 * Builds a context from one attachment.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @throws InvalidArgumentException When the attachment ID is invalid.
	 */
	public function __construct( int $attachment_id ) {
		$post = get_post( $attachment_id );
		if ( ! $post instanceof WP_Post ) {
			throw new InvalidArgumentException( 'Invalid attachment ID.' );
		}

		$this->id            = $attachment_id;
		$this->post          = $post;
		$this->file          = (string) get_attached_file( $attachment_id, true );
		$this->filename      = basename( $this->file );
		$this->extension     = strtolower( (string) pathinfo( $this->filename, PATHINFO_EXTENSION ) );
		$this->file_exists   = '' !== $this->file && is_file( $this->file );
		$this->file_readable = $this->file_exists && is_readable( $this->file );
		$this->file_size     = $this->file_readable ? (int) filesize( $this->file ) : 0;
		$this->is_image      = wp_attachment_is_image( $attachment_id );
		$this->actual_mime   = $this->is_image && $this->file_readable ? (string) wp_get_image_mime( $this->file ) : '';
		$this->image_info    = $this->is_image && $this->file_readable ? wp_getimagesize( $this->file ) : false;
		$this->metadata      = wp_get_attachment_metadata( $attachment_id );

		$this->has_preserved_original = is_array( $this->metadata ) && ! empty( $this->metadata['original_image'] );
		$this->original_file          = '';
		$this->original_file_readable = false;
		$this->original_file_size     = 0;
		$this->original_image_info    = false;

		if ( $this->has_preserved_original && '' !== $this->file ) {
			$this->original_file          = path_join( dirname( $this->file ), wp_basename( (string) $this->metadata['original_image'] ) );
			$this->original_file_readable = is_file( $this->original_file ) && is_readable( $this->original_file );
			$this->original_file_size     = $this->original_file_readable ? wp_filesize( $this->original_file ) : 0;
			$this->original_image_info    = $this->original_file_readable ? wp_getimagesize( $this->original_file ) : false;
		}

		$this->edit_url = (string) get_edit_post_link( $attachment_id, 'raw' );
	}

	/**
	 * Gets the image width.
	 *
	 * @return int Image width or zero.
	 */
	public function get_width(): int {
		return is_array( $this->image_info ) && isset( $this->image_info[0] ) ? (int) $this->image_info[0] : 0;
	}

	/**
	 * Gets the image height.
	 *
	 * @return int Image height or zero.
	 */
	public function get_height(): int {
		return is_array( $this->image_info ) && isset( $this->image_info[1] ) ? (int) $this->image_info[1] : 0;
	}

	/**
	 * Gets the preserved upload original width.
	 *
	 * @return int Original image width or zero.
	 */
	public function get_original_width(): int {
		return is_array( $this->original_image_info ) && isset( $this->original_image_info[0] ) ? (int) $this->original_image_info[0] : 0;
	}

	/**
	 * Gets the preserved upload original height.
	 *
	 * @return int Original image height or zero.
	 */
	public function get_original_height(): int {
		return is_array( $this->original_image_info ) && isset( $this->original_image_info[1] ) ? (int) $this->original_image_info[1] : 0;
	}
}
