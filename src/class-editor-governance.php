<?php
/**
 * Block editor governance.
 *
 * @package TwentyThreeCore
 */

namespace TwentyThree\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Curates Site Manager editing without changing saved content.
 */
final class Editor_Governance {

	/**
	 * Registers editor filters.
	 *
	 * @return void
	 */
	public static function register() {
		add_filter( 'block_editor_settings_all', array( __CLASS__, 'filter_editor_settings' ), 20, 2 );
		add_filter( 'allowed_block_types_all', array( __CLASS__, 'filter_allowed_blocks' ), 20, 2 );
		add_filter( 'should_load_remote_block_patterns', array( __CLASS__, 'filter_remote_patterns' ) );
	}

	/**
	 * Removes lock and code controls from governed editors.
	 *
	 * @param array<string, mixed>    $settings Editor settings.
	 * @param \WP_Block_Editor_Context $context  Editor context.
	 * @return array<string, mixed>
	 */
	public static function filter_editor_settings( $settings, $context ) {
		unset( $context );

		if ( self::is_governed_editor() ) {
			$settings['canLockBlocks'] = false;
			$settings['codeEditingEnabled'] = false;
		}

		return $settings;
	}

	/**
	 * Restricts new block insertion while retaining blocks already saved.
	 *
	 * @param bool|string[]           $allowed Existing allowed-block setting.
	 * @param \WP_Block_Editor_Context $context Editor context.
	 * @return bool|string[]
	 */
	public static function filter_allowed_blocks( $allowed, $context ) {
		if ( ! self::is_governed_editor() || ! self::is_content_context( $context ) ) {
			return $allowed;
		}

		$curated = self::approved_blocks();
		$post    = isset( $context->post ) ? $context->post : null;

		if ( $post instanceof \WP_Post ) {
			self::collect_saved_block_names( parse_blocks( $post->post_content ), $curated );
		}

		$curated = array_values( array_unique( array_filter( $curated, 'is_string' ) ) );

		if ( false === $allowed ) {
			return false;
		}

		if ( is_array( $allowed ) ) {
			return array_values( array_intersect( $curated, $allowed ) );
		}

		return $curated;
	}

	/**
	 * Prevents third-party remote patterns being added for governed editors.
	 *
	 * @param bool $should_load Whether remote patterns should load.
	 * @return bool
	 */
	public static function filter_remote_patterns( $should_load ) {
		if ( self::is_governed_editor() ) {
			return false;
		}

		return $should_load;
	}

	/**
	 * Whether the current user receives Site Manager governance.
	 *
	 * @return bool
	 */
	private static function is_governed_editor() {
		return current_user_can( 'twentythree_manage_sections' )
			&& ! current_user_can( 'twentythree_unlock_blocks' );
	}

	/**
	 * Limits curation to post and page content editors.
	 *
	 * @param \WP_Block_Editor_Context $context Editor context.
	 * @return bool
	 */
	private static function is_content_context( $context ) {
		if ( ! isset( $context->post ) || ! $context->post instanceof \WP_Post ) {
			return false;
		}

		return in_array( $context->post->post_type, array( 'post', 'page' ), true );
	}

	/**
	 * Returns blocks approved for new insertion.
	 *
	 * Structural blocks are required by approved theme patterns. Locking controls
	 * their internal movement; allowing them ensures those patterns remain valid.
	 *
	 * @return string[]
	 */
	private static function approved_blocks() {
		return array(
			'core/paragraph',
			'core/heading',
			'core/list',
			'core/list-item',
			'core/quote',
			'core/pullquote',
			'core/table',
			'core/image',
			'core/gallery',
			'core/video',
			'core/audio',
			'core/file',
			'core/cover',
			'core/media-text',
			'core/buttons',
			'core/button',
			'core/separator',
			'core/spacer',
			'core/group',
			'core/columns',
			'core/column',
			'core/details',
			'core/embed',
		);
	}

	/**
	 * Recursively adds names of blocks already present in saved content.
	 *
	 * @param array<int, array<string, mixed>> $blocks Block parser output.
	 * @param string[]                         $names  Collected block names.
	 * @return void
	 */
	private static function collect_saved_block_names( $blocks, &$names ) {
		foreach ( $blocks as $block ) {
			if ( ! empty( $block['blockName'] ) && is_string( $block['blockName'] ) ) {
				$names[] = $block['blockName'];
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				self::collect_saved_block_names( $block['innerBlocks'], $names );
			}
		}
	}
}
