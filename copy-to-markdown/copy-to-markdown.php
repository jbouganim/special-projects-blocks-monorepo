<?php
/**
 * Plugin Name:       Copy to Markdown
 * Description:       A button that copies page content to the clipboard as Markdown.
 * Version:           0.3.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            WordPress Special Projects Team
 * Author URI:        https://wpspecialprojects.wordpress.com/
 * Update URI:        https://opsoasis.wpspecialprojects.com/copy-to-markdown/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       copy-to-markdown
 *
 * @package a8csp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// If no other WPCOMSP Block Plugin added the self update class, add it.
if ( ! class_exists( 'WPCOMSP_Blocks_Self_Update' ) ) {
	require __DIR__ . '/classes/class-wpcomsp-blocks-self-update.php';

	$wpcomsp_blocks_self_update = WPCOMSP_Blocks_Self_Update::get_instance();
	$wpcomsp_blocks_self_update->hooks();
}

/**
 * Setup auto-updates for this plugin from our monorepo.
 *
 * @param array $blocks Array of plugin files.
 * @return array
 */
add_filter(
	'wpcomsp_installed_blocks',
	static function ( $blocks ) {
		$blocks[] = 'copy-to-markdown';

		return $blocks;
	}
);

/**
 * Registers the block using metadata from block.json.
 *
 * @return void
 */
function a8csp_copy_to_markdown_block_init(): void {
	register_block_type_from_metadata( __DIR__ . '/build' );
}
add_action( 'init', 'a8csp_copy_to_markdown_block_init' );
