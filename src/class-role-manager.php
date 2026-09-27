<?php
/**
 * Role and capability lifecycle.
 *
 * @package TwentyThreeCore
 */

namespace TwentyThree\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and safely synchronises the roles managed by 23 Core.
 */
final class Role_Manager {

	const ROLE = 'twentythree_site_manager';
	const VERSION_OPTION = 'twentythree_core_version';
	const ROLE_CAPS_OPTION = 'twentythree_core_managed_role_caps';
	const ADMIN_CAPS_OPTION = 'twentythree_core_managed_admin_caps';

	/**
	 * Runs when the plugin is activated.
	 *
	 * @param bool $network_wide Whether this is a network activation.
	 * @return void
	 */
	public static function activate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::synchronize();
				restore_current_blog();
			}

			return;
		}

		self::synchronize();
	}

	/**
	 * Ordinary deactivation intentionally preserves roles and user assignments.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Intentionally empty. See README.md for the recovery policy.
	}

	/**
	 * Synchronises capabilities after a plugin version change.
	 *
	 * @return void
	 */
	public static function maybe_synchronize() {
		if ( TWENTYTHREE_CORE_VERSION !== get_option( self::VERSION_OPTION ) ) {
			self::synchronize();
		}
	}

	/**
	 * Creates or updates the Site Manager role and Administrator capabilities.
	 *
	 * Only capabilities previously managed by this plugin are removed. This
	 * preserves capabilities deliberately added by another integration.
	 *
	 * @return void
	 */
	public static function synchronize() {
		$role = get_role( self::ROLE );

		if ( ! $role ) {
			add_role( self::ROLE, __( '23 Site Manager', 'twentythree-core' ), array( 'read' => true ) );
			$role = get_role( self::ROLE );
		}

		if ( $role ) {
			self::synchronize_role_caps(
				$role,
				self::site_manager_capabilities(),
				self::ROLE_CAPS_OPTION
			);
		}

		$administrator = get_role( 'administrator' );

		if ( $administrator ) {
			self::synchronize_role_caps(
				$administrator,
				self::administrator_capabilities(),
				self::ADMIN_CAPS_OPTION
			);
		}

		update_option( self::VERSION_OPTION, TWENTYTHREE_CORE_VERSION, false );
	}

	/**
	 * Returns Site Manager capabilities.
	 *
	 * @return array<string, bool>
	 */
	public static function site_manager_capabilities() {
		$capabilities = array(
			'read',
			'edit_posts',
			'edit_others_posts',
			'edit_published_posts',
			'edit_private_posts',
			'publish_posts',
			'delete_posts',
			'delete_others_posts',
			'delete_published_posts',
			'delete_private_posts',
			'read_private_posts',
			'edit_pages',
			'edit_others_pages',
			'edit_published_pages',
			'edit_private_pages',
			'publish_pages',
			'delete_pages',
			'delete_others_pages',
			'delete_published_pages',
			'delete_private_pages',
			'read_private_pages',
			'upload_files',
			'manage_categories',
			'twentythree_manage_sections',
			'twentythree_manage_navigation',
		);

		return array_fill_keys( $capabilities, true );
	}

	/**
	 * Returns custom capabilities reserved for administrators.
	 *
	 * @return array<string, bool>
	 */
	public static function administrator_capabilities() {
		$capabilities = array(
			'twentythree_manage_sections',
			'twentythree_manage_navigation',
			'twentythree_manage_navigation_structure',
			'twentythree_unlock_blocks',
			'twentythree_manage_governance',
		);

		return array_fill_keys( $capabilities, true );
	}

	/**
	 * Synchronises the plugin-owned portion of one role.
	 *
	 * @param \WP_Role            $role        Role object.
	 * @param array<string, bool> $desired     Desired plugin-managed capabilities.
	 * @param string              $option_name Option used to remember managed caps.
	 * @return void
	 */
	private static function synchronize_role_caps( $role, $desired, $option_name ) {
		$previous = get_option( $option_name, array() );

		if ( ! is_array( $previous ) ) {
			$previous = array();
		}

		foreach ( array_diff( $previous, array_keys( $desired ) ) as $capability ) {
			if ( is_string( $capability ) ) {
				$role->remove_cap( $capability );
			}
		}

		foreach ( $desired as $capability => $grant ) {
			$role->add_cap( $capability, (bool) $grant );
		}

		update_option( $option_name, array_keys( $desired ), false );
	}
}
