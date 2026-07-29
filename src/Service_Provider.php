<?php
/**
 * Service_Provider class for the WP Webhook Framework.
 *
 * @package juvo\WP_Webhook_Framework
 */

declare(strict_types=1);

namespace juvo\WP_Webhook_Framework;

use juvo\WP_Webhook_Framework\Notifications\Blocked;
use juvo\WP_Webhook_Framework\Notifications\Notification_Registry;

/**
 * Bootstraps the framework by wiring up the Dispatcher, Registry, and
 * Notification system. Does not register any webhooks -- consumers must
 * register their own via the `wpwf_register_webhooks` action.
 */
class Service_Provider {

	/**
	 * Minimum WordPress version supported by the framework.
	 *
	 * The dispatcher signals every failure by throwing `WP_Exception`, which was
	 * added to WordPress core in 6.7.0. On older releases those throw sites raise
	 * an uncatchable "class not found" fatal, so the framework refuses to boot.
	 */
	public const MIN_WP_VERSION = '6.7';

	/**
	 * Singleton instance.
	 *
	 * @var Service_Provider|null
	 */
	private static ?Service_Provider $instance = null;

	/**
	 * Registration state flag.
	 *
	 * @var bool
	 */
	private static bool $registered = false;

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
	 * The notification registry instance.
	 *
	 * @var Notification_Registry
	 */
	private Notification_Registry $notification_registry;

	/**
	 * Private constructor to prevent direct instantiation.
	 *
	 * @param Dispatcher|null $dispatcher Optional dispatcher instance.
	 */
	private function __construct( ?Dispatcher $dispatcher = null ) {
		$this->dispatcher            = $dispatcher ?: new Dispatcher();
		$this->registry              = Webhook_Registry::instance( $this->dispatcher );
		$this->notification_registry = Notification_Registry::instance();
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
	 * Check whether the current WordPress version supports the framework.
	 *
	 * @return bool True when WordPress is new enough to boot the framework.
	 */
	public static function is_supported_wp_version(): bool {
		return version_compare( (string) get_bloginfo( 'version' ), self::MIN_WP_VERSION, '>=' );
	}

	/**
	 * Print an admin notice explaining why the framework did not boot.
	 */
	public static function render_unsupported_wp_version_notice(): void {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: required WordPress version, 2: current WordPress version */
					__( 'WP Webhook Framework requires WordPress %1$s or newer and has been disabled. This site runs WordPress %2$s.', 'wp-webhook-framework' ),
					self::MIN_WP_VERSION,
					(string) get_bloginfo( 'version' )
				)
			)
		);
	}

	/**
	 * Registers all actions/filters. Safe to call multiple times.
	 *
	 * Bails out without registering anything when the WordPress version is older
	 * than {@see self::MIN_WP_VERSION}, surfacing an admin notice instead of
	 * letting the dispatcher fatal on a missing `WP_Exception` class.
	 */
	public static function register(): void {

		// Guard against duplicate registration
		if ( self::$registered ) {
			return;
		}

		if ( ! self::is_supported_wp_version() ) {
			add_action( 'admin_notices', array( self::class, 'render_unsupported_wp_version_notice' ) );
			self::$registered = true;
			return;
		}

		$instance = self::get_instance();

		add_action(
			'wpwf_send_webhook',
			array( $instance->dispatcher, 'process_scheduled_webhook' ),
			10,
			6
		);

		// Defer webhook and notification registration until 'init' to avoid race conditions.
		// This allows other plugins to hook into 'wpwf_register_webhooks' and
		// 'wpwf_register_notifications' actions before they are fired.
		add_action( 'init', array( $instance, 'on_init' ) );

		self::$registered = true;
	}

	/**
	 * Fire the registration action so consumers can register webhooks.
	 *
	 * No webhooks are registered by default. Consumers choose which
	 * webhooks to register via the `wpwf_register_webhooks` action.
	 * Each call to `Webhook_Registry::register()` calls `init()` on the
	 * webhook, activating its hooks during the WordPress 'init' action.
	 */
	private function register_webhooks(): void {
		/**
		 * Register webhooks with the framework.
		 *
		 * @param Webhook_Registry $registry The webhook registry instance.
		 */
		do_action( 'wpwf_register_webhooks', $this->registry );
	}

	/**
	 * Handle the WordPress 'init' action.
	 *
	 * Registers webhooks and notification handlers during WordPress initialization.
	 */
	public function on_init(): void {
		$this->register_available_notifications();
		$this->register_webhooks();
	}

	/**
	 * Register notification handlers.
	 *
	 * Registers available notification handlers to the registry.
	 * Notifications must be explicitly enabled per-webhook to be initialized.
	 */
	private function register_available_notifications(): void {
		// Register built-in notification handlers
		$this->notification_registry->register( new Blocked() );

		/**
		 * Allow third parties to register custom notification handlers.
		 *
		 * @param Notification_Registry $notification_registry The notification registry instance.
		 */
		do_action( 'wpwf_register_notifications', $this->notification_registry );
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
	 * Get the notification registry instance.
	 *
	 * @return Notification_Registry
	 */
	public static function get_notification_registry(): Notification_Registry {
		$instance = self::get_instance();
		return $instance->notification_registry;
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
