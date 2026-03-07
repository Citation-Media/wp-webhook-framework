<?php
/**
 * Covers post lifecycle webhook behaviour.
 *
 * @package juvo\WP_Webhook_Framework\Tests
 */

declare(strict_types=1);

/**
 * Verifies post webhooks dispatch stable CRUD payloads for multiple consumers.
 */
final class PostWebhookCrudTest extends WPWF_Webhook_Test_Case {

	/**
	 * Ensures post create, update, and delete events dispatch through both fixture consumers.
	 */
	public function test_post_create_update_and_delete_webhooks(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title' => 'Initial title',
				'post_type'  => 'post',
			)
		);

		$primary_actions   = $this->get_scheduled_actions_by_webhook_name( 'post' );
		$secondary_actions = $this->get_scheduled_actions_by_webhook_name( 'post_secondary' );

		$this->assertCount( 1, $primary_actions );
		$this->assertCount( 1, $secondary_actions );
		$this->assertSame( 'create', $primary_actions[0]['action'] );
		$this->assertSame( 'post', $primary_actions[0]['entity'] );
		$this->assertSame( $post_id, $primary_actions[0]['id'] );

		$this->run_scheduled_webhooks();

		$primary_request   = $this->get_captured_request_by_webhook_name( 'post' );
		$secondary_request = $this->get_captured_request_by_webhook_name( 'post_secondary' );

		$this->assertSame( 'https://wpwf.test/primary/post', $primary_request['url'] );
		$this->assertSame( 'create', $primary_request['body']['action'] );
		$this->assertSame( 'post', $primary_request['body']['entity'] );
		$this->assertSame( $post_id, $primary_request['body']['id'] );
		$this->assertSame( 'post', $primary_request['body']['post_type'] );
		$this->assertStringContainsString( '/wp/v2/posts/' . $post_id, $primary_request['body']['rest_url'] );

		$this->assertSame( 'https://wpwf.test/secondary/post', $secondary_request['url'] );
		$this->assertSame( 'create', $secondary_request['body']['action'] );
		$this->assertSame( $post_id, $secondary_request['body']['id'] );

		$this->reset_captured_requests();

		\wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Updated title',
			)
		);

		$primary_actions   = $this->get_scheduled_actions_by_webhook_name( 'post' );
		$secondary_actions = $this->get_scheduled_actions_by_webhook_name( 'post_secondary' );

		$this->assertCount( 1, $primary_actions );
		$this->assertCount( 1, $secondary_actions );
		$this->assertSame( 'update', $primary_actions[0]['action'] );

		$this->run_scheduled_webhooks();

		$primary_request   = $this->get_captured_request_by_webhook_name( 'post' );
		$secondary_request = $this->get_captured_request_by_webhook_name( 'post_secondary' );

		$this->assertSame( 'update', $primary_request['body']['action'] );
		$this->assertSame( 'post', $primary_request['body']['post_type'] );
		$this->assertSame( 'update', $secondary_request['body']['action'] );

		$this->reset_captured_requests();

		\wp_delete_post( $post_id, true );

		$primary_actions   = $this->get_scheduled_actions_by_webhook_name( 'post' );
		$secondary_actions = $this->get_scheduled_actions_by_webhook_name( 'post_secondary' );

		$this->assertCount( 1, $primary_actions );
		$this->assertCount( 1, $secondary_actions );
		$this->assertSame( 'delete', $primary_actions[0]['action'] );

		$this->run_scheduled_webhooks();

		$primary_request   = $this->get_captured_request_by_webhook_name( 'post' );
		$secondary_request = $this->get_captured_request_by_webhook_name( 'post_secondary' );

		$this->assertSame( 'delete', $primary_request['body']['action'] );
		$this->assertSame( 'post', $primary_request['body']['entity'] );
		$this->assertSame( $post_id, $primary_request['body']['id'] );
		$this->assertSame( 'delete', $secondary_request['body']['action'] );
	}
}
