<?php
/**
 * User webhook implementation.
 *
 * @package Citation\WP_Webhook_Framework\Webhooks
 */

declare(strict_types=1);

namespace Citation\WP_Webhook_Framework\Webhooks;

use Citation\WP_Webhook_Framework\Webhook;
use Citation\WP_Webhook_Framework\Entities\User;
use Citation\WP_Webhook_Framework\Webhook_Registry;

/**
 * User webhook implementation with configuration capabilities.
 *
 * Handles user-related webhook events with configurable retry policies,
 * timeouts, and other webhook-specific settings.
 */
class User_Webhook extends Webhook {

	/**
	 * The user emitter instance.
	 *
	 * @var User
	 */
	private User $user_emitter;

	/**
	 * Constructor.
	 *
	 * @param string $name The webhook name.
	 * @phpstan-param non-empty-string $name
	 */
	public function __construct( string $name = 'user' ) {
		parent::__construct( $name );
		
		// Get dispatcher from registry
		$registry = Webhook_Registry::instance();
		$this->user_emitter = new User( $registry->get_dispatcher() );
	}

	/**
	 * Initialize the webhook by registering WordPress hooks.
	 */
	public function init(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		add_action( 'user_register', array( $this->user_emitter, 'on_user_register' ), 10, 1 );
		add_action( 'profile_update', array( $this->user_emitter, 'on_profile_update' ), 10, 1 );
		add_action( 'deleted_user', array( $this->user_emitter, 'on_deleted_user' ), 10, 1 );
	}

	/**
	 * Get the user emitter instance.
	 *
	 * @return User
	 */
	public function get_emitter(): User {
		return $this->user_emitter;
	}
}