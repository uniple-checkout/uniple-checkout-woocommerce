/**
 * WC Blocks payment method registration for uniple checkout.
 *
 * Redirect-only gateway: presents label + description only, no card field.
 * Actual payment is processed in PHP `process_payment()` → redirect to uniple.
 */
( function () {
	const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
	const { getSetting } = window.wc.wcSettings;
	const { decodeEntities } = window.wp.htmlEntities;
	const { createElement } = window.wp.element;

	const settings = getSetting( 'uniple_data', {} );
	const label = decodeEntities( settings.label || 'uniple checkout (JPYC)' );
	const description = decodeEntities(
		settings.description ||
			'Pay with JPYC via uniple hosted checkout.'
	);

	const Content = () => createElement( 'div', null, description );
	const Label = () => createElement( 'span', null, label );

	registerPaymentMethod( {
		name: 'uniple',
		label: createElement( Label, null ),
		content: createElement( Content, null ),
		edit: createElement( Content, null ),
		canMakePayment: () => true,
		ariaLabel: label,
		supports: {
			features: settings.supports || [ 'products' ],
		},
	} );
} )();
