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
		if ( self::post_type_supports_rest( $post_type ) ) {
			$payload['rest_url'] = self::get_post_rest_url( $post_id, $post_type );
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
		if ( self::taxonomy_supports_rest( $taxonomy ) ) {
			$payload['rest_url'] = self::get_term_rest_url( $term_id, $taxonomy );
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
		if ( self::users_support_rest() ) {
			$payload['rest_url'] = self::get_user_rest_url( $user_id );
		}

		return $payload;
	}

	/**
	 * Check if a post type supports REST API.
	 *
	 * @param string|false $post_type The post type to check.
	 * @return bool Whether the post type supports REST API.
	 */
	private static function post_type_supports_rest( $post_type ): bool {
		if ( false === $post_type ) {
			return false;
		}

		$post_type_object = get_post_type_object( $post_type );
		return $post_type_object && $post_type_object->show_in_rest;
	}

	/**
	 * Check if a taxonomy supports REST API.
	 *
	 * @param string $taxonomy The taxonomy to check.
	 * @return bool Whether the taxonomy supports REST API.
	 */
	private static function taxonomy_supports_rest( string $taxonomy ): bool {
		$taxonomy_object = get_taxonomy( $taxonomy );
		return $taxonomy_object && $taxonomy_object->show_in_rest;
	}

	/**
	 * Check if users endpoint supports REST API.
	 *
	 * @return bool Whether users support REST API.
	 */
	private static function users_support_rest(): bool {
		// Users REST API is enabled by default in WordPress but can be disabled
		// Check if the WP_REST_Users_Controller is available
		return class_exists( 'WP_REST_Users_Controller' );
	}

	/**
	 * Generate REST API URL for a post.
	 *
	 * @param int          $post_id   The post ID.
	 * @param string|false $post_type The post type.
	 * @return string The REST API URL for the post.
	 */
	private static function get_post_rest_url( int $post_id, $post_type ): string {
		if ( false === $post_type ) {
			return '';
		}

		$post_type_object = get_post_type_object( $post_type );
		if ( ! $post_type_object ) {
			return '';
		}

		if ( $post_type_object->rest_base ) {
			$rest_base = $post_type_object->rest_base;
		} else {
			// Fallback to default REST base if not explicitly set
			$rest_base = 'post' === $post_type ? 'posts' : $post_type;
		}

		return rest_url( "wp/v2/{$rest_base}/{$post_id}" );
	}

	/**
	 * Generate REST API URL for a term.
	 *
	 * @param int    $term_id  The term ID.
	 * @param string $taxonomy The taxonomy name.
	 * @return string The REST API URL for the term.
	 */
	private static function get_term_rest_url( int $term_id, string $taxonomy ): string {
		$taxonomy_object = get_taxonomy( $taxonomy );
		if ( ! $taxonomy_object ) {
			return '';
		}

		if ( $taxonomy_object->rest_base ) {
			$rest_base = $taxonomy_object->rest_base;
		} else {
			// Fallback to taxonomy name if rest_base not set
			$rest_base = $taxonomy;
		}

		return rest_url( "wp/v2/{$rest_base}/{$term_id}" );
	}

	/**
	 * Generate REST API URL for a user.
	 *
	 * @param int $user_id The user ID.
	 * @return string The REST API URL for the user.
	 */
	private static function get_user_rest_url( int $user_id ): string {
		return rest_url( "wp/v2/users/{$user_id}" );
	}
}
