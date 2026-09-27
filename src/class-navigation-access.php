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
		add_filter( 'rest_pre_insert_wp_navigation', array( __CLASS__, 'guard_rest_write' ), 10, 2 );
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

	/**
	 * Prevents the unrestricted core REST endpoint bypassing the scoped editor.
	 *
	 * Administrators retain REST access through the structural capability. Site
	 * Managers save through Navigation_Admin, which validates the supported model
	 * and preserves protected markup server-side.
	 *
	 * @param \stdClass       $prepared_post Prepared navigation post.
	 * @param \WP_REST_Request $request       REST request.
	 * @return \stdClass|\WP_Error
	 */
	public static function guard_rest_write( $prepared_post, $request ) {
		unset( $request );

		if ( current_user_can( 'twentythree_manage_navigation' ) && ! current_user_can( 'twentythree_manage_navigation_structure' ) ) {
			return new \WP_Error(
				'rest_cannot_update_navigation',
				__( 'Use the Navigation administration screen to edit this navigation.', 'twentythree-core' ),
				array( 'status' => 403 )
			);
		}

		return $prepared_post;
	}
}
