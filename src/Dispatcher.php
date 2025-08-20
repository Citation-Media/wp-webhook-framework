<?php
/**
 * Webhook dispatcher class.
 *
 * @package CitationMedia\WpWebhookFramework
 */

declare(strict_types=1);

namespace CitationMedia\WpWebhookFramework;

use ActionScheduler_Store;

/**
 * Queues and sends webhooks. AS-only. Dedupe on action+entity+id.
 */
class Dispatcher {

	/**
	 * The webhook URL to send requests to.
	 *
	 * @var string
	 */
	private string $webhook_url;

	/**
	 * The process hook name.
	 *
	 * @var string
	 */
	private string $process_hook;

	/**
	 * The action scheduler group.
	 *
	 * @var string
	 */
	private string $group;

	/**
	 * Constructor.
	 *
	 * @param string $webhook_url The webhook URL.
	 * @param string $process_hook The process hook name.
	 * @param string $group The action scheduler group.
	 */
	public function __construct( string $webhook_url, string $process_hook = 'wpwf_send_webhook', string $group = 'wpwf' ) {
		$this->webhook_url  = $webhook_url;
		$this->process_hook = $process_hook;
		$this->group        = $group;
	}

	/**
	 * Whether dispatcher is operational (URL present).
	 *
	 * @return bool True if enabled, false otherwise.
	 */
	public function is_enabled(): bool {
		return '' !== $this->webhook_url;
	}

	/**
	 * Schedule a webhook if not already pending with same (action, entity, id).
	 *
	 * @param array<string,mixed> $payload
	 */
	/**
	 * Schedule a webhook if not already pending with same (action, entity, id).
	 *
	 * @param string              $action The action type.
	 * @param string              $entity The entity type.
	 * @param int|string          $id The entity ID.
	 * @param array<string,mixed> $payload The payload data.
	 */
	public function schedule( string $action, string $entity, int|string $id, array $payload = array() ): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		if ( ! function_exists( 'as_schedule_single_action' ) || ! function_exists( 'as_get_scheduled_actions' ) ) {
			// AS not available; fail fast
			return;
		}

		$query = as_get_scheduled_actions(
			array(
				'per_page'              => 1,
				'hook'                  => $this->process_hook,
				'group'                 => $this->group,
				'status'                => ActionScheduler_Store::STATUS_PENDING,
				'args'                  => array(
					'action' => $action,
					'entity' => $entity,
					'id'     => $id,
				),
				'partial_args_matching' => 'json',
			)
		);

		if ( ! empty( $query ) ) {
			return;
		}

		as_schedule_single_action(
			time() + 5,
			$this->process_hook,
			array(
				'action'  => $action,
				'entity'  => $entity,
				'id'      => $id,
				'payload' => $payload,
			),
			$this->group
		);
	}

	/**
	 * Action Scheduler callback. Sends the POST request non-blocking.
	 *
	 * @param string              $action The action type.
	 * @param string              $entity The entity type.
	 * @param int|string          $id The entity ID.
	 * @param array<string,mixed> $payload The payload data.
	 */
	public function process_scheduled_webhook( string $action, string $entity, $id, array $payload ): void {
		$body = array_merge(
			$payload,
			array(
				'action' => $action,
				'entity' => $entity,
				'id'     => $id,
			)
		);

		$args = array(
			'body'     => wp_json_encode( $body ),
			'headers'  => array( 'Content-Type' => 'application/json' ),
			'timeout'  => 10,
			'blocking' => false,
		);

		wp_remote_post( $this->webhook_url, $args );
	}
}
