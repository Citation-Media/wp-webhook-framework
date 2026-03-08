<?php
/**
 * Covers structured email webhook delivery.
 *
 * @package juvo\WP_Webhook_Framework\Tests
 */

declare(strict_types=1);

use juvo\WP_Webhook_Framework\Delivery_Mode;
use juvo\WP_Webhook_Framework\Service_Provider;
use juvo\WP_Webhook_Framework\Webhooks\Email_Webhook;

/**
 * Verifies `wp_mail()` interception, structured payloads, and abort behavior.
 */
final class EmailWebhookTest extends WPWF_Webhook_Test_Case {

	/**
	 * Ensures email webhooks emit the expected structured payload.
	 */
	public function test_email_webhook_emits_structured_payload(): void {
		$webhook = $this->register_email_webhook( 'structured', true, Delivery_Mode::IMMEDIATE );

		try {
			$result = \wp_mail(
				array(
					'Jane Recipient <jane@example.com>',
					'john@example.com',
				),
				'Welcome aboard',
				'<p>Hello world</p>',
				array(
					'From: Sender Example <sender@example.com>',
					'Cc: Carbon Copy <cc@example.com>',
					'Bcc: Blind Copy <bcc@example.com>',
					'Reply-To: Reply Person <reply@example.com>',
					'Content-Type: text/html; charset=UTF-8',
					'X-Custom-Trace: abc123',
				),
				array(
					'/tmp/example.pdf',
				)
			);

			$this->assertTrue( $result );

			$request = $this->get_captured_request_by_webhook_name( $webhook->get_name() );
			$email   = $request['body']['email'];

			$this->assertSame( 'send', $request['body']['action'] );
			$this->assertSame( 'email', $request['body']['entity'] );
			$this->assertSame( 'Welcome aboard', $email['subject'] );
			$this->assertSame( '<p>Hello world</p>', $email['content'] );
			$this->assertSame( 'sender@example.com', $email['sender'] );
			$this->assertSame( 'Sender Example', $email['sender_name'] );
			$this->assertSame( 'text/html', $email['content_type'] );
			$this->assertSame( 'UTF-8', $email['charset'] );
			$this->assertSame( 'abc123', $email['headers']['x-custom-trace'] );
			$this->assertSame( '/tmp/example.pdf', $email['attachments'][0] );
			$this->assertSame( 'jane@example.com', $email['to'][0]['email'] );
			$this->assertSame( 'Jane Recipient', $email['to'][0]['name'] );
			$this->assertSame( 'john@example.com', $email['to'][1]['email'] );
			$this->assertSame( 'cc@example.com', $email['cc'][0]['email'] );
			$this->assertSame( 'bcc@example.com', $email['bcc'][0]['email'] );
			$this->assertSame( 'reply@example.com', $email['reply_to'][0]['email'] );
		} finally {
			$this->unregister_email_webhook( $webhook );
		}
	}

	/**
	 * Ensures abort mode short-circuits before PHPMailer initialization.
	 */
	public function test_email_webhook_abort_mode_prevents_phpmailer_send_flow(): void {
		$webhook          = $this->register_email_webhook( 'abort', true );
		$phpmailer_called = false;

		$phpmailer_init = static function () use ( &$phpmailer_called ): void {
			$phpmailer_called = true;
		};

		\add_action( 'phpmailer_init', $phpmailer_init, 10, 1 );

		try {
			$result = \wp_mail( 'abort@example.com', 'Abort test', 'Webhook only' );

			$this->assertTrue( $result );
			$this->assertFalse( $phpmailer_called );
			$this->assertCount( 1, $this->get_captured_requests() );
		} finally {
			\remove_action( 'phpmailer_init', $phpmailer_init, 10 );
			$this->unregister_email_webhook( $webhook );
		}
	}

	/**
	 * Ensures non-abort mode keeps the `wp_mail()` pipeline open for later hooks.
	 */
	public function test_email_webhook_can_emit_without_aborting_wp_mail(): void {
		$webhook            = $this->register_email_webhook( 'continue', false );
		$downstream_reached = false;

		$downstream_pre_wp_mail = static function ( $return, array $atts ) use ( &$downstream_reached ) {
			if ( null !== $return || 'Continue mail' !== $atts['subject'] ) {
				return $return;
			}

			$downstream_reached = true;
			return true;
		};

		\add_filter( 'pre_wp_mail', $downstream_pre_wp_mail, 20, 2 );

		try {
			$result = \wp_mail( 'continue@example.com', 'Continue mail', 'Webhook and mail flow' );

			$this->assertTrue( $result );
			$this->assertTrue( $downstream_reached );
			$this->assertCount( 1, $this->get_captured_requests() );
		} finally {
			\remove_filter( 'pre_wp_mail', $downstream_pre_wp_mail, 20 );
			$this->unregister_email_webhook( $webhook );
		}
	}

	/**
	 * Ensures the email-specific payload filter can skip webhook delivery.
	 */
	public function test_email_payload_filter_can_skip_delivery(): void {
		$webhook = $this->register_email_webhook( 'filtered', true );

		$email_payload_filter = static function ( array $email_data, array $atts ) {
			if ( 'Skip me' !== $atts['subject'] ) {
				return $email_data;
			}

			return false;
		};

		$fallback_pre_wp_mail = static function ( $return, array $atts ) {
			if ( null !== $return || 'Skip me' !== $atts['subject'] ) {
				return $return;
			}

			return true;
		};

		\add_filter( 'wpwf_email_payload', $email_payload_filter, 10, 3 );
		\add_filter( 'pre_wp_mail', $fallback_pre_wp_mail, 20, 2 );

		try {
			$result = \wp_mail( 'skip@example.com', 'Skip me', 'No webhook should be sent' );

			$this->assertTrue( $result );
			$this->assertCount( 0, $this->get_captured_requests() );
		} finally {
			\remove_filter( 'wpwf_email_payload', $email_payload_filter, 10 );
			\remove_filter( 'pre_wp_mail', $fallback_pre_wp_mail, 20 );
			$this->unregister_email_webhook( $webhook );
		}
	}

	/**
	 * Ensures the abort filter can force mail replacement at runtime.
	 */
	public function test_email_abort_filter_can_force_short_circuit(): void {
		$webhook = $this->register_email_webhook( 'abort-filter', false );

		$abort_filter = static function ( bool $abort, array $email_data ): bool {
			if ( 'Force abort' !== $email_data['subject'] ) {
				return $abort;
			}

			return true;
		};

		try {
			\add_filter( 'wpwf_email_abort_send', $abort_filter, 10, 3 );

			$result = \wp_mail( 'forced@example.com', 'Force abort', 'Abort via filter' );

			$this->assertTrue( $result );
			$this->assertCount( 1, $this->get_captured_requests() );
		} finally {
			\remove_filter( 'wpwf_email_abort_send', $abort_filter, 10 );
			$this->unregister_email_webhook( $webhook );
		}
	}

	/**
	 * Register a temporary email webhook for the current test.
	 *
	 * @param string        $suffix        Readable name suffix.
	 * @param bool          $abort_wp_mail Whether the original mail send should be aborted.
	 * @param Delivery_Mode $delivery_mode Delivery mode under test.
	 * @return Email_Webhook
	 */
	private function register_email_webhook( string $suffix, bool $abort_wp_mail, Delivery_Mode $delivery_mode = Delivery_Mode::IMMEDIATE ): Email_Webhook {
		$name    = 'email_' . $suffix . '_' . str_replace( '.', '', uniqid( '', true ) );
		$webhook = new Email_Webhook( $name );

		$webhook->webhook_url( $this->get_receiver_webhook_url( 'email-' . $suffix ) );
		$webhook->abort_wp_mail( $abort_wp_mail );
		$webhook->delivery_mode( $delivery_mode );

		Service_Provider::get_registry()->register( $webhook );

		return $webhook;
	}

	/**
	 * Remove the temporary email webhook hooks after the test completes.
	 *
	 * @param Email_Webhook $webhook The webhook instance to clean up.
	 */
	private function unregister_email_webhook( Email_Webhook $webhook ): void {
		\remove_filter( 'pre_wp_mail', array( $webhook, 'on_pre_wp_mail' ), 10 );
		Service_Provider::get_registry()->unregister( $webhook->get_name() );
	}
}
