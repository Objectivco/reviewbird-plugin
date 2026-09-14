import { createRoot } from '@wordpress/element';
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

test( 'health descriptions use WordPress translations instead of English API messages', async () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	window.reviewbirdAdmin = {
		apiUrl: 'https://app.example.com',
		locale: 'de-DE',
	};
	global.fetch = jest.fn().mockResolvedValue( {
		ok: true,
		json: async () => ( {
			status: 'healthy',
			store_id: 7,
			message:
				'An English API diagnostic that must not replace the translation.',
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
		expect( container.textContent ).toContain(
			'Ihr WooCommerce-Shop ist erfolgreich mit Reviewbird verbunden.'
		);
		expect( container.textContent ).not.toContain(
			'An English API diagnostic'
		);
		expect( time ).toHaveBeenCalledWith( 'de-DE' );
	} finally {
		act( () => root.unmount() );
		time.mockRestore();
		delete window.reviewbirdAdmin;
		delete global.fetch;
		delete global.IS_REACT_ACT_ENVIRONMENT;
	}
} );
