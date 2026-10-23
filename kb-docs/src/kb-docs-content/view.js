import { store, withSyncEvent } from '@wordpress/interactivity';

const getAnchorFromEvent = ( event ) => {
	if ( event.currentTarget instanceof HTMLAnchorElement ) {
		return event.currentTarget;
	}

	if ( event.target instanceof Element ) {
		return event.target.closest( 'a' );
	}

	return null;
};

const isModifiedEvent = ( event ) =>
	event.metaKey || event.ctrlKey || event.shiftKey || event.altKey;

/**
 * Whether the router can open this link: same-site KB Docs pages only.
 * Other origins can't be pushState'd, non-docs pages lack the KB Docs regions,
 * and the router passes the fragment to querySelector().
 *
 * @param {HTMLAnchorElement} anchor  Link.
 * @param {Object}            [config] state.clientNavigation from the server.
 * @return {boolean} Whether to use client navigation.
 */
const canClientNavigate = ( anchor, config ) => {
	if ( anchor.origin !== window.location.origin ) {
		return false;
	}

	const path = anchor.pathname.replace( /\/?$/, '/' );
	const basePath = config?.basePath || '/';
	const excludedPaths = Array.isArray( config?.excludedPaths )
		? config.excludedPaths
		: [];

	if (
		! path.startsWith( basePath ) ||
		excludedPaths.some( ( excluded ) => path.startsWith( excluded ) )
	) {
		return false;
	}

	if ( anchor.hash ) {
		// Same-page anchors: let the browser scroll.
		if ( anchor.pathname === window.location.pathname ) {
			return false;
		}

		try {
			document.querySelector( anchor.hash );
		} catch {
			return false;
		}
	}

	return true;
};

const { state, actions: storeActions } = store( 'a8cKBDocs', {
	actions: {
		prefetch: function* ( event ) {
			const anchor = getAnchorFromEvent( event );
			if (
				! anchor ||
				anchor.target === '_blank' ||
				! canClientNavigate( anchor, state.clientNavigation )
			) {
				return;
			}

			const { actions } = yield import( '@wordpress/interactivity-router' );
			yield actions.prefetch( anchor.href );
		},

		navigate: withSyncEvent( function* ( event ) {
			const anchor = getAnchorFromEvent( event );
			if ( ! anchor ) {
				return;
			}

			if (
				isModifiedEvent( event ) ||
				anchor.target === '_blank' ||
				anchor.hasAttribute( 'download' ) ||
				! canClientNavigate( anchor, state.clientNavigation )
			) {
				return;
			}

			event.preventDefault();

			const { actions } = yield import( '@wordpress/interactivity-router' );
			const prefersReducedMotion = window.matchMedia(
				'(prefers-reduced-motion: reduce)'
			).matches;

			yield actions.navigate( anchor.href );

			// Close the mobile docs sidebar after client navigation. The
			// sidebar action also lifts `inert` so the heading can take focus.
			if ( typeof storeActions.closeMobileSidebar === 'function' ) {
				storeActions.closeMobileSidebar();
			} else {
				state.isMobileSidebarOpen = false;
				document.documentElement.classList.remove(
					'a8csp-kbdocs-sidebar-open'
				);
			}

			const content = document.getElementById( 'a8csp-kbdocs-content' );
			if ( content ) {
				// Focus the new page heading so screen readers announce it;
				// fall back to the content region if there is none.
				const heading = content.querySelector( 'h1' );
				const focusTarget = heading || content;

				if ( heading && ! heading.hasAttribute( 'tabindex' ) ) {
					heading.setAttribute( 'tabindex', '-1' );
				}

				focusTarget.focus( { preventScroll: true } );
				window.scrollTo( { top: 0, behavior: 'instant' } );

				if ( ! prefersReducedMotion ) {
					content.classList.add( 'is-entering' );
					content.addEventListener(
						'animationend',
						() => content.classList.remove( 'is-entering' ),
						{ once: true }
					);
				}
			}

			document.dispatchEvent(
				new CustomEvent( 'a8c:page:navigated' )
			);
		} ),
	},
} );
