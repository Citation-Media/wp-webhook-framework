<?php
/**
 * UserEmitter class for handling user-related webhook events.
 *
 * @package Citation\WP_Webhook_Framework\Entities
 */

namespace Citation\WP_Webhook_Framework\Entities;

use Citation\WP_Webhook_Framework\Dispatcher;
use Citation\WP_Webhook_Framework\Support\Payload;

/**
 * Class UserEmitter
 *
 * Emits webhooks for user lifecycle and meta changes.
 */
class User extends Emitter {



	/**
	 * Handle user registration event.
	 *
	 * @param int $user_id The user ID.
	 */
	public function on_user_register( int $user_id ): void {
		$this->emit( $user_id, 'create' );
	}

	/**
	 * Handle user profile update event.
	 *
	 * @param int $user_id The user ID.
	 */
	public function on_profile_update( int $user_id ): void {
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
	public function on_deleted_user( int $user_id ): void {
		$this->emit( $user_id, 'delete' );
	}
}
