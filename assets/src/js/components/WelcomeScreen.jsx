import { __ } from '@wordpress/i18n';

const SETUP_STEPS = [
	{
		number: '1',
		title: __( 'Create your Reviewbird account', 'reviewbird' ),
		description: __(
			'Start with the free plan, then connect your WooCommerce store whenever you are ready.',
			'reviewbird'
		),
	},
	{
		number: '2',
		title: __( 'Authorize your WooCommerce store', 'reviewbird' ),
		description: __(
			'Connect Reviewbird so it can securely sync products, orders, and review activity.',
			'reviewbird'
		),
	},
	{
		number: '3',
		title: __( 'Turn on your review experience', 'reviewbird' ),
		description: __(
			'Enable the widget in WordPress and start collecting polished, conversion-friendly product reviews.',
			'reviewbird'
		),
	},
];

function ArrowRightIcon() {
	return (
		<svg
			className="h-4 w-4"
			fill="none"
			viewBox="0 0 24 24"
			stroke="currentColor"
		>
			<path
				strokeLinecap="round"
				strokeLinejoin="round"
				strokeWidth="1.8"
				d="M5 12h14m-5-5 5 5-5 5"
			/>
		</svg>
	);
}

function StepRow( { number, title, description } ) {
	return (
		<li className="reviewbird-step-card">
			<div className="reviewbird-step-chip">
				<span className="reviewbird-step-chip-label">
					{ __( 'Step', 'reviewbird' ) }
				</span>
				<span className="reviewbird-step-chip-number">{ number }</span>
			</div>
			<div>
				<h3 className="text-lg font-semibold text-slate-900">
					{ title }
				</h3>
				<p className="mt-2 text-sm leading-6 text-slate-600">
					{ description }
				</p>
			</div>
		</li>
	);
}

export default function WelcomeScreen( {
	registerUrl,
	dashboardUrl,
	settingsUrl,
	onboardingVideo,
} ) {
	return (
		<div className="reviewbird-admin-shell reviewbird-sans relative overflow-hidden">
			<div className="reviewbird-background-orb reviewbird-background-orb-left" />
			<div className="reviewbird-background-orb reviewbird-background-orb-right" />

			<div className="relative z-10 mb-8 max-w-3xl">
				<h1 className="reviewbird-display-title text-[clamp(2.1rem,4vw,3.3rem)] leading-[1.02] text-slate-950">
					{ __(
						'Welcome to Reviewbird for WooCommerce',
						'reviewbird'
					) }
				</h1>
			</div>

			<div className="relative z-10 grid gap-10 xl:grid-cols-[minmax(0,0.82fr)_minmax(0,1.18fr)] xl:items-start">
				<div className="space-y-6">
					<div className="space-y-4">
						<h2 className="reviewbird-display-title max-w-3xl text-[clamp(2.6rem,5vw,4.5rem)] leading-[0.97] text-slate-950">
							{ __(
								'Collect more reviews with less friction.',
								'reviewbird'
							) }
						</h2>

						<p className="max-w-xl text-lg leading-8 text-slate-600">
							{ __(
								'Reviewbird helps WooCommerce merchants collect, moderate, and showcase product reviews with a polished experience that feels native to their storefront.',
								'reviewbird'
							) }
						</p>
					</div>

					<div className="flex flex-wrap items-center gap-4">
						<a
							className="reviewbird-button-primary"
							href={ registerUrl }
							target="_blank"
							rel="noopener noreferrer"
						>
							<span>
								{ __( 'Get Started Free', 'reviewbird' ) }
							</span>
							<ArrowRightIcon />
						</a>
					</div>

					<p className="text-sm leading-6 text-slate-500">
						{ __(
							'Free plan available. Create your account first, then connect WooCommerce when you are ready.',
							'reviewbird'
						) }
					</p>

					<p className="text-sm leading-6 text-slate-500">
						{ __( 'Already have an account?', 'reviewbird' ) }{ ' ' }
						<a
							className="font-semibold text-slate-900 underline decoration-[#ef8f35] underline-offset-4"
							href={ dashboardUrl }
							target="_blank"
							rel="noopener noreferrer"
						>
							{ __( 'Connect your store', 'reviewbird' ) }
						</a>
						.
					</p>
				</div>

				<div className="reviewbird-video-shell">
					<div className="space-y-2">
						<p className="reviewbird-eyebrow">
							{ __( 'See It In Action', 'reviewbird' ) }
						</p>
						<h2 className="text-2xl font-semibold text-slate-950">
							{ __(
								'A quick look at the Reviewbird widget',
								'reviewbird'
							) }
						</h2>
						<p className="text-sm leading-6 text-slate-600">
							{ __(
								"Leaving a review shouldn't feel applying for a loan. Our widget makes review submission easy and painless.",
								'reviewbird'
							) }
						</p>
					</div>

					<div className="reviewbird-video-frame mt-5">
						<div className="reviewbird-video-embed">
							<iframe
								src={ onboardingVideo }
								title={ __(
									'Reviewbird onboarding video',
									'reviewbird'
								) }
								className="reviewbird-video-iframe"
								allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;"
								allowFullScreen
								loading="lazy"
							/>
						</div>
					</div>
				</div>
			</div>

			<div className="reviewbird-steps-shell relative z-10 mt-12">
				<div className="max-w-2xl">
					<p className="reviewbird-eyebrow">
						{ __( 'Setup', 'reviewbird' ) }
					</p>
					<h2 className="mt-3 text-3xl font-semibold text-slate-950">
						{ __( 'Go live in three quick steps.', 'reviewbird' ) }
					</h2>
					<p className="mt-3 max-w-xl text-sm leading-6 text-slate-600">
						{ __(
							'Create your account, connect WooCommerce, then',
							'reviewbird'
						) }{ ' ' }
						<a
							className="font-semibold text-slate-900 underline decoration-[#ef8f35] underline-offset-4"
							href={ settingsUrl }
						>
							{ __(
								'enable the widget in settings',
								'reviewbird'
							) }
						</a>
						.
					</p>
				</div>

				<ol className="reviewbird-steps-grid mt-8">
					{ SETUP_STEPS.map( ( step ) => (
						<StepRow
							key={ step.number }
							number={ step.number }
							title={ step.title }
							description={ step.description }
						/>
					) ) }
				</ol>
			</div>
		</div>
	);
}
