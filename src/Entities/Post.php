<?php
/**
 * PostEmitter class for handling post-related webhook events.
 *
 * @package Citation\WP_Webhook_Framework\Entities
 */

namespace Citation\WP_Webhook_Framework\Entities;

use Citation\WP_Webhook_Framework\Dispatcher;
use Citation\WP_Webhook_Framework\Support\Payload;

/**
 * Class PostEmitter
 *
 * Emits webhooks for post lifecycle and meta changes.
 * Restricted to configured post types (default: empty array).
 */
class Post extends Emitter {

	/**
	 * Constructor for PostEmitter.
	 *
	 * Initializes the PostEmitter with a dispatcher.
	 *
	 * @param Dispatcher $dispatcher The webhook dispatcher instance.
	 */
	public function __construct( Dispatcher $dispatcher ) {
		parent::__construct( $dispatcher );
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

		$action = $update ? 'update' : 'create';
		$this->emit( $post_id, $action );
	}

	/**
	 * Handle post deletion event.
	 *
	 * @param int $post_id The post ID.
	 */
	public function onDeletePost( int $post_id ): void {
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
