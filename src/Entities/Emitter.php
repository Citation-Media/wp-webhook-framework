<?php
/**
 * Abstract base class for webhook emitters.
 *
 * @package Citation\WP_Webhook_Framework\Entities
 */

declare(strict_types=1);

namespace Citation\WP_Webhook_Framework\Entities;

use Citation\WP_Webhook_Framework\Dispatcher;
use Citation\WP_Webhook_Framework\Support\Payload;

/**
 * Abstract base class for all webhook emitters.
 *
 * Provides common functionality for scheduling webhooks and handling ACF updates.
 */
abstract class Emitter {

	/**
	 * The dispatcher instance.
	 *
	 * @var Dispatcher
	 */
	protected Dispatcher $dispatcher;

	/**
	 * Constructor.
	 *
	 * @param Dispatcher $dispatcher The dispatcher instance.
	 */
	public function __construct( Dispatcher $dispatcher ) {
		$this->dispatcher = $dispatcher;
	}

	/**
	 * Schedule a webhook with the given parameters.
	 *
	 * @param string              $action      The action type (create, update, delete).
	 * @param string              $entity_type The entity type (post, term, user, meta).
	 * @param int|string          $entity_id   The entity ID.
	 * @param array<string,mixed> $payload     The payload data.
	 */
	protected function schedule( string $action, string $entity_type, int|string $entity_id, array $payload ): void {
		// Apply filter to allow modification of the payload
		$filtered_payload = apply_filters(
			"wp_webhook_framework_{$entity_type}_payload",
			$payload,
			$entity_id,
			$action
		);

		// If the filtered payload is empty, don't schedule the webhook
		if ( empty( $filtered_payload ) ) {
			return;
		}

		$this->dispatcher->schedule( $action, $entity_type, $entity_id, $filtered_payload );
	}

	/**
	 * Get the dispatcher instance.
	 *
	 * @return Dispatcher The dispatcher instance.
	 */
	protected function get_dispatcher(): Dispatcher {
		return $this->dispatcher;
	}
}
