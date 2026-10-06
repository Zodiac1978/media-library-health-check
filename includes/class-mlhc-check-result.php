<?php
/**
 * Value object for one health-check finding.
 *
 * @package MediaLibraryHealthCheck
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Represents a single problem found by a check.
 */
final class MLHC_Check_Result {
	/**
	 * Check identifier.
	 *
	 * @var string
	 */
	private $check_id;

	/**
	 * Finding category.
	 *
	 * @var string
	 */
	private $category;

	/**
	 * Finding severity.
	 *
	 * @var string
	 */
	private $severity;

	/**
	 * Short finding label.
	 *
	 * @var string
	 */
	private $label;

	/**
	 * Finding explanation.
	 *
	 * @var string
	 */
	private $message;

	/**
	 * Creates a check result.
	 *
	 * @param string $check_id Check identifier.
	 * @param string $category Result category.
	 * @param string $severity Result severity: error, warning, or info.
	 * @param string $label    Short result label.
	 * @param string $message  Result explanation.
	 */
	public function __construct( string $check_id, string $category, string $severity, string $label, string $message ) {
		$this->check_id = $check_id;
		$this->category = $category;
		$this->severity = $severity;
		$this->label    = $label;
		$this->message  = $message;
	}

	/**
	 * Converts the result to JSON-ready data.
	 *
	 * @return array{checkId:string,category:string,severity:string,label:string,message:string} Result data.
	 */
	public function to_array(): array {
		return array(
			'checkId'  => $this->check_id,
			'category' => $this->category,
			'severity' => $this->severity,
			'label'    => $this->label,
			'message'  => $this->message,
		);
	}
}
