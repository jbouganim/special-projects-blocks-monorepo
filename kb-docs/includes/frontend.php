<?php
/**
 * Frontend query helpers for KB Docs blocks.
 *
 * @package wpcomsp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the docs article post type.
 *
 * @return string
 */
function a8csp_kb_docs_get_post_type(): string {
	/**
	 * Filters the post type used by KB Docs blocks.
	 *
	 * @param string $post_type Post type slug.
	 */
	return (string) apply_filters( 'a8csp_kb_docs_post_type', 'kb_article' );
}

/**
 * Get the docs taxonomy.
 *
 * @return string
 */
function a8csp_kb_docs_get_taxonomy(): string {
	/**
	 * Filters the taxonomy used by KB Docs blocks.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 */
	return (string) apply_filters( 'a8csp_kb_docs_taxonomy', 'kb_cat' );
}

/**
 * Get the docs base path/page slug.
 *
 * @return string
 */
function a8csp_kb_docs_get_base_path(): string {
	$base_path = 'docs';

	/**
	 * Filters the docs base path/page slug used by KB Docs blocks.
	 *
	 * @param string $base_path Docs path slug.
	 */
	return (string) apply_filters( 'a8csp_kb_docs_base_path', $base_path );
}

/**
 * Check if request is a docs request.
 *
 * @return bool
 */
function a8csp_kb_docs_is_docs_request(): bool {
	$taxonomy  = a8csp_kb_docs_get_taxonomy();
	$post_type = a8csp_kb_docs_get_post_type();
	$base_path = a8csp_kb_docs_get_base_path();

	return is_page( $base_path ) || is_tax( $taxonomy ) || is_singular( $post_type );
}

/**
 * Get the current docs view slug for IAPI/template-aware styling.
 *
 * @return string One of 'home', 'category', 'single', or empty string.
 */
function a8csp_kb_docs_get_view(): string {
	$taxonomy  = a8csp_kb_docs_get_taxonomy();
	$post_type = a8csp_kb_docs_get_post_type();
	$base_path = a8csp_kb_docs_get_base_path();

	if ( is_singular( $post_type ) ) {
		return 'single';
	}

	if ( is_tax( $taxonomy ) ) {
		return 'category';
	}

	if ( is_page( $base_path ) ) {
		return 'home';
	}

	/**
	 * Filters the KB Docs view slug used for data-kb-view attributes.
	 *
	 * @param string $view View slug or empty string outside docs context.
	 */
	return (string) apply_filters( 'a8csp_kb_docs_view', '' );
}

/**
 * Attributes that identify the current docs view on block markup.
 *
 * Prefer attaching these to IAPI router-region roots so they update on
 * client-side navigation (body classes do not).
 *
 * @return array<string, string>
 */
function a8csp_kb_docs_get_view_attributes(): array {
	$view = a8csp_kb_docs_get_view();

	if ( '' === $view ) {
		return array();
	}

	return array(
		'data-kb-view' => $view,
	);
}

/**
 * Resolve a block spacing attribute value to a CSS spacing value.
 *
 * @param mixed $value Block spacing attribute value.
 * @return string CSS spacing value or empty string.
 */
function a8csp_kb_docs_get_spacing_css_value( mixed $value ): string {
	if ( is_array( $value ) ) {
		$resolved_top = isset( $value['top'] ) && is_string( $value['top'] ) && '' !== $value['top']
			? a8csp_kb_docs_get_spacing_css_value( $value['top'] )
			: '';

		if ( '' !== $resolved_top ) {
			return $resolved_top;
		}

		return '';
	}

	if ( ! is_string( $value ) || '' === $value ) {
		return '';
	}

	if ( '0' === $value ) {
		return '0';
	}

	if ( str_contains( $value, 'var:preset|spacing|' ) ) {
		$slug = _wp_to_kebab_case( (string) substr( $value, strrpos( $value, '|' ) + 1 ) );

		// Theme has no spacing--0 preset; "None" stores var:preset|spacing|0.
		if ( '0' === $slug ) {
			return '0';
		}

		return 'var(--wp--preset--spacing--' . $slug . ')';
	}

	return $value;
}

/**
 * Get link directives for interactivity navigation.
 *
 * @return string
 */
function a8csp_kb_docs_get_link_directives(): string {
	/**
	 * Filters router directives added to KB Docs links.
	 *
	 * @param string $directives Interactivity directives string.
	 */
	return (string) apply_filters(
		'a8csp_kb_docs_link_directives',
		' data-wp-on--mouseenter="actions.prefetch" data-wp-on--click="actions.navigate"'
	);
}

/**
 * Whether the KB Docs Content block should use interactivity on this request.
 *
 *
 * @return bool
 */
function a8csp_kb_docs_content_use_interactivity(): bool {
	/**
	 * Filters whether KB Docs Content outputs the IAPI shell and link directives.
	 *
	 * @param bool $use_interactivity Whether interactivity is enabled.
	 */
	return (bool) apply_filters( 'a8csp_kb_docs_content_use_interactivity', true );
}

/**
 * Add interactivity link directives to anchor tags in HTML content.
 *
 *
 * @param string $html HTML content.
 *
 * @return string
 */
function a8csp_kb_docs_add_link_directives_to_anchors( string $html ): string {
	if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
		return $html;
	}

	$processor = new WP_HTML_Tag_Processor( $html );

	while ( $processor->next_tag( 'A' ) ) {
		if ( null !== $processor->get_attribute( 'data-wp-on--click' ) ) {
			continue;
		}

		$href = $processor->get_attribute( 'href' );
		if ( ! is_string( $href ) || '' === $href || str_starts_with( $href, '#' ) ) {
			continue;
		}

		if ( '_blank' === $processor->get_attribute( 'target' ) ) {
			continue;
		}

		if ( null !== $processor->get_attribute( 'download' ) ) {
			continue;
		}

		$processor->set_attribute( 'data-wp-on--mouseenter', 'actions.prefetch' );
		$processor->set_attribute( 'data-wp-on--click', 'actions.navigate' );
	}

	return $processor->get_updated_html();
}

/**
 * Get the primary docs term for a post.
 *
 * @param int         $post_id  Post ID.
 * @param string|null $taxonomy Optional taxonomy slug override.
 *
 * @return WP_Term|null
 */
function a8csp_kb_docs_get_primary_term_for_post( int $post_id, ?string $taxonomy = null ): ?WP_Term {
	$taxonomy = null !== $taxonomy && '' !== $taxonomy
		? $taxonomy
		: a8csp_kb_docs_get_taxonomy();

	$primary_term_id = (int) get_post_meta( $post_id, '_kb_primary_category', true );
	if ( $primary_term_id > 0 ) {
		$primary_term = get_term( $primary_term_id, $taxonomy );
		if ( $primary_term instanceof WP_Term ) {
			return $primary_term;
		}
	}

	$terms = wp_get_post_terms(
		$post_id,
		$taxonomy,
		array(
			'orderby' => 'parent',
			'order'   => 'ASC',
		)
	);

	if ( ! is_array( $terms ) || count( $terms ) < 1 ) {
		return null;
	}

	$first_term = reset( $terms );
	return $first_term instanceof WP_Term ? $first_term : null;
}

/**
 * Get current docs term from the request.
 *
 * @return WP_Term|null
 */
function a8csp_kb_docs_get_current_term(): ?WP_Term {
	$taxonomy  = a8csp_kb_docs_get_taxonomy();
	$post_type = a8csp_kb_docs_get_post_type();

	if ( is_tax( $taxonomy ) ) {
		$term = get_queried_object();
		return $term instanceof WP_Term ? $term : null;
	}

	if ( is_singular( $post_type ) ) {
		$post_id = get_queried_object_id();
		if ( $post_id > 0 ) {
			return a8csp_kb_docs_get_primary_term_for_post( $post_id );
		}
	}

	return null;
}

/**
 * Get current top-level docs term.
 *
 * @return WP_Term|null
 */
function a8csp_kb_docs_get_current_top_term(): ?WP_Term {
	$current_term = a8csp_kb_docs_get_current_term();
	$taxonomy     = a8csp_kb_docs_get_taxonomy();

	if ( ! ( $current_term instanceof WP_Term ) ) {
		return null;
	}

	$top_term = $current_term;

	while ( (int) $top_term->parent > 0 ) {
		$parent_term = get_term( (int) $top_term->parent, $taxonomy );
		if ( ! ( $parent_term instanceof WP_Term ) ) {
			break;
		}

		$top_term = $parent_term;
	}

	return $top_term;
}

/**
 * Get top-level terms for a taxonomy.
 *
 *
 * @param string $taxonomy Taxonomy slug.
 * @param string $orderby  Term field to order by.
 * @param string $order    Sort direction.
 *
 * @return array<WP_Term>
 */
function a8csp_kb_docs_get_top_level_terms(
	string $taxonomy,
	string $orderby = 'name',
	string $order = 'ASC'
): array {
	$allowed_orderby = array( 'name', 'slug', 'term_id', 'count' );
	$orderby         = in_array( $orderby, $allowed_orderby, true ) ? $orderby : 'name';
	$order           = 'DESC' === strtoupper( $order ) ? 'DESC' : 'ASC';

	if ( ! taxonomy_exists( $taxonomy ) ) {
		return array();
	}

	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'parent'     => 0,
			'hide_empty' => false,
			'orderby'    => $orderby,
			'order'      => $order,
		)
	);

	if ( ! is_array( $terms ) || count( $terms ) < 1 ) {
		return array();
	}

	return array_values(
		array_filter(
			$terms,
			static function ( $term ): bool {
				return $term instanceof WP_Term;
			}
		)
	);
}

/**
 * Get top-level docs sections.
 *
 * @return array<WP_Term>
 */
function a8csp_kb_docs_get_sections(): array {
	return a8csp_kb_docs_get_top_level_terms(
		a8csp_kb_docs_get_taxonomy(),
		'name',
		'ASC'
	);
}

/**
 * Get child terms for a parent term.
 *
 * @param int    $parent_id Parent term ID.
 * @param string $taxonomy  Taxonomy slug.
 * @param string $orderby   Term field to order by.
 * @param string $order     Sort direction.
 *
 * @return array<WP_Term>
 */
function a8csp_kb_docs_get_child_terms(
	int $parent_id,
	string $taxonomy,
	string $orderby = 'name',
	string $order = 'ASC'
): array {
	$allowed_orderby = array( 'name', 'slug', 'term_id', 'count' );
	$orderby         = in_array( $orderby, $allowed_orderby, true ) ? $orderby : 'name';
	$order           = 'DESC' === strtoupper( $order ) ? 'DESC' : 'ASC';

	if ( ! taxonomy_exists( $taxonomy ) || $parent_id < 0 ) {
		return array();
	}

	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'parent'     => $parent_id,
			'hide_empty' => false,
			'orderby'    => $orderby,
			'order'      => $order,
		)
	);

	if ( ! is_array( $terms ) || count( $terms ) < 1 ) {
		return array();
	}

	return array_values(
		array_filter(
			$terms,
			static function ( $term ): bool {
				return $term instanceof WP_Term;
			}
		)
	);
}

/**
 * Get term IDs that should be expanded for the current docs request.
 *
 * Includes the current term and all of its ancestors.
 *
 * @param string|null $taxonomy Optional taxonomy override.
 *
 * @return array<int>
 */
function a8csp_kb_docs_get_expanded_term_ids( ?string $taxonomy = null ): array {
	$taxonomy = null !== $taxonomy && '' !== $taxonomy
		? $taxonomy
		: a8csp_kb_docs_get_taxonomy();

	$current_term = a8csp_kb_docs_get_current_term();
	if ( ! ( $current_term instanceof WP_Term ) ) {
		return array();
	}

	if ( $current_term->taxonomy !== $taxonomy ) {
		return array();
	}

	$expanded_ids = array( (int) $current_term->term_id );
	$walker       = $current_term;

	while ( (int) $walker->parent > 0 ) {
		$parent_term = get_term( (int) $walker->parent, $taxonomy );
		if ( ! ( $parent_term instanceof WP_Term ) ) {
			break;
		}

		$expanded_ids[] = (int) $parent_term->term_id;
		$walker         = $parent_term;
	}

	return array_values( array_unique( $expanded_ids ) );
}

/**
 * Get docs articles for a term.
 *
 * @param int         $term_id          Term ID.
 * @param bool        $include_children Include descendant terms.
 * @param string      $orderby          Post field to order by.
 * @param string      $order            Sort direction.
 * @param string|null $post_type        Optional post type override.
 * @param string|null $taxonomy         Optional taxonomy override.
 *
 * @return array<WP_Post>
 */
function a8csp_kb_docs_get_articles_for_term(
	int $term_id,
	bool $include_children,
	string $orderby = 'menu_order',
	string $order = 'ASC',
	?string $post_type = null,
	?string $taxonomy = null
): array {
	$post_type = null !== $post_type && '' !== $post_type
		? $post_type
		: a8csp_kb_docs_get_post_type();
	$taxonomy  = null !== $taxonomy && '' !== $taxonomy
		? $taxonomy
		: a8csp_kb_docs_get_taxonomy();

	$allowed_orderby = array( 'menu_order', 'title', 'date', 'ID' );
	$orderby         = in_array( $orderby, $allowed_orderby, true ) ? $orderby : 'menu_order';
	$order           = 'DESC' === strtoupper( $order ) ? 'DESC' : 'ASC';

	if ( ! post_type_exists( $post_type ) || ! taxonomy_exists( $taxonomy ) || $term_id < 1 ) {
		return array();
	}

	$query_orderby = 'menu_order' === $orderby
		? array(
			'menu_order' => $order,
			'title'      => 'ASC',
		)
		: $orderby;

	$posts = get_posts(
		array(
			'post_type'              => $post_type,
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'orderby'                => $query_orderby,
			'order'                  => 'menu_order' === $orderby ? 'ASC' : $order,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'tax_query'              => array(
				array(
					'taxonomy'         => $taxonomy,
					'field'            => 'term_id',
					'terms'            => array( $term_id ),
					'include_children' => $include_children,
				),
			),
		)
	);

	if ( ! is_array( $posts ) || count( $posts ) < 1 ) {
		return array();
	}

	return array_values(
		array_filter(
			$posts,
			static function ( $post ): bool {
				return $post instanceof WP_Post;
			}
		)
	);
}

/**
 * Get the docs landing page object in docs context.
 *
 * @return WP_Post|null
 */
function a8csp_kb_docs_get_docs_page(): ?WP_Post {
	$base_path = a8csp_kb_docs_get_base_path();
	if ( ! is_page( $base_path ) ) {
		return null;
	}

	$docs_page = get_queried_object();
	return $docs_page instanceof WP_Post ? $docs_page : null;
}

/**
 * Chevron icon markup for expandable sidebar terms.
 *
 * @return string
 */
function a8csp_kb_docs_get_sidebar_chevron_markup(): string {
	return '<span class="a8csp-kbdocs-sidebar-chevron" aria-hidden="true">'
		. '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" focusable="false">'
		. '<path d="M6 3.5L10.5 8L6 12.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>'
		. '</svg>'
		. '</span>';
}

/**
 * Render a sidebar navigation term branch (recursive).
 *
 * @param WP_Term     $term              Term to render.
 * @param array<int>  $expanded_term_ids Term IDs that should start expanded.
 * @param int         $current_post_id   Current article ID, or 0.
 * @param int|null    $current_term_id   Current term ID, or null.
 * @param bool        $include_children  Whether nested child terms are included.
 * @param string      $section_order_by  Term orderby.
 * @param string      $section_order     Term order.
 * @param string      $article_order_by  Article orderby.
 * @param string      $article_order     Article order.
 * @param string      $post_type         Post type slug.
 * @param string      $taxonomy          Taxonomy slug.
 * @param string      $link_directives   Interactivity link directives.
 *
 * @return string
 */
function a8csp_kb_docs_render_sidebar_term_branch(
	WP_Term $term,
	array $expanded_term_ids,
	int $current_post_id,
	?int $current_term_id,
	bool $include_children,
	string $section_order_by,
	string $section_order,
	string $article_order_by,
	string $article_order,
	string $post_type,
	string $taxonomy,
	string $link_directives
): string {
	$term_id = (int) $term->term_id;

	$child_terms = $include_children
		? a8csp_kb_docs_get_child_terms( $term_id, $taxonomy, $section_order_by, $section_order )
		: array();

	$direct_articles = a8csp_kb_docs_get_articles_for_term(
		$term_id,
		false,
		$article_order_by,
		$article_order,
		$post_type,
		$taxonomy
	);

	$has_children = count( $child_terms ) > 0 || count( $direct_articles ) > 0;
	$is_expanded  = in_array( $term_id, $expanded_term_ids, true );
	$is_active    = null !== $current_term_id && $term_id === $current_term_id;

	$li_classes = array( 'a8csp-kbdocs-sidebar-term' );
	if ( $has_children ) {
		$li_classes[] = 'has-children';
	}
	if ( $is_expanded ) {
		$li_classes[] = 'is-expanded';
	}

	$context = wp_json_encode(
		array(
			'termId' => $term_id,
		),
		JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
	);

	$markup  = '<li class="' . esc_attr( implode( ' ', $li_classes ) ) . '"';
	$markup .= ' data-wp-context="' . esc_attr( (string) $context ) . '"';
	$markup .= ' data-wp-class--is-expanded="state.isTermExpanded">';

	$term_link_classes = array( 'a8csp-kbdocs-sidebar-term-link' );
	if ( $is_active ) {
		$term_link_classes[] = 'is-active';
	}

	$term_link_class_attr = esc_attr( implode( ' ', $term_link_classes ) );

	if ( $has_children ) {
		// Expandable terms toggle only — category pages open via server/IAPI state.
		$markup .= '<button type="button" class="' . $term_link_class_attr . '"';
		$markup .= ' data-wp-on--click="actions.toggleTerm"';
		$markup .= ' data-wp-bind--aria-expanded="state.isTermExpanded"';
		$markup .= ' aria-expanded="' . ( $is_expanded ? 'true' : 'false' ) . '">';
		$markup .= '<span class="a8csp-kbdocs-sidebar-term-label">' . esc_html( $term->name ) . '</span>';
		$markup .= a8csp_kb_docs_get_sidebar_chevron_markup();
		$markup .= '</button>';
	} else {
		$markup .= '<a href="' . esc_url( get_term_link( $term ) ) . '"';
		$markup .= ' class="' . $term_link_class_attr . '"';
		$markup .= ' data-wp-on--mouseenter="actions.prefetch"';
		$markup .= ' data-wp-on--click="actions.navigate">';
		$markup .= '<span class="a8csp-kbdocs-sidebar-term-label">' . esc_html( $term->name ) . '</span>';
		$markup .= '</a>';
	}

	if ( $has_children ) {
		$markup .= '<ul class="a8csp-kbdocs-sidebar-children">';

		foreach ( $child_terms as $child_term ) {
			if ( ! ( $child_term instanceof WP_Term ) ) {
				continue;
			}

			$markup .= a8csp_kb_docs_render_sidebar_term_branch(
				$child_term,
				$expanded_term_ids,
				$current_post_id,
				$current_term_id,
				$include_children,
				$section_order_by,
				$section_order,
				$article_order_by,
				$article_order,
				$post_type,
				$taxonomy,
				$link_directives
			);
		}

		foreach ( $direct_articles as $article ) {
			if ( ! ( $article instanceof WP_Post ) ) {
				continue;
			}

			$is_active_article = $current_post_id > 0 &&
				$current_post_id === (int) $article->ID;
			$article_class     = $is_active_article ? ' class="is-active"' : '';

			$markup .= '<li class="a8csp-kbdocs-sidebar-article">';
			$markup .= '<a href="' . esc_url( get_permalink( $article ) ) . '"';
			$markup .= $link_directives . $article_class . '>';
			$markup .= esc_html( get_the_title( $article ) );
			$markup .= '</a>';
			$markup .= '</li>';
		}

		$markup .= '</ul>';
	}

	$markup .= '</li>';

	return $markup;
}
