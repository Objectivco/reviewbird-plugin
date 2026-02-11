const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');
const BundleOutputPlugin = require('webpack-bundle-output');

module.exports = {
    ...defaultConfig,
    entry: {
        admin: path.resolve(process.cwd(), 'assets/src/js', 'admin.js'),
        'admin-style': path.resolve(process.cwd(), 'assets/src/scss', 'admin.scss'),
    },
    output: {
        path: path.resolve(process.cwd(), 'assets/build'),
        filename: '[name].js',
    },
    plugins: [
        ...defaultConfig.plugins,
        new BundleOutputPlugin({ output: 'map-admin.json' }),
    ],
};