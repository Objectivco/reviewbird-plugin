import { __ } from '@wordpress/i18n';

export default function LoadingSpinner() {
	return (
		<div className="flex items-center justify-center min-h-screen">
			<div className="text-center">
				<div className="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
				<p className="mt-4 text-gray-600">{__('Loading...', 'reviewbird-reviews')}</p>
			</div>
		</div>
	);
}
