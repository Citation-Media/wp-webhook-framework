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
use Citation\WP_Webhook_Framework\Entities\Post;
use Citation\WP_Webhook_Framework\Entities\Term;
use Citation\WP_Webhook_Framework\Entities\User;
use Citation\WP_Webhook_Framework\Webhook_Registry;
use Citation\WP_Webhook_Framework\Support\AcfUtil;

/**
 * Meta webhook implementation with configuration capabilities.
 *
 * Handles meta-related webhook events with configurable retry policies,
 * timeouts, and other webhook-specific settings.
 */
class Meta_Webhook extends Webhook {

	/**
	 * The meta emitter instance.
	 *
	 * @var Meta
	 */
	private Meta $meta_emitter;

	/**
	 * Constructor.
	 *
	 * @param string $name The webhook name.
	 * @phpstan-param non-empty-string $name
	 */
	public function __construct( string $name = 'meta' ) {
		parent::__construct( $name );
		
		// Get dispatcher and emitters from registry
		$registry = Webhook_Registry::instance();
		$dispatcher = $registry->get_dispatcher();
		
		// We need access to other emitters for Meta emitter
		$post_webhook = $registry->get( 'post' );
		$term_webhook = $registry->get( 'term' );
		$user_webhook = $registry->get( 'user' );
		
		// If other webhooks aren't registered yet, create temporary emitters
		$post_emitter = $post_webhook ? $post_webhook->get_emitter() : new Post( $dispatcher );
		$term_emitter = $term_webhook ? $term_webhook->get_emitter() : new Term( $dispatcher );
		$user_emitter = $user_webhook ? $user_webhook->get_emitter() : new User( $dispatcher );
		
		$this->meta_emitter = new Meta( $dispatcher, $post_emitter, $term_emitter, $user_emitter );
	}

	/**
	 * Initialize the webhook by registering WordPress hooks.
	 */
	public function init(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		add_action( 'deleted_post_meta', array( $this->meta_emitter, 'on_deleted_post_meta' ), 10, 4 );
		add_action( 'deleted_term_meta', array( $this->meta_emitter, 'on_deleted_term_meta' ), 10, 4 );
		add_action( 'deleted_user_meta', array( $this->meta_emitter, 'on_deleted_user_meta' ), 10, 4 );

		add_filter(
			'acf/update_value',
			function ( $value, $object_id, $field, $original ): mixed {
				[$entity, $id] = AcfUtil::parse_object_id( $object_id );

				if ( null === $entity || null === $id ) {
					return $value;
				}

				$this->meta_emitter->acf_update_handler( $entity, (int) $id, is_array( $field ) ? $field : array(), $value, $original );
				return $value;
			},
			10,
			4
		);

		// Add filters for all meta types with high priority to run late
		add_filter( 'update_post_metadata', array( $this->meta_emitter, 'on_updated_post_meta' ), 999, 5 );
		add_filter( 'update_term_metadata', array( $this->meta_emitter, 'on_updated_term_meta' ), 999, 5 );
		add_filter( 'update_user_metadata', array( $this->meta_emitter, 'on_updated_user_meta' ), 999, 5 );
	}

	/**
	 * Get the meta emitter instance.
	 *
	 * @return Meta
	 */
	public function get_emitter(): Meta {
		return $this->meta_emitter;
	}
}