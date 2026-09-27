<?php
/**
 * Plugin bootstrap.
 *
 * @package TwentyThreeCore
 */

namespace TwentyThree\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates the plugin components.
 */
final class Plugin {

	/**
	 * Registers the plugin.
	 *
	 * @return void
	 */
	public static function boot() {
		Role_Manager::maybe_synchronize();
		Editor_Governance::register();
		Navigation_Access::register();
	}
}
