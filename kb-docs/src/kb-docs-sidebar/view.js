/**
 * WordPress dependencies
 */
import {
	store,
	getContext,
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
 * Sync the document class used for scroll locking.
 *
 * @param {boolean} isOpen Whether the mobile sidebar is open.
 */
const setMobileSidebarDocumentState = ( isOpen ) => {
	document.documentElement.classList.toggle(
		'a8csp-kbdocs-sidebar-open',
		isOpen === true
	);
};

const { state } = store( 'a8cKBDocs', {
	state: {
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
