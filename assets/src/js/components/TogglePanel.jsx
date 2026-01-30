import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const TOGGLE_BUTTON_STYLE = {
	height: '24px',
	width: '44px',
	padding: '0',
	margin: '0',
	verticalAlign: 'middle',
	boxSizing: 'border-box',
	lineHeight: '1'
};

const TOGGLE_KNOB_STYLE = {
	height: '20px',
	width: '20px',
	display: 'block',
	margin: '0',
	boxSizing: 'border-box'
};

const STATUS_LABEL_STYLE = {
	margin: '8px 0 0 0',
	fontSize: '12px',
	lineHeight: '1.4'
};

function InfoIcon() {
	return (
		<svg className="w-5 h-5 text-blue-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
			<path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
		</svg>
	);
}

function ExternalLinkIcon() {
	return (
		<svg className="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
			<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
		</svg>
	);
}

function InfoBox({ title, items }) {
	return (
		<div className="mt-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
			<div className="flex">
				<InfoIcon />
				<div className="ml-3 text-sm text-blue-800">
					<p className="font-medium">{title}</p>
					<ul className="mt-2 space-y-1">
						{items.map((item, index) => (
							<li key={index}>• {item}</li>
						))}
					</ul>
				</div>
			</div>
		</div>
	);
}

function ExternalLink({ href, text, showSeparator }) {
	return (
		<span>
			{showSeparator && <span className="mx-2 text-gray-400">•</span>}
			<a
				href={href}
				target="_blank"
				rel="noopener noreferrer"
				className="inline-flex items-center text-sm text-indigo-600 hover:text-indigo-700"
			>
				{text}
				<ExternalLinkIcon />
			</a>
		</span>
	);
}

function LinksList({ links }) {
	if (!links || links.length === 0) {
		return null;
	}

	return (
		<div className="mt-3 space-y-2">
			{links.map((link, index) => (
				<ExternalLink
					key={index}
					href={link.href}
					text={link.text}
					showSeparator={index > 0}
				/>
			))}
		</div>
	);
}

function ToggleSwitch({ enabled, isSaving, onToggle }) {
	const buttonClasses = [
		'relative inline-flex flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent',
		'transition-colors duration-200 ease-in-out',
		'focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2',
		enabled ? 'bg-indigo-600' : 'bg-gray-200',
		isSaving ? 'opacity-50 cursor-not-allowed' : ''
	].join(' ');

	const knobClasses = [
		'pointer-events-none inline-block transform rounded-full bg-white shadow ring-0',
		'transition duration-200 ease-in-out',
		enabled ? 'translate-x-5' : 'translate-x-0'
	].join(' ');

	const statusText = enabled
		? __('Enabled', 'reviewbird')
		: __('Disabled', 'reviewbird');

	return (
		<div className="ml-6">
			<button
				type="button"
				onClick={onToggle}
				disabled={isSaving}
				className={buttonClasses}
				style={TOGGLE_BUTTON_STYLE}
				role="switch"
				aria-checked={enabled}
			>
				<span className={knobClasses} style={TOGGLE_KNOB_STYLE} />
			</button>
			<p className="mt-2 text-xs text-gray-500 text-right" style={STATUS_LABEL_STYLE}>
				{statusText}
			</p>
		</div>
	);
}

function EnabledContent({ enabledText, links }) {
	return (
		<div className="mt-4 pt-4 border-t border-gray-200">
			<p className="text-sm text-gray-600">{enabledText}</p>
			<LinksList links={links} />
		</div>
	);
}

export default function TogglePanel({
	title,
	description,
	enabled,
	onToggle,
	enabledText,
	infoBox,
	links,
	children
}) {
	const [isSaving, setIsSaving] = useState(false);

	async function handleToggle() {
		setIsSaving(true);
		await onToggle();
		setIsSaving(false);
	}

	const showEnabledContent = enabled && enabledText;

	return (
		<div className="bg-white rounded-lg shadow p-6">
			<div className="flex items-start justify-between">
				<div className="flex-1">
					<h2 className="text-lg font-semibold text-gray-900">{title}</h2>
					<p className="mt-1 text-sm text-gray-600">{description}</p>
					{infoBox && <InfoBox title={infoBox.title} items={infoBox.items} />}
				</div>
				<ToggleSwitch enabled={enabled} isSaving={isSaving} onToggle={handleToggle} />
			</div>
			{showEnabledContent && <EnabledContent enabledText={enabledText} links={links} />}
			{enabled && children}
		</div>
	);
}
