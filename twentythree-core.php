<?php
/**
 * Plugin Name:       23 Core
 * Description:       Shared permissions and editorial governance for websites managed by 23 Web.
 * Version:           0.2.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            23 Web
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       twentythree-core
 */

namespace TwentyThree\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TWENTYTHREE_CORE_VERSION', '0.2.0' );
define( 'TWENTYTHREE_CORE_FILE', __FILE__ );

require_once __DIR__ . '/src/class-role-manager.php';
require_once __DIR__ . '/src/class-editor-governance.php';
require_once __DIR__ . '/src/class-navigation-access.php';
require_once __DIR__ . '/src/class-navigation-content.php';
require_once __DIR__ . '/src/class-navigation-admin.php';
require_once __DIR__ . '/src/class-plugin.php';

register_activation_hook( __FILE__, array( Role_Manager::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Role_Manager::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( Plugin::class, 'boot' ) );
