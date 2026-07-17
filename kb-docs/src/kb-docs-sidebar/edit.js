/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	InnerBlocks,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	ToggleControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

/**
 * Internal dependencies
 */
import { ALLOWED_BLOCKS } from './allowed-blocks';
import SidebarNavPreview from './nav-preview';
import { getBlockGapCssValue } from '../kb-docs-breadcrumb/get-block-gap-css';

const SECTION_ORDER_BY_OPTIONS = [
	{ label: __( 'Name', 'kb-docs' ), value: 'name' },
	{ label: __( 'Slug', 'kb-docs' ), value: 'slug' },
	{ label: __( 'Term ID', 'kb-docs' ), value: 'term_id' },
	{ label: __( 'Count', 'kb-docs' ), value: 'count' },
];

const ARTICLE_ORDER_BY_OPTIONS = [
	{ label: __( 'Menu order', 'kb-docs' ), value: 'menu_order' },
	{ label: __( 'Title', 'kb-docs' ), value: 'title' },
	{ label: __( 'Date', 'kb-docs' ), value: 'date' },
];

const ORDER_OPTIONS = [
	{ label: __( 'Ascending', 'kb-docs' ), value: 'asc' },
	{ label: __( 'Descending', 'kb-docs' ), value: 'desc' },
];

/**
 * Sidebar block edit component.
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
		sectionOrderBy,
		sectionOrder,
		articleOrderBy,
		articleOrder,
		includeChildren,
		style,
	} = attributes;

	const sectionGap = getBlockGapCssValue( style?.spacing?.blockGap );

	const blockProps = useBlockProps( {
		className: 'a8csp-kbdocs-sidebar-block',
		style:
			sectionGap !== undefined
				? { '--wp--style--block-gap': sectionGap }
				: undefined,
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
			taxonomy
				? select( coreStore ).getTaxonomy( taxonomy )
				: null,
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

	return (
		<aside { ...blockProps }>
			<InspectorControls>
				<PanelBody
					title={ __( 'Navigation settings', 'kb-docs' ) }
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
					<SelectControl
						label={ __( 'Section order by', 'kb-docs' ) }
						value={ sectionOrderBy }
						options={ SECTION_ORDER_BY_OPTIONS }
						onChange={ ( value ) =>
							setAttributes( { sectionOrderBy: value } )
						}
					/>
					<SelectControl
						label={ __( 'Section order', 'kb-docs' ) }
						value={ sectionOrder }
						options={ ORDER_OPTIONS }
						onChange={ ( value ) =>
							setAttributes( { sectionOrder: value } )
						}
					/>
					<SelectControl
						label={ __( 'Article order by', 'kb-docs' ) }
						value={ articleOrderBy }
						options={ ARTICLE_ORDER_BY_OPTIONS }
						onChange={ ( value ) =>
							setAttributes( { articleOrderBy: value } )
						}
					/>
					<SelectControl
						label={ __( 'Article order', 'kb-docs' ) }
						value={ articleOrder }
						options={ ORDER_OPTIONS }
						onChange={ ( value ) =>
							setAttributes( { articleOrder: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Include child terms', 'kb-docs' ) }
						help={ __(
							'Include articles assigned to descendant categories.',
							'kb-docs'
						) }
						checked={ includeChildren }
						onChange={ ( value ) =>
							setAttributes( { includeChildren: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<div className="a8csp-kbdocs-sidebar-chrome">
				<InnerBlocks
					allowedBlocks={ ALLOWED_BLOCKS }
					templateLock={ false }
					renderAppender={ InnerBlocks.ButtonBlockAppender }
				/>
			</div>

			<SidebarNavPreview attributes={ attributes } />
		</aside>
	);
}
