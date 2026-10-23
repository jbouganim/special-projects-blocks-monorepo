/**
 * WordPress dependencies
 */
import {
	store,
	getContext,
	getElement,
	withSyncEvent,
} from '@wordpress/interactivity';

/**
 * Ensure expandedTermIds exists without wiping PHP-provided values.
 *
 * Declaring `expandedTermIds: {}` in store state would override
 * `wp_interactivity_state()` on hydration.
 *
 * @return {Object} Expanded term ID map.
 */
const getExpandedTermIds = () => {
	if (
		! state.expandedTermIds ||
		typeof state.expandedTermIds !== 'object'
	) {
		state.expandedTermIds = {};
	}

	return state.expandedTermIds;
};

/**
 * Elements made inert while the mobile drawer is open, and the element that
 * had focus before it opened (normally the breadcrumb trigger).
 */
let inertElements = [];
let focusBeforeOpen = null;
let wasMobileSidebarOpen = false;

/**
 * Make everything outside the drawer inert so keyboard and screen reader
 * users cannot reach content hidden behind the panel and backdrop.
 *
 * Walks up from the sidebar block and marks the siblings at each level,
 * so it works wherever the block sits (e.g. nested inside <main>).
 * Live regions stay reachable so router announcements are still spoken.
 *
 * @param {Element} root Sidebar block wrapper.
 */
const inertBackground = ( root ) => {
	let node = root;

	while ( node && node.parentElement && node !== document.body ) {
		for ( const sibling of node.parentElement.children ) {
			if (
				sibling === node ||
				sibling.inert ||
				[ 'SCRIPT', 'STYLE', 'TEMPLATE', 'LINK' ].includes(
					sibling.tagName
				) ||
				sibling.matches( '[aria-live], .a11y-speak-region' ) ||
				sibling.querySelector( '.a11y-speak-region' )
			) {
				continue;
			}

			sibling.inert = true;
			inertElements.push( sibling );
		}

		node = node.parentElement;
	}
};

/**
 * Undo inertBackground().
 */
const restoreBackground = () => {
	inertElements.forEach( ( element ) => {
		element.inert = false;
	} );
	inertElements = [];
};

/**
 * Sync the document class used for scroll locking.
 *
 * Closing also lifts `inert` right away (not in the watch callback) so
 * callers can move focus into the page immediately afterwards.
 *
 * @param {boolean} isOpen Whether the mobile sidebar is open.
 */
const setMobileSidebarDocumentState = ( isOpen ) => {
	document.documentElement.classList.toggle(
		'a8csp-kbdocs-sidebar-open',
		isOpen === true
	);

	if ( isOpen !== true ) {
		restoreBackground();
	}
};

const { state } = store( 'a8cKBDocs', {
	state: {
		/**
		 * Dialog semantics apply only while the drawer is open; on desktop
		 * the same panel is a static sidebar.
		 *
		 * @return {string|null} Attribute value, or null to remove it.
		 */
		get mobileSidebarRole() {
			return state.isMobileSidebarOpen === true ? 'dialog' : null;
		},
		get mobileSidebarModal() {
			return state.isMobileSidebarOpen === true ? 'true' : null;
		},
		get mobileSidebarLabel() {
			return state.isMobileSidebarOpen === true
				? state.mobileSidebarDialogLabel
				: null;
		},
		get isTermExpanded() {
			const context = getContext();
			const termId = context?.termId;

			if ( termId === undefined || termId === null ) {
				return false;
			}

			const expandedTermIds = state.expandedTermIds;

			if (
				! expandedTermIds ||
				typeof expandedTermIds !== 'object'
			) {
				return false;
			}

			return expandedTermIds[ String( termId ) ] === true;
		},
	},
	actions: {
		toggleTerm: withSyncEvent( function ( event ) {
			const context = getContext();
			const termId = context?.termId;

			if ( termId === undefined || termId === null ) {
				return;
			}

			event.preventDefault();

			const expandedTermIds = getExpandedTermIds();
			const key = String( termId );
			expandedTermIds[ key ] = expandedTermIds[ key ] !== true;
		} ),

		openMobileSidebar: withSyncEvent( function ( event ) {
			if ( event && typeof event.preventDefault === 'function' ) {
				event.preventDefault();
			}

			state.isMobileSidebarOpen = true;
			setMobileSidebarDocumentState( true );
		} ),

		closeMobileSidebar: withSyncEvent( function ( event ) {
			if ( event && typeof event.preventDefault === 'function' ) {
				event.preventDefault();
			}

			state.isMobileSidebarOpen = false;
			setMobileSidebarDocumentState( false );
		} ),

		toggleMobileSidebar: withSyncEvent( function ( event ) {
			if ( event && typeof event.preventDefault === 'function' ) {
				event.preventDefault();
			}

			const isOpen = state.isMobileSidebarOpen !== true;
			state.isMobileSidebarOpen = isOpen;
			setMobileSidebarDocumentState( isOpen );
		} ),

		handleMobileSidebarKeydown: withSyncEvent( function ( event ) {
			if ( event.key !== 'Escape' ) {
				return;
			}

			if ( state.isMobileSidebarOpen !== true ) {
				return;
			}

			state.isMobileSidebarOpen = false;
			setMobileSidebarDocumentState( false );
		} ),
	},
	callbacks: {
		/**
		 * Manage focus and background inertness when the drawer opens/closes.
		 *
		 * Runs for every way of closing (Escape, backdrop, close button,
		 * breakpoint change, client navigation from a drawer link).
		 */
		syncMobileSidebarFocus() {
			const isOpen = state.isMobileSidebarOpen === true;

			if ( isOpen === wasMobileSidebarOpen ) {
				return;
			}

			wasMobileSidebarOpen = isOpen;

			const { ref } = getElement();

			if ( ! ref ) {
				return;
			}

			if ( isOpen ) {
				focusBeforeOpen = ref.ownerDocument.activeElement;
				inertBackground( ref );
				ref.querySelector( '.a8csp-kbdocs-sidebar-close' )?.focus( {
					preventScroll: true,
				} );
				return;
			}

			restoreBackground();

			// Only pull focus back when it was left in the drawer; after a
			// client navigation, focus has already moved to the new page.
			const active = ref.ownerDocument.activeElement;

			if (
				active &&
				active !== document.body &&
				! ref.contains( active )
			) {
				return;
			}

			const trigger =
				focusBeforeOpen && focusBeforeOpen.isConnected
					? focusBeforeOpen
					: document.querySelector( '.a8csp-kbdocs-sidebar-trigger' );

			trigger?.focus( { preventScroll: true } );
			focusBeforeOpen = null;
		},

		syncExpandedFromServer() {
			const context = getContext();
			const serverExpandedTermIds = context?.serverExpandedTermIds;

			if ( ! Array.isArray( serverExpandedTermIds ) ) {
				return;
			}

			const expandedTermIds = getExpandedTermIds();

			serverExpandedTermIds.forEach( ( termId ) => {
				expandedTermIds[ String( termId ) ] = true;
			} );
		},
	},
} );

/**
 * While resizing across the mobile breakpoint, suppress drawer transitions.
 * Desktop has no off-canvas transform; mobile closed uses translateX(-105%).
 * With a transition enabled, the browser animates that jump (flash of open drawer).
 */
const MOBILE_SIDEBAR_MQ = '(max-width: 959px)';
const RESIZING_CLASS = 'a8csp-kbdocs-sidebar-resizing';
const RESIZING_MS = 150;

let resizeTimer = 0;

/**
 * Toggle the document class that disables mobile drawer transitions.
 *
 * @param {boolean} isResizing Whether the viewport is mid-resize.
 */
const setSidebarResizing = ( isResizing ) => {
	document.documentElement.classList.toggle(
		RESIZING_CLASS,
		isResizing === true
	);
};

/**
 * Mark resizing, then clear after the viewport settles.
 */
const pulseSidebarResizing = () => {
	setSidebarResizing( true );
	window.clearTimeout( resizeTimer );
	resizeTimer = window.setTimeout( () => {
		setSidebarResizing( false );
	}, RESIZING_MS );
};

/**
 * Close the mobile drawer when the breakpoint flips; suppress transition flash.
 */
const onMobileSidebarViewportChange = () => {
	pulseSidebarResizing();

	if ( state.isMobileSidebarOpen === true ) {
		state.isMobileSidebarOpen = false;
		setMobileSidebarDocumentState( false );
	}
};

if ( typeof window !== 'undefined' ) {
	window.addEventListener( 'resize', pulseSidebarResizing, {
		passive: true,
	} );

	if ( typeof window.matchMedia === 'function' ) {
		const mobileSidebarMq = window.matchMedia( MOBILE_SIDEBAR_MQ );

		if ( typeof mobileSidebarMq.addEventListener === 'function' ) {
			mobileSidebarMq.addEventListener(
				'change',
				onMobileSidebarViewportChange
			);
		} else if ( typeof mobileSidebarMq.addListener === 'function' ) {
			mobileSidebarMq.addListener( onMobileSidebarViewportChange );
		}
	}
}
