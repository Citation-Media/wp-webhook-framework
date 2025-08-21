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
 * error handling with error codes. Designed for use with Action Scheduler
 * and webhook processing where meaningful exception messages are crucial.
 */
class WP_Exception extends Exception {

	/**
	 * Error code identifier.
	 *
	 * @var string
	 */
	protected string $error_code;

	/**
	 * Constructor.
	 *
	 * @param string         $error_code Error code identifier.
	 * @param string         $message    Exception message.
	 * @param int            $code       Exception code (optional).
	 * @param Exception|null $previous   Previous exception (optional).
	 */
	public function __construct(
		string $error_code,
		string $message = '',
		int $code = 0,
		?Exception $previous = null
	) {
		$this->error_code = $error_code;

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
}