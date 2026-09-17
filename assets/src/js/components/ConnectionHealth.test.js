/* eslint-disable @wordpress/i18n-no-variables -- Test cases use source strings from the translation catalog. */

import { createRoot } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import ConnectionHealth from './ConnectionHealth.jsx';

jest.mock( '@wordpress/i18n', () => {
	const i18n = jest.requireActual( '@wordpress/i18n' );
	const catalog = require( '../../../../languages/reviewbird-de_DE-4b5e3ab6c07e433d28bd2c4dd4bdf29e.json' );
	i18n.setLocaleData( catalog.locale_data.messages, 'reviewbird' );
	return i18n;
} );

const { act } = require(
	require.resolve( 'react', {
		paths: [ require.resolve( '@wordpress/element' ) ],
	} )
);

test( 'health status uses translations, checks once on load, and permits manual refresh', async () => {
	jest.useFakeTimers();
	global.IS_REACT_ACT_ENVIRONMENT = true;
	window.reviewbirdAdmin = {
		apiUrl: 'https://app.example.com',
		ajaxUrl: '/admin-ajax.php',
		nonce: 'admin-token',
		locale: 'de-DE',
	};
	global.fetch = jest.fn().mockResolvedValue( {
		ok: true,
		json: async () => ( {
			success: true,
			data: {
				status: {
					status: 'healthy',
					store_id: 7,
					message:
						'An English API diagnostic that must not replace the translation.',
				},
			},
		} ),
	} );
	const time = jest
		.spyOn( Date.prototype, 'toLocaleTimeString' )
		.mockReturnValue( '14:30:00' );
	const container = document.createElement( 'div' );
	const root = createRoot( container );
	try {
		await act( async () => root.render( <ConnectionHealth /> ) );
		expect( container.textContent ).toContain( 'Mit Reviewbird verbunden' );
		expect(
			container.querySelector(
				'.reviewbird-connection__copy > p:not(.reviewbird-connection__meta)'
			)
		).toBeNull();
		expect( container.textContent ).not.toContain(
			'An English API diagnostic'
		);
		expect( time ).toHaveBeenCalledWith( 'de-DE' );
		await act( async () => jest.advanceTimersByTime( 30 * 60 * 1000 ) );
		expect( global.fetch ).toHaveBeenCalledTimes( 1 );
		expect( fetch.mock.calls[ 0 ][ 1 ].body.get( 'action' ) ).toBe(
			'reviewbird_get_health_status'
		);
		await act( async () => container.querySelector( 'button' ).click() );
		expect( global.fetch ).toHaveBeenCalledTimes( 2 );
		expect( fetch.mock.calls[ 1 ][ 1 ].body.get( 'action' ) ).toBe(
			'reviewbird_clear_health_cache'
		);
	} finally {
		act( () => root.unmount() );
		time.mockRestore();
		jest.useRealTimers();
		delete window.reviewbirdAdmin;
		delete global.fetch;
		delete global.IS_REACT_ACT_ENVIRONMENT;
	}
} );

test( 'refresh is disabled while pending and reports failure without replacing the last status', async () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	window.reviewbirdAdmin = {
		apiUrl: 'https://app.example.com',
		ajaxUrl: '/admin-ajax.php',
		locale: 'de-DE',
	};
	let completeRefresh;
	global.fetch = jest
		.fn()
		.mockResolvedValueOnce( {
			ok: true,
			json: async () => ( {
				success: true,
				data: { status: { status: 'healthy', store_id: 7 } },
			} ),
		} )
		.mockImplementationOnce(
			() =>
				new Promise( ( resolve ) => {
					completeRefresh = resolve;
				} )
		);
	const container = document.createElement( 'div' );
	const root = createRoot( container );
	try {
		await act( async () => root.render( <ConnectionHealth /> ) );
		const button = container.querySelector( 'button' );
		await act( async () => button.click() );
		expect( button.disabled ).toBe( true );
		expect(
			container.querySelector( 'section' ).getAttribute( 'aria-busy' )
		).toBe( 'true' );
		await act( async () =>
			completeRefresh( {
				ok: true,
				json: async () => ( { success: false } ),
			} )
		);
		expect( button.disabled ).toBe( false );
		expect( container.querySelector( 'section' ).dataset.state ).toBe(
			'healthy'
		);
		expect(
			container.querySelector( '[role="alert"]' ).textContent
		).toBeTruthy();
		expect( global.fetch ).toHaveBeenCalledTimes( 2 );
	} finally {
		act( () => root.unmount() );
		delete window.reviewbirdAdmin;
		delete global.fetch;
		delete global.IS_REACT_ACT_ENVIRONMENT;
	}
} );

test.each( [
	[
		'billing_required',
		'Reviewbird is disabled',
		'Your Reviewbird subscription is not active. Update your billing details to turn Reviewbird back on.',
		'Update billing',
		'/my-org/stores/7/billing',
	],
	[
		'not_connected',
		'Your store is not connected',
		'Reviewbird is disabled until you connect your WooCommerce store.',
		'Connect store',
		'/my-org/stores/7/connect',
	],
	[
		'unhealthy',
		'Reviewbird cannot connect to your store',
		'Open the connection settings in Reviewbird to reconnect your store.',
		'Check connection',
		'/my-org/stores/7/connect',
	],
	[
		'error',
		'We could not check your connection',
		'Refresh to try again, or contact support if the problem continues.',
		'Contact support',
		'/my-org/support',
	],
] )(
	'explains %s with a useful next action',
	async ( status, heading, message, action, route ) => {
		global.IS_REACT_ACT_ENVIRONMENT = true;
		window.reviewbirdAdmin = {
			apiUrl: 'https://app.example.com',
			locale: 'de-DE',
		};
		global.fetch = jest.fn().mockResolvedValue( {
			ok: true,
			json: async () => ( {
				success: true,
				data: {
					status: {
						status,
						store_id: 7,
						org_slug: 'my-org',
						error_code: 'internal_diagnostic',
					},
				},
			} ),
		} );
		const container = document.createElement( 'div' );
		const root = createRoot( container );
		try {
			await act( async () => root.render( <ConnectionHealth /> ) );
			expect( container.querySelector( 'h2' ).textContent ).toBe(
				__( heading, 'reviewbird' )
			);
			expect( container.textContent ).toContain(
				__( message, 'reviewbird' )
			);
			expect( container.textContent ).not.toContain(
				'internal_diagnostic'
			);
			const link = container.querySelector( 'a' );
			expect( link.textContent ).toBe( __( action, 'reviewbird' ) );
			expect( link.href ).toBe( `https://app.example.com${ route }` );
			expect( link.target ).toBe( '_blank' );
		} finally {
			act( () => root.unmount() );
			delete window.reviewbirdAdmin;
			delete global.fetch;
			delete global.IS_REACT_ACT_ENVIRONMENT;
		}
	}
);
