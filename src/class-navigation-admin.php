<?php
/**
 * Restricted navigation administration screen.
 *
 * @package TwentyThreeCore
 */

namespace TwentyThree\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides an intentionally narrow editor for existing navigation entities.
 */
final class Navigation_Admin {

	const PAGE_SLUG = 'twentythree-navigation';

	/** @var string */
	private static $screen_hook = '';

	/**
	 * Registers admin hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_twentythree_save_navigation', array( __CLASS__, 'handle_save' ) );
	}

	/**
	 * Registers the normal WordPress administration menu item.
	 *
	 * @return void
	 */
	public static function register_menu() {
		self::$screen_hook = add_menu_page(
			__( 'Navigation', 'twentythree-core' ),
			__( 'Navigation', 'twentythree-core' ),
			'twentythree_manage_navigation',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_screen' ),
			'dashicons-menu',
			59
		);
	}

	/**
	 * Loads assets only on the navigation screen.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		if ( self::$screen_hook !== $hook ) {
			return;
		}

		$base_url = plugin_dir_url( TWENTYTHREE_CORE_FILE );
		wp_enqueue_style( 'twentythree-navigation-admin', $base_url . 'assets/admin-navigation.css', array(), TWENTYTHREE_CORE_VERSION );
		wp_enqueue_script( 'twentythree-navigation-admin', $base_url . 'assets/admin-navigation.js', array(), TWENTYTHREE_CORE_VERSION, true );
		wp_localize_script(
			'twentythree-navigation-admin',
			'twentyThreeNavigation',
			array(
				'added'      => __( 'Navigation item added.', 'twentythree-core' ),
				'removed'    => __( 'Navigation item removed.', 'twentythree-core' ),
				'moved'      => __( 'Navigation item moved.', 'twentythree-core' ),
				'indented'   => __( 'Navigation item nested.', 'twentythree-core' ),
				'outdented'  => __( 'Navigation item moved to the top level.', 'twentythree-core' ),
			)
		);
	}

	/**
	 * Renders the navigation management screen.
	 *
	 * @return void
	 */
	public static function render_screen() {
		if ( ! current_user_can( 'twentythree_manage_navigation' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage navigation.', 'twentythree-core' ), '', array( 'response' => 403 ) );
		}

		$navigations = get_posts(
			array(
				'post_type'      => 'wp_navigation',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => array( 'title' => 'ASC', 'ID' => 'ASC' ),
				'no_found_rows'  => true,
			)
		);

		$requested_id = isset( $_GET['navigation'] ) ? absint( wp_unslash( $_GET['navigation'] ) ) : 0;
		$navigation   = $navigations ? self::select_navigation( $navigations, $requested_id ) : null;
		$invalid_id   = $navigation && $requested_id && $requested_id !== (int) $navigation->ID;
		?>
		<div class="wrap twentythree-navigation-wrap">
			<h1><?php esc_html_e( 'Navigation', 'twentythree-core' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Edit the links inside an existing navigation. Templates, global styles and theme settings are not available here.', 'twentythree-core' ); ?></p>
			<?php self::render_notice(); ?>
			<?php if ( $invalid_id ) : ?>
				<div class="notice notice-error inline"><p><?php esc_html_e( 'The requested navigation was not found. The first available navigation is shown instead.', 'twentythree-core' ); ?></p></div>
			<?php endif; ?>

			<?php if ( empty( $navigations ) ) : ?>
				<div class="notice notice-info inline"><p>
					<?php
					if ( current_user_can( 'twentythree_manage_navigation_structure' ) && current_user_can( 'edit_theme_options' ) ) {
						echo wp_kses_post( sprintf( __( 'No navigation entities exist yet. <a href="%s">Create one in the Site Editor</a>.', 'twentythree-core' ), esc_url( admin_url( 'site-editor.php?p=%2Fnavigation' ) ) ) );
					} else {
						esc_html_e( 'No navigation is available. Ask an Administrator to create one first.', 'twentythree-core' );
					}
					?>
				</p></div>
			<?php else : ?>
				<form method="get" class="twentythree-navigation-picker">
					<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
					<label for="twentythree-navigation-select"><?php esc_html_e( 'Navigation to edit', 'twentythree-core' ); ?></label>
					<select id="twentythree-navigation-select" name="navigation">
						<?php foreach ( $navigations as $candidate ) : ?>
							<option value="<?php echo esc_attr( $candidate->ID ); ?>" <?php selected( $navigation->ID, $candidate->ID ); ?>><?php echo esc_html( $candidate->post_title ? $candidate->post_title : sprintf( __( 'Navigation #%d', 'twentythree-core' ), $candidate->ID ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php submit_button( __( 'Choose', 'twentythree-core' ), 'secondary', '', false ); ?>
				</form>

				<?php self::render_editor( $navigation ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handles a navigation save request.
	 *
	 * @return void
	 */
	public static function handle_save() {
		if ( ! current_user_can( 'twentythree_manage_navigation' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage navigation.', 'twentythree-core' ), '', array( 'response' => 403 ) );
		}

		$navigation_id = isset( $_POST['navigation_id'] ) ? absint( $_POST['navigation_id'] ) : 0;
		check_admin_referer( 'twentythree_save_navigation_' . $navigation_id );

		$navigation = get_post( $navigation_id );
		if ( ! $navigation instanceof \WP_Post || 'wp_navigation' !== $navigation->post_type || ! current_user_can( 'edit_post', $navigation_id ) ) {
			wp_die( esc_html__( 'You are not allowed to edit this navigation.', 'twentythree-core' ), '', array( 'response' => 403 ) );
		}

		$submitted_revision = isset( $_POST['content_revision'] ) ? sanitize_text_field( wp_unslash( $_POST['content_revision'] ) ) : '';
		$current_revision   = self::content_revision( $navigation );
		if ( ! hash_equals( $current_revision, $submitted_revision ) ) {
			self::redirect_with_notice( $navigation_id, 'conflict' );
		}

		$items  = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array();
		$result = self::update_navigation( $navigation, $items );

		if ( is_wp_error( $result ) ) {
			self::redirect_with_notice( $navigation_id, $result->get_error_code() );
		}

		self::redirect_with_notice( $navigation_id, 'saved' );
	}

	/**
	 * Validates and updates one existing navigation entity.
	 *
	 * The normal content KSES filter is removed only around the final update.
	 * Editable values have already been sanitised and unsupported markup comes
	 * directly from the current database value, so this preserves trusted markup
	 * without accepting custom markup from the request.
	 *
	 * @param \WP_Post                        $navigation Navigation entity.
	 * @param array<int, array<string,mixed>> $items      Submitted editor rows.
	 * @return int|\WP_Error
	 */
	public static function update_navigation( $navigation, $items ) {
		if ( ! $navigation instanceof \WP_Post || 'wp_navigation' !== $navigation->post_type || ! current_user_can( 'twentythree_manage_navigation' ) || ! current_user_can( 'edit_post', $navigation->ID ) ) {
			return new \WP_Error( 'forbidden', __( 'You are not allowed to edit this navigation.', 'twentythree-core' ) );
		}

		$content = Navigation_Content::build_content( $navigation->post_content, $items );
		if ( is_wp_error( $content ) ) {
			return $content;
		}

		$kses_priority = has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		if ( false !== $kses_priority ) {
			remove_filter( 'content_save_pre', 'wp_filter_post_kses', $kses_priority );
		}

		try {
			$result = wp_update_post(
				array(
					'ID'           => $navigation->ID,
					'post_content' => $content,
				),
				true
			);
		} finally {
			if ( false !== $kses_priority ) {
				add_filter( 'content_save_pre', 'wp_filter_post_kses', $kses_priority );
			}
		}

		if ( is_wp_error( $result ) ) {
			return new \WP_Error( 'save_failed', __( 'WordPress could not save the navigation.', 'twentythree-core' ) );
		}

		return $result;
	}

	/**
	 * Renders the editable item form.
	 *
	 * @param \WP_Post $navigation Navigation entity.
	 * @return void
	 */
	private static function render_editor( $navigation ) {
		$items = Navigation_Content::editor_items( $navigation->post_content );
		$pages = self::content_choices( 'page' );
		$posts = self::content_choices( 'post' );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="twentythree-navigation-editor" data-navigation-editor>
			<input type="hidden" name="action" value="twentythree_save_navigation">
			<input type="hidden" name="navigation_id" value="<?php echo esc_attr( $navigation->ID ); ?>">
			<input type="hidden" name="content_revision" value="<?php echo esc_attr( self::content_revision( $navigation ) ); ?>">
			<?php wp_nonce_field( 'twentythree_save_navigation_' . $navigation->ID ); ?>

			<h2><?php echo esc_html( $navigation->post_title ? $navigation->post_title : sprintf( __( 'Navigation #%d', 'twentythree-core' ), $navigation->ID ) ); ?></h2>
			<p><?php esc_html_e( 'Items may be nested one level. Unsupported blocks are retained as protected items and cannot be edited or removed here.', 'twentythree-core' ); ?></p>
			<noscript><div class="notice notice-warning inline"><p><?php esc_html_e( 'JavaScript is required to add, remove, reorder or nest items. Existing fields can still be edited and saved.', 'twentythree-core' ); ?></p></div></noscript>
			<div class="twentythree-navigation-status screen-reader-text" aria-live="polite" data-navigation-status></div>
			<div class="twentythree-navigation-items" data-navigation-items>
				<?php foreach ( $items as $index => $item ) : ?>
					<?php self::render_item( $item, (string) $index, $pages, $posts ); ?>
				<?php endforeach; ?>
			</div>

			<p class="hide-if-no-js"><button type="button" class="button" data-add-navigation-item><?php esc_html_e( 'Add item', 'twentythree-core' ); ?></button></p>
			<?php submit_button( __( 'Save navigation', 'twentythree-core' ) ); ?>

			<template data-navigation-item-template>
				<?php
				self::render_item(
					array(
						'source_token' => '',
						'preserved'    => false,
						'depth'        => 0,
						'type'         => 'custom',
						'label'        => '',
						'url'          => '',
						'page_id'      => 0,
						'post_id'      => 0,
						'new_tab'      => false,
					),
					'__INDEX__',
					$pages,
					$posts
				);
				?>
			</template>
		</form>
		<?php
	}

	/**
	 * Renders one editable or preserved item.
	 *
	 * @param array<string, mixed> $item  Item data.
	 * @param string               $index Form index.
	 * @param \WP_Post[]           $pages Published pages.
	 * @param \WP_Post[]           $posts Published posts.
	 * @return void
	 */
	private static function render_item( $item, $index, $pages, $posts ) {
		$preserved = ! empty( $item['preserved'] );
		$depth     = isset( $item['depth'] ) ? absint( $item['depth'] ) : 0;
		$base_name = 'items[' . $index . ']';
		$base_id   = 'twentythree-navigation-item-' . $index;
		$item_name = $preserved ? $item['description'] : ( $item['label'] ? $item['label'] : __( 'new item', 'twentythree-core' ) );
		?>
		<section class="twentythree-navigation-item<?php echo $depth ? ' is-child' : ''; ?><?php echo $preserved ? ' is-preserved' : ''; ?>" data-navigation-item data-preserved="<?php echo $preserved ? '1' : '0'; ?>">
			<input type="hidden" name="<?php echo esc_attr( $base_name ); ?>[source_token]" value="<?php echo esc_attr( isset( $item['source_token'] ) ? $item['source_token'] : '' ); ?>" data-field="source_token">
			<input type="hidden" name="<?php echo esc_attr( $base_name ); ?>[depth]" value="<?php echo esc_attr( $depth ); ?>" data-field="depth">
			<?php if ( $preserved ) : ?>
				<input type="hidden" name="<?php echo esc_attr( $base_name ); ?>[preserved]" value="1" data-field="preserved">
				<div class="twentythree-navigation-item__preserved">
					<strong><?php esc_html_e( 'Protected item', 'twentythree-core' ); ?></strong>
					<span><?php echo esc_html( $item['description'] ); ?></span>
				</div>
			<?php else : ?>
				<div class="twentythree-navigation-item__fields">
					<p>
						<label for="<?php echo esc_attr( $base_id ); ?>-type"><?php esc_html_e( 'Item type', 'twentythree-core' ); ?></label>
						<select id="<?php echo esc_attr( $base_id ); ?>-type" name="<?php echo esc_attr( $base_name ); ?>[type]" data-field="type">
							<option value="custom" <?php selected( $item['type'], 'custom' ); ?>><?php esc_html_e( 'Custom URL or label', 'twentythree-core' ); ?></option>
							<option value="page" <?php selected( $item['type'], 'page' ); ?>><?php esc_html_e( 'Page', 'twentythree-core' ); ?></option>
							<option value="post" <?php selected( $item['type'], 'post' ); ?>><?php esc_html_e( 'Post', 'twentythree-core' ); ?></option>
						</select>
					</p>
					<p>
						<label for="<?php echo esc_attr( $base_id ); ?>-label"><?php esc_html_e( 'Label', 'twentythree-core' ); ?></label>
						<input id="<?php echo esc_attr( $base_id ); ?>-label" type="text" name="<?php echo esc_attr( $base_name ); ?>[label]" value="<?php echo esc_attr( $item['label'] ); ?>" maxlength="200" required data-field="label">
					</p>
					<p data-type-field="custom">
						<label for="<?php echo esc_attr( $base_id ); ?>-url"><?php esc_html_e( 'URL', 'twentythree-core' ); ?></label>
						<input id="<?php echo esc_attr( $base_id ); ?>-url" type="text" inputmode="url" name="<?php echo esc_attr( $base_name ); ?>[url]" value="<?php echo esc_attr( $item['url'] ); ?>" placeholder="/contact/" data-field="url">
						<span class="description"><?php esc_html_e( 'May be empty only when this item contains nested links.', 'twentythree-core' ); ?></span>
					</p>
					<p data-type-field="page">
						<label for="<?php echo esc_attr( $base_id ); ?>-page"><?php esc_html_e( 'Page', 'twentythree-core' ); ?></label>
						<select id="<?php echo esc_attr( $base_id ); ?>-page" name="<?php echo esc_attr( $base_name ); ?>[page_id]" data-field="page_id">
							<option value="0"><?php esc_html_e( 'Select a page', 'twentythree-core' ); ?></option>
							<?php self::render_content_options( $pages, isset( $item['page_id'] ) ? (int) $item['page_id'] : 0 ); ?>
						</select>
					</p>
					<p data-type-field="post">
						<label for="<?php echo esc_attr( $base_id ); ?>-post"><?php esc_html_e( 'Post', 'twentythree-core' ); ?></label>
						<select id="<?php echo esc_attr( $base_id ); ?>-post" name="<?php echo esc_attr( $base_name ); ?>[post_id]" data-field="post_id">
							<option value="0"><?php esc_html_e( 'Select a post', 'twentythree-core' ); ?></option>
							<?php self::render_content_options( $posts, isset( $item['post_id'] ) ? (int) $item['post_id'] : 0 ); ?>
						</select>
					</p>
					<p class="twentythree-navigation-item__checkbox">
						<label><input type="checkbox" name="<?php echo esc_attr( $base_name ); ?>[new_tab]" value="1" <?php checked( ! empty( $item['new_tab'] ) ); ?> data-field="new_tab"> <?php esc_html_e( 'Open in a new tab', 'twentythree-core' ); ?></label>
					</p>
				</div>
			<?php endif; ?>

			<div class="twentythree-navigation-item__actions hide-if-no-js" aria-label="<?php echo esc_attr( sprintf( __( 'Controls for %s', 'twentythree-core' ), $item_name ) ); ?>">
				<button type="button" class="button" data-move-up aria-label="<?php echo esc_attr( sprintf( __( 'Move %s up', 'twentythree-core' ), $item_name ) ); ?>"><?php esc_html_e( 'Move up', 'twentythree-core' ); ?></button>
				<button type="button" class="button" data-move-down aria-label="<?php echo esc_attr( sprintf( __( 'Move %s down', 'twentythree-core' ), $item_name ) ); ?>"><?php esc_html_e( 'Move down', 'twentythree-core' ); ?></button>
				<?php if ( ! $preserved ) : ?>
					<button type="button" class="button" data-indent aria-label="<?php echo esc_attr( sprintf( __( 'Nest %s under the preceding item', 'twentythree-core' ), $item_name ) ); ?>"><?php esc_html_e( 'Nest', 'twentythree-core' ); ?></button>
					<button type="button" class="button" data-outdent aria-label="<?php echo esc_attr( sprintf( __( 'Move %s to the top level', 'twentythree-core' ), $item_name ) ); ?>"><?php esc_html_e( 'Unnest', 'twentythree-core' ); ?></button>
					<button type="button" class="button-link-delete" data-remove aria-label="<?php echo esc_attr( sprintf( __( 'Remove %s', 'twentythree-core' ), $item_name ) ); ?>"><?php esc_html_e( 'Remove', 'twentythree-core' ); ?></button>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}

	/**
	 * Renders options for linked pages or posts.
	 *
	 * @param \WP_Post[] $posts       Content choices.
	 * @param int        $selected_id Selected post ID.
	 * @return void
	 */
	private static function render_content_options( $posts, $selected_id ) {
		$selected_present = false;
		foreach ( $posts as $post ) {
			$selected_present = $selected_present || $selected_id === (int) $post->ID;
			printf( '<option value="%1$d" %2$s>%3$s</option>', (int) $post->ID, selected( $selected_id, $post->ID, false ), esc_html( get_the_title( $post ) ) );
		}

		if ( $selected_id && ! $selected_present ) {
			$post = get_post( $selected_id );
			if ( $post instanceof \WP_Post ) {
				printf( '<option value="%1$d" selected>%2$s</option>', (int) $post->ID, esc_html( get_the_title( $post ) ) );
			}
		}
	}

	/**
	 * Gets a bounded list of published content choices.
	 *
	 * @param string $post_type Post type.
	 * @return \WP_Post[]
	 */
	private static function content_choices( $post_type ) {
		return get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
	}

	/**
	 * Selects a requested navigation without exposing arbitrary post types.
	 *
	 * @param \WP_Post[] $navigations Available entities.
	 * @param int        $requested_id Requested entity ID.
	 * @return \WP_Post
	 */
	private static function select_navigation( $navigations, $requested_id ) {
		foreach ( $navigations as $navigation ) {
			if ( $requested_id === (int) $navigation->ID && current_user_can( 'edit_post', $navigation->ID ) ) {
				return $navigation;
			}
		}

		return $navigations[0];
	}

	/**
	 * Creates a stable value for stale-form detection.
	 *
	 * @param \WP_Post $navigation Navigation entity.
	 * @return string
	 */
	private static function content_revision( $navigation ) {
		return hash( 'sha256', $navigation->post_modified_gmt . "\n" . $navigation->post_content );
	}

	/**
	 * Displays a whitelisted result notice.
	 *
	 * @return void
	 */
	private static function render_notice() {
		$code = isset( $_GET['twentythree_notice'] ) ? sanitize_key( wp_unslash( $_GET['twentythree_notice'] ) ) : '';
		if ( ! $code ) {
			return;
		}

		$messages = array(
			'saved'                  => array( 'success', __( 'Navigation saved.', 'twentythree-core' ) ),
			'conflict'               => array( 'error', __( 'This navigation changed after the page loaded. Review the latest version and try again.', 'twentythree-core' ) ),
			'invalid_items'          => array( 'error', __( 'The submitted navigation items are invalid.', 'twentythree-core' ) ),
			'invalid_item'           => array( 'error', __( 'A navigation item is invalid.', 'twentythree-core' ) ),
			'invalid_preserved_item' => array( 'error', __( 'Protected navigation content could not be verified.', 'twentythree-core' ) ),
			'missing_preserved_item' => array( 'error', __( 'Protected navigation content cannot be removed from this screen.', 'twentythree-core' ) ),
			'invalid_source_item'    => array( 'error', __( 'A navigation item could not be verified.', 'twentythree-core' ) ),
			'invalid_depth'          => array( 'error', __( 'Navigation items may be nested one level only.', 'twentythree-core' ) ),
			'invalid_type'           => array( 'error', __( 'A navigation item has an unsupported type.', 'twentythree-core' ) ),
			'missing_label'          => array( 'error', __( 'Every navigation item needs a label.', 'twentythree-core' ) ),
			'invalid_object'         => array( 'error', __( 'The selected page or post is not available.', 'twentythree-core' ) ),
			'invalid_url'            => array( 'error', __( 'A custom navigation URL is invalid.', 'twentythree-core' ) ),
			'missing_url'            => array( 'error', __( 'A link needs a destination unless it contains nested items.', 'twentythree-core' ) ),
			'save_failed'            => array( 'error', __( 'WordPress could not save the navigation. Please try again.', 'twentythree-core' ) ),
		);

		if ( ! isset( $messages[ $code ] ) ) {
			return;
		}

		printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $messages[ $code ][0] ), esc_html( $messages[ $code ][1] ) );
	}

	/**
	 * Redirects back to the screen with a safe status code.
	 *
	 * @param int    $navigation_id Navigation entity ID.
	 * @param string $notice_code   Whitelisted notice code.
	 * @return void
	 */
	private static function redirect_with_notice( $navigation_id, $notice_code ) {
		$url = add_query_arg(
			array(
				'page'                 => self::PAGE_SLUG,
				'navigation'           => $navigation_id,
				'twentythree_notice'   => sanitize_key( $notice_code ),
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}
}
