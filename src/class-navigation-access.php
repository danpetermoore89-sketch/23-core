<?php
/**
 * Navigation capability isolation.
 *
 * @package TwentyThreeCore
 */

namespace TwentyThree\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Separates navigation content permissions from broad theme administration.
 */
final class Navigation_Access {

	/**
	 * Registers the post-type capability filter before core's init callback.
	 *
	 * @return void
	 */
	public static function register() {
		add_filter( 'register_post_type_args', array( __CLASS__, 'filter_post_type_args' ), 20, 2 );
	}

	/**
	 * Maps existing navigation content to a narrow capability.
	 *
	 * Creation and deletion remain structural operations reserved for users with
	 * twentythree_manage_navigation_structure.
	 *
	 * @param array<string, mixed> $args      Post-type arguments.
	 * @param string               $post_type Post-type name.
	 * @return array<string, mixed>
	 */
	public static function filter_post_type_args( $args, $post_type ) {
		if ( 'wp_navigation' !== $post_type ) {
			return $args;
		}

		$args['map_meta_cap'] = false;
		$args['capabilities'] = array_merge(
			isset( $args['capabilities'] ) && is_array( $args['capabilities'] ) ? $args['capabilities'] : array(),
			array(
				'edit_post'              => 'twentythree_manage_navigation',
				'read_post'              => 'twentythree_manage_navigation',
				'delete_post'            => 'twentythree_manage_navigation_structure',
				'edit_posts'             => 'twentythree_manage_navigation',
				'edit_others_posts'      => 'twentythree_manage_navigation',
				'delete_posts'           => 'twentythree_manage_navigation_structure',
				'publish_posts'          => 'twentythree_manage_navigation',
				'read_private_posts'     => 'twentythree_manage_navigation',
				'delete_private_posts'   => 'twentythree_manage_navigation_structure',
				'delete_published_posts' => 'twentythree_manage_navigation_structure',
				'delete_others_posts'    => 'twentythree_manage_navigation_structure',
				'edit_private_posts'     => 'twentythree_manage_navigation',
				'edit_published_posts'   => 'twentythree_manage_navigation',
				'create_posts'           => 'twentythree_manage_navigation_structure',
			),
		);

		return $args;
	}
}
