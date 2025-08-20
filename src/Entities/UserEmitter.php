<?php

namespace CitationMedia\WpWebhookFramework\Entities;
use CitationMedia\WpWebhookFramework\Dispatcher;
use CitationMedia\WpWebhookFramework\Support\Payload;

/**
 * Emits webhooks for user lifecycle and meta changes.
 */
class UserEmitter extends AbstractEmitter {

	public function __construct( Dispatcher $dispatcher ) {
		parent::__construct( $dispatcher );
	}

	public function onUserRegister( int $user_id ): void {
		$this->scheduleWebhook( 'create', 'user', $user_id, Payload::for_user( $user_id ) );
	}

	public function onProfileUpdate( int $user_id ): void {
		$this->scheduleWebhook( 'update', 'user', $user_id, Payload::for_user( $user_id ) );
	}

	public function onDeletedUser( int $user_id ): void {
		$this->scheduleWebhook( 'delete', 'user', $user_id, Payload::for_user( $user_id ) );
	}

	/**
	 * Handle ACF update routed to a user.
	 *
	 * @param array<string,mixed> $field
	 */
	public function onAcfUpdate( int $user_id, array $field ): void {
		$this->handleAcfUpdate( 'user', $user_id, $field, Payload::for_user( $user_id ) );
	}

	private function emitUpdateForUser( int $user_id ): void {
		$this->scheduleWebhook( 'update', 'user', (int) $user_id, Payload::for_user( (int) $user_id ) );
	}
}
