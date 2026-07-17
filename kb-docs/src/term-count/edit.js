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
import { useSelect } from '@wordpress/data';
import { store as coreStore, useEntityProp } from '@wordpress/core-data';

registerBlockVariation( 'core/term-count', {
	name: 'a8csp/kb-docs-term-count-with-post-type-label',
	title: __( 'Term Count with Post Type Label', 'kb-docs' ),
	description: __(
		'Displays the term count followed by the associated post type label, e.g. "2 articles".',
		'kb-docs'
	),
	icon: 'tag',
	attributes: {
		bracketType: 'none',
		suffixPostTypeLabel: true,
	},
	isActive: ( blockAttributes ) => blockAttributes.suffixPostTypeLabel === true,
	scope: [ 'block', 'inserter' ],
} );

/**
 * Get the hierarchical post count for a term from the REST API.
 *
 * @param {number|null} termId   Term ID.
 * @param {string}      taxonomy Taxonomy slug.
 * @return {number|null} Post count including child terms, or null when unavailable.
 */
function useHierarchicalTermPostCount( termId, taxonomy ) {
	return useSelect(
		( select ) => {
			if ( ! termId || ! taxonomy ) {
				return null;
			}

			const record = select( coreStore ).getEntityRecord(
				'taxonomy',
				taxonomy,
				termId
			);

			if (
				record &&
				typeof record.kb_docs_post_count === 'number'
			) {
				return record.kb_docs_post_count;
			}

			return null;
		},
		[ termId, taxonomy ]
	);
}

/**
 * Get the singular and plural post type labels for a taxonomy.
 *
 * @param {string} taxonomy Taxonomy slug.
 * @return {{singular: string, plural: string}} Post type labels.
 */
function usePostTypeLabelsForTaxonomy( taxonomy ) {
	return useSelect(
		( select ) => {
			const empty = { singular: '', plural: '' };

			if ( ! taxonomy ) {
				return empty;
			}

			const taxonomies = select( coreStore ).getTaxonomies( {
				per_page: -1,
			} );

			if ( ! Array.isArray( taxonomies ) ) {
				return empty;
			}

			const taxonomyRecord = taxonomies.find(
				( record ) => record.slug === taxonomy
			);

			if (
				! taxonomyRecord ||
				! Array.isArray( taxonomyRecord.types ) ||
				taxonomyRecord.types.length < 1
			) {
				return empty;
			}

			const postType = select( coreStore ).getPostType(
				taxonomyRecord.types[ 0 ]
			);

			if ( ! postType || ! postType.labels ) {
				return empty;
			}

			return {
				singular: postType.labels.singular_name || '',
				plural: postType.labels.name || '',
			};
		},
		[ taxonomy ]
	);
}

/**
 * Get the term archive link for a term.
 *
 * @param {number|null} termId   Term ID.
 * @param {string}      taxonomy Taxonomy slug.
 * @return {string} Term archive URL, or empty string.
 */
function useTermLink( termId, taxonomy ) {
	return useSelect(
		( select ) => {
			if ( ! termId || ! taxonomy ) {
				return '';
			}

			const record = select( coreStore ).getEntityRecord(
				'taxonomy',
				taxonomy,
				termId
			);

			return record && typeof record.link === 'string'
				? record.link
				: '';
		},
		[ termId, taxonomy ]
	);
}

/**
 * Format a count with a bracket style.
 *
 * @param {number} count       Post count.
 * @param {string} bracketType Bracket style.
 * @return {string} Formatted count string.
 */
function formatBracketCount( count, bracketType ) {
	switch ( bracketType ) {
		case 'none':
			return String( count );
		case 'square':
			return `[${ count }]`;
		case 'curly':
			return `{${ count }}`;
		case 'angle':
			return `<${ count }>`;
		case 'round':
		default:
			return `(${ count })`;
	}
}

/**
 * Format term count output for the variation preview.
 *
 * @param {number|null} count      Post count.
 * @param {Object}      attributes Block attributes.
 * @param {Object}      labels     Post type labels.
 * @return {string} Formatted count string.
 */
function formatTermCount( count, attributes, labels ) {
	const safeCount = typeof count === 'number' ? count : 0;

	if ( attributes.suffixPostTypeLabel === true ) {
		const customLabel =
			safeCount === 1
				? attributes.suffixSingularLabel
				: attributes.suffixPluralLabel;

		const trimmed =
			typeof customLabel === 'string' ? customLabel.trim() : '';

		const label =
			trimmed !== ''
				? trimmed
				: safeCount === 1
				? labels.singular
				: labels.plural;

		if ( label ) {
			return `${ safeCount } ${ label }`;
		}

		return String( safeCount );
	}

	return formatBracketCount( safeCount, attributes.bracketType );
}

/**
 * Editor preview for the term-count KB Docs variation.
 *
 * @param {Object} props Block edit props.
 * @return {Object} Block preview element.
 */
function TermCountVariationEdit( props ) {
	const { attributes, context } = props;
	const { termId, taxonomy } = context;
	const blockProps = useBlockProps();
	const [ directCount ] = useEntityProp(
		'taxonomy',
		taxonomy,
		'count',
		termId
	);
	const hierarchicalCount = useHierarchicalTermPostCount(
		termId,
		taxonomy
	);
	const count =
		typeof hierarchicalCount === 'number'
			? hierarchicalCount
			: Number( directCount ) || 0;
	const labels = usePostTypeLabelsForTaxonomy( taxonomy );
	const termLink = useTermLink( termId, taxonomy );

	const text = formatTermCount( count, attributes, labels );

	if ( attributes.linkToArchive === true && termLink ) {
		return (
			<div { ...blockProps }>
				<a
					className="a8csp-kbdocs-term-count-link"
					href={ termLink }
					onClick={ ( event ) => event.preventDefault() }
				>
					{ text }
				</a>
			</div>
		);
	}

	return <div { ...blockProps }>{ text }</div>;
}

/**
 * Wrap core/term-count edit to support the KB Docs variation preview.
 *
 * @param {Object} settings Block settings.
 * @param {string} name     Block name.
 * @return {Object} Block settings.
 */
function wrapTermCountEdit( settings, name ) {
	if ( name !== 'core/term-count' ) {
		return settings;
	}

	const OriginalEdit = settings.edit;

	settings.edit = ( props ) => {
		const { attributes } = props;

		if (
			attributes?.suffixPostTypeLabel === true ||
			attributes?.linkToArchive === true
		) {
			return <TermCountVariationEdit { ...props } />;
		}

		return <OriginalEdit { ...props } />;
	};

	return settings;
}

addFilter(
	'blocks.registerBlockType',
	'a8csp/kb-docs-term-count-edit',
	wrapTermCountEdit
);

/**
 * Adds inspector controls for the KB Docs term-count variation.
 *
 * @param {Object} BlockEdit The original BlockEdit component.
 * @return {Object}          The modified block edit component.
 */
function addTermCountInspectorControls( BlockEdit ) {
	return ( props ) => {
		const { name, attributes, setAttributes } = props;

		if ( name !== 'core/term-count' ) {
			return <BlockEdit { ...props } />;
		}

		const {
			suffixPostTypeLabel,
			suffixSingularLabel,
			suffixPluralLabel,
			linkToArchive,
		} = attributes;

		return (
			<>
				<BlockEdit { ...props } />
				<InspectorControls>
					<PanelBody
						title={ __( 'Display', 'kb-docs' ) }
						initialOpen={ true }
					>
						<PanelRow>
							<ToggleControl
								label={ __( 'Link to term archive', 'kb-docs' ) }
								help={ __(
									'Wraps the count in a link to the term archive page.',
									'kb-docs'
								) }
								checked={ linkToArchive === true }
								onChange={ ( value ) =>
									setAttributes( { linkToArchive: value } )
								}
							/>
						</PanelRow>
						<PanelRow>
							<ToggleControl
								label={ __( 'Suffix label', 'kb-docs' ) }
								help={ __(
									'Shows the count with a label, e.g. "2 articles" instead of "(2)".',
									'kb-docs'
								) }
								checked={ suffixPostTypeLabel === true }
								onChange={ ( value ) =>
									setAttributes( {
										suffixPostTypeLabel: value,
										bracketType: value
											? 'none'
											: attributes.bracketType,
									} )
								}
							/>
						</PanelRow>
						{ suffixPostTypeLabel === true && (
							<>
								<TextControl
									label={ __(
										'Singular suffix label',
										'kb-docs'
									) }
									help={ __(
										'Used when the count is 1. Leave empty to use the post type label.',
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
									help={ __(
										'Used when the count is not 1. Leave empty to use the post type label.',
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
	'a8csp/kb-docs-term-count-inspector-controls',
	addTermCountInspectorControls
);
