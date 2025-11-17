<?php
/**
 * Meta entity handler for handling meta-related webhook events.
 *
 * @package Citation\WP_Webhook_Framework\Entities
 */

namespace Citation\WP_Webhook_Framework\Entities;

use Citation\WP_Webhook_Framework\Dispatcher;
use Citation\WP_Webhook_Framework\Support\Payload;

/**
 * Meta entity handler.
 *
 * Provides utilities for detecting meta changes, filtering excluded keys,
 * and preparing payloads for meta-related webhooks.
 */
class Meta extends Entity_Handler {

	/**
	 * Post handler instance.
	 *
	 * @var Post
	 */
	private Post $post_handler;

	/**
	 * Term handler instance.
	 *
	 * @var Term
	 */
	private Term $term_handler;

	/**
	 * User handler instance.
	 *
	 * @var User
	 */
	private User $user_handler;

	/**
	 * Constructor for Meta handler.
	 *
	 * Initializes the Meta handler with a dispatcher and entity handlers.
	 *
	 * @param Post $post_handler Post handler instance.
	 * @param Term $term_handler Term handler instance.
	 * @param User $user_handler User handler instance.
	 */
	public function __construct( Post $post_handler, Term $term_handler, User $user_handler ) {
		$this->post_handler = $post_handler;
		$this->term_handler = $term_handler;
		$this->user_handler = $user_handler;
	}

	/**
	 * Determine if a metadata update represents a deletion.
	 *
	 * @param mixed $new_value The new value.
	 * @param mixed $old_value The old value.
	 * @return bool True if this represents a deletion.
	 */
	public function is_deletion( mixed $new_value, mixed $old_value ): bool {
		// If new value is empty/null and old value existed, it's a deletion
		return empty( $new_value ) && ! empty( $old_value );
	}

	/**
	 * Check if a meta key should be excluded from webhook emission.
	 *
	 * @param string $meta_key   The meta key to check.
	 * @param string $meta_type  The meta type (post, term, user).
	 * @param int    $object_id  The object ID.
	 * @return bool True if the meta key should be excluded.
	 */
	public function is_meta_key_excluded( string $meta_key, string $meta_type, int $object_id ): bool {
		/**
		 * Filter the list of meta keys that should be excluded from webhook emission.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string> $excluded_keys Array of meta keys to exclude from webhooks.
		 * @param string        $meta_key      The current meta key being processed.
		 * @param string        $meta_type     The meta type (post, term, user).
		 * @param int           $object_id     The object ID.
		 */
		$excluded_keys = apply_filters(
			'wpwf_excluded_meta_keys',
			array(
				'_edit_lock',
				'_edit_last',
				'session_tokens',
			),
			$meta_key,
			$meta_type,
			$object_id
		);

		return in_array( $meta_key, $excluded_keys, true );
	}

	/**
	 * Prepare payload for a meta update.
	 *
	 * @param string $meta_type The meta type (post, term, user).
	 * @param int    $object_id The object ID.
	 * @param string $meta_key  The meta key.
	 * @return array<string,mixed> The prepared payload data with meta_key included.
	 */
	public function prepare_payload( string $meta_type, int $object_id, string $meta_key ): array {
		$base_payload = match ( $meta_type ) {
			'post' => $this->post_handler->prepare_payload( $object_id ),
			'term' => $this->term_handler->prepare_payload( $object_id ),
			'user' => $this->user_handler->prepare_payload( $object_id ),
			default => array(),
		};

		return array_merge(
			$base_payload,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Not a database query, just webhook payload data
			array( 'meta_key' => $meta_key )
		);
	}

	/**
	 * Get the entity payload (without meta_key) for triggering parent entity updates.
	 *
	 * @param string $meta_type The meta type (post, term, user).
	 * @param int    $object_id The object ID.
	 * @return array<string,mixed> The entity payload data.
	 */
	public function get_entity_payload( string $meta_type, int $object_id ): array {
		return match ( $meta_type ) {
			'post' => $this->post_handler->prepare_payload( $object_id ),
			'term' => $this->term_handler->prepare_payload( $object_id ),
			'user' => $this->user_handler->prepare_payload( $object_id ),
			default => array(),
		};
	}
}
