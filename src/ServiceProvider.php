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
 * Registers WordPress hooks and wires emitters to the dispatcher using the registry pattern.
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
	 * Private constructor to prevent direct instantiation.
	 *
	 * @param Dispatcher|null $dispatcher Optional dispatcher instance.
	 */
	private function __construct( ?Dispatcher $dispatcher = null ) {
		$this->dispatcher = $dispatcher ?: new Dispatcher();
		$this->registry = WebhookRegistry::instance( $this->dispatcher );
	}

	/**
	 * Get singleton instance.
	 *
	 * @param Dispatcher|null $dispatcher Optional dispatcher instance.
	 * @return ServiceProvider
	 */
	private static function get_instance( ?Dispatcher $dispatcher = null ): ServiceProvider {
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
