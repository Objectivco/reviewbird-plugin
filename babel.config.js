const preset = require.resolve( '@wordpress/babel-preset-default', {
	paths: [ require.resolve( '@wordpress/scripts/package.json' ) ],
} );

module.exports = ( api ) => ( {
	presets: [ preset ],
	// WordPress 5.9 has wp.element, but no react-jsx-runtime script.
	plugins: api.env( 'test' )
		? []
		: [
				[
					require.resolve( '@babel/plugin-transform-react-jsx', {
						paths: [ preset ],
					} ),
					{
						runtime: 'classic',
						pragma: 'wp.element.createElement',
						pragmaFrag: 'wp.element.Fragment',
					},
				],
		  ],
} );
