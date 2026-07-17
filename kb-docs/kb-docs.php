<?php
/**
 * Plugin Name:       KB Docs
 * Description:       Docs layout blocks for sidebar, breadcrumb, and content rendering.
 * Requires at least: 6.6
 * Requires PHP:      8.0
 * Version:           0.3.0
 * Author:            WordPress Special Projects Team
 * Author URI:        https://wpspecialprojects.wordpress.com/
 * Update URI:        https://opsoasis.wpspecialprojects.com/kb-docs/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       kb-docs
 *
 * @package wpcomsp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require __DIR__ . '/includes/frontend.php';
require __DIR__ . '/includes/blocks.php';
require __DIR__ . '/includes/block-variations.php';

// If no other WPCOMSP Block Plugin added the self update class, add it.
if ( ! class_exists( 'WPCOMSP_Blocks_Self_Update' ) ) {
	require __DIR__ . '/classes/class-wpcomsp-blocks-self-update.php';

	$wpcomsp_blocks_self_update = WPCOMSP_Blocks_Self_Update::get_instance();
	$wpcomsp_blocks_self_update->hooks();
}

/**
 * Setup auto-updates for this plugin from our monorepo.
 * Done in an anonymous function for simplicity in making this a drop-in snippet.
 *
 * @param array $blocks Array of plugin files.
 *
 * @return array
 */
add_filter(
	'wpcomsp_installed_blocks',
	function ( $blocks ) {
		$blocks[] = 'kb-docs';

		return $blocks;
	}
);

/**
 * Register all KB Docs blocks.
 *
 * @return void
 */
function a8csp_kb_docs_block_init(): void {
	register_block_type( __DIR__ . '/build/kb-docs-sidebar' );
	register_block_type( __DIR__ . '/build/kb-docs-breadcrumb' );
	register_block_type( __DIR__ . '/build/kb-docs-content' );
	register_block_type( __DIR__ . '/build/term-count' );
	register_block_type( __DIR__ . '/build/categories' );
}
add_action( 'init', 'a8csp_kb_docs_block_init' );
