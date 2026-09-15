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

test( 'the editor previews text without Google, then saves or resets all prompt fields', async () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	const defaults = {
		heading: 'Rate your purchase experience',
		message: 'Would you like a survey?',
		yes_label: 'Yes',
		no_label: 'No',
	};
	window.reviewbirdAdmin = {
		ajaxUrl: '/wp-admin/admin-ajax.php',
		nonce: 'admin-token',
		googleCustomerReviews: {
			enabled: true,
			prompt: defaults,
			defaults,
			integrationsUrl:
				'https://app.example.com/acme/stores/7/integrations',
		},
	};
	global.fetch = jest.fn( async ( url, { body } ) => ( {
		ok: true,
		json: async () => ( {
			success: true,
			data: {
				prompt: Object.fromEntries(
					Object.keys( defaults ).map( ( key ) => [
						key,
						body.get( key ),
					] )
				),
			},
		} ),
	} ) );
	const container = document.createElement( 'div' );
	document.body.appendChild( container );
	const root = createRoot( container );
	try {
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
	} finally {
		act( () => root.unmount() );
		container.remove();
		delete window.reviewbirdAdmin;
		delete global.fetch;
		delete global.IS_REACT_ACT_ENVIRONMENT;
	}
} );
