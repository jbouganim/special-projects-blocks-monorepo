/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import { InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	PanelRow,
	ToggleControl,
	RangeControl,
} from '@wordpress/components';
import { registerBlockVariation } from '@wordpress/blocks';

registerBlockVariation( 'core/categories', {
	name: 'a8csp/kb-docs-categories-child-terms',
	title: __( 'KB Docs Child Terms', 'kb-docs' ),
	description: __(
		'Lists child terms of the current Term Template term. Use inside a Terms Query term template.',
		'kb-docs'
	),
	icon: 'category',
	attributes: {
		inheritParentFromContext: true,
		showOnlyTopLevel: true,
		limitTerms: false,
		termsLimit: 5,
		taxonomy: 'kb_cat',
	},
	isActive: ( blockAttributes ) =>
		blockAttributes.inheritParentFromContext === true,
	scope: [ 'block', 'inserter', 'transform' ],
} );

/**
 * Adds inspector controls for KB Docs categories variation options.
 *
 * @param {Object} BlockEdit The original BlockEdit component.
 * @return {Object} The modified block edit component.
 */
function addCategoriesInspectorControls( BlockEdit ) {
	return ( props ) => {
		const { name, attributes, setAttributes } = props;

		if ( name !== 'core/categories' ) {
			return <BlockEdit { ...props } />;
		}

		const {
			inheritParentFromContext,
			limitTerms,
			termsLimit,
		} = attributes;

		return (
			<>
				<BlockEdit { ...props } />
				<InspectorControls>
					<PanelBody
						title={ __( 'KB Docs', 'kb-docs' ) }
						initialOpen={ true }
					>
						<PanelRow>
							<ToggleControl
								label={ __(
									'Inherit parent from Term Template',
									'kb-docs'
								) }
								help={ __(
									'When placed inside a Term Template, list children of the current term. With “Show only top level terms” enabled, only direct children are shown; otherwise all descendants are included.',
									'kb-docs'
								) }
								checked={ inheritParentFromContext === true }
								onChange={ ( value ) =>
									setAttributes( {
										inheritParentFromContext: value,
									} )
								}
							/>
						</PanelRow>
						{ inheritParentFromContext === true && (
							<>
								<PanelRow>
									<ToggleControl
										label={ __(
											'Limit number of terms',
											'kb-docs'
										) }
										help={ __(
											'Cap how many child terms are displayed.',
											'kb-docs'
										) }
										checked={ limitTerms === true }
										onChange={ ( value ) =>
											setAttributes( {
												limitTerms: value,
											} )
										}
									/>
								</PanelRow>
								{ limitTerms === true && (
									<RangeControl
										__next40pxDefaultSize
										label={ __(
											'Maximum terms',
											'kb-docs'
										) }
										value={
											typeof termsLimit === 'number'
												? termsLimit
												: 5
										}
										onChange={ ( value ) =>
											setAttributes( {
												termsLimit:
													typeof value === 'number'
														? value
														: 5,
											} )
										}
										min={ 1 }
										max={ 50 }
									/>
								) }
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
	'a8csp/kb-docs-categories-inspector-controls',
	addCategoriesInspectorControls
);
