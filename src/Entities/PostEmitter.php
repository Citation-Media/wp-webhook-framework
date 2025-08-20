<?php

namespace CitationMedia\WpWebhookFramework\Entities;
use CitationMedia\WpWebhookFramework\Dispatcher;
use CitationMedia\WpWebhookFramework\Support\Payload;

/**
 * Emits webhooks for post lifecycle and meta changes.
 * Restricted to configured post types (default: ['zg_products']).
 */
class PostEmitter {

	private Dispatcher $dispatcher;
	/** @var string[] */
	private array $allowedPostTypes;

	/**
	 * @param string[] $allowedPostTypes
	 */
	public function __construct( Dispatcher $dispatcher, array $allowedPostTypes = array( 'zg_products' ) ) {
		$this->dispatcher       = $dispatcher;
		$this->allowedPostTypes = $allowedPostTypes;
	}

	public function onSavePost( int $post_id, \WP_Post $post, bool $update ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$post_type = get_post_type( $post_id );
		if ( ! $post_type || ! in_array( $post_type, $this->allowedPostTypes, true ) ) {
			return;
		}

		$action = $update ? 'update' : 'create';
		$this->dispatcher->schedule( $action, 'post', $post_id, Payload::for_post( $post_type ) );
	}

	public function onDeletePost( int $post_id ): void {
		$post_type = get_post_type( $post_id );
		if ( ! $post_type || ! in_array( $post_type, $this->allowedPostTypes, true ) ) {
			return;
		}

		$this->dispatcher->schedule( 'delete', 'post', $post_id, Payload::for_post( $post_type ) );
	}

	/**
	 * Handle ACF update routed to a post.
	 *
	 * @param array<string,mixed> $field
	 */
	public function onAcfUpdate( int $post_id, array $field ): void {
		$post_type = get_post_type( $post_id );
		if ( ! $post_type || ! in_array( $post_type, $this->allowedPostTypes, true ) ) {
			return;
		}

		$payload = array_merge(
			Payload::for_post( $post_type ),
			Payload::from_acf_field( $field )
		);

		$this->dispatcher->schedule( 'update', 'post', $post_id, $payload );
	}

	private function emitUpdateForPost( int $post_id ): void {
		$post_type = get_post_type( $post_id );
		if ( ! $post_type || ! in_array( $post_type, $this->allowedPostTypes, true ) ) {
			return;
		}

		$this->dispatcher->schedule( 'update', 'post', (int) $post_id, Payload::for_post( $post_type ) );
	}
}
