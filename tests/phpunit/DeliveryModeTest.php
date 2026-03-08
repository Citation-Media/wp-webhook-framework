<?php
/**
 * Covers scheduled and immediate delivery modes.
 *
 * @package juvo\WP_Webhook_Framework\Tests
 */

declare(strict_types=1);

use juvo\WP_Webhook_Framework\Delivery_Mode;
use juvo\WP_Webhook_Framework\Service_Provider;
use juvo\WP_Webhook_Framework\Webhook;

/**
 * Verifies webhook delivery mode behavior across scheduling and immediate sends.
 */
final class DeliveryModeTest extends WPWF_Webhook_Test_Case {

	/**
	 * Ensures scheduled mode queues work and delivers only after action execution.
	 */
	public function test_scheduled_mode_queues_and_delivers_via_action_scheduler(): void {
		$webhook = $this->register_test_webhook(
			'scheduled',
			$this->get_receiver_webhook_url( 'delivery-mode-scheduled' ),
			Delivery_Mode::SCHEDULED
		);

		$webhook->trigger_event( 'job-scheduled', array( 'scope' => 'scheduled' ) );

		$this->assertCount( 0, $this->get_captured_requests() );

		$actions = $this->get_scheduled_actions_by_webhook_name( $webhook->get_name() );
		$this->assertCount( 1, $actions );

		$this->run_scheduled_webhooks();

		$request = $this->get_captured_request_by_webhook_name( $webhook->get_name() );
		$this->assertSame( 'trigger', $request['body']['action'] );
		$this->assertSame( 'delivery_mode', $request['body']['entity'] );
		$this->assertSame( 'job-scheduled', $request['body']['id'] );
		$this->assertSame( array( 'scope' => 'scheduled' ), $request['body']['context'] );
	}

	/**
	 * Ensures immediate mode sends the first attempt without queuing.
	 */
	public function test_immediate_mode_sends_without_queueing(): void {
		$webhook = $this->register_test_webhook(
			'immediate',
			$this->get_receiver_webhook_url( 'delivery-mode-immediate' ),
			Delivery_Mode::IMMEDIATE
		);

		$webhook->trigger_event( 'job-immediate', array( 'scope' => 'immediate' ) );

		$requests = $this->get_captured_requests();
		$this->assertCount( 1, $requests );
		$this->assertSame( $webhook->get_name(), $requests[0]['webhook_name'] );

		$actions = $this->get_scheduled_actions_by_webhook_name( $webhook->get_name() );
		$this->assertCount( 0, $actions );
	}

	/**
	 * Register a dedicated test webhook with an isolated unique name.
	 *
	 * @param string        $suffix        Name suffix for readability.
	 * @param string        $url           Delivery URL.
	 * @param Delivery_Mode $delivery_mode Delivery mode under test.
	 * @return Delivery_Mode_Test_Webhook
	 */
	private function register_test_webhook( string $suffix, string $url, Delivery_Mode $delivery_mode ): Delivery_Mode_Test_Webhook {
		$name    = 'delivery_mode_' . $suffix . '_' . str_replace( '.', '', uniqid( '', true ) );
		$webhook = new Delivery_Mode_Test_Webhook( $name, $url, $delivery_mode );

		Service_Provider::get_registry()->register( $webhook );

		return $webhook;
	}
}

/**
 * Emits fixture payloads for delivery mode tests.
 */
final class Delivery_Mode_Test_Webhook extends Webhook {

	/**
	 * Configure URL and delivery mode for this fixture webhook.
	 *
	 * @param string        $name          Unique webhook name.
	 * @param string        $url           Delivery URL.
	 * @param Delivery_Mode $delivery_mode Delivery mode under test.
	 */
	public function __construct( string $name, string $url, Delivery_Mode $delivery_mode ) {
		parent::__construct( $name );

		$this->webhook_url( $url );
		$this->delivery_mode( $delivery_mode );
	}

	/**
	 * No WordPress hooks are needed for this fixture webhook.
	 */
	public function init(): void {
	}

	/**
	 * Emit a deterministic payload for assertions.
	 *
	 * @param string              $reference Stable ID used by assertions.
	 * @param array<string,mixed> $context   Additional context.
	 */
	public function trigger_event( string $reference, array $context = array() ): void {
		$this->emit(
			'trigger',
			'delivery_mode',
			$reference,
			array(
				'reference' => $reference,
				'context'   => $context,
			)
		);
	}
}
