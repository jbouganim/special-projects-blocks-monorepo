/**
 * Copy (clipboard) icon.
 *
 * @return {Object} SVG element.
 */
export function CopyIcon() {
	return (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			width="16"
			height="16"
			viewBox="0 0 16 16"
			fill="none"
			aria-hidden="true"
			focusable="false"
		>
			<path
				d="M13.3333 6H7.33333C6.59695 6 6 6.59695 6 7.33333V13.3333C6 14.0697 6.59695 14.6667 7.33333 14.6667H13.3333C14.0697 14.6667 14.6667 14.0697 14.6667 13.3333V7.33333C14.6667 6.59695 14.0697 6 13.3333 6Z"
				stroke="currentColor"
				strokeWidth="1.33333"
				strokeLinecap="round"
				strokeLinejoin="round"
			/>
			<path
				d="M3.33398 10.0002H2.66732C2.3137 10.0002 1.97456 9.85969 1.72451 9.60964C1.47446 9.35959 1.33398 9.02045 1.33398 8.66683V2.66683C1.33398 2.31321 1.47446 1.97407 1.72451 1.72402C1.97456 1.47397 2.3137 1.3335 2.66732 1.3335H8.66732C9.02094 1.3335 9.36008 1.47397 9.61013 1.72402C9.86018 1.97407 10.0007 2.31321 10.0007 2.66683V3.3335"
				stroke="currentColor"
				strokeWidth="1.33333"
				strokeLinecap="round"
				strokeLinejoin="round"
			/>
		</svg>
	);
}

/**
 * Checkmark icon shown after a successful copy.
 *
 * @return {Object} SVG element.
 */
export function CheckIcon() {
	return (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			width="16"
			height="16"
			viewBox="0 0 16 16"
			fill="none"
			aria-hidden="true"
			focusable="false"
		>
			<path
				fill="currentColor"
				fillRule="evenodd"
				d="m15.56 4-.53.53-8.8 8.8c-.68.68-1.78.68-2.47 0l.53-.54-.53.53-2.79-2.79L.44 10 1.5 8.94l.53.53 2.8 2.8c.1.09.25.09.35 0l8.79-8.8.53-.53z"
				clipRule="evenodd"
			/>
		</svg>
	);
}
