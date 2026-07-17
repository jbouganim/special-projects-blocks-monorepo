/**
 * External dependencies
 */
import TurndownService from 'turndown';
import { gfm } from 'turndown-plugin-gfm';

/**
 * WordPress dependencies
 */
import domReady from '@wordpress/dom-ready';

const SUCCESS_DURATION_MS = 3000;

const turndown = new TurndownService( {
	headingStyle: 'atx',
	codeBlockStyle: 'fenced',
	bulletListMarker: '-',
} );

turndown.use( gfm );

/**
 * Convert HTML from a selector into Markdown.
 *
 * @param {string} selector CSS selector for the content root.
 * @return {string} Markdown string.
 */
function htmlToMarkdown( selector ) {
	const source = document.querySelector( selector );

	if ( ! ( source instanceof Element ) ) {
		return '';
	}

	const clone = source.cloneNode( true );

	if ( clone instanceof Element ) {
		clone
			.querySelectorAll(
				'.a8csp-copy-to-markdown, .wp-block-a8csp-copy-to-markdown, script, style, noscript'
			)
			.forEach( ( node ) => node.remove() );
	}

	return turndown.turndown( clone ).trim();
}

/**
 * Show the copied success state, then restore the default label/icon.
 * Icon and label both transition via the shared `.is-copied` CSS class.
 *
 * @param {HTMLButtonElement} button Button element.
 */
function showCopiedState( button ) {
	const defaultAria =
		button.getAttribute( 'data-label' ) ||
		button.getAttribute( 'aria-label' ) ||
		'';
	const copiedLabel =
		button.getAttribute( 'data-copied-label' ) || 'Copied';

	button.classList.add( 'is-copied' );
	button.setAttribute( 'aria-label', copiedLabel );

	window.setTimeout( () => {
		button.classList.remove( 'is-copied' );
		button.setAttribute( 'aria-label', defaultAria );
	}, SUCCESS_DURATION_MS );
}

/**
 * Handle copy button click.
 *
 * @param {MouseEvent} event Click event.
 * @return {Promise<void>}
 */
async function onCopyClick( event ) {
	const button = event.currentTarget;

	if ( ! ( button instanceof HTMLButtonElement ) ) {
		return;
	}

	if ( button.classList.contains( 'is-copied' ) ) {
		return;
	}

	const selector =
		button.getAttribute( 'data-content-selector' ) || '.entry-content';
	const markdown = htmlToMarkdown( selector );

	if ( markdown === '' ) {
		return;
	}

	try {
		await navigator.clipboard.writeText( markdown );
		showCopiedState( button );
	} catch ( error ) {
		// Fallback for older browsers / insecure contexts.
		const textarea = document.createElement( 'textarea' );
		textarea.value = markdown;
		textarea.setAttribute( 'readonly', '' );
		textarea.style.position = 'absolute';
		textarea.style.left = '-9999px';
		document.body.appendChild( textarea );
		textarea.select();

		try {
			document.execCommand( 'copy' );
			showCopiedState( button );
		} finally {
			textarea.remove();
		}
	}
}

domReady( () => {
	document
		.querySelectorAll( '.wp-block-a8csp-copy-to-markdown' )
		.forEach( ( button ) => {
			if ( ! ( button instanceof HTMLButtonElement ) ) {
				return;
			}

			button.addEventListener( 'click', onCopyClick );
		} );
} );
