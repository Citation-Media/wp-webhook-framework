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
	 * @param bool|null $check      Whether to allow updating metadata for the given type.
	 * @param int       $object_id  The object ID.
	 * @param string    $meta_key   The meta key.
	 * @param mixed     $meta_value The meta value.
	 * @param mixed     $prev_value The previous value. Most likely not filled. Value has to be passed to update_metadata().
	 * @return bool|null
	 */
	public function on_updated_post_meta( ?bool $check, int $object_id, string $meta_key, mixed $meta_value, mixed $prev_value ): ?bool {

		if ( empty( $prev_value ) ) {
			$prev_value = get_post_meta( $object_id, $meta_key, true );
		}

		$this->on_meta_update( 'post', $object_id, $meta_key, $meta_value, $prev_value );
		return $check;
	}

	/**
	 * Handle post meta deletion event.
	 *
	 * @param mixed  $meta_ids   The meta IDs.
	 * @param int    $object_id  The object ID.
	 * @param string $meta_key   The meta key.
	 * @param mixed  $meta_value The meta value.
	 */
	public function on_deleted_post_meta( $meta_ids, int $object_id, string $meta_key, $meta_value ): void {
		$this->on_meta_update( 'post', $object_id, $meta_key, $meta_value );
	}

	/**
	 * Handle term meta update event.
	 *
	 * @param bool|null $check      Whether to allow updating metadata for the given type.
	 * @param int       $object_id  The object ID.
	 * @param string    $meta_key   The meta key.
	 * @param mixed     $meta_value The meta value.
	 * @param mixed     $prev_value The previous value. Most likely not filled. Value has to be passed to update_metadata().
	 * @return bool|null
	 */
	public function on_updated_term_meta( ?bool $check, int $object_id, string $meta_key, mixed $meta_value, mixed $prev_value ): ?bool {

		if ( empty( $prev_value ) ) {
			$prev_value = get_term_meta( $object_id, $meta_key, true );
		}

		$this->on_meta_update( 'term', $object_id, $meta_key, $meta_value, $prev_value );
		return $check;
	}

	/**
	 * Handle term meta deletion event.
	 *
	 * @param mixed  $meta_ids   The meta IDs.
	 * @param int    $object_id  The object ID.
	 * @param string $meta_key   The meta key.
	 * @param mixed  $meta_value The meta value.
	 */
	public function on_deleted_term_meta( $meta_ids, int $object_id, string $meta_key, $meta_value ): void {
		$this->on_meta_update( 'term', $object_id, $meta_key, $meta_value );
	}

	/**
	 * Handle user meta update event.
	 *
	 * @param bool|null $check      Whether to allow updating metadata for the given type.
	 * @param int       $object_id  The object ID.
	 * @param string    $meta_key   The meta key.
	 * @param mixed     $meta_value The meta value.
	 * @param mixed     $prev_value The previous value. Most likely not filled. Value has to be passed to update_metadata().
	 * @return bool|null
	 */
	public function on_updated_user_meta( ?bool $check, int $object_id, string $meta_key, mixed $meta_value, mixed $prev_value ): ?bool {

		if ( empty( $prev_value ) ) {
			$prev_value = get_user_meta( $object_id, $meta_key, true );
		}

		$this->on_meta_update( 'user', $object_id, $meta_key, $meta_value, $prev_value );
		return $check;
	}

	/**
	 * Handle user meta deletion event.
	 *
	 * @param mixed  $meta_ids   The meta IDs.
	 * @param int    $object_id  The object ID.
	 * @param string $meta_key   The meta key.
	 * @param mixed  $meta_value The meta value.
	 */
	public function on_deleted_user_meta( $meta_ids, int $object_id, string $meta_key, $meta_value ): void {
		$this->on_meta_update( 'user', $object_id, $meta_key, $meta_value );
	}

	/**
	 * Central handler for all metadata updates with automatic change and deletion detection.
	 *
	 * @param string $meta_type  The meta type (post, term, user).
	 * @param int    $object_id  The object ID.
	 * @param string $meta_key   The meta key.
	 * @param mixed  $new_value  The new value.
	 * @param mixed  $old_value  The old value.
	 */
	public function on_meta_update( string $meta_type, int $object_id, string $meta_key, mixed $new_value, mixed $old_value = null ): void {

		// No change do nothing. Never compare strict!
		if ( $new_value === $old_value ) {
			return;
		}

		// Automatically detect if this is effectively a deletion
		$is_deletion = $this->is_deletion( $new_value, $old_value );

		// Emit meta-level webhook
		$this->emit( $object_id, $is_deletion ? 'delete' : 'update', $meta_key, $this->get_payload( $meta_type, $object_id ) );

		// Trigger upstream entity-level update
		$this->trigger_entity_update( $meta_type, $object_id );
	}

	/**
	 * Handle ACF update routed from ServiceProvider. Entity is one of 'post','term','user'.
	 * MetaEmitter will decide whether to emit a meta-level webhook and will also trigger
	 * the upstream entity-level update.
	 *
	 * @param string              $entity   The entity type.
	 * @param int                 $id       The entity ID.
	 * @param array<string,mixed> $field    The field data.
	 * @param mixed               $value    The new value.
	 * @param mixed               $original The original value.
	 */
	public function acf_update_handler( string $entity, int $id, array $field, $value = null, $original = null ): void {
		$this->on_meta_update( $entity, $id, $field['name'], $value, $original );
	}



	/**
	 * Determine if a metadata update represents a deletion.
	 *
	 * @param mixed $new_value The new value.
	 * @param mixed $old_value The old value.
	 * @return bool True if this represents a deletion.
	 */
	private function is_deletion( $new_value, $old_value ): bool {
		// If new value is empty/null and old value existed, it's a deletion
		return empty( $new_value ) && ! empty( $old_value );
	}

	/**
	 * Get the appropriate payload for the meta type.
	 *
	 * @param string $meta_type The meta type.
	 * @param int    $object_id The object ID.
	 * @return array<string,mixed> The payload data.
	 */
	private function get_payload( string $meta_type, int $object_id ): array {
		switch ( $meta_type ) {
			case 'post':
				return Payload::post( $object_id );
			case 'term':
				return Payload::term( $object_id );
			case 'user':
				return Payload::user( $object_id );
			default:
				return array();
		}
	}

	/**
	 * Trigger the appropriate entity-level update.
	 *
	 * @param string $meta_type The meta type.
	 * @param int    $object_id The object ID.
	 */
	private function trigger_entity_update( string $meta_type, int $object_id ): void {
		switch ( $meta_type ) {
			case 'post':
				$this->post_emitter->emit( $object_id, 'update' );
				break;
			case 'term':
				$this->term_emitter->emit( $object_id, 'update' );
				break;
			case 'user':
				$this->user_emitter->emit( $object_id, 'update' );
				break;
		}
	}

	/**
	 * Emit a webhook for a meta action.
	 *
	 * @param int                 $entity_id The entity ID.
	 * @param string              $action    The action performed.
	 * @param string              $meta_key  The meta key.
	 * @param array<string,mixed> $payload   The payload data.
	 */
	public function emit( int $entity_id, string $action, string $meta_key, array $payload = array() ): void {
		$this->schedule(
			$action,
			'meta',
			$entity_id,
			array_merge(
				$payload,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Not a database query, just webhook payload data
				array( 'meta_key' => $meta_key )
			)
		);
	}
}
