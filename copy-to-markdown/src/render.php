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

$label_text   = wp_strip_all_tags( $label );
$copied_label = __( 'Copied page markdown', 'copy-to-markdown' );

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'                         => 'a8csp-copy-to-markdown',
		'type'                          => 'button',
		'data-wp-interactive'           => 'a8csp/copy-to-markdown',
		'data-wp-on--click'             => 'actions.copy',
		'data-wp-class--is-copied'      => 'context.isCopied',
		'data-wp-bind--aria-label'      => 'state.ariaLabel',
		'aria-label'                    => $label_text,
	)
);
?>
<button
	<?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- From get_block_wrapper_attributes(). ?>
	<?php
	echo wp_kses_data(
		wp_interactivity_data_wp_context(
			array(
				'contentSelector' => $content_selector,
				'label'           => $label_text,
				'copiedLabel'     => $copied_label,
				'isCopied'        => false,
			)
		)
	);
	?>
>
	<span class="a8csp-copy-to-markdown__icons" aria-hidden="true">
		<span class="a8csp-copy-to-markdown__icon a8csp-copy-to-markdown__icon--copy">
			<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" focusable="false">
				<path d="M13.3333 6H7.33333C6.59695 6 6 6.59695 6 7.33333V13.3333C6 14.0697 6.59695 14.6667 7.33333 14.6667H13.3333C14.0697 14.6667 14.6667 14.0697 14.6667 13.3333V7.33333C14.6667 6.59695 14.0697 6 13.3333 6Z" stroke="currentColor" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/>
				<path d="M3.33398 10.0002H2.66732C2.3137 10.0002 1.97456 9.85969 1.72451 9.60964C1.47446 9.35959 1.33398 9.02045 1.33398 8.66683V2.66683C1.33398 2.31321 1.47446 1.97407 1.72451 1.72402C1.97456 1.47397 2.3137 1.3335 2.66732 1.3335H8.66732C9.02094 1.3335 9.36008 1.47397 9.61013 1.72402C9.86018 1.97407 10.0007 2.31321 10.0007 2.66683V3.3335" stroke="currentColor" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
		</span>
		<span class="a8csp-copy-to-markdown__icon a8csp-copy-to-markdown__icon--check">
		<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none">
			<path d="M13.75 8C13.75 4.82436 11.1756 2.25 8 2.25C4.82436 2.25 2.25 4.82436 2.25 8C2.25 11.1756 4.82436 13.75 8 13.75V15C4.13401 15 1 11.866 1 8C1 4.13401 4.13401 1 8 1C11.866 1 15 4.13401 15 8C15 11.866 11.866 15 8 15V13.75C11.1756 13.75 13.75 11.1756 13.75 8Z" fill="currentColor"/>
			<path fill-rule="evenodd" clip-rule="evenodd" d="M11.3507 5.46092C11.6459 5.70712 11.676 6.1551 11.4117 6.43416L7.52708 10.4861C7.27958 10.7474 6.86943 10.7644 6.60118 10.5245L4.62933 8.70194C4.3546 8.45628 4.33222 8.0332 4.57865 7.7591C4.8233 7.48699 5.24211 7.4629 5.51553 7.70601L6.70964 8.82991C6.84186 8.94748 7.04287 8.94092 7.16716 8.81498L10.4481 5.50153C10.691 5.25541 11.0852 5.23943 11.3507 5.46092Z" fill="currentColor"/>
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
