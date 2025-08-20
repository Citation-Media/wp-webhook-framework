<?php
/**
 * Utility class for building payload fragments for entities.
 *
 * @package CitationMedia\WpWebhookFramework\Support
 */

declare(strict_types=1);

namespace CitationMedia\WpWebhookFramework\Support;

/**
 * Builds payload fragments for entities. Always includes the requested identifiers.
 */
final class Payload {

	/**
	 * Post payload includes post_type.
	 *
	 * @param string $post_type The post type.
	 * @return array{post_type:string}
	 */
	public static function for_post( string $post_type ): array {
		return array( 'post_type' => $post_type );
	}

	/**
	 * Term payload includes taxonomy.
	 *
	 * @param string $taxonomy The taxonomy name.
	 * @return array{taxonomy:string}
	 */
	public static function for_term( string $taxonomy ): array {
		return array( 'taxonomy' => $taxonomy );
	}

	/**
	 * User payload includes roles array.
	 *
	 * @param int $user_id The user ID.
	 * @return array{roles:array<int,string>}
	 */
	public static function for_user( int $user_id ): array {
		$user  = get_userdata( $user_id );
		$roles = ( $user && is_array( $user->roles ?? null ) ) ? array_values( $user->roles ) : array();
		return array( 'roles' => $roles );
	}

	/**
	 * ACF context payload, if available.
	 *
	 * @param array<string,mixed> $field The ACF field array.
	 * @return array<string,string>
	 */
	public static function from_acf_field( array $field ): array {
		$out = array();
		if ( isset( $field['key'] ) && is_string( $field['key'] ) ) {
			$out['acf_field_key'] = $field['key'];
		}
		if ( isset( $field['name'] ) && is_string( $field['name'] ) ) {
			$out['acf_field_name'] = $field['name'];
		}
		return $out;
	}
}
