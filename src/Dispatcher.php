<?php
/**
 * Webhook dispatcher class.
 *
 * @package Citation\WP_Webhook_Framework
 */

declare(strict_types=1);

namespace Citation\WP_Webhook_Framework;

use ActionScheduler_Store;

/**
 * Queues and sends webhooks. AS-only. Dedupe on action+entity+id.
 */
class Dispatcher {


	/**
	 * Schedule a webhook if not already pending with same (action, entity, id).
	 *
	 * @param string              $url     The webhook URL.
	 * @param string              $action The action type.
	 * @param string              $entity The entity type.
	 * @param int|string          $id The entity ID.
	 * @param array<string,mixed> $payload The payload data.
	 */
	public function schedule( string $url, string $action, string $entity, int|string $id, array $payload = array() ): void {

		if ( ! function_exists( 'as_schedule_single_action' ) || ! function_exists( 'as_get_scheduled_actions' ) ) {
			return;
		}

		$query = as_get_scheduled_actions(
			array(
				'per_page'              => 1,
				'hook'                  => 'wpwf_send_webhook',
				'group'                 => 'wpwf',
				'status'                => ActionScheduler_Store::STATUS_PENDING,
				'args'                  => array(
					'url'   => $url,
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
			'wpwf_send_webhook',
			array(
				'url' => $url,
				'action'  => $action,
				'entity'  => $entity,
				'id'      => $id,
				'payload' => $payload,
			),
			'wpwf'
		);
	}

	/**
	 * Action Scheduler callback. Sends the POST request non-blocking.
	 *
	 * @param string              $url The webhook URL.
	 * @param string              $action The action type.
	 * @param string              $entity The entity type.
	 * @param int|string          $id The entity ID.
	 * @param array<string,mixed> $payload The payload data.
	 */
	public function process_scheduled_webhook( string $url, string $action, string $entity, $id, array $payload ): void {

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

		wp_remote_post( $url, $args );
	}
}
