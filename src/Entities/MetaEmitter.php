<?php

namespace CitationMedia\WpWebhookFramework\Entities;
use CitationMedia\WpWebhookFramework\Dispatcher;
use CitationMedia\WpWebhookFramework\Support\Payload;

/**
 * Routes meta changes to owning entity updates and optionally emits meta-level webhooks.
 */
class MetaEmitter {

	private Dispatcher $dispatcher;
	private PostEmitter $postEmitter;
	private TermEmitter $termEmitter;
	private UserEmitter $userEmitter;

	public function __construct( Dispatcher $dispatcher, PostEmitter $postEmitter, TermEmitter $termEmitter, UserEmitter $userEmitter ) {
		$this->dispatcher  = $dispatcher;
		$this->postEmitter = $postEmitter;
		$this->termEmitter = $termEmitter;
		$this->userEmitter = $userEmitter;
	}

	// Post meta hooks - check for ACF fields and emit meta-level webhook when appropriate,
	// then trigger upstream post update so consumers receive both granular and entity-level events.
	public function onAddedPostMeta( int $meta_id, int $object_id, string $meta_key, $meta_value ): void {
		$this->handlePostMetaChange( $object_id, $meta_key, $meta_value );
	}

	public function onUpdatedPostMeta( int $meta_id, int $object_id, string $meta_key, $meta_value ): void {
		$this->handlePostMetaChange( $object_id, $meta_key, $meta_value );
	}

	public function onDeletedPostMeta( $meta_ids, int $object_id, string $meta_key, $meta_value ): void {
		$this->handlePostMetaChange( $object_id, $meta_key, $meta_value, true );
	}

	// Term meta hooks
	public function onAddedTermMeta( int $meta_id, int $object_id, string $meta_key, $meta_value ): void {
		$this->handleTermMetaChange( $object_id, $meta_key, $meta_value );
	}

	public function onUpdatedTermMeta( int $meta_id, int $object_id, string $meta_key, $meta_value ): void {
		$this->handleTermMetaChange( $object_id, $meta_key, $meta_value );
	}

	public function onDeletedTermMeta( $meta_ids, int $object_id, string $meta_key, $meta_value ): void {
		$this->handleTermMetaChange( $object_id, $meta_key, $meta_value, true );
	}

	// User meta hooks
	public function onAddedUserMeta( int $meta_id, int $object_id, string $meta_key, $meta_value ): void {
		$this->handleUserMetaChange( $object_id, $meta_key, $meta_value );
	}

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
			$this->emitMetaForPostAcf( $id, $field );
			// also schedule the upstream post update
			$this->emitPostUpdate( $id );
			return;
		}

		if ( $entity === 'term' ) {
			$this->emitMetaForTermAcf( $id, $field );
			$this->emitTermUpdate( $id );
			return;
		}

		if ( $entity === 'user' ) {
			$this->emitMetaForUserAcf( $id, $field );
			$this->emitUserUpdate( $id );
			return;
		}
	}

	private function handlePostMetaChange( int $post_id, string $meta_key, $meta_value, bool $deleted = false ): void {
		// Emit meta-level webhook only for ACF-managed fields (value meta, not the stored field-key meta)
		if ( $this->isAcfValueMeta( 'post', $post_id, $meta_key ) ) {
			$post_type = get_post_type( $post_id );
			if ( $post_type ) {
				$payload = array_merge(
					Payload::for_post( $post_type ),
					array(
						'meta_key' => $meta_key,
						'deleted'  => $deleted,
					)
				);

				// include any available ACF context key if present in the special meta
				$field_key = get_post_meta( $post_id, '_' . $meta_key, true );
				if ( is_string( $field_key ) && $field_key !== '' ) {
					$payload = array_merge( $payload, array( 'acf_field_key' => $field_key ) );
				}

				// Use a stable string id so dedupe by AS works per meta key change
				$meta_id = sprintf( 'post:%d:%s', $post_id, $meta_key );
				$this->dispatcher->schedule( 'update', 'meta', $meta_id, $payload );
			}
		}

		// Always schedule upstream post update so consumers receive entity-level event
		$this->emitPostUpdate( $post_id );
	}

	private function handleTermMetaChange( int $term_id, string $meta_key, $meta_value, bool $deleted = false ): void {
		if ( $this->isAcfValueMeta( 'term', $term_id, $meta_key ) ) {
			$term     = get_term( $term_id );
			$taxonomy = $term && isset( $term->taxonomy ) ? (string) $term->taxonomy : null;
			if ( $taxonomy !== null ) {
				$payload = array_merge(
					Payload::for_term( $taxonomy ),
					array(
						'meta_key' => $meta_key,
						'deleted'  => $deleted,
					)
				);

				$field_key = get_term_meta( $term_id, '_' . $meta_key, true );
				if ( is_string( $field_key ) && $field_key !== '' ) {
					$payload = array_merge( $payload, array( 'acf_field_key' => $field_key ) );
				}

				$meta_id = sprintf( 'term:%d:%s', $term_id, $meta_key );
				$this->dispatcher->schedule( 'update', 'meta', $meta_id, $payload );
			}
		}

		$this->emitTermUpdate( $term_id );
	}

	private function handleUserMetaChange( int $user_id, string $meta_key, $meta_value, bool $deleted = false ): void {
		if ( $this->isAcfValueMeta( 'user', $user_id, $meta_key ) ) {
			$payload = array_merge(
				Payload::for_user( $user_id ),
				array(
					'meta_key' => $meta_key,
					'deleted'  => $deleted,
				)
			);

			$field_key = get_user_meta( $user_id, '_' . $meta_key, true );
			if ( is_string( $field_key ) && $field_key !== '' ) {
				$payload = array_merge( $payload, array( 'acf_field_key' => $field_key ) );
			}

			$meta_id = sprintf( 'user:%d:%s', $user_id, $meta_key );
			$this->dispatcher->schedule( 'update', 'meta', $meta_id, $payload );
		}

		$this->emitUserUpdate( $user_id );
	}

	private function emitMetaForPostAcf( int $post_id, array $field ): void {
		// field should contain 'name' and/or 'key'
		$post_type = get_post_type( $post_id );
		if ( ! $post_type ) {
			return;
		}

		$payload  = array_merge( Payload::for_post( $post_type ), Payload::from_acf_field( $field ) );
		$meta_key = isset( $field['name'] ) && is_string( $field['name'] ) ? $field['name'] : null;
		if ( $meta_key !== null ) {
			$payload['meta_key'] = $meta_key;
		}

		$meta_id = sprintf( 'post:%d:%s', $post_id, $meta_key ?? uniqid( 'acf', true ) );
		$this->dispatcher->schedule( 'update', 'meta', $meta_id, $payload );
	}

	private function emitMetaForTermAcf( int $term_id, array $field ): void {
		$term     = get_term( $term_id );
		$taxonomy = $term && isset( $term->taxonomy ) ? (string) $term->taxonomy : null;
		if ( $taxonomy === null ) {
			return;
		}

		$payload  = array_merge( Payload::for_term( $taxonomy ), Payload::from_acf_field( $field ) );
		$meta_key = isset( $field['name'] ) && is_string( $field['name'] ) ? $field['name'] : null;
		if ( $meta_key !== null ) {
			$payload['meta_key'] = $meta_key;
		}

		$meta_id = sprintf( 'term:%d:%s', $term_id, $meta_key ?? uniqid( 'acf', true ) );
		$this->dispatcher->schedule( 'update', 'meta', $meta_id, $payload );
	}

	private function emitMetaForUserAcf( int $user_id, array $field ): void {
		$payload  = array_merge( Payload::for_user( $user_id ), Payload::from_acf_field( $field ) );
		$meta_key = isset( $field['name'] ) && is_string( $field['name'] ) ? $field['name'] : null;
		if ( $meta_key !== null ) {
			$payload['meta_key'] = $meta_key;
		}

		$meta_id = sprintf( 'user:%d:%s', $user_id, $meta_key ?? uniqid( 'acf', true ) );
		$this->dispatcher->schedule( 'update', 'meta', $meta_id, $payload );
	}

	/**
	 * Decide whether the given meta key on the object looks like an ACF field value meta.
	 * We consider a meta an ACF value meta if the corresponding "_meta_key" exists and
	 * contains a field key (prefixed with "field_").
	 */
	private function isAcfValueMeta( string $objectType, int $objectId, string $metaKey ): bool {
		if ( strpos( $metaKey, '_' ) === 0 ) {
			// This is likely the stored field-key meta itself, not the value meta.
			return false;
		}

		if ( $objectType === 'post' ) {
			$val = get_post_meta( $objectId, '_' . $metaKey, true );
			return is_string( $val ) && $val !== '' && strpos( $val, 'field_' ) === 0;
		}

		if ( $objectType === 'term' ) {
			if ( ! function_exists( 'get_term_meta' ) ) {
				return false;
			}
			$val = get_term_meta( $objectId, '_' . $metaKey, true );
			return is_string( $val ) && $val !== '' && strpos( $val, 'field_' ) === 0;
		}

		if ( $objectType === 'user' ) {
			$val = get_user_meta( $objectId, '_' . $metaKey, true );
			return is_string( $val ) && $val !== '' && strpos( $val, 'field_' ) === 0;
		}

		return false;
	}

	private function emitPostUpdate( int $post_id ): void {
		$post_type = get_post_type( $post_id );
		if ( ! $post_type ) {
			return;
		}

		$allowed = ( new \ReflectionProperty( $this->postEmitter, 'allowedPostTypes' ) );
		$allowed->setAccessible( true );
		$allowedTypes = (array) $allowed->getValue( $this->postEmitter );
		if ( ! in_array( $post_type, $allowedTypes, true ) ) {
			return;
		}

		$this->dispatcher->schedule( 'update', 'post', $post_id, Payload::for_post( $post_type ) );
	}

	private function emitTermUpdate( int $term_id ): void {
		$term     = get_term( $term_id );
		$taxonomy = $term && isset( $term->taxonomy ) ? (string) $term->taxonomy : null;
		if ( $taxonomy === null ) {
			return;
		}
		$this->dispatcher->schedule( 'update', 'term', $term_id, Payload::for_term( $taxonomy ) );
	}

	private function emitUserUpdate( int $user_id ): void {
		$this->dispatcher->schedule( 'update', 'user', $user_id, Payload::for_user( $user_id ) );
	}
}
