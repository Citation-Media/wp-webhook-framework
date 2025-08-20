<?php
/**
 * Abstract base class for webhook emitters.
 *
 * @package CitationMedia\WpWebhookFramework\Entities
 */

declare(strict_types=1);

namespace CitationMedia\WpWebhookFramework\Entities;

use CitationMedia\WpWebhookFramework\Dispatcher;
use CitationMedia\WpWebhookFramework\Support\Payload;

/**
 * Abstract base class for all webhook emitters.
 *
 * Provides common functionality for scheduling webhooks and handling ACF updates.
 */
abstract class AbstractEmitter {

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
	 * @param string $action The action type (create, update, delete).
	 * @param string $entity_type The entity type (post, term, user).
	 * @param int|string $entity_id The entity ID.
	 * @param array<string,mixed> $payload The payload data.
	 */
	protected function schedule(string $action, string $entity_type, int|string $entity_id, array $payload ): void {
		$this->dispatcher->schedule( $action, $entity_type, $entity_id, $payload );
	}

	/**
	 * Get the dispatcher instance.
	 *
	 * @return Dispatcher The dispatcher instance.
	 */
	protected function getDispatcher(): Dispatcher {
		return $this->dispatcher;
	}
}