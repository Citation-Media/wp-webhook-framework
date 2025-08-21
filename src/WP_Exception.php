<?php
/**
 * WordPress Exception class for webhook framework.
 *
 * @package Citation\WP_Webhook_Framework
 */

declare(strict_types=1);

namespace Citation\WP_Webhook_Framework;

use Exception;

/**
 * Exception class for WordPress webhook framework operations.
 *
 * Extends PHP's base Exception class to provide WordPress-specific
 * error handling with error codes and additional data context.
 * Designed for use with Action Scheduler and webhook processing.
 */
class WP_Exception extends Exception {

	/**
	 * Error code identifier.
	 *
	 * @var string
	 */
	protected string $error_code;

	/**
	 * Additional error data context.
	 *
	 * @var array<string,mixed>
	 */
	protected array $error_data;

	/**
	 * Constructor.
	 *
	 * @param string                $error_code Error code identifier.
	 * @param string                $message    Exception message.
	 * @param array<string,mixed>   $data       Additional error data.
	 * @param int                   $code       Exception code (optional).
	 * @param Exception|null        $previous   Previous exception (optional).
	 */
	public function __construct(
		string $error_code,
		string $message = '',
		array $data = array(),
		int $code = 0,
		?Exception $previous = null
	) {
		$this->error_code = $error_code;
		$this->error_data = $data;

		parent::__construct( $message, $code, $previous );
	}

	/**
	 * Get the error code.
	 *
	 * @return string The error code.
	 */
	public function getErrorCode(): string {
		return $this->error_code;
	}

	/**
	 * Get the error data.
	 *
	 * @return array<string,mixed> The error data.
	 */
	public function getErrorData(): array {
		return $this->error_data;
	}

	/**
	 * Get specific error data by key.
	 *
	 * @param string $key     The data key.
	 * @param mixed  $default Default value if key not found.
	 * @return mixed The error data value or default.
	 */
	public function getErrorDataValue( string $key, $default = null ) {
		return $this->error_data[ $key ] ?? $default;
	}
}