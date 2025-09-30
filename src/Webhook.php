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
	 */
	protected string $name;

	/**
	 * Number of allowed retry attempts.
	 *
	 * @var int
	 */
	protected int $allowed_retries = 3;

	/**
	 * Request timeout in seconds.
	 *
	 * @var int
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
	 */
	protected ?string $webhook_url = null;

	/**
	 * Additional HTTP headers for webhook requests.
	 *
	 * @var array<string,string>
	 */
	protected array $headers = array();

	/**
	 * Constructor.
	 *
	 * @param string $name The webhook identifier/name.
	 */
	public function __construct( string $name ) {
		$this->name = $name;
	}

	/**
	 * Set the number of allowed retry attempts.
	 *
	 * @param int $retries Number of retry attempts (0-10).
	 * @return static
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
	 * Initialize the webhook (register hooks, etc.).
	 *
	 * This method will be called by the registry when registering the webhook.
	 */
	abstract public function init(): void;

	/**
	 * Get the webhook configuration as an array.
	 *
	 * @return array<string,mixed>
	 */
	public function get_config(): array {
		return array(
			'name'            => $this->name,
			'allowed_retries' => $this->allowed_retries,
			'timeout'         => $this->timeout,
			'enabled'         => $this->enabled,
			'webhook_url'     => $this->webhook_url,
			'headers'         => $this->headers,
		);
	}
}