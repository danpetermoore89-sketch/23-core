<?php
/**
 * Navigation block parsing and serialisation.
 *
 * @package TwentyThreeCore
 */

namespace TwentyThree\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts a deliberately small subset of navigation blocks to editable rows.
 */
final class Navigation_Content {

	const MAX_ITEMS = 100;

	/**
	 * Returns the editor rows represented by navigation block markup.
	 *
	 * Unsupported top-level branches are represented by immutable tokens. Their
	 * markup never travels through the browser and is recovered from the current
	 * database value during saving.
	 *
	 * @param string $content Navigation post content.
	 * @return array<int, array<string, mixed>>
	 */
	public static function editor_items( $content ) {
		$model = self::parse_model( $content );

		return $model['items'];
	}

	/**
	 * Builds safe navigation markup from submitted editor rows.
	 *
	 * @param string                         $current_content Current database content.
	 * @param array<int, array<string,mixed>> $submitted       Submitted editor rows.
	 * @return string|\WP_Error
	 */
	public static function build_content( $current_content, $submitted ) {
		if ( ! is_array( $submitted ) || count( $submitted ) > self::MAX_ITEMS ) {
			return new \WP_Error( 'invalid_items', __( 'The submitted navigation items are invalid.', 'twentythree-core' ) );
		}

		$model              = self::parse_model( $current_content );
		$rows               = array();
		$seen_preserved     = array();
		$seen_source_tokens = array();

		foreach ( $submitted as $submitted_item ) {
			if ( ! is_array( $submitted_item ) ) {
				return new \WP_Error( 'invalid_item', __( 'A navigation item is invalid.', 'twentythree-core' ) );
			}

			$token = isset( $submitted_item['source_token'] ) ? sanitize_key( $submitted_item['source_token'] ) : '';

			if ( ! empty( $submitted_item['preserved'] ) ) {
				if ( ! isset( $model['preserved'][ $token ] ) || isset( $seen_preserved[ $token ] ) ) {
					return new \WP_Error( 'invalid_preserved_item', __( 'Protected navigation content could not be verified.', 'twentythree-core' ) );
				}

				$seen_preserved[ $token ] = true;
				$rows[]                    = array(
					'preserved' => true,
					'block'     => $model['preserved'][ $token ],
					'depth'     => 0,
				);
				continue;
			}

			if ( $token ) {
				if ( ! isset( $model['editable'][ $token ] ) || isset( $seen_source_tokens[ $token ] ) ) {
					return new \WP_Error( 'invalid_source_item', __( 'A navigation item could not be verified.', 'twentythree-core' ) );
				}

				$seen_source_tokens[ $token ] = true;
			}

			$row = self::sanitize_item( $submitted_item );
			if ( is_wp_error( $row ) ) {
				return $row;
			}

			$row['source_attrs'] = $token ? $model['editable'][ $token ] : array();
			$rows[]              = $row;
		}

		if ( count( $seen_preserved ) !== count( $model['preserved'] ) ) {
			return new \WP_Error( 'missing_preserved_item', __( 'Protected navigation content cannot be removed from this screen.', 'twentythree-core' ) );
		}

		$parent_index = null;
		foreach ( $rows as $index => $row ) {
			if ( ! empty( $row['preserved'] ) ) {
				$parent_index = null;
				continue;
			}

			if ( 0 === $row['depth'] ) {
				$parent_index = $index;
				continue;
			}

			if ( null === $parent_index || ! empty( $rows[ $parent_index ]['preserved'] ) ) {
				return new \WP_Error( 'invalid_depth', __( 'A submenu item must follow an editable top-level item.', 'twentythree-core' ) );
			}
		}

		$blocks = array();
		$count  = count( $rows );

		for ( $index = 0; $index < $count; $index++ ) {
			$row = $rows[ $index ];

			if ( ! empty( $row['preserved'] ) ) {
				$blocks[] = $row['block'];
				continue;
			}

			if ( 1 === $row['depth'] ) {
				continue;
			}

			$children = array();
			$cursor   = $index + 1;
			while ( $cursor < $count && empty( $rows[ $cursor ]['preserved'] ) && 1 === $rows[ $cursor ]['depth'] ) {
				if ( '' === $rows[ $cursor ]['url'] ) {
					return new \WP_Error( 'missing_url', __( 'Submenu links must have a destination.', 'twentythree-core' ) );
				}
				$children[] = self::create_block( $rows[ $cursor ], array(), false );
				$cursor++;
			}

			if ( '' === $row['url'] && empty( $children ) ) {
				return new \WP_Error( 'missing_url', __( 'A top-level label without a URL must contain submenu items.', 'twentythree-core' ) );
			}

			$blocks[] = self::create_block( $row, $children, true );
		}

		return serialize_blocks( $blocks );
	}

	/**
	 * Parses navigation content into editor rows and trusted server-side maps.
	 *
	 * @param string $content Navigation content.
	 * @return array<string, array<mixed>>
	 */
	private static function parse_model( $content ) {
		$model = array(
			'items'     => array(),
			'editable'  => array(),
			'preserved' => array(),
		);

		foreach ( parse_blocks( $content ) as $index => $block ) {
			if ( empty( $block['blockName'] ) && '' === trim( serialize_block( $block ) ) ) {
				continue;
			}

			if ( self::is_supported_root( $block ) ) {
				$token                       = 'e-' . $index;
				$model['editable'][ $token ] = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
				$model['items'][]             = self::block_to_item( $block, $token, 0 );

				if ( 'core/navigation-submenu' === $block['blockName'] ) {
					foreach ( $block['innerBlocks'] as $child_index => $child ) {
						$child_token                       = 'e-' . $index . '-' . $child_index;
						$model['editable'][ $child_token ] = isset( $child['attrs'] ) && is_array( $child['attrs'] ) ? $child['attrs'] : array();
						$model['items'][]                   = self::block_to_item( $child, $child_token, 1 );
					}
				}
				continue;
			}

			$token                         = 'p-' . $index;
			$model['preserved'][ $token ]  = $block;
			$model['items'][]               = array(
				'source_token' => $token,
				'preserved'    => true,
				'depth'        => 0,
				'description'  => self::preserved_description( $block ),
			);
		}

		return $model;
	}

	/**
	 * Whether a top-level branch can be safely represented by this editor.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @return bool
	 */
	private static function is_supported_root( $block ) {
		if ( 'core/navigation-link' === $block['blockName'] ) {
			return true;
		}

		if ( 'core/navigation-submenu' !== $block['blockName'] || empty( $block['innerBlocks'] ) ) {
			return false;
		}

		foreach ( $block['innerBlocks'] as $child ) {
			if ( 'core/navigation-link' !== $child['blockName'] ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Converts one supported block to an editor row.
	 *
	 * @param array<string, mixed> $block Block data.
	 * @param string               $token Source token.
	 * @param int                  $depth Nesting depth.
	 * @return array<string, mixed>
	 */
	private static function block_to_item( $block, $token, $depth ) {
		$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
		$type  = isset( $attrs['kind'], $attrs['type'] ) && 'post-type' === $attrs['kind'] && in_array( $attrs['type'], array( 'page', 'post' ), true ) ? $attrs['type'] : 'custom';

		return array(
			'source_token' => $token,
			'preserved'    => false,
			'depth'        => $depth,
			'type'         => $type,
			'label'        => isset( $attrs['label'] ) ? (string) $attrs['label'] : '',
			'url'          => isset( $attrs['url'] ) ? (string) $attrs['url'] : '',
			'page_id'      => 'page' === $type && ! empty( $attrs['id'] ) ? (int) $attrs['id'] : 0,
			'post_id'      => 'post' === $type && ! empty( $attrs['id'] ) ? (int) $attrs['id'] : 0,
			'new_tab'      => ! empty( $attrs['opensInNewTab'] ),
		);
	}

	/**
	 * Sanitises one submitted editable row.
	 *
	 * @param array<string, mixed> $item Submitted row.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function sanitize_item( $item ) {
		$type = isset( $item['type'] ) ? sanitize_key( $item['type'] ) : 'custom';
		if ( ! in_array( $type, array( 'page', 'post', 'custom' ), true ) ) {
			return new \WP_Error( 'invalid_type', __( 'A navigation item has an unsupported type.', 'twentythree-core' ) );
		}

		$label = isset( $item['label'] ) ? sanitize_text_field( $item['label'] ) : '';
		if ( '' === $label ) {
			return new \WP_Error( 'missing_label', __( 'Every navigation item needs a label.', 'twentythree-core' ) );
		}

		$depth = isset( $item['depth'] ) ? absint( $item['depth'] ) : 0;
		if ( $depth > 1 ) {
			return new \WP_Error( 'invalid_depth', __( 'Navigation items can be nested by one level only.', 'twentythree-core' ) );
		}

		$row = array(
			'preserved' => false,
			'depth'     => $depth,
			'type'      => $type,
			'label'     => $label,
			'new_tab'   => ! empty( $item['new_tab'] ),
			'url'       => '',
			'object_id' => 0,
		);

		if ( in_array( $type, array( 'page', 'post' ), true ) ) {
			$id   = isset( $item[ $type . '_id' ] ) ? absint( $item[ $type . '_id' ] ) : 0;
			$post = $id ? get_post( $id ) : null;

			if ( ! $post instanceof \WP_Post || $type !== $post->post_type || 'publish' !== $post->post_status ) {
				return new \WP_Error( 'invalid_object', __( 'The selected page or post is not available.', 'twentythree-core' ) );
			}

			$row['object_id'] = $id;
			$row['url']       = (string) get_permalink( $id );
			return $row;
		}

		$url = isset( $item['url'] ) ? trim( (string) $item['url'] ) : '';
		if ( '' !== $url ) {
			$protocol_safe = wp_kses_bad_protocol( $url, wp_allowed_protocols() );
			if ( $protocol_safe !== $url ) {
				return new \WP_Error( 'invalid_url', __( 'A custom navigation URL is invalid.', 'twentythree-core' ) );
			}
			$url           = esc_url_raw( $protocol_safe );

			if ( '' === $url ) {
				return new \WP_Error( 'invalid_url', __( 'A custom navigation URL is invalid.', 'twentythree-core' ) );
			}
		}

		$row['url'] = $url;

		return $row;
	}

	/**
	 * Creates a serialisable navigation block.
	 *
	 * @param array<string, mixed>                $row      Sanitised editor row.
	 * @param array<int, array<string, mixed>>    $children Child blocks.
	 * @param bool                                $top_level Whether this is top-level.
	 * @return array<string, mixed>
	 */
	private static function create_block( $row, $children, $top_level ) {
		$attrs = isset( $row['source_attrs'] ) && is_array( $row['source_attrs'] ) ? $row['source_attrs'] : array();

		foreach ( array( 'label', 'url', 'type', 'kind', 'id', 'opensInNewTab', 'isTopLevelLink', 'isTopLevelItem' ) as $controlled_attr ) {
			unset( $attrs[ $controlled_attr ] );
		}

		$attrs['label'] = $row['label'];
		if ( '' !== $row['url'] ) {
			$attrs['url'] = $row['url'];
		}

		if ( 'custom' === $row['type'] ) {
			$attrs['kind'] = 'custom';
			$attrs['type'] = 'custom';
		} else {
			$attrs['kind'] = 'post-type';
			$attrs['type'] = $row['type'];
			$attrs['id']   = $row['object_id'];
		}

		if ( $row['new_tab'] ) {
			$attrs['opensInNewTab'] = true;
		}

		if ( $children ) {
			$attrs['isTopLevelItem'] = true;
			return array(
				'blockName'    => 'core/navigation-submenu',
				'attrs'        => $attrs,
				'innerBlocks'  => $children,
				'innerHTML'    => '',
				'innerContent' => array_fill( 0, count( $children ), null ),
			);
		}

		$attrs['isTopLevelLink'] = (bool) $top_level;

		return array(
			'blockName'    => 'core/navigation-link',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);
	}

	/**
	 * Describes an immutable block without exposing its markup.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @return string
	 */
	private static function preserved_description( $block ) {
		if ( ! empty( $block['blockName'] ) ) {
			return sprintf(
				/* translators: %s: block name. */
				__( 'Preserved block: %s', 'twentythree-core' ),
				(string) $block['blockName']
			);
		}

		return __( 'Preserved unsupported markup', 'twentythree-core' );
	}
}
