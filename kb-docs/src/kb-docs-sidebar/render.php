<?php
/**
 *
 * Render callback for a8csp/kb-docs-sidebar.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block inner content / InnerBlocks HTML.
 * @var WP_Block $block      Block instance.
 * @package wpcomsp
 */

$block_attributes = isset( $block ) && $block instanceof WP_Block && is_array( $block->attributes )
	? $block->attributes
	: ( is_array( $attributes ) ? $attributes : array() );

$block_taxonomy = isset( $block_attributes['taxonomy'] ) && is_string( $block_attributes['taxonomy'] ) && '' !== $block_attributes['taxonomy']
	? sanitize_key( $block_attributes['taxonomy'] )
	: a8csp_kb_docs_get_taxonomy();

$block_post_type = isset( $block_attributes['postType'] ) && is_string( $block_attributes['postType'] ) && '' !== $block_attributes['postType']
	? sanitize_key( $block_attributes['postType'] )
	: a8csp_kb_docs_get_post_type();

$section_order_by = isset( $block_attributes['sectionOrderBy'] ) && is_string( $block_attributes['sectionOrderBy'] )
	? sanitize_key( $block_attributes['sectionOrderBy'] )
	: 'name';

$section_order = isset( $block_attributes['sectionOrder'] ) && is_string( $block_attributes['sectionOrder'] )
	? strtolower( $block_attributes['sectionOrder'] )
	: 'asc';

$article_order_by = isset( $block_attributes['articleOrderBy'] ) && is_string( $block_attributes['articleOrderBy'] )
	? sanitize_key( $block_attributes['articleOrderBy'] )
	: 'menu_order';

$article_order = isset( $block_attributes['articleOrder'] ) && is_string( $block_attributes['articleOrder'] )
	? strtolower( $block_attributes['articleOrder'] )
	: 'asc';

$include_children = isset( $block_attributes['includeChildren'] )
	? (bool) $block_attributes['includeChildren']
	: true;

$chrome_content = is_string( $content ) && '' !== $content
	? a8csp_kb_docs_add_link_directives_to_anchors( $content )
	: '';

$nav_markup = '';

wp_interactivity_state(
	'a8cKBDocs',
	array(
		'isMobileSidebarOpen' => false,
	)
);

if ( a8csp_kb_docs_is_docs_request() ) {
	$sections = a8csp_kb_docs_get_top_level_terms(
		$block_taxonomy,
		$section_order_by,
		$section_order
	);

	if ( count( $sections ) > 0 ) {
		$current_post_id = is_singular( $block_post_type ) ? get_queried_object_id() : 0;
		$current_term    = null;
		$expanded_ids    = array();

		if ( $block_taxonomy === a8csp_kb_docs_get_taxonomy() ) {
			$current_term = a8csp_kb_docs_get_current_term();
			$expanded_ids = a8csp_kb_docs_get_expanded_term_ids( $block_taxonomy );
		}

		$current_term_id = $current_term instanceof WP_Term
			? (int) $current_term->term_id
			: null;

		$expanded_state = array();
		foreach ( $expanded_ids as $expanded_id ) {
			$expanded_state[ (string) $expanded_id ] = true;
		}

		wp_interactivity_state(
			'a8cKBDocs',
			array(
				'expandedTermIds' => $expanded_state,
			)
		);

		$link_directives = a8csp_kb_docs_get_link_directives();

		$nav_context = wp_json_encode(
			array(
				'serverExpandedTermIds' => array_values( $expanded_ids ),
			),
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);

		$view_attributes = a8csp_kb_docs_get_view_attributes();
		$view_attr_html  = '';

		foreach ( $view_attributes as $attribute_name => $attribute_value ) {
			$view_attr_html .= sprintf(
				' %1$s="%2$s"',
				esc_attr( $attribute_name ),
				esc_attr( $attribute_value )
			);
		}

		$nav_markup  = '<nav class="a8csp-kbdocs-sidebar" data-wp-interactive="a8cKBDocs"';
		$nav_markup .= ' data-wp-context="' . esc_attr( (string) $nav_context ) . '"';
		$nav_markup .= ' data-wp-init="callbacks.syncExpandedFromServer"';
		$nav_markup .= ' data-wp-router-region="a8cKBDocs/sidebar" aria-label="' . esc_attr__( 'Docs navigation', 'kb-docs' ) . '"';
		$nav_markup .= $view_attr_html . '>';
		$nav_markup .= '<div class="a8csp-kbdocs-sidebar-inner">';
		$nav_markup .= '<ul class="a8csp-kbdocs-sidebar-sections">';

		foreach ( $sections as $section ) {
			if ( ! ( $section instanceof WP_Term ) ) {
				continue;
			}

			$nav_markup .= a8csp_kb_docs_render_sidebar_term_branch(
				$section,
				$expanded_ids,
				$current_post_id,
				$current_term_id,
				$include_children,
				$section_order_by,
				$section_order,
				$article_order_by,
				$article_order,
				$block_post_type,
				$block_taxonomy,
				$link_directives
			);
		}

		$nav_markup .= '</ul>';
		$nav_markup .= '</div>';
		$nav_markup .= '</nav>';
	}
}

$wrapper_extra = array(
	'class'                               => 'a8csp-kbdocs-sidebar-block',
	'data-wp-interactive'                 => 'a8cKBDocs',
	'data-wp-class--is-mobile-open'       => 'state.isMobileSidebarOpen',
	'data-wp-on-window--keydown'          => 'actions.handleMobileSidebarKeydown',
);

$block_spacing = isset( $block_attributes['style']['spacing'] ) && is_array( $block_attributes['style']['spacing'] )
	? $block_attributes['style']['spacing']
	: array();

$section_gap = isset( $block_spacing['blockGap'] )
	? a8csp_kb_docs_get_spacing_css_value( $block_spacing['blockGap'] )
	: '';

if ( '' !== $section_gap ) {
	$wrapper_extra['style'] = '--wp--style--block-gap: ' . $section_gap;
}

$wrapper_attributes = get_block_wrapper_attributes( $wrapper_extra );

$close_icon  = '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32" fill="none" aria-hidden="true" focusable="false">';
$close_icon .= '<path d="M8 8L24 24M24 8L8 24" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>';
$close_icon .= '</svg>';
?>
<aside <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- From get_block_wrapper_attributes(). ?>>
	<div
		class="a8csp-kbdocs-sidebar-backdrop"
		data-wp-bind--hidden="!state.isMobileSidebarOpen"
		data-wp-on--click="actions.closeMobileSidebar"
		hidden
	></div>

	<div
		class="a8csp-kbdocs-sidebar-panel"
		data-wp-class--is-open="state.isMobileSidebarOpen"
	>
		<div class="a8csp-kbdocs-sidebar-mobile-header wpdocs-sidebar-row">
			<p class="a8csp-kbdocs-sidebar-mobile-title">
				<?php echo esc_html__( 'Browse', 'kb-docs' ); ?>
			</p>
			<button
				type="button"
				class="a8csp-kbdocs-sidebar-close"
				data-wp-on--click="actions.closeMobileSidebar"
				aria-label="<?php echo esc_attr__( 'Close docs navigation', 'kb-docs' ); ?>"
			>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup.
				echo $close_icon;
				?>
			</button>
		</div>

		<?php if ( '' !== $chrome_content ) : ?>
			<div class="a8csp-kbdocs-sidebar-chrome">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner block content is escaped at render time.
				echo $chrome_content;
				?>
			</div>
		<?php endif; ?>

		<?php if ( '' !== $nav_markup ) : ?>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Nav HTML uses escaped values; IAPI attributes must be preserved.
			echo $nav_markup;
			?>
		<?php elseif ( a8csp_kb_docs_is_docs_request() ) : ?>
			<p class="a8csp-kbdocs-sidebar-empty">
				<?php echo esc_html__( 'No navigation sections found.', 'kb-docs' ); ?>
			</p>
		<?php endif; ?>
	</div>
</aside>
