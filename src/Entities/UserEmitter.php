<?php

namespace CitationMedia\WpWebhookFramework\Entities;
use CitationMedia\WpWebhookFramework\Dispatcher;
use CitationMedia\WpWebhookFramework\Support\Payload;

/**
 * Emits webhooks for user lifecycle and meta changes.
 */
class UserEmitter {

	private Dispatcher $dispatcher;

	public function __construct( Dispatcher $dispatcher ) {
		$this->dispatcher = $dispatcher;
	}

	public function onUserRegister( int $user_id ): void {
		$this->dispatcher->schedule( 'create', 'user', $user_id, Payload::for_user( $user_id ) );
	}

	public function onProfileUpdate( int $user_id ): void {
		$this->dispatcher->schedule( 'update', 'user', $user_id, Payload::for_user( $user_id ) );
	}

	public function onDeletedUser( int $user_id ): void {
		$this->dispatcher->schedule( 'delete', 'user', $user_id, Payload::for_user( $user_id ) );
	}

	/**
	 * Handle ACF update routed to a user.
	 *
	 * @param array<string,mixed> $field
	 */
	public function onAcfUpdate( int $user_id, array $field ): void {
		$payload = array_merge(
			Payload::for_user( $user_id ),
			Payload::from_acf_field( $field )
		);

		$this->dispatcher->schedule( 'update', 'user', $user_id, $payload );
	}

	private function emitUpdateForUser( int $user_id ): void {
		$this->dispatcher->schedule( 'update', 'user', (int) $user_id, Payload::for_user( (int) $user_id ) );
	}
}
