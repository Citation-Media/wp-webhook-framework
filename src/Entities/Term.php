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
	 * Handle term creation event.
	 *
	 * @param int    $term_id  The term ID.
	 * @param int    $tt_id    The term taxonomy ID.
	 * @param string $taxonomy The taxonomy name.
	 */
	public function on_created_term( int $term_id, int $tt_id, string $taxonomy ): void {
		$this->emit( $term_id, 'create' );
	}

	/**
	 * Handle term update event.
	 *
	 * @param int    $term_id  The term ID.
	 * @param int    $tt_id    The term taxonomy ID.
	 * @param string $taxonomy The taxonomy name.
	 */
	public function on_edited_term( int $term_id, int $tt_id, string $taxonomy ): void {
		$this->emit( $term_id, 'update' );
	}

	/**
	 * Handle term deletion event.
	 *
	 * @param int    $term_id  The term ID.
	 * @param int    $tt_id    The term taxonomy ID.
	 * @param string $taxonomy The taxonomy name.
	 */
	public function on_deleted_term( int $term_id, int $tt_id, string $taxonomy ): void {
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
