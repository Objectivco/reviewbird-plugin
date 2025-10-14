import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export default function ReviewRequestPanel({ settings, onSave, saving, isConnected }) {
	const [triggerStatus, setTriggerStatus] = useState(
		settings?.review_request_trigger_status || 'completed'
	);

	const handleStatusChange = async (e) => {
		const status = e.target.value;
		setTriggerStatus(status);

		try {
			await onSave({ review_request_trigger_status: status });
		} catch (err) {
			// Error handled by parent - revert to previous value
			setTriggerStatus(settings?.review_request_trigger_status || 'completed');
		}
	};

	if (!isConnected) {
		return null;
	}

	return (
		<div className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
			<div className="px-6 py-5 border-b border-gray-200 bg-gray-50">
				<h2 className="text-xl font-semibold text-gray-900">
					{__('Review Requests', 'reviewapp-reviews')}
				</h2>
			</div>

			<div className="px-6 py-5 space-y-6">
				<div>
					<p className="text-sm text-gray-600 mb-4">
						{__('Review request emails are automatically sent to customers after their orders are fulfilled. Configure which order status triggers the emails below.', 'reviewapp-reviews')}
					</p>

					<div className="space-y-4">
						<div>
							<label htmlFor="trigger-status" className="block text-sm font-medium text-gray-900 mb-2">
								{__('Fulfilled Order Status', 'reviewapp-reviews')}
							</label>
							<select
								id="trigger-status"
								value={triggerStatus}
								onChange={handleStatusChange}
								disabled={saving}
								className="block w-full max-w-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
							>
								{settings?.available_order_statuses && Object.entries(settings.available_order_statuses).map(([value, label]) => (
									<option key={value} value={value}>
										{label}
									</option>
								))}
							</select>
							<p className="mt-2 text-sm text-gray-500">
								{__('When an order reaches this status, a review request email will be scheduled to be sent a few days later.', 'reviewapp-reviews')}
							</p>
						</div>

						<div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
							<div className="flex">
								<svg className="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
									<path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
								</svg>
								<div className="ml-3">
									<h3 className="text-sm font-medium text-blue-800">
										{__('How it works', 'reviewapp-reviews')}
									</h3>
									<div className="mt-2 text-sm text-blue-700">
										<ul className="list-disc pl-5 space-y-1">
											<li>{__('Review requests are sent a few days after the order reaches the selected status', 'reviewapp-reviews')}</li>
											<li>{__('Customers can review up to 3 products per email', 'reviewapp-reviews')}</li>
											<li>{__('Reminder emails are sent automatically if no review is submitted', 'reviewapp-reviews')}</li>
											<li>{__('Products already reviewed are excluded from future reminders', 'reviewapp-reviews')}</li>
										</ul>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	);
}
