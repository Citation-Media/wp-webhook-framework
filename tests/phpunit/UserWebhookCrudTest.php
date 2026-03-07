<?php
/**
 * Covers user lifecycle webhook behaviour.
 *
 * @package juvo\WP_Webhook_Framework\Tests
 */

declare(strict_types=1);

/**
 * Verifies user webhooks dispatch stable CRUD payloads.
 */
final class UserWebhookCrudTest extends WPWF_Webhook_Test_Case {

	/**
	 * Ensures user create, update, and delete events dispatch stable payloads.
	 */
	public function test_user_create_update_and_delete_webhooks(): void {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'editor',
			)
		);

		$actions = $this->get_scheduled_actions_by_webhook_name( 'user' );

		$this->assertCount( 1, $actions );
		$this->assertSame( 'create', $actions[0]['action'] );

		$this->run_scheduled_webhooks();

		$request = $this->get_captured_request_by_webhook_name( 'user' );

		$this->assertSame( 'https://wpwf.test/primary/user', $request['url'] );
		$this->assertSame( 'create', $request['body']['action'] );
		$this->assertSame( array( 'editor' ), $request['body']['roles'] );
		$this->assertStringContainsString( '/wp/v2/users/' . $user_id, $request['body']['rest_url'] );

		$this->reset_captured_requests();

		\wp_update_user(
			array(
				'ID'           => $user_id,
				'display_name' => 'Updated editor',
			)
		);

		$actions = $this->get_scheduled_actions_by_webhook_name( 'user' );
		$this->assertCount( 1, $actions );
		$this->assertSame( 'update', $actions[0]['action'] );

		$this->run_scheduled_webhooks();

		$request = $this->get_captured_request_by_webhook_name( 'user' );
		$this->assertSame( 'update', $request['body']['action'] );
		$this->assertSame( array( 'editor' ), $request['body']['roles'] );

		$this->reset_captured_requests();

		\wp_delete_user( $user_id );

		$actions = $this->get_scheduled_actions_by_webhook_name( 'user' );
		$this->assertCount( 1, $actions );
		$this->assertSame( 'delete', $actions[0]['action'] );

		$this->run_scheduled_webhooks();

		$request = $this->get_captured_request_by_webhook_name( 'user' );
		$this->assertSame( 'delete', $request['body']['action'] );
		$this->assertSame( 'user', $request['body']['entity'] );
		$this->assertSame( $user_id, $request['body']['id'] );
	}
}
