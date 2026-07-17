/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { CopyIcon, CheckIcon } from './icons';

/**
 * Block edit component.
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Set attributes callback.
 * @return {Object} Block editor element.
 */
export default function Edit( { attributes, setAttributes } ) {
	const { label, contentSelector } = attributes;

	const blockProps = useBlockProps( {
		className: 'a8csp-copy-to-markdown',
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Settings', 'copy-to-markdown' ) }
					initialOpen={ true }
				>
					<TextControl
						label={ __( 'Content selector', 'copy-to-markdown' ) }
						help={ __(
							'CSS selector for the content to copy. Defaults to .entry-content.',
							'copy-to-markdown'
						) }
						value={ contentSelector }
						onChange={ ( value ) =>
							setAttributes( {
								contentSelector:
									value !== '' ? value : '.entry-content',
							} )
						}
						placeholder=".entry-content"
					/>
				</PanelBody>
			</InspectorControls>

			<button type="button" { ...blockProps } disabled>
				<span
					className="a8csp-copy-to-markdown__icons"
					aria-hidden="true"
				>
					<span className="a8csp-copy-to-markdown__icon a8csp-copy-to-markdown__icon--copy">
						<CopyIcon />
					</span>
					<span className="a8csp-copy-to-markdown__icon a8csp-copy-to-markdown__icon--check">
						<CheckIcon />
					</span>
				</span>
				<span
					className="a8csp-copy-to-markdown__labels"
					aria-hidden="true"
				>
					<RichText
						tagName="span"
						className="a8csp-copy-to-markdown__label a8csp-copy-to-markdown__label--default"
						value={ label }
						onChange={ ( value ) =>
							setAttributes( { label: value } )
						}
						placeholder={ __(
							'Copy content',
							'copy-to-markdown'
						) }
						allowedFormats={ [
							'core/bold',
							'core/italic',
							'core/strikethrough',
						] }
						withoutInteractiveFormatting
					/>
					<span className="a8csp-copy-to-markdown__label a8csp-copy-to-markdown__label--copied">
						{ __( 'Copied', 'copy-to-markdown' ) }
					</span>
				</span>
			</button>
		</>
	);
}
