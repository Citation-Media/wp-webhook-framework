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
	 * @param string              $action The action type.
	 * @param string              $entity The entity type.
	 * @param int|string          $id The entity ID.
	 * @param array<string,mixed> $payload The payload data.
	 */
	public function schedule( string $action, string $entity, int|string $id, array $payload = array() ): void {

		if ( ! function_exists( 'as_schedule_single_action' ) || ! function_exists( 'as_get_scheduled_actions' ) ) {
			return;
		}

		// Apply filter first to allow customization
		$url = apply_filters(
			'wpwf_url',
			'',
			$entity,
			$id,
			$action,
			$payload
		);

		// Constants always take precedence over filters for reliability
		if (
			defined( 'WP_WEBHOOK_FRAMEWORK_URL' )
			&& WP_WEBHOOK_FRAMEWORK_URL !== ''
			&& is_string( WP_WEBHOOK_FRAMEWORK_URL )
		) {
			$url = WP_WEBHOOK_FRAMEWORK_URL;
		}

		if ( empty( $url ) ) {
			return; // No URL provided, nothing to schedule.
		}

		// Apply filter to allow modification of the payload
		$filtered_payload = apply_filters(
			'wpwf_payload',
			$payload,
			$entity,
			$action
		);

		// If the filtered payload is empty, don't schedule the webhook
		if ( empty( $filtered_payload ) ) {
			return;
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
			),
			'wpwf'
		);
	}

	/**
	 * Action Scheduler callback. Sends the POST request non-blocking.
	 *
	 * @param string              $url The webhook URL.
	 * @param string              $action The action type.
	 * @param string              $entity The entity type.
	 * @param int|string          $id The entity ID.
	 * @param array<string,mixed> $payload The payload data.
	 */
	public function process_scheduled_webhook( string $url, string $action, string $entity, $id, array $payload ): void {

		// Check if this URL is blocked due to too many failures
		if ( $this->is_url_blocked( $url ) ) {
			return;
		}

		$body = array_merge(
			$payload,
			array(
				'action' => $action,
				'entity' => $entity,
				'id'     => $id,
			)
		);

		$args = array(
			'body'     => wp_json_encode( $body ),
			'headers'  => array( 'Content-Type' => 'application/json' ),
			'timeout'  => 10,
			'blocking' => true, // Changed to blocking to check response
		);

		$response = wp_remote_post( $url, $args );

		// Check if the request was successful
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$this->handle_webhook_failure( $url, $response );
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
	 * @param string $url      The webhook URL.
	 * @param mixed  $response The response from wp_remote_post.
	 */
	private function handle_webhook_failure( string $url, $response ): void {

		$failure_dto = Failure::from_transient( $url );
		$failure_dto->increment_count();
		$failure_dto->save( $url );

		// Send notification email on first failure
		if ( 1 === $failure_dto->get_count() ) {
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
