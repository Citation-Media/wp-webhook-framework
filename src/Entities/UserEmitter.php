<?php
/**
 * UserEmitter class for handling user-related webhook events.
 *
 * @package CitationMedia\WpWebhookFramework\Entities
 */

namespace CitationMedia\WpWebhookFramework\Entities;

use CitationMedia\WpWebhookFramework\Dispatcher;
use CitationMedia\WpWebhookFramework\Support\Payload;

/**
 * Class UserEmitter
 *
 * Emits webhooks for user lifecycle and meta changes.
 */
class UserEmitter extends AbstractEmitter {

	/**
	 * Constructor for UserEmitter.
	 *
	 * @param Dispatcher $dispatcher The webhook dispatcher instance.
	 */
	public function __construct( Dispatcher $dispatcher ) {
		parent::__construct( $dispatcher );
	}

	/**
	 * Handle user registration event.
	 *
	 * @param int $user_id The user ID.
	 */
	public function onUserRegister( int $user_id ): void {
		$this->emit( $user_id, 'create' );
	}

	/**
	 * Handle user profile update event.
	 *
	 * @param int $user_id The user ID.
	 */
	public function onProfileUpdate( int $user_id ): void {
		$this->emit( $user_id, 'update' );
	}

	/**
	 * Emit a webhook for a user action.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $action  The action performed (create/update/delete).
	 */
	public function emit( int $user_id, string $action ): void {
		$this->schedule( $action, 'user', $user_id, Payload::user( $user_id ) );
	}

	/**
	 * Handle user deletion event.
	 *
	 * @param int $user_id The user ID.
	 */
	public function onDeletedUser( int $user_id ): void {
		$this->emit( $user_id, 'delete' );
	}
}
