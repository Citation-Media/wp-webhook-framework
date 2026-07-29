<?php
/**
 * Delivery mode enum for webhook emissions.
 *
 * @package juvo\WP_Webhook_Framework
 */

declare(strict_types=1);

namespace juvo\WP_Webhook_Framework;

/**
 * Controls whether webhook emissions are queued or delivered immediately.
 *
 * - SCHEDULED queues the webhook in Action Scheduler.
 * - IMMEDIATE sends the first attempt in the current request.
 */
enum Delivery_Mode: string {

	/**
	 * Queue delivery through Action Scheduler.
	 */
	case SCHEDULED = 'scheduled';

	/**
	 * Send delivery immediately in the current request.
	 */
	case IMMEDIATE = 'immediate';

	/**
	 * Check if this mode queues delivery.
	 *
	 * @return bool
	 */
	public function is_scheduled(): bool {
		return self::SCHEDULED === $this;
	}
}
