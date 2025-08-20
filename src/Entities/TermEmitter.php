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
		$this->emit( $term_id, 'create' );
	}

	public function onEditedTerm( int $term_id, int $tt_id, string $taxonomy ): void {
		$this->emit( $term_id, 'update' );
	}

	public function onDeletedTerm( int $term_id, int $tt_id, string $taxonomy ): void {
		$this->emit( $term_id, 'delete' );
	}

	public function emit(int $term_id, string $action): void {
		$this->schedule( $action, 'term', $term_id, Payload::term($term_id) );
	}
}
