/**
 * External dependencies
 */
import TurndownService from 'turndown';
import { gfm } from 'turndown-plugin-gfm';

/**
 * WordPress dependencies
 */
import { store, getContext } from '@wordpress/interactivity';

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
 * Copy text to the clipboard, with a legacy fallback.
 *
 * @param {string} text Text to copy.
 * @return {Promise<boolean>} Whether the copy succeeded.
 */
async function writeClipboardText( text ) {
	try {
		await navigator.clipboard.writeText( text );
		return true;
	} catch ( error ) {
		const textarea = document.createElement( 'textarea' );
		textarea.value = text;
		textarea.setAttribute( 'readonly', '' );
		textarea.style.position = 'absolute';
		textarea.style.left = '-9999px';
		document.body.appendChild( textarea );
		textarea.select();

		try {
			return document.execCommand( 'copy' );
		} finally {
			textarea.remove();
		}
	}
}

store( 'a8csp/copy-to-markdown', {
	state: {
		get ariaLabel() {
			const context = getContext();
			return context.isCopied ? context.copiedLabel : context.label;
		},
	},
	actions: {
		*copy() {
			const context = getContext();

			if ( context.isCopied ) {
				return;
			}

			const markdown = htmlToMarkdown( context.contentSelector );

			if ( markdown === '' ) {
				return;
			}

			const didCopy = yield writeClipboardText( markdown );

			if ( ! didCopy ) {
				return;
			}

			context.isCopied = true;

			yield new Promise( ( resolve ) => {
				window.setTimeout( resolve, SUCCESS_DURATION_MS );
			} );

			context.isCopied = false;
		},
	},
} );
