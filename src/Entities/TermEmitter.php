<?php

namespace CitationMedia\WpWebhookFramework\Entities;
use CitationMedia\WpWebhookFramework\Dispatcher;
use CitationMedia\WpWebhookFramework\Support\Payload;

/**
 * Emits webhooks for term lifecycle and meta changes.
 */
class TermEmitter extends AbstractEmitter {

	public function __construct( Dispatcher $dispatcher ) {
		parent::__construct( $dispatcher );
	}

	public function onCreatedTerm( int $term_id, int $tt_id, string $taxonomy ): void {
		$this->scheduleWebhook( 'create', 'term', $term_id, Payload::for_term( $taxonomy ) );
	}

	public function onEditedTerm( int $term_id, int $tt_id, string $taxonomy ): void {
		$this->scheduleWebhook( 'update', 'term', $term_id, Payload::for_term( $taxonomy ) );
	}

	public function onDeletedTerm( int $term_id, int $tt_id, string $taxonomy ): void {
		$this->scheduleWebhook( 'delete', 'term', $term_id, Payload::for_term( $taxonomy ) );
	}

	private function emitUpdateForTerm( int $term_id ): void {
		$taxonomy = $this->getTaxonomy( $term_id );
		if ( $taxonomy === null ) {
			return;
		}

		$this->scheduleWebhook( 'update', 'term', (int) $term_id, Payload::for_term( $taxonomy ) );
	}

	private function getTaxonomy( int $term_id ): ?string {
		$term = get_term( $term_id );
		if ( ! $term || ! isset( $term->taxonomy ) ) {
			return null;
		}
		return (string) $term->taxonomy;
	}
}
