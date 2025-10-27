module.exports = {
	content: [
		'./assets/src/**/*.{js,jsx,ts,tsx}',
		'./src/**/*.php',
	],
	theme: {
		extend: {
			colors: {
				'reviewbird-primary': '#4f46e5',
				'reviewbird-secondary': '#0ea5e9',
			},
		},
	},
	plugins: [],
	corePlugins: {
		preflight: false,
	},
};
