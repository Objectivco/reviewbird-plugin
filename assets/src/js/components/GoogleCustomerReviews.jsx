import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { ExternalLinkIcon } from './ConnectionHealth.jsx';

const fields = [
	[ 'heading', __( 'Heading', 'reviewbird' ), 160 ],
	[ 'message', __( 'Message', 'reviewbird' ), 2000 ],
	[ 'yes_label', __( 'Yes button', 'reviewbird' ), 80 ],
	[ 'no_label', __( 'No button', 'reviewbird' ), 80 ],
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
	const [ savedPrompt, setSavedPrompt ] = useState( settings.prompt );
	const [ saving, setSaving ] = useState( false );
	const [ status, setStatus ] = useState( '' );
	const [ saveError, setSaveError ] = useState( false );
	const [ pending, setPending ] = useState( '' );
	const [ controlMessage, setControlMessage ] = useState( '' );
	const [ controlError, setControlError ] = useState( '' );
	const dirty = fields.some(
		( [ key ] ) => prompt[ key ] !== savedPrompt[ key ]
	);
	const integrationState = [ 'enabled', 'disabled' ].includes(
		settings.status
	)
		? settings.status
		: 'unknown';
	const integrationLabel = {
		enabled: __( 'Integration enabled', 'reviewbird' ),
		disabled: __( 'Integration disabled', 'reviewbird' ),
		unknown: __( 'Status unknown', 'reviewbird' ),
	}[ integrationState ];
	const integrationEnabled = integrationState === 'enabled';

	async function updateControl( action ) {
		if (
			pending ||
			saving ||
			( action === 'toggle' && ! integrationEnabled )
		) {
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
				const fresh = data.googleCustomerReviews;
				setSettings( fresh );
				setPrompt( ( current ) =>
					fields.every(
						( [ key ] ) => current[ key ] === savedPrompt[ key ]
					)
						? fresh.prompt
						: current
				);
				setSavedPrompt( fresh.prompt );
				setControlMessage( __( 'Status updated.', 'reviewbird' ) );
			} else {
				const data = await request( 'reviewbird_update_setting', {
					setting: 'enable_gcr_prompt',
					value: settings.promptEnabled ? '0' : '1',
				} );
				setSettings( ( current ) => ( {
					...current,
					promptEnabled: data.value,
				} ) );
				setControlMessage(
					__( 'Display setting saved.', 'reviewbird' )
				);
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

	async function save() {
		if ( ! dirty || saving || pending ) {
			return;
		}
		setSaving( true );
		setStatus( '' );
		setSaveError( false );
		try {
			const data = await request(
				'reviewbird_update_gcr_prompt',
				prompt
			);
			setPrompt( data.prompt );
			setSavedPrompt( data.prompt );
			setStatus( __( 'Text saved.', 'reviewbird' ) );
		} catch ( error ) {
			setSaveError( true );
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
			className="reviewbird-gcr-settings"
			aria-labelledby="reviewbird-gcr-settings-title"
		>
			<header className="reviewbird-gcr-settings__header">
				<h2 id="reviewbird-gcr-settings-title">
					{ __( 'Google Customer Reviews', 'reviewbird' ) }
				</h2>
				<span
					id="reviewbird-gcr-integration-status"
					className="reviewbird-gcr-settings__status"
					data-state={ integrationState }
				>
					<span aria-hidden="true" />
					{ integrationLabel }
				</span>
			</header>

			<div className="reviewbird-gcr-settings__visibility">
				<div className="reviewbird-gcr-settings__choice">
					<input
						id="reviewbird-gcr-prompt-toggle"
						type="checkbox"
						checked={
							integrationEnabled && !! settings.promptEnabled
						}
						aria-describedby={
							integrationEnabled
								? undefined
								: 'reviewbird-gcr-visibility-description'
						}
						aria-busy={ pending === 'toggle' }
						disabled={
							! integrationEnabled || !! pending || saving
						}
						onChange={ () => updateControl( 'toggle' ) }
					/>
					<div>
						<label htmlFor="reviewbird-gcr-prompt-toggle">
							{ __(
								'Enable widget on the thank you page',
								'reviewbird'
							) }
						</label>
						{ ! integrationEnabled && (
							<p id="reviewbird-gcr-visibility-description">
								{ integrationState === 'disabled'
									? __(
											'Enable Google Customer Reviews in Reviewbird, then refresh.',
											'reviewbird'
									  )
									: __(
											'Refresh to check your connection.',
											'reviewbird'
									  ) }
							</p>
						) }
					</div>
				</div>
				<div className="reviewbird-gcr-toolbar">
					<a
						href={ settings.integrationsUrl }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __( 'Configure integration', 'reviewbird' ) }
						<ExternalLinkIcon />
					</a>
					<button
						type="button"
						disabled={ !! pending || saving }
						onClick={ () => updateControl( 'refresh' ) }
						aria-label={ __(
							'Refresh integration status',
							'reviewbird'
						) }
					>
						<svg
							width="16"
							height="16"
							viewBox="0 0 24 24"
							fill="none"
							stroke="currentColor"
							strokeWidth="1.75"
							aria-hidden="true"
						>
							<path
								strokeLinecap="round"
								strokeLinejoin="round"
								d="M20 7v5h-5M4 17v-5h5M6.1 7a7 7 0 0 1 11.55-1.4L20 8M4 16l2.35 2.4A7 7 0 0 0 17.9 17"
							/>
						</svg>
						{ pending === 'refresh'
							? __( 'Refreshing…', 'reviewbird' )
							: __( 'Refresh', 'reviewbird' ) }
					</button>
				</div>
			</div>
			{ controlError && (
				<p className="reviewbird-gcr-settings__notice" role="alert">
					{ controlError }
				</p>
			) }
			{ controlMessage && (
				<p className="screen-reader-text" role="status">
					{ controlMessage }
				</p>
			) }

			<form
				onSubmit={ ( event ) => {
					event.preventDefault();
					save();
				} }
			>
				<div className="reviewbird-gcr-settings__editor">
					<fieldset disabled={ saving }>
						<legend>{ __( 'Prompt text', 'reviewbird' ) }</legend>
						<div className="reviewbird-gcr-settings__fields">
							{ fields.map( ( [ key, label, maxLength ] ) => {
								const props = {
									id: `reviewbird-gcr-${ key }`,
									name: key,
									value: prompt[ key ],
									maxLength,
									required: true,
									onChange: ( event ) => {
										setPrompt( {
											...prompt,
											[ key ]: event.target.value,
										} );
										setStatus( '' );
										setSaveError( false );
									},
								};
								return (
									<div
										key={ key }
										className={
											key === 'heading' ||
											key === 'message'
												? 'reviewbird-gcr-settings__wide-field'
												: ''
										}
									>
										<label htmlFor={ props.id }>
											{ label }
										</label>
										{ key === 'message' ? (
											<textarea { ...props } rows={ 4 } />
										) : (
											<input { ...props } type="text" />
										) }
									</div>
								);
							} ) }
						</div>
					</fieldset>
					<aside
						className="reviewbird-gcr-settings__preview"
						aria-label={ __( 'Customer preview', 'reviewbird' ) }
					>
						<div className="reviewbird-gcr-settings__preview-heading">
							<h3>{ __( 'Customer preview', 'reviewbird' ) }</h3>
						</div>
						<div className="reviewbird-gcr-settings__preview-surface">
							<div
								className="reviewbird-gcr"
								aria-label={ __(
									'Prompt preview',
									'reviewbird'
								) }
							>
								<h3 className="reviewbird-gcr__heading">
									{ prompt.heading }
								</h3>
								<p className="reviewbird-gcr__message">
									{ prompt.message }
								</p>
								<div className="reviewbird-gcr__actions">
									<button
										type="button"
										aria-disabled="true"
										tabIndex={ -1 }
									>
										{ prompt.yes_label }
									</button>
									<button
										type="button"
										aria-disabled="true"
										tabIndex={ -1 }
									>
										{ prompt.no_label }
									</button>
								</div>
							</div>
						</div>
					</aside>
				</div>
				<footer className="reviewbird-gcr-settings__footer">
					<p
						role={ saveError ? 'alert' : 'status' }
						className={
							saveError ? 'reviewbird-gcr-settings__error' : ''
						}
					>
						{ status }
					</p>
					<div>
						<button
							type="button"
							className="reviewbird-gcr-settings__reset"
							disabled={
								saving ||
								! fields.some(
									( [ key ] ) =>
										prompt[ key ] !==
										settings.defaults[ key ]
								)
							}
							onClick={ () => {
								setPrompt( { ...settings.defaults } );
								setStatus( '' );
								setSaveError( false );
							} }
						>
							{ __( 'Reset to defaults', 'reviewbird' ) }
						</button>
						<button
							type="submit"
							className="reviewbird-gcr-settings__save"
							disabled={ ! dirty || saving || !! pending }
						>
							{ saving
								? __( 'Saving…', 'reviewbird' )
								: __( 'Save text', 'reviewbird' ) }
						</button>
					</div>
				</footer>
			</form>
		</section>
	);
}
