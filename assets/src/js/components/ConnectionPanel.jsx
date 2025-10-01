import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import StatusIndicator from './StatusIndicator';

export default function ConnectionPanel({ settings, onSave, saving }) {
	const handleOAuthConnect = () => {
		// Create a form and submit it via POST to start OAuth flow
		const form = document.createElement('form');
		form.method = 'POST';
		form.action = window.reviewappAdmin?.ajaxUrl || '/wp-admin/admin-ajax.php';
		
		const actionField = document.createElement('input');
		actionField.type = 'hidden';
		actionField.name = 'action';
		actionField.value = 'reviewapp_start_oauth';
		form.appendChild(actionField);
		
		const nonceField = document.createElement('input');
		nonceField.type = 'hidden';
		nonceField.name = 'nonce';
		nonceField.value = settings?.oauth_nonce || '';
		form.appendChild(nonceField);
		
		document.body.appendChild(form);
		form.submit();
	};

	const handleDisconnect = async () => {
		if (confirm(__('Are you sure you want to disconnect from ReviewApp?', 'reviewapp-reviews'))) {
			try {
				await onSave({ store_token: '' });
			} catch (err) {
				// Error handled by parent
			}
		}
	};

	const isConnected = !!settings?.store_token;

	return (
		<div className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
			<div className="px-6 py-5 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
				<h2 className="text-xl font-semibold text-gray-900">
					{__('Connection', 'reviewapp-reviews')}
				</h2>
				<StatusIndicator
					status={settings?.connection_status}
					storeId={settings?.store_id}
				/>
			</div>

			<div className="px-6 py-5 space-y-6">
				{!isConnected ? (
					<div className="text-center py-8">
						<div className="mx-auto w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mb-4">
							<svg className="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
							</svg>
						</div>
						<h3 className="text-lg font-medium text-gray-900 mb-2">
							{__('Connect to ReviewApp', 'reviewapp-reviews')}
						</h3>
						<p className="text-gray-600 mb-6 max-w-md mx-auto">
							{__('Start collecting and displaying reviews by connecting your store to ReviewApp', 'reviewapp-reviews')}
						</p>
						<Button
							variant="primary"
							onClick={handleOAuthConnect}
							className="!bg-indigo-600 hover:!bg-indigo-700 !text-white !px-8 !py-3 !text-base !h-auto !font-medium"
						>
							{__('Connect Now', 'reviewapp-reviews')}
						</Button>
					</div>
				) : (
					<div className="space-y-4">
						<div className="bg-green-50 border border-green-200 rounded-lg p-4">
							<div className="flex items-start">
								<svg className="w-5 h-5 text-green-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
									<path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
								</svg>
								<div className="ml-3">
									<h3 className="text-sm font-medium text-green-800">
										{__('Your store is connected', 'reviewapp-reviews')}
									</h3>
									{settings.store_id && (
										<p className="text-sm text-green-700 mt-1">
											{__('Store ID:', 'reviewapp-reviews')} <code className="font-mono">{settings.store_id}</code>
										</p>
									)}
								</div>
							</div>
						</div>

						<button
							type="button"
							onClick={handleDisconnect}
							disabled={saving}
							className="inline-flex items-center justify-center px-4 py-2 bg-red-50 hover:bg-red-100 text-red-700 text-sm border border-red-200 hover:border-red-300 rounded shadow-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
							style={{ boxShadow: 'none' }}
						>
							{__('Disconnect', 'reviewapp-reviews')}
						</button>
					</div>
				)}
			</div>
		</div>
	);
}
