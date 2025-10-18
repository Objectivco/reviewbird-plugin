import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

export default function ReviewSyncPanel({ isConnected, productsAreSynced }) {
	const [syncStatus, setSyncStatus] = useState(null);
	const [loading, setLoading] = useState(true);
	const [syncing, setSyncing] = useState(false);
	const [error, setError] = useState(null);

	useEffect(() => {
		if (isConnected) {
			loadSyncStatus();
			// Poll for status while syncing
			const interval = setInterval(() => {
				if (syncStatus?.is_syncing) {
					loadSyncStatus();
				}
			}, 2000); // Poll every 2 seconds

			return () => clearInterval(interval);
		}
	}, [isConnected, syncStatus?.is_syncing]);

	const loadSyncStatus = async () => {
		try {
			const data = await apiFetch({ path: '/reviewbop/v1/sync/reviews/status' });
			setSyncStatus(data);
			setError(null);
		} catch (err) {
			console.error('Failed to load review sync status:', err);
			setError(err.message || __('Failed to load review sync status', 'reviewbop-reviews'));
		} finally {
			setLoading(false);
		}
	};

	const startSync = async () => {
		setSyncing(true);
		setError(null);
		try {
			await apiFetch({
				path: '/reviewbop/v1/sync/reviews/start',
				method: 'POST',
			});
			// Reload status immediately
			await loadSyncStatus();
		} catch (err) {
			setError(err.message || __('Failed to start review sync', 'reviewbop-reviews'));
		} finally {
			setSyncing(false);
		}
	};

	if (!isConnected) {
		return null;
	}

	if (loading) {
		return (
			<div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
				<div className="animate-pulse">
					<div className="h-4 bg-gray-200 rounded w-1/4 mb-4"></div>
					<div className="h-3 bg-gray-200 rounded w-1/2"></div>
				</div>
			</div>
		);
	}

	const percentage = syncStatus?.total_reviews > 0
		? Math.round((syncStatus.synced_reviews / syncStatus.total_reviews) * 100)
		: 0;

	const isDisabled = !productsAreSynced || syncing || syncStatus?.total_reviews === 0;

	return (
		<div className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
			<div className="px-6 py-5 border-b border-gray-200 bg-gray-50">
				<h2 className="text-xl font-semibold text-gray-900">
					{__('Review Sync', 'reviewbop-reviews')}
				</h2>
			</div>

			<div className="px-6 py-5 space-y-6">
				{!productsAreSynced && (
					<div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
						<div className="flex">
							<svg className="w-5 h-5 text-yellow-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
								<path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
							</svg>
							<div className="ml-3">
								<h3 className="text-sm font-medium text-yellow-800">
									{__('Products Must Be Synced First', 'reviewbop-reviews')}
								</h3>
								<p className="text-sm text-yellow-700 mt-1">
									{__('Please sync your products before syncing reviews. Reviews are linked to products.', 'reviewbop-reviews')}
								</p>
							</div>
						</div>
					</div>
				)}

				{error && (
					<div className="bg-red-50 border border-red-200 rounded-lg p-4">
						<div className="flex">
							<svg className="w-5 h-5 text-red-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
								<path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
							</svg>
							<div className="ml-3">
								<p className="text-sm text-red-800">{error}</p>
							</div>
						</div>
					</div>
				)}

				{syncStatus?.is_syncing ? (
					<div className="space-y-4">
						<div className="flex items-center justify-between text-sm">
							<span className="font-medium text-gray-700">
								{__('Syncing reviews...', 'reviewbop-reviews')}
							</span>
							<span className="text-gray-600">
								{syncStatus.synced_reviews} / {syncStatus.total_reviews}
							</span>
						</div>

						<div className="relative">
							<div className="overflow-hidden h-4 text-xs flex rounded bg-gray-200">
								<div
									style={{ width: `${percentage}%` }}
									className="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-indigo-600 transition-all duration-500"
								></div>
							</div>
							<div className="text-center mt-2 text-sm font-medium text-gray-700">
								{percentage}%
							</div>
						</div>

						<div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
							<div className="flex">
								<svg className="w-5 h-5 text-blue-600 mt-0.5 animate-spin" fill="none" viewBox="0 0 24 24">
									<circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
									<path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
								</svg>
								<div className="ml-3">
									<p className="text-sm text-blue-800">
										{__('Sync in progress. This may take a few minutes...', 'reviewbop-reviews')}
									</p>
								</div>
							</div>
						</div>
					</div>
				) : (
					<div className="space-y-4">
						<div className="grid grid-cols-3 gap-4">
							<div className="bg-gray-50 rounded-lg p-4">
								<div className="text-2xl font-bold text-gray-900">
									{syncStatus?.total_reviews || 0}
								</div>
								<div className="text-sm text-gray-600">
									{__('Approved reviews', 'reviewbop-reviews')}
								</div>
							</div>

							<div className="bg-green-50 rounded-lg p-4">
								<div className="text-2xl font-bold text-green-900">
									{syncStatus?.synced_reviews || 0}
								</div>
								<div className="text-sm text-green-700">
									{__('Synced', 'reviewbop-reviews')}
								</div>
							</div>

							{syncStatus?.failed_reviews > 0 && (
								<div className="bg-red-50 rounded-lg p-4">
									<div className="text-2xl font-bold text-red-900">
										{syncStatus.failed_reviews}
									</div>
									<div className="text-sm text-red-700">
										{__('Failed', 'reviewbop-reviews')}
									</div>
								</div>
							)}
						</div>

						{syncStatus?.last_sync && (
							<p className="text-sm text-gray-600">
								{__('Last synced:', 'reviewbop-reviews')}{' '}
								{new Date(syncStatus.last_sync * 1000).toLocaleString()}
							</p>
						)}

						{syncStatus?.needs_sync && productsAreSynced ? (
							<>
								<div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
									<div className="flex">
										<svg className="w-5 h-5 text-yellow-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
											<path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
										</svg>
										<div className="ml-3">
											<h3 className="text-sm font-medium text-yellow-800">
												{__('Sync Required', 'reviewbop-reviews')}
											</h3>
											<p className="text-sm text-yellow-700 mt-1">
												{__('You have approved reviews that need to be synced to ReviewBop.', 'reviewbop-reviews')}
											</p>
										</div>
									</div>
								</div>
								
								<button
									type="button"
									onClick={startSync}
									disabled={isDisabled}
									className="inline-flex items-center justify-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm border border-transparent rounded shadow-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
								>
									{syncing ? __('Starting sync...', 'reviewbop-reviews') : __('Sync Reviews', 'reviewbop-reviews')}
								</button>
							</>
						) : productsAreSynced && !syncStatus?.needs_sync ? (
							<div className="bg-green-50 border border-green-200 rounded-lg p-4">
								<div className="flex">
									<svg className="w-5 h-5 text-green-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
										<path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
									</svg>
									<div className="ml-3">
										<p className="text-sm text-green-800">
											{__('All reviews are synced', 'reviewbop-reviews')}
										</p>
									</div>
								</div>
							</div>
						) : null}

						{!productsAreSynced && (
							<p className="text-xs text-gray-500">
								{__('Sync products first to enable review sync', 'reviewbop-reviews')}
							</p>
						)}
					</div>
				)}
			</div>
		</div>
	);
}
