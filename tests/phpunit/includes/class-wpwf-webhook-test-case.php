<?php
/**
 * Shared helpers for exercising webhook scheduling and delivery.
 *
 * @package juvo\WP_Webhook_Framework\Tests
 */

declare(strict_types=1);

/**
 * Provides stable assertions around scheduled actions and dispatched payloads.
 */
abstract class WPWF_Webhook_Test_Case extends WP_UnitTestCase {

	/**
	 * Captured webhook requests for the current test.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	protected static array $captured_requests = array();

	/**
	 * Resets the queue state before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		require_once \ABSPATH . 'wp-admin/includes/user.php';

		self::$captured_requests = array();
		$this->clear_scheduled_webhooks();

		\add_filter( 'pre_http_request', array( static::class, 'capture_http_request' ), 10, 3 );
	}

	/**
	 * Removes test filters and queued work after each test.
	 */
	public function tear_down(): void {
		\remove_filter( 'pre_http_request', array( static::class, 'capture_http_request' ), 10 );

		$this->clear_scheduled_webhooks();
		self::$captured_requests = array();

		parent::tear_down();
	}

	/**
	 * Captures fixture webhook requests and short-circuits remote transport.
	 *
	 * @param mixed                $preempt Existing preempt value.
	 * @param array<string,mixed>  $args    Parsed request arguments.
	 * @param string               $url     Request URL.
	 * @return mixed
	 */
	public static function capture_http_request( $preempt, array $args, string $url ) {
		if ( ! str_starts_with( $url, 'https://wpwf.test/' ) ) {
			return $preempt;
		}

		$body = array();
		if ( isset( $args['body'] ) && is_string( $args['body'] ) ) {
			$decoded_body = json_decode( $args['body'], true );
			if ( is_array( $decoded_body ) ) {
				$body = $decoded_body;
			}
		}

		self::$captured_requests[] = array(
			'url'          => $url,
			'args'         => $args,
			'body'         => $body,
			'webhook_name' => is_array( $args['headers'] ?? null ) ? (string) ( $args['headers']['wpwf-webhook-name'] ?? '' ) : '',
		);

		return array(
			'headers'  => array(),
			'body'     => '{}',
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Returns scheduled webhook actions keyed by Action Scheduler arguments.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	protected function get_scheduled_webhook_actions(): array {
		$actions = array();
		$store   = $this->get_action_store();

		$action_ids = \as_get_scheduled_actions(
			array(
				'hook'    => 'wpwf_send_webhook',
				'status'  => \ActionScheduler_Store::STATUS_PENDING,
				'orderby' => 'date',
				'order'   => 'ASC',
				'per_page' => 100,
			),
			'ids'
		);

		foreach ( $action_ids as $action_id ) {
			$action = $store->fetch_action( $action_id );
			$args   = $action->get_args();

			if ( ! is_array( $args ) ) {
				continue;
			}

			$args['action_id'] = $action_id;
			$actions[]         = $args;
		}

		return $actions;
	}

	/**
	 * Returns scheduled webhook actions for a single webhook name.
	 *
	 * @param string $webhook_name The webhook identifier.
	 * @return array<int,array<string,mixed>>
	 */
	protected function get_scheduled_actions_by_webhook_name( string $webhook_name ): array {
		$matches = array();

		foreach ( $this->get_scheduled_webhook_actions() as $action ) {
			$headers = $action['headers'] ?? array();
			$name    = is_array( $headers ) ? (string) ( $headers['wpwf-webhook-name'] ?? '' ) : '';

			if ( $webhook_name !== $name ) {
				continue;
			}

			$matches[] = $action;
		}

		return $matches;
	}

	/**
	 * Executes all queued framework webhook actions immediately.
	 */
	protected function run_scheduled_webhooks(): void {
		$actions = $this->get_scheduled_webhook_actions();
		$store   = $this->get_action_store();

		foreach ( $actions as $action ) {
			$action_id = (int) $action['action_id'];
			$stored_action = $store->fetch_action( $action_id );
			$stored_action->execute();
			$store->mark_complete( $action_id );
			$store->delete_action( $action_id );
		}
	}

	/**
	 * Removes all queued framework webhook actions.
	 */
	protected function clear_scheduled_webhooks(): void {
		$store      = $this->get_action_store();
		$action_ids = \as_get_scheduled_actions(
			array(
				'hook'     => 'wpwf_send_webhook',
				'orderby'  => 'date',
				'order'    => 'ASC',
				'per_page' => 100,
			),
			'ids'
		);

		foreach ( $action_ids as $action_id ) {
			$store->delete_action( $action_id );
		}
	}

	/**
	 * Resolves the Action Scheduler store used by fixture actions.
	 *
	 * @return object
	 */
	private function get_action_store() {
		$store = \ActionScheduler::store();

		if ( $store instanceof \ActionScheduler_HybridStore ) {
			return new \ActionScheduler_DBStore();
		}

		return $store;
	}

	/**
	 * Clears captured webhook requests between assertion phases.
	 */
	protected function reset_captured_requests(): void {
		self::$captured_requests = array();
	}

	/**
	 * Returns all intercepted requests for the current test.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	protected function get_captured_requests(): array {
		return self::$captured_requests;
	}

	/**
	 * Returns the first captured request for a webhook name.
	 *
	 * @param string $webhook_name The webhook identifier.
	 * @return array<string,mixed>
	 */
	protected function get_captured_request_by_webhook_name( string $webhook_name ): array {
		foreach ( self::$captured_requests as $request ) {
			if ( $webhook_name === $request['webhook_name'] ) {
				return $request;
			}
		}

		$this->fail( sprintf( 'Failed to capture a request for webhook "%s".', $webhook_name ) );

		return array();
	}
}
