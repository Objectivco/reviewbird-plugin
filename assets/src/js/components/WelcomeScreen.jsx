import { createInterpolateElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import reviewbirdLogo from '../../images/logo-dark.svg';

function ArrowRightIcon() {
	return (
		<svg
			className="reviewbird-arrow-icon"
			fill="none"
			viewBox="0 0 24 24"
			stroke="currentColor"
			aria-hidden="true"
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

export default function WelcomeScreen( { registerUrl, dashboardUrl } ) {
	const setupSteps = [
		{
			title: __( 'Create your free account', 'reviewbird' ),
			description: __( 'takes about a minute', 'reviewbird' ),
		},
		{
			title: __( 'Connect your WooCommerce site', 'reviewbird' ),
			description: __( 'one secure click', 'reviewbird' ),
		},
		{
			title: __( 'Turn on automatic review requests', 'reviewbird' ),
			description: __( 'you set the timing and content', 'reviewbird' ),
		},
	];

	return (
		<div className="reviewbird-admin-shell reviewbird-sans">
			<header className="reviewbird-brand-bar">
				<img
					className="reviewbird-brand-logo"
					src={ reviewbirdLogo }
					alt="Reviewbird"
					width="308"
					height="39"
				/>

				<p className="reviewbird-header-account">
					<span>
						{ __( 'Already have an account?', 'reviewbird' ) }
					</span>{ ' ' }
					<a
						href={ dashboardUrl }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __( 'Sign in', 'reviewbird' ) }
					</a>
				</p>
			</header>

			<div className="reviewbird-admin-main">
				<section
					className="reviewbird-signup-hero"
					aria-labelledby="reviewbird-start-title"
				>
					<div className="reviewbird-signup-grid">
						<div className="reviewbird-signup-copy">
							<p className="reviewbird-start-kicker">
								{ __( 'Getting started', 'reviewbird' ) }
							</p>

							<h1
								id="reviewbird-start-title"
								className="reviewbird-display-title reviewbird-start-title"
							>
								{ __(
									'Turn your orders into powerful social proof.',
									'reviewbird'
								) }
							</h1>

							<p className="reviewbird-start-lede">
								{ __(
									"You're three quick steps from your first five-star review!",
									'reviewbird'
								) }
							</p>

							<ol className="reviewbird-setup-steps">
								{ setupSteps.map( ( step, index ) => (
									<li key={ step.title }>
										<span>{ index + 1 }</span>
										<div>
											<strong className="reviewbird-setup-step-title">
												{ step.title }
											</strong>
											<small>{ step.description }</small>
										</div>
									</li>
								) ) }
							</ol>

							<a
								className="reviewbird-button-primary"
								href={ registerUrl }
								target="_blank"
								rel="noopener noreferrer"
							>
								<span>
									{ __(
										'Create free account',
										'reviewbird'
									) }
								</span>
								<ArrowRightIcon />
							</a>

							<ul
								className="reviewbird-risk-line"
								aria-label={ __(
									'Free plan details',
									'reviewbird'
								) }
							>
								<li>{ __( 'Free plan', 'reviewbird' ) }</li>
								<li>
									{ __(
										'No credit card required',
										'reviewbird'
									) }
								</li>
								<li>
									{ __(
										'25 review requests a month',
										'reviewbird'
									) }
								</li>
								<li>
									{ __(
										'Import your existing reviews',
										'reviewbird'
									) }
								</li>
								<li>
									{ __(
										'Send a bulk request to past customers',
										'reviewbird'
									) }
								</li>
							</ul>

							<p className="reviewbird-trust-line">
								<span
									className="reviewbird-trust-stars"
									aria-hidden="true"
								>
									★★★★★
								</span>
								<span>
									{ createInterpolateElement(
										__(
											'Built by the team behind CheckoutWC, trusted by <strong>8,000+ WooCommerce stores.</strong>',
											'reviewbird'
										),
										{ strong: <strong /> }
									) }
								</span>
							</p>
						</div>

						<div
							className="reviewbird-email-visual"
							role="img"
							aria-label={ __(
								'Example Reviewbird product review request email',
								'reviewbird'
							) }
						>
							<div
								className="reviewbird-email-window"
								aria-hidden="true"
							>
								<div className="reviewbird-email-window-header">
									<div className="reviewbird-email-sender">
										<span className="reviewbird-email-merchant-mark">
											★
										</span>
										<span className="reviewbird-email-sender-copy">
											<strong>
												{ __(
													'Reviewbird',
													'reviewbird'
												) }{ ' ' }
												<span className="reviewbird-email-sender-store">
													{ __(
														'for Aster & Oak',
														'reviewbird'
													) }
												</span>
											</strong>
											<small>
												{ __(
													'To: jamie@example.com',
													'reviewbird'
												) }
											</small>
										</span>
									</div>
									<span className="reviewbird-email-timing">
										{ __(
											'Sent 3 days after delivery',
											'reviewbird'
										) }
									</span>
								</div>

								<div className="reviewbird-email-canvas">
									<div className="reviewbird-email-card">
										<h2>
											{ __(
												'How would you rate this product?',
												'reviewbird'
											) }
										</h2>
										<p>
											{ __(
												'We would love it if you would share a bit about your experience.',
												'reviewbird'
											) }
										</p>

										<svg
											className="reviewbird-email-product-art"
											viewBox="0 0 180 130"
											aria-hidden="true"
										>
											<rect
												x="35"
												y="29"
												width="110"
												height="76"
												rx="14"
												fill="#d5dfda"
												transform="rotate(-6 90 67)"
											/>
											<rect
												x="40"
												y="25"
												width="108"
												height="76"
												rx="14"
												fill="#edf1ee"
												transform="rotate(5 94 63)"
											/>
											<path
												d="M51 47h85M50 61h86M49 75h87"
												stroke="#b9cbc2"
												strokeWidth="6"
												strokeLinecap="round"
											/>
											<path
												d="M48 92h91"
												stroke="#0f766e"
												strokeWidth="5"
												strokeLinecap="round"
											/>
										</svg>

										<strong>
											{ __(
												'Linen Throw Blanket — Sage',
												'reviewbird'
											) }
										</strong>

										<div className="reviewbird-email-stars">
											☆☆☆☆☆
										</div>
										<div className="reviewbird-email-star-labels">
											<span>
												{ __( 'Poor', 'reviewbird' ) }
											</span>
											<span>
												{ __( 'Great', 'reviewbird' ) }
											</span>
										</div>

										<div className="reviewbird-email-footer">
											{ __(
												'You received this email because you made a purchase from Aster & Oak.',
												'reviewbird'
											) }
											<br />
											<u>
												{ __(
													'Unsubscribe from review emails',
													'reviewbird'
												) }
											</u>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</section>
			</div>
		</div>
	);
}
