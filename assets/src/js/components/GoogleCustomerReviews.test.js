import { createRoot } from '@wordpress/element';
import GoogleCustomerReviews from './GoogleCustomerReviews.jsx';

const { act } = require(
	require.resolve( 'react', {
		paths: [ require.resolve( '@wordpress/element' ) ],
	} )
);
const { Simulate } = require(
	require.resolve( 'react-dom/test-utils', {
		paths: [ require.resolve( '@wordpress/element' ) ],
	} )
);
const defaults = {
	heading: 'Rate your purchase experience',
	message: 'Would you like a survey?',
	yes_label: 'Yes',
	no_label: 'No',
};
let container;
let root;

function response( data ) {
	return { ok: true, json: async () => ( { success: true, data } ) };
}

beforeEach( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	window.reviewbirdAdmin = {
		ajaxUrl: '/wp-admin/admin-ajax.php',
		nonce: 'admin-token',
		googleCustomerReviews: {
			enabled: true,
			status: 'enabled',
			promptEnabled: true,
			prompt: defaults,
			defaults,
			integrationsUrl:
				'https://app.example.com/acme/stores/7/integrations',
		},
	};
	global.fetch = jest.fn();
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
} );

afterEach( () => {
	act( () => root.unmount() );
	container.remove();
	delete window.reviewbirdAdmin;
	delete global.fetch;
	delete global.IS_REACT_ACT_ENVIRONMENT;
} );

test( 'the editor previews text without Google, then saves or resets all prompt fields', async () => {
	fetch.mockImplementation( async ( url, { body } ) =>
		response( {
			prompt: Object.fromEntries(
				Object.keys( defaults ).map( ( key ) => [
					key,
					body.get( key ),
				] )
			),
		} )
	);
	await act( async () => root.render( <GoogleCustomerReviews /> ) );
	const input = container.querySelector( '[name="heading"]' );
	await act( async () =>
		Simulate.change( input, {
			target: { value: 'Tell us about your order' },
		} )
	);
	expect(
		container.querySelector( '.reviewbird-gcr__heading' ).textContent
	).toBe( 'Tell us about your order' );
	container
		.querySelectorAll( '.reviewbird-gcr button' )
		.forEach( ( button ) => button.click() );
	expect( fetch ).not.toHaveBeenCalled();
	expect( container.querySelector( '[data-reviewbird-gcr]' ) ).toBeNull();
	await act( async () =>
		Simulate.submit( container.querySelector( 'form' ) )
	);
	expect( fetch.mock.calls[ 0 ][ 1 ].body.get( 'heading' ) ).toBe(
		'Tell us about your order'
	);
	expect( fetch.mock.calls[ 0 ][ 1 ].body.get( 'nonce' ) ).toBe(
		'admin-token'
	);
	await act( async () =>
		container.querySelector( 'fieldset button[type="button"]' ).click()
	);
	expect( fetch.mock.calls[ 1 ][ 1 ].body.get( 'heading' ) ).toBe(
		defaults.heading
	);
	expect(
		container.querySelector( '.reviewbird-gcr__heading' ).textContent
	).toBe( defaults.heading );
} );

test( 'initial status is cached and the integration link opens a new tab with an icon', async () => {
	await act( async () => root.render( <GoogleCustomerReviews /> ) );
	expect( fetch ).not.toHaveBeenCalled();
	expect(
		container.querySelector( '#reviewbird-gcr-integration-status' )
			.textContent
	).toBe( 'Enabled' );
	const link = container.querySelector( '.reviewbird-gcr-toolbar a' );
	expect( link.target ).toBe( '_blank' );
	expect( link.rel ).toContain( 'noopener' );
	expect( link.querySelector( 'svg' ) ).not.toBeNull();
} );

test( 'refresh updates status and the local switch without changing unsaved prompt text; failure retains the prior status', async () => {
	window.reviewbirdAdmin.googleCustomerReviews.status = 'unknown';
	await act( async () => root.render( <GoogleCustomerReviews /> ) );
	expect(
		container.querySelector( '#reviewbird-gcr-integration-status' )
			.textContent
	).toBe( 'Refresh needed' );
	await act( async () =>
		Simulate.change( container.querySelector( '[name="heading"]' ), {
			target: { value: 'Unsaved heading' },
		} )
	);
	let resolve;
	fetch.mockReturnValueOnce(
		new Promise( ( done ) => {
			resolve = done;
		} )
	);
	const refresh = container.querySelector( '.reviewbird-gcr-toolbar button' );
	const toggle = container.querySelector( '[role="switch"]' );
	await act( async () => refresh.click() );
	expect( refresh.textContent ).toBe( 'Refreshing…' );
	expect( refresh.disabled ).toBe( true );
	expect( toggle.disabled ).toBe( true );
	expect( fetch.mock.calls[ 0 ][ 1 ].body.get( 'action' ) ).toBe(
		'reviewbird_clear_health_cache'
	);
	expect( fetch.mock.calls[ 0 ][ 1 ].body.get( 'nonce' ) ).toBe(
		'admin-token'
	);
	await act( async () =>
		resolve(
			response( {
				googleCustomerReviews: {
					...window.reviewbirdAdmin.googleCustomerReviews,
					status: 'disabled',
					enabled: false,
					promptEnabled: false,
					prompt: { ...defaults, heading: 'Saved elsewhere' },
				},
			} )
		)
	);
	expect(
		container.querySelector( '#reviewbird-gcr-integration-status' )
			.textContent
	).toBe( 'Disabled' );
	expect( toggle.getAttribute( 'aria-checked' ) ).toBe( 'false' );
	expect( container.querySelector( '[name="heading"]' ).value ).toBe(
		'Unsaved heading'
	);
	expect(
		container.querySelector( '.reviewbird-gcr__heading' ).textContent
	).toBe( 'Unsaved heading' );
	fetch.mockRejectedValueOnce( new Error() );
	await act( async () => refresh.click() );
	expect( container.querySelector( '[role="alert"]' ).textContent ).toContain(
		'could not be refreshed'
	);
	expect(
		container.querySelector( '#reviewbird-gcr-integration-status' )
			.textContent
	).toBe( 'Disabled' );
	expect( refresh.disabled ).toBe( false );
	expect( toggle.disabled ).toBe( false );
} );

test( 'the local switch waits for its save and retains the saved value on failure', async () => {
	await act( async () => root.render( <GoogleCustomerReviews /> ) );
	const toggle = container.querySelector( '[role="switch"]' );
	let resolve;
	fetch.mockReturnValueOnce(
		new Promise( ( done ) => {
			resolve = done;
		} )
	);
	await act( async () => toggle.click() );
	expect( toggle.getAttribute( 'aria-checked' ) ).toBe( 'true' );
	expect( toggle.disabled ).toBe( true );
	expect( toggle.getAttribute( 'aria-busy' ) ).toBe( 'true' );
	const body = fetch.mock.calls[ 0 ][ 1 ].body;
	expect( body.get( 'action' ) ).toBe( 'reviewbird_update_setting' );
	expect( body.get( 'setting' ) ).toBe( 'enable_gcr_prompt' );
	expect( body.get( 'value' ) ).toBe( '0' );
	await act( async () => resolve( response( { value: false } ) ) );
	expect( toggle.getAttribute( 'aria-checked' ) ).toBe( 'false' );
	fetch.mockResolvedValueOnce( {
		ok: false,
		json: async () => ( {
			success: false,
			data: 'The setting could not be saved.',
		} ),
	} );
	await act( async () => toggle.click() );
	expect( fetch.mock.calls[ 1 ][ 1 ].body.get( 'value' ) ).toBe( '1' );
	expect( toggle.getAttribute( 'aria-checked' ) ).toBe( 'false' );
	expect( toggle.disabled ).toBe( false );
	expect( container.querySelector( '[role="alert"]' ).textContent ).toBe(
		'The setting could not be saved.'
	);
} );
