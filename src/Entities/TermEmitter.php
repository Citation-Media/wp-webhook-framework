<?php
/**
 * TermEmitter class for handling term-related webhook events.
 *
 * @package CitationMedia\WpWebhookFramework\Entities
 */

namespace CitationMedia\WpWebhookFramework\Entities;

use CitationMedia\WpWebhookFramework\Dispatcher;
use CitationMedia\WpWebhookFramework\Support\Payload;

/**
 * Class TermEmitter
 *
 * Emits webhooks for term lifecycle and meta changes.
 */
class TermEmitter extends AbstractEmitter {

	/**
	 * Constructor for TermEmitter.
	 *
	 * @param Dispatcher $dispatcher The webhook dispatcher instance.
	 */
	public function __construct( Dispatcher $dispatcher ) {
		parent::__construct( $dispatcher );
	}

	/**
	 * Handle term creation event.
	 *
	 * @param int    $term_id  The term ID.
	 * @param int    $tt_id    The term taxonomy ID.
	 * @param string $taxonomy The taxonomy name.
	 */
	public function onCreatedTerm( int $term_id, int $tt_id, string $taxonomy ): void {
		$this->emit( $term_id, 'create' );
	}

	/**
	 * Handle term update event.
	 *
	 * @param int    $term_id  The term ID.
	 * @param int    $tt_id    The term taxonomy ID.
	 * @param string $taxonomy The taxonomy name.
	 */
	public function onEditedTerm( int $term_id, int $tt_id, string $taxonomy ): void {
		$this->emit( $term_id, 'update' );
	}

	/**
	 * Handle term deletion event.
	 *
	 * @param int    $term_id  The term ID.
	 * @param int    $tt_id    The term taxonomy ID.
	 * @param string $taxonomy The taxonomy name.
	 */
	public function onDeletedTerm( int $term_id, int $tt_id, string $taxonomy ): void {
		$this->emit( $term_id, 'delete' );
	}

	/**
	 * Emit a webhook for a term action.
	 *
	 * @param int    $term_id The term ID.
	 * @param string $action  The action performed (create/update/delete).
	 */
	public function emit( int $term_id, string $action ): void {
		$this->schedule( $action, 'term', $term_id, Payload::term( $term_id ) );
	}
}
