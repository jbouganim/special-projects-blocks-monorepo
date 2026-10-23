<?php
/**
 * Cached docs sidebar tree.
 *
 * The sidebar lists every docs category and article. Building it walks every
 * term and runs one article query per term (hundreds of queries on a large
 * library), and the result is identical on every docs page — only the
 * active/expanded state differs. The tree data is cached here; per-request
 * state is applied when rendering.
 *
 * @package wpcomsp
 */

/**
 * Option holding the sidebar cache generation. Bumping it invalidates every
 * cached tree variant (different block attributes produce different keys).
 *
 * @return string
 */
function a8csp_kb_docs_get_sidebar_cache_generation_option(): string {
	return 'a8csp_kb_docs_sidebar_cache_gen';
}

/**
 * Transient key for one sidebar tree variant.
 *
 * @param array<string, mixed> $args Normalized tree arguments.
 * @return string
 */
function a8csp_kb_docs_get_sidebar_cache_key( array $args ): string {
	$generation = (string) get_option( a8csp_kb_docs_get_sidebar_cache_generation_option(), '0' );

	return 'a8csp_kb_docs_sidebar_v1_' . md5( wp_json_encode( $args ) . '|' . $generation );
}

/**
 * Invalidate all cached sidebar trees.
 *
 * @return void
 */
function a8csp_kb_docs_flush_sidebar_cache(): void {
	update_option( a8csp_kb_docs_get_sidebar_cache_generation_option(), (string) microtime( true ), true );
}

/**
 * Build one cached tree node for a term (recursive).
 *
 * Order matches the previous uncached render: child terms first, then the
 * term's direct articles.
 *
 * @param WP_Term              $term Term to build.
 * @param array<string, mixed> $args Normalized tree arguments.
 * @return array<string, mixed>
 */
function a8csp_kb_docs_build_sidebar_tree_node( WP_Term $term, array $args ): array {
	$term_id = (int) $term->term_id;

	$child_terms = $args['include_children']
		? a8csp_kb_docs_get_child_terms( $term_id, $args['taxonomy'], $args['section_order_by'], $args['section_order'] )
		: array();

	$children = array();
	foreach ( $child_terms as $child_term ) {
		if ( $child_term instanceof WP_Term ) {
			$children[] = a8csp_kb_docs_build_sidebar_tree_node( $child_term, $args );
		}
	}

	$articles = array();
	foreach ( a8csp_kb_docs_get_articles_for_term(
		$term_id,
		false,
		$args['article_order_by'],
		$args['article_order'],
		$args['post_type'],
		$args['taxonomy']
	) as $article ) {
		if ( $article instanceof WP_Post ) {
			$articles[] = array(
				'id'    => (int) $article->ID,
				'title' => get_the_title( $article ),
				'link'  => (string) get_permalink( $article ),
			);
		}
	}

	$link = '';
	if ( count( $children ) < 1 && count( $articles ) < 1 ) {
		$term_link = get_term_link( $term );
		$link      = is_string( $term_link ) ? $term_link : '';
	}

	return array(
		'id'       => $term_id,
		'name'     => $term->name,
		'link'     => $link,
		'children' => $children,
		'articles' => $articles,
	);
}

/**
 * Get the docs sidebar tree (cached).
 *
 * @param string $taxonomy         Taxonomy slug.
 * @param string $post_type        Post type slug.
 * @param bool   $include_children Whether nested child terms are included.
 * @param string $section_order_by Term orderby.
 * @param string $section_order    Term order.
 * @param string $article_order_by Article orderby.
 * @param string $article_order    Article order.
 * @return array<int, array<string, mixed>> Top-level term nodes.
 */
function a8csp_kb_docs_get_sidebar_tree(
	string $taxonomy,
	string $post_type,
	bool $include_children,
	string $section_order_by,
	string $section_order,
	string $article_order_by,
	string $article_order
): array {
	static $request_cache = array();

	$args = array(
		'taxonomy'         => $taxonomy,
		'post_type'        => $post_type,
		'include_children' => $include_children,
		'section_order_by' => $section_order_by,
		'section_order'    => $section_order,
		'article_order_by' => $article_order_by,
		'article_order'    => $article_order,
		// Term ordering plugins can apply only on taxonomy archives (e.g. Simple
		// Content Order's "main query" pins), so archives get their own variant.
		'is_tax_archive'   => is_tax( $taxonomy ),
	);

	$cache_key = a8csp_kb_docs_get_sidebar_cache_key( $args );

	if ( isset( $request_cache[ $cache_key ] ) ) {
		return $request_cache[ $cache_key ];
	}

	$cached = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		$request_cache[ $cache_key ] = $cached;
		return $cached;
	}

	$tree = array();
	foreach ( a8csp_kb_docs_get_top_level_terms( $taxonomy, $section_order_by, $section_order ) as $section ) {
		if ( $section instanceof WP_Term ) {
			$tree[] = a8csp_kb_docs_build_sidebar_tree_node( $section, $args );
		}
	}

	/**
	 * Filters the transient TTL for the cached docs sidebar tree.
	 *
	 * @param int $ttl TTL in seconds.
	 */
	$ttl = (int) apply_filters( 'a8csp_kb_docs_sidebar_cache_ttl', DAY_IN_SECONDS );
	if ( $ttl > 0 ) {
		set_transient( $cache_key, $tree, $ttl );
	}

	$request_cache[ $cache_key ] = $tree;

	return $tree;
}

/**
 * Flush the sidebar cache when a docs article changes (incl. trash/status).
 *
 * @param int $post_id Post ID.
 * @return void
 */
function a8csp_kb_docs_flush_sidebar_cache_on_post( int $post_id ): void {
	if ( $post_id < 1 || a8csp_kb_docs_get_post_type() !== get_post_type( $post_id ) ) {
		return;
	}

	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}

	a8csp_kb_docs_flush_sidebar_cache();
}
add_action( 'save_post', 'a8csp_kb_docs_flush_sidebar_cache_on_post' );
add_action( 'before_delete_post', 'a8csp_kb_docs_flush_sidebar_cache_on_post' );

/**
 * Flush the sidebar cache when docs categories change.
 *
 * @param int    $term_id  Term ID.
 * @param int    $tt_id    Term taxonomy ID.
 * @param string $taxonomy Taxonomy slug.
 * @return void
 */
function a8csp_kb_docs_flush_sidebar_cache_on_term( int $term_id, int $tt_id, string $taxonomy ): void {
	unset( $term_id, $tt_id );

	if ( a8csp_kb_docs_get_taxonomy() === $taxonomy ) {
		a8csp_kb_docs_flush_sidebar_cache();
	}
}
add_action( 'created_term', 'a8csp_kb_docs_flush_sidebar_cache_on_term', 10, 3 );
add_action( 'edited_term', 'a8csp_kb_docs_flush_sidebar_cache_on_term', 10, 3 );
add_action( 'delete_term', 'a8csp_kb_docs_flush_sidebar_cache_on_term', 10, 3 );

/**
 * Flush the sidebar cache when articles move between categories.
 *
 * @param int    $object_id Object ID.
 * @param array  $terms     Object term IDs or slugs.
 * @param array  $tt_ids    Term taxonomy IDs.
 * @param string $taxonomy  Taxonomy slug.
 * @return void
 */
function a8csp_kb_docs_flush_sidebar_cache_on_object_terms( int $object_id, array $terms, array $tt_ids, string $taxonomy ): void {
	unset( $terms, $tt_ids );

	if ( a8csp_kb_docs_get_taxonomy() === $taxonomy && a8csp_kb_docs_get_post_type() === get_post_type( $object_id ) ) {
		a8csp_kb_docs_flush_sidebar_cache();
	}
}
add_action( 'set_object_terms', 'a8csp_kb_docs_flush_sidebar_cache_on_object_terms', 10, 4 );

/**
 * Flush the sidebar cache when docs category meta changes — sites can order
 * categories by term meta (e.g. a navigation order field).
 *
 * @param mixed $meta_id Meta ID(s).
 * @param int   $term_id Term ID.
 * @return void
 */
function a8csp_kb_docs_flush_sidebar_cache_on_term_meta( $meta_id, int $term_id ): void {
	unset( $meta_id );

	$term = get_term( $term_id );
	if ( $term instanceof WP_Term && a8csp_kb_docs_get_taxonomy() === $term->taxonomy ) {
		a8csp_kb_docs_flush_sidebar_cache();
	}
}
add_action( 'added_term_meta', 'a8csp_kb_docs_flush_sidebar_cache_on_term_meta', 10, 2 );
add_action( 'updated_term_meta', 'a8csp_kb_docs_flush_sidebar_cache_on_term_meta', 10, 2 );
add_action( 'deleted_term_meta', 'a8csp_kb_docs_flush_sidebar_cache_on_term_meta', 10, 2 );
