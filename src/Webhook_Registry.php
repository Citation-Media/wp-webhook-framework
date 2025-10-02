<?php
/**
 * Webhook registry for managing webhook instances.
 *
 * @package Citation\WP_Webhook_Framework
 */

declare(strict_types=1);

namespace Citation\WP_Webhook_Framework;

/**
 * Registry for managing webhook instances and enabling third-party extensibility.
 *
 * Provides a centralized way to register, configure, and manage webhook instances
 * for both core framework webhooks and third-party extensions.
 */
class Webhook_Registry {

	/**
	 * Singleton instance.
	 *
	 * @var Webhook_Registry|null
	 */
	private static ?Webhook_Registry $instance = null;

	/**
	 * Registered webhooks.
	 *
	 * @var array<string,Webhook>
	 */
	private array $webhooks = array();

	/**
	 * The dispatcher instance.
	 *
	 * @var Dispatcher
	 */
	private Dispatcher $dispatcher;

	/**
	 * Private constructor to prevent direct instantiation.
	 *
	 * @param Dispatcher|null $dispatcher Optional dispatcher instance.
	 */
	private function __construct( ?Dispatcher $dispatcher = null ) {
		$this->dispatcher = $dispatcher ?: new Dispatcher();
	}

	/**
	 * Get singleton instance.
	 *
	 * @param Dispatcher|null $dispatcher Optional dispatcher instance.
	 * @return Webhook_Registry
	 */
	public static function instance( ?Dispatcher $dispatcher = null ): Webhook_Registry {
		if ( null === self::$instance ) {
			self::$instance = new self( $dispatcher );
		}
		return self::$instance;
	}

	/**
	 * Register a webhook instance.
	 *
	 * @param Webhook $webhook The webhook instance to register.
	 * @return static
	 * @phpstan-return static
	 *
	 * @throws \InvalidArgumentException If webhook name is already registered.
	 */
	public function register( Webhook $webhook ): static {
		$name = $webhook->get_name();

		if ( isset( $this->webhooks[ $name ] ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Webhook with name "%s" is already registered.', $name )
			);
		}

		$this->webhooks[ $name ] = $webhook;

		// Initialize the webhook if it's enabled
		if ( $webhook->is_enabled() ) {
			$webhook->init();
		}

		return $this;
	}

	/**
	 * Get a registered webhook by name.
	 *
	 * @param string $name The webhook name.
	 * @return Webhook|null
	 * @phpstan-param non-empty-string $name
	 */
	public function get( string $name ): ?Webhook {
		return $this->webhooks[ $name ] ?? null;
	}

	/**
	 * Get all registered webhooks.
	 *
	 * @return array<string,Webhook>
	 * @phpstan-return array<non-empty-string,Webhook>
	 */
	public function get_all(): array {
		return $this->webhooks;
	}

	/**
	 * Get all enabled webhooks.
	 *
	 * @return array<string,Webhook>
	 * @phpstan-return array<non-empty-string,Webhook>
	 */
	public function get_enabled(): array {
		return array_filter(
			$this->webhooks,
			static function ( Webhook $webhook ): bool {
				return $webhook->is_enabled();
			}
		);
	}

	/**
	 * Check if a webhook is registered.
	 *
	 * @param string $name The webhook name.
	 * @return bool
	 * @phpstan-param non-empty-string $name
	 */
	public function has( string $name ): bool {
		return isset( $this->webhooks[ $name ] );
	}

	/**
	 * Unregister a webhook.
	 *
	 * @param string $name The webhook name.
	 * @return bool True if webhook was found and removed, false otherwise.
	 * @phpstan-param non-empty-string $name
	 */
	public function unregister( string $name ): bool {
		if ( isset( $this->webhooks[ $name ] ) ) {
			unset( $this->webhooks[ $name ] );
			return true;
		}
		return false;
	}

	/**
	 * Get the dispatcher instance.
	 *
	 * @return Dispatcher
	 */
	public function get_dispatcher(): Dispatcher {
		return $this->dispatcher;
	}

	/**
	 * Initialize all registered webhooks.
	 *
	 * Called by the ServiceProvider to initialize all webhooks.
	 */
	public function init_all(): void {
		foreach ( $this->get_enabled() as $webhook ) {
			$webhook->init();
		}
	}

	/**
	 * Get webhook configuration by name.
	 *
	 * @param string $name The webhook name.
	 * @return array<string,mixed>|null
	 * @phpstan-param non-empty-string $name
	 * @phpstan-return array{name: string, allowed_retries: int, timeout: int, enabled: bool, webhook_url: string|null, headers: array<string,string>}|null
	 */
	public function get_config( string $name ): ?array {
		$webhook = $this->get( $name );
		return $webhook ? $webhook->get_config() : null;
	}

	/**
	 * Get all webhook configurations.
	 *
	 * @return array<string,array<string,mixed>>
	 * @phpstan-return array<non-empty-string,array{name: string, allowed_retries: int, timeout: int, enabled: bool, webhook_url: string|null, headers: array<string,string>}>
	 */
	public function get_all_configs(): array {
		$configs = array();
		foreach ( $this->webhooks as $name => $webhook ) {
			$configs[ $name ] = $webhook->get_config();
		}
		return $configs;
	}

	/**
	 * Apply webhook-specific configuration to dispatcher arguments.
	 *
	 * This method allows webhooks to customize the HTTP request arguments
	 * based on their configuration (timeout, headers, etc.).
	 *
	 * @param array<string,mixed> $args    Original HTTP request arguments.
	 * @param string              $webhook_name The webhook name.
	 * @return array<string,mixed> Modified arguments.
	 * @phpstan-param array<string,mixed> $args
	 * @phpstan-param non-empty-string $webhook_name
	 * @phpstan-return array<string,mixed>
	 */
	public function apply_webhook_config( array $args, string $webhook_name ): array {
		$webhook = $this->get( $webhook_name );
		if ( ! $webhook ) {
			return $args;
		}

		// Apply timeout
		$args['timeout'] = $webhook->get_timeout();

		// Apply custom headers
		$webhook_headers = $webhook->get_headers();
		if ( ! empty( $webhook_headers ) ) {
			$args['headers'] = array_merge( $args['headers'] ?? array(), $webhook_headers );
		}

		return $args;
	}
}