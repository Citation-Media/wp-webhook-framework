<?php
/**
 * Service_Provider class for the WP Webhook Framework.
 *
 * @package Citation\WP_Webhook_Framework
 */

namespace Citation\WP_Webhook_Framework;

use Citation\WP_Webhook_Framework\Entities\Post;
use Citation\WP_Webhook_Framework\Entities\Term;
use Citation\WP_Webhook_Framework\Entities\User;
use Citation\WP_Webhook_Framework\Entities\Meta;
use Citation\WP_Webhook_Framework\Support\AcfUtil;
use Citation\WP_Webhook_Framework\Webhooks\Post_Webhook;
use Citation\WP_Webhook_Framework\Webhooks\Term_Webhook;
use Citation\WP_Webhook_Framework\Webhooks\User_Webhook;
use Citation\WP_Webhook_Framework\Webhooks\Meta_Webhook;

/**
 * Class Service_Provider
 *
 * Registers WordPress hooks and wires emitters to the dispatcher using the registry pattern.
 */
class Service_Provider {

	/**
	 * Singleton instance.
	 *
	 * @var Service_Provider|null
	 */
	private static ?Service_Provider $instance = null;

	/**
	 * The webhook dispatcher instance.
	 *
	 * @var Dispatcher
	 */
	private Dispatcher $dispatcher;

	/**
	 * The webhook registry instance.
	 *
	 * @var Webhook_Registry
	 */
	private Webhook_Registry $registry;

	/**
	 * Private constructor to prevent direct instantiation.
	 *
	 * @param Dispatcher|null $dispatcher Optional dispatcher instance.
	 */
	private function __construct( ?Dispatcher $dispatcher = null ) {
		$this->dispatcher = $dispatcher ?: new Dispatcher();
		$this->registry = Webhook_Registry::instance( $this->dispatcher );
	}

	/**
	 * Get singleton instance.
	 *
	 * @param Dispatcher|null $dispatcher Optional dispatcher instance.
	 * @return Service_Provider
	 */
	private static function get_instance( ?Dispatcher $dispatcher = null ): Service_Provider {
		if ( null === self::$instance ) {
			self::$instance = new self( $dispatcher );
		}
		return self::$instance;
	}

	/**
	 * Registers all actions/filters. Safe to call multiple times.
	 */
	public static function register(): void {

		$instance = self::get_instance();

		add_action(
			'wpwf_send_webhook',
			array( $instance->dispatcher, 'process_scheduled_webhook' ),
			10,
			5
		);

		$instance->register_webhooks();
	}

	/**
	 * Register webhooks using the registry pattern.
	 */
	private function register_webhooks(): void {
		// Register core webhooks with default configuration
		$this->registry->register( new Post_Webhook() );
		$this->registry->register( new Term_Webhook() );
		$this->registry->register( new User_Webhook() );
		$this->registry->register( new Meta_Webhook() );

		/**
		 * Allow third parties to register custom webhooks.
		 *
		 * @param Webhook_Registry $registry The webhook registry instance.
		 */
		do_action( 'wpwf_register_webhooks', $this->registry );

		// Initialize all registered webhooks
		$this->registry->init_all();
	}

	/**
	 * Get the webhook registry instance.
	 *
	 * @return Webhook_Registry
	 */
	public static function get_registry(): Webhook_Registry {
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
