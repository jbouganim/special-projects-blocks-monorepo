<?php
/**
 * Render the block on the frontend.
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */

// Don't render this block if it's not in the context of a post.
if ( ! isset( $block->context['postId'] ) ) {
	return;
}

// WPCom is having trouble enqueueing view.js for this block, so doing it here.
$asset_deps = require trailingslashit( plugin_dir_path( __FILE__ ) ) . 'view.asset.php';

wp_enqueue_script(
	'wpcomsp-dynamic-table-of-contents-view',
	plugins_url( 'view.js', __FILE__ ),
	$asset_deps['dependencies'],
	$asset_deps['version'],
	true
);
?>

<?php
// Prepare data attributes for heading levels
$heading_levels     = array();
$heading_attributes = array( 'includeH1', 'includeH2', 'includeH3', 'includeH4', 'includeH5', 'includeH6' );

foreach ( $heading_attributes as $heading_attr ) {
	if ( isset( $attributes[ $heading_attr ] ) && true === $attributes[ $heading_attr ] ) {
		$heading_level    = strtolower( str_replace( 'include', '', $heading_attr ) );
		$heading_levels[] = $heading_level;
	}
}

$default_content_class_name = 'wp-block-post-content';
$content_class_name         = $attributes['contentClassName'] ?? '';

if ( ! is_string( $content_class_name ) ) {
	$content_class_name = '';
}

$content_class_name = sanitize_html_class( $content_class_name );

if ( '' === $content_class_name ) {
	$content_class_name = $default_content_class_name;
}

$accordion_mode         = isset( $attributes['accordionMode'] ) && true === $attributes['accordionMode'];
$accordion_breakpoint   = isset( $attributes['accordionBreakpoint'] ) ? (int) $attributes['accordionBreakpoint'] : 600;
$accordion_default_open = isset( $attributes['accordionDefaultOpen'] ) && true === $attributes['accordionDefaultOpen'];
$enable_deep_links      = isset( $attributes['enableDeepLinks'] ) && true === $attributes['enableDeepLinks'];

if ( $accordion_breakpoint < 0 ) {
	$accordion_breakpoint = 0;
}

if ( $accordion_breakpoint > 1920 ) {
	$accordion_breakpoint = 1920;
}

$list_id = $accordion_mode ? wp_unique_id( 'toc-list-' ) : '';

$data_attributes = array(
	'data-heading-levels'     => esc_attr( implode( ',', $heading_levels ) ),
	'data-content-class-name' => esc_attr( $content_class_name ),
);

if ( $enable_deep_links ) {
	$data_attributes['data-enable-deep-links'] = 'true';
}

if ( $accordion_mode ) {
	$data_attributes['data-accordion-mode']         = 'true';
	$data_attributes['data-accordion-breakpoint']   = (string) $accordion_breakpoint;
	$data_attributes['data-accordion-default-open'] = $accordion_default_open ? 'true' : 'false';

	if ( $accordion_default_open ) {
		$data_attributes['class'] = 'is-accordion-open';
	}
}

$wrapper_attributes = get_block_wrapper_attributes( $data_attributes );

$block_title = $attributes['title'] ?? '';
$block_title = apply_filters( 'wpcomsp_dynamic_table_of_contents_block_title', $block_title, $attributes );
$has_title   = is_string( $block_title ) && '' !== $block_title;
?>

<div <?php echo wp_kses_post( $wrapper_attributes ); ?>>
	<?php if ( $has_title ) : ?>
		<?php if ( $accordion_mode ) : ?>
			<h2 class="table-of-contents-title">
				<button
					type="button"
					class="table-of-contents-accordion-toggle"
					aria-expanded="<?php echo $accordion_default_open ? 'true' : 'false'; ?>"
					aria-controls="<?php echo esc_attr( $list_id ); ?>"
				>
					<span class="table-of-contents-accordion-label">
						<?php echo wp_kses_post( $block_title ); ?>
					</span>
					<span class="table-of-contents-accordion-icon" aria-hidden="true"></span>
				</button>
			</h2>
		<?php else : ?>
			<?php
			printf(
				'<h2>%s</h2>',
				wp_kses_post( $block_title )
			);
			?>
		<?php endif; ?>
	<?php endif; ?>
	<ul<?php echo $accordion_mode ? ' id="' . esc_attr( $list_id ) . '"' : ''; ?>></ul>
</div>
