import { __ } from '@wordpress/i18n';
import { ExternalLinkIcon } from './ConnectionHealth.jsx';

export function ToggleSwitch( { enabled, isSaving, onToggle, label } ) {
	return (
		<button
			type="button"
			className="reviewbird-settings-switch"
			role="switch"
			aria-label={ label }
			aria-checked={ enabled }
			aria-busy={ isSaving }
			disabled={ isSaving }
			onClick={ onToggle }
		>
			<span aria-hidden="true" />
		</button>
	);
}

export default function TogglePanel( {
	id,
	title,
	description,
	enabled,
	onToggle,
	isSaving,
	error,
	links,
	children,
} ) {
	return (
		<section className="reviewbird-toggle-panel" aria-labelledby={ id }>
			<div className="reviewbird-toggle-panel__heading">
				<h2 id={ id }>{ title }</h2>
				<ToggleSwitch
					enabled={ enabled }
					isSaving={ isSaving }
					onToggle={ onToggle }
					label={ title }
				/>
			</div>
			<p className="reviewbird-toggle-panel__description">
				{ description }
			</p>
			{ error && (
				<p className="reviewbird-settings-error" role="alert">
					{ error }
				</p>
			) }
			<span className="screen-reader-text" role="status">
				{ isSaving ? __( 'Saving…', 'reviewbird' ) : '' }
			</span>
			{ enabled && children }
			{ enabled && links?.length > 0 && (
				<div className="reviewbird-toggle-panel__links">
					{ links.map( ( link ) => (
						<a
							key={ link.href }
							href={ link.href }
							target="_blank"
							rel="noopener noreferrer"
							className="reviewbird-settings-link"
						>
							{ link.text }
							<ExternalLinkIcon />
						</a>
					) ) }
				</div>
			) }
		</section>
	);
}
