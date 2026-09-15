import { useState, useEffect, useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

const HEALTH_CHECK_INTERVAL = 300000; // 5 minutes

const STATUS_CONFIG = {
	healthy: {
		text: __( 'Connected to Reviewbird', 'reviewbird' ),
		message: '',
		buttonText: __( 'View dashboard', 'reviewbird' ),
		route: 'dashboard',
	},
	not_connected: {
		text: __( 'Your store is not connected', 'reviewbird' ),
		message: __(
			'Reviewbird is disabled until you connect your WooCommerce store.',
			'reviewbird'
		),
		buttonText: __( 'Connect store', 'reviewbird' ),
		route: 'connect',
	},
	syncing: {
		text: __( 'Your store is syncing', 'reviewbird' ),
		message: __( 'Your products and reviews are syncing.', 'reviewbird' ),
		buttonText: __( 'View progress', 'reviewbird' ),
		route: 'connect',
	},
	billing_required: {
		text: __( 'Reviewbird is disabled', 'reviewbird' ),
		message: __(
			'Your Reviewbird subscription is not active. Update your billing details to turn Reviewbird back on.',
			'reviewbird'
		),
		buttonText: __( 'Update billing', 'reviewbird' ),
		route: 'billing',
	},
	unhealthy: {
		text: __( 'Reviewbird cannot connect to your store', 'reviewbird' ),
		message: __(
			'Open the connection settings in Reviewbird to reconnect your store.',
			'reviewbird'
		),
		buttonText: __( 'Check connection', 'reviewbird' ),
		route: 'connect',
	},
	error: {
		text: __( 'We could not check your connection', 'reviewbird' ),
		message: __(
			'Refresh to try again, or contact support if the problem continues.',
			'reviewbird'
		),
		buttonText: __( 'Contact support', 'reviewbird' ),
		route: 'support',
	},
	checking: {
		text: __( 'Checking connection…', 'reviewbird' ),
		message: '',
		buttonText: __( 'Open Reviewbird', 'reviewbird' ),
		route: 'settings',
	},
};

function CheckIcon( { className } ) {
	return (
		<svg
			className={ className }
			width="20"
			height="20"
			fill="currentColor"
			viewBox="0 0 20 20"
			aria-hidden="true"
			focusable="false"
		>
			<path
				fillRule="evenodd"
				d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
				clipRule="evenodd"
			/>
		</svg>
	);
}

function InfoIcon( { className } ) {
	return (
		<svg
			className={ className }
			width="20"
			height="20"
			fill="currentColor"
			viewBox="0 0 20 20"
			aria-hidden="true"
			focusable="false"
		>
			<path
				fillRule="evenodd"
				d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
				clipRule="evenodd"
			/>
		</svg>
	);
}

function SpinnerIcon( { className } ) {
	return (
		<svg
			className={ `${ className } reviewbird-connection__icon--spinning` }
			width="20"
			height="20"
			fill="none"
			viewBox="0 0 24 24"
			aria-hidden="true"
			focusable="false"
		>
			<circle
				opacity="0.25"
				cx="12"
				cy="12"
				r="10"
				stroke="currentColor"
				strokeWidth="4"
			></circle>
			<path
				opacity="0.75"
				fill="currentColor"
				d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
			></path>
		</svg>
	);
}

function WarningIcon( { className } ) {
	return (
		<svg
			className={ className }
			width="20"
			height="20"
			fill="currentColor"
			viewBox="0 0 20 20"
			aria-hidden="true"
			focusable="false"
		>
			<path
				fillRule="evenodd"
				d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
				clipRule="evenodd"
			/>
		</svg>
	);
}

function ErrorIcon( { className } ) {
	return (
		<svg
			className={ className }
			width="20"
			height="20"
			fill="currentColor"
			viewBox="0 0 20 20"
			aria-hidden="true"
			focusable="false"
		>
			<path
				fillRule="evenodd"
				d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
				clipRule="evenodd"
			/>
		</svg>
	);
}

export function ExternalLinkIcon() {
	return (
		<svg
			className="reviewbird-external-icon"
			width="16"
			height="16"
			fill="none"
			stroke="currentColor"
			viewBox="0 0 24 24"
			aria-hidden="true"
			focusable="false"
		>
			<path
				strokeLinecap="round"
				strokeLinejoin="round"
				strokeWidth={ 2 }
				d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
			/>
		</svg>
	);
}

function StatusIcon( { status } ) {
	const iconClass = 'reviewbird-connection__icon';

	switch ( status ) {
		case 'healthy':
			return <CheckIcon className={ iconClass } />;
		case 'not_connected':
			return <InfoIcon className={ iconClass } />;
		case 'syncing':
			return <SpinnerIcon className={ iconClass } />;
		case 'billing_required':
			return <WarningIcon className={ iconClass } />;
		case 'unhealthy':
		case 'error':
			return <ErrorIcon className={ iconClass } />;
		default:
			return <SpinnerIcon className={ iconClass } />;
	}
}

async function clearHealthCache() {
	const formData = new FormData();
	formData.append( 'action', 'reviewbird_clear_health_cache' );
	formData.append( 'nonce', window.reviewbirdAdmin.nonce );

	const response = await fetch( window.reviewbirdAdmin.ajaxUrl, {
		method: 'POST',
		body: formData,
	} );
	const result = await response.json();
	if ( ! response.ok || ! result.success ) {
		throw new Error( 'Unable to refresh the store connection' );
	}
}

export async function fetchHealthStatus( signal ) {
	const response = await fetch(
		`${
			window.reviewbirdAdmin.apiUrl
		}/api/woocommerce/health?domain=${ encodeURIComponent(
			window.reviewbirdAdmin.siteDomain || window.location.hostname
		) }`,
		{
			method: 'GET',
			signal,
			cache: 'no-store',
			headers: { Accept: 'application/json' },
		}
	);

	const data = await response.json().catch( () => ( {} ) );
	if (
		! response.ok &&
		! ( response.status === 404 && data.status === 'not_connected' )
	) {
		throw new Error( 'Unable to check the store connection' );
	}
	if ( ! data.status ) {
		throw new Error( 'Invalid store status' );
	}
	const status = data.status;

	return { data, status };
}

function getActionUrl( status, healthData ) {
	const storeId = healthData?.store_id || window.reviewbirdConfig?.storeId;
	const baseUrl = window.reviewbirdAdmin.apiUrl;
	const orgSlug = healthData?.org_slug;

	if ( ! storeId ) {
		return orgSlug
			? `${ baseUrl }/${ orgSlug }/stores`
			: `${ baseUrl }/stores`;
	}

	const config = STATUS_CONFIG[ status ] || STATUS_CONFIG.checking;
	const route = config.route;

	if ( route === 'support' ) {
		return orgSlug
			? `${ baseUrl }/${ orgSlug }/support`
			: `${ baseUrl }/support`;
	}

	return orgSlug
		? `${ baseUrl }/${ orgSlug }/stores/${ storeId }/${ route }`
		: `${ baseUrl }/stores/${ storeId }/${ route }`;
}

export default function ConnectionHealth() {
	const [ healthStatus, setHealthStatus ] = useState( 'checking' );
	const [ healthData, setHealthData ] = useState( null );
	const [ lastChecked, setLastChecked ] = useState( null );
	const [ refreshing, setRefreshing ] = useState( false );
	const [ refreshError, setRefreshError ] = useState( '' );

	const checkHealth = useCallback( async ( shouldClearCache = false ) => {
		setRefreshing( true );
		setRefreshError( '' );
		try {
			if ( shouldClearCache ) {
				await clearHealthCache();
			}

			const { data, status } = await fetchHealthStatus();
			setHealthData( data );
			setHealthStatus( status );
			setLastChecked( new Date() );
		} catch {
			if ( shouldClearCache ) {
				setRefreshError(
					__(
						'The connection could not be refreshed. Please try again.',
						'reviewbird'
					)
				);
			} else {
				setHealthStatus( 'error' );
				setHealthData( null );
			}
		} finally {
			setRefreshing( false );
		}
	}, [] );

	useEffect( () => {
		checkHealth();
		const interval = setInterval( checkHealth, HEALTH_CHECK_INTERVAL );
		return () => clearInterval( interval );
	}, [ checkHealth ] );

	const config = STATUS_CONFIG[ healthStatus ] || STATUS_CONFIG.checking;
	const isChecking = healthStatus === 'checking';

	return (
		<section
			className="reviewbird-connection"
			data-state={ healthStatus }
			aria-busy={ refreshing }
		>
			<div className="reviewbird-connection__main">
				<StatusIcon status={ healthStatus } />
				<div className="reviewbird-connection__copy">
					<h2>{ config.text }</h2>
					{ config.message && <p>{ config.message }</p> }
					{ lastChecked && (
						<p className="reviewbird-connection__meta">
							{ sprintf(
								// translators: %s: time of the last connection check.
								__( 'Last checked: %s', 'reviewbird' ),
								lastChecked.toLocaleTimeString(
									window.reviewbirdAdmin.locale
								)
							) }
						</p>
					) }
				</div>
			</div>
			{ ! isChecking && (
				<div className="reviewbird-connection__actions">
					<a
						href={ getActionUrl( healthStatus, healthData ) }
						target="_blank"
						rel="noopener noreferrer"
						className="reviewbird-settings-link"
					>
						{ config.buttonText }
						<ExternalLinkIcon />
					</a>
					<button
						type="button"
						onClick={ () => checkHealth( true ) }
						disabled={ refreshing }
						className="reviewbird-settings-button"
						aria-label={ __(
							'Refresh connection status',
							'reviewbird'
						) }
					>
						{ refreshing
							? __( 'Refreshing…', 'reviewbird' )
							: __( 'Refresh', 'reviewbird' ) }
					</button>
				</div>
			) }
			{ refreshError && (
				<p className="reviewbird-connection__error" role="alert">
					{ refreshError }
				</p>
			) }
		</section>
	);
}
