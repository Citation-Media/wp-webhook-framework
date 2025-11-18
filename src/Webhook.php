<?php
/**
 * Base webhook class with configuration methods.
 *
 * @package Citation\WP_Webhook_Framework
 */

declare(strict_types=1);

namespace Citation\WP_Webhook_Framework;

/**
 * Base webhook class with configuration capabilities.
 *
 * Provides configuration methods for webhook behavior including retry policies,
 * timeout settings, and other webhook-specific configurations.
 */
abstract class Webhook {

	/**
	 * The webhook identifier/name.
	 *
	 * @var string
	 * @phpstan-var non-empty-string
	 */
	protected string $name;

	/**
	 * Maximum consecutive failures before URL is blocked.
	 *
	 * Defines threshold of consecutive failed webhook deliveries before the URL is blocked.
	 * Default 10 means URL blocks after 10 consecutive failures.
	 *
	 * @var int
	 * @phpstan-var positive-int
	 */
	protected int $max_consecutive_failures = 10;

	/**
	 * Request timeout in seconds.
	 *
	 * @var int
	 * @phpstan-var int<1,300>
	 */
	protected int $timeout = 30;

	/**
	 * Whether this webhook is enabled.
	 *
	 * @var bool
	 */
	protected bool $enabled = true;

	/**
	 * Custom webhook URL (optional).
	 *
	 * @var string
	 */
	protected string $webhook_url = "";

	/**
	 * Additional HTTP headers for webhook requests.
	 *
	 * Stateless configuration set during init(), not per-emission data.
	 *
	 * @var array<string,string>
	 * @phpstan-var array<non-empty-string,non-empty-string>
	 */
	protected array $headers = array();

	/**
	 * Constructor.
	 *
	 * @param string $name The webhook identifier/name.
	 * @phpstan-param non-empty-string $name
	 */
	public function __construct( string $name ) {
		$this->name                         = $name;
		$this->headers['wpwf-webhook-name'] = $name;
	}

	/**
	 * Set maximum consecutive failures before URL blocking.
	 *
	 * Defines how many consecutive failed webhook deliveries trigger URL blocking.
	 *
	 * @param int $failures Maximum number of consecutive failures allowed.
	 * @return static
	 * @phpstan-param positive-int $failures
	 * @phpstan-return static
	 */
	public function max_consecutive_failures(int $failures ): static {
		$this->max_consecutive_failures = max( 1, $failures );
		return $this;
	}

	/**
	 * Set the request timeout in seconds.
	 *
	 * @param int $timeout Timeout in seconds (1-300).
	 * @return static
	 * @phpstan-param positive-int $timeout
	 * @phpstan-return static
	 */
	public function timeout( int $timeout ): static {
		$this->timeout = max( 1, min( 300, $timeout ) );
		return $this;
	}

	/**
	 * Enable or disable this webhook.
	 *
	 * @param bool $enabled Whether the webhook is enabled.
	 * @return static
	 * @phpstan-return static
	 */
	public function enabled( bool $enabled = true ): static {
		$this->enabled = $enabled;
		return $this;
	}

	/**
	 * Set a custom webhook URL for this specific webhook.
	 *
	 * @param string|null $url The webhook URL or null to use default.
	 * @return static
	 * @phpstan-param non-empty-string|null $url
	 * @phpstan-return static
	 */
	public function webhook_url( ?string $url ): static {
		$this->webhook_url = $url;
		return $this;
	}

	/**
	 * Add custom HTTP headers for webhook requests.
	 *
	 * @param array<string,string> $headers Additional headers.
	 * @return static
	 * @phpstan-param array<non-empty-string,non-empty-string> $headers
	 * @phpstan-return static
	 */
	public function headers( array $headers ): static {
		$this->headers = array_merge( $this->headers, $headers );
		return $this;
	}

	/**
	 * Get the webhook name/identifier.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Get the maximum consecutive failures threshold.
	 *
	 * Returns how many consecutive failures are allowed before URL is blocked.
	 * Applies 'wpwf_max_consecutive_failures' filter for runtime modification.
	 *
	 * @return int
	 */
	public function get_max_consecutive_failures(): int {
		/**
		 * Filter the maximum consecutive failures threshold.
		 *
		 * @param int    $max_failures The maximum consecutive failures threshold.
		 * @param string $webhook_name The webhook name/identifier.
		 */
		return (int) apply_filters( 'wpwf_max_consecutive_failures', $this->max_consecutive_failures, $this->name );
	}

	/**
	 * Get the request timeout.
	 *
	 * Applies 'wpwf_timeout' filter for runtime modification.
	 *
	 * @return int
	 */
	public function get_timeout(): int {
		/**
		 * Filter the webhook request timeout in seconds.
		 *
		 * @param int    $timeout      The timeout in seconds (1-300).
		 * @param string $webhook_name The webhook name/identifier.
		 */
		return (int) apply_filters( 'wpwf_timeout', $this->timeout, $this->name );
	}

	/**
	 * Check if this webhook is enabled.
	 *
	 * Applies 'wpwf_webhook_enabled' filter for runtime modification.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		/**
		 * Filter whether the webhook is enabled.
		 *
		 * @param bool   $enabled      Whether the webhook is enabled.
		 * @param string $webhook_name The webhook name/identifier.
		 */
		return (bool) apply_filters( 'wpwf_webhook_enabled', $this->enabled, $this->name );
	}

	/**
	 * Get the custom webhook URL if set.
	 *
	 * @return string
	 */
	public function get_webhook_url(): string {
		return $this->webhook_url;
	}

	/**
	 * Get additional HTTP headers.
	 *
	 * Returns stateless configuration headers set during init().
	 *
	 * @return array<string,string>
	 */
	public function get_headers(): array {
		return $this->headers;
	}

	/**
	 * Initialize the webhook (register hooks, etc.).
	 *
	 * This method will be called by the registry when registering the webhook.
	 */
	abstract public function init(): void;

	/**
	 * Emit a webhook with the given parameters.
	 *
	 * Schedules a webhook delivery via the Dispatcher using this webhook's configuration.
	 * Payload and headers passed as parameters are merged with configured headers.
	 *
	 * @param string              $action      The action type (create, update, delete).
	 * @param string              $entity_type The entity type (post, term, user, meta).
	 * @param int|string          $entity_id   The entity ID.
	 * @param array<string,mixed> $payload     Dynamic payload data for this emission.
	 * @param array<string,mixed> $headers     Optional dynamic headers (merged with get_headers()).
	 */
	protected function emit( string $action, string $entity_type, int|string $entity_id, array $payload = array(), array $headers = array() ): void {
		$registry   = Webhook_Registry::instance();
		$dispatcher = $registry->get_dispatcher();

		// Merge passed headers with configured headers (passed headers take precedence)
		$final_headers = array_merge( $this->get_headers(), $headers );

		try {
			$dispatcher->schedule( $action, $entity_type, $entity_id, $this->get_webhook_url(), $payload, $final_headers );
		} catch ( \WP_Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error, WordPress.Security.EscapeOutput.OutputNotEscaped -- Error handling context, no escaping needed.
			trigger_error( sprintf( 'Failed to schedule webhook "%s": %s', $this->name, $e->getMessage() ), E_USER_WARNING );
		}
	}
}
