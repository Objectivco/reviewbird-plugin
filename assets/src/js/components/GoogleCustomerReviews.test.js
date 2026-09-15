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

const response = ( data ) => ( {
	ok: true,
	json: async () => ( { success: true, data } ),
} );
const mount = () => act( async () => root.render( <GoogleCustomerReviews /> ) );
const saveButton = () => container.querySelector( 'button[type="submit"]' );
const checkbox = () =>
	container.querySelector( '#reviewbird-gcr-prompt-toggle' );
const badge = () =>
	container.querySelector( '#reviewbird-gcr-integration-status' ).textContent;
const changeField = ( field, value ) =>
	act( async () =>
		Simulate.change( container.querySelector( `[name="${ field }"]` ), {
			target: { value },
		} )
	);

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

test( 'cached status and a safe preview need no request; only changed text can be saved', async () => {
	await mount();
	expect( badge() ).toBe( 'Integration enabled' );
	expect( saveButton().textContent ).toBe( 'Save text' );
	expect( saveButton().disabled ).toBe( true );
	const link = container.querySelector( '.reviewbird-gcr-toolbar a' );
	expect( link.target ).toBe( '_blank' );
	expect( link.rel ).toContain( 'noopener' );
	expect( link.querySelector( 'svg' ) ).not.toBeNull();
	await changeField( 'heading', 'Tell us about your order' );
	expect( saveButton().disabled ).toBe( false );
	expect(
		container.querySelector( '.reviewbird-gcr__heading' ).textContent
	).toBe( 'Tell us about your order' );
	container
		.querySelectorAll( '.reviewbird-gcr button' )
		.forEach( ( button ) => button.click() );
	expect( container.querySelector( '[data-reviewbird-gcr]' ) ).toBeNull();
	await changeField( 'heading', defaults.heading );
	expect( saveButton().disabled ).toBe( true );
	expect( fetch ).not.toHaveBeenCalled();
} );

test( 'Reset changes the draft; Save text commits it and sets the new saved baseline', async () => {
	window.reviewbirdAdmin.googleCustomerReviews.prompt = {
		...defaults,
		heading: 'Saved custom heading',
	};
	await mount();
	expect( saveButton().disabled ).toBe( true );
	const reset = Array.from( container.querySelectorAll( 'button' ) ).find(
		( button ) => button.textContent === 'Reset to defaults'
	);
	await act( async () => reset.click() );
	expect( container.querySelector( '[name="heading"]' ).value ).toBe(
		defaults.heading
	);
	expect( saveButton().disabled ).toBe( false );
	expect( fetch ).not.toHaveBeenCalled();
	let resolve;
	fetch.mockReturnValueOnce(
		new Promise( ( done ) => {
			resolve = done;
		} )
	);
	await act( async () => saveButton().click() );
	expect( saveButton().disabled ).toBe( true );
	const body = fetch.mock.calls[ 0 ][ 1 ].body;
	expect( body.get( 'action' ) ).toBe( 'reviewbird_update_gcr_prompt' );
	expect( body.get( 'nonce' ) ).toBe( 'admin-token' );
	Object.entries( defaults ).forEach( ( [ key, value ] ) =>
		expect( body.get( key ) ).toBe( value )
	);
	await act( async () => resolve( response( { prompt: defaults } ) ) );
	expect( saveButton().disabled ).toBe( true );
	await changeField( 'heading', 'Another heading' );
	expect( saveButton().disabled ).toBe( false );
	await changeField( 'heading', defaults.heading );
	expect( saveButton().disabled ).toBe( true );
} );

test( 'a failed text save keeps the draft and the previous saved baseline', async () => {
	await mount();
	await changeField( 'message', 'Unsaved survey message' );
	fetch.mockResolvedValueOnce( {
		ok: false,
		json: async () => ( {
			success: false,
			data: 'The text could not be saved.',
		} ),
	} );
	await act( async () => saveButton().click() );
	expect( container.textContent ).toContain( 'The text could not be saved.' );
	expect( container.querySelector( '[name="message"]' ).value ).toBe(
		'Unsaved survey message'
	);
	expect( saveButton().disabled ).toBe( false );
	await changeField( 'message', defaults.message );
	expect( saveButton().disabled ).toBe( true );
} );

test.each( [
	[
		'disabled',
		'Integration disabled',
		'Enable Google Customer Reviews in Reviewbird, then refresh.',
	],
	[ 'unknown', 'Status unknown', 'Refresh to check your connection.' ],
] )(
	'a %s integration locks the unchecked checkbox and shows one hint',
	async ( status, label, hint ) => {
		Object.assign( window.reviewbirdAdmin.googleCustomerReviews, {
			enabled: false,
			status,
			promptEnabled: true,
		} );
		await mount();
		expect( badge() ).toBe( label );
		expect( checkbox().type ).toBe( 'checkbox' );
		expect( checkbox().getAttribute( 'role' ) ).not.toBe( 'switch' );
		expect( checkbox().labels[ 0 ].textContent.trim() ).toBe(
			'Enable widget on the thank you page'
		);
		expect( checkbox().disabled ).toBe( true );
		expect( checkbox().checked ).toBe( false );
		const description = checkbox().getAttribute( 'aria-describedby' );
		expect( description ).toBeTruthy();
		expect(
			container.querySelectorAll( `#${ description }` )
		).toHaveLength( 1 );
		expect(
			container.querySelector( `#${ description }` ).textContent
		).toBe( hint );
		await act( async () => checkbox().click() );
		expect( fetch ).not.toHaveBeenCalled();
	}
);

test( 'Refresh gates the checkbox from current integration status, keeps drafts, and retains the gate on failure', async () => {
	Object.assign( window.reviewbirdAdmin.googleCustomerReviews, {
		status: 'disabled',
		enabled: false,
		promptEnabled: true,
	} );
	await mount();
	expect( badge() ).toBe( 'Integration disabled' );
	expect( checkbox().checked ).toBe( false );
	await changeField( 'heading', 'Unsaved heading' );
	let resolve;
	fetch.mockReturnValueOnce(
		new Promise( ( done ) => {
			resolve = done;
		} )
	);
	const refresh = container.querySelector( '.reviewbird-gcr-toolbar button' );
	await act( async () => refresh.click() );
	expect( refresh.disabled ).toBe( true );
	expect( checkbox().disabled ).toBe( true );
	expect( fetch.mock.calls[ 0 ][ 1 ].body.get( 'action' ) ).toBe(
		'reviewbird_clear_health_cache'
	);
	expect( fetch.mock.calls[ 0 ][ 1 ].body.get( 'nonce' ) ).toBe(
		'admin-token'
	);
	const updated = {
		...window.reviewbirdAdmin.googleCustomerReviews,
		status: 'enabled',
		enabled: true,
		promptEnabled: true,
		prompt: { ...defaults, heading: 'Saved elsewhere' },
	};
	await act( async () =>
		resolve( response( { googleCustomerReviews: updated } ) )
	);
	expect( badge() ).toBe( 'Integration enabled' );
	expect( checkbox().checked ).toBe( true );
	expect( checkbox().disabled ).toBe( false );
	expect(
		container.querySelector( '#reviewbird-gcr-visibility-description' )
	).toBeNull();
	expect( container.querySelector( '[name="heading"]' ).value ).toBe(
		'Unsaved heading'
	);
	expect( saveButton().disabled ).toBe( false );
	fetch.mockResolvedValueOnce(
		response( {
			googleCustomerReviews: {
				...updated,
				status: 'disabled',
				enabled: false,
			},
		} )
	);
	await act( async () => refresh.click() );
	expect( badge() ).toBe( 'Integration disabled' );
	expect( checkbox().checked ).toBe( false );
	expect( checkbox().disabled ).toBe( true );
	expect(
		container.querySelector( '#reviewbird-gcr-visibility-description' )
	).not.toBeNull();
	fetch.mockRejectedValueOnce( new Error() );
	await act( async () => refresh.click() );
	expect( container.querySelector( '[role="alert"]' ).textContent ).toContain(
		'could not be refreshed'
	);
	expect( badge() ).toBe( 'Integration disabled' );
	expect( refresh.disabled ).toBe( false );
	expect( checkbox().checked ).toBe( false );
	expect( checkbox().disabled ).toBe( true );
	expect( container.querySelector( '[name="heading"]' ).value ).toBe(
		'Unsaved heading'
	);
} );

test( 'the local checkbox waits for its save and retains the saved value on failure', async () => {
	await mount();
	let resolve;
	fetch.mockReturnValueOnce(
		new Promise( ( done ) => {
			resolve = done;
		} )
	);
	await act( async () => checkbox().click() );
	expect( checkbox().checked ).toBe( true );
	expect( checkbox().disabled ).toBe( true );
	const body = fetch.mock.calls[ 0 ][ 1 ].body;
	expect( body.get( 'action' ) ).toBe( 'reviewbird_update_setting' );
	expect( body.get( 'setting' ) ).toBe( 'enable_gcr_prompt' );
	expect( body.get( 'value' ) ).toBe( '0' );
	await act( async () => resolve( response( { value: false } ) ) );
	expect( checkbox().checked ).toBe( false );
	fetch.mockResolvedValueOnce( {
		ok: false,
		json: async () => ( {
			success: false,
			data: 'The setting could not be saved.',
		} ),
	} );
	await act( async () => checkbox().click() );
	expect( fetch.mock.calls[ 1 ][ 1 ].body.get( 'value' ) ).toBe( '1' );
	expect( checkbox().checked ).toBe( false );
	expect( checkbox().disabled ).toBe( false );
	expect( container.querySelector( '[role="alert"]' ).textContent ).toBe(
		'The setting could not be saved.'
	);
} );
