<?php
/**
 * Term webhook implementation.
 *
 * @package Citation\WP_Webhook_Framework\Webhooks
 */

declare(strict_types=1);

namespace Citation\WP_Webhook_Framework\Webhooks;

use Citation\WP_Webhook_Framework\Webhook;
use Citation\WP_Webhook_Framework\Entities\Term;
use Citation\WP_Webhook_Framework\WebhookRegistry;

/**
 * Term webhook implementation with configuration capabilities.
 *
 * Handles term-related webhook events with configurable retry policies,
 * timeouts, and other webhook-specific settings.
 */
class TermWebhook extends Webhook {

	/**
	 * The term emitter instance.
	 *
	 * @var Term
	 */
	private Term $term_emitter;

	/**
	 * Constructor.
	 *
	 * @param string $name The webhook name.
	 * @phpstan-param non-empty-string $name
	 */
	public function __construct( string $name = 'term' ) {
		parent::__construct( $name );
		
		// Get dispatcher from registry
		$registry = WebhookRegistry::instance();
		$this->term_emitter = new Term( $registry->get_dispatcher() );
	}

	/**
	 * Initialize the webhook by registering WordPress hooks.
	 */
	public function init(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		add_action( 'created_term', array( $this->term_emitter, 'on_created_term' ), 10, 3 );
		add_action( 'edited_term', array( $this->term_emitter, 'on_edited_term' ), 10, 3 );
		add_action( 'delete_term', array( $this->term_emitter, 'on_deleted_term' ), 10, 3 );
	}

	/**
	 * Get the term emitter instance.
	 *
	 * @return Term
	 */
	public function get_emitter(): Term {
		return $this->term_emitter;
	}
}