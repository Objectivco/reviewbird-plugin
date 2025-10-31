import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export default function ConnectionHealth() {
	const [healthStatus, setHealthStatus] = useState('checking');
	const [healthData, setHealthData] = useState(null);
	const [lastChecked, setLastChecked] = useState(null);

	useEffect(() => {
		checkHealth();
		// Check health every 5 minutes instead of every 30 seconds
		const interval = setInterval(checkHealth, 300000);
		return () => clearInterval(interval);
	}, []);

	const checkHealth = async () => {
		try {
            const response = await fetch( `${window.reviewbirdAdmin.apiUrl}/api/woocommerce/health?domain=${window.location.hostname}`, {
				method: 'GET',
				headers: {
					'Accept': 'application/json',
				}
			});

			if (response.ok) {
				const data = await response.json();
				setHealthData(data);
				setHealthStatus(data.status);
			} else {
				const data = await response.json().catch(() => ({}));
				setHealthData(data);
				setHealthStatus(data.status || 'unhealthy');
			}

			setLastChecked(new Date());
		} catch (error) {
			console.error('Health check failed:', error);
			setHealthStatus('error');
			setHealthData({ message: 'Unable to reach ReviewBird API' });
			setLastChecked(new Date());
		}
	};

	const getStatusColor = () => {
		switch (healthStatus) {
			case 'healthy':
				return 'bg-green-50 border-green-200';
			case 'not_connected':
				return 'bg-blue-50 border-blue-200';
			case 'syncing':
				return 'bg-yellow-50 border-yellow-200';
			case 'billing_required':
				return 'bg-orange-50 border-orange-200';
			case 'unhealthy':
			case 'error':
				return 'bg-red-50 border-red-200';
			default:
				return 'bg-gray-50 border-gray-200';
		}
	};

	const getStatusIcon = () => {
		switch (healthStatus) {
			case 'healthy':
				return (
					<svg className="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
						<path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
					</svg>
				);
			case 'not_connected':
				return (
					<svg className="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
						<path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
					</svg>
				);
			case 'syncing':
				return (
					<svg className="w-5 h-5 text-yellow-600 animate-spin" fill="none" viewBox="0 0 24 24">
						<circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
						<path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
					</svg>
				);
			case 'billing_required':
				return (
					<svg className="w-5 h-5 text-orange-600" fill="currentColor" viewBox="0 0 20 20">
						<path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
					</svg>
				);
			case 'unhealthy':
			case 'error':
				return (
					<svg className="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 20 20">
						<path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
					</svg>
				);
			default:
				return (
					<svg className="w-5 h-5 text-gray-400 animate-spin" fill="none" viewBox="0 0 24 24">
						<circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
						<path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
					</svg>
				);
		}
	};

	const getStatusText = () => {
		switch (healthStatus) {
			case 'healthy':
				return __('Connected to ReviewBird', 'reviewbird-reviews');
			case 'not_connected':
				return __('WooCommerce Not Connected', 'reviewbird-reviews');
			case 'syncing':
				return __('Initial Sync in Progress', 'reviewbird-reviews');
			case 'billing_required':
				return __('Subscription Required', 'reviewbird-reviews');
			case 'unhealthy':
				const errorCode = healthData?.error_code;
				return errorCode ? `${__('Connection Issue', 'reviewbird-reviews')} (${errorCode})` : __('Connection Issue Detected', 'reviewbird-reviews');
			case 'error':
				return __('Connection Issue Detected', 'reviewbird-reviews');
			default:
				return __('Checking connection...', 'reviewbird-reviews');
		}
	};

	const getStatusMessage = () => {
		// Use message from API if available
		if (healthData?.message) {
			return healthData.message;
		}

		switch (healthStatus) {
			case 'healthy':
				return __('Your WooCommerce store is successfully connected to ReviewBird. Review data is syncing properly.', 'reviewbird-reviews');
			case 'not_connected':
				return __('WooCommerce is not yet connected to ReviewBird. Connect your store to start syncing products and reviews.', 'reviewbird-reviews');
			case 'syncing':
				return __('ReviewBird is syncing your products and reviews. This may take a few minutes depending on your catalog size. The widget will be available once sync completes.', 'reviewbird-reviews');
			case 'billing_required':
				return __('Your ReviewBird subscription is inactive. Please update your billing information to continue using the service.', 'reviewbird-reviews');
			case 'unhealthy':
				return __('Unable to connect to ReviewBird. Please check your connection settings.', 'reviewbird-reviews');
			case 'error':
				return __('Unable to reach ReviewBird API. Please check your internet connection.', 'reviewbird-reviews');
			default:
				return __('Verifying connection to ReviewBird...', 'reviewbird-reviews');
		}
	};

	const getActionUrl = () => {
		// Priority 1: store_id from health check response
		// Priority 2: storeId from window.reviewbirdConfig
		const storeId = healthData?.store_id || window.reviewbirdConfig?.storeId;

		if (!storeId) {
			// Fallback: stores index page
			return `${window.reviewbirdAdmin.apiUrl}/stores`;
		}

		// Route based on health status
		switch (healthStatus) {
			case 'billing_required':
				return `${window.reviewbirdAdmin.apiUrl}/stores/${storeId}/billing`;

			case 'not_connected':
			case 'syncing':
			case 'unhealthy':
				return `${window.reviewbirdAdmin.apiUrl}/stores/${storeId}/connect`;

			case 'healthy':
				return `${window.reviewbirdAdmin.apiUrl}/stores/${storeId}/settings`;

			case 'error':
				return `${window.reviewbirdAdmin.apiUrl}/support`;

			default:
				return `${window.reviewbirdAdmin.apiUrl}/stores/${storeId}/settings`;
		}
	};

	const getActionButtonText = () => {
		switch (healthStatus) {
			case 'billing_required':
				return __('Update Billing', 'reviewbird-reviews');
			case 'not_connected':
				return __('Connect WooCommerce', 'reviewbird-reviews');
			case 'syncing':
				return __('View Sync Progress', 'reviewbird-reviews');
			case 'unhealthy':
				return __('Fix Connection', 'reviewbird-reviews');
			case 'healthy':
				return __('View Dashboard', 'reviewbird-reviews');
			case 'error':
				return __('Get Help', 'reviewbird-reviews');
			default:
				return __('Open ReviewBird', 'reviewbird-reviews');
		}
	};

	return (
		<div className={`rounded-lg border p-6 ${getStatusColor()}`}>
			<div className="flex items-start">
				<div className="flex-shrink-0">
					{getStatusIcon()}
				</div>
				<div className="ml-3 flex-1">
					<h3 className="text-sm font-medium text-gray-900">
						{getStatusText()}
					</h3>
					<div className="mt-1 text-sm text-gray-600">
						<p>{getStatusMessage()}</p>
						{healthStatus !== 'checking' && (
							<a
								href={getActionUrl()}
								target="_blank"
								rel="noopener noreferrer"
								className="mt-2 inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-700"
							>
								{getActionButtonText()}
								<svg className="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
									<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
								</svg>
							</a>
						)}
					</div>
					{lastChecked && (
						<p className="mt-2 text-xs text-gray-500">
							{__('Last checked:', 'reviewbird-reviews')} {lastChecked.toLocaleTimeString()}
						</p>
					)}
				</div>
				{healthStatus !== 'checking' && (
					<button
						onClick={checkHealth}
						className="ml-4 text-sm text-indigo-600 hover:text-indigo-700 font-medium whitespace-nowrap"
					>
						{__('Refresh', 'reviewbird-reviews')}
					</button>
				)}
			</div>
		</div>
	);
}
