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
					{__('Review Requests', 'reviewbird-reviews')}
				</h2>
			</div>

			<div className="px-6 py-5 space-y-6">
				<div>
					<p className="text-sm text-gray-600 mb-4">
						{__('Review request emails are automatically sent to customers after their orders are fulfilled. Configure which order status triggers the emails below.', 'reviewbird-reviews')}
					</p>

					<div className="space-y-4">
						<div>
							<label htmlFor="trigger-status" className="block text-sm font-medium text-gray-900 mb-2">
								{__('Fulfilled Order Status', 'reviewbird-reviews')}
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
								{__('When an order reaches this status, a review request email will be scheduled.', 'reviewbird-reviews')}
							</p>
						</div>

						<div className="bg-gray-50 rounded-lg p-4">
							<div className="text-2xl font-bold text-gray-900">
								{settings?.orders_synced_count || 0}
							</div>
							<div className="text-sm text-gray-600">
								{__('Synced Orders', 'reviewbird-reviews')}
							</div>
							{settings?.orders_last_synced > 0 && (
								<p className="text-xs text-gray-500 mt-1">
									{__('Last synced:', 'reviewbird-reviews')}{' '}
									{new Date(settings.orders_last_synced * 1000).toLocaleString()}
								</p>
							)}
						</div>
					</div>
				</div>
			</div>
		</div>
	);
}
