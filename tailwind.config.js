module.exports = {
	content: [
		'./assets/src/**/*.{js,jsx,ts,tsx}',
		'./src/**/*.php',
	],
	theme: {
		extend: {
			colors: {
				'reviewapp-primary': '#4f46e5',
				'reviewapp-secondary': '#0ea5e9',
			},
		},
	},
	plugins: [],
	corePlugins: {
		preflight: false,
	},
};
