<?php
/**
 * Webhook dispatcher class.
 *
 * @package Citation\WP_Webhook_Framework
 */

namespace Citation\WP_Webhook_Framework;

use ActionScheduler_Store;
use WP_Exception;

/**
 * Queues and sends webhooks. AS-only. Dedupe on action+entity+id.
 */
class Dispatcher {


	/**
	 * Schedule a webhook if not already pending with same (action, entity, id).
	 *
	 * Accepts a Webhook instance for strongly-typed configuration access during scheduling.
	 * Only the webhook name is persisted to Action Scheduler for later lookup.
	 *
	 * @param string              $url           The webhook URL.
	 * @param string              $action        The action type.
	 * @param string              $entity        The entity type.
	 * @param int|string          $id            The entity ID.
	 * @param array<string,mixed> $payload       The request payload data.
	 * @param array<string,mixed> $headers       The request headers.
	 *
	 * @throws WP_Exception If Action Scheduler is not active or URL/payload issues.
	 */
	public function schedule( string $action, string $entity, int|string $id, string $url = "", array $payload = array(), array $headers = array() ): void {

		if ( ! function_exists( 'as_schedule_single_action' ) || ! function_exists( 'as_get_scheduled_actions' ) ) {
			throw new WP_Exception( 'action_scheduler_not_active' );
		}

		// Apply filter if no webhook-specific URL was set
		$url = apply_filters( 'wpwf_url', $url, $entity, $id );

		// Constants always take precedence over filters for reliability
		if (
			defined( 'WP_WEBHOOK_FRAMEWORK_URL' )
			&& WP_WEBHOOK_FRAMEWORK_URL !== ''
			&& is_string( WP_WEBHOOK_FRAMEWORK_URL )
		) {
			$url = WP_WEBHOOK_FRAMEWORK_URL;
		}

		if ( empty( $url ) ) {
			throw new WP_Exception( 'webhook_url_not_set' );
		}

		// Check if this URL is blocked due to too many failures
		if ( $this->is_url_blocked( $url ) ) {
			throw new WP_Exception( 'webhook_url_blocked' );
		}

		$payload = apply_filters( 'wpwf_payload', $payload, $entity, $id );
		if ( empty( $payload ) ) {
			throw new WP_Exception( 'webhook_payload_empty' );
		}

		$query = as_get_scheduled_actions(
			array(
				'per_page'              => 1,
				'hook'                  => 'wpwf_send_webhook',
				'group'                 => 'wpwf',
				'status'                => ActionScheduler_Store::STATUS_PENDING,
				'args'                  => array(
					'url'    => $url,
					'action' => $action,
					'entity' => $entity,
					'id'     => $id,
				),
				'partial_args_matching' => 'json',
			)
		);

		if ( ! empty( $query ) ) {
			return;
		}

		as_schedule_single_action(
			time() + 5,
			'wpwf_send_webhook',
			array(
				'url'     => $url,
				'action'  => $action,
				'entity'  => $entity,
				'id'      => $id,
				'payload' => $payload,
				'headers' => $headers,
			),
			'wpwf'
		);
	}

	/**
	 * Action Scheduler callback. Sends the POST request non-blocking.
	 *
	 * Reconstructs the webhook instance from the registry using the persisted webhook name.
	 *
	 * @param string              $url          The webhook URL.
	 * @param string              $action       The action type.
	 * @param string              $entity       The entity type.
	 * @param int|string          $id           The entity ID.
	 * @param array<string,mixed> $payload      The payload data.
	 * @param array<string,mixed> $headers      The HTML headers.
	 *
	 * @throws WP_Exception If Action Scheduler is not active or URL is blocked.
	 */
	public function process_scheduled_webhook( string $url, string $action, string $entity, $id, array $payload, array $headers ): void {

		// Check if this URL is blocked due to too many failures
		if ( $this->is_url_blocked( $url ) ) {
			throw new WP_Exception( 'webhook_url_blocked' );
		}

		// Reconstruct webhook instance from registry
		$registry = Webhook_Registry::instance();
		$webhook  = $registry->get( $headers['wpwf-webhook-name'] ?? '' );

		$body = array_merge(
			$payload,
			array(
				'action' => $action,
				'entity' => $entity,
				'id'     => $id,
			)
		);

		if ( ! isset( $headers['Content-Type'] ) ) {
			$headers['Content-Type'] = 'application/json';
		}
		$headers = apply_filters( 'wpwf_headers', $headers, $entity, $id, $webhook?->get_name() );

		$args = array(
			'body'     => wp_json_encode( $body ),
			'headers'  => $headers,
			'timeout'  => $webhook?->get_timeout() ?? 10,
			'blocking' => true,
		);

		$response = wp_remote_post( $url, $args );

		// Check if the request was successful
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$this->schedule_retry_if_applicable( $url, $action, $entity, $id, $payload, $headers, $webhook );
			$this->trigger_webhook_failure( $url, $response, $webhook );
		} else {
			$this->handle_webhook_success( $url );
		}
	}

	/**
	 * Handle successful webhook delivery.
	 *
	 * @param string $url The webhook URL.
	 */
	private function handle_webhook_success( string $url ): void {
		// Reset failure count and unblock on success
		$failure_dto = Failure::create_fresh();
		$failure_dto->save( $url );

		do_action( 'wpwf_webhook_success', $url );
	}

	/**
	 * Handle failed webhook delivery.
	 *
	 * Tracks failed webhook events (not individual retry attempts) for blocking decisions.
	 * Each webhook event that fails (after Action Scheduler retries) increments the counter by 1.
	 *
	 * @param string       $url      The webhook URL.
	 * @param mixed        $response The response from wp_remote_post.
	 * @param Webhook $webhook  Webhook instance for configuration.
	 *
	 * @throws WP_Exception Always throws to mark Action Scheduler action as failed.
	 */
	private function trigger_webhook_failure( string $url, $response, Webhook $webhook ): void {

		// Increment consecutive failure count
		$failure_dto = Failure::from_transient( $url );
		$failure_dto->increment_count();
		$failure_dto->save( $url );

		// Get max consecutive failures threshold
		$max_failures = $webhook->get_max_consecutive_failures();

		do_action( 'wpwf_webhook_failed', $url, $response, $failure_dto->get_count(), $max_failures );

		// Block URL if consecutive failures reach threshold
		if ( $failure_dto->get_count() >= $max_failures && ! $failure_dto->is_blocked() ) {
			$this->block_url( $url );
			do_action( 'wpwf_webhook_blocked', $url, $response, $max_failures );
		}

		// Throw exception to mark Action Scheduler action as failed (triggers AS retry)
		throw new WP_Exception( 'webhook_delivery_failed' );
	}

	/**
	 * Block a URL due to too many failures.
	 *
	 * @param string $url The webhook URL.
	 */
	private function block_url( string $url ): void {
		$failure_dto = Failure::from_transient( $url );
		$failure_dto->set_blocked( true )->set_blocked_time( time() );
		$failure_dto->save( $url );
	}

	/**
	 * Check if a URL is blocked due to too many failures.
	 *
	 * @param string $url The webhook URL.
	 * @return bool True if the URL is blocked.
	 */
	private function is_url_blocked( string $url ): bool {
		$failure_dto = Failure::from_transient( $url );

		// Check if block has expired (more than 1 hour ago)
		if ( $failure_dto->is_block_expired() ) {
			// Unblock automatically
			$failure_dto->set_blocked( false );
			$failure_dto->save( $url );
			return false;
		}

		return $failure_dto->is_blocked();
	}

	/**
	 * Schedule a retry if retries are configured and not exhausted.
	 *
	 * @param string              $url          The webhook URL.
	 * @param string              $action       The action type.
	 * @param string              $entity       The entity type.
	 * @param int|string          $id           The entity ID.
	 * @param array<string,mixed> $payload      The payload data.
	 * @param array<string,mixed> $headers      The headers.
	 * @param Webhook|null        $webhook      Webhook instance for configuration.
	 */
	private function schedule_retry_if_applicable( string $url, string $action, string $entity, $id, array $payload, array $headers, ?Webhook $webhook ): void {
		if ( ! $webhook ) {
			return;
		}

		// Get current retry count from headers (default to 0)
		$retry_count = isset( $headers['wpwf-retry-count'] ) ? (int) $headers['wpwf-retry-count'] : 0;
		$max_retries = $webhook->get_max_retries();

		// Exit if max retries reached
		if ( $retry_count >= $max_retries ) {
			return;
		}

		$next_retry = $retry_count + 1;
		$delay      = $webhook->calculate_retry_delay( $next_retry );

		// Update headers with new retry count
		$headers['wpwf-retry-count'] = $next_retry;

		// Schedule the retry
		as_schedule_single_action(
			time() + $delay,
			'wpwf_send_webhook',
			array(
				'url'     => $url,
				'action'  => $action,
				'entity'  => $entity,
				'id'      => $id,
				'payload' => $payload,
				'headers' => $headers,
			),
			'wpwf'
		);
	}
}
