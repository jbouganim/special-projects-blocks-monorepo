/**
 * Resolve block gap attribute value to a CSS gap value.
 *
 * @param {Object|string|undefined} blockGap Block gap attribute value.
 * @param {string}                    fallback Fallback gap value.
 * @return {string|undefined} CSS gap value.
 */
export function getBlockGapCssValue( blockGap, fallback = '0.5rem' ) {
	if ( blockGap === undefined || blockGap === null || blockGap === '' ) {
		return undefined;
	}

	const resolveSpacingValue = ( value ) => {
		if ( value === 0 || value === '0' ) {
			return '0';
		}

		if ( typeof value !== 'string' || value === '' ) {
			return fallback;
		}

		const presetMatch = value.match( /^var:preset\|spacing\|(.+)$/ );
		if ( presetMatch ) {
			const slug = presetMatch[ 1 ];

			// Theme has no spacing--0 preset; "None" stores var:preset|spacing|0.
			if ( slug === '0' ) {
				return '0';
			}

			return `var(--wp--preset--spacing--${ slug })`;
		}

		return value;
	};

	if ( blockGap === 0 || blockGap === '0' ) {
		return '0';
	}

	if ( typeof blockGap === 'string' ) {
		return resolveSpacingValue( blockGap );
	}

	if ( typeof blockGap === 'object' ) {
		const row = resolveSpacingValue( blockGap.top );
		const column = resolveSpacingValue(
			blockGap.left !== undefined && blockGap.left !== null && blockGap.left !== ''
				? blockGap.left
				: blockGap.top
		);

		return row === column ? row : `${ row } ${ column }`;
	}

	return undefined;
}
