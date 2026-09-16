let init;
let render;
const text = {
	loading: 'Loading…',
	error: 'Please try again.',
	retry: 'Retry',
	opened: 'You can close this page when you finish.',
};

function card( mode = 'standard' ) {
	const root = document.createElement( 'section' );
	root.dataset.reviewbirdGcr = JSON.stringify( {
		mode,
		google: {
			merchant_id: '1234',
			order_id: '7',
			email: 'buyer@example.com',
			delivery_country: 'US',
			estimated_delivery_date: '2026-09-20',
		},
		text,
	} );
	root.hidden = mode !== 'direct';
	root.innerHTML =
		'<p role="status" data-gcr-status></p><button data-gcr-retry hidden>Retry</button>';
	document.body.appendChild( root );
	return root;
}

async function settle() {
	for ( let i = 0; i < 8; i++ ) {
		await Promise.resolve();
	}
}

function readyGoogle() {
	window.gapi = {
		load: jest.fn( ( name, options ) => options.callback() ),
		surveyoptin: { render },
	};
}

beforeEach( () => {
	jest.resetModules();
	jest.useFakeTimers();
	document.body.innerHTML = '';
	document.head.innerHTML = '';
	delete window.reviewbirdGcrState;
	delete window.gapi;
	global.fetch = jest.fn().mockResolvedValue( { ok: true } );
	render = jest.fn();
	init = require( './google-customer-reviews' ).initGoogleCustomerReviews;
} );

afterEach( () => {
	jest.clearAllTimers();
	jest.useRealTimers();
	delete global.fetch;
	delete window.gapi;
	delete window.reviewbirdGcrLoaded;
	delete window.reviewbirdGcrState;
} );

test( 'the native order confirmation block places the widget below its title', () => {
	const root = card();
	const block = document.createElement( 'div' );
	block.className = 'wc-block-order-confirmation-status';
	block.innerHTML = '<h1>Order received</h1><p>Thank you.</p>';
	document.body.appendChild( block );
	block.prepend( root );
	init( root );
	expect( block.querySelector( 'h1' ).nextElementSibling ).toBe( root );
	expect( fetch ).not.toHaveBeenCalled();
} );

test( 'checkout loads Google automatically and duplicate hooks render it only once', async () => {
	const root = card();
	init( root );
	init( root );
	const duplicate = card();
	init( duplicate );
	expect( root.hidden ).toBe( true );
	expect( fetch ).not.toHaveBeenCalled();
	expect(
		document.querySelectorAll( 'script[src^="https://apis.google.com/"]' )
	).toHaveLength( 1 );
	readyGoogle();
	window.reviewbirdGcrLoaded();
	await settle();
	expect( render ).toHaveBeenCalledTimes( 1 );
	expect( render ).toHaveBeenCalledWith( {
		merchant_id: 1234,
		order_id: '7',
		email: 'buyer@example.com',
		delivery_country: 'US',
		estimated_delivery_date: '2026-09-20',
	} );
	expect( root.hidden ).toBe( true );
	expect( duplicate.hidden ).toBe( true );
	expect( fetch ).not.toHaveBeenCalled();
	init( card() );
	expect( render ).toHaveBeenCalledTimes( 1 );
} );

test( 'a direct order page opens Google without recording Yes', async () => {
	readyGoogle();
	const root = card( 'direct' );
	init( root );
	init( root );
	await settle();
	expect( fetch ).not.toHaveBeenCalled();
	expect( render ).toHaveBeenCalledTimes( 1 );
	expect( root.hidden ).toBe( false );
	expect( root.querySelector( '[data-gcr-status]' ).textContent ).toBe(
		text.opened
	);
} );

test( 'a Google render error shows an accessible retry without saving consent', async () => {
	readyGoogle();
	render.mockImplementationOnce( () => {
		throw new Error( 'Render failed' );
	} );
	const root = card();
	init( root );
	await settle();
	expect( root.hidden ).toBe( false );
	expect( root.querySelector( '[data-gcr-status]' ).textContent ).toBe(
		text.error
	);
	const retry = root.querySelector( '[data-gcr-retry]' );
	expect( document.activeElement ).toBe( retry );
	retry.click();
	retry.click();
	await settle();
	expect( fetch ).not.toHaveBeenCalled();
	expect( render ).toHaveBeenCalledTimes( 2 );
	expect( root.hidden ).toBe( true );
} );

test( 'a Google script error permits another load without saving consent', async () => {
	const root = card();
	init( root );
	await settle();
	document.querySelector( 'script' ).onerror();
	await settle();
	expect( root.hidden ).toBe( false );
	root.querySelector( '[data-gcr-retry]' ).click();
	await settle();
	expect( document.querySelectorAll( 'script' ) ).toHaveLength( 1 );
	readyGoogle();
	window.reviewbirdGcrLoaded();
	await settle();
	expect( fetch ).not.toHaveBeenCalled();
	expect( render ).toHaveBeenCalledTimes( 1 );
} );

test.each( [ 'direct', 'standard' ] )(
	'a Google timeout offers retry in %s mode without recording a choice',
	async ( mode ) => {
		const root = card( mode );
		init( root );
		jest.advanceTimersByTime( 15000 );
		await settle();
		expect( root.querySelector( '[data-gcr-retry]' ).hidden ).toBe( false );
		expect( fetch ).not.toHaveBeenCalled();
		readyGoogle();
		root.querySelector( '[data-gcr-retry]' ).click();
		await settle();
		expect( render ).toHaveBeenCalledTimes( 1 );
		expect( fetch ).not.toHaveBeenCalled();
		expect( root.hidden ).toBe( mode === 'standard' );
	}
);
