<?php
/**
 * Payload utility class for generating webhook payload data.
 *
 * @package CitationMedia\WpWebhookFramework\Support
 */

namespace CitationMedia\WpWebhookFramework\Support;

/**
 * Class Payload
 *
 * Provides static methods to generate standardized payload data for webhooks
 * based on WordPress entities (posts, terms, users).
 */
class Payload {

	/**
	 * Generate payload data for a post.
	 *
	 * @param int $post_id The post ID.
	 * @return array<string,mixed> The payload data containing post type.
	 */
	public static function post( int $post_id ): array {
		return array( 'post_type' => get_post_type( $post_id ) );
	}

	/**
	 * Generate payload data for a term.
	 *
	 * @param int $term_id The term ID.
	 * @return array<string,mixed> The payload data containing taxonomy.
	 */
	public static function term( int $term_id ): array {
		$term = get_term( $term_id );
		return array( 'taxonomy' => $term->taxonomy );
	}

	/**
	 * Generate payload data for a user.
	 *
	 * @param int $user_id The user ID.
	 * @return array<string,mixed> The payload data containing user roles.
	 */
	public static function user( int $user_id ): array {
		$user  = get_userdata( $user_id );
		$roles = ( $user && $user->roles ) ? array_values( $user->roles ) : array();

		return array( 'roles' => $roles );
	}
}
