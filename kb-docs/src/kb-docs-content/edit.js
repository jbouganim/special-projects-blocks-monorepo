/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

/**
 * Internal dependencies.
 */
import { ALLOWED_BLOCKS } from './allowed-blocks';

export default function Edit() {
	return (
		<section { ...useBlockProps() }>
			<InnerBlocks allowedBlocks={ ALLOWED_BLOCKS } templateLock={ false } />
		</section>
	);
}
