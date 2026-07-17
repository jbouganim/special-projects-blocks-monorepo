<?php
/**
 * Render callback for a8csp/copy-to-markdown.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block inner content.
 * @var WP_Block $block      Block instance.
 * @package a8csp
 */

$block_attributes = isset( $block ) && $block instanceof WP_Block && is_array( $block->attributes )
	? $block->attributes
	: ( is_array( $attributes ) ? $attributes : array() );

$label = isset( $block_attributes['label'] ) && is_string( $block_attributes['label'] ) && '' !== $block_attributes['label']
	? $block_attributes['label']
	: __( 'Copy content', 'copy-to-markdown' );

$content_selector = isset( $block_attributes['contentSelector'] ) && is_string( $block_attributes['contentSelector'] ) && '' !== $block_attributes['contentSelector']
	? $block_attributes['contentSelector']
	: '.entry-content';

$copied_label = __( 'Copied', 'copy-to-markdown' );

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'                   => 'a8csp-copy-to-markdown',
		'type'                    => 'button',
		'data-content-selector'   => $content_selector,
		'data-label'              => wp_strip_all_tags( $label ),
		'data-copied-label'       => $copied_label,
		'aria-label'              => wp_strip_all_tags( $label ),
	)
);
?>
<button <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- From get_block_wrapper_attributes(). ?>>
	<span class="a8csp-copy-to-markdown__icons" aria-hidden="true">
		<span class="a8csp-copy-to-markdown__icon a8csp-copy-to-markdown__icon--copy">
			<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" focusable="false">
				<path d="M13.3333 6H7.33333C6.59695 6 6 6.59695 6 7.33333V13.3333C6 14.0697 6.59695 14.6667 7.33333 14.6667H13.3333C14.0697 14.6667 14.6667 14.0697 14.6667 13.3333V7.33333C14.6667 6.59695 14.0697 6 13.3333 6Z" stroke="currentColor" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/>
				<path d="M3.33398 10.0002H2.66732C2.3137 10.0002 1.97456 9.85969 1.72451 9.60964C1.47446 9.35959 1.33398 9.02045 1.33398 8.66683V2.66683C1.33398 2.31321 1.47446 1.97407 1.72451 1.72402C1.97456 1.47397 2.3137 1.3335 2.66732 1.3335H8.66732C9.02094 1.3335 9.36008 1.47397 9.61013 1.72402C9.86018 1.97407 10.0007 2.31321 10.0007 2.66683V3.3335" stroke="currentColor" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
		</span>
		<span class="a8csp-copy-to-markdown__icon a8csp-copy-to-markdown__icon--check">
			<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" focusable="false">
				<path fill="currentColor" fill-rule="evenodd" d="m15.56 4-.53.53-8.8 8.8c-.68.68-1.78.68-2.47 0l.53-.54-.53.53-2.79-2.79L.44 10 1.5 8.94l.53.53 2.8 2.8c.1.09.25.09.35 0l8.79-8.8.53-.53z" clip-rule="evenodd"/>
			</svg>
		</span>
	</span>
	<span class="a8csp-copy-to-markdown__labels" aria-hidden="true">
		<span class="a8csp-copy-to-markdown__label a8csp-copy-to-markdown__label--default">
			<?php echo wp_kses_post( $label ); ?>
		</span>
		<span class="a8csp-copy-to-markdown__label a8csp-copy-to-markdown__label--copied">
			<?php echo esc_html( $copied_label ); ?>
		</span>
	</span>
</button>
