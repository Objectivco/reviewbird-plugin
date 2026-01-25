import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import ConnectionHealth from './ConnectionHealth.jsx';
import TogglePanel from './TogglePanel.jsx';

function getAdminSetting(key, defaultValue = false) {
	const value = window.reviewbirdAdmin?.[key];
	return value !== undefined ? value : defaultValue;
}

async function updateSetting(settingName, value) {
	const formData = new FormData();
	formData.append('action', 'reviewbird_update_setting');
	formData.append('nonce', window.reviewbirdAdmin.nonce);
	formData.append('setting', settingName);
	formData.append('value', value ? '1' : '0');

	const response = await fetch(window.reviewbirdAdmin.ajaxUrl, {
		method: 'POST',
		body: formData,
	});

	const result = await response.json();

	if (!result.success) {
		throw new Error(result.data || 'Failed to update setting');
	}
}

function useToggleSetting(settingKey, apiSettingName) {
	const [enabled, setEnabled] = useState(() => getAdminSetting(settingKey));

	async function handleToggle() {
		const newValue = !enabled;
		try {
			await updateSetting(apiSettingName, newValue);
			setEnabled(newValue);
		} catch (err) {
			console.error(`Failed to update ${apiSettingName} setting:`, err);
			alert(__('Failed to update setting. Please try again.', 'reviewbird-reviews'));
		}
	}

	return [enabled, handleToggle];
}

export default function SettingsApp() {
	const [enableWidget, handleWidgetToggle] = useToggleSetting('enableWidget', 'enable_widget');
	const [enableSchema, handleSchemaToggle] = useToggleSetting('enableSchema', 'enable_schema');
	const [forceReviewsOpen, handleForceReviewsToggle] = useToggleSetting('forceReviewsOpen', 'force_reviews_open');

	return (
		<div className="max-w-4xl mx-auto py-8">
			<header className="mb-8">
				<h1 className="text-3xl font-bold text-gray-900">
					{__('reviewbird Settings', 'reviewbird-reviews')}
				</h1>
				<p className="mt-2 text-gray-600">
					{__('Manage your reviewbird integration with WooCommerce.', 'reviewbird-reviews')}
				</p>
			</header>

			<div className="space-y-6">
				<ConnectionHealth />

				<TogglePanel
					title={__('reviewbird Widget', 'reviewbird-reviews')}
					description={__('Display reviewbird review widget on product pages. The widget shows customer reviews and allows customers to submit new reviews.', 'reviewbird-reviews')}
					enabled={enableWidget}
					onToggle={handleWidgetToggle}
					enabledText={__('Widget is enabled on all WooCommerce product pages.', 'reviewbird-reviews')}
				>
					<div className="mt-4 pt-4 border-t border-gray-200">
						<div className="flex items-center justify-between">
							<div>
								<p className="text-sm font-medium text-gray-900">
									{__('Forcefully enable reviews on all products', 'reviewbird-reviews')}
								</p>
								<p className="text-xs text-gray-500 mt-1">
									{__('Leave this off if you prefer to control reviews per product using the "Enable reviews" checkbox.', 'reviewbird-reviews')}
								</p>
							</div>
							<button
								type="button"
								onClick={handleForceReviewsToggle}
								className={`relative inline-flex flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 ${forceReviewsOpen ? 'bg-indigo-600' : 'bg-gray-200'}`}
								style={{ height: '24px', width: '44px', padding: 0 }}
								role="switch"
								aria-checked={forceReviewsOpen}
							>
								<span
									className={`pointer-events-none inline-block transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${forceReviewsOpen ? 'translate-x-5' : 'translate-x-0'}`}
									style={{ height: '20px', width: '20px' }}
								/>
							</button>
						</div>
					</div>
				</TogglePanel>

				<TogglePanel
					title={__('SEO Schema Markup', 'reviewbird-reviews')}
					description={__('Enable Google-compliant structured data (JSON-LD schema) on product pages for rich snippets in search results.', 'reviewbird-reviews')}
					enabled={enableSchema}
					onToggle={handleSchemaToggle}
					enabledText={__('Schema markup is active on all WooCommerce product pages.', 'reviewbird-reviews')}
					infoBox={{
						title: __('What is Schema Markup?', 'reviewbird-reviews'),
						items: [
							__('Displays star ratings in Google search results', 'reviewbird-reviews'),
							__('Shows review counts and product information', 'reviewbird-reviews'),
							__('Review schema is cached for 4 hours for optimal performance', 'reviewbird-reviews'),
						]
					}}
					links={[
						{
							href: 'https://search.google.com/test/rich-results',
							text: __('Test with Google Rich Results', 'reviewbird-reviews')
						},
						{
							href: 'https://validator.schema.org/',
							text: __('Validate Schema', 'reviewbird-reviews')
						}
					]}
				/>
			</div>
		</div>
	);
}
