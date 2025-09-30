<?php
/**
 * ServiceProvider class for the WP Webhook Framework.
 *
 * @package Citation\WP_Webhook_Framework
 */

namespace Citation\WP_Webhook_Framework;

use Citation\WP_Webhook_Framework\Entities\Post;
use Citation\WP_Webhook_Framework\Entities\Term;
use Citation\WP_Webhook_Framework\Entities\User;
use Citation\WP_Webhook_Framework\Entities\Meta;
use Citation\WP_Webhook_Framework\Support\AcfUtil;
use Citation\WP_Webhook_Framework\Webhooks\PostWebhook;
use Citation\WP_Webhook_Framework\Webhooks\TermWebhook;
use Citation\WP_Webhook_Framework\Webhooks\UserWebhook;
use Citation\WP_Webhook_Framework\Webhooks\MetaWebhook;

/**
 * Class ServiceProvider
 *
 * Registers WordPress hooks and wires emitters to the dispatcher.
 * Supports both the new registry pattern and legacy direct instantiation.
 */
class ServiceProvider {

	/**
	 * Singleton instance.
	 *
	 * @var ServiceProvider|null
	 */
	private static ?ServiceProvider $instance = null;

	/**
	 * The webhook dispatcher instance.
	 *
	 * @var Dispatcher
	 */
	private Dispatcher $dispatcher;

	/**
	 * The webhook registry instance.
	 *
	 * @var WebhookRegistry
	 */
	private WebhookRegistry $registry;

	/**
	 * Whether to use registry pattern (default: true).
	 *
	 * @var bool
	 */
	private bool $use_registry = true;

	/**
	 * Private constructor to prevent direct instantiation.
	 *
	 * @param Dispatcher|null $dispatcher Optional dispatcher instance.
	 * @param bool            $use_registry Whether to use registry pattern.
	 */
	private function __construct( ?Dispatcher $dispatcher = null, bool $use_registry = true ) {
		$this->dispatcher = $dispatcher ?: new Dispatcher();
		$this->use_registry = $use_registry;
		$this->registry = WebhookRegistry::instance( $this->dispatcher );
	}

	/**
	 * Get singleton instance.
	 *
	 * @param Dispatcher|null $dispatcher Optional dispatcher instance.
	 * @param bool            $use_registry Whether to use registry pattern.
	 * @return ServiceProvider
	 */
	private static function get_instance( ?Dispatcher $dispatcher = null, bool $use_registry = true ): ServiceProvider {
		if ( null === self::$instance ) {
			self::$instance = new self( $dispatcher, $use_registry );
		}
		return self::$instance;
	}

	/**
	 * Registers all actions/filters. Safe to call multiple times.
	 *
	 * @param bool $use_registry Whether to use the new registry pattern (default: true).
	 */
	public static function register( bool $use_registry = true ): void {

		$instance = self::get_instance( null, $use_registry );

		add_action(
			'wpwf_send_webhook',
			array( $instance->dispatcher, 'process_scheduled_webhook' ),
			10,
			5
		);

		if ( $instance->use_registry ) {
			$instance->register_with_registry();
		} else {
			$instance->register_legacy();
		}
	}

	/**
	 * Register webhooks using the new registry pattern.
	 */
	private function register_with_registry(): void {
		// Register core webhooks with default configuration
		$this->registry->register( new PostWebhook() );
		$this->registry->register( new TermWebhook() );
		$this->registry->register( new UserWebhook() );
		$this->registry->register( new MetaWebhook() );

		/**
		 * Allow third parties to register custom webhooks.
		 *
		 * @param WebhookRegistry $registry The webhook registry instance.
		 */
		do_action( 'wpwf_register_webhooks', $this->registry );

		// Initialize all registered webhooks
		$this->registry->init_all();
	}

	/**
	 * Register webhooks using the legacy direct instantiation pattern.
	 *
	 * Maintained for backwards compatibility.
	 */
	private function register_legacy(): void {

		$post_emitter = new Post( $this->dispatcher );
		$term_emitter = new Term( $this->dispatcher );
		$user_emitter = new User( $this->dispatcher );
		$meta_emitter = new Meta( $this->dispatcher, $post_emitter, $term_emitter, $user_emitter );

		add_action( 'save_post', array( $post_emitter, 'on_save_post' ), 10, 3 );
		add_action( 'before_delete_post', array( $post_emitter, 'on_delete_post' ), 10, 1 );

		add_action( 'created_term', array( $term_emitter, 'on_created_term' ), 10, 3 );
		add_action( 'edited_term', array( $term_emitter, 'on_edited_term' ), 10, 3 );
		add_action( 'delete_term', array( $term_emitter, 'on_deleted_term' ), 10, 3 );

		add_action( 'user_register', array( $user_emitter, 'on_user_register' ), 10, 1 );
		add_action( 'profile_update', array( $user_emitter, 'on_profile_update' ), 10, 1 );
		add_action( 'deleted_user', array( $user_emitter, 'on_deleted_user' ), 10, 1 );

		add_action( 'deleted_post_meta', array( $meta_emitter, 'on_deleted_post_meta' ), 10, 4 );
		add_action( 'deleted_term_meta', array( $meta_emitter, 'on_deleted_term_meta' ), 10, 4 );
		add_action( 'deleted_user_meta', array( $meta_emitter, 'on_deleted_user_meta' ), 10, 4 );

		add_filter(
			'acf/update_value',
			function ( $value, $object_id, $field, $original ) use ( $meta_emitter ) {
				[$entity, $id] = AcfUtil::parse_object_id( $object_id );

				if ( null === $entity || null === $id ) {
					return $value;
				}

				$meta_emitter->acf_update_handler( $entity, (int) $id, is_array( $field ) ? $field : array(), $value, $original );
				return $value;
			},
			10,
			4
		);

		// Add filters for all meta types with high priority to run late
		add_filter( 'update_post_metadata', array( $meta_emitter, 'on_updated_post_meta' ), 999, 5 );
		add_filter( 'update_term_metadata', array( $meta_emitter, 'on_updated_term_meta' ), 999, 5 );
		add_filter( 'update_user_metadata', array( $meta_emitter, 'on_updated_user_meta' ), 999, 5 );
	}

	/**
	 * Get the webhook registry instance.
	 *
	 * @return WebhookRegistry
	 */
	public static function get_registry(): WebhookRegistry {
		$instance = self::get_instance();
		return $instance->registry;
	}

	/**
	 * Get the dispatcher instance.
	 *
	 * @return Dispatcher
	 */
	public static function get_dispatcher(): Dispatcher {
		$instance = self::get_instance();
		return $instance->dispatcher;
	}
}
