<?php
/**
 * Runtime integration checks executed inside WordPress Playground.
 *
 * @package TwentyThreeCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	require_once '/wordpress/wp-load.php';
}

$twentythree_results  = array();
$twentythree_failures = array();

/**
 * Records one assertion.
 *
 * @param bool   $condition Assertion result.
 * @param string $label     Human-readable label.
 * @return void
 */
function twentythree_test_assert( $condition, $label ) {
	global $twentythree_results, $twentythree_failures;
	$twentythree_results[] = array(
		'test' => $label,
		'pass' => (bool) $condition,
	);
	if ( ! $condition ) {
		$twentythree_failures[] = $label;
	}
}

/**
 * Finds an editor item by its label or preservation state.
 *
 * @param array<int, array<string, mixed>> $items     Editor items.
 * @param string                           $label     Item label.
 * @param bool                             $preserved Whether to find a preserved item.
 * @return array<string, mixed>
 */
function twentythree_test_find_item( $items, $label = '', $preserved = false ) {
	foreach ( $items as $item ) {
		if ( $preserved && ! empty( $item['preserved'] ) ) {
			return $item;
		}
		if ( ! $preserved && empty( $item['preserved'] ) && isset( $item['label'] ) && $label === $item['label'] ) {
			return $item;
		}
	}
	return array();
}

twentythree_test_assert( defined( 'TWENTYTHREE_CORE_VERSION' ) && '0.2.0' === TWENTYTHREE_CORE_VERSION, 'Plugin loaded at version 0.2.0' );
twentythree_test_assert( 'twentythree-theme' === get_stylesheet(), '23 Theme activated' );
twentythree_test_assert( null !== get_role( 'twentythree_site_manager' ), '23 Site Manager role exists' );

$manager_id = username_exists( 'site-manager' );
if ( ! $manager_id ) {
	$manager_id = wp_create_user( 'site-manager', 'temporary-test-password', 'manager@example.test' );
}
$manager = new WP_User( $manager_id );
$manager->set_role( 'twentythree_site_manager' );

$admins   = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
$admin_id = $admins ? $admins[0]->ID : wp_create_user( 'qa-admin', 'temporary-test-password', 'admin@example.test' );
if ( ! $admins ) {
	( new WP_User( $admin_id ) )->set_role( 'administrator' );
}

wp_set_current_user( $manager_id );
foreach ( array( 'edit_posts', 'edit_pages', 'publish_posts', 'publish_pages', 'upload_files', 'manage_categories', 'twentythree_manage_sections', 'twentythree_manage_navigation' ) as $capability ) {
	twentythree_test_assert( current_user_can( $capability ), 'Site Manager allowed: ' . $capability );
}
foreach ( array( 'manage_options', 'edit_theme_options', 'install_plugins', 'activate_plugins', 'update_plugins', 'delete_plugins', 'edit_plugins', 'install_themes', 'switch_themes', 'update_themes', 'delete_themes', 'edit_themes', 'edit_users', 'create_users', 'delete_users', 'promote_users', 'unfiltered_html', 'twentythree_unlock_blocks', 'twentythree_manage_navigation_structure', 'twentythree_manage_governance' ) as $capability ) {
	twentythree_test_assert( ! current_user_can( $capability ), 'Site Manager denied: ' . $capability );
}

$page_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'About the studio',
		'post_content' => '<!-- wp:paragraph --><p>About content.</p><!-- /wp:paragraph -->',
	)
);
$post_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => 'Latest news',
		'post_content' => '<!-- wp:paragraph --><p>News content.</p><!-- /wp:paragraph -->',
	)
);
twentythree_test_assert( $page_id > 0 && $post_id > 0, 'Page and post fixtures created' );

$page_controller = new WP_REST_Posts_Controller( 'page' );
$page_request    = new WP_REST_Request( 'POST', '/wp/v2/pages' );
$page_request->set_param( 'title', 'Manager page' );
$page_request->set_param( 'status', 'publish' );
twentythree_test_assert( true === $page_controller->create_item_permissions_check( $page_request ), 'Site Manager may create pages through REST permissions' );

$context  = new WP_Block_Editor_Context( array( 'post' => get_post( $page_id ) ) );
$settings = apply_filters( 'block_editor_settings_all', array(), $context );
twentythree_test_assert( isset( $settings['canLockBlocks'] ) && false === $settings['canLockBlocks'], 'Site Manager block-lock controls disabled' );
twentythree_test_assert( isset( $settings['codeEditingEnabled'] ) && false === $settings['codeEditingEnabled'], 'Site Manager Code Editor disabled' );
$allowed = apply_filters( 'allowed_block_types_all', true, $context );
twentythree_test_assert( is_array( $allowed ) && in_array( 'core/paragraph', $allowed, true ) && ! in_array( 'core/html', $allowed, true ), 'Site Manager block allow-list applied' );

$registry = WP_Block_Patterns_Registry::get_instance();
foreach ( array( 'twentythree-theme/hero', 'twentythree-theme/text-and-image', 'twentythree-theme/call-to-action' ) as $pattern_name ) {
	twentythree_test_assert( $registry->is_registered( $pattern_name ), 'Theme pattern registered: ' . $pattern_name );
}

$initial_content = sprintf(
	'<!-- wp:navigation-link {"label":"Home","type":"page","kind":"post-type","id":%1$d,"url":"%2$s","description":"Preserve this description"} /-->
<!-- wp:page-list {"parentPageID":0} /-->
<!-- wp:html --><script data-preserve="true">window.twentythreePreserved = true;</script><!-- /wp:html -->
<!-- wp:navigation-submenu {"label":"Services","url":"/services/","kind":"custom","type":"custom"} -->
<!-- wp:navigation-link {"label":"Consulting","url":"/consulting/","kind":"custom","type":"custom"} /-->
<!-- /wp:navigation-submenu -->',
	$page_id,
	esc_url_raw( get_permalink( $page_id ) )
);

wp_set_current_user( $admin_id );
$navigation_id = wp_insert_post(
	array(
		'post_type'    => 'wp_navigation',
		'post_status'  => 'publish',
		'post_title'   => 'Primary navigation',
		'post_content' => $initial_content,
	)
);
twentythree_test_assert( $navigation_id > 0, 'Administrator created a navigation entity' );

$editor_items = TwentyThree\Core\Navigation_Content::editor_items( get_post_field( 'post_content', $navigation_id ) );
$home_item    = twentythree_test_find_item( $editor_items, 'Home' );
$service_item = twentythree_test_find_item( $editor_items, 'Services' );
$child_item   = twentythree_test_find_item( $editor_items, 'Consulting' );
$protected_items = array_values( array_filter( $editor_items, function ( $item ) { return ! empty( $item['preserved'] ); } ) );
$protected       = $protected_items[0];
twentythree_test_assert( 5 === count( $editor_items ) && 2 === count( $protected_items ), 'Supported and protected navigation branches parsed' );
twentythree_test_assert( ! empty( $protected['source_token'] ), 'Unsupported block represented by an opaque token' );

$valid_edit = array(
	array(
		'source_token' => $service_item['source_token'],
		'type'         => 'custom',
		'label'        => 'What we do',
		'url'          => '',
		'depth'        => 0,
	),
	array(
		'source_token' => $child_item['source_token'],
		'type'         => 'custom',
		'label'        => 'Advisory',
		'url'          => '/advisory/',
		'depth'        => 1,
	),
	array(
		'source_token' => '',
		'type'         => 'post',
		'label'        => 'News',
		'post_id'      => $post_id,
		'depth'        => 0,
	),
	array(
		'source_token' => $protected['source_token'],
		'preserved'    => 1,
		'depth'        => 0,
	),
	array(
		'source_token' => $protected_items[1]['source_token'],
		'preserved'    => 1,
		'depth'        => 0,
	),
	array(
		'source_token' => $home_item['source_token'],
		'type'         => 'page',
		'label'        => 'About',
		'page_id'      => $page_id,
		'new_tab'      => 1,
		'depth'        => 0,
	),
);

$built_content = TwentyThree\Core\Navigation_Content::build_content( get_post_field( 'post_content', $navigation_id ), $valid_edit );
twentythree_test_assert( ! is_wp_error( $built_content ), 'Valid navigation edit accepted' );
$built_blocks = is_wp_error( $built_content ) ? array() : parse_blocks( $built_content );
twentythree_test_assert( isset( $built_blocks[0] ) && 'core/navigation-submenu' === $built_blocks[0]['blockName'], 'Items reordered and nested to one controlled level' );
twentythree_test_assert( isset( $built_blocks[0]['attrs']['label'] ) && 'What we do' === $built_blocks[0]['attrs']['label'], 'Top-level label edited' );
twentythree_test_assert( isset( $built_blocks[0]['innerBlocks'][0]['attrs']['url'] ) && '/advisory/' === $built_blocks[0]['innerBlocks'][0]['attrs']['url'], 'Custom URL edited' );
twentythree_test_assert( isset( $built_blocks[1]['attrs']['id'] ) && $post_id === $built_blocks[1]['attrs']['id'], 'Published post link created' );
twentythree_test_assert( isset( $built_blocks[2] ) && 'core/page-list' === $built_blocks[2]['blockName'] && 0 === $built_blocks[2]['attrs']['parentPageID'], 'Unsupported block markup preserved' );
twentythree_test_assert( isset( $built_blocks[3] ) && 'core/html' === $built_blocks[3]['blockName'] && false !== strpos( serialize_block( $built_blocks[3] ), 'data-preserve="true"' ), 'Unsupported HTML branch preserved in generated content' );
twentythree_test_assert( isset( $built_blocks[4]['attrs']['description'] ) && 'Preserve this description' === $built_blocks[4]['attrs']['description'], 'Unmanaged attributes on supported links preserved' );
twentythree_test_assert( ! empty( $built_blocks[4]['attrs']['opensInNewTab'] ), 'New-tab setting saved' );

$missing_protected = array_values( array_filter( $valid_edit, function ( $item ) { return empty( $item['preserved'] ); } ) );
$result = TwentyThree\Core\Navigation_Content::build_content( get_post_field( 'post_content', $navigation_id ), $missing_protected );
twentythree_test_assert( is_wp_error( $result ) && 'missing_preserved_item' === $result->get_error_code(), 'Removal of unsupported markup rejected' );

$invalid_depth = $valid_edit;
$invalid_depth[0]['depth'] = 2;
$result = TwentyThree\Core\Navigation_Content::build_content( get_post_field( 'post_content', $navigation_id ), $invalid_depth );
twentythree_test_assert( is_wp_error( $result ) && 'invalid_depth' === $result->get_error_code(), 'Excessive nesting rejected' );

$invalid_source = $valid_edit;
$invalid_source[0]['source_token'] = 'e-forged';
$result = TwentyThree\Core\Navigation_Content::build_content( get_post_field( 'post_content', $navigation_id ), $invalid_source );
twentythree_test_assert( is_wp_error( $result ) && 'invalid_source_item' === $result->get_error_code(), 'Forged source token rejected' );

$invalid_url = $valid_edit;
$invalid_url[1]['url'] = 'javascript:alert(1)';
$result = TwentyThree\Core\Navigation_Content::build_content( get_post_field( 'post_content', $navigation_id ), $invalid_url );
twentythree_test_assert( is_wp_error( $result ) && 'invalid_url' === $result->get_error_code(), 'Unsafe URL rejected' );

$invalid_object = $valid_edit;
$invalid_object[2]['post_id'] = $page_id;
$result = TwentyThree\Core\Navigation_Content::build_content( get_post_field( 'post_content', $navigation_id ), $invalid_object );
twentythree_test_assert( is_wp_error( $result ) && 'invalid_object' === $result->get_error_code(), 'Mismatched linked content type rejected' );

wp_set_current_user( $manager_id );
$navigation_type = get_post_type_object( 'wp_navigation' );
twentythree_test_assert( current_user_can( 'edit_post', $navigation_id ), 'Site Manager may edit existing navigation content' );
twentythree_test_assert( ! current_user_can( 'delete_post', $navigation_id ), 'Site Manager may not delete navigation entities' );
twentythree_test_assert( ! current_user_can( $navigation_type->cap->create_posts ), 'Site Manager may not create navigation entities' );
$screen_update = TwentyThree\Core\Navigation_Admin::update_navigation( get_post( $navigation_id ), $valid_edit );
twentythree_test_assert( ! is_wp_error( $screen_update ), 'Restricted administration save completed' );
twentythree_test_assert( false !== strpos( get_post_field( 'post_content', $navigation_id ), '<script data-preserve="true">' ), 'Restricted administration save preserves trusted unsupported HTML' );

$navigation_controller = new WP_REST_Posts_Controller( 'wp_navigation' );
$update_request = new WP_REST_Request( 'PUT', '/wp/v2/navigation/' . $navigation_id );
$update_request->set_param( 'id', $navigation_id );
$update_request->set_param( 'content', $built_content );
twentythree_test_assert( true === $navigation_controller->update_item_permissions_check( $update_request ), 'Site Manager edit capability reaches scoped REST validation' );
$update_response = $navigation_controller->update_item( $update_request );
twentythree_test_assert( is_wp_error( $update_response ) && 'rest_cannot_update_navigation' === $update_response->get_error_code(), 'Unrestricted Site Manager navigation REST update rejected' );

$create_request = new WP_REST_Request( 'POST', '/wp/v2/navigation' );
$create_request->set_param( 'title', 'Forbidden navigation' );
twentythree_test_assert( is_wp_error( $navigation_controller->create_item_permissions_check( $create_request ) ), 'Site Manager navigation creation rejected by REST' );
$delete_request = new WP_REST_Request( 'DELETE', '/wp/v2/navigation/' . $navigation_id );
$delete_request->set_param( 'id', $navigation_id );
twentythree_test_assert( is_wp_error( $navigation_controller->delete_item_permissions_check( $delete_request ) ), 'Site Manager navigation deletion rejected by REST' );

wp_set_current_user( $admin_id );
twentythree_test_assert( current_user_can( 'twentythree_manage_navigation_structure' ) && current_user_can( 'twentythree_unlock_blocks' ), 'Administrator retains structural capabilities' );
twentythree_test_assert( true === $navigation_controller->create_item_permissions_check( $create_request ), 'Administrator navigation creation permitted by REST' );
twentythree_test_assert( true === $navigation_controller->delete_item_permissions_check( $delete_request ), 'Administrator navigation deletion permitted by REST' );
$admin_update_response = $navigation_controller->update_item( $update_request );
twentythree_test_assert( ! is_wp_error( $admin_update_response ), 'Administrator navigation update permitted by REST' );

$manager_role = get_role( 'twentythree_site_manager' );
$manager_role->add_cap( 'qa_external_capability' );
update_option( TwentyThree\Core\Role_Manager::VERSION_OPTION, '0.1.0' );
TwentyThree\Core\Role_Manager::maybe_synchronize();
twentythree_test_assert( get_role( 'twentythree_site_manager' )->has_cap( 'qa_external_capability' ), 'Upgrade synchronisation preserves third-party capabilities' );
TwentyThree\Core\Role_Manager::deactivate();
twentythree_test_assert( null !== get_role( 'twentythree_site_manager' ), 'Deactivation preserves role and assignments' );

$twentythree_report = array(
	'wordpress' => get_bloginfo( 'version' ),
	'php'       => PHP_VERSION,
	'total'     => count( $twentythree_results ),
	'passed'    => count( $twentythree_results ) - count( $twentythree_failures ),
	'failed'    => $twentythree_failures,
	'results'   => $twentythree_results,
);

$twentythree_json = wp_json_encode( $twentythree_report, JSON_PRETTY_PRINT );
echo "TWENTYTHREE_QA_REPORT\n" . $twentythree_json . "\n";

if ( defined( 'TWENTYTHREE_QA_OUTPUT' ) ) {
	file_put_contents( TWENTYTHREE_QA_OUTPUT, $twentythree_json . "\n" );
}

if ( $twentythree_failures ) {
	throw new RuntimeException( '23 Core QA failed: ' . implode( '; ', $twentythree_failures ) );
}
