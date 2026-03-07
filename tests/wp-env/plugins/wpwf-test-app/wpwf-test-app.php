<?php
/**
 * Plugin Name: WPWF Test App
 * Description: Registers primary fixture webhooks for wp-env application tests.
 * Version: 1.0.0
 *
 * @package juvo\WP_Webhook_Framework\Tests
 */

declare(strict_types=1);

namespace juvo\WP_Webhook_Framework\Tests\Fixtures\App;

use juvo\WP_Webhook_Framework\Service_Provider;
use juvo\WP_Webhook_Framework\Webhook;
use juvo\WP_Webhook_Framework\Webhook_Registry;
use juvo\WP_Webhook_Framework\Webhooks\Post_Webhook;
use juvo\WP_Webhook_Framework\Webhooks\Term_Webhook;
use juvo\WP_Webhook_Framework\Webhooks\User_Webhook;

$wp_content_dir        = dirname( __DIR__, 2 );
$autoload_path         = $wp_content_dir . '/wpwf-framework/vendor/autoload.php';
$action_scheduler_path = $wp_content_dir . '/wpwf-framework/vendor/woocommerce/action-scheduler/action-scheduler.php';

if ( ! file_exists( $autoload_path ) ) {
	return;
}

require_once $autoload_path;

require_once $action_scheduler_path;

Service_Provider::register();

/**
 * Emits a custom test event so application-level integrations can be validated.
 */
final class Custom_Primary_Webhook extends Webhook {

	/**
	 * Configures the fixture webhook endpoint.
	 */
	public function __construct() {
		parent::__construct( 'custom_primary' );
		$this->webhook_url( 'https://wpwf.test/primary/custom' );
	}

	/**
	 * Hooks the fixture event into the framework.
	 */
	public function init(): void {
		\add_action( 'wpwf_test_custom_event', array( $this, 'on_custom_event' ), 10, 2 );
	}

	/**
	 * Schedules a custom webhook payload for the current fixture event.
	 *
	 * @param string              $reference The fixture event identifier.
	 * @param array<string,mixed> $context   Extra event metadata.
	 */
	public function on_custom_event( string $reference, array $context = array() ): void {
		$this->emit(
			'trigger',
			'custom',
			$reference,
			array(
				'reference' => $reference,
				'context'   => $context,
			)
		);
	}
}

/**
 * Registers the primary built-in and custom fixture webhooks.
 *
 * @param Webhook_Registry $registry The framework registry.
 */
function register_test_webhooks( Webhook_Registry $registry ): void {
	$post_webhook = new Post_Webhook();
	$post_webhook->webhook_url( 'https://wpwf.test/primary/post' );
	$registry->register( $post_webhook );

	$term_webhook = new Term_Webhook();
	$term_webhook->webhook_url( 'https://wpwf.test/primary/term' );
	$registry->register( $term_webhook );

	$user_webhook = new User_Webhook();
	$user_webhook->webhook_url( 'https://wpwf.test/primary/user' );
	$registry->register( $user_webhook );

	$registry->register( new Custom_Primary_Webhook() );
}

\add_action( 'wpwf_register_webhooks', __NAMESPACE__ . '\\register_test_webhooks' );
