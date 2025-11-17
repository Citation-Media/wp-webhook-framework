<?php
/**
 * Webhook dispatcher class.
 *
 * @package Citation\WP_Webhook_Framework
 */

declare(strict_types=1);

namespace Citation\WP_Webhook_Framework;

use ActionScheduler_Store;

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
	 * @param string              $action  The action type.
	 * @param string              $entity  The entity type.
	 * @param int|string          $id      The entity ID.
	 * @param array<string,mixed> $payload The request payload data.
	 * @param array<string,mixed> $headers The request headers.
	 *
	 * @throws \WP_Exception If Action Scheduler is not active or URL/payload issues.
	 */
	public function schedule( string $url, string $action, string $entity, int|string $id, array $payload = array(), array $headers = array() ): void {

		if ( ! function_exists( 'as_schedule_single_action' ) || ! function_exists( 'as_get_scheduled_actions' ) ) {
			throw new \WP_Exception('action_scheduler_not_active');
		}

		// Apply filter if no webhook-specific URL was set
		$url = apply_filters('wpwf_url', $url,  $entity,  $id);

		// Constants always take precedence over filters for reliability
		if (
			defined( 'WP_WEBHOOK_FRAMEWORK_URL' )
			&& WP_WEBHOOK_FRAMEWORK_URL !== ''
			&& is_string( WP_WEBHOOK_FRAMEWORK_URL )
		) {
			$url = WP_WEBHOOK_FRAMEWORK_URL;
		}

		if ( empty( $url ) ) {
			throw new \WP_Exception('webhook_url_not_set');
		}

		// Check if this URL is blocked due to too many failures
		if ( $this->is_url_blocked( $url ) ) {
			throw new \WP_Exception('webhook_url_blocked');
		}

		$payload = apply_filters('wpwf_payload', $payload,  $entity,  $id);
		if (empty($payload)) {
			throw new \WP_Exception('webhook_payload_empty');
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
				'url'          => $url,
				'action'       => $action,
				'entity'       => $entity,
				'id'           => $id,
				'payload'      => $payload,
				'headers'      => $headers
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
	 * @throws \WP_Exception If Action Scheduler is not active or URL is blocked.
	 */
	public function process_scheduled_webhook( string $url, string $action, string $entity, $id, array $payload, array $headers ): void {

		// Check if this URL is blocked due to too many failures
		if ( $this->is_url_blocked( $url ) ) {
			throw new \WP_Exception('action_scheduler_not_active');
		}

		// Reconstruct webhook instance from registry
		$registry = Webhook_Registry::instance();
		$webhook = $registry->get( $headers['wpwf-webhook-name'] );

		$body = array_merge(
			$payload,
			array(
				'action' => $action,
				'entity' => $entity,
				'id'     => $id,
			)
		);

		if (!isset($headers['Content-Type'])) {
			$headers['Content-Type'] = 'application/json';
		}
		$headers = apply_filters('wpwf_headers', $headers,  $entity,  $id, $webhook?->get_name()) ;

		$args = array(
			'body'     => wp_json_encode( $body ),
			'headers'  => $headers,
			'timeout'  => $webhook?->get_timeout() ?? 10,
			'blocking' => true,
		);

		$response = wp_remote_post( $url, $args );

		// Check if the request was successful
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$this->handle_webhook_failure( $url, $response, $webhook );
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
	}

	/**
	 * Handle failed webhook delivery.
	 *
	 * @param string       $url      The webhook URL.
	 * @param mixed        $response The response from wp_remote_post.
	 * @param Webhook|null $webhook  Optional webhook instance for configuration.
	 */
	private function handle_webhook_failure( string $url, $response, ?Webhook $webhook = null ): void {

		$failure_dto = Failure::from_transient( $url );
		$failure_dto->increment_count();
		$failure_dto->save( $url );

		// Check if retries are allowed and we haven't exceeded them
		$allowed_retries = $webhook?->get_allowed_retries() ?? 0;
		if ( $allowed_retries > 0 && $failure_dto->get_count() <= $allowed_retries ) {
			// Don't send notification or block yet - we'll retry
			return;
		}

		// Send notification email on first failure after retries exhausted
		if ( $failure_dto->get_count() === ( $allowed_retries + 1 ) ) {
			$this->send_failure_notification( $url, $response );
		}

		// Block URL if more than 10 consecutive failures in 1 hour
		if ( $failure_dto->get_count() > 10 && ! $failure_dto->is_blocked() ) {
			$this->block_url( $url );
		}
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
	 * Send failure notification email to admin.
	 *
	 * @param string $url      The webhook URL.
	 * @param mixed  $response The response from wp_remote_post.
	 */
	private function send_failure_notification( string $url, $response ): void {
		$admin_email = get_option( 'admin_email' );
		if ( ! $admin_email ) {
			return;
		}

		// Set default error message
		$error_message = '';
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
		} else {
			$status_code   = wp_remote_retrieve_response_code( $response );
			$error_message = sprintf(
				/* translators: %d: HTTP status code */
				__( 'HTTP Status Code: %d', 'wp-webhook-framework' ),
				$status_code
			);
		}

		// Set default message
		$message = sprintf(
			/* translators: 1: URL, 2: Error message, 3: Time */
			__(
				'A webhook delivery has failed.

URL: %1$s
Error: %2$s
Time: %3$s

This webhook will be blocked after 10 consecutive failures within 1 hour.',
				'wp-webhook-framework'
			),
			$url,
			$error_message,
			current_time( 'mysql' )
		);

		// Set default subject
		$subject = sprintf(
			/* translators: %s: Site name */
			__( 'Webhook Delivery Failed - %s', 'wp-webhook-framework' ),
			get_bloginfo( 'name' )
		);

		// Set default recipient
		$recipient = $admin_email;

		// Set default headers
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		// Apply custom filter to allow modification of email data
		$email_data = apply_filters(
			'wpwf_failure_notification_email',
			array(
				'recipient'     => $recipient,
				'subject'       => $subject,
				'message'       => $message,
				'headers'       => $headers,
				'url'           => $url,
				'error_message' => $error_message,
				'response'      => $response,
			),
			$url,
			$response
		);

		// Send the email with potentially modified data
		wp_mail(
			$email_data['recipient'],
			$email_data['subject'],
			$email_data['message'],
			$email_data['headers']
		);
	}
}
