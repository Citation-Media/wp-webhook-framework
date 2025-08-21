<?php
/**
 * ServiceProvider class for the WP Webhook Framework.
 *
 * @package Citation\WP_Webhook_Framework
 */

namespace Citation\WP_Webhook_Framework;

use Citation\WP_Webhook_Framework\Entities\Post;
use Citation\WP_Webhook_Framework\Entities\Term;
use Citation\WP_Webhook_Framework\Entities\User;
use Citation\WP_Webhook_Framework\Entities\Meta;
use Citation\WP_Webhook_Framework\Support\AcfUtil;

/**
 * Class ServiceProvider
 *
 * Registers WordPress hooks and wires emitters to the dispatcher.
 */
class ServiceProvider {

	/**
	 * Singleton instance.
	 *
	 * @var ServiceProvider|null
	 */
	private static ?ServiceProvider $instance = null;

	/**
	 * The webhook dispatcher instance.
	 *
	 * @var Dispatcher
	 */
	private Dispatcher $dispatcher;

	/**
	 * Private constructor to prevent direct instantiation.
	 *
	 * @param Dispatcher|null $dispatcher Optional dispatcher instance.
	 */
	private function __construct( ?Dispatcher $dispatcher = null ) {
		$this->dispatcher = $dispatcher ?: new Dispatcher();
	}

	/**
	 * Get singleton instance.
	 *
	 * @param Dispatcher|null $dispatcher Optional dispatcher instance.
	 * @return ServiceProvider
	 */
	private static function get_instance( ?Dispatcher $dispatcher = null ): ServiceProvider {
		if ( null === self::$instance ) {
			self::$instance = new self( $dispatcher );
		}
		return self::$instance;
	}

	/**
	 * Registers all actions/filters. Safe to call multiple times.
	 */
	public static function register(): void {

		$instance = self::get_instance();

		add_action(
			'wpwf_send_webhook',
			array( $instance->dispatcher, 'process_scheduled_webhook' ),
			10,
			5
		);

		$post_emitter = new Post( $instance->dispatcher );
		$term_emitter = new Term( $instance->dispatcher );
		$user_emitter = new User( $instance->dispatcher );
		$meta_emitter = new Meta( $instance->dispatcher, $post_emitter, $term_emitter, $user_emitter );

		add_action( 'save_post', array( $post_emitter, 'onSavePost' ), 10, 3 );
		add_action( 'before_delete_post', array( $post_emitter, 'onDeletePost' ), 10, 1 );

		add_action( 'created_term', array( $term_emitter, 'onCreatedTerm' ), 10, 3 );
		add_action( 'edited_term', array( $term_emitter, 'onEditedTerm' ), 10, 3 );
		add_action( 'delete_term', array( $term_emitter, 'onDeletedTerm' ), 10, 3 );

		add_action( 'user_register', array( $user_emitter, 'onUserRegister' ), 10, 1 );
		add_action( 'profile_update', array( $user_emitter, 'onProfileUpdate' ), 10, 1 );
		add_action( 'deleted_user', array( $user_emitter, 'onDeletedUser' ), 10, 1 );

		add_action( 'deleted_post_meta', array( $meta_emitter, 'onDeletedPostMeta' ), 10, 4 );
		add_action( 'deleted_term_meta', array( $meta_emitter, 'onDeletedTermMeta' ), 10, 4 );
		add_action( 'deleted_user_meta', array( $meta_emitter, 'onDeletedUserMeta' ), 10, 4 );

		add_filter(
			'acf/update_value',
			function ( $value, $object_id, $field, $original ) use ( $meta_emitter ) {
				[$entity, $id] = AcfUtil::parse_object_id( $object_id );

				if ( null === $entity || null === $id ) {
					return $value;
				}

				$meta_emitter->acf_update_handler( $entity, (int) $id, is_array( $field ) ? $field : array(), $value, $original );
				return $value;
			},
			10,
			4
		);

		// Add filters for all meta types with high priority to run late
		add_filter( 'update_post_metadata', array( $meta_emitter, 'onUpdatedPostMeta' ), 999, 5 );
		add_filter( 'update_term_metadata', array( $meta_emitter, 'onUpdatedTermMeta' ), 999, 5 );
		add_filter( 'update_user_metadata', array( $meta_emitter, 'onUpdatedUserMeta' ), 999, 5 );
	}
}
