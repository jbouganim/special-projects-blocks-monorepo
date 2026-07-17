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

const { state } = store( 'a8cKBDocs', {
	actions: {
		prefetch: function* ( event ) {
			const anchor = getAnchorFromEvent( event );
			if ( ! anchor || anchor.target === '_blank' ) {
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
				anchor.hasAttribute( 'download' )
			) {
				return;
			}

			event.preventDefault();

			const { actions } = yield import( '@wordpress/interactivity-router' );
			const prefersReducedMotion = window.matchMedia(
				'(prefers-reduced-motion: reduce)'
			).matches;

			yield actions.navigate( anchor.href );

			// Close the mobile docs sidebar after client navigation.
			state.isMobileSidebarOpen = false;
			document.documentElement.classList.remove(
				'a8csp-kbdocs-sidebar-open'
			);

			const content = document.getElementById( 'a8csp-kbdocs-content' );
			if ( content ) {
				content.focus( { preventScroll: true } );
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
