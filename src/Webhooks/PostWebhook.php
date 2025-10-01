<?php
/**
 * Post webhook implementation.
 *
 * @package Citation\WP_Webhook_Framework\Webhooks
 */

declare(strict_types=1);

namespace Citation\WP_Webhook_Framework\Webhooks;

use Citation\WP_Webhook_Framework\Webhook;
use Citation\WP_Webhook_Framework\Entities\Post;
use Citation\WP_Webhook_Framework\WebhookRegistry;

/**
 * Post webhook implementation with configuration capabilities.
 *
 * Handles post-related webhook events with configurable retry policies,
 * timeouts, and other webhook-specific settings.
 */
class PostWebhook extends Webhook {

	/**
	 * The post emitter instance.
	 *
	 * @var Post
	 */
	private Post $post_emitter;

	/**
	 * Constructor.
	 *
	 * @param string $name The webhook name.
	 * @phpstan-param non-empty-string $name
	 */
	public function __construct( string $name = 'post' ) {
		parent::__construct( $name );
		
		// Get dispatcher from registry
		$registry = WebhookRegistry::instance();
		$this->post_emitter = new Post( $registry->get_dispatcher() );
	}

	/**
	 * Initialize the webhook by registering WordPress hooks.
	 */
	public function init(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		add_action( 'save_post', array( $this->post_emitter, 'on_save_post' ), 10, 3 );
		add_action( 'before_delete_post', array( $this->post_emitter, 'on_delete_post' ), 10, 1 );
	}

	/**
	 * Get the post emitter instance.
	 *
	 * @return Post
	 */
	public function get_emitter(): Post {
		return $this->post_emitter;
	}
}