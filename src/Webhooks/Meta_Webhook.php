<?php
/**
 * Meta webhook implementation.
 *
 * @package Citation\WP_Webhook_Framework\Webhooks
 */

declare(strict_types=1);

namespace Citation\WP_Webhook_Framework\Webhooks;

use Citation\WP_Webhook_Framework\Webhook;
use Citation\WP_Webhook_Framework\Entities\Meta;
use Citation\WP_Webhook_Framework\Webhook_Registry;
use Citation\WP_Webhook_Framework\Support\AcfUtil;

/**
 * Meta webhook implementation with configuration capabilities.
 *
 * Handles meta-related webhook events with configurable retry policies,
 * timeouts, and other webhook-specific settings.
 *
 * Uses a processed fields cache to prevent duplicate webhook emissions when
 * field providers (ACF, CMB2, etc.) trigger both their own hooks and WordPress
 * core meta hooks when saving field values.
 */
class Meta_Webhook extends Webhook {

	/**
	 * The meta handler instance.
	 *
	 * @var Meta
	 */
	private Meta $meta_handler;

	/**
	 * Tracks meta keys already processed during this request.
	 *
	 * Prevents duplicate emissions when field providers trigger multiple hooks
	 * for the same field update (e.g., ACF fires both acf/update_value and
	 * native update_*_metadata hooks).
	 *
	 * @var array<string,true>
	 * @phpstan-var array<non-empty-string,true>
	 */
	private array $processed_fields = array();

	/**
	 * Tracks the current entity being processed.
	 *
	 * Used to purge the processed fields cache when switching to a different
	 * entity, keeping memory usage constant during bulk operations.
	 * Format: "{entity_type}:{object_id}"
	 *
	 * @var string
	 */
	private string $current_entity = '';

	/**
	 * Constructor.
	 *
	 * @param string $name The webhook name.
	 * @phpstan-param non-empty-string $name
	 */
	public function __construct( string $name = 'meta' ) {
		parent::__construct( $name );
		$this->meta_handler = new Meta();
	}

	/**
	 * Initialize the webhook by registering WordPress hooks.
	 */
	public function init(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		add_action( 'deleted_post_meta', array( $this, 'on_deleted_post_meta' ), 10, 4 );
		add_action( 'deleted_term_meta', array( $this, 'on_deleted_term_meta' ), 10, 4 );
		add_action( 'deleted_user_meta', array( $this, 'on_deleted_user_meta' ), 10, 4 );

		add_filter(
			'acf/update_value',
			function ( $value, $object_id, $field, $original ): mixed {
				[$entity, $id] = AcfUtil::parse_object_id( $object_id );

				if ( null === $entity || null === $id ) {
					return $value;
				}

				$this->on_acf_update( $entity, (int) $id, is_array( $field ) ? $field : array(), $value, $original );
				return $value;
			},
			10,
			4
		);

		// Add filters for all meta types with high priority to run late
		add_filter( 'update_post_metadata', array( $this, 'on_updated_post_meta' ), 999, 5 );
		add_filter( 'update_term_metadata', array( $this, 'on_updated_term_meta' ), 999, 5 );
		add_filter( 'update_user_metadata', array( $this, 'on_updated_user_meta' ), 999, 5 );
	}

	/**
	 * Handle post meta update event.
	 *
	 * Skips processing if the field was already processed in this request.
	 *
	 * @param bool|null $check      Whether to allow updating metadata for the given type.
	 * @param int       $object_id  The object ID.
	 * @param string    $meta_key   The meta key.
	 * @param mixed     $meta_value The meta value.
	 * @param mixed     $prev_value The previous value. Most likely not filled. Value has to be passed to update_metadata().
	 * @return bool|null
	 */
	public function on_updated_post_meta( ?bool $check, int $object_id, string $meta_key, mixed $meta_value, mixed $prev_value ): ?bool {
		if ( wp_is_post_revision( $object_id ) || wp_is_post_autosave( $object_id ) ) {
			return $check;
		}

		// Skip if already processed to prevent duplicate emissions
		if ( $this->is_field_processed( 'post', $object_id, $meta_key ) ) {
			return $check;
		}

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

		if ( wp_is_post_revision( $object_id ) || wp_is_post_autosave( $object_id ) ) {
			return;
		}

		$this->on_meta_update( 'post', $object_id, $meta_key, $meta_value );
	}

	/**
	 * Handle term meta update event.
	 *
	 * Skips processing if the field was already processed in this request.
	 *
	 * @param bool|null $check      Whether to allow updating metadata for the given type.
	 * @param int       $object_id  The object ID.
	 * @param string    $meta_key   The meta key.
	 * @param mixed     $meta_value The meta value.
	 * @param mixed     $prev_value The previous value. Most likely not filled. Value has to be passed to update_metadata().
	 * @return bool|null
	 */
	public function on_updated_term_meta( ?bool $check, int $object_id, string $meta_key, mixed $meta_value, mixed $prev_value ): ?bool {
		// Skip if already processed to prevent duplicate emissions
		if ( $this->is_field_processed( 'term', $object_id, $meta_key ) ) {
			return $check;
		}

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
	 * Skips processing if the field was already processed in this request.
	 *
	 * @param bool|null $check      Whether to allow updating metadata for the given type.
	 * @param int       $object_id  The object ID.
	 * @param string    $meta_key   The meta key.
	 * @param mixed     $meta_value The meta value.
	 * @param mixed     $prev_value The previous value. Most likely not filled. Value has to be passed to update_metadata().
	 * @return bool|null
	 */
	public function on_updated_user_meta( ?bool $check, int $object_id, string $meta_key, mixed $meta_value, mixed $prev_value ): ?bool {
		// Skip if already processed to prevent duplicate emissions
		if ( $this->is_field_processed( 'user', $object_id, $meta_key ) ) {
			return $check;
		}

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
	 * Handle ACF update events.
	 *
	 * Routes ACF field updates to the central meta update handler and marks the field
	 * as processed to prevent duplicate processing via native WordPress hooks.
	 *
	 * @param string              $entity   The entity type.
	 * @param int                 $id       The entity ID.
	 * @param array<string,mixed> $field    The field data.
	 * @param mixed               $value    The new value.
	 * @param mixed               $original The original value.
	 */
	private function on_acf_update( string $entity, int $id, array $field, $value = null, $original = null ): void {
		$meta_key = $field['name'] ?? '';
		if ( empty( $meta_key ) ) {
			return;
		}

		// Mark this field as processed to prevent duplicate handling via native hooks
		$this->mark_field_processed( $entity, $id, $meta_key );

		$this->on_meta_update( $entity, $id, $meta_key, $value, $original );
	}

	/**
	 * Central handler for all metadata updates with automatic change and deletion detection.
	 *
	 * Emits both meta-level webhooks and triggers parent entity update webhooks.
	 *
	 * @param string $meta_type  The meta type (post, term, user).
	 * @param int    $object_id  The object ID.
	 * @param string $meta_key   The meta key.
	 * @param mixed  $new_value  The new value.
	 * @param mixed  $old_value  The old value.
	 */
	private function on_meta_update( string $meta_type, int $object_id, string $meta_key, mixed $new_value, mixed $old_value = null ): void {
		// No change, do nothing (ALWAYS use loose equality for value comparison)
		// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison,Universal.Operators.StrictComparisons.LooseEqual
		if ( $new_value == $old_value ) {
			return;
		}

		// Check if this meta key should be excluded from webhook emission
		if ( $this->meta_handler->is_meta_key_excluded( $meta_key, $meta_type, $object_id ) ) {
			return;
		}

		// Automatically detect if this is effectively a deletion
		$is_deletion = $this->meta_handler->is_deletion( $new_value, $old_value );
		$action      = $is_deletion ? 'delete' : 'update';

		// Emit meta-level webhook with meta_key in payload
		$payload = $this->meta_handler->prepare_payload( $meta_type, $object_id, $meta_key );
		$this->emit( $action, 'meta', $object_id, $payload );

		// Trigger upstream entity-level update webhook
		$this->trigger_entity_update( $meta_type, $object_id );
	}

	/**
	 * Trigger the appropriate entity-level update webhook.
	 *
	 * When meta changes, the parent entity (post/term/user) is also considered updated.
	 * Uses the parent entity's webhook instance for configuration.
	 *
	 * @param string $meta_type The meta type.
	 * @param int    $object_id The object ID.
	 */
	private function trigger_entity_update( string $meta_type, int $object_id ): void {
		$registry = Webhook_Registry::instance();

		// Get the parent entity webhook instance
		$parent_webhook = $registry->get( $meta_type );
		if ( ! $parent_webhook ) {
			return;
		}

		// Get entity payload without meta_key and pass directly to emit
		$payload = $this->meta_handler->get_entity_payload( $meta_type, $object_id );
		$parent_webhook->emit( 'update', $meta_type, $object_id, $payload );
	}

	/**
	 * Get the meta handler instance.
	 *
	 * @return Meta
	 */
	public function get_handler(): Meta {
		return $this->meta_handler;
	}

	/**
	 * Build a unique cache key for tracking processed field updates.
	 *
	 * @param string $entity_type The entity type (post, term, user).
	 * @param int    $object_id   The object ID.
	 * @param string $meta_key    The meta key.
	 * @return string The cache key.
	 */
	private function build_field_cache_key( string $entity_type, int $object_id, string $meta_key ): string {
		return sprintf( '%s:%d:%s', $entity_type, $object_id, $meta_key );
	}

	/**
	 * Mark a field as processed to prevent duplicate webhook emissions.
	 *
	 * Automatically purges the cache when switching to a different entity,
	 * keeping memory usage constant during bulk operations.
	 *
	 * @param string $entity_type The entity type (post, term, user).
	 * @param int    $object_id   The object ID.
	 * @param string $meta_key    The meta key.
	 */
	private function mark_field_processed( string $entity_type, int $object_id, string $meta_key ): void {
		$entity_key = sprintf( '%s:%d', $entity_type, $object_id );

		// Purge cache when switching to a different entity (memory optimization for bulk operations)
		if ( $this->current_entity !== $entity_key ) {
			$this->processed_fields = array();
			$this->current_entity   = $entity_key;
		}

		$cache_key                            = $this->build_field_cache_key( $entity_type, $object_id, $meta_key );
		$this->processed_fields[ $cache_key ] = true;
	}

	/**
	 * Check if a field was already processed in this request.
	 *
	 * @param string $entity_type The entity type (post, term, user).
	 * @param int    $object_id   The object ID.
	 * @param string $meta_key    The meta key.
	 * @return bool True if already processed.
	 */
	private function is_field_processed( string $entity_type, int $object_id, string $meta_key ): bool {
		$cache_key = $this->build_field_cache_key( $entity_type, $object_id, $meta_key );
		return isset( $this->processed_fields[ $cache_key ] );
	}
}
