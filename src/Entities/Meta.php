<?php
/**
 * MetaEmitter class for handling meta-related webhook events.
 *
 * @package Citation\WP_Webhook_Framework\Entities
 */

namespace Citation\WP_Webhook_Framework\Entities;

use Citation\WP_Webhook_Framework\Dispatcher;
use Citation\WP_Webhook_Framework\Support\Payload;

/**
 * Class MetaEmitter
 *
 * Routes meta changes to owning entity updates and optionally emits meta-level webhooks.
 */
class Meta extends Emitter {

	/**
	 * Post emitter instance.
	 *
	 * @var Post
	 */
	private Post $post_emitter;

	/**
	 * Term emitter instance.
	 *
	 * @var Term
	 */
	private Term $term_emitter;

	/**
	 * User emitter instance.
	 *
	 * @var User
	 */
	private User $user_emitter;

	/**
	 * Constructor for MetaEmitter.
	 *
	 * Initializes the MetaEmitter with a dispatcher and entity emitters.
	 *
	 * @param Dispatcher $dispatcher   The webhook dispatcher instance.
	 * @param Post       $post_emitter Post emitter instance.
	 * @param Term       $term_emitter Term emitter instance.
	 * @param User       $user_emitter User emitter instance.
	 */
	public function __construct( Dispatcher $dispatcher, Post $post_emitter, Term $term_emitter, User $user_emitter ) {
		parent::__construct( $dispatcher );
		$this->post_emitter = $post_emitter;
		$this->term_emitter = $term_emitter;
		$this->user_emitter = $user_emitter;
	}

	/**
	 * Handle post meta update event.
	 *
	 * @param int    $meta_id    The meta ID.
	 * @param int    $object_id  The object ID.
	 * @param string $meta_key   The meta key.
	 * @param mixed  $meta_value The meta value.
	 */
	public function onUpdatedPostMeta( int $meta_id, int $object_id, string $meta_key, $meta_value ): void {
		$this->handlePostMetaChange( $object_id, $meta_key, $meta_value );
	}

	/**
	 * Handle post meta deletion event.
	 *
	 * @param mixed  $meta_ids   The meta IDs.
	 * @param int    $object_id  The object ID.
	 * @param string $meta_key   The meta key.
	 * @param mixed  $meta_value The meta value.
	 */
	public function onDeletedPostMeta( $meta_ids, int $object_id, string $meta_key, $meta_value ): void {
		$this->handlePostMetaChange( $object_id, $meta_key, $meta_value, true );
	}

	/**
	 * Handle term meta update event.
	 *
	 * @param int    $meta_id    The meta ID.
	 * @param int    $object_id  The object ID.
	 * @param string $meta_key   The meta key.
	 * @param mixed  $meta_value The meta value.
	 */
	public function onUpdatedTermMeta( int $meta_id, int $object_id, string $meta_key, $meta_value ): void {
		$this->handleTermMetaChange( $object_id, $meta_key, $meta_value );
	}

	/**
	 * Handle term meta deletion event.
	 *
	 * @param mixed  $meta_ids   The meta IDs.
	 * @param int    $object_id  The object ID.
	 * @param string $meta_key   The meta key.
	 * @param mixed  $meta_value The meta value.
	 */
	public function onDeletedTermMeta( $meta_ids, int $object_id, string $meta_key, $meta_value ): void {
		$this->handleTermMetaChange( $object_id, $meta_key, $meta_value, true );
	}

	/**
	 * Handle user meta update event.
	 *
	 * @param int    $meta_id    The meta ID.
	 * @param int    $object_id  The object ID.
	 * @param string $meta_key   The meta key.
	 * @param mixed  $meta_value The meta value.
	 */
	public function onUpdatedUserMeta( int $meta_id, int $object_id, string $meta_key, $meta_value ): void {
		$this->handleUserMetaChange( $object_id, $meta_key, $meta_value );
	}

	/**
	 * Handle user meta deletion event.
	 *
	 * @param mixed  $meta_ids   The meta IDs.
	 * @param int    $object_id  The object ID.
	 * @param string $meta_key   The meta key.
	 * @param mixed  $meta_value The meta value.
	 */
	public function onDeletedUserMeta( $meta_ids, int $object_id, string $meta_key, $meta_value ): void {
		$this->handleUserMetaChange( $object_id, $meta_key, $meta_value, true );
	}

	/**
	 * Handle ACF update routed from ServiceProvider. Entity is one of 'post','term','user'.
	 * MetaEmitter will decide whether to emit a meta-level webhook and will also trigger
	 * the upstream entity-level update.
	 *
	 * @param string              $entity The entity type.
	 * @param int                 $id     The entity ID.
	 * @param array<string,mixed> $field  The field data.
	 */
	public function onAcfUpdate( string $entity, int $id, array $field ): void {
		if ( 'post' === $entity ) {
			$this->handlePostMetaChange( $id, $field['name'] );
			return;
		}

		if ( 'term' === $entity ) {
			$this->handleTermMetaChange( $id, $field['name'] );
			return;
		}

		if ( 'user' === $entity ) {
			$this->handleUserMetaChange( $id, $field['name'] );
			return;
		}
	}

	/**
	 * Handle post meta change.
	 *
	 * @param int    $post_id    The post ID.
	 * @param string $meta_key   The meta key.
	 * @param mixed  $meta_value The meta value.
	 * @param bool   $deleted    Whether the meta was deleted.
	 */
	private function handlePostMetaChange( int $post_id, string $meta_key, $meta_value = null, bool $deleted = false ): void {
		$this->emit( $post_id, $deleted ? 'delete' : 'update', $meta_key, Payload::post( $post_id ) );
		$this->post_emitter->emit( $post_id, 'update' );
	}

	/**
	 * Handle term meta change.
	 *
	 * @param int    $term_id    The term ID.
	 * @param string $meta_key   The meta key.
	 * @param mixed  $meta_value The meta value.
	 * @param bool   $deleted    Whether the meta was deleted.
	 */
	private function handleTermMetaChange( int $term_id, string $meta_key, $meta_value = null, bool $deleted = false ): void {
		$this->emit( $term_id, $deleted ? 'delete' : 'update', $meta_key, Payload::term( $term_id ) );
		$this->term_emitter->emit( $term_id, 'update' );
	}

	/**
	 * Handle user meta change.
	 *
	 * @param int    $user_id    The user ID.
	 * @param string $meta_key   The meta key.
	 * @param mixed  $meta_value The meta value.
	 * @param bool   $deleted    Whether the meta was deleted.
	 */
	private function handleUserMetaChange( int $user_id, string $meta_key, $meta_value = null, bool $deleted = false ): void {
		$this->emit( $user_id, $deleted ? 'delete' : 'update', $meta_key, Payload::user( $user_id ) );
		$this->user_emitter->emit( $user_id, 'update' );
	}

	/**
	 * Emit a webhook for a meta action.
	 *
	 * @param int                 $user_id The entity ID.
	 * @param string              $action  The action performed.
	 * @param string              $meta_key The meta key.
	 * @param array<string,mixed> $payload The payload data.
	 */
	public function emit( int $user_id, string $action, string $meta_key, array $payload = array() ): void {
		$this->schedule(
			$action,
			'meta',
			$user_id,
			array_merge(
				$payload,
				array( 'meta_key' => $meta_key )
			)
		);
	}
}
