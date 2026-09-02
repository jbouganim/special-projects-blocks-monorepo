<?php
/**
 * Render callback for a8csp/kb-docs-breadcrumb.
 *
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

$home_label = isset( $block_attributes['homeLabel'] ) && is_string( $block_attributes['homeLabel'] ) && '' !== $block_attributes['homeLabel']
	? $block_attributes['homeLabel']
	: __( 'Docs', 'kb-docs' );

$separator = isset( $block_attributes['separator'] ) && is_string( $block_attributes['separator'] ) && '' !== $block_attributes['separator']
	? $block_attributes['separator']
	: '/';

$show_sidebar_button = ! isset( $block_attributes['showMobileSidebarButton'] )
	|| (bool) $block_attributes['showMobileSidebarButton'];

$collapse_on_mobile = ! isset( $block_attributes['collapseOnMobile'] )
	|| (bool) $block_attributes['collapseOnMobile'];

$wrapper_classes = 'a8csp-kbdocs-breadcrumb-block';

if ( $collapse_on_mobile ) {
	$wrapper_classes .= ' is-collapsed-on-mobile';
}

$docs_home_url   = home_url( trailingslashit( a8csp_kb_docs_get_base_path() ) );
$link_directives = a8csp_kb_docs_get_link_directives();
$items           = array();
$breadcrumb_markup = '';
$trigger_markup    = '';

if ( $show_sidebar_button && a8csp_kb_docs_is_docs_request() ) {
	$trigger_icon  = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true" focusable="false">';
	$trigger_icon .= '<circle cx="10" cy="10" r="1.5" fill="currentColor"/>';
	$trigger_icon .= '<circle cx="16" cy="10" r="1.5" fill="currentColor"/>';
	$trigger_icon .= '<circle cx="4" cy="10" r="1.5" fill="currentColor"/>';
	$trigger_icon .= '</svg>';

	$trigger_markup  = '<button type="button" class="a8csp-kbdocs-sidebar-trigger"';
	$trigger_markup .= ' data-wp-interactive="a8cKBDocs"';
	$trigger_markup .= ' data-wp-on--click="actions.openMobileSidebar"';
	$trigger_markup .= ' data-wp-bind--aria-expanded="state.isMobileSidebarOpen"';
	$trigger_markup .= ' aria-expanded="false"';
	$trigger_markup .= ' aria-label="' . esc_attr__( 'Open docs navigation', 'kb-docs' ) . '">';
	$trigger_markup .= $trigger_icon;
	$trigger_markup .= '</button>';
}

if ( a8csp_kb_docs_is_docs_request() ) {
	$items[] = sprintf(
		'<a class="a8csp-kbdocs-breadcrumb-link" href="%1$s"%2$s>%3$s</a>',
		esc_url( $docs_home_url ),
		$link_directives,
		esc_html( $home_label )
	);

	if ( a8csp_kb_docs_is_all_guides_page() ) {
		$page_id = get_queried_object_id();
		if ( $page_id > 0 ) {
			$items[] = sprintf(
				'<span class="a8csp-kbdocs-breadcrumb-current">%s</span>',
				esc_html( get_the_title( $page_id ) )
			);
		}
	} elseif ( is_tax( $block_taxonomy ) ) {
		$term = get_queried_object();

		if ( $term instanceof WP_Term && $term->taxonomy === $block_taxonomy ) {
			$ancestors = array_reverse( get_ancestors( (int) $term->term_id, $block_taxonomy, 'taxonomy' ) );

			foreach ( $ancestors as $ancestor_id ) {
				$ancestor = get_term( (int) $ancestor_id, $block_taxonomy );
				if ( ! ( $ancestor instanceof WP_Term ) ) {
					continue;
				}

				$items[] = sprintf(
					'<a class="a8csp-kbdocs-breadcrumb-link" href="%1$s"%2$s>%3$s</a>',
					esc_url( get_term_link( $ancestor ) ),
					$link_directives,
					esc_html( $ancestor->name )
				);
			}

			$items[] = sprintf(
				'<span class="a8csp-kbdocs-breadcrumb-current">%s</span>',
				esc_html( $term->name )
			);
		}
	} elseif ( is_singular( $block_post_type ) ) {
		$post_id = get_queried_object_id();
		$term    = $post_id > 0
			? a8csp_kb_docs_get_primary_term_for_post( $post_id, $block_taxonomy )
			: null;

		if ( $term instanceof WP_Term ) {
			$ancestors = array_reverse( get_ancestors( (int) $term->term_id, $block_taxonomy, 'taxonomy' ) );

			foreach ( $ancestors as $ancestor_id ) {
				$ancestor = get_term( (int) $ancestor_id, $block_taxonomy );
				if ( ! ( $ancestor instanceof WP_Term ) ) {
					continue;
				}

				$items[] = sprintf(
					'<a class="a8csp-kbdocs-breadcrumb-link" href="%1$s"%2$s>%3$s</a>',
					esc_url( get_term_link( $ancestor ) ),
					$link_directives,
					esc_html( $ancestor->name )
				);
			}

			$items[] = sprintf(
				'<a class="a8csp-kbdocs-breadcrumb-link" href="%1$s"%2$s>%3$s</a>',
				esc_url( get_term_link( $term ) ),
				$link_directives,
				esc_html( $term->name )
			);
		}

		if ( $post_id > 0 ) {
			$items[] = sprintf(
				'<span class="a8csp-kbdocs-breadcrumb-current">%s</span>',
				esc_html( get_the_title( $post_id ) )
			);
		}
	}

	if ( count( $items ) > 0 ) {
		$separator_markup = sprintf(
			'<span class="a8csp-kbdocs-breadcrumb-sep" aria-hidden="true">%s</span>',
			esc_html( $separator )
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

		$breadcrumb_markup = '<nav class="a8csp-kbdocs-breadcrumbs" aria-label="' .
			esc_attr__( 'Breadcrumb', 'kb-docs' ) .
			'" data-wp-interactive="a8cKBDocs" data-wp-router-region="a8cKBDocs/breadcrumb"' .
			$view_attr_html . '>' .
			implode( $separator_markup, $items ) .
			'</nav>';
	}
}

?>
<div <?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Wrapper attributes include IAPI directives.
echo get_block_wrapper_attributes(
	array(
		'class' => $wrapper_classes,
	)
);
?>>
	<?php if ( '' !== $breadcrumb_markup ) : ?>
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Breadcrumb HTML uses escaped values; IAPI attributes must be preserved.
		echo $breadcrumb_markup;
		?>
	<?php elseif ( a8csp_kb_docs_is_docs_request() ) : ?>
		<p class="a8csp-kbdocs-breadcrumb-empty">
			<?php echo esc_html__( 'No breadcrumb trail available.', 'kb-docs' ); ?>
		</p>
	<?php endif; ?>
	<?php if ( '' !== $trigger_markup ) : ?>
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static button markup with escaped values; IAPI attributes must be preserved.
		echo $trigger_markup;
		?>
	<?php endif; ?>
</div>
