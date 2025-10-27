import { __ } from '@wordpress/i18n';
import classNames from 'classnames';

export default function StatusIndicator({ status, storeId }) {
	const statusConfig = {
		connected: {
			color: 'green',
			icon: '●',
			label: __('Connected', 'reviewbird-reviews'),
		},
		disconnected: {
			color: 'gray',
			icon: '●',
			label: __('Not Connected', 'reviewbird-reviews'),
		},
		error: {
			color: 'red',
			icon: '●',
			label: __('Connection Error', 'reviewbird-reviews'),
		},
	};

	const config = statusConfig[status] || statusConfig.disconnected;

	return (
		<span
			className={classNames(
				'inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-medium',
				{
					'bg-green-100 text-green-800': config.color === 'green',
					'bg-gray-100 text-gray-800': config.color === 'gray',
					'bg-red-100 text-red-800': config.color === 'red',
				}
			)}
		>
			<span className="text-xs">{config.icon}</span>
			{config.label}
		</span>
	);
}
