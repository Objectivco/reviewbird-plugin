const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

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
};