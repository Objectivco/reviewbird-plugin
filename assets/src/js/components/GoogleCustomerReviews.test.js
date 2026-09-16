import { createRoot } from '@wordpress/element';
import GoogleCustomerReviews from './GoogleCustomerReviews.jsx';

const { act } = require(
	require.resolve( 'react', {
		paths: [ require.resolve( '@wordpress/element' ) ],
	} )
);
let container;
let root;

const response = ( data ) => ( {
	ok: true,
	json: async () => ( { success: true, data } ),
} );
const mount = () => act( async () => root.render( <GoogleCustomerReviews /> ) );
const refreshButton = () => container.querySelector( 'button' );
const integrationStatus = () =>
	container.querySelector( '#reviewbird-gcr-integration-status' ).textContent;

beforeEach( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	window.reviewbirdAdmin = {
		ajaxUrl: '/wp-admin/admin-ajax.php',
		nonce: 'admin-token',
		googleCustomerReviews: {
			enabled: true,
			status: 'enabled',
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

test.each( [ 'enabled', 'disabled', 'unknown' ] )(
	'the %s integration shows status and automatic consent guidance without local controls',
	async ( status ) => {
		window.reviewbirdAdmin.googleCustomerReviews.status = status;
		await mount();
		expect( integrationStatus() ).toBe(
			status === 'unknown'
				? 'Integration status is unknown.'
				: `Integration is currently ${ status }.`
		);
		expect( container.textContent ).toContain(
			"When the integration is enabled, Google's consent window opens automatically for every order on the thank you page."
		);
		const link = container.querySelector( '.reviewbird-gcr-toolbar a' );
		expect( link.textContent ).toBe( 'Configure integration' );
		expect( link.href ).toBe(
			window.reviewbirdAdmin.googleCustomerReviews.integrationsUrl
		);
		expect( link.target ).toBe( '_blank' );
		expect( link.rel ).toContain( 'noopener' );
		expect( container.querySelector( 'input, textarea, form' ) ).toBeNull();
		expect( container.querySelectorAll( 'button' ) ).toHaveLength( 1 );
		expect( refreshButton().disabled ).toBe( false );
		expect( fetch ).not.toHaveBeenCalled();
	}
);

test( 'Refresh updates integration status and prevents a second request while pending', async () => {
	window.reviewbirdAdmin.googleCustomerReviews.status = 'disabled';
	await mount();
	let resolve;
	fetch.mockReturnValueOnce(
		new Promise( ( done ) => {
			resolve = done;
		} )
	);
	await act( async () => refreshButton().click() );
	expect( refreshButton().disabled ).toBe( true );
	expect( refreshButton().textContent ).toBe( 'Refreshing…' );
	await act( async () => refreshButton().click() );
	expect( fetch ).toHaveBeenCalledTimes( 1 );
	const [ url, options ] = fetch.mock.calls[ 0 ];
	expect( url ).toBe( window.reviewbirdAdmin.ajaxUrl );
	expect( options.method ).toBe( 'POST' );
	expect( options.body.get( 'action' ) ).toBe(
		'reviewbird_clear_health_cache'
	);
	expect( options.body.get( 'nonce' ) ).toBe( 'admin-token' );
	await act( async () =>
		resolve(
			response( {
				googleCustomerReviews: {
					...window.reviewbirdAdmin.googleCustomerReviews,
					status: 'enabled',
				},
			} )
		)
	);
	expect( integrationStatus() ).toBe( 'Integration is currently enabled.' );
	expect( refreshButton().disabled ).toBe( false );
	expect( container.querySelector( '[role="status"]' ).textContent ).toBe(
		'Status updated.'
	);
} );

test.each( [ 'network', 'server', 'missing status' ] )(
	'a %s failure keeps the last status and permits another refresh',
	async ( failure ) => {
		await mount();
		if ( failure === 'network' ) {
			fetch.mockRejectedValueOnce( new Error() );
		} else if ( failure === 'server' ) {
			fetch.mockResolvedValueOnce( {
				ok: false,
				json: async () => ( {
					success: false,
					data: 'The status service is unavailable.',
				} ),
			} );
		} else {
			fetch.mockResolvedValueOnce( response( {} ) );
		}
		await act( async () => refreshButton().click() );
		expect( integrationStatus() ).toBe(
			'Integration is currently enabled.'
		);
		expect( refreshButton().disabled ).toBe( false );
		expect( container.querySelector( '[role="alert"]' ).textContent ).toBe(
			failure === 'server'
				? 'The status service is unavailable.'
				: 'The connection could not be refreshed. Please try again.'
		);
		fetch.mockResolvedValueOnce(
			response( {
				googleCustomerReviews: {
					...window.reviewbirdAdmin.googleCustomerReviews,
					status: 'disabled',
				},
			} )
		);
		await act( async () => refreshButton().click() );
		expect( integrationStatus() ).toBe(
			'Integration is currently disabled.'
		);
		expect( container.querySelector( '[role="alert"]' ) ).toBeNull();
	}
);
