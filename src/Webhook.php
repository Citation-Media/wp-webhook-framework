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
	 * Number of allowed retry attempts.
	 *
	 * @var int
	 * @phpstan-var int<0,10>
	 */
	protected int $allowed_retries = 0;

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
	 * @var string|null
	 * @phpstan-var non-empty-string|null
	 */
	protected ?string $webhook_url = null;

	/**
	 * Additional HTTP headers for webhook requests.
	 *
	 * @var array<string,string>
	 * @phpstan-var array<non-empty-string,non-empty-string>
	 */
	protected array $headers = array();

	/**
	 * The webhook payload data.
	 *
	 * @var array<string,mixed>
	 * @phpstan-var array<string,mixed>
	 */
	protected array $payload = array();

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
	 * Set the number of allowed retry attempts.
	 *
	 * @param int $retries Number of retry attempts (0-10).
	 * @return static
	 * @phpstan-param positive-int|0 $retries
	 * @phpstan-return static
	 */
	public function allowed_retries( int $retries ): static {
		$this->allowed_retries = max( 0, min( 10, $retries ) );
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
	 * Get the allowed retry count.
	 *
	 * @return int
	 */
	public function get_allowed_retries(): int {
		return $this->allowed_retries;
	}

	/**
	 * Get the request timeout.
	 *
	 * @return int
	 */
	public function get_timeout(): int {
		return $this->timeout;
	}

	/**
	 * Check if this webhook is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return $this->enabled;
	}

	/**
	 * Get the custom webhook URL if set.
	 *
	 * @return string|null
	 */
	public function get_webhook_url(): ?string {
		return $this->webhook_url;
	}

	/**
	 * Get additional HTTP headers.
	 *
	 * @return array<string,string>
	 */
	public function get_headers(): array {
		return $this->headers;
	}

	/**
	 * Get the webhook payload data.
	 *
	 * @return array<string,mixed>
	 * @phpstan-return array<string,mixed>
	 */
	public function get_payload(): array {
		return $this->payload;
	}

	/**
	 * Set the webhook payload data.
	 *
	 * @param array<string,mixed> $payload The payload data.
	 * @phpstan-param array<string,mixed> $payload
	 */
	public function set_payload( array $payload ): void {
		$this->payload = $payload;
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
	 * Passes the webhook instance for strongly-typed configuration access.
	 *
	 * @param string     $action      The action type (create, update, delete).
	 * @param string     $entity_type The entity type (post, term, user, meta).
	 * @param int|string $entity_id   The entity ID.
	 */
	protected function emit( string $action, string $entity_type, int|string $entity_id ): void {
		$registry   = Webhook_Registry::instance();
		$dispatcher = $registry->get_dispatcher();

		try {
			$dispatcher->schedule( $this->get_webhook_url(), $action, $entity_type, $entity_id, $this->get_payload(), $this->get_headers() );
		} catch ( \WP_Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error, WordPress.Security.EscapeOutput.OutputNotEscaped -- Error handling context, no escaping needed.
			trigger_error( sprintf( 'Failed to schedule webhook "%s": %s', $this->name, $e->getMessage() ), E_USER_WARNING );
		}
	}
}
