<?php
/**
 * Block variation attributes and render filters for KB Docs.
 *
 * @package wpcomsp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register custom attributes for core blocks used by KB Docs variations.
 *
 * @param array  $args       Block type args.
 * @param string $block_name Block name.
 *
 * @return array
 */
function a8csp_kb_docs_register_block_variation_attributes( array $args, string $block_name ): array {
	if ( 'core/term-count' === $block_name ) {
		if ( ! isset( $args['attributes'] ) || ! is_array( $args['attributes'] ) ) {
			$args['attributes'] = array();
		}

		$args['attributes']['suffixPostTypeLabel'] = array(
			'type'    => 'boolean',
			'default' => false,
		);

		$args['attributes']['suffixSingularLabel'] = array(
			'type'    => 'string',
			'default' => '',
		);

		$args['attributes']['suffixPluralLabel'] = array(
			'type'    => 'string',
			'default' => '',
		);

		$args['attributes']['linkToArchive'] = array(
			'type'    => 'boolean',
			'default' => false,
		);

		return $args;
	}

	if ( 'core/categories' !== $block_name ) {
		return $args;
	}

	if ( ! isset( $args['attributes'] ) || ! is_array( $args['attributes'] ) ) {
		$args['attributes'] = array();
	}

	$args['attributes']['inheritParentFromContext'] = array(
		'type'    => 'boolean',
		'default' => false,
	);

	$args['attributes']['limitTerms'] = array(
		'type'    => 'boolean',
		'default' => false,
	);

	$args['attributes']['termsLimit'] = array(
		'type'    => 'number',
		'default' => 5,
	);

	$existing_context = array();
	if ( isset( $args['usesContext'] ) && is_array( $args['usesContext'] ) ) {
		$existing_context = $args['usesContext'];
	}

	$args['usesContext'] = array_values(
		array_unique(
			array_merge(
				$existing_context,
				array( 'termId', 'taxonomy' )
			)
		)
	);

	return $args;
}
add_filter( 'register_block_type_args', 'a8csp_kb_docs_register_block_variation_attributes', 10, 2 );

/**
 * Get the number of published posts assigned to a term.
 *
 * WordPress stores direct assignments only in `$term->count`. For hierarchical
 * docs categories, include posts assigned to descendant terms as well.
 *
 * @param WP_Term $term             Term object.
 * @param bool    $include_children Whether to include descendant term posts.
 *
 * @return int
 */
function a8csp_kb_docs_get_term_post_count( WP_Term $term, bool $include_children = true ): int {
	if ( ! $include_children ) {
		return (int) $term->count;
	}

	$taxonomy_object = get_taxonomy( $term->taxonomy );
	if ( ! ( $taxonomy_object instanceof WP_Taxonomy ) || ! $taxonomy_object->hierarchical ) {
		return (int) $term->count;
	}

	$post_types = array_values(
		array_filter(
			$taxonomy_object->object_type,
			'post_type_exists'
		)
	);

	if ( count( $post_types ) < 1 ) {
		return (int) $term->count;
	}

	$query = new WP_Query(
		array(
			'post_type'              => $post_types,
			'post_status'            => 'publish',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'tax_query'              => array(
				array(
					'taxonomy'         => $term->taxonomy,
					'field'            => 'term_id',
					'terms'            => array( (int) $term->term_id ),
					'include_children' => true,
				),
			),
		)
	);

	return (int) $query->found_posts;
}

/**
 * Register REST fields used by KB Docs block variations.
 *
 * @return void
 */
function a8csp_kb_docs_register_block_variation_rest_fields(): void {
	$taxonomy = a8csp_kb_docs_get_taxonomy();

	register_rest_field(
		$taxonomy,
		'kb_docs_post_count',
		array(
			'get_callback' => static function ( array $term_data ) use ( $taxonomy ): int {
				if ( ! isset( $term_data['id'] ) ) {
					return 0;
				}

				$term = get_term( (int) $term_data['id'], $taxonomy );
				if ( ! ( $term instanceof WP_Term ) ) {
					return 0;
				}

				return a8csp_kb_docs_get_term_post_count( $term, true );
			},
			'schema'       => array(
				'description' => __( 'Published post count including child terms.', 'kb-docs' ),
				'type'        => 'integer',
				'context'     => array( 'view', 'edit' ),
			),
		)
	);
}
add_action( 'rest_api_init', 'a8csp_kb_docs_register_block_variation_rest_fields' );

/**
 * Resolve the term for a core/term-count block instance.
 *
 * @param WP_Block $block Block instance.
 *
 * @return WP_Term|null
 */
function a8csp_kb_docs_get_term_for_term_count_block( WP_Block $block ): ?WP_Term {
	if ( isset( $block->context['termId'], $block->context['taxonomy'] ) ) {
		$term = get_term( (int) $block->context['termId'], (string) $block->context['taxonomy'] );
		return ( $term instanceof WP_Term && ! is_wp_error( $term ) ) ? $term : null;
	}

	$queried_object = get_queried_object();
	return $queried_object instanceof WP_Term ? $queried_object : null;
}

/**
 * Get the singular or plural post type label for a taxonomy term count.
 *
 * @param string $taxonomy Taxonomy slug.
 * @param int    $count    Term post count.
 *
 * @return string
 */
function a8csp_kb_docs_get_post_type_label_for_taxonomy( string $taxonomy, int $count ): string {
	$taxonomy_object = get_taxonomy( $taxonomy );
	if ( ! ( $taxonomy_object instanceof WP_Taxonomy ) ) {
		return '';
	}

	if ( ! is_array( $taxonomy_object->object_type ) || count( $taxonomy_object->object_type ) < 1 ) {
		return '';
	}

	$post_type        = (string) $taxonomy_object->object_type[0];
	$post_type_object = get_post_type_object( $post_type );
	if ( ! ( $post_type_object instanceof WP_Post_Type ) ) {
		return '';
	}

	if ( 1 === $count ) {
		return (string) $post_type_object->labels->singular_name;
	}

	return (string) $post_type_object->labels->name;
}

/**
 * Resolve the suffix label for a term count.
 *
 * Uses the user-provided singular/plural suffix when set, otherwise falls back
 * to the taxonomy's associated post type label.
 *
 * @param string $taxonomy   Taxonomy slug.
 * @param int    $count      Term post count.
 * @param array  $attributes Block attributes.
 *
 * @return string
 */
function a8csp_kb_docs_get_suffix_label( string $taxonomy, int $count, array $attributes ): string {
	$custom_label = 1 === $count
		? ( isset( $attributes['suffixSingularLabel'] ) ? trim( (string) $attributes['suffixSingularLabel'] ) : '' )
		: ( isset( $attributes['suffixPluralLabel'] ) ? trim( (string) $attributes['suffixPluralLabel'] ) : '' );

	if ( '' !== $custom_label ) {
		return $custom_label;
	}

	return a8csp_kb_docs_get_post_type_label_for_taxonomy( $taxonomy, $count );
}

/**
 * Format a term count with an optional label suffix.
 *
 * @param WP_Term $term       Term object.
 * @param array   $attributes Block attributes.
 *
 * @return string
 */
function a8csp_kb_docs_format_term_count_output( WP_Term $term, array $attributes ): string {
	$include_children = ! empty( $attributes['suffixPostTypeLabel'] );
	$count            = a8csp_kb_docs_get_term_post_count( $term, $include_children );

	if ( ! empty( $attributes['suffixPostTypeLabel'] ) ) {
		$suffix_label = a8csp_kb_docs_get_suffix_label( $term->taxonomy, $count, $attributes );
		if ( '' !== $suffix_label ) {
			return sprintf( '%d %s', $count, $suffix_label );
		}
	}

	$bracket_type = isset( $attributes['bracketType'] ) ? (string) $attributes['bracketType'] : 'round';

	switch ( $bracket_type ) {
		case 'none':
			return (string) $count;
		case 'square':
			return sprintf( '[%d]', $count );
		case 'curly':
			return sprintf( '{%d}', $count );
		case 'angle':
			return sprintf( '<%d>', $count );
		case 'round':
		default:
			return sprintf( '(%d)', $count );
	}
}

/**
 * Wrap the inner content of a rendered term-count block in a term archive link.
 *
 * Preserves the core wrapper (and its supports classes) by only wrapping the
 * inner content of the outer <div>.
 *
 * @param string $block_content Rendered block HTML.
 * @param string $term_link     Term archive URL.
 *
 * @return string
 */
function a8csp_kb_docs_wrap_term_count_in_link( string $block_content, string $term_link ): string {
	$link_directives = a8csp_kb_docs_get_link_directives();

	$anchor_open = sprintf(
		'<a class="a8csp-kbdocs-term-count-link" href="%1$s"%2$s>',
		esc_url( $term_link ),
		$link_directives
	);

	$updated_content = preg_replace_callback(
		'#^(<div\b[^>]*>)(.*?)(</div>)$#s',
		static function ( array $matches ) use ( $anchor_open ): string {
			return $matches[1]
				. $anchor_open
				. '<span class="a8csp-kbdocs-term-count-label">'
				. $matches[2]
				. '</span></a>'
				. $matches[3];
		},
		$block_content,
		1
	);

	return is_string( $updated_content ) ? $updated_content : $block_content;
}

/**
 * Render core/term-count with KB Docs variation output when enabled.
 *
 * Replaces only the inner text of the core-rendered markup so block supports
 * classes (color, typography, etc.) from get_block_wrapper_attributes() are
 * preserved. Calling get_block_wrapper_attributes() again inside this filter
 * fails because WP_Block_Supports::$block_to_render has already been restored
 * to the parent block (often the Term Template).
 *
 * @param string   $block_content Rendered block HTML.
 * @param array    $block         Parsed block data.
 * @param WP_Block $instance      Block instance.
 *
 * @return string
 */
function a8csp_kb_docs_render_term_count_block_variation( string $block_content, array $block, WP_Block $instance ): string {
	unset( $block );

	$attributes = $instance->attributes ?? array();
	if ( ! is_array( $attributes ) ) {
		return $block_content;
	}

	$do_suffix = ! empty( $attributes['suffixPostTypeLabel'] );
	$do_link   = ! empty( $attributes['linkToArchive'] );

	if ( ! $do_suffix && ! $do_link ) {
		return $block_content;
	}

	if ( '' === $block_content ) {
		return $block_content;
	}

	$term = a8csp_kb_docs_get_term_for_term_count_block( $instance );
	if ( ! ( $term instanceof WP_Term ) ) {
		return $block_content;
	}

	$updated_content = $block_content;

	if ( $do_suffix ) {
		$formatted_count = a8csp_kb_docs_format_term_count_output( $term, $attributes );

		/*
		 * Use a callback — concatenating '$1' . '5 articles' produces '$15 articles',
		 * which preg_replace treats as backreference 15 and drops the opening <div>.
		 */
		$suffixed_content = preg_replace_callback(
			'#^(<div\b[^>]*>).*?(</div>)$#s',
			static function ( array $matches ) use ( $formatted_count ): string {
				return $matches[1] . esc_html( $formatted_count ) . $matches[2];
			},
			$updated_content,
			1
		);

		if ( is_string( $suffixed_content ) ) {
			$updated_content = $suffixed_content;
		}
	}

	if ( $do_link ) {
		$term_link = get_term_link( $term );
		if ( ! is_wp_error( $term_link ) && is_string( $term_link ) && '' !== $term_link ) {
			$updated_content = a8csp_kb_docs_wrap_term_count_in_link( $updated_content, $term_link );
		}
	}

	return is_string( $updated_content ) ? $updated_content : $block_content;
}
add_filter( 'render_block_core/term-count', 'a8csp_kb_docs_render_term_count_block_variation', 10, 3 );

/**
 * Resolve the parent term ID from Term Template context for categories.
 *
 * @param WP_Block $block Block instance.
 *
 * @return int Parent term ID, or 0 when unavailable.
 */
function a8csp_kb_docs_get_categories_parent_term_id( WP_Block $block ): int {
	if ( isset( $block->context['termId'] ) && (int) $block->context['termId'] > 0 ) {
		return (int) $block->context['termId'];
	}

	return 0;
}

/**
 * Resolve the taxonomy for a categories block with parent inheritance.
 *
 * @param WP_Block $block      Block instance.
 * @param array    $attributes Block attributes.
 *
 * @return string
 */
function a8csp_kb_docs_get_categories_taxonomy( WP_Block $block, array $attributes ): string {
	if ( isset( $block->context['taxonomy'] ) && is_string( $block->context['taxonomy'] ) && '' !== $block->context['taxonomy'] ) {
		return sanitize_key( $block->context['taxonomy'] );
	}

	if ( isset( $attributes['taxonomy'] ) && is_string( $attributes['taxonomy'] ) && '' !== $attributes['taxonomy'] ) {
		return sanitize_key( $attributes['taxonomy'] );
	}

	return a8csp_kb_docs_get_taxonomy();
}

/**
 * Build wp_list_categories / wp_dropdown_categories args for the KB Docs variation.
 *
 * @param array    $attributes Block attributes.
 * @param WP_Block $block      Block instance.
 *
 * @return array<string, mixed>|null Query args, or null when the block should render nothing.
 */
function a8csp_kb_docs_get_categories_variation_args( array $attributes, WP_Block $block ): ?array {
	$parent_term_id = a8csp_kb_docs_get_categories_parent_term_id( $block );
	if ( $parent_term_id < 1 ) {
		return null;
	}

	$taxonomy = a8csp_kb_docs_get_categories_taxonomy( $block, $attributes );
	if ( ! taxonomy_exists( $taxonomy ) ) {
		return null;
	}

	$hide_empty = empty( $attributes['showEmpty'] );
	$show_only_top_level = ! empty( $attributes['showOnlyTopLevel'] );

	$args = array(
		'echo'         => false,
		'hierarchical' => ! empty( $attributes['showHierarchy'] ),
		'orderby'      => 'name',
		'show_count'   => ! empty( $attributes['showPostCounts'] ),
		'taxonomy'     => $taxonomy,
		'title_li'     => '',
		'hide_empty'   => $hide_empty,
	);

	/*
	 * When inheriting, "Show only top level terms" means direct children of the
	 * context term (second level relative to the docs root). Otherwise include
	 * all descendants via child_of.
	 */
	if ( $show_only_top_level ) {
		$args['parent'] = $parent_term_id;
	} else {
		$args['child_of'] = $parent_term_id;
	}

	/*
	 * WP_Term_Query ignores `number` when `parent` is set unless `hierarchical`
	 * is also true (post-query array_slice). Categories often has hierarchy off,
	 * so apply the limit ourselves via `include`.
	 */
	if ( ! empty( $attributes['limitTerms'] ) ) {
		$terms_limit = isset( $attributes['termsLimit'] ) ? (int) $attributes['termsLimit'] : 0;

		if ( $terms_limit > 0 ) {
			$term_query_args = array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => $hide_empty,
				'orderby'    => 'name',
				'order'      => 'ASC',
				'fields'     => 'ids',
			);

			if ( $show_only_top_level ) {
				$term_query_args['parent'] = $parent_term_id;
			} else {
				$term_query_args['child_of'] = $parent_term_id;
			}

			$term_ids = get_terms( $term_query_args );

			if ( ! is_array( $term_ids ) || count( $term_ids ) < 1 ) {
				return null;
			}

			$term_ids = array_slice( array_map( 'intval', $term_ids ), 0, $terms_limit );

			if ( count( $term_ids ) < 1 ) {
				return null;
			}

			unset( $args['parent'], $args['child_of'], $args['number'] );
			$args['include'] = $term_ids;
			$args['orderby'] = 'include';
		}
	}

	return $args;
}

/**
 * Extract opening-tag attributes from core-rendered block markup.
 *
 * Used after render_block_* filters, when get_block_wrapper_attributes() no
 * longer has the correct WP_Block_Supports::$block_to_render context.
 *
 * @param string $block_content Core-rendered block HTML.
 *
 * @return string Attribute string suitable for an opening tag, or empty.
 */
function a8csp_kb_docs_get_rendered_wrapper_attributes( string $block_content ): string {
	if ( '' === $block_content ) {
		return '';
	}

	if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
		return '';
	}

	$processor = new WP_HTML_Tag_Processor( $block_content );
	if ( ! $processor->next_tag() ) {
		return '';
	}

	$class = $processor->get_attribute( 'class' );
	$style = $processor->get_attribute( 'style' );
	$id    = $processor->get_attribute( 'id' );

	$parts = array();

	if ( is_string( $class ) && '' !== $class ) {
		$parts[] = 'class="' . esc_attr( $class ) . '"';
	}

	if ( is_string( $style ) && '' !== $style ) {
		$parts[] = 'style="' . esc_attr( $style ) . '"';
	}

	if ( is_string( $id ) && '' !== $id ) {
		$parts[] = 'id="' . esc_attr( $id ) . '"';
	}

	return implode( ' ', $parts );
}

/**
 * Append a CSS class to a wrapper attributes string.
 *
 * @param string $wrapper_attributes Existing attributes string.
 * @param string $class_name         Class name to append.
 *
 * @return string
 */
function a8csp_kb_docs_append_wrapper_class( string $wrapper_attributes, string $class_name ): string {
	if ( '' === $class_name ) {
		return $wrapper_attributes;
	}

	if ( preg_match( '/\bclass=(["\'])(.*?)\1/', $wrapper_attributes, $matches ) === 1 ) {
		$quote        = $matches[1];
		$existing     = $matches[2];
		$updated_class = trim( $existing . ' ' . $class_name );

		return (string) preg_replace(
			'/\bclass=(["\'])(.*?)\1/',
			'class=' . $quote . esc_attr( $updated_class ) . $quote,
			$wrapper_attributes,
			1
		);
	}

	$class_attr = 'class="' . esc_attr( $class_name ) . '"';

	return '' === $wrapper_attributes
		? $class_attr
		: $wrapper_attributes . ' ' . $class_attr;
}

/**
 * Render core/categories with KB Docs parent-inheritance variation.
 *
 * @param string   $block_content Rendered block HTML.
 * @param array    $block         Parsed block data.
 * @param WP_Block $instance      Block instance.
 *
 * @return string
 */
function a8csp_kb_docs_render_categories_block_variation( string $block_content, array $block, WP_Block $instance ): string {
	unset( $block );

	$attributes = $instance->attributes ?? array();
	if ( ! is_array( $attributes ) || empty( $attributes['inheritParentFromContext'] ) ) {
		return $block_content;
	}

	$args = a8csp_kb_docs_get_categories_variation_args( $attributes, $instance );
	if ( null === $args ) {
		return '';
	}

	$taxonomy = get_taxonomy( (string) $args['taxonomy'] );
	if ( ! ( $taxonomy instanceof WP_Taxonomy ) ) {
		return '';
	}

	static $block_id = 0;
	++$block_id;

	// Reuse supports classes/styles from core's already-rendered wrapper.
	$wrapper_attributes = a8csp_kb_docs_get_rendered_wrapper_attributes( $block_content );
	$wrapper_attributes = a8csp_kb_docs_append_wrapper_class(
		$wrapper_attributes,
		'a8csp-kbdocs-categories-child-terms'
	);

	if ( ! empty( $attributes['displayAsDropdown'] ) ) {
		$id                       = 'wp-block-categories-kb-docs-' . $block_id;
		$args['id']               = $id;
		$args['name']             = $taxonomy->query_var;
		$args['value_field']      = 'slug';
		$args['show_option_none'] = sprintf(
			/* translators: %s: taxonomy's singular name */
			__( 'Select %s', 'kb-docs' ),
			$taxonomy->labels->singular_name
		);
		$args['selected']         = get_query_var( $taxonomy->query_var );

		$show_label     = empty( $attributes['showLabel'] ) ? ' screen-reader-text' : '';
		$default_label  = $taxonomy->label;
		$label_text     = ! empty( $attributes['label'] ) ? wp_kses_post( (string) $attributes['label'] ) : $default_label;
		$wrapper_markup = '<div %1$s><label class="wp-block-categories__label' . $show_label . '" for="' . esc_attr( $id ) . '">' . $label_text . '</label>%2$s</div>';
		$items_markup   = wp_dropdown_categories( $args );

		if ( ! is_admin() && is_string( $items_markup ) && '' !== $items_markup ) {
			$items_markup = preg_replace(
				'#(?<=</select>)#',
				build_dropdown_script_block_core_categories( $id ),
				$items_markup,
				1
			);
		}
	} else {
		$args['show_option_none'] = $taxonomy->labels->no_terms;
		$wrapper_markup           = '<ul %1$s>%2$s</ul>';
		$items_markup             = wp_list_categories( $args );

		if ( is_string( $items_markup ) && '' !== $items_markup ) {
			$items_markup = a8csp_kb_docs_add_link_directives_to_anchors( $items_markup );
		}
	}

	if ( ! is_string( $items_markup ) || '' === $items_markup ) {
		return '';
	}

	return sprintf(
		$wrapper_markup,
		$wrapper_attributes,
		$items_markup
	);
}
add_filter( 'render_block_core/categories', 'a8csp_kb_docs_render_categories_block_variation', 10, 3 );
