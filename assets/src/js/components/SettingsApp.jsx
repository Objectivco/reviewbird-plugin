import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import ConnectionHealth from './ConnectionHealth.jsx';
import TogglePanel from './TogglePanel.jsx';
import WelcomeScreen from './WelcomeScreen.jsx';
import GoogleCustomerReviews from './GoogleCustomerReviews.jsx';
import reviewbirdLogo from '../../images/logo-dark.svg';

function getAdminSetting( key, defaultValue = false ) {
	const value = window.reviewbirdAdmin?.[ key ];
	return value !== undefined ? value : defaultValue;
}

async function updateSetting( settingName, value ) {
	const formData = new FormData();
	formData.append( 'action', 'reviewbird_update_setting' );
	formData.append( 'nonce', window.reviewbirdAdmin.nonce );
	formData.append( 'setting', settingName );
	formData.append( 'value', value ? '1' : '0' );

	const response = await fetch( window.reviewbirdAdmin.ajaxUrl, {
		method: 'POST',
		body: formData,
	} );
	const result = await response.json();
	if ( ! response.ok || ! result.success ) {
		throw new Error();
	}
}

function useToggleSetting( settingKey, apiSettingName ) {
	const [ enabled, setEnabled ] = useState( () =>
		[ true, 1, '1' ].includes( getAdminSetting( settingKey ) )
	);
	const [ pending, setPending ] = useState( false );
	const [ error, setError ] = useState( '' );

	async function toggle() {
		if ( pending ) {
			return;
		}
		setPending( true );
		setError( '' );
		try {
			await updateSetting( apiSettingName, ! enabled );
			setEnabled( ! enabled );
		} catch {
			setError(
				__(
					'The setting could not be saved. Please try again.',
					'reviewbird'
				)
			);
		} finally {
			setPending( false );
		}
	}

	return { enabled, pending, error, toggle };
}

export default function SettingsApp() {
	const pageType = getAdminSetting( 'pageType', 'settings' );
	const widget = useToggleSetting( 'enableWidget', 'enable_widget' );
	const schema = useToggleSetting( 'enableSchema', 'enable_schema' );
	const forceReviews = useToggleSetting(
		'forceReviewsOpen',
		'force_reviews_open'
	);

	if ( 'get_started' === pageType ) {
		return (
			<div className="reviewbird-get-started-page">
				<WelcomeScreen
					registerUrl={ window.reviewbirdAdmin.registerUrl }
					dashboardUrl={ window.reviewbirdAdmin.dashboardUrl }
					settingsUrl={ window.reviewbirdAdmin.settingsUrl }
				/>
			</div>
		);
	}

	return (
		<div className="reviewbird-settings-shell">
			<header className="reviewbird-brand-bar">
				<img
					className="reviewbird-brand-logo"
					src={ reviewbirdLogo }
					alt="Reviewbird"
					width="308"
					height="39"
				/>
			</header>
			<div className="reviewbird-settings-page">
				<header className="reviewbird-settings-page__header">
					<h1>{ __( 'Reviewbird Settings', 'reviewbird' ) }</h1>
				</header>
				<ConnectionHealth />
				<div className="reviewbird-settings-card reviewbird-settings-page__features">
					<TogglePanel
						id="reviewbird-widget-title"
						title={ __( 'Reviewbird Widget', 'reviewbird' ) }
						description={ __(
							'Show customer reviews on product pages.',
							'reviewbird'
						) }
						enabled={ widget.enabled }
						onToggle={ widget.toggle }
						isSaving={ widget.pending || forceReviews.pending }
						error={ widget.error }
					>
						<div className="reviewbird-toggle-panel__option">
							<div className="reviewbird-settings-choice">
								<input
									id="reviewbird-force-reviews"
									className="reviewbird-settings-checkbox"
									type="checkbox"
									checked={ forceReviews.enabled }
									disabled={
										forceReviews.pending || widget.pending
									}
									aria-busy={ forceReviews.pending }
									aria-describedby="reviewbird-force-reviews-help"
									onChange={ forceReviews.toggle }
								/>
								<div>
									<label htmlFor="reviewbird-force-reviews">
										{ __(
											'Enable reviews for all products',
											'reviewbird'
										) }
									</label>
									<p id="reviewbird-force-reviews-help">
										{ __(
											'Also show reviews on products with reviews turned off.',
											'reviewbird'
										) }
									</p>
								</div>
							</div>
							{ forceReviews.error && (
								<p
									className="reviewbird-settings-error"
									role="alert"
								>
									{ forceReviews.error }
								</p>
							) }
						</div>
					</TogglePanel>
					<TogglePanel
						id="reviewbird-schema-title"
						title={ __( 'Search results', 'reviewbird' ) }
						description={ __(
							'Add review data that search engines can use for star ratings.',
							'reviewbird'
						) }
						enabled={ schema.enabled }
						onToggle={ schema.toggle }
						isSaving={ schema.pending }
						error={ schema.error }
						links={ [
							{
								href: 'https://search.google.com/test/rich-results',
								text: __( 'Test rich results', 'reviewbird' ),
							},
							{
								href: 'https://validator.schema.org/',
								text: __( 'Validate schema', 'reviewbird' ),
							},
						] }
					/>
				</div>
				<GoogleCustomerReviews />
			</div>
		</div>
	);
}
