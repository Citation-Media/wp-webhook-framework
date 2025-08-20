<?php

namespace CitationMedia\WpWebhookFramework\Support;

class Payload
{

	public static function post(int $post_id): array {
		return array( 'post_type' => get_post_type( $post_id) );
	}

	public static function term(int $term_id): array {
		$term = get_term( $term_id );
		return array( 'taxonomy' => $term->taxonomy );
	}

	public static function user(int $user_id): array {
		$user  = get_userdata( $user_id );
		$roles = ( $user && is_array( $user->roles ?? null ) ) ? array_values( $user->roles ) : array();

		return array( 'roles' => $roles );
	}

}