import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import ConnectionHealth from './ConnectionHealth';
import TogglePanel from './TogglePanel';
import LoadingSpinner from './LoadingSpinner';

export default function SettingsApp() {
	const [enableWidget, setEnableWidget] = useState(
		window.reviewbirdAdmin?.enableWidget !== undefined ? window.reviewbirdAdmin.enableWidget : true
	);
	const [enableSchema, setEnableSchema] = useState(
		window.reviewbirdAdmin?.enableSchema !== undefined ? window.reviewbirdAdmin.enableSchema : true
	);
	const [loading, setLoading] = useState(true);

	useEffect(() => {
		// Settings are loaded from PHP via reviewbirdAdmin global
		setLoading(false);
	}, []);

	const updateSetting = async (settingName, value) => {
		try {
			const formData = new FormData();
			formData.append('action', `reviewbird_update_${settingName}_setting`);
			formData.append('nonce', window.reviewbirdAdmin.nonce);
			formData.append(settingName, value ? '1' : '0');

			const response = await fetch(window.reviewbirdAdmin.ajaxUrl, {
				method: 'POST',
				body: formData,
			});

			const result = await response.json();

			if (!result.success) {
				throw new Error(result.data || 'Failed to update setting');
			}

			return true;
		} catch (err) {
			console.error(`Failed to update ${settingName} setting:`, err);
			alert(__('Failed to update setting. Please try again.', 'reviewbird-reviews'));
			return false;
		}
	};

	const handleWidgetToggle = async () => {
		const newValue = !enableWidget;
		const success = await updateSetting('enable_widget', newValue);
		if (success) {
			setEnableWidget(newValue);
		}
	};

	const handleSchemaToggle = async () => {
		const newValue = !enableSchema;
		const success = await updateSetting('enable_schema', newValue);
		if (success) {
			setEnableSchema(newValue);
		}
	};

	if (loading) {
		return <LoadingSpinner />;
	}

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
					title={__('ReviewBird Widget', 'reviewbird-reviews')}
					description={__('Display reviewbird review widget on product pages. The widget shows customer reviews and allows customers to submit new reviews.', 'reviewbird-reviews')}
					enabled={enableWidget}
					onToggle={handleWidgetToggle}
					enabledText={__('Widget is enabled on all WooCommerce product pages.', 'reviewbird-reviews')}
				/>

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
							__('Can increase click-through rates by up to 30%', 'reviewbird-reviews'),
							__('Reviews are cached for 4 hours for optimal performance', 'reviewbird-reviews'),
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
