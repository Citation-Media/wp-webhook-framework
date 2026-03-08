<?php
/**
 * Bootstraps the wp-env WordPress PHPUnit suite.
 *
 * @package juvo\WP_Webhook_Framework\Tests
 */

declare(strict_types=1);

$tests_dir = getenv( 'WP_TESTS_DIR' );

if ( false === $tests_dir || '' === $tests_dir ) {
	$tests_dir = '/tmp/wordpress-tests-lib';
}

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' );
}

require_once $tests_dir . '/includes/functions.php';

/**
 * Loads the fixture plugin entrypoints for the WordPress test bootstrap.
 *
 * The `plugins` setting in `.wp-env.json` activates both fixtures on the
 * development site, but the core PHPUnit bootstrap uses its own install.
 * Loading the plugin files here keeps the plugin-owned framework boot logic
 * intact while matching that isolated test runtime.
 */
function wpwf_load_fixture_plugins(): void {
	$wp_content_dir = dirname( __DIR__, 3 );

	require_once $wp_content_dir . '/mu-plugins/wpwf-test-receiver.php';
	require_once $wp_content_dir . '/plugins/wpwf-test-app/wpwf-test-app.php';
	require_once $wp_content_dir . '/plugins/wpwf-test-secondary/wpwf-test-secondary.php';
}

call_user_func( 'tests_add_filter', 'muplugins_loaded', 'wpwf_load_fixture_plugins' );

require_once $tests_dir . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/class-wpwf-webhook-test-case.php';
