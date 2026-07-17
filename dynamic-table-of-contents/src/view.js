/* global IntersectionObserver */
( function () {
	const tocSelector = '.wp-block-wpcomsp-dynamic-table-of-contents';
	const deepLinkClass = 'toc-heading-deep-link';
	const deepLinkCopiedClass = 'is-copied';
	const copyFeedbackMs = 3000;

	const linkIconSvg =
		'<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true" focusable="false"><path d="M9.05029 12.5732L10.1954 11.4205C9.29136 11.3377 8.65855 11.0514 8.19147 10.5843C6.91832 9.31118 6.91832 7.50314 8.18394 6.23751L10.6851 3.7364C11.9582 2.46324 13.7588 2.46324 15.0319 3.7364C16.3126 5.02462 16.305 6.8176 15.0395 8.08322L13.7436 9.37897C13.9847 9.92892 14.06 10.5994 13.932 11.1795L16.0941 9.0249C17.9474 7.1792 17.9548 4.55754 16.0866 2.68925C14.2183 0.813406 11.5966 0.828474 9.7509 2.67418L7.12925 5.30336C5.28355 7.14907 5.26848 9.77072 7.13679 11.639C7.58126 12.076 8.1764 12.4074 9.05029 12.5732ZM9.71324 6.22245L8.57568 7.37507C9.47217 7.45793 10.1125 7.75175 10.5721 8.21129C11.8527 9.49197 11.8528 11.2925 10.5796 12.5581L8.07847 15.0592C6.80532 16.3323 5.01235 16.3323 3.73165 15.0592C2.45096 13.7785 2.4585 11.978 3.73165 10.7124L5.01989 9.41664C4.78634 8.8667 4.70347 8.20375 4.83154 7.61614L2.67697 9.77072C0.82373 11.6164 0.816197 14.2456 2.6845 16.1139C4.5528 17.9822 7.17446 17.9671 9.02015 16.1215L11.6343 13.4998C13.4875 11.6466 13.4951 9.0249 11.6267 7.16414C11.1898 6.71965 10.5947 6.38818 9.71324 6.22245Z" fill="currentColor"/></svg>';

	const checkIconSvg =
		'<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" focusable="false"><path fill="currentColor" fill-rule="evenodd" d="m15.56 4-.53.53-8.8 8.8c-.68.68-1.78.68-2.47 0l.53-.54-.53.53-2.79-2.79L.44 10 1.5 8.94l.53.53 2.8 2.8c.1.09.25.09.35 0l8.79-8.8.53-.53z" clip-rule="evenodd"/></svg>';

	const escapeSelector =
		window.CSS && window.CSS.escape
			? window.CSS.escape
			: ( value ) => value.replace( /[^a-zA-Z0-9_-]/g, '\\$&' );

	/**
	 * Get heading label text without the deep-link control.
	 *
	 * @param {HTMLElement} heading Heading element.
	 * @return {string} Clean heading label.
	 */
	const getHeadingLabel = ( heading ) => {
		const clone = heading.cloneNode( true );
		clone
			.querySelectorAll( `.${ deepLinkClass }` )
			.forEach( ( deepLink ) => {
				deepLink.remove();
			} );

		return clone.textContent ? clone.textContent.trim() : '';
	};

	/**
	 * Build the absolute URL for a heading anchor.
	 *
	 * @param {string} id Heading id.
	 * @return {string} Absolute URL including hash.
	 */
	const getHeadingDeepLinkUrl = ( id ) =>
		`${ window.location.origin }${ window.location.pathname }${ window.location.search }#${ id }`;

	/**
	 * Copy text to the clipboard.
	 *
	 * @param {string} text Text to copy.
	 * @return {Promise<void>} Resolves when copy completes.
	 */
	const copyTextToClipboard = ( text ) => {
		if (
			navigator.clipboard &&
			typeof navigator.clipboard.writeText === 'function'
		) {
			return navigator.clipboard.writeText( text );
		}

		return new Promise( ( resolve, reject ) => {
			const textarea = document.createElement( 'textarea' );
			textarea.value = text;
			textarea.setAttribute( 'readonly', '' );
			textarea.style.position = 'absolute';
			textarea.style.left = '-9999px';
			document.body.appendChild( textarea );
			textarea.select();

			try {
				const didCopy = document.execCommand( 'copy' );
				document.body.removeChild( textarea );

				if ( didCopy ) {
					resolve();
					return;
				}

				reject( new Error( 'Copy command failed.' ) );
			} catch ( error ) {
				document.body.removeChild( textarea );
				reject( error );
			}
		} );
	};

	/**
	 * Append deep-link controls to source headings captured by this TOC.
	 *
	 * @param {HTMLElement} tocBlock TOC block element.
	 * @param {NodeListOf<Element>|Element[]} headings Matching heading elements.
	 */
	const initDeepLinks = ( tocBlock, headings ) => {
		if ( tocBlock.getAttribute( 'data-enable-deep-links' ) !== 'true' ) {
			return;
		}

		headings.forEach( ( heading ) => {
			if ( ! ( heading instanceof HTMLElement ) ) {
				return;
			}

			const id = heading.id;

			if ( ! id.length ) {
				return;
			}

			// Skip headings rendered inside any TOC block instance.
			if ( heading.closest( tocSelector ) ) {
				return;
			}

			// Avoid double-appending across multiple TOC blocks / reinits.
			if ( heading.querySelector( `.${ deepLinkClass }` ) ) {
				return;
			}

			const button = document.createElement( 'button' );
			button.type = 'button';
			button.className = deepLinkClass;
			button.setAttribute( 'aria-label', 'Copy link to this section' );
			button.innerHTML = linkIconSvg;

			button.addEventListener( 'click', ( event ) => {
				event.preventDefault();
				event.stopPropagation();

				const deepLinkUrl = getHeadingDeepLinkUrl( id );
				const nextUrl = `${ window.location.pathname }${ window.location.search }#${ id }`;

				// Update the hash without scrolling to the heading.
				window.history.replaceState(
					window.history.state,
					document.title,
					nextUrl
				);

				copyTextToClipboard( deepLinkUrl )
					.catch( () => {
						// Clipboard may be unavailable; hash update still applies.
					} )
					.finally( () => {
						button.classList.add( deepLinkCopiedClass );
						button.innerHTML = checkIconSvg;
						button.setAttribute( 'aria-label', 'Link copied' );

						window.setTimeout( () => {
							button.classList.remove( deepLinkCopiedClass );
							button.innerHTML = linkIconSvg;
							button.setAttribute(
								'aria-label',
								'Copy link to this section'
							);
						}, copyFeedbackMs );
					} );
			} );

			heading.appendChild( button );
		} );
	};

	/**
	 * Whether accordion layout should be active for the given breakpoint.
	 *
	 * @param {number} breakpoint Accordion breakpoint in pixels.
	 * @return {boolean} True when accordion layout should be active.
	 */
	const shouldUseAccordionLayout = ( breakpoint ) => {
		if ( breakpoint <= 0 ) {
			return true;
		}

		return window.matchMedia( `(max-width: ${ breakpoint }px)` ).matches;
	};

	/**
	 * Whether the accordion should start open.
	 *
	 * @param {HTMLElement} tocBlock TOC block element.
	 * @return {boolean} True when the default open state is enabled.
	 */
	const getAccordionDefaultOpen = ( tocBlock ) =>
		tocBlock.getAttribute( 'data-accordion-default-open' ) === 'true';

	/**
	 * Apply the configured default open/closed state.
	 *
	 * @param {HTMLElement} tocBlock TOC block element.
	 * @param {HTMLElement|null} toggle Accordion toggle button.
	 */
	const applyAccordionDefaultOpenState = ( tocBlock, toggle ) => {
		const shouldOpen = getAccordionDefaultOpen( tocBlock );
		tocBlock.classList.toggle( 'is-accordion-open', shouldOpen );

		if ( toggle ) {
			toggle.setAttribute( 'aria-expanded', shouldOpen ? 'true' : 'false' );
		}
	};

	/**
	 * Sync accordion active state and open/closed UI with the current viewport.
	 *
	 * @param {HTMLElement} tocBlock TOC block element.
	 */
	const syncAccordionLayout = ( tocBlock ) => {
		const toggle = tocBlock.querySelector(
			'.table-of-contents-accordion-toggle'
		);
		const breakpoint = Number(
			tocBlock.getAttribute( 'data-accordion-breakpoint' )
		);
		const resolvedBreakpoint = Number.isFinite( breakpoint )
			? breakpoint
			: 600;
		const wasAccordionActive = tocBlock.classList.contains(
			'is-accordion-active'
		);
		const isAccordionActive = shouldUseAccordionLayout( resolvedBreakpoint );

		tocBlock.classList.toggle( 'is-accordion-active', isAccordionActive );

		if ( ! toggle ) {
			return;
		}

		if ( ! isAccordionActive ) {
			tocBlock.classList.add( 'is-accordion-open' );
			toggle.setAttribute( 'aria-expanded', 'true' );
			toggle.setAttribute( 'disabled', 'disabled' );
			return;
		}

		toggle.removeAttribute( 'disabled' );

		// Restoring accordion layout: reset to the configured default.
		if ( ! wasAccordionActive ) {
			applyAccordionDefaultOpenState( tocBlock, toggle );
			return;
		}

		const isOpen = tocBlock.classList.contains( 'is-accordion-open' );
		toggle.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
	};

	/**
	 * Initialize accordion toggle behavior for a TOC block.
	 *
	 * @param {HTMLElement} tocBlock TOC block element.
	 */
	const initAccordion = ( tocBlock ) => {
		if ( tocBlock.getAttribute( 'data-accordion-mode' ) !== 'true' ) {
			return;
		}

		const toggle = tocBlock.querySelector(
			'.table-of-contents-accordion-toggle'
		);

		if ( ! toggle || tocBlock.__tocAccordionInitialized ) {
			syncAccordionLayout( tocBlock );
			return;
		}

		tocBlock.__tocAccordionInitialized = true;
		applyAccordionDefaultOpenState( tocBlock, toggle );

		toggle.addEventListener( 'click', () => {
			if ( ! tocBlock.classList.contains( 'is-accordion-active' ) ) {
				return;
			}

			const willOpen = ! tocBlock.classList.contains( 'is-accordion-open' );
			tocBlock.classList.toggle( 'is-accordion-open', willOpen );
			toggle.setAttribute( 'aria-expanded', willOpen ? 'true' : 'false' );
		} );

		const breakpoint = Number(
			tocBlock.getAttribute( 'data-accordion-breakpoint' )
		);
		const resolvedBreakpoint = Number.isFinite( breakpoint )
			? breakpoint
			: 600;

		if ( resolvedBreakpoint > 0 ) {
			const mediaQuery = window.matchMedia(
				`(max-width: ${ resolvedBreakpoint }px)`
			);
			const onBreakpointChange = () => {
				syncAccordionLayout( tocBlock );
			};

			if ( typeof mediaQuery.addEventListener === 'function' ) {
				mediaQuery.addEventListener( 'change', onBreakpointChange );
			} else if ( typeof mediaQuery.addListener === 'function' ) {
				mediaQuery.addListener( onBreakpointChange );
			}
		}

		syncAccordionLayout( tocBlock );
	};

	const buildTableOfContents = ( tocBlock ) => {
		const headingList = tocBlock.querySelector( 'ul' );
		if ( ! headingList ) {
			return;
		}

		if ( tocBlock.__tocObserver ) {
			tocBlock.__tocObserver.disconnect();
		}

		headingList.innerHTML = '';

		const defaultContentClassName = 'wp-block-post-content';
		const contentClassNameData = tocBlock.getAttribute(
			'data-content-class-name'
		);
		const contentClassName =
			contentClassNameData && contentClassNameData.trim().length
				? contentClassNameData.trim().replace( /^\.+/, '' )
				: defaultContentClassName;
		const contentSelector = `.${ escapeSelector( contentClassName ) }`;

		const headingLevelsData = tocBlock.getAttribute( 'data-heading-levels' );
		const allowedHeadings =
			headingLevelsData !== null
				? headingLevelsData.split( ',' ).filter( Boolean )
				: [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ];

		if ( ! allowedHeadings.length ) {
			return;
		}

		const headingSelectors = allowedHeadings
			.map( ( level ) => `${ contentSelector } ${ level }` )
			.join( ', ' );
		const headings = document.querySelectorAll( headingSelectors );

		const observer = new IntersectionObserver(
			( entries ) => {
				const links = tocBlock.querySelectorAll( 'a' );

				entries.forEach( ( entry ) => {
					const id = entry.target.id;
					const link = tocBlock.querySelector( `a[href="#${ id }"]` );

					if ( entry.isIntersecting && link ) {
						links.forEach( ( tocLink ) => {
							tocLink.classList.remove( 'active' );
						} );

						link.classList.add( 'active' );
					}
				} );
			},
			{
				rootMargin: '0px 0px -75% 0px',
			}
		);

		tocBlock.__tocObserver = observer;

		const headingLevelStack = [];
		let tableOfContentsItemIndex = 0;
		const capturedHeadings = [];

		headings.forEach( ( heading ) => {
			if ( ! ( heading instanceof HTMLElement ) ) {
				return;
			}

			const id = heading.id;
			const headingLevel = Number( heading.tagName.replace( 'H', '' ) );

			if ( ! id.length ) {
				return;
			}

			// Never treat TOC chrome headings as content headings.
			if ( heading.closest( tocSelector ) ) {
				return;
			}

			capturedHeadings.push( heading );

			const latestListItem = document.createElement( 'li' );
			const latestLink = document.createElement( 'a' );

			while (
				headingLevelStack.length &&
				headingLevelStack[ headingLevelStack.length - 1 ] >= headingLevel
			) {
				headingLevelStack.pop();
			}

			const headingDepth = headingLevelStack.length;
			headingLevelStack.push( headingLevel );

			latestListItem.classList.add( 'table-of-contents-list-item' );
			latestLink.href = `#${ id }`;
			latestLink.textContent = getHeadingLabel( heading );

			if ( headingDepth > 0 ) {
				latestListItem.classList.add(
					'table-of-contents-list-item--nested',
					`table-of-contents-list-item--depth-${ headingDepth }`
				);
			}

			if ( 0 === tableOfContentsItemIndex ) {
				latestLink.classList.add( 'active' );
			}

			latestListItem.appendChild( latestLink );
			headingList.appendChild( latestListItem );

			observer.observe( heading );
			tableOfContentsItemIndex++;
		} );

		initDeepLinks( tocBlock, capturedHeadings );
		initAccordion( tocBlock );
	};

	const initTableOfContents = () => {
		const tocBlocks = document.querySelectorAll( tocSelector );
		if ( ! tocBlocks.length ) {
			return;
		}

		tocBlocks.forEach( ( tocBlock ) => {
			buildTableOfContents( tocBlock );
		} );
	};

	const scheduleTableOfContentsInit = () => {
		window.requestAnimationFrame( initTableOfContents );
	};

	scheduleTableOfContentsInit();
	document.addEventListener( 'a8c:page:navigated', scheduleTableOfContentsInit );
} )();
