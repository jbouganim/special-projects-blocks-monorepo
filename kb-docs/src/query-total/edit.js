/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import {
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	PanelBody,
	PanelRow,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { registerBlockVariation } from '@wordpress/blocks';

registerBlockVariation( 'core/query-total', {
	name: 'a8csp/kb-docs-query-remaining',
	title: __( 'Remaining Guides', 'kb-docs' ),
	description: __(
		'Shows how many results remain beyond the current page, e.g. "3 more guides", and links to the taxonomy archive.',
		'kb-docs'
	),
	icon: 'editor-ol',
	attributes: {
		showRemaining: true,
		linkToTaxArchive: true,
		suffixSingularLabel: 'more guide',
		suffixPluralLabel: 'more guides',
	},
	isActive: ( blockAttributes ) => blockAttributes.showRemaining === true,
	scope: [ 'block', 'inserter' ],
} );

/**
 * Editor preview for the remaining-results Query Total variation.
 *
 * @param {Object} props Block edit props.
 * @return {JSX.Element} Preview markup.
 */
function QueryRemainingVariationEdit( props ) {
	const { attributes } = props;
	const {
		suffixSingularLabel,
		suffixPluralLabel,
		linkToTaxArchive,
	} = attributes;
	const blockProps = useBlockProps( {
		className: 'wp-block-query-total',
	} );

	const singular =
		typeof suffixSingularLabel === 'string' && suffixSingularLabel !== ''
			? suffixSingularLabel
			: __( 'more guide', 'kb-docs' );
	const plural =
		typeof suffixPluralLabel === 'string' && suffixPluralLabel !== ''
			? suffixPluralLabel
			: __( 'more guides', 'kb-docs' );

	const previewCount = 3;
	const text = `${ previewCount } ${
		previewCount === 1 ? singular : plural
	}`;

	if ( linkToTaxArchive === true ) {
		return (
			<div { ...blockProps }>
				<a
					className="a8csp-kbdocs-term-count-link"
					href="#kb-docs-query-remaining-preview"
					onClick={ ( event ) => event.preventDefault() }
				>
					<span className="a8csp-kbdocs-term-count-label">{ text }</span>
				</a>
			</div>
		);
	}

	return <div { ...blockProps }>{ text }</div>;
}

/**
 * Wrap core/query-total edit for the remaining-results variation.
 *
 * @param {Object} settings Block settings.
 * @param {string} name     Block name.
 * @return {Object} Block settings.
 */
function wrapQueryTotalEdit( settings, name ) {
	if ( name !== 'core/query-total' ) {
		return settings;
	}

	const OriginalEdit = settings.edit;

	settings.edit = ( props ) => {
		const { attributes } = props;

		if ( attributes?.showRemaining === true ) {
			return <QueryRemainingVariationEdit { ...props } />;
		}

		return <OriginalEdit { ...props } />;
	};

	return settings;
}

addFilter(
	'blocks.registerBlockType',
	'a8csp/kb-docs-query-total-edit',
	wrapQueryTotalEdit
);

/**
 * Adds inspector controls for the remaining-results Query Total variation.
 *
 * @param {Object} BlockEdit The original BlockEdit component.
 * @return {Function} The modified block edit component.
 */
function addQueryTotalInspectorControls( BlockEdit ) {
	return ( props ) => {
		const { name, attributes, setAttributes } = props;

		if ( name !== 'core/query-total' ) {
			return <BlockEdit { ...props } />;
		}

		const {
			showRemaining,
			linkToTaxArchive,
			suffixSingularLabel,
			suffixPluralLabel,
		} = attributes;

		return (
			<>
				<BlockEdit { ...props } />
				<InspectorControls>
					<PanelBody
						title={ __( 'Remaining results', 'kb-docs' ) }
						initialOpen={ showRemaining === true }
					>
						<PanelRow>
							<ToggleControl
								label={ __(
									'Show remaining count',
									'kb-docs'
								) }
								help={ __(
									'Shows how many results are left after the current page (e.g. "3 more guides"). Hides when there are none.',
									'kb-docs'
								) }
								checked={ showRemaining === true }
								onChange={ ( value ) =>
									setAttributes( { showRemaining: value } )
								}
							/>
						</PanelRow>
						{ showRemaining === true && (
							<>
								<PanelRow>
									<ToggleControl
										label={ __(
											'Link to taxonomy archive',
											'kb-docs'
										) }
										help={ __(
											'Uses the first term from this Query Loop’s taxonomy filter.',
											'kb-docs'
										) }
										checked={ linkToTaxArchive === true }
										onChange={ ( value ) =>
											setAttributes( {
												linkToTaxArchive: value,
											} )
										}
									/>
								</PanelRow>
								<TextControl
									label={ __(
										'Singular suffix label',
										'kb-docs'
									) }
									value={ suffixSingularLabel || '' }
									onChange={ ( value ) =>
										setAttributes( {
											suffixSingularLabel: value,
										} )
									}
								/>
								<TextControl
									label={ __(
										'Plural suffix label',
										'kb-docs'
									) }
									value={ suffixPluralLabel || '' }
									onChange={ ( value ) =>
										setAttributes( {
											suffixPluralLabel: value,
										} )
									}
								/>
							</>
						) }
					</PanelBody>
				</InspectorControls>
			</>
		);
	};
}

addFilter(
	'editor.BlockEdit',
	'a8csp/kb-docs-query-total-inspector-controls',
	addQueryTotalInspectorControls
);
