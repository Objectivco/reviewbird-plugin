import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import ConnectionHealth from './ConnectionHealth.jsx';
import TogglePanel from './TogglePanel.jsx';
import WelcomeScreen from './WelcomeScreen.jsx';

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

	if ( ! result.success ) {
		throw new Error( result.data || 'Failed to update setting' );
	}
}

function useToggleSetting( settingKey, apiSettingName, onError ) {
	const [ enabled, setEnabled ] = useState( () =>
		getAdminSetting( settingKey )
	);

	async function handleToggle() {
		const newValue = ! enabled;
		try {
			await updateSetting( apiSettingName, newValue );
			setEnabled( newValue );
			onError( '' );
		} catch {
			onError(
				__(
					'Failed to update setting. Please try again.',
					'reviewbird'
				)
			);
		}
	}

	return [ enabled, handleToggle ];
}

export default function SettingsApp() {
	const storeId = getAdminSetting( 'storeId', null );
	const [ errorMessage, setErrorMessage ] = useState( '' );
	const [ enableWidget, handleWidgetToggle ] = useToggleSetting(
		'enableWidget',
		'enable_widget',
		setErrorMessage
	);
	const [ enableSchema, handleSchemaToggle ] = useToggleSetting(
		'enableSchema',
		'enable_schema',
		setErrorMessage
	);
	const [ forceReviewsOpen, handleForceReviewsToggle ] = useToggleSetting(
		'forceReviewsOpen',
		'force_reviews_open',
		setErrorMessage
	);
	const hasConnectedStore = Boolean( storeId );

	if ( ! hasConnectedStore ) {
		return (
			<div className="mx-auto max-w-7xl py-8">
				<WelcomeScreen
					registerUrl={ window.reviewbirdAdmin.registerUrl }
					appHomeUrl={ window.reviewbirdAdmin.appHomeUrl }
					marketingUrl={ window.reviewbirdAdmin.marketingUrl }
				/>
			</div>
		);
	}

	return (
		<div className="mx-auto max-w-5xl py-8">
			<header className="mb-8">
				<p className="reviewbird-eyebrow mb-3">
					{ __( 'Connected Store Settings', 'reviewbird' ) }
				</p>
				<h1 className="reviewbird-display-title text-3xl text-gray-900 md:text-4xl">
					{ __( 'Reviewbird Settings', 'reviewbird' ) }
				</h1>
				<p className="mt-3 max-w-2xl text-base leading-7 text-gray-600">
					{ __(
						'Manage your Reviewbird integration with WooCommerce.',
						'reviewbird'
					) }
				</p>
			</header>

			<div className="space-y-6">
				{ errorMessage && (
					<div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 shadow-sm">
						{ errorMessage }
					</div>
				) }

				<ConnectionHealth />

				<TogglePanel
					title={ __( 'Reviewbird Widget', 'reviewbird' ) }
					description={ __(
						'Display Reviewbird review widget on product pages. The widget shows customer reviews and allows customers to submit new reviews.',
						'reviewbird'
					) }
					enabled={ enableWidget }
					onToggle={ handleWidgetToggle }
					enabledText={ __(
						'Widget is enabled on all WooCommerce product pages.',
						'reviewbird'
					) }
				>
					<div className="mt-4 pt-4 border-t border-gray-200">
						<div className="flex items-center justify-between">
							<div>
								<p className="text-sm font-medium text-gray-900">
									{ __(
										'Forcefully enable reviews on all products',
										'reviewbird'
									) }
								</p>
								<p className="text-xs text-gray-500 mt-1">
									{ __(
										'Leave this off if you prefer to control reviews per product using the "Enable reviews" checkbox.',
										'reviewbird'
									) }
								</p>
							</div>
							<button
								type="button"
								onClick={ handleForceReviewsToggle }
								className={ `relative inline-flex flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 ${
									forceReviewsOpen
										? 'bg-indigo-600'
										: 'bg-gray-200'
								}` }
								style={ {
									height: '24px',
									width: '44px',
									padding: 0,
								} }
								role="switch"
								aria-checked={ forceReviewsOpen }
							>
								<span
									className={ `pointer-events-none inline-block transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
										forceReviewsOpen
											? 'translate-x-5'
											: 'translate-x-0'
									}` }
									style={ { height: '20px', width: '20px' } }
								/>
							</button>
						</div>
					</div>
				</TogglePanel>

				<TogglePanel
					title={ __( 'SEO Schema Markup', 'reviewbird' ) }
					description={ __(
						'Enable Google-compliant structured data (JSON-LD schema) on product pages for rich snippets in search results.',
						'reviewbird'
					) }
					enabled={ enableSchema }
					onToggle={ handleSchemaToggle }
					enabledText={ __(
						'Schema markup is active on all WooCommerce product pages.',
						'reviewbird'
					) }
					infoBox={ {
						title: __( 'What is Schema Markup?', 'reviewbird' ),
						items: [
							__(
								'Displays star ratings in Google search results',
								'reviewbird'
							),
							__(
								'Shows review counts and product information',
								'reviewbird'
							),
							__(
								'Review schema is cached for 4 hours for optimal performance',
								'reviewbird'
							),
						],
					} }
					links={ [
						{
							href: 'https://search.google.com/test/rich-results',
							text: __(
								'Test with Google Rich Results',
								'reviewbird'
							),
						},
						{
							href: 'https://validator.schema.org/',
							text: __( 'Validate Schema', 'reviewbird' ),
						},
					] }
				/>
			</div>
		</div>
	);
}
