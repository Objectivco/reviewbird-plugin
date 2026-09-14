import { createRoot } from '@wordpress/element';
import WelcomeScreen, { getWelcomeState } from './WelcomeScreen.jsx';
import { fetchHealthStatus } from './ConnectionHealth.jsx';

jest.mock( '../../images/logo-dark.svg', () => 'logo.svg' );

// Use the same React instance as WordPress, including in pnpm installs.
const { act } = require(
	require.resolve( 'react', {
		paths: [ require.resolve( '@wordpress/element' ) ],
	} )
);

test.each( [
	[ null, null ],
	[ { status: 'not_connected' }, 'new' ],
	[ { status: 'not_connected', store_id: 240 }, 'setup' ],
	[
		{
			status: 'billing_required',
			store_id: 240,
			onboarding_completed: false,
		},
		'setup',
	],
	[
		{
			status: 'healthy',
			store_id: 240,
			onboarding_completed: false,
			has_active_subscription: true,
		},
		'setup',
	],
	[
		{
			status: 'healthy',
			store_id: 240,
			onboarding_completed: true,
			has_active_subscription: true,
		},
		'ready',
	],
	[
		{
			status: 'syncing',
			store_id: 240,
			onboarding_completed: true,
			has_active_subscription: true,
		},
		'ready',
	],
	[
		{
			status: 'billing_required',
			store_id: 240,
			onboarding_completed: true,
		},
		'billing',
	],
	[
		{ status: 'not_connected', store_id: 240, onboarding_completed: true },
		'setup',
	],
	[
		{
			status: 'healthy',
			store_id: 240,
			onboarding_completed: true,
			has_active_subscription: false,
		},
		'new',
	],
	[ { status: 'error' }, 'new' ],
	[ {}, 'new' ],
] )( 'chooses the correct welcome state for %j', ( data, expected ) => {
	expect( getWelcomeState( data ) ).toBe( expected );
} );

afterEach( () => {
	window.history.replaceState( {}, '', '/' );
	delete window.reviewbirdAdmin;
	delete global.fetch;
} );

test( 'checks the public store domain and accepts a confirmed new store', async () => {
	window.reviewbirdAdmin = {
		apiUrl: 'https://app.example.com',
		siteDomain: 'shop.example.com',
	};
	global.fetch = jest.fn().mockResolvedValue( {
		ok: false,
		status: 404,
		json: async () => ( { status: 'not_connected' } ),
	} );
	expect( ( await fetchHealthStatus() ).status ).toBe( 'not_connected' );
	expect( fetch.mock.calls[ 0 ][ 0 ] ).toBe(
		'https://app.example.com/api/woocommerce/health?domain=shop.example.com'
	);
} );

test.each( [
	{
		ok: false,
		status: 500,
		json: async () => ( { status: 'not_connected' } ),
	},
	{ ok: true, status: 200, json: async () => ( {} ) },
] )(
	'reports failed checks so the welcome page can apply its fallback',
	async ( response ) => {
		window.reviewbirdAdmin = {
			apiUrl: 'https://app.example.com',
			siteDomain: 'shop.example.com',
		};
		global.fetch = jest.fn().mockResolvedValue( response );
		await expect( fetchHealthStatus() ).rejects.toThrow();
	}
);

test( 'updates on return and falls back to Get Started when the check fails', async () => {
	window.reviewbirdAdmin = {
		apiUrl: 'https://app.example.com',
		siteDomain: 'shop.example.com',
	};
	const storeUrl = 'https://app.example.com/my-org/stores/240';
	const data = {
		status: 'not_connected',
		store_id: 240,
		org_slug: 'my-org',
		onboarding_completed: false,
	};
	global.fetch = jest.fn().mockImplementation( async () => ( {
		ok: true,
		json: async () => ( { ...data } ),
	} ) );
	global.IS_REACT_ACT_ENVIRONMENT = true;
	const container = document.createElement( 'div' );
	document.body.appendChild( container );
	const root = createRoot( container );
	const visible = jest
		.spyOn( document, 'hidden', 'get' )
		.mockReturnValue( false );
	try {
		await act( async () =>
			root.render(
				<WelcomeScreen
					registerUrl="/register"
					dashboardUrl="/dashboard"
					settingsUrl="/wp-admin/admin.php?page=reviewbird-settings"
				/>
			)
		);
		expect(
			container.querySelector( '.reviewbird-button-primary' ).href
		).toBe( `${ storeUrl }/onboarding` );
		expect( container.textContent ).toContain( 'Continue setup' );
		expect( container.textContent ).not.toContain( 'Get started free' );
		Object.assign( data, {
			status: 'healthy',
			onboarding_completed: true,
			has_active_subscription: true,
		} );
		await act( async () => {
			window.dispatchEvent( new Event( 'focus' ) );
			document.dispatchEvent( new Event( 'visibilitychange' ) );
		} );
		expect( fetch ).toHaveBeenCalledTimes( 2 );
		expect( container.textContent ).toContain( 'Your store is connected' );
		expect(
			container.querySelector( '.reviewbird-button-primary' ).href
		).toBe( `${ storeUrl }/dashboard` );
		expect(
			container
				.querySelector( '.reviewbird-button-secondary' )
				.getAttribute( 'href' )
		).toBe( '/wp-admin/admin.php?page=reviewbird-settings' );
		global.fetch.mockRejectedValue( new Error( 'Network error' ) );
		await act( async () => window.dispatchEvent( new Event( 'focus' ) ) );
		expect( container.textContent ).toContain( 'Get started free' );
		expect( container.textContent ).not.toContain(
			'Your store is connected'
		);
		expect(
			container
				.querySelector( '.reviewbird-button-primary' )
				.getAttribute( 'href' )
		).toBe( '/register' );
		await act( async () => window.dispatchEvent( new Event( 'focus' ) ) );
		expect( container.textContent ).toContain( 'Get started free' );
	} finally {
		act( () => root.unmount() );
		container.remove();
		visible.mockRestore();
		delete global.IS_REACT_ACT_ENVIRONMENT;
	}
} );

test.each( [
	[ 'new', 'new' ],
	[ 'setup', 'setup' ],
	[ 'ready', 'ready' ],
	[ 'billing', 'billing' ],
] )( 'previews %s without changing the store status', ( preview, expected ) => {
	const health = Object.freeze( { status: 'not_connected' } );
	expect( getWelcomeState( health, preview ) ).toBe( expected );
	expect( getWelcomeState( health ) ).toBe( 'new' );
} );

test.each( [
	'invalid',
	'__proto__',
	'constructor',
	'syncing',
	'connection',
	'error',
	'checking',
] )( 'ignores invalid preview %s', ( preview ) => {
	expect(
		getWelcomeState( { status: 'not_connected', store_id: 240 }, preview )
	).toBe( 'setup' );
} );
