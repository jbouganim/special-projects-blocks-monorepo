<?php
/**
 * Block registration filters for KB Docs.
 *
 * @package wpcomsp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Allow core query pagination block inside KB Docs Content block.
 *
 * Query Pagination uses `ancestor` restrictions in core, so we extend that
 * list to include `a8csp/kb-docs-content`.
 *
 * @param array  $args       Block type args.
 * @param string $block_name Block name.
 *
 * @return array
 */
function a8csp_kb_docs_allow_query_pagination_in_content( array $args, string $block_name ): array {
	if ( 'core/query-pagination' !== $block_name ) {
		return $args;
	}

	$ancestor_blocks = array( 'a8csp/kb-docs-content' );

	/**
	 * Filters allowed ancestor blocks for Query Pagination in KB Docs.
	 *
	 * @param array $ancestor_blocks Ancestor block names.
	 */
	$ancestor_blocks = apply_filters(
		'a8csp_kb_docs_query_pagination_ancestors',
		$ancestor_blocks
	);

	if ( ! is_array( $ancestor_blocks ) || count( $ancestor_blocks ) < 1 ) {
		return $args;
	}

	$existing_ancestors = array();
	if ( isset( $args['ancestor'] ) && is_array( $args['ancestor'] ) ) {
		$existing_ancestors = $args['ancestor'];
	}

	$args['ancestor'] = array_values(
		array_unique(
			array_merge( $existing_ancestors, $ancestor_blocks )
		)
	);

	return $args;
}
add_filter( 'register_block_type_args', 'a8csp_kb_docs_allow_query_pagination_in_content', 10, 2 );

/**
 * Get the docs home sections block pattern markup.
 *
 * @return string
 */
function a8csp_kb_docs_get_home_sections_pattern_content(): string {
	$taxonomy = a8csp_kb_docs_get_taxonomy();

	return '<!-- wp:terms-query {"termQuery":{"perPage":100,"taxonomy":"' . $taxonomy . '","order":"asc","orderBy":"name","hideEmpty":false,"showNested":false}} -->
<div class="wp-block-terms-query"><!-- wp:term-template -->
<!-- wp:term-name {"isLink":true,"level":3} /-->

<!-- wp:term-description /-->
<!-- /wp:term-template --></div>
<!-- /wp:terms-query -->';
}

/**
 * Register KB Docs block patterns.
 *
 * @return void
 */
function a8csp_kb_docs_register_block_patterns(): void {
	register_block_pattern_category(
		'a8csp-kb-docs',
		array(
			'label' => __( 'KB Docs', 'kb-docs' ),
		)
	);

	register_block_pattern(
		'a8csp/kb-docs-home-sections',
		array(
			'title'       => __( 'Docs Home Sections', 'kb-docs' ),
			'description' => __( 'Top-level documentation category sections.', 'kb-docs' ),
			'categories'  => array( 'a8csp-kb-docs' ),
			'blockTypes'  => array( 'a8csp/kb-docs-content' ),
			'content'     => a8csp_kb_docs_get_home_sections_pattern_content(),
		)
	);
}
add_action( 'init', 'a8csp_kb_docs_register_block_patterns' );
