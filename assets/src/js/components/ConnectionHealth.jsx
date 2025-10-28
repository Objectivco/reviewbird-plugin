import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export default function ConnectionHealth() {
	const [healthStatus, setHealthStatus] = useState('checking');
	const [lastChecked, setLastChecked] = useState(null);

	useEffect(() => {
		checkHealth();
		// Check health every 30 seconds
		const interval = setInterval(checkHealth, 30000);
		return () => clearInterval(interval);
	}, []);

	const checkHealth = async () => {
		try {
			const response = await fetch(`${window.reviewbirdAdmin.apiUrl}/api/woocommerce/health?domain=${window.location.hostname}`, {
				method: 'GET',
				headers: {
					'Accept': 'application/json',
				},
			});

			if (response.ok) {
				const data = await response.json();
				setHealthStatus(data.status === 'healthy' ? 'healthy' : 'unhealthy');
			} else {
				setHealthStatus('unhealthy');
			}

			setLastChecked(new Date());
		} catch (error) {
			console.error('Health check failed:', error);
			setHealthStatus('unhealthy');
			setLastChecked(new Date());
		}
	};

	const getStatusColor = () => {
		switch (healthStatus) {
			case 'healthy':
				return 'bg-green-50 border-green-200';
			case 'unhealthy':
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
			case 'unhealthy':
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
				return __('Connected to reviewbird', 'reviewbird-reviews');
			case 'unhealthy':
				return __('Connection issue detected', 'reviewbird-reviews');
			default:
				return __('Checking connection...', 'reviewbird-reviews');
		}
	};

	const getStatusMessage = () => {
		switch (healthStatus) {
			case 'healthy':
				return __('Your WooCommerce store is successfully connected to reviewbird. Review data is syncing properly.', 'reviewbird-reviews');
			case 'unhealthy':
				return null; // Will show custom message with link
			default:
				return __('Verifying connection to reviewbird...', 'reviewbird-reviews');
		}
	};

	const getFixConnectionUrl = () => {
		// Extract store ID from store_id field in health response, or construct from domain
		const storeId = window.reviewbirdConfig?.storeId;
		if (storeId) {
			return `${window.reviewbirdAdmin.apiUrl}/stores/${storeId}/settings`;
		}
		return `${window.reviewbirdAdmin.apiUrl}/stores`;
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
					{healthStatus === 'unhealthy' ? (
						<div className="mt-1 text-sm text-gray-600">
							<p>{__('Unable to connect to reviewbird. Please check your connection settings.', 'reviewbird-reviews')}</p>
							<a
								href={getFixConnectionUrl()}
								target="_blank"
								rel="noopener noreferrer"
								className="mt-2 inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-700"
							>
								{__('Fix Connection Settings', 'reviewbird-reviews')}
								<svg className="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
									<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
								</svg>
							</a>
						</div>
					) : (
						<p className="mt-1 text-sm text-gray-600">
							{getStatusMessage()}
						</p>
					)}
					{lastChecked && (
						<p className="mt-2 text-xs text-gray-500">
							{__('Last checked:', 'reviewbird-reviews')} {lastChecked.toLocaleTimeString()}
						</p>
					)}
				</div>
				{healthStatus !== 'checking' && (
					<button
						onClick={checkHealth}
						className="ml-4 text-sm text-indigo-600 hover:text-indigo-700 font-medium"
					>
						{__('Check again', 'reviewbird-reviews')}
					</button>
				)}
			</div>
		</div>
	);
}
