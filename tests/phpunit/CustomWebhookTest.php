<?php
/**
 * Covers custom webhook registration and delivery.
 *
 * @package juvo\WP_Webhook_Framework\Tests
 */

declare(strict_types=1);

/**
 * Verifies consumer-defined webhook classes integrate through the registry.
 */
final class CustomWebhookTest extends WPWF_Webhook_Test_Case {

	/**
	 * Ensures the fixture custom webhook can schedule and dispatch a payload.
	 */
	public function test_custom_webhook_trigger_dispatches_expected_payload(): void {
		\do_action(
			'wpwf_test_custom_event',
			'job-123',
			array(
				'status' => 'queued',
			)
		);

		$actions = $this->get_scheduled_actions_by_webhook_name( 'custom_primary' );

		$this->assertCount( 1, $actions );
		$this->assertSame( 'trigger', $actions[0]['action'] );
		$this->assertSame( 'custom', $actions[0]['entity'] );
		$this->assertSame( 'job-123', $actions[0]['id'] );

		$this->run_scheduled_webhooks();

		$request = $this->get_captured_request_by_webhook_name( 'custom_primary' );

		$this->assertSame( $this->get_receiver_webhook_url( 'primary-custom' ), $request['url'] );
		$this->assertSame( 'trigger', $request['body']['action'] );
		$this->assertSame( 'custom', $request['body']['entity'] );
		$this->assertSame( 'job-123', $request['body']['id'] );
		$this->assertSame( 'job-123', $request['body']['reference'] );
		$this->assertSame( array( 'status' => 'queued' ), $request['body']['context'] );
	}
}
