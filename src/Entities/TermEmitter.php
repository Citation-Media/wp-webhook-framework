<?php

namespace CitationMedia\WpWebhookFramework\Entities;
use CitationMedia\WpWebhookFramework\Dispatcher;
use CitationMedia\WpWebhookFramework\Support\Payload;

/**
 * Emits webhooks for term lifecycle and meta changes.
 */
class TermEmitter {

	private Dispatcher $dispatcher;

	public function __construct( Dispatcher $dispatcher ) {
		$this->dispatcher = $dispatcher;
	}

	public function onCreatedTerm( int $term_id, int $tt_id, string $taxonomy ): void {
		$this->dispatcher->schedule( 'create', 'term', $term_id, Payload::for_term( $taxonomy ) );
	}

	public function onEditedTerm( int $term_id, int $tt_id, string $taxonomy ): void {
		$this->dispatcher->schedule( 'update', 'term', $term_id, Payload::for_term( $taxonomy ) );
	}

	public function onDeletedTerm( int $term_id, int $tt_id, string $taxonomy ): void {
		$this->dispatcher->schedule( 'delete', 'term', $term_id, Payload::for_term( $taxonomy ) );
	}

	/**
	 * Handle ACF update routed to a term.
	 *
	 * @param array<string,mixed> $field
	 */
	public function onAcfUpdate( int $term_id, array $field ): void {
		$taxonomy = $this->getTaxonomy( $term_id );
		if ( $taxonomy === null ) {
			return;
		}

		$payload = array_merge(
			Payload::for_term( $taxonomy ),
			Payload::from_acf_field( $field )
		);

		$this->dispatcher->schedule( 'update', 'term', $term_id, $payload );
	}

	private function emitUpdateForTerm( int $term_id ): void {
		$taxonomy = $this->getTaxonomy( $term_id );
		if ( $taxonomy === null ) {
			return;
		}

		$this->dispatcher->schedule( 'update', 'term', (int) $term_id, Payload::for_term( $taxonomy ) );
	}

	private function getTaxonomy( int $term_id ): ?string {
		$term = get_term( $term_id );
		if ( ! $term || ! isset( $term->taxonomy ) ) {
			return null;
		}
		return (string) $term->taxonomy;
	}
}
