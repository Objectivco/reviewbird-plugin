/**
 * ReviewApp Admin JavaScript
 */

import { render } from '@wordpress/element';
import '../scss/admin.scss';
import SettingsApp from './components/SettingsApp';

document.addEventListener('DOMContentLoaded', () => {
	const root = document.getElementById('reviewapp-settings-root');
	if (root) {
		render(<SettingsApp />, root);
	}
});
