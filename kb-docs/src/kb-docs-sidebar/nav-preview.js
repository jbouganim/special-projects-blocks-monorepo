/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import { useEntityRecords } from '@wordpress/core-data';

/**
 * Build REST orderby value for article queries.
 *
 * @param {string} articleOrderBy Block attribute.
 * @return {string} REST orderby value.
 */
function getArticleRestOrderBy( articleOrderBy ) {
	if ( articleOrderBy === 'menu_order' ) {
		return 'menu_order';
	}

	if ( articleOrderBy === 'date' ) {
		return 'date';
	}

	return 'title';
}

/**
 * Get a display title from a post entity record.
 *
 * @param {Object} article Post entity record.
 * @return {string} Post title.
 */
function getPostTitle( article ) {
	if ( ! article || typeof article !== 'object' ) {
		return '';
	}

	if ( typeof article.title === 'string' ) {
		return decodeEntities( article.title );
	}

	if (
		article.title &&
		typeof article.title === 'object' &&
		typeof article.title.rendered === 'string'
	) {
		return decodeEntities( article.title.rendered );
	}

	return '';
}

/**
 * Chevron icon for expandable preview terms.
 *
 * @return {Object} Chevron element.
 */
function PreviewChevron() {
	return (
		<span className="a8csp-kbdocs-sidebar-chevron" aria-hidden="true">
			<svg
				xmlns="http://www.w3.org/2000/svg"
				width="16"
				height="16"
				viewBox="0 0 16 16"
				fill="none"
				focusable="false"
			>
				<path
					d="M6 3.5L10.5 8L6 12.5"
					stroke="currentColor"
					strokeWidth="1.5"
					strokeLinecap="round"
					strokeLinejoin="round"
				/>
			</svg>
		</span>
	);
}

/**
 * Editor preview for the docs navigation tree.
 *
 * Shows top-level terms; expands the first section so nested styles are visible.
 *
 * @param {Object} props            Component props.
 * @param {Object} props.attributes Block attributes.
 * @return {Object|null} Preview markup.
 */
export default function SidebarNavPreview( { attributes } ) {
	const taxonomy = attributes?.taxonomy || 'kb_cat';
	const postType = attributes?.postType || 'kb_article';
	const sectionOrderBy = attributes?.sectionOrderBy || 'name';
	const sectionOrder = attributes?.sectionOrder || 'asc';
	const articleOrderBy = attributes?.articleOrderBy || 'menu_order';
	const articleOrder = attributes?.articleOrder || 'asc';
	const includeChildren = attributes?.includeChildren !== false;

	const sectionQuery = {
		per_page: 100,
		parent: 0,
		orderby: sectionOrderBy,
		order: sectionOrder,
		hide_empty: false,
	};

	const {
		records: sections,
		isResolving: isResolvingSections,
	} = useEntityRecords( 'taxonomy', taxonomy, sectionQuery, {
		enabled: !! taxonomy,
	} );

	const previewSectionId =
		Array.isArray( sections ) && sections.length > 0
			? sections[ 0 ].id || sections[ 0 ].term_id
			: 0;

	const childTermQuery = {
		per_page: 100,
		parent: previewSectionId,
		orderby: sectionOrderBy,
		order: sectionOrder,
		hide_empty: false,
	};

	const {
		records: childTerms,
		isResolving: isResolvingChildTerms,
	} = useEntityRecords( 'taxonomy', taxonomy, childTermQuery, {
		enabled:
			includeChildren && previewSectionId > 0 && !! taxonomy,
	} );

	const articleQuery = {
		per_page: 100,
		orderby: getArticleRestOrderBy( articleOrderBy ),
		order: articleOrder,
		status: 'publish',
	};

	if ( previewSectionId > 0 ) {
		articleQuery[ taxonomy ] = previewSectionId;
	}

	const {
		records: articles,
		isResolving: isResolvingArticles,
	} = useEntityRecords( 'postType', postType, articleQuery, {
		enabled: previewSectionId > 0 && !! postType && !! taxonomy,
	} );

	if ( isResolvingSections ) {
		return (
			<p className="a8csp-kbdocs-sidebar-preview-status">
				{ __( 'Loading navigation preview…', 'kb-docs' ) }
			</p>
		);
	}

	if ( ! Array.isArray( sections ) || sections.length < 1 ) {
		return (
			<p className="a8csp-kbdocs-sidebar-preview-status">
				{ __( 'No top-level terms found for this taxonomy.', 'kb-docs' ) }
			</p>
		);
	}

	const previewChildTerms =
		includeChildren && Array.isArray( childTerms ) ? childTerms : [];
	const previewArticles = Array.isArray( articles ) ? articles : [];
	const isResolvingChildren =
		( includeChildren && isResolvingChildTerms ) || isResolvingArticles;

	return (
		<nav
			className="a8csp-kbdocs-sidebar a8csp-kbdocs-sidebar-preview"
			aria-label={ __( 'Docs navigation preview', 'kb-docs' ) }
		>
			<div className="a8csp-kbdocs-sidebar-inner">
				<ul className="a8csp-kbdocs-sidebar-sections">
					{ sections.map( ( section, sectionIndex ) => {
						const isActiveSection = sectionIndex === 0;
						const sectionKey =
							section?.id || section?.term_id || sectionIndex;
						const sectionName =
							typeof section?.name === 'string'
								? decodeEntities( section.name )
								: '';
						const hasChildren =
							isActiveSection &&
							( previewChildTerms.length > 0 ||
								previewArticles.length > 0 ||
								isResolvingChildren );

						const termClassName = [
							'a8csp-kbdocs-sidebar-term',
							hasChildren ? 'has-children' : '',
							isActiveSection && hasChildren
								? 'is-expanded'
								: '',
						]
							.filter( Boolean )
							.join( ' ' );

						return (
							<li key={ sectionKey } className={ termClassName }>
								<span
									className={ [
										'a8csp-kbdocs-sidebar-term-link',
										isActiveSection ? 'is-active' : '',
									]
										.filter( Boolean )
										.join( ' ' ) }
								>
									<span className="a8csp-kbdocs-sidebar-term-label">
										{ sectionName ||
											__(
												'(Untitled section)',
												'kb-docs'
											) }
									</span>
									{ hasChildren && <PreviewChevron /> }
								</span>
								{ isActiveSection && hasChildren && (
									<ul className="a8csp-kbdocs-sidebar-children">
										{ isResolvingChildren && (
											<li>
												{ __(
													'Loading articles…',
													'kb-docs'
												) }
											</li>
										) }
										{ ! isResolvingChildren &&
											previewChildTerms.map(
												( childTerm, index ) => {
													const childName =
														typeof childTerm?.name ===
														'string'
															? decodeEntities(
																	childTerm.name
															  )
															: '';

													return (
														<li
															key={
																childTerm?.id ||
																childTerm?.term_id ||
																`child-${ index }`
															}
															className="a8csp-kbdocs-sidebar-term has-children"
														>
															<span className="a8csp-kbdocs-sidebar-term-link">
																<span className="a8csp-kbdocs-sidebar-term-label">
																	{ childName ||
																		__(
																			'(Untitled section)',
																			'kb-docs'
																		) }
																</span>
																<PreviewChevron />
															</span>
														</li>
													);
												}
											) }
										{ ! isResolvingChildren &&
											previewArticles.map(
												( article, index ) => {
													const articleTitle =
														getPostTitle(
															article
														);

													return (
														<li
															key={
																article?.id ||
																index
															}
															className="a8csp-kbdocs-sidebar-article"
														>
															<span
																className={
																	index === 0
																		? 'is-active'
																		: undefined
																}
															>
																{ articleTitle ||
																	__(
																		'(No title)',
																		'kb-docs'
																	) }
															</span>
														</li>
													);
												}
											) }
										{ ! isResolvingChildren &&
											previewChildTerms.length < 1 &&
											previewArticles.length < 1 && (
												<li>
													{ __(
														'No articles in this section.',
														'kb-docs'
													) }
												</li>
											) }
									</ul>
								) }
							</li>
						);
					} ) }
				</ul>
			</div>
		</nav>
	);
}
