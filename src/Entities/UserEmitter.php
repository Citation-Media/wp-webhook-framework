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
		$this->emit( $user_id, 'create' );
	}

	public function onProfileUpdate( int $user_id ): void {
		$this->emit( $user_id, 'update' );
	}

	public function onDeletedUser( int $user_id ): void {
		$this->emit( $user_id, 'delete' );
	}

	private function emitUpdateForUser( int $user_id ): void {
		$this->emit( $user_id, 'update' );
	}

	public function emit(int $user_id, string $action): void {
		$this->schedule( $action, 'user', $user_id, Payload::user($user_id) );
	}
}
