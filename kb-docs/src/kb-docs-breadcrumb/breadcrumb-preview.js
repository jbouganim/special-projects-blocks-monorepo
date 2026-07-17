/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Fragment } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import { useEntityRecords } from '@wordpress/core-data';

/**
 * Editor preview for docs breadcrumbs.
 *
 * Crumbs render as direct flex children of the block wrapper (via blockProps)
 * so typography, colors, and block spacing match the front-end output.
 *
 * @param {Object} props               Component props.
 * @param {Object} props.attributes    Block attributes.
 * @param {Object} props.blockProps    Block wrapper props from useBlockProps().
 * @return {Object} Preview markup.
 */
export default function BreadcrumbPreview( { attributes, blockProps } ) {
	const taxonomy = attributes?.taxonomy || 'kb_cat';
	const homeLabel = attributes?.homeLabel || __( 'Docs', 'kb-docs' );
	const separator =
		typeof attributes?.separator === 'string' &&
		attributes.separator !== ''
			? attributes.separator
			: '/';

	const { records: sections, isResolving } = useEntityRecords(
		'taxonomy',
		taxonomy,
		{
			per_page: 1,
			parent: 0,
			orderby: 'name',
			order: 'asc',
			hide_empty: false,
		},
		{
			enabled: !! taxonomy,
		}
	);

	if ( isResolving ) {
		return (
			<div { ...blockProps }>
				<p className="a8csp-kbdocs-breadcrumb-preview-status">
					{ __( 'Loading breadcrumb preview…', 'kb-docs' ) }
				</p>
			</div>
		);
	}

	const sectionName =
		Array.isArray( sections ) && sections.length > 0 &&
		typeof sections[ 0 ].name === 'string'
			? decodeEntities( sections[ 0 ].name )
			: __( 'Getting Started', 'kb-docs' );

	const crumbs = [
		homeLabel,
		sectionName,
		__( 'Current Page', 'kb-docs' ),
	];

	return (
		<div
			{ ...blockProps }
			role="navigation"
			aria-label={ __( 'Breadcrumb preview', 'kb-docs' ) }
		>
			{ crumbs.map( ( crumb, index ) => (
				<Fragment key={ `${ crumb }-${ index }` }>
					{ index > 0 && (
						<span
							className="a8csp-kbdocs-breadcrumb-sep"
							aria-hidden="true"
						>
							{ separator }
						</span>
					) }
					<span
						className={
							index === crumbs.length - 1
								? 'a8csp-kbdocs-breadcrumb-current'
								: 'a8csp-kbdocs-breadcrumb-link'
						}
					>
						{ crumb }
					</span>
				</Fragment>
			) ) }
		</div>
	);
}
