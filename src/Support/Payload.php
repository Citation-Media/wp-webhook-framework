<?php
/**
 * Payload utility class for generating webhook payload data.
 *
 * @package Citation\WP_Webhook_Framework\Support
 */

namespace Citation\WP_Webhook_Framework\Support;

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
	 * @return array<string,mixed> The payload data containing post type and REST URL if supported.
	 */
	public static function post( int $post_id ): array {
		$post_type = get_post_type( $post_id );
		$payload   = array( 'post_type' => $post_type );

		// Add REST API URL if post type has REST support enabled
		if ( false !== $post_type ) {
			$post_type_object = get_post_type_object( $post_type );
			if ( $post_type_object && $post_type_object->show_in_rest && $post_type_object->rest_base ) {
				$rest_namespace = $post_type_object->rest_namespace ?: 'wp/v2';
				$payload['rest_url'] = rest_url( "{$rest_namespace}/{$post_type_object->rest_base}/{$post_id}" );
			}
		}

		return $payload;
	}

	/**
	 * Generate payload data for a term.
	 *
	 * @param int $term_id The term ID.
	 * @return array<string,mixed> The payload data containing taxonomy and REST URL if supported.
	 */
	public static function term( int $term_id ): array {
		$term = get_term( $term_id );
		
		// Handle case where term doesn't exist or is an error
		if ( is_wp_error( $term ) || ! $term ) {
			return array( 'taxonomy' => '' );
		}

		$taxonomy = $term->taxonomy;
		$payload  = array( 'taxonomy' => $taxonomy );

		// Add REST API URL if taxonomy has REST support enabled
		$taxonomy_object = get_taxonomy( $taxonomy );
		if ( $taxonomy_object && $taxonomy_object->show_in_rest && $taxonomy_object->rest_base ) {
			$payload['rest_url'] = rest_url( "wp/v2/{$taxonomy_object->rest_base}/{$term_id}" );
		}

		return $payload;
	}

	/**
	 * Generate payload data for a user.
	 *
	 * @param int $user_id The user ID.
	 * @return array<string,mixed> The payload data containing user roles and REST URL if supported.
	 */
	public static function user( int $user_id ): array {
		$user    = get_userdata( $user_id );
		$roles   = ( $user && $user->roles ) ? array_values( $user->roles ) : array();
		$payload = array( 'roles' => $roles );

		// Add REST API URL if users endpoint has REST support enabled
		if ( class_exists( 'WP_REST_Users_Controller' ) ) {
			$payload['rest_url'] = rest_url( "wp/v2/users/{$user_id}" );
		}

		return $payload;
	}

}
