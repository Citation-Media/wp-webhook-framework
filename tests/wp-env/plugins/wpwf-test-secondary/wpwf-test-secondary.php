<?php
/**
 * Plugin Name: WPWF Test Secondary
 * Description: Registers a secondary consumer webhook for multi-plugin validation.
 * Version: 1.0.0
 *
 * @package juvo\WP_Webhook_Framework\Tests
 */

declare(strict_types=1);

namespace juvo\WP_Webhook_Framework\Tests\Fixtures\Secondary;

use juvo\WP_Webhook_Framework\Service_Provider;
use juvo\WP_Webhook_Framework\Webhook_Registry;
use juvo\WP_Webhook_Framework\Webhooks\Post_Webhook;

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
 * Registers a second post webhook from another plugin namespace.
 *
 * @param Webhook_Registry $registry The framework registry.
 */
function register_secondary_webhooks( Webhook_Registry $registry ): void {
	$post_webhook = new Post_Webhook( 'post_secondary' );
	$post_webhook->webhook_url( 'https://wpwf.test/secondary/post' );

	$registry->register( $post_webhook );
}

\add_action( 'wpwf_register_webhooks', __NAMESPACE__ . '\\register_secondary_webhooks' );
