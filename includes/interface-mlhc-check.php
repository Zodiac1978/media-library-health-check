<?php
/**
 * Contract for Media Library Health Check rules.
 *
 * @package MediaLibraryHealthCheck
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines a single, independently executable attachment check.
 */
interface MLHC_Check {
	/**
	 * Gets the check identifier.
	 *
	 * @return string Check identifier.
	 */
	public function get_id(): string;

	/**
	 * Determines whether the check is available.
	 *
	 * @return bool Whether the check is available.
	 */
	public function is_available(): bool;

	/**
	 * Gets the reason an unavailable check cannot run.
	 *
	 * @return string Unavailability reason or an empty string.
	 */
	public function get_unavailable_reason(): string;

	/**
	 * Inspects one attachment using its shared context.
	 *
	 * @param MLHC_Attachment_Context $context Attachment context.
	 * @return MLHC_Check_Result[] Findings for the attachment.
	 */
	public function run( MLHC_Attachment_Context $context ): array;
}
