/**
 * reviewbird Admin JavaScript
 */

import { render } from '@wordpress/element';
import '../scss/admin.scss';
import SettingsApp from './components/SettingsApp';

document.addEventListener('DOMContentLoaded', () => {
	const root = document.getElementById('reviewbird-settings-root');
	if (root) {
		render(<SettingsApp />, root);
	}
});
