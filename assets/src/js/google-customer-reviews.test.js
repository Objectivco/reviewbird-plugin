let init;
let render;
const text = {
	loading: 'Loading…',
	error: 'Please try again.',
	saveError: 'Your choice could not be saved. Please try again.',
	retry: 'Retry',
	opened: 'You can close this page when you finish.',
};

function card( mode = 'prompt' ) {
	const root = document.createElement( 'section' );
	root.dataset.reviewbirdGcr = JSON.stringify( {
		mode,
		choiceUrl: '/reviewbird/v1/google-customer-reviews/7/prompt-yes',
		noUrl: '/reviewbird/v1/google-customer-reviews/7/prompt-no',
		clickId: '00000000-0000-4000-8000-000000000001',
		token: 'order-token',
		google: {
			merchant_id: '1234',
			order_id: '7',
			email: 'buyer@example.com',
			delivery_country: 'US',
			estimated_delivery_date: '2026-09-20',
		},
		text,
	} );
	root.innerHTML =
		'<button data-gcr-yes>Yes</button><button data-gcr-no>No</button><p role="status" data-gcr-status></p><button data-gcr-retry hidden>Retry</button>';
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

test( 'No saves one click and hides this visit without loading Google', async () => {
	const root = card();
	init( root );
	const no = root.querySelector( '[data-gcr-no]' );
	no.click();
	no.click();
	await settle();
	expect( root.hidden ).toBe( true );
	expect( fetch ).toHaveBeenCalledTimes( 1 );
	expect( fetch.mock.calls[ 0 ][ 0 ] ).toMatch( /prompt-no$/ );
	expect( JSON.parse( fetch.mock.calls[ 0 ][ 1 ].body ) ).toEqual( {
		token: 'order-token',
		click_id: '00000000-0000-4000-8000-000000000001',
	} );
	expect( document.querySelector( 'script' ) ).toBeNull();
	no.click();
	root.querySelector( '[data-gcr-yes]' ).click();
	expect( fetch ).toHaveBeenCalledTimes( 1 );
	const revisit = card();
	init( revisit );
	expect( revisit.hidden ).toBe( false );
} );

test( 'a failed No save retries the same click without loading Google', async () => {
	readyGoogle();
	fetch.mockRejectedValueOnce( new Error( 'Offline' ) );
	const root = card();
	init( root );
	root.querySelector( '[data-gcr-no]' ).click();
	await settle();
	expect( root.hidden ).toBe( false );
	expect( root.querySelector( '[data-gcr-status]' ).textContent ).toBe(
		text.saveError
	);
	const retry = root.querySelector( '[data-gcr-retry]' );
	expect( retry.hidden ).toBe( false );
	retry.click();
	await settle();
	expect( fetch ).toHaveBeenCalledTimes( 2 );
	expect( fetch.mock.calls[ 1 ][ 0 ] ).toBe( fetch.mock.calls[ 0 ][ 0 ] );
	expect( fetch.mock.calls[ 1 ][ 1 ].body ).toBe(
		fetch.mock.calls[ 0 ][ 1 ].body
	);
	expect( root.hidden ).toBe( true );
	expect( window.gapi.load ).not.toHaveBeenCalled();
	expect( render ).not.toHaveBeenCalled();
} );

test( 'Yes saves once before Google loads and renders only once', async () => {
	let save;
	fetch.mockReturnValue(
		new Promise( ( resolve ) => {
			save = resolve;
		} )
	);
	const root = card();
	init( root );
	init( root );
	const yes = root.querySelector( '[data-gcr-yes]' );
	yes.click();
	yes.click();
	expect( fetch ).toHaveBeenCalledTimes( 1 );
	expect( JSON.parse( fetch.mock.calls[ 0 ][ 1 ].body ) ).toEqual( {
		token: 'order-token',
	} );
	expect( document.querySelector( 'script' ) ).toBeNull();
	save( { ok: true } );
	await settle();
	expect(
		document.querySelectorAll( 'script[src^="https://apis.google.com/"]' )
	).toHaveLength( 1 );
	readyGoogle();
	window.reviewbirdGcrLoaded();
	await settle();
	expect( render ).toHaveBeenCalledTimes( 1 );
	expect( render.mock.calls[ 0 ][ 0 ].merchant_id ).toBe( 1234 );
	expect( root.hidden ).toBe( true );
	yes.click();
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

test( 'a failed save shows retry and prevents Google calls', async () => {
	readyGoogle();
	fetch.mockResolvedValueOnce( { ok: false } );
	const root = card();
	init( root );
	root.querySelector( '[data-gcr-yes]' ).click();
	await settle();
	expect( render ).not.toHaveBeenCalled();
	expect( window.gapi.load ).not.toHaveBeenCalled();
	expect( root.querySelector( '[data-gcr-status]' ).textContent ).toBe(
		text.error
	);
	const retry = root.querySelector( '[data-gcr-retry]' );
	expect( retry.hidden ).toBe( false );
	retry.click();
	await settle();
	expect( fetch ).toHaveBeenCalledTimes( 2 );
	expect( render ).toHaveBeenCalledTimes( 1 );
} );

test( 'a Google script error permits another load without saving Yes twice', async () => {
	const root = card();
	init( root );
	root.querySelector( '[data-gcr-yes]' ).click();
	await settle();
	document.querySelector( 'script' ).onerror();
	await settle();
	expect( root.querySelector( '[data-gcr-yes]' ).hidden ).toBe( true );
	root.querySelector( '[data-gcr-retry]' ).click();
	await settle();
	expect( document.querySelectorAll( 'script' ) ).toHaveLength( 1 );
	readyGoogle();
	window.reviewbirdGcrLoaded();
	await settle();
	expect( fetch ).toHaveBeenCalledTimes( 1 );
	expect( render ).toHaveBeenCalledTimes( 1 );
} );

test( 'a Google timeout offers retry on direct pages without recording a choice', async () => {
	const root = card( 'direct' );
	init( root );
	jest.advanceTimersByTime( 15000 );
	await settle();
	expect( root.querySelector( '[data-gcr-retry]' ).hidden ).toBe( false );
	expect( fetch ).not.toHaveBeenCalled();
	readyGoogle();
	root.querySelector( '[data-gcr-retry]' ).click();
	await settle();
	expect( render ).toHaveBeenCalledTimes( 1 );
} );
