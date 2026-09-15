import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const fields = [
	[ 'heading', __( 'Heading', 'reviewbird' ), 160 ],
	[ 'message', __( 'Message', 'reviewbird' ), 2000 ],
	[ 'yes_label', __( 'Yes button label', 'reviewbird' ), 80 ],
	[ 'no_label', __( 'No button label', 'reviewbird' ), 80 ],
];

export default function GoogleCustomerReviews() {
	const settings = window.reviewbirdAdmin.googleCustomerReviews;
	const [ prompt, setPrompt ] = useState( settings.prompt );
	const [ saving, setSaving ] = useState( false );
	const [ status, setStatus ] = useState( '' );

	async function save( values ) {
		setSaving( true );
		setStatus( '' );
		const body = new FormData();
		body.append( 'action', 'reviewbird_update_gcr_prompt' );
		body.append( 'nonce', window.reviewbirdAdmin.nonce );
		Object.entries( values ).forEach( ( [ key, value ] ) =>
			body.append( key, value )
		);
		try {
			const response = await fetch( window.reviewbirdAdmin.ajaxUrl, {
				method: 'POST',
				body,
			} );
			const result = await response.json();
			if ( ! response.ok || ! result.success ) {
				throw new Error(
					typeof result.data === 'string' ? result.data : ''
				);
			}
			setPrompt( result.data.prompt );
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
			<h2
				id="reviewbird-gcr-settings-title"
				className="text-lg font-semibold text-gray-900"
			>
				{ __( 'Google Customer Reviews', 'reviewbird' ) }
			</h2>
			<p className="mt-2 text-sm text-gray-600">
				{ settings.enabled
					? __(
							'Enabled. Customers can open the Google survey prompt from the order confirmation page.',
							'reviewbird'
					  )
					: __(
							'Disabled or awaiting a connection update. Enable this integration in Reviewbird with a Plus plan or higher.',
							'reviewbird'
					  ) }
			</p>
			<p className="mt-2 text-sm text-gray-600">
				{ __(
					'Set the Merchant ID and delivery days in Reviewbird. Connection updates can take up to five minutes.',
					'reviewbird'
				) }
			</p>
			<a
				className="mt-2 inline-block text-blue-600"
				href={ settings.integrationsUrl }
				target="_blank"
				rel="noopener noreferrer"
			>
				{ __( 'Manage integration in Reviewbird', 'reviewbird' ) }
			</a>
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
