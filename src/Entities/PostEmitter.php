<?php

namespace CitationMedia\WpWebhookFramework\Entities;
use CitationMedia\WpWebhookFramework\Dispatcher;
use CitationMedia\WpWebhookFramework\Support\Payload;

/**
 * Emits webhooks for post lifecycle and meta changes.
 * Restricted to configured post types (default: empty array).
 */
class PostEmitter extends AbstractEmitter {

	/** @var string[] */
	private array $allowedPostTypes;

	/**
	 * @param string[] $allowedPostTypes
	 */
	public function __construct( Dispatcher $dispatcher, array $allowedPostTypes = array() ) {
		parent::__construct( $dispatcher );
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
		$this->emit($post_id ,$action);
	}

	public function onDeletePost( int $post_id ): void {
		$post_type = get_post_type( $post_id );
		if ( ! $post_type || ! in_array( $post_type, $this->allowedPostTypes, true ) ) {
			return;
		}

		$this->emit($post_id ,'delete');
	}

	public function emit(int $post_id, string $action): void
	{
		$this->schedule( $action, 'post', $post_id, array( 'post_type' => get_post_type( $post_id) ) );
	}
}
