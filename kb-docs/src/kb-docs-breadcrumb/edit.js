/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

/**
 * Internal dependencies
 */
import BreadcrumbPreview from './breadcrumb-preview';
import { getBlockGapCssValue } from './get-block-gap-css';

const SEPARATOR_OPTIONS = [
	{ label: '/ (slash)', value: '/' },
	{ label: '› (chevron)', value: '›' },
	{ label: '→ (arrow)', value: '→' },
	{ label: '· (middle dot)', value: '·' },
	{ label: '| (pipe)', value: '|' },
];

/**
 * Breadcrumb block edit component.
 *
 * @param {Object} props               Component props.
 * @param {Object} props.attributes    Block attributes.
 * @param {Function} props.setAttributes Set attributes callback.
 * @return {Object} Block editor element.
 */
export default function Edit( { attributes, setAttributes } ) {
	const {
		taxonomy,
		postType,
		homeLabel,
		separator,
		showMobileSidebarButton,
		style,
	} = attributes;

	const blockGap = getBlockGapCssValue( style?.spacing?.blockGap );

	const blockProps = useBlockProps( {
		className:
			'a8csp-kbdocs-breadcrumb-block a8csp-kbdocs-breadcrumbs a8csp-kbdocs-breadcrumb-preview',
		style: {
			display: 'flex',
			flexWrap: 'wrap',
			alignItems: 'center',
			...( blockGap !== undefined ? { gap: blockGap } : {} ),
		},
	} );

	const taxonomies = useSelect(
		( select ) =>
			select( coreStore ).getTaxonomies( { per_page: -1 } ) ?? [],
		[]
	);

	const taxonomyOptions = taxonomies
		.filter( ( tax ) => tax?.visibility?.public !== false )
		.map( ( tax ) => ( {
			label: tax.name,
			value: tax.slug,
		} ) );

	const selectedTaxonomy = useSelect(
		( select ) =>
			taxonomy ? select( coreStore ).getTaxonomy( taxonomy ) : null,
		[ taxonomy ]
	);

	const postTypeOptions = useSelect(
		( select ) => {
			if (
				! selectedTaxonomy ||
				! Array.isArray( selectedTaxonomy.types )
			) {
				return [];
			}

			return selectedTaxonomy.types
				.map( ( slug ) => select( coreStore ).getPostType( slug ) )
				.filter( Boolean )
				.map( ( type ) => ( {
					label: type.labels?.singular_name || type.slug,
					value: type.slug,
				} ) );
		},
		[ selectedTaxonomy ]
	);

	const separatorSelectValue = SEPARATOR_OPTIONS.some(
		( option ) => option.value === separator
	)
		? separator
		: 'custom';

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Breadcrumb settings', 'kb-docs' ) }
					initialOpen={ true }
				>
					<SelectControl
						label={ __( 'Taxonomy', 'kb-docs' ) }
						value={ taxonomy }
						options={
							taxonomyOptions.length > 0
								? taxonomyOptions
								: [
										{
											label: taxonomy,
											value: taxonomy,
										},
								  ]
						}
						onChange={ ( value ) =>
							setAttributes( { taxonomy: value } )
						}
					/>
					<SelectControl
						label={ __( 'Post type', 'kb-docs' ) }
						help={ __(
							'Used for singular article breadcrumb trails.',
							'kb-docs'
						) }
						value={ postType }
						options={
							postTypeOptions.length > 0
								? postTypeOptions
								: [
										{
											label: postType,
											value: postType,
										},
								  ]
						}
						onChange={ ( value ) =>
							setAttributes( { postType: value } )
						}
					/>
					<TextControl
						label={ __( 'Home label', 'kb-docs' ) }
						value={ homeLabel }
						onChange={ ( value ) =>
							setAttributes( { homeLabel: value } )
						}
					/>
					<SelectControl
						label={ __( 'Separator', 'kb-docs' ) }
						value={ separatorSelectValue }
						options={ [
							...SEPARATOR_OPTIONS,
							{
								label: __( 'Custom', 'kb-docs' ),
								value: 'custom',
							},
						] }
						onChange={ ( value ) => {
							if ( value !== 'custom' ) {
								setAttributes( { separator: value } );
							}
						} }
					/>
					{ separatorSelectValue === 'custom' && (
						<TextControl
							label={ __( 'Custom separator', 'kb-docs' ) }
							value={ separator }
							onChange={ ( value ) =>
								setAttributes( { separator: value } )
							}
						/>
					) }
					<ToggleControl
						label={ __(
							'Show mobile sidebar overlay button',
							'kb-docs'
						) }
						help={ __(
							'Displays a button on screens below 960px that opens the docs sidebar overlay.',
							'kb-docs'
						) }
						checked={ showMobileSidebarButton !== false }
						onChange={ ( value ) =>
							setAttributes( {
								showMobileSidebarButton: value,
							} )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<BreadcrumbPreview
				attributes={ attributes }
				blockProps={ blockProps }
			/>
		</>
	);
}
