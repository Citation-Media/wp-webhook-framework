<?php
/**
 * ServiceProvider class for the WP Webhook Framework.
 *
 * @package CitationMedia\WpWebhookFramework
 */

namespace CitationMedia\WpWebhookFramework;

use CitationMedia\WpWebhookFramework\Entities\Post;
use CitationMedia\WpWebhookFramework\Entities\Term;
use CitationMedia\WpWebhookFramework\Entities\User;
use CitationMedia\WpWebhookFramework\Entities\Meta;
use CitationMedia\WpWebhookFramework\Support\AcfUtil;

/**
 * Class ServiceProvider
 *
 * Registers WordPress hooks and wires emitters to the dispatcher.
 */
class ServiceProvider {

	/**
	 * The webhook dispatcher instance.
	 *
	 * @var Dispatcher
	 */
	private Dispatcher $dispatcher;

	/**
	 * Configuration array for the service provider.
	 *
	 * @var array<string,mixed>
	 */
	private array $config;

	/**
	 * Constructor for ServiceProvider.
	 *
	 * @param array{webhook_url?:string|null,hook_group?:string,process_hook?:string} $config     Configuration array.
	 * @param Dispatcher|null                                                         $dispatcher Optional dispatcher instance.
	 */
	public function __construct( array $config = array(), ?Dispatcher $dispatcher = null ) {
		$defaults = array(
			'webhook_url'  => null,
			'hook_group'   => 'wpwf',
			'process_hook' => 'wpwf_send_webhook',
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

		$post_emitter = new Post( $this->dispatcher );
		$term_emitter = new Term( $this->dispatcher );
		$user_emitter = new User( $this->dispatcher );
		$meta_emitter = new Meta( $this->dispatcher, $post_emitter, $term_emitter, $user_emitter );

		add_action( 'save_post', array( $post_emitter, 'onSavePost' ), 10, 3 );
		add_action( 'before_delete_post', array( $post_emitter, 'onDeletePost' ), 10, 1 );

		add_action( 'created_term', array( $term_emitter, 'onCreatedTerm' ), 10, 3 );
		add_action( 'edited_term', array( $term_emitter, 'onEditedTerm' ), 10, 3 );
		add_action( 'delete_term', array( $term_emitter, 'onDeletedTerm' ), 10, 3 );

		add_action( 'user_register', array( $user_emitter, 'onUserRegister' ), 10, 1 );
		add_action( 'profile_update', array( $user_emitter, 'onProfileUpdate' ), 10, 1 );
		add_action( 'deleted_user', array( $user_emitter, 'onDeletedUser' ), 10, 1 );

		add_action( 'added_post_meta', array( $meta_emitter, 'onUpdatedPostMeta' ), 10, 4 );
		add_action( 'updated_post_meta', array( $meta_emitter, 'onUpdatedPostMeta' ), 10, 4 );
		add_action( 'deleted_post_meta', array( $meta_emitter, 'onDeletedPostMeta' ), 10, 4 );

		add_action( 'added_term_meta', array( $meta_emitter, 'onUpdatedTermMeta' ), 10, 4 );
		add_action( 'updated_term_meta', array( $meta_emitter, 'onUpdatedTermMeta' ), 10, 4 );
		add_action( 'deleted_term_meta', array( $meta_emitter, 'onDeletedTermMeta' ), 10, 4 );

		add_action( 'added_user_meta', array( $meta_emitter, 'onUpdatedUserMeta' ), 10, 4 );
		add_action( 'updated_user_meta', array( $meta_emitter, 'onUpdatedUserMeta' ), 10, 4 );
		add_action( 'deleted_user_meta', array( $meta_emitter, 'onDeletedUserMeta' ), 10, 4 );

		add_filter(
			'acf/update_value',
			function ( $value, $object_id, $field ) use ( $meta_emitter ) {
				[$entity, $id] = AcfUtil::parse_object_id( $object_id );

				if ( null === $entity || null === $id ) {
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
