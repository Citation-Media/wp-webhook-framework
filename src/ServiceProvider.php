<?php

namespace CitationMedia\WpWebhookFramework;

use CitationMedia\WpWebhookFramework\Entities\PostEmitter;
use CitationMedia\WpWebhookFramework\Entities\TermEmitter;
use CitationMedia\WpWebhookFramework\Entities\UserEmitter;
use CitationMedia\WpWebhookFramework\Entities\MetaEmitter;
use CitationMedia\WpWebhookFramework\Support\AcfUtil;

/**
 * Registers WordPress hooks and wires emitters to the dispatcher.
 */
class ServiceProvider {

	private Dispatcher $dispatcher;

	/** @var array<string,mixed> */
	private array $config;

	/**
	 * @param array{webhook_url?:string|null,hook_group?:string,process_hook?:string,allowed_post_types?:string[]} $config
	 * @param Dispatcher|null                                                                                      $dispatcher
	 */
	public function __construct( array $config = array(), ?Dispatcher $dispatcher = null ) {
		$defaults = array(
			'webhook_url'        => null,
			'hook_group'         => 'wpwf',
			'process_hook'       => 'wpwf_send_webhook',
			'allowed_post_types' => array(),
		);

		$this->config = array_merge( $defaults, $config );

		$url              = (string) ( $this->config['webhook_url'] ?? '' );
		$this->dispatcher = $dispatcher ?: new Dispatcher(
			$url,
			(string) $this->config['process_hook'],
			(string) $this->config['hook_group']
		);
	}

	/**
	 * Registers all actions/filters. Safe to call multiple times.
	 */
	public function register(): void {
		if ( ! $this->dispatcher->is_enabled() ) {
			return;
		}

		add_action(
			$this->config['process_hook'],
			array( $this->dispatcher, 'process_scheduled_webhook' ),
			10,
			4
		);

		$post_emitter = new PostEmitter( $this->dispatcher, (array) $this->config['allowed_post_types'] );
		$term_emitter = new TermEmitter( $this->dispatcher );
		$user_emitter = new UserEmitter( $this->dispatcher );
		$meta_emitter = new MetaEmitter( $this->dispatcher, $post_emitter, $term_emitter, $user_emitter );

		add_action( 'save_post', array( $post_emitter, 'onSavePost' ), 10, 3 );
		add_action( 'before_delete_post', array( $post_emitter, 'onDeletePost' ), 10, 1 );

		add_action( 'created_term', array( $term_emitter, 'onCreatedTerm' ), 10, 3 );
		add_action( 'edited_term', array( $term_emitter, 'onEditedTerm' ), 10, 3 );
		add_action( 'delete_term', array( $term_emitter, 'onDeletedTerm' ), 10, 3 );

		add_action( 'user_register', array( $user_emitter, 'onUserRegister' ), 10, 1 );
		add_action( 'profile_update', array( $user_emitter, 'onProfileUpdate' ), 10, 1 );
		add_action( 'deleted_user', array( $user_emitter, 'onDeletedUser' ), 10, 1 );

		add_action( 'added_post_meta', array( $meta_emitter, 'onAddedPostMeta' ), 10, 4 );
		add_action( 'updated_post_meta', array( $meta_emitter, 'onUpdatedPostMeta' ), 10, 4 );
		add_action( 'deleted_post_meta', array( $meta_emitter, 'onDeletedPostMeta' ), 10, 4 );

		add_action( 'added_term_meta', array( $meta_emitter, 'onAddedTermMeta' ), 10, 4 );
		add_action( 'updated_term_meta', array( $meta_emitter, 'onUpdatedTermMeta' ), 10, 4 );
		add_action( 'deleted_term_meta', array( $meta_emitter, 'onDeletedTermMeta' ), 10, 4 );

		add_action( 'added_user_meta', array( $meta_emitter, 'onAddedUserMeta' ), 10, 4 );
		add_action( 'updated_user_meta', array( $meta_emitter, 'onUpdatedUserMeta' ), 10, 4 );
		add_action( 'deleted_user_meta', array( $meta_emitter, 'onDeletedUserMeta' ), 10, 4 );

		add_filter(
			'acf/update_value',
			function ( $value, $object_id, $field ) use ( $meta_emitter ) {
				[$entity, $id] = AcfUtil::parse_object_id( $object_id );

				if ( $entity === null || $id === null ) {
					return $value;
				}

				// Route all ACF updates through MetaEmitter which will emit meta-level webhooks
				// when appropriate and also schedule the upstream entity-level update.
				$meta_emitter->onAcfUpdate( $entity, (int) $id, is_array( $field ) ? $field : array() );

				return $value;
			},
			10,
			3
		);
	}
}
