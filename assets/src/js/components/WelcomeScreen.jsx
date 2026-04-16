import { __ } from '@wordpress/i18n';

const SETUP_STEPS = [
	{
		step: __( 'Step 1', 'reviewbird' ),
		title: __( 'Create your Reviewbird account', 'reviewbird' ),
		description: __(
			'Start with a free account so you can connect your WooCommerce store and configure your review collection flow.',
			'reviewbird'
		),
	},
	{
		step: __( 'Step 2', 'reviewbird' ),
		title: __( 'Connect WooCommerce in a couple clicks', 'reviewbird' ),
		description: __(
			'Authorize Reviewbird to securely read your store data, import products, and prepare your review widget.',
			'reviewbird'
		),
	},
	{
		step: __( 'Step 3', 'reviewbird' ),
		title: __( 'Launch beautifully branded reviews', 'reviewbird' ),
		description: __(
			'Turn on your widget, tune the experience to your brand, and start collecting customer feedback that converts.',
			'reviewbird'
		),
	},
];

const HIGHLIGHTS = [
	{
		title: __( 'Beautifully designed widgets', 'reviewbird' ),
		description: __(
			'Showcase reviews in a polished display that feels native to your storefront instead of bolted on.',
			'reviewbird'
		),
	},
	{
		title: __( 'Review collection that feels delightful', 'reviewbird' ),
		description: __(
			'Guide customers through a simple, modern review flow with photos, videos, and smart prompts.',
			'reviewbird'
		),
	},
	{
		title: __( 'SEO and AI-ready markup built in', 'reviewbird' ),
		description: __(
			'Publish structured review content that helps search engines and LLMs understand your products.',
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

function ExternalLinkIcon() {
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
				d="M14 5h5m0 0v5m0-5-9 9"
			/>
			<path
				strokeLinecap="round"
				strokeLinejoin="round"
				strokeWidth="1.8"
				d="M19 14v3a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h3"
			/>
		</svg>
	);
}

function SparkIcon() {
	return (
		<svg
			className="h-5 w-5"
			fill="none"
			viewBox="0 0 24 24"
			stroke="currentColor"
		>
			<path
				strokeLinecap="round"
				strokeLinejoin="round"
				strokeWidth="1.7"
				d="m12 3 1.7 4.6L18.5 9l-4.8 1.4L12 15l-1.7-4.6L5.5 9l4.8-1.4L12 3Z"
			/>
			<path
				strokeLinecap="round"
				strokeLinejoin="round"
				strokeWidth="1.7"
				d="m18.5 15 1 2.7L22 18.5l-2.5.8-1 2.7-1-2.7-2.5-.8 2.5-.8 1-2.7Z"
			/>
		</svg>
	);
}

function ShieldIcon() {
	return (
		<svg
			className="h-5 w-5"
			fill="none"
			viewBox="0 0 24 24"
			stroke="currentColor"
		>
			<path
				strokeLinecap="round"
				strokeLinejoin="round"
				strokeWidth="1.7"
				d="M12 3c2.4 2 5.2 3 8 3v6c0 5-3.3 8.3-8 9-4.7-.7-8-4-8-9V6c2.8 0 5.6-1 8-3Z"
			/>
			<path
				strokeLinecap="round"
				strokeLinejoin="round"
				strokeWidth="1.7"
				d="m9.5 12 1.7 1.7 3.3-3.7"
			/>
		</svg>
	);
}

function GlobeIcon() {
	return (
		<svg
			className="h-5 w-5"
			fill="none"
			viewBox="0 0 24 24"
			stroke="currentColor"
		>
			<circle cx="12" cy="12" r="9" strokeWidth="1.7" />
			<path
				strokeLinecap="round"
				strokeLinejoin="round"
				strokeWidth="1.7"
				d="M3.5 12h17M12 3c2.8 2.7 4.2 5.7 4.2 9S14.8 18.3 12 21c-2.8-2.7-4.2-5.7-4.2-9S9.2 5.7 12 3Z"
			/>
		</svg>
	);
}

function WidgetIcon() {
	return (
		<svg
			className="h-5 w-5"
			fill="none"
			viewBox="0 0 24 24"
			stroke="currentColor"
		>
			<rect x="4" y="5" width="16" height="14" rx="3" strokeWidth="1.7" />
			<path
				strokeLinecap="round"
				strokeLinejoin="round"
				strokeWidth="1.7"
				d="M8 10h8M8 14h5"
			/>
		</svg>
	);
}

function MockPreview() {
	return (
		<div className="reviewbird-dark-panel relative overflow-hidden">
			<div className="absolute -right-8 -top-8 h-28 w-28 rounded-full bg-[#f59f3a]/25 blur-2xl" />
			<div className="absolute -bottom-10 -left-6 h-32 w-32 rounded-full bg-[#f8d59b]/10 blur-3xl" />

			<div className="relative z-10 space-y-6">
				<div className="flex items-start justify-between gap-4">
					<div>
						<p className="reviewbird-eyebrow text-[#f8d59b]">
							{ __( 'Review collection preview', 'reviewbird' ) }
						</p>
						<h3 className="mt-2 text-2xl font-semibold text-white">
							{ __( 'Delightful by default', 'reviewbird' ) }
						</h3>
					</div>
					<div className="rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-[#f8d59b]">
						{ __( '5-minute setup', 'reviewbird' ) }
					</div>
				</div>

				<div className="rounded-[26px] bg-white/95 p-5 shadow-[0_20px_60px_rgba(15,23,42,0.18)]">
					<div className="flex items-center justify-between gap-4">
						<div>
							<p className="text-sm font-semibold text-slate-900">
								{ __( 'Review widget', 'reviewbird' ) }
							</p>
							<p className="mt-1 text-xs uppercase tracking-[0.2em] text-[#c46d30]">
								{ __( 'Brand matched', 'reviewbird' ) }
							</p>
						</div>
						<div className="rounded-full bg-[#fff3e6] px-3 py-1 text-xs font-semibold text-[#a8551d]">
							{ __( 'Live', 'reviewbird' ) }
						</div>
					</div>

					<div className="mt-5 rounded-[22px] bg-[#fff8ef] p-4">
						<div className="flex items-center justify-between">
							<div>
								<p className="text-base font-semibold text-slate-900">
									{ __(
										'How would you rate this product?',
										'reviewbird'
									) }
								</p>
								<p className="mt-1 text-sm text-slate-500">
									{ __(
										'Fast, friendly, and built for real customers.',
										'reviewbird'
									) }
								</p>
							</div>
							<div className="text-right">
								<p className="text-lg font-semibold text-[#c46d30]">
									4.9
								</p>
								<p className="text-xs text-slate-500">
									{ __( 'average rating', 'reviewbird' ) }
								</p>
							</div>
						</div>

						<div className="mt-4 flex gap-2 text-[#ef8f35]">
							<span>★</span>
							<span>★</span>
							<span>★</span>
							<span>★</span>
							<span>★</span>
						</div>

						<div className="mt-5 grid gap-3 sm:grid-cols-2">
							<div className="rounded-2xl bg-white p-3 shadow-sm">
								<p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
									{ __( 'Review Guard', 'reviewbird' ) }
								</p>
								<p className="mt-2 text-sm text-slate-600">
									{ __(
										'AI moderation keeps spam and low-quality submissions out of your queue.',
										'reviewbird'
									) }
								</p>
							</div>
							<div className="rounded-2xl bg-white p-3 shadow-sm">
								<p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
									{ __( 'Rich snippets', 'reviewbird' ) }
								</p>
								<p className="mt-2 text-sm text-slate-600">
									{ __(
										'Schema-ready review content helps your products stand out in search.',
										'reviewbird'
									) }
								</p>
							</div>
						</div>
					</div>
				</div>

				<div className="grid gap-3 sm:grid-cols-3">
					<div className="reviewbird-stat-tile">
						<SparkIcon />
						<div>
							<p className="reviewbird-stat-value">70%+</p>
							<p className="reviewbird-stat-label">
								{ __(
									'more reviews with requests',
									'reviewbird'
								) }
							</p>
						</div>
					</div>
					<div className="reviewbird-stat-tile">
						<ShieldIcon />
						<div>
							<p className="reviewbird-stat-value">
								{ __( 'AI', 'reviewbird' ) }
							</p>
							<p className="reviewbird-stat-label">
								{ __(
									'moderation and complaint triage',
									'reviewbird'
								) }
							</p>
						</div>
					</div>
					<div className="reviewbird-stat-tile">
						<GlobeIcon />
						<div>
							<p className="reviewbird-stat-value">SEO</p>
							<p className="reviewbird-stat-label">
								{ __(
									'optimized review content',
									'reviewbird'
								) }
							</p>
						</div>
					</div>
				</div>
			</div>
		</div>
	);
}

function StepCard( { step, title, description } ) {
	return (
		<div className="reviewbird-step-card">
			<p className="reviewbird-eyebrow">{ step }</p>
			<h3 className="mt-3 text-lg font-semibold text-slate-900">
				{ title }
			</h3>
			<p className="mt-3 text-sm leading-6 text-slate-600">
				{ description }
			</p>
		</div>
	);
}

function HighlightCard( { title, description, icon } ) {
	return (
		<div className="reviewbird-feature-card">
			<div className="reviewbird-feature-icon">{ icon }</div>
			<h3 className="mt-5 text-lg font-semibold text-slate-900">
				{ title }
			</h3>
			<p className="mt-3 text-sm leading-6 text-slate-600">
				{ description }
			</p>
		</div>
	);
}

export default function WelcomeScreen( {
	registerUrl,
	appHomeUrl,
	marketingUrl,
} ) {
	return (
		<div className="reviewbird-admin-shell reviewbird-sans relative overflow-hidden">
			<div className="reviewbird-background-orb reviewbird-background-orb-left" />
			<div className="reviewbird-background-orb reviewbird-background-orb-right" />

			<div className="relative z-10 grid gap-10 lg:grid-cols-[1.1fr_0.9fr]">
				<div className="space-y-8">
					<div className="space-y-6">
						<div className="reviewbird-pill">
							<SparkIcon />
							<span>
								{ __(
									'Welcome to Reviewbird for WooCommerce',
									'reviewbird'
								) }
							</span>
						</div>

						<div className="space-y-5">
							<h1 className="reviewbird-display-title max-w-3xl text-[clamp(2.6rem,5vw,4.6rem)] leading-[0.95] text-slate-950">
								{ __(
									'Collect reviews that look as good as your products.',
									'reviewbird'
								) }
							</h1>

							<p className="max-w-2xl text-lg leading-8 text-slate-600">
								{ __(
									'Reviewbird helps WooCommerce merchants collect, moderate, and showcase product reviews in a way that feels polished, trustworthy, and conversion-friendly from day one.',
									'reviewbird'
								) }
							</p>
						</div>

						<div className="flex flex-wrap gap-3">
							<span className="reviewbird-chip">
								{ __( '7-day free trial', 'reviewbird' ) }
							</span>
							<span className="reviewbird-chip">
								{ __( 'Connect in minutes', 'reviewbird' ) }
							</span>
							<span className="reviewbird-chip">
								{ __( 'Beautiful widgets', 'reviewbird' ) }
							</span>
						</div>

						<div className="flex flex-wrap gap-4">
							<a
								className="reviewbird-button-primary"
								href={ registerUrl }
								target="_blank"
								rel="noopener noreferrer"
							>
								<span>
									{ __(
										'Create Your Account',
										'reviewbird'
									) }
								</span>
								<ArrowRightIcon />
							</a>

							<a
								className="reviewbird-button-secondary"
								href={ marketingUrl }
								target="_blank"
								rel="noopener noreferrer"
							>
								<span>
									{ __(
										'See How Reviewbird Works',
										'reviewbird'
									) }
								</span>
								<ExternalLinkIcon />
							</a>
						</div>

						<p className="max-w-2xl text-sm leading-6 text-slate-500">
							{ __( 'Already have an account?', 'reviewbird' ) }{ ' ' }
							<a
								className="font-semibold text-slate-900 underline decoration-[#ef8f35] underline-offset-4"
								href={ appHomeUrl }
								target="_blank"
								rel="noopener noreferrer"
							>
								{ __(
									'Open Reviewbird and connect this store',
									'reviewbird'
								) }
							</a>
							.
						</p>
					</div>

					<div className="grid gap-4 md:grid-cols-3">
						{ SETUP_STEPS.map( ( item ) => (
							<StepCard
								key={ item.title }
								step={ item.step }
								title={ item.title }
								description={ item.description }
							/>
						) ) }
					</div>
				</div>

				<MockPreview />
			</div>

			<div className="relative z-10 mt-10 grid gap-4 md:grid-cols-3">
				<HighlightCard
					title={ HIGHLIGHTS[ 0 ].title }
					description={ HIGHLIGHTS[ 0 ].description }
					icon={ <WidgetIcon /> }
				/>
				<HighlightCard
					title={ HIGHLIGHTS[ 1 ].title }
					description={ HIGHLIGHTS[ 1 ].description }
					icon={ <SparkIcon /> }
				/>
				<HighlightCard
					title={ HIGHLIGHTS[ 2 ].title }
					description={ HIGHLIGHTS[ 2 ].description }
					icon={ <GlobeIcon /> }
				/>
			</div>
		</div>
	);
}
