import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { ExternalLinkIcon } from './ConnectionHealth.jsx';

const fields = [
	[ 'heading', __( 'Heading', 'reviewbird' ), 160 ],
	[ 'message', __( 'Message', 'reviewbird' ), 2000 ],
	[ 'yes_label', __( 'Yes button label', 'reviewbird' ), 80 ],
	[ 'no_label', __( 'No button label', 'reviewbird' ), 80 ],
];

async function request( action, values = {} ) {
	const body = new FormData();
	body.append( 'action', action );
	body.append( 'nonce', window.reviewbirdAdmin.nonce );
	Object.entries( values ).forEach( ( [ key, value ] ) =>
		body.append( key, value )
	);
	const response = await fetch( window.reviewbirdAdmin.ajaxUrl, {
		method: 'POST',
		body,
	} );
	const result = await response.json();
	if ( ! response.ok || ! result.success ) {
		throw new Error( typeof result.data === 'string' ? result.data : '' );
	}
	return result.data;
}

export default function GoogleCustomerReviews() {
	const [ settings, setSettings ] = useState(
		window.reviewbirdAdmin.googleCustomerReviews
	);
	const [ prompt, setPrompt ] = useState( settings.prompt );
	const [ saving, setSaving ] = useState( false );
	const [ status, setStatus ] = useState( '' );
	const [ pending, setPending ] = useState( '' );
	const [ controlMessage, setControlMessage ] = useState( '' );
	const [ controlError, setControlError ] = useState( '' );
	const integrationStatus = {
		enabled: {
			label: __( 'Enabled', 'reviewbird' ),
			className: 'bg-green-50 text-green-800',
		},
		disabled: {
			label: __( 'Disabled', 'reviewbird' ),
			className: 'bg-gray-100 text-gray-600',
		},
		unknown: {
			label: __( 'Refresh needed', 'reviewbird' ),
			className: 'bg-yellow-50 text-yellow-800',
		},
	}[ settings.status || 'unknown' ];

	async function updateControl( action ) {
		if ( pending ) {
			return;
		}
		setPending( action );
		setControlMessage( '' );
		setControlError( '' );
		try {
			if ( action === 'refresh' ) {
				const data = await request( 'reviewbird_clear_health_cache' );
				if ( ! data.googleCustomerReviews ) {
					throw new Error();
				}
				setSettings( data.googleCustomerReviews );
				setControlMessage( __( 'Connection updated.', 'reviewbird' ) );
			} else {
				const data = await request( 'reviewbird_update_setting', {
					setting: 'enable_gcr_prompt',
					value: settings.promptEnabled ? '0' : '1',
				} );
				setSettings( ( current ) => ( {
					...current,
					promptEnabled: data.value,
				} ) );
				setControlMessage( __( 'Setting saved.', 'reviewbird' ) );
			}
		} catch ( error ) {
			setControlError(
				error.message ||
					( action === 'refresh'
						? __(
								'The connection could not be refreshed. Please try again.',
								'reviewbird'
						  )
						: __(
								'The setting could not be saved. Please try again.',
								'reviewbird'
						  ) )
			);
		} finally {
			setPending( '' );
		}
	}

	async function save( values ) {
		setSaving( true );
		setStatus( '' );
		try {
			const data = await request(
				'reviewbird_update_gcr_prompt',
				values
			);
			setPrompt( data.prompt );
			setStatus( __( 'Prompt saved.', 'reviewbird' ) );
		} catch ( error ) {
			setStatus(
				error.message ||
					__(
						'The prompt could not be saved. Please try again.',
						'reviewbird'
					)
			);
		} finally {
			setSaving( false );
		}
	}

	return (
		<section
			className="bg-white rounded-lg shadow p-6"
			aria-labelledby="reviewbird-gcr-settings-title"
		>
			<div className="flex flex-wrap items-center gap-3">
				<h2
					id="reviewbird-gcr-settings-title"
					className="text-lg font-semibold text-gray-900"
				>
					{ __( 'Google Customer Reviews', 'reviewbird' ) }
				</h2>
				<span
					id="reviewbird-gcr-integration-status"
					className={ `rounded-full px-3 py-1 text-xs font-medium ${ integrationStatus.className }` }
				>
					{ integrationStatus.label }
				</span>
			</div>
			<div className="reviewbird-gcr-toolbar mt-4 flex flex-wrap items-center gap-2">
				<a
					className="button"
					href={ settings.integrationsUrl }
					target="_blank"
					rel="noopener noreferrer"
				>
					{ __( 'Manage integration in Reviewbird', 'reviewbird' ) }
					<ExternalLinkIcon />
				</a>
				<button
					type="button"
					className="button"
					disabled={ !! pending }
					onClick={ () => updateControl( 'refresh' ) }
				>
					{ pending === 'refresh'
						? __( 'Refreshing…', 'reviewbird' )
						: __( 'Refresh', 'reviewbird' ) }
				</button>
			</div>
			<div className="mt-5 flex items-center justify-between gap-4 border-t border-gray-200 pt-5">
				<label
					id="reviewbird-gcr-prompt-label"
					className="text-sm font-medium text-gray-900"
					htmlFor="reviewbird-gcr-prompt-toggle"
				>
					{ __( 'Show prompt on thank-you page', 'reviewbird' ) }
				</label>
				<button
					id="reviewbird-gcr-prompt-toggle"
					type="button"
					role="switch"
					aria-checked={ !! settings.promptEnabled }
					aria-labelledby="reviewbird-gcr-prompt-label"
					aria-busy={ pending === 'toggle' }
					disabled={ !! pending }
					onClick={ () => updateControl( 'toggle' ) }
					className={ `reviewbird-gcr-toggle ${
						settings.promptEnabled ? 'bg-indigo-600' : 'bg-gray-200'
					}` }
				>
					<span
						className={ `block rounded-full bg-white shadow transition-transform ${
							settings.promptEnabled
								? 'translate-x-5'
								: 'translate-x-0'
						}` }
					/>
				</button>
			</div>
			{ controlError && (
				<p role="alert" className="mt-3 text-sm text-red-700">
					{ controlError }
				</p>
			) }
			<p role="status" className="mt-3 text-sm">
				{ controlMessage }
			</p>
			<div className="mt-6 grid gap-6 md:grid-cols-2">
				<form
					onSubmit={ ( event ) => {
						event.preventDefault();
						save( prompt );
					} }
				>
					<fieldset disabled={ saving } className="space-y-4">
						{ fields.map( ( [ key, label, maxLength ] ) => {
							const props = {
								id: `reviewbird-gcr-${ key }`,
								name: key,
								value: prompt[ key ],
								maxLength,
								required: true,
								className: 'mt-1 block w-full',
								onChange: ( event ) => {
									setPrompt( {
										...prompt,
										[ key ]: event.target.value,
									} );
									setStatus( '' );
								},
							};
							return (
								<div key={ key }>
									<label
										className="block text-sm font-medium"
										htmlFor={ props.id }
									>
										{ label }
									</label>
									{ key === 'message' ? (
										<textarea { ...props } rows={ 5 } />
									) : (
										<input { ...props } type="text" />
									) }
								</div>
							);
						} ) }
						<div className="flex flex-wrap gap-2">
							<button
								className="button button-primary"
								type="submit"
							>
								{ saving
									? __( 'Saving…', 'reviewbird' )
									: __( 'Save', 'reviewbird' ) }
							</button>
							<button
								className="button"
								type="button"
								onClick={ () => save( settings.defaults ) }
							>
								{ __( 'Reset to defaults', 'reviewbird' ) }
							</button>
						</div>
					</fieldset>
					<p role="status" className="mt-3 text-sm">
						{ status }
					</p>
				</form>
				<div>
					<h3 className="text-sm font-medium">
						{ __( 'Preview', 'reviewbird' ) }
					</h3>
					<div
						className="reviewbird-gcr"
						aria-label={ __( 'Prompt preview', 'reviewbird' ) }
					>
						<h3 className="reviewbird-gcr__heading">
							{ prompt.heading }
						</h3>
						<p className="reviewbird-gcr__message">
							{ prompt.message }
						</p>
						<div className="reviewbird-gcr__actions">
							<button type="button" aria-disabled="true">
								{ prompt.yes_label }
							</button>
							<button type="button" aria-disabled="true">
								{ prompt.no_label }
							</button>
						</div>
					</div>
					<p className="text-sm text-gray-600">
						{ __(
							'The preview does not open Google. Google controls the text in its own window.',
							'reviewbird'
						) }
					</p>
				</div>
			</div>
		</section>
	);
}
