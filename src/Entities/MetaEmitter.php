<?php

namespace CitationMedia\WpWebhookFramework\Entities;
use CitationMedia\WpWebhookFramework\Dispatcher;
use CitationMedia\WpWebhookFramework\Support\Payload;

/**
 * Routes meta changes to owning entity updates and optionally emits meta-level webhooks.
 */
class MetaEmitter extends AbstractEmitter {

	private PostEmitter $postEmitter;
	private TermEmitter $termEmitter;
	private UserEmitter $userEmitter;

	public function __construct( Dispatcher $dispatcher, PostEmitter $postEmitter, TermEmitter $termEmitter, UserEmitter $userEmitter ) {
		parent::__construct( $dispatcher );
		$this->postEmitter = $postEmitter;
		$this->termEmitter = $termEmitter;
		$this->userEmitter = $userEmitter;
	}

	public function onUpdatedPostMeta( int $meta_id, int $object_id, string $meta_key, $meta_value ): void {
		$this->handlePostMetaChange( $object_id, $meta_key, $meta_value );
	}

	public function onDeletedPostMeta( $meta_ids, int $object_id, string $meta_key, $meta_value ): void {
		$this->handlePostMetaChange( $object_id, $meta_key, $meta_value, true );
	}

	// Term meta hooks
	public function onUpdatedTermMeta( int $meta_id, int $object_id, string $meta_key, $meta_value ): void {
		$this->handleTermMetaChange( $object_id, $meta_key, $meta_value );
	}

	public function onDeletedTermMeta( $meta_ids, int $object_id, string $meta_key, $meta_value ): void {
		$this->handleTermMetaChange( $object_id, $meta_key, $meta_value, true );
	}

	// User meta hooks

	public function onUpdatedUserMeta( int $meta_id, int $object_id, string $meta_key, $meta_value ): void {
		$this->handleUserMetaChange( $object_id, $meta_key, $meta_value );
	}

	public function onDeletedUserMeta( $meta_ids, int $object_id, string $meta_key, $meta_value ): void {
		$this->handleUserMetaChange( $object_id, $meta_key, $meta_value, true );
	}

	/**
	 * Handle ACF update routed from ServiceProvider. Entity is one of 'post','term','user'.
	 * MetaEmitter will decide whether to emit a meta-level webhook and will also trigger
	 * the upstream entity-level update.
	 */
	public function onAcfUpdate( string $entity, int $id, array $field ): void {
		if ( $entity === 'post' ) {
			$this->handlePostMetaChange($id, $field['name']);
			return;
		}

		if ( $entity === 'term' ) {
			$this->handleTermMetaChange($id, $field['name']);
			return;
		}

		if ( $entity === 'user' ) {
			$this->handleUserMetaChange($id, $field['name']);
			return;
		}
	}

	private function handlePostMetaChange( int $post_id, string $meta_key, $meta_value = null, bool $deleted = false ): void {
		$this->emit( $post_id, $deleted ? 'delete' : 'update', $meta_key, Payload::post($post_id) );
		$this->postEmitter->emit( $post_id, 'update');
	}

	private function handleTermMetaChange( int $term_id, string $meta_key, $meta_value = null, bool $deleted = false ): void {
		$this->emit( $term_id, $deleted ? 'delete' : 'update', $meta_key, Payload::term($term_id) );
		$this->termEmitter->emit( $term_id, 'update');
	}

	private function handleUserMetaChange( int $user_id, string $meta_key, $meta_value = null, bool $deleted = false ): void {
		$this->emit( $user_id, $deleted ? 'delete' : 'update', $meta_key, Payload::user($user_id));
		$this->userEmitter->emit( $user_id, 'update');
	}

	public function emit(int $user_id, string $action, string $meta_key, array $payload = array()): void
	{
		$this->schedule( $action, 'meta', $user_id, array_merge(
			$payload,
			array( 'meta_key' => $meta_key )
		) );
	}
}
