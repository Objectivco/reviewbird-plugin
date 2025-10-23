import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export default function SchemaPanel({ settings, onSave, saving, isConnected }) {
	const [enableSchema, setEnableSchema] = useState(
		window.reviewbopAdmin?.enableSchema !== undefined ? window.reviewbopAdmin.enableSchema : true
	);
	const [isSaving, setIsSaving] = useState(false);

	if (!isConnected) {
		return null;
	}

	const handleToggle = async () => {
		setIsSaving(true);
		const newValue = !enableSchema;

		try {
			// Update WordPress option directly via admin-ajax
			const formData = new FormData();
			formData.append('action', 'reviewbop_update_schema_setting');
			formData.append('nonce', window.reviewbopAdmin.nonce);
			formData.append('enable_schema', newValue ? '1' : '0');

			const response = await fetch(window.reviewbopAdmin.ajaxUrl, {
				method: 'POST',
				body: formData,
			});

			const result = await response.json();

			if (result.success) {
				setEnableSchema(newValue);
			} else {
				throw new Error(result.data || 'Failed to update setting');
			}
		} catch (err) {
			console.error('Failed to update schema setting:', err);
			alert(__('Failed to update schema setting. Please try again.', 'reviewbop-reviews'));
		} finally {
			setIsSaving(false);
		}
	};

	return (
		<div className="bg-white rounded-lg shadow p-6">
			<div className="flex items-start justify-between">
				<div className="flex-1">
					<h2 className="text-lg font-semibold text-gray-900">
						{__('SEO Schema Markup', 'reviewbop-reviews')}
					</h2>
					<p className="mt-1 text-sm text-gray-600">
						{__('Enable Google-compliant structured data (JSON-LD schema) on product pages for rich snippets in search results.', 'reviewbop-reviews')}
					</p>

					<div className="mt-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
						<div className="flex">
							<svg className="w-5 h-5 text-blue-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
								<path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
							</svg>
							<div className="ml-3 text-sm text-blue-800">
								<p className="font-medium">{__('What is Schema Markup?', 'reviewbop-reviews')}</p>
								<ul className="mt-2 space-y-1">
									<li>• {__('Displays star ratings in Google search results', 'reviewbop-reviews')}</li>
									<li>• {__('Shows review counts and product information', 'reviewbop-reviews')}</li>
									<li>• {__('Can increase click-through rates by up to 30%', 'reviewbop-reviews')}</li>
									<li>• {__('Reviews are cached for 4 hours for optimal performance', 'reviewbop-reviews')}</li>
								</ul>
							</div>
						</div>
					</div>
				</div>

				<div className="ml-6">
					<button
						type="button"
						onClick={handleToggle}
						disabled={isSaving}
						className={`relative inline-flex flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 ${
							enableSchema ? 'bg-indigo-600' : 'bg-gray-200'
						} ${isSaving ? 'opacity-50 cursor-not-allowed' : ''}`}
						style={{
							height: '24px',
							width: '44px',
							padding: '0',
							margin: '0',
							verticalAlign: 'middle',
							boxSizing: 'border-box',
							lineHeight: '1'
						}}
						role="switch"
						aria-checked={enableSchema}
					>
						<span
							className={`pointer-events-none inline-block transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
								enableSchema ? 'translate-x-5' : 'translate-x-0'
							}`}
							style={{
								height: '20px',
								width: '20px',
								display: 'block',
								margin: '0',
								boxSizing: 'border-box'
							}}
						/>
					</button>
					<p className="mt-2 text-xs text-gray-500 text-right" style={{ margin: '8px 0 0 0', fontSize: '12px', lineHeight: '1.4' }}>
						{enableSchema ? __('Enabled', 'reviewbop-reviews') : __('Disabled', 'reviewbop-reviews')}
					</p>
				</div>
			</div>

			{enableSchema && (
				<div className="mt-4 pt-4 border-t border-gray-200">
					<p className="text-sm text-gray-600">
						{__('Schema markup is active on all WooCommerce product pages.', 'reviewbop-reviews')}
					</p>
					<div className="mt-3 space-y-2">
						<a
							href="https://search.google.com/test/rich-results"
							target="_blank"
							rel="noopener noreferrer"
							className="inline-flex items-center text-sm text-indigo-600 hover:text-indigo-700"
						>
							{__('Test with Google Rich Results', 'reviewbop-reviews')}
							<svg className="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
							</svg>
						</a>
						<span className="mx-2 text-gray-400">•</span>
						<a
							href="https://validator.schema.org/"
							target="_blank"
							rel="noopener noreferrer"
							className="inline-flex items-center text-sm text-indigo-600 hover:text-indigo-700"
						>
							{__('Validate Schema', 'reviewbop-reviews')}
							<svg className="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
							</svg>
						</a>
					</div>
				</div>
			)}
		</div>
	);
}
