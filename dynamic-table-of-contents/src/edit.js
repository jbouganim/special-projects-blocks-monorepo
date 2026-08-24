import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	ToggleControl,
	RangeControl,
} from '@wordpress/components';

const DEFAULT_CONTENT_CLASS_NAME = 'wp-block-post-content';
const DEFAULT_ACCORDION_BREAKPOINT = 600;

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @param {Object}   root0               The component props.
 * @param {Object}   root0.attributes    The block attributes.
 * @param {Function} root0.setAttributes Function to update block attributes.
 * @return {Element} Element to render.
 */
export default function Edit( { attributes, setAttributes } ) {
	const {
		title,
		includeH1,
		includeH2,
		includeH3,
		includeH4,
		includeH5,
		includeH6,
		contentClassName,
		accordionMode,
		accordionBreakpoint,
		accordionDefaultOpen,
		enableDeepLinks,
	} = attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __(
						'Accordion',
						'dynamic-table-of-contents'
					) }
					initialOpen={ true }
				>
					<ToggleControl
						label={ __(
							'Enable accordion mode',
							'dynamic-table-of-contents'
						) }
						help={ __(
							'Collapse the table of contents into an accordion at and below the breakpoint.',
							'dynamic-table-of-contents'
						) }
						checked={ !! accordionMode }
						onChange={ ( newAccordionMode ) =>
							setAttributes( { accordionMode: newAccordionMode } )
						}
					/>
					{ !! accordionMode && (
						<>
							<ToggleControl
								label={ __(
									'Open by default',
									'dynamic-table-of-contents'
								) }
								help={ __(
									'When enabled, the accordion starts expanded. When disabled, it starts collapsed.',
									'dynamic-table-of-contents'
								) }
								checked={ !! accordionDefaultOpen }
								onChange={ ( newAccordionDefaultOpen ) =>
									setAttributes( {
										accordionDefaultOpen:
											newAccordionDefaultOpen,
									} )
								}
							/>
							<RangeControl
								label={ __(
									'Accordion breakpoint (px)',
									'dynamic-table-of-contents'
								) }
								help={ __(
									'Accordion at this width and below. Set to 0 for accordion at all screen sizes.',
									'dynamic-table-of-contents'
								) }
								value={
									typeof accordionBreakpoint === 'number'
										? accordionBreakpoint
										: DEFAULT_ACCORDION_BREAKPOINT
								}
								onChange={ ( newBreakpoint ) =>
									setAttributes( {
										accordionBreakpoint:
											typeof newBreakpoint === 'number'
												? newBreakpoint
												: DEFAULT_ACCORDION_BREAKPOINT,
									} )
								}
								min={ 0 }
								max={ 1920 }
								step={ 1 }
							/>
						</>
					) }
				</PanelBody>
				<PanelBody
					title={ __(
						'Deep Links',
						'dynamic-table-of-contents'
					) }
					initialOpen={ false }
				>
					<ToggleControl
						label={ __(
							'Enable deep links',
							'dynamic-table-of-contents'
						) }
						help={ __(
							'Show a copy-link icon on captured headings in the content when hovered.',
							'dynamic-table-of-contents'
						) }
						checked={ !! enableDeepLinks }
						onChange={ ( newEnableDeepLinks ) =>
							setAttributes( {
								enableDeepLinks: newEnableDeepLinks,
							} )
						}
					/>
				</PanelBody>
				<PanelBody
					title={ __(
						'Heading Levels',
						'dynamic-table-of-contents'
					) }
					initialOpen={ true }
				>
					<ToggleControl
						label={ __(
							'Include H1 headings',
							'dynamic-table-of-contents'
						) }
						checked={ includeH1 }
						onChange={ ( newIncludeH1 ) =>
							setAttributes( { includeH1: newIncludeH1 } )
						}
					/>
					<ToggleControl
						label={ __(
							'Include H2 headings',
							'dynamic-table-of-contents'
						) }
						checked={ includeH2 }
						onChange={ ( newIncludeH2 ) =>
							setAttributes( { includeH2: newIncludeH2 } )
						}
					/>
					<ToggleControl
						label={ __(
							'Include H3 headings',
							'dynamic-table-of-contents'
						) }
						checked={ includeH3 }
						onChange={ ( newIncludeH3 ) =>
							setAttributes( { includeH3: newIncludeH3 } )
						}
					/>
					<ToggleControl
						label={ __(
							'Include H4 headings',
							'dynamic-table-of-contents'
						) }
						checked={ includeH4 }
						onChange={ ( newIncludeH4 ) =>
							setAttributes( { includeH4: newIncludeH4 } )
						}
					/>
					<ToggleControl
						label={ __(
							'Include H5 headings',
							'dynamic-table-of-contents'
						) }
						checked={ includeH5 }
						onChange={ ( newIncludeH5 ) =>
							setAttributes( { includeH5: newIncludeH5 } )
						}
					/>
					<ToggleControl
						label={ __(
							'Include H6 headings',
							'dynamic-table-of-contents'
						) }
						checked={ includeH6 }
						onChange={ ( newIncludeH6 ) =>
							setAttributes( { includeH6: newIncludeH6 } )
						}
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Content Source', 'dynamic-table-of-contents' ) }
					initialOpen={ false }
				>
					<TextControl
						label={ __(
							'Content class name',
							'dynamic-table-of-contents'
						) }
						help={ __(
							'Headings are found inside this class. Leave blank to use the default content class.',
							'dynamic-table-of-contents'
						) }
						placeholder={ DEFAULT_CONTENT_CLASS_NAME }
						value={ contentClassName || '' }
						onChange={ ( newContentClassName ) =>
							setAttributes( {
								contentClassName: newContentClassName,
							} )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps() }>
				<RichText
					tagName="h2"
					placeholder={ __(
						'Table of Contents Title',
						'dynamic-table-of-contents'
					) }
					value={ title }
					onChange={ ( newTitle ) =>
						setAttributes( { title: newTitle } )
					}
					className="table-of-contents-title"
				/>
				<ul className="table-of-contents-list">
					<li className="table-of-contents-list-item">
						{ /* eslint-disable-next-line jsx-a11y/anchor-is-valid -- Not a valid link, just preview. */ }
						<a href="#" className="active">
							This is an example.
						</a>
					</li>
					<li className="table-of-contents-list-item">
						{ /* eslint-disable-next-line jsx-a11y/anchor-is-valid -- Not a valid link, just preview. */ }
						<a href="#">Of the block appearance.</a>
					</li>
					<li className="table-of-contents-list-item">
						{ /* eslint-disable-next-line jsx-a11y/anchor-is-valid -- Not a valid link, just preview. */ }
						<a href="#">When used in your post.</a>
					</li>
				</ul>
			</div>
		</>
	);
}
