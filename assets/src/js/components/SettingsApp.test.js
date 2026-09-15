import { createRoot } from '@wordpress/element';
import SettingsApp from './SettingsApp.jsx';

jest.mock( '../../images/logo-dark.svg', () => 'reviewbird-logo.svg' );

jest.mock( './WelcomeScreen.jsx', () => () => null );
jest.mock( './GoogleCustomerReviews.jsx', () => () => null );
jest.mock( './ConnectionHealth.jsx', () => ( {
	...jest.requireActual( './ConnectionHealth.jsx' ),
	__esModule: true,
	default: () => null,
} ) );

const { act } = require(
	require.resolve( 'react', {
		paths: [ require.resolve( '@wordpress/element' ) ],
	} )
);
let container;
let root;
const response = ( success = true, ok = true ) => ( {
	ok,
	json: async () => ( { success } ),
} );
const mount = () => act( async () => root.render( <SettingsApp /> ) );
const control = ( label ) =>
	container.querySelector( `[role="switch"][aria-label="${ label }"]` );
const checkbox = () => container.querySelector( '#reviewbird-force-reviews' );

beforeEach( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	window.reviewbirdAdmin = {
		ajaxUrl: '/wp-admin/admin-ajax.php',
		nonce: 'admin-token',
		enableWidget: '1',
		enableSchema: '1',
		forceReviewsOpen: '',
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

test.each( [
	[ 'Reviewbird Widget', 'enable_widget' ],
	[ 'Search results', 'enable_schema' ],
] )(
	'%s waits for its save and prevents duplicate requests',
	async ( label, setting ) => {
		await mount();
		const toggle = control( label );
		expect( toggle ).not.toBeNull();
		expect( toggle.getAttribute( 'aria-checked' ) ).toBe( 'true' );
		let resolve;
		fetch.mockReturnValueOnce(
			new Promise( ( done ) => {
				resolve = done;
			} )
		);
		await act( async () => toggle.click() );
		expect( toggle.disabled ).toBe( true );
		expect( toggle.getAttribute( 'aria-busy' ) ).toBe( 'true' );
		expect( toggle.getAttribute( 'aria-checked' ) ).toBe( 'true' );
		await act( async () => toggle.click() );
		expect( fetch ).toHaveBeenCalledTimes( 1 );
		expect( fetch.mock.calls[ 0 ][ 0 ] ).toBe( '/wp-admin/admin-ajax.php' );
		const body = fetch.mock.calls[ 0 ][ 1 ].body;
		expect( body.get( 'action' ) ).toBe( 'reviewbird_update_setting' );
		expect( body.get( 'nonce' ) ).toBe( 'admin-token' );
		expect( body.get( 'setting' ) ).toBe( setting );
		expect( body.get( 'value' ) ).toBe( '0' );
		await act( async () => resolve( response() ) );
		expect( toggle.disabled ).toBe( false );
		expect( toggle.getAttribute( 'aria-busy' ) ).toBe( 'false' );
		expect( toggle.getAttribute( 'aria-checked' ) ).toBe( 'false' );
	}
);

test.each( [ 'network', 'http', 'save' ] )(
	'a %s failure keeps the saved switch value and allows a retry',
	async ( failure ) => {
		await mount();
		if ( failure === 'network' ) {
			fetch.mockRejectedValueOnce( new Error() );
		} else {
			fetch.mockResolvedValueOnce(
				response( failure !== 'save', failure !== 'http' )
			);
		}
		const toggle = control( 'Search results' );
		await act( async () => toggle.click() );
		expect( toggle.getAttribute( 'aria-checked' ) ).toBe( 'true' );
		expect( toggle.disabled ).toBe( false );
		expect(
			toggle.closest( 'section' ).querySelector( '[role="alert"]' )
				.textContent
		).toBe( 'The setting could not be saved. Please try again.' );
		fetch.mockResolvedValueOnce( response() );
		await act( async () => toggle.click() );
		expect( toggle.getAttribute( 'aria-checked' ) ).toBe( 'false' );
		expect( container.querySelector( '[role="alert"]' ) ).toBeNull();
	}
);

test( 'the product checkbox saves independently, keeps its value on failure, and hides when the widget is off', async () => {
	await mount();
	expect( checkbox().labels[ 0 ].textContent ).toBe(
		'Enable reviews for all products'
	);
	let resolve;
	fetch.mockReturnValueOnce(
		new Promise( ( done ) => {
			resolve = done;
		} )
	);
	await act( async () => checkbox().click() );
	expect( checkbox().checked ).toBe( false );
	expect( checkbox().disabled ).toBe( true );
	expect( control( 'Reviewbird Widget' ).disabled ).toBe( true );
	await act( async () => control( 'Reviewbird Widget' ).click() );
	expect( fetch ).toHaveBeenCalledTimes( 1 );
	const body = fetch.mock.calls[ 0 ][ 1 ].body;
	expect( body.get( 'setting' ) ).toBe( 'force_reviews_open' );
	expect( body.get( 'value' ) ).toBe( '1' );
	await act( async () => resolve( response() ) );
	expect( checkbox().checked ).toBe( true );
	expect( checkbox().disabled ).toBe( false );
	fetch.mockResolvedValueOnce( response( false ) );
	await act( async () => checkbox().click() );
	expect( checkbox().checked ).toBe( true );
	expect( checkbox().disabled ).toBe( false );
	expect(
		checkbox().closest( 'section' ).querySelector( '[role="alert"]' )
	).not.toBeNull();
	fetch.mockReturnValueOnce(
		new Promise( ( done ) => {
			resolve = done;
		} )
	);
	await act( async () => control( 'Reviewbird Widget' ).click() );
	expect( checkbox().disabled ).toBe( true );
	await act( async () => resolve( response() ) );
	expect( checkbox() ).toBeNull();
	fetch.mockResolvedValueOnce( response() );
	await act( async () => control( 'Reviewbird Widget' ).click() );
	expect( checkbox().checked ).toBe( true );
} );

test( 'schema tools retain their external links and icons', async () => {
	await mount();
	const links = Array.from(
		control( 'Search results' ).closest( 'section' ).querySelectorAll( 'a' )
	);
	expect( links.map( ( link ) => [ link.textContent, link.href ] ) ).toEqual(
		[
			[
				'Test rich results',
				'https://search.google.com/test/rich-results',
			],
			[ 'Validate schema', 'https://validator.schema.org/' ],
		]
	);
	links.forEach( ( link ) => {
		expect( link.target ).toBe( '_blank' );
		expect( link.rel ).toBe( 'noopener noreferrer' );
		expect( link.querySelector( 'svg' ) ).not.toBeNull();
	} );
	expect( fetch ).not.toHaveBeenCalled();
} );
