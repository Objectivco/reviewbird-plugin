import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import ConnectionPanel from './ConnectionPanel';
import SyncPanel from './SyncPanel';
import LoadingSpinner from './LoadingSpinner';

export default function SettingsApp() {
	const [settings, setSettings] = useState(null);
	const [loading, setLoading] = useState(true);
	const [saving, setSaving] = useState(false);
	const [error, setError] = useState(null);

	useEffect(() => {
		loadSettings();
	}, []);

	const loadSettings = async () => {
		try {
			const data = await apiFetch({
				path: '/reviewapp/v1/settings',
			});
			setSettings(data);
			setError(null);
		} catch (err) {
			console.error('Failed to load settings:', err);
			setError(err.message || __('Failed to load settings', 'reviewapp-reviews'));
		} finally {
			setLoading(false);
		}
	};

	const saveSettings = async (newSettings) => {
		setSaving(true);
		setError(null);
		try {
			const data = await apiFetch({
				path: '/reviewapp/v1/settings',
				method: 'POST',
				data: newSettings,
			});
			setSettings(data);
		} catch (err) {
			setError(err.message || __('Failed to save settings', 'reviewapp-reviews'));
			throw err;
		} finally {
			setSaving(false);
		}
	};

	if (loading) {
		return <LoadingSpinner />;
	}

	return (
		<div className="max-w-4xl mx-auto py-8">
			<header className="mb-8">
				<h1 className="text-3xl font-bold text-gray-900">
					{__('ReviewApp Settings', 'reviewapp-reviews')}
				</h1>
				<p className="mt-2 text-gray-600">
					{__('Connect your WooCommerce store to ReviewApp for advanced review collection and display.', 'reviewapp-reviews')}
				</p>
			</header>

			{error && (
				<div className="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
					<div className="flex">
						<svg className="w-5 h-5 text-red-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
							<path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
						</svg>
						<div className="ml-3">
							<p className="text-sm text-red-800">{error}</p>
						</div>
					</div>
				</div>
			)}

			<div className="space-y-6">
				<ConnectionPanel
					settings={settings}
					onSave={saveSettings}
					saving={saving}
				/>

				<SyncPanel isConnected={settings?.connection_status === 'connected'} />
			</div>
		</div>
	);
}
