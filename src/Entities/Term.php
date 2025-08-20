<?php
/**
 * TermEmitter class for handling term-related webhook events.
 *
 * @package Citation\WP_Webhook_Framework\Entities
 */

namespace Citation\WP_Webhook_Framework\Entities;

use Citation\WP_Webhook_Framework\Dispatcher;
use Citation\WP_Webhook_Framework\Support\Payload;

/**
 * Class TermEmitter
 *
 * Emits webhooks for term lifecycle and meta changes.
 */
class Term extends Emitter {

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
