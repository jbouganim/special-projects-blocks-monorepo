<?php
/**
 * All Guides page helpers, caching, and server-side rendering.
 *
 * @package wpcomsp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the All Guides page slug (child of the docs home page).
 *
 * @return string
 */
function a8csp_kb_docs_get_all_guides_page_slug(): string {
	/**
	 * Filters the All Guides page slug under the docs base path.
	 *
	 * @param string $slug Page slug.
	 */
	return (string) apply_filters( 'a8csp_kb_docs_all_guides_page_slug', 'all-guides' );
}

/**
 * Get the All Guides page path relative to the site root.
 *
 * @return string Path without leading or trailing slashes.
 */
function a8csp_kb_docs_get_all_guides_page_path(): string {
	return trailingslashit( a8csp_kb_docs_get_base_path() ) . a8csp_kb_docs_get_all_guides_page_slug();
}

/**
 * Whether the current request is the All Guides docs page.
 *
 * @return bool
 */
function a8csp_kb_docs_is_all_guides_page(): bool {
	if ( ! is_page() ) {
		return false;
	}

	$page = get_queried_object();
	if ( ! ( $page instanceof WP_Post ) ) {
		return false;
	}

	$page_path = get_page_uri( $page );

	return a8csp_kb_docs_get_all_guides_page_path() === $page_path
		|| a8csp_kb_docs_get_all_guides_page_slug() === $page->post_name;
}

/**
 * Transient cache key for All Guides section data.
 *
 * @return string
 */
function a8csp_kb_docs_get_all_guides_cache_key(): string {
	return 'a8csp_kb_docs_all_guides_v2_' . md5(
		a8csp_kb_docs_get_post_type() . '|' . a8csp_kb_docs_get_taxonomy()
	);
}

/**
 * Flush cached All Guides section data.
 *
 * @return void
 */
function a8csp_kb_docs_flush_all_guides_cache(): void {
	delete_transient( a8csp_kb_docs_get_all_guides_cache_key() );
}

/**
 * Get a trimmed excerpt for All Guides archive cards.
 *
 * @param WP_Post $post Article post object.
 * @return string
 */
function a8csp_kb_docs_get_all_guides_article_excerpt( WP_Post $post ): string {
	$excerpt = get_the_excerpt( $post );
	if ( ! is_string( $excerpt ) || '' === $excerpt ) {
		return '';
	}

	return wp_trim_words( $excerpt, 15 );
}

/**
 * Get the time-to-read label for an All Guides archive card.
 *
 * @param WP_Post $post Article post object.
 * @return string
 */
function a8csp_kb_docs_get_all_guides_time_to_read_label( WP_Post $post ): string {
	if ( ! function_exists( 'block_core_post_time_to_read_word_count' ) ) {
		return '';
	}

	$content = get_post_field( 'post_content', $post->ID );
	if ( ! is_string( $content ) || '' === $content ) {
		return '';
	}

	$word_count_type      = wp_get_word_count_type();
	$total_words          = block_core_post_time_to_read_word_count( $content, $word_count_type );
	$average_reading_rate = 189;
	$minutes              = max( 1, (int) round( $total_words / $average_reading_rate ) );

	/* translators: %d: number of minutes. */
	return sprintf( _n( '%d minute', '%d minutes', $minutes, 'kb-docs' ), $minutes );
}

/**
 * Build one cached article payload for All Guides cards.
 *
 * @param WP_Post $post Article post object.
 * @return array<string, string>|null
 */
function a8csp_kb_docs_build_all_guides_article_cache_item( WP_Post $post ): ?array {
	$permalink = get_permalink( $post );
	if ( ! is_string( $permalink ) || '' === $permalink ) {
		return null;
	}

	$post_classes = get_post_class( 'wp-block-post', $post );

	return array(
		'title'        => get_the_title( $post ),
		'permalink'    => $permalink,
		'excerpt'      => a8csp_kb_docs_get_all_guides_article_excerpt( $post ),
		'time_to_read' => a8csp_kb_docs_get_all_guides_time_to_read_label( $post ),
		'post_class'   => implode( ' ', $post_classes ),
	);
}

/**
 * Build All Guides section data from live queries.
 *
 * Caches the expensive query + excerpt/time-to-read work, not final HTML.
 * Markup generation from this payload is cheap on each request.
 *
 * @return array<int, array{name: string, description: string, articles: array<int, array<string, string>>}>
 */
function a8csp_kb_docs_build_all_guides_sections_data(): array {
	$taxonomy = a8csp_kb_docs_get_taxonomy();

	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'parent'     => 0,
			'hide_empty' => true,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);

	if ( is_wp_error( $terms ) || ! is_array( $terms ) || count( $terms ) < 1 ) {
		return array();
	}

	$sections = array();

	foreach ( $terms as $term ) {
		if ( ! ( $term instanceof WP_Term ) ) {
			continue;
		}

		$articles = a8csp_kb_docs_get_articles_for_term(
			(int) $term->term_id,
			true,
			'menu_order',
			'ASC'
		);

		if ( count( $articles ) < 1 ) {
			continue;
		}

		$description   = term_description( (int) $term->term_id, $taxonomy );
		$article_items = array();

		foreach ( $articles as $article ) {
			if ( ! ( $article instanceof WP_Post ) ) {
				continue;
			}

			$cache_item = a8csp_kb_docs_build_all_guides_article_cache_item( $article );
			if ( null === $cache_item ) {
				continue;
			}

			$article_items[] = $cache_item;
		}

		if ( count( $article_items ) < 1 ) {
			continue;
		}

		$sections[] = array(
			'name'        => $term->name,
			'description' => is_string( $description ) ? $description : '',
			'articles'    => $article_items,
		);
	}

	return $sections;
}

/**
 * Get cached All Guides section data.
 *
 * @param bool $force_refresh Skip the transient and rebuild section data.
 * @return array<int, array{name: string, description: string, articles: array<int, array<string, string>>}>
 */
function a8csp_kb_docs_get_all_guides_sections_data( bool $force_refresh = false ): array {
	$cache_key = a8csp_kb_docs_get_all_guides_cache_key();

	if ( ! $force_refresh ) {
		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
	}

	$sections = a8csp_kb_docs_build_all_guides_sections_data();

	/**
	 * Filters the transient TTL for All Guides section data.
	 *
	 * @param int $ttl Cache lifetime in seconds.
	 */
	$ttl = (int) apply_filters( 'a8csp_kb_docs_all_guides_cache_ttl', DAY_IN_SECONDS );
	if ( $ttl > 0 ) {
		set_transient( $cache_key, $sections, $ttl );
	}

	return $sections;
}

/**
 * Format the time-to-read label to match docs archive cards.
 *
 * @param string $label Raw time-to-read label.
 * @return string
 */
function a8csp_kb_docs_format_all_guides_time_to_read_label( string $label ): string {
	if ( '' === $label ) {
		return '';
	}

	$updated_label = preg_replace( '/\bminutes?\b/i', 'Min.', $label );

	return is_string( $updated_label ) ? $updated_label : $label;
}

/**
 * Render one All Guides article row from cached card data.
 *
 * Markup mirrors taxonomy-kb_cat archive cards rendered by core blocks.
 *
 * @param array<string, string> $article Cached article payload.
 * @return string
 */
function a8csp_kb_docs_render_all_guides_article_row( array $article ): string {
	$permalink = $article['permalink'] ?? '';
	$title     = $article['title'] ?? '';
	$excerpt   = $article['excerpt'] ?? '';

	if ( ! is_string( $permalink ) || '' === $permalink || ! is_string( $title ) || '' === $title ) {
		return '';
	}

	$time_to_read = is_string( $article['time_to_read'] ?? null )
		? a8csp_kb_docs_format_all_guides_time_to_read_label( $article['time_to_read'] )
		: '';
	$post_class_attr = is_string( $article['post_class'] ?? null ) && '' !== $article['post_class']
		? esc_attr( $article['post_class'] )
		: 'wp-block-post';

	$markup  = '<li class="' . $post_class_attr . '">';
	$markup .= '<div class="wp-block-columns are-vertically-aligned-center is-layout-flex wp-block-columns-is-layout-flex">';
	$markup .= '<div class="wp-block-column is-vertically-aligned-center is-layout-flow wp-block-column-is-layout-flow">';
	$markup .= '<div class="wp-block-group wp-block-group-all-guides-article-row is-style-default is-vertical is-layout-flex wp-block-group-is-layout-flex">';
	$markup .= '<h3 class="has-text-align-left wp-block-post-title has-medium-font-size">';
	$markup .= '<a href="' . esc_url( $permalink ) . '" target="_self">';
	$markup .= esc_html( $title );
	$markup .= '</a></h3>';

	if ( is_string( $excerpt ) && '' !== $excerpt ) {
		$markup .= '<div class="has-link-color wp-block-post-excerpt has-text-color has-base-3-color has-tiny-font-size">';
		$markup .= '<p class="wp-block-post-excerpt__excerpt">' . esc_html( $excerpt ) . '</p>';
		$markup .= '</div>';
	}

	$markup .= '</div>';
	$markup .= '</div>';
	$markup .= '<div class="wp-block-column is-vertically-aligned-center is-layout-flow wp-block-column-is-layout-flow">';
	$markup .= '<div class="wp-block-group has-accent-2-color has-text-color has-link-color';
	$markup .= ' is-content-justification-right is-layout-flex wp-block-group-is-layout-flex">';

	if ( '' !== $time_to_read ) {
		$markup .= '<div class="wp-block-post-time-to-read">' . esc_html( $time_to_read ) . '</div>';
	}

	$markup .= '<p class="wp-block-paragraph">READ</p>';
	$markup .= '</div>';
	$markup .= '</div>';
	$markup .= '</div>';
	$markup .= '</li>';

	return $markup;
}

/**
 * Render All Guides sections for top-level docs categories and their articles.
 *
 * Mirrors page-all-guides.html markup without nested Terms Query / Query Loop blocks,
 * which can exceed PHP max execution time on large doc sets.
 *
 * @return string
 */
function a8csp_kb_docs_get_all_guides_sections_markup(): string {
	$sections = a8csp_kb_docs_get_all_guides_sections_data();

	if ( count( $sections ) < 1 ) {
		return '<div class="wp-block-terms-query"></div>';
	}

	$markup = '<div class="wp-block-terms-query">';

	foreach ( $sections as $section ) {
		$name     = is_string( $section['name'] ?? null ) ? $section['name'] : '';
		$articles = is_array( $section['articles'] ?? null ) ? $section['articles'] : array();

		if ( '' === $name || count( $articles ) < 1 ) {
			continue;
		}

		$description = is_string( $section['description'] ?? null ) ? $section['description'] : '';

		$markup .= '<div class="wp-block-group kb-docs-all-guides-section is-layout-flow wp-block-group-is-layout-flow">';
		$markup .= '<h2 class="wp-block-term-name has-large-font-size">';
		$markup .= esc_html( $name );
		$markup .= '</h2>';

		if ( '' !== $description ) {
			$markup .= '<div class="wp-block-term-description">' . wp_kses_post( $description ) . '</div>';
		}

		$markup .= '<div class="wp-block-query kb-docs-all-guides-articles">';
		$markup .= '<div class="wp-block-group has-border-color">';
		$markup .= '<ul class="kb-docs-archive-list wp-block-post-template is-layout-flow wp-block-post-template-is-layout-flow">';

		foreach ( $articles as $article ) {
			if ( ! is_array( $article ) ) {
				continue;
			}

			$markup .= a8csp_kb_docs_render_all_guides_article_row( $article );
		}

		$markup .= '</ul>';
		$markup .= '</div>';
		$markup .= '</div>';
		$markup .= '</div>';
	}

	$markup .= '</div>';

	return $markup;
}

/**
 * Short-circuit Terms Query rendering on the All Guides page.
 *
 * Nested Terms Query + Query Loop blocks re-render global styles for every card,
 * which can exceed PHP max execution time on large doc libraries.
 *
 * @param string|null $pre_render   Short-circuit return value.
 * @param array       $parsed_block Parsed block data.
 * @return string|null
 */
function a8csp_kb_docs_pre_render_all_guides_terms_query( $pre_render, array $parsed_block ) {
	if ( null !== $pre_render ) {
		return $pre_render;
	}

	if ( ! a8csp_kb_docs_is_all_guides_page() ) {
		return null;
	}

	if ( ! isset( $parsed_block['blockName'] ) || 'core/terms-query' !== $parsed_block['blockName'] ) {
		return null;
	}

	return a8csp_kb_docs_get_all_guides_sections_markup();
}
add_filter( 'pre_render_block', 'a8csp_kb_docs_pre_render_all_guides_terms_query', 10, 2 );

/**
 * Full per-page limit used when rendering All Guides on the front end.
 *
 * @return int
 */
function a8csp_kb_docs_get_all_guides_render_limit(): int {
	/**
	 * Filters the All Guides front-end per-page limit.
	 *
	 * @param int $limit Maximum terms/articles rendered per query.
	 */
	return (int) apply_filters( 'a8csp_kb_docs_all_guides_render_limit', 100 );
}

/**
 * Restore full query limits when rendering All Guides on the front end.
 *
 * The page-all-guides.html template deliberately stores small `perPage` values.
 * The editor
 * renders every term's inner Query Loop in full and cannot scope those loops to
 * their term (core/query does not consume `termId` context), so a complete
 * preview is both slow and inaccurate; the small values keep it to a couple of
 * representative sections.
 *
 * The front end normally never reads them — the whole Terms Query is
 * short-circuited by a8csp_kb_docs_pre_render_all_guides_terms_query(). This
 * filter is the safety net: if that short-circuit is ever bypassed, the page
 * falls back to core block rendering with the real limits instead of silently
 * publishing two categories.
 *
 * @param array<string, mixed> $parsed_block Parsed block data.
 * @return array<string, mixed>
 */
function a8csp_kb_docs_restore_all_guides_render_limits( array $parsed_block ): array {
	if ( is_admin() || ! a8csp_kb_docs_is_all_guides_page() ) {
		return $parsed_block;
	}

	$block_name = isset( $parsed_block['blockName'] ) && is_string( $parsed_block['blockName'] )
		? $parsed_block['blockName']
		: '';

	if ( 'core/terms-query' === $block_name && isset( $parsed_block['attrs']['termQuery'] ) && is_array( $parsed_block['attrs']['termQuery'] ) ) {
		$parsed_block['attrs']['termQuery']['perPage'] = a8csp_kb_docs_get_all_guides_render_limit();

		return $parsed_block;
	}

	if ( 'core/query' !== $block_name || ! isset( $parsed_block['attrs']['query'] ) || ! is_array( $parsed_block['attrs']['query'] ) ) {
		return $parsed_block;
	}

	$class_name = isset( $parsed_block['attrs']['className'] ) && is_string( $parsed_block['attrs']['className'] )
		? $parsed_block['attrs']['className']
		: '';
	$namespace  = isset( $parsed_block['attrs']['namespace'] ) && is_string( $parsed_block['attrs']['namespace'] )
		? $parsed_block['attrs']['namespace']
		: '';

	if ( str_contains( $class_name, 'kb-docs-all-guides-articles' ) || 'kb-docs-all-guides-articles' === $namespace ) {
		$parsed_block['attrs']['query']['perPage'] = a8csp_kb_docs_get_all_guides_render_limit();
	}

	return $parsed_block;
}
add_filter( 'render_block_data', 'a8csp_kb_docs_restore_all_guides_render_limits' );

/**
 * Scope Query Loop blocks inside the All Guides Term Template to the current term.
 *
 * Kept for editor / fallback rendering when the PHP short-circuit is not used.
 *
 * @param array<string, mixed> $query Array of WP_Query args from the Query block.
 * @param WP_Block             $block Block instance.
 * @param int                  $page  Current query page number.
 * @return array<string, mixed>
 */
function a8csp_kb_docs_all_guides_term_template_query_vars( array $query, WP_Block $block, int $page ): array {
	unset( $page );

	if ( ! a8csp_kb_docs_is_all_guides_page() ) {
		return $query;
	}

	$class_name = '';
	if ( isset( $block->attributes['className'] ) && is_string( $block->attributes['className'] ) ) {
		$class_name = $block->attributes['className'];
	}

	$namespace = '';
	if ( isset( $block->attributes['namespace'] ) && is_string( $block->attributes['namespace'] ) ) {
		$namespace = $block->attributes['namespace'];
	}

	$is_all_guides_articles = str_contains( $class_name, 'kb-docs-all-guides-articles' )
		|| 'kb-docs-all-guides-articles' === $namespace;

	if ( ! $is_all_guides_articles ) {
		return $query;
	}

	$term_id  = isset( $block->context['termId'] ) ? (int) $block->context['termId'] : 0;
	$taxonomy = isset( $block->context['taxonomy'] ) && is_string( $block->context['taxonomy'] )
		? sanitize_key( $block->context['taxonomy'] )
		: '';

	if ( $term_id < 1 || a8csp_kb_docs_get_taxonomy() !== $taxonomy ) {
		return $query;
	}

	$query['post_type']              = a8csp_kb_docs_get_post_type();
	$query['post_status']            = 'publish';
	$query['ignore_sticky_posts']    = true;
	$query['no_found_rows']          = true;
	$query['update_post_meta_cache'] = false;
	$query['update_post_term_cache'] = false;
	$query['tax_query']              = array(
		array(
			'taxonomy'         => $taxonomy,
			'field'            => 'term_id',
			'terms'            => array( $term_id ),
			'include_children' => true,
		),
	);

	return $query;
}
add_filter( 'query_loop_block_query_vars', 'a8csp_kb_docs_all_guides_term_template_query_vars', 5, 3 );

/**
 * Flush All Guides cache when a docs article changes.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function a8csp_kb_docs_flush_all_guides_cache_on_article_save( int $post_id ): void {
	if ( $post_id < 1 ) {
		return;
	}

	if ( a8csp_kb_docs_get_post_type() !== get_post_type( $post_id ) ) {
		return;
	}

	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}

	a8csp_kb_docs_flush_all_guides_cache();
}
add_action( 'save_post', 'a8csp_kb_docs_flush_all_guides_cache_on_article_save' );

/**
 * Flush All Guides cache when docs category terms change.
 *
 * @param int    $term_id  Term ID.
 * @param int    $tt_id    Term taxonomy ID.
 * @param string $taxonomy Taxonomy slug.
 * @return void
 */
function a8csp_kb_docs_flush_all_guides_cache_on_term_change( int $term_id, int $tt_id, string $taxonomy ): void {
	unset( $term_id, $tt_id );

	if ( a8csp_kb_docs_get_taxonomy() !== $taxonomy ) {
		return;
	}

	a8csp_kb_docs_flush_all_guides_cache();
}
add_action( 'created_term', 'a8csp_kb_docs_flush_all_guides_cache_on_term_change', 10, 3 );
add_action( 'edited_term', 'a8csp_kb_docs_flush_all_guides_cache_on_term_change', 10, 3 );
add_action( 'delete_term', 'a8csp_kb_docs_flush_all_guides_cache_on_term_change', 10, 3 );

/**
 * Flush All Guides cache when article/category assignments change.
 *
 * @param int    $object_id Object ID.
 * @param array  $terms     An array of object term IDs or slugs.
 * @param array  $tt_ids    An array of term taxonomy IDs.
 * @param string $taxonomy  Taxonomy slug.
 * @return void
 */
function a8csp_kb_docs_flush_all_guides_cache_on_term_assignment(
	int $object_id,
	array $terms,
	array $tt_ids,
	string $taxonomy
): void {
	unset( $terms, $tt_ids );

	if ( a8csp_kb_docs_get_taxonomy() !== $taxonomy ) {
		return;
	}

	if ( a8csp_kb_docs_get_post_type() !== get_post_type( $object_id ) ) {
		return;
	}

	a8csp_kb_docs_flush_all_guides_cache();
}
add_action( 'set_object_terms', 'a8csp_kb_docs_flush_all_guides_cache_on_term_assignment', 10, 4 );
