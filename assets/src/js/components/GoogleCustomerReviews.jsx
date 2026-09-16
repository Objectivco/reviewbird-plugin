import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { ExternalLinkIcon } from './ConnectionHealth.jsx';

export default function GoogleCustomerReviews() {
	const [ settings, setSettings ] = useState(
		window.reviewbirdAdmin.googleCustomerReviews
	);
	const [ pending, setPending ] = useState( false );
	const [ message, setMessage ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const integrationDescription =
		{
			enabled: __( 'Integration is currently enabled.', 'reviewbird' ),
			disabled: __( 'Integration is currently disabled.', 'reviewbird' ),
		}[ settings.status ] ||
		__( 'Integration status is unknown.', 'reviewbird' );

	async function refresh() {
		if ( pending ) {
			return;
		}
		setPending( true );
		setMessage( '' );
		setError( '' );
		try {
			const body = new FormData();
			body.append( 'action', 'reviewbird_clear_health_cache' );
			body.append( 'nonce', window.reviewbirdAdmin.nonce );
			const response = await fetch( window.reviewbirdAdmin.ajaxUrl, {
				method: 'POST',
				body,
			} );
			const result = await response.json();
			if ( ! response.ok || ! result.success ) {
				throw new Error(
					typeof result.data === 'string' ? result.data : ''
				);
			}
			if ( ! result.data?.googleCustomerReviews ) {
				throw new Error();
			}
			setSettings( result.data.googleCustomerReviews );
			setMessage( __( 'Status updated.', 'reviewbird' ) );
		} catch ( failure ) {
			setError(
				failure.message ||
					__(
						'The connection could not be refreshed. Please try again.',
						'reviewbird'
					)
			);
		} finally {
			setPending( false );
		}
	}

	return (
		<section
			className="reviewbird-settings-card reviewbird-gcr-settings"
			aria-labelledby="reviewbird-gcr-settings-title"
		>
			<header className="reviewbird-gcr-settings__header">
				<h2 id="reviewbird-gcr-settings-title">
					{ __( 'Google Customer Reviews', 'reviewbird' ) }
				</h2>
			</header>
			<div className="reviewbird-gcr-settings__content">
				<p>
					{ __(
						"When the integration is enabled, Google's consent window opens automatically for every order on the thank you page.",
						'reviewbird'
					) }
				</p>
				<div className="reviewbird-gcr-settings__integration">
					<div className="reviewbird-gcr-settings__integration-help">
						<p>
							{ __(
								'Use Configure integration to enable or disable Google Customer Reviews. Then select Refresh status.',
								'reviewbird'
							) }
						</p>
						<p>
							<strong id="reviewbird-gcr-integration-status">
								{ integrationDescription }
							</strong>
						</p>
					</div>
					<div className="reviewbird-gcr-toolbar">
						<a
							className="reviewbird-settings-link"
							href={ settings.integrationsUrl }
							target="_blank"
							rel="noopener noreferrer"
						>
							{ __( 'Configure integration', 'reviewbird' ) }
							<ExternalLinkIcon />
						</a>
						<button
							type="button"
							className="reviewbird-settings-button"
							disabled={ pending }
							onClick={ refresh }
							aria-busy={ pending }
							aria-label={ __(
								'Refresh integration status',
								'reviewbird'
							) }
						>
							<svg
								width="16"
								height="16"
								viewBox="0 0 24 24"
								fill="none"
								stroke="currentColor"
								strokeWidth="1.75"
								aria-hidden="true"
							>
								<path
									strokeLinecap="round"
									strokeLinejoin="round"
									d="M20 7v5h-5M4 17v-5h5M6.1 7a7 7 0 0 1 11.55-1.4L20 8M4 16l2.35 2.4A7 7 0 0 0 17.9 17"
								/>
							</svg>
							{ pending
								? __( 'Refreshing…', 'reviewbird' )
								: __( 'Refresh status', 'reviewbird' ) }
						</button>
					</div>
				</div>
				{ error && (
					<p className="reviewbird-gcr-settings__notice" role="alert">
						{ error }
					</p>
				) }
				{ message && (
					<p className="screen-reader-text" role="status">
						{ message }
					</p>
				) }
			</div>
		</section>
	);
}
