<?php
/**
 * Failure notification handler.
 *
 * @package Citation\WP_Webhook_Framework
 */

namespace Citation\WP_Webhook_Framework\Notifications;

/**
 * Sends email notifications when webhooks fail after reaching threshold.
 */
class Blocked {

	/**
	 * Register hooks for failure notifications.
	 */
	public function init(): void {
		add_action( 'wpwf_webhook_blocked', array( $this, 'send_failure_notification' ), 10, 3 );
	}

	/**
	 * Send failure notification email to admin.
	 *
	 * Triggered when a webhook URL is blocked due to consecutive failures.
	 *
	 * @param string $url          The webhook URL that was blocked.
	 * @param mixed  $response     The response from wp_remote_post.
	 * @param int    $max_failures Maximum consecutive failures before blocking.
	 */
	public function send_failure_notification( string $url, \WP_Error|array $response, int $max_failures ): void {
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

		$message = sprintf(
			/* translators: 1: URL, 2: Max failures threshold, 3: Error message, 4: Time */
			__(
				'A webhook URL has been blocked due to consecutive failures.

URL: %1$s
Consecutive Failures: %2$d
Last Error: %3$s
Time: %4$s

This URL will be automatically unblocked after 1 hour. No webhooks will be delivered to this URL until then.',
				'wp-webhook-framework'
			),
			$url,
			$max_failures,
			$error_message,
			current_time( 'mysql' )
		);

		// Set default subject
		$subject = sprintf(
			/* translators: %s: Site name */
			__( 'Webhook URL Blocked - %s', 'wp-webhook-framework' ),
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

		// Skip sending if filter returns false
		if ( false === $email_data ) {
			return;
		}

		// Send the email with potentially modified data
		wp_mail(
			$email_data['recipient'],
			$email_data['subject'],
			$email_data['message'],
			$email_data['headers']
		);
	}
}
