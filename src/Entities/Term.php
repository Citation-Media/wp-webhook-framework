<?php
/**
 * Term entity handler for handling term-related webhook events.
 *
 * @package Citation\WP_Webhook_Framework\Entities
 */

namespace Citation\WP_Webhook_Framework\Entities;

use Citation\WP_Webhook_Framework\Dispatcher;
use Citation\WP_Webhook_Framework\Support\Payload;

/**
 * Term entity handler.
 *
 * Transforms term data into webhook payloads.
 */
class Term extends Entity_Handler {

	/**
	 * Prepare payload for a term.
	 *
	 * @param int $term_id The term ID.
	 * @return array<string,mixed> The prepared payload data.
	 */
	public function prepare_payload( int $term_id ): array {
		$term = get_term( $term_id );
		return array( 'taxonomy' => $term->taxonomy );
	}
}
