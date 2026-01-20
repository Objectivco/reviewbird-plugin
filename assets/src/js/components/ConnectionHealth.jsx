import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const HEALTH_CHECK_INTERVAL = 300000; // 5 minutes

const STATUS_CONFIG = {
	healthy: {
		color: 'bg-green-50 border-green-200',
		iconColor: 'text-green-600',
		text: __('Connected to ReviewBird', 'reviewbird-reviews'),
		message: __('Your WooCommerce store is successfully connected to ReviewBird. Review data is syncing properly.', 'reviewbird-reviews'),
		buttonText: __('View Dashboard', 'reviewbird-reviews'),
		route: 'settings'
	},
	not_connected: {
		color: 'bg-blue-50 border-blue-200',
		iconColor: 'text-blue-600',
		text: __('WooCommerce Not Connected', 'reviewbird-reviews'),
		message: __('WooCommerce is not yet connected to ReviewBird. Connect your store to start syncing products and reviews.', 'reviewbird-reviews'),
		buttonText: __('Connect WooCommerce', 'reviewbird-reviews'),
		route: 'connect'
	},
	syncing: {
		color: 'bg-yellow-50 border-yellow-200',
		iconColor: 'text-yellow-600',
		text: __('Initial Sync in Progress', 'reviewbird-reviews'),
		message: __('ReviewBird is syncing your products and reviews. This may take a few minutes depending on your catalog size. The widget will be available once sync completes.', 'reviewbird-reviews'),
		buttonText: __('View Sync Progress', 'reviewbird-reviews'),
		route: 'connect'
	},
	billing_required: {
		color: 'bg-orange-50 border-orange-200',
		iconColor: 'text-orange-600',
		text: __('Subscription Required', 'reviewbird-reviews'),
		message: __('Your ReviewBird subscription is inactive. Please update your billing information to continue using the service.', 'reviewbird-reviews'),
		buttonText: __('Update Billing', 'reviewbird-reviews'),
		route: 'billing'
	},
	unhealthy: {
		color: 'bg-red-50 border-red-200',
		iconColor: 'text-red-600',
		text: __('Connection Issue Detected', 'reviewbird-reviews'),
		message: __('Unable to connect to ReviewBird. Please check your connection settings.', 'reviewbird-reviews'),
		buttonText: __('Fix Connection', 'reviewbird-reviews'),
		route: 'connect'
	},
	error: {
		color: 'bg-red-50 border-red-200',
		iconColor: 'text-red-600',
		text: __('Connection Issue Detected', 'reviewbird-reviews'),
		message: __('Unable to reach ReviewBird API. Please check your internet connection.', 'reviewbird-reviews'),
		buttonText: __('Get Help', 'reviewbird-reviews'),
		route: 'support'
	},
	checking: {
		color: 'bg-gray-50 border-gray-200',
		iconColor: 'text-gray-400',
		text: __('Checking connection...', 'reviewbird-reviews'),
		message: __('Verifying connection to ReviewBird...', 'reviewbird-reviews'),
		buttonText: __('Open ReviewBird', 'reviewbird-reviews'),
		route: 'settings'
	}
};

function CheckIcon({ className }) {
	return (
		<svg className={className} fill="currentColor" viewBox="0 0 20 20">
			<path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
		</svg>
	);
}

function InfoIcon({ className }) {
	return (
		<svg className={className} fill="currentColor" viewBox="0 0 20 20">
			<path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
		</svg>
	);
}

function SpinnerIcon({ className }) {
	return (
		<svg className={`${className} animate-spin`} fill="none" viewBox="0 0 24 24">
			<circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
			<path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
		</svg>
	);
}

function WarningIcon({ className }) {
	return (
		<svg className={className} fill="currentColor" viewBox="0 0 20 20">
			<path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
		</svg>
	);
}

function ErrorIcon({ className }) {
	return (
		<svg className={className} fill="currentColor" viewBox="0 0 20 20">
			<path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
		</svg>
	);
}

function ExternalLinkIcon() {
	return (
		<svg className="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
			<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
		</svg>
	);
}

function StatusIcon({ status }) {
	const iconClass = `w-5 h-5 ${STATUS_CONFIG[status]?.iconColor || STATUS_CONFIG.checking.iconColor}`;

	switch (status) {
		case 'healthy':
			return <CheckIcon className={iconClass} />;
		case 'not_connected':
			return <InfoIcon className={iconClass} />;
		case 'syncing':
			return <SpinnerIcon className={iconClass} />;
		case 'billing_required':
			return <WarningIcon className={iconClass} />;
		case 'unhealthy':
		case 'error':
			return <ErrorIcon className={iconClass} />;
		default:
			return <SpinnerIcon className={iconClass} />;
	}
}

async function clearHealthCache() {
	const formData = new FormData();
	formData.append('action', 'reviewbird_clear_health_cache');
	formData.append('nonce', window.reviewbirdAdmin.nonce);

	try {
		const response = await fetch(window.reviewbirdAdmin.ajaxUrl, {
			method: 'POST',
			body: formData
		});

		if (!response.ok) {
			console.warn('Failed to clear cache, continuing with health check');
		}
	} catch (error) {
		console.warn('Cache clear request failed:', error);
	}
}

async function fetchHealthStatus() {
	const response = await fetch(
		`${window.reviewbirdAdmin.apiUrl}/api/woocommerce/health?domain=${window.location.hostname}`,
		{
			method: 'GET',
			headers: { 'Accept': 'application/json' }
		}
	);

	const data = await response.json().catch(() => ({}));
	const status = response.ok ? data.status : (data.status || 'unhealthy');

	return { data, status };
}

function getActionUrl(status, healthData) {
	const storeId = healthData?.store_id || window.reviewbirdConfig?.storeId;
	const baseUrl = window.reviewbirdAdmin.apiUrl;

	if (!storeId) {
		return `${baseUrl}/stores`;
	}

	const config = STATUS_CONFIG[status] || STATUS_CONFIG.checking;
	const route = config.route;

	if (route === 'support') {
		return `${baseUrl}/support`;
	}

	return `${baseUrl}/stores/${storeId}/${route}`;
}

function getStatusText(status, healthData) {
	if (status === 'unhealthy' && healthData?.error_code) {
		return `${__('Connection Issue', 'reviewbird-reviews')} (${healthData.error_code})`;
	}
	return STATUS_CONFIG[status]?.text || STATUS_CONFIG.checking.text;
}

function getStatusMessage(status, healthData) {
	if (healthData?.message) {
		return healthData.message;
	}
	return STATUS_CONFIG[status]?.message || STATUS_CONFIG.checking.message;
}

export default function ConnectionHealth() {
	const [healthStatus, setHealthStatus] = useState('checking');
	const [healthData, setHealthData] = useState(null);
	const [lastChecked, setLastChecked] = useState(null);

	const checkHealth = useCallback(async (shouldClearCache = false) => {
		try {
			if (shouldClearCache) {
				await clearHealthCache();
			}

			const { data, status } = await fetchHealthStatus();
			setHealthData(data);
			setHealthStatus(status);
		} catch (error) {
			console.error('Health check failed:', error);
			setHealthStatus('error');
			setHealthData({ message: 'Unable to reach ReviewBird API' });
		}

		setLastChecked(new Date());
	}, []);

	useEffect(() => {
		checkHealth();
		const interval = setInterval(checkHealth, HEALTH_CHECK_INTERVAL);
		return () => clearInterval(interval);
	}, [checkHealth]);

	const config = STATUS_CONFIG[healthStatus] || STATUS_CONFIG.checking;
	const isChecking = healthStatus === 'checking';

	return (
		<div className={`rounded-lg border p-6 ${config.color}`}>
			<div className="flex items-start">
				<div className="flex-shrink-0">
					<StatusIcon status={healthStatus} />
				</div>
				<div className="ml-3 flex-1">
					<h3 className="text-sm font-medium text-gray-900">
						{getStatusText(healthStatus, healthData)}
					</h3>
					<div className="mt-1 text-sm text-gray-600">
						<p>{getStatusMessage(healthStatus, healthData)}</p>
						{!isChecking && (
							<a
								href={getActionUrl(healthStatus, healthData)}
								target="_blank"
								rel="noopener noreferrer"
								className="mt-2 inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-700"
							>
								{config.buttonText}
								<ExternalLinkIcon />
							</a>
						)}
					</div>
					{lastChecked && (
						<p className="mt-2 text-xs text-gray-500">
							{__('Last checked:', 'reviewbird-reviews')} {lastChecked.toLocaleTimeString()}
						</p>
					)}
				</div>
				{!isChecking && (
					<button
						onClick={() => checkHealth(true)}
						className="ml-4 text-sm text-indigo-600 hover:text-indigo-700 font-medium whitespace-nowrap"
					>
						{__('Refresh', 'reviewbird-reviews')}
					</button>
				)}
			</div>
		</div>
	);
}
