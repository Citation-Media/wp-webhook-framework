<?php
/**
 * PostEmitter class for handling post-related webhook events.
 *
 * @package CitationMedia\WpWebhookFramework\Entities
 */

namespace CitationMedia\WpWebhookFramework\Entities;

use CitationMedia\WpWebhookFramework\Dispatcher;
use CitationMedia\WpWebhookFramework\Support\Payload;

/**
 * Class PostEmitter
 *
 * Emits webhooks for post lifecycle and meta changes.
 * Restricted to configured post types (default: empty array).
 */
class PostEmitter extends AbstractEmitter {

	/**
	 * Array of allowed post types for webhook emission.
	 *
	 * @var string[]
	 */
	private array $allowed_post_types;

	/**
	 * Constructor for PostEmitter.
	 *
	 * Initializes the PostEmitter with a dispatcher and allowed post types.
	 *
	 * @param Dispatcher    $dispatcher         The webhook dispatcher instance.
	 * @param array<string> $allowed_post_types Array of allowed post types.
	 */
	public function __construct( Dispatcher $dispatcher, array $allowed_post_types = array() ) {
		parent::__construct( $dispatcher );
		$this->allowed_post_types = $allowed_post_types;
	}

	/**
	 * Handle post save event (create/update).
	 *
	 * @param int      $post_id The post ID.
	 * @param \WP_Post $post    The post object.
	 * @param bool     $update  Whether this is an update or new post.
	 */
	public function onSavePost( int $post_id, \WP_Post $post, bool $update ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$post_type = get_post_type( $post_id );
		if ( ! $post_type || ! in_array( $post_type, $this->allowed_post_types, true ) ) {
			return;
		}

		$action = $update ? 'update' : 'create';
		$this->emit( $post_id, $action );
	}

	/**
	 * Handle post deletion event.
	 *
	 * @param int $post_id The post ID.
	 */
	public function onDeletePost( int $post_id ): void {
		$post_type = get_post_type( $post_id );
		if ( ! $post_type || ! in_array( $post_type, $this->allowed_post_types, true ) ) {
			return;
		}

		$this->emit( $post_id, 'delete' );
	}

	/**
	 * Emit a webhook for a post action.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $action  The action performed (create/update/delete).
	 */
	public function emit( int $post_id, string $action ): void {
		$this->schedule( $action, 'post', $post_id, Payload::post( $post_id ) );
	}
}
