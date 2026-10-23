<?php
/**
 * Render callback for a8csp/kb-docs-content.
 *
 * @package wpcomsp
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block inner content / InnerBlocks HTML.
 * @var WP_Block $block      Block instance.
 */

$use_interactivity = a8csp_kb_docs_content_use_interactivity();

if ( $use_interactivity ) {
	wp_interactivity_state(
		'a8cKBDocs',
		array(
			'clientNavigation' => a8csp_kb_docs_get_client_navigation_config(),
		)
	);
}

$view_attributes    = a8csp_kb_docs_get_view_attributes();
$wrapper_attributes = get_block_wrapper_attributes();
$inner_content      = $use_interactivity
	? a8csp_kb_docs_add_link_directives_to_anchors( $content )
	: $content;
?>
<section <?php echo wp_kses_post( $wrapper_attributes ); ?>>
	<?php if ( $use_interactivity ) : ?>
		<section
			id="a8csp-kbdocs-content"
			class="a8csp-kbdocs-content"
			tabindex="-1"
			data-wp-interactive="a8cKBDocs"
			data-wp-router-region="a8cKBDocs/content"
			<?php
			foreach ( $view_attributes as $attribute_name => $attribute_value ) {
				printf(
					' %1$s="%2$s"',
					esc_attr( $attribute_name ),
					esc_attr( $attribute_value )
				);
			}
			?>
		>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block inner content is escaped at render time.
			echo $inner_content;
			?>
		</section>
	<?php else : ?>
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block inner content is escaped at render time.
		echo $inner_content;
		?>
	<?php endif; ?>
</section>
