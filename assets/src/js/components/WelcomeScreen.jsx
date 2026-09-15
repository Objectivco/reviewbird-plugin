import {
	useEffect,
	useState,
	createInterpolateElement,
} from '@wordpress/element';
import ConnectionHealth, {
	fetchHealthStatus,
	ExternalLinkIcon,
} from './ConnectionHealth.jsx';
import { __ } from '@wordpress/i18n';
import reviewbirdLogo from '../../images/logo-dark.svg';

export function getWelcomeState( data, preview ) {
	if ( [ 'new', 'setup', 'ready' ].includes( preview ) ) {
		return preview;
	}
	if ( ! data ) {
		return null;
	}
	if ( data.status === 'error' || ! data.store_id ) {
		return 'new';
	}
	if ( ! data.onboarding_completed || data.status === 'not_connected' ) {
		return 'setup';
	}
	return 'ready';
}

export default function WelcomeScreen( {
	registerUrl,
	dashboardUrl,
	settingsUrl,
} ) {
	const [ health, setHealth ] = useState( null );
	useEffect( () => {
		let controller;
		let disposed = false;
		async function check() {
			if ( document.hidden || controller ) {
				return;
			}
			controller = new AbortController();
			const timeout = setTimeout( () => controller?.abort(), 10000 );
			try {
				const { data } = await fetchHealthStatus( controller.signal );
				if ( ! disposed ) {
					setHealth( data );
				}
			} catch {
				if ( ! disposed ) {
					setHealth( { status: 'error' } );
				}
			} finally {
				clearTimeout( timeout );
				controller = null;
			}
		}
		check();
		window.addEventListener( 'focus', check );
		document.addEventListener( 'visibilitychange', check );
		return () => {
			disposed = true;
			controller?.abort();
			window.removeEventListener( 'focus', check );
			document.removeEventListener( 'visibilitychange', check );
		};
	}, [] );
	const preview = new URLSearchParams( window.location.search ).get(
		'reviewbird_preview'
	);
	const state = getWelcomeState( health, preview );
	const storePath =
		health?.store_id && health?.org_slug
			? `${ window.reviewbirdAdmin.apiUrl }/${ encodeURIComponent(
					health.org_slug
			  ) }/stores/${ health.store_id }`
			: null;
	const storeDashboardUrl =
		health?.dashboard_url ||
		( storePath ? `${ storePath }/dashboard` : dashboardUrl );
	const onboardingUrl =
		health?.onboarding_url ||
		( storePath ? `${ storePath }/onboarding` : dashboardUrl );
	const needsConnection =
		preview === 'setup' || health?.status === 'not_connected';
	const messages = {
		setup: {
			title: needsConnection
				? __( 'The plugin is installed!', 'reviewbird' )
				: __( 'Your store is connected to Reviewbird', 'reviewbird' ),
			description: needsConnection
				? __(
						"Now let's connect Reviewbird to your WooCommerce store.",
						'reviewbird'
				  )
				: __(
						'Continue in Reviewbird to customize your widget and review request emails.',
						'reviewbird'
				  ),
			label: __( 'Continue setup', 'reviewbird' ),
			href: onboardingUrl,
		},
		ready: {
			title: __( 'Your store is connected', 'reviewbird' ),
			description: __(
				'Manage your reviews, widgets, showcases and other settings in the dashboard.',
				'reviewbird'
			),
			label: __( 'Open dashboard', 'reviewbird' ),
			href: storeDashboardUrl,
		},
	};
	const message = messages[ state ];
	const benefits = [
		{
			icon: (
				<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3" />
			),
			title: __( 'Bring your reviews with you', 'reviewbird' ),
			description: __(
				'Import from WooCommerce, Judge.me, Yotpo, and many more.',
				'reviewbird'
			),
		},
		{
			icon: (
				<>
					<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3Z" />
					<circle cx="12" cy="13.5" r="3.5" />
				</>
			),
			title: __( 'Photo and video reviews', 'reviewbird' ),
			description: __(
				'Allow customers to upload photos and videos of your product in real life.',
				'reviewbird'
			),
		},
		{
			icon: (
				<>
					<path d="M12 3a9 9 0 1 0 0 18h1a2 2 0 0 0 1.6-3.2 1 1 0 0 1 .8-1.6H17a4 4 0 0 0 4-4A9.2 9.2 0 0 0 12 3Z" />
					<circle cx="7.5" cy="11" r="1" />
					<circle cx="10" cy="7" r="1" />
					<circle cx="15" cy="7" r="1" />
					<circle cx="17" cy="11" r="1" />
				</>
			),
			title: __( 'Match your branding', 'reviewbird' ),
			description: __(
				'Customize your emails, widgets, and showcases to match your brand.',
				'reviewbird'
			),
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

				{ state === 'new' && (
					<p className="reviewbird-header-account">
						<span>
							{ __( 'Already use Reviewbird?', 'reviewbird' ) }
						</span>{ ' ' }
						<a
							href={ dashboardUrl }
							target="_blank"
							rel="noopener noreferrer"
						>
							{ __( 'Sign in', 'reviewbird' ) }
							<ExternalLinkIcon />
						</a>
					</p>
				) }
			</header>

			<div className="reviewbird-admin-main">
				<section
					className="reviewbird-signup-hero"
					aria-labelledby={
						state ? 'reviewbird-start-title' : undefined
					}
					aria-busy={ ! state }
				>
					{ ! state && (
						<p role="status">
							{ __( 'Loading Reviewbird…', 'reviewbird' ) }
						</p>
					) }
					{ message && (
						<div
							className="reviewbird-store-status"
							aria-live="polite"
						>
							<h1
								id="reviewbird-start-title"
								className="reviewbird-display-title reviewbird-start-title"
							>
								{ message.title }
							</h1>
							{ message.description && (
								<p className="reviewbird-start-lede">
									{ message.description }
								</p>
							) }
							<div className="reviewbird-status-actions">
								{ message.href && (
									<a
										className="reviewbird-button-primary"
										href={ message.href }
										target="_blank"
										rel="noopener noreferrer"
									>
										{ message.label }
										<span className="sr-only">
											{ __(
												'(opens in a new tab)',
												'reviewbird'
											) }
										</span>
										<ExternalLinkIcon />
									</a>
								) }
								{ state === 'ready' && (
									<a
										className="reviewbird-button-secondary"
										href={ settingsUrl }
									>
										{ __(
											'Plugin settings',
											'reviewbird'
										) }
									</a>
								) }
							</div>
							{ state === 'ready' && (
								<div className="mt-8">
									<ConnectionHealth />
								</div>
							) }
						</div>
					) }
					{ state === 'new' && (
						<div className="reviewbird-signup-grid">
							<div className="reviewbird-signup-copy">
								<h1
									id="reviewbird-start-title"
									className="reviewbird-display-title reviewbird-start-title"
								>
									{ __(
										"Let's turn your WooCommerce product reviews up to 11.",
										'reviewbird'
									) }
								</h1>

								<p className="reviewbird-start-lede">
									{ __(
										'Automated review requests help you collect reviews automatically after each order. No busywork, just reliable review collection on autopilot.',
										'reviewbird'
									) }
								</p>

								<ul className="reviewbird-start-benefits">
									{ benefits.map( ( benefit ) => (
										<li key={ benefit.title }>
											<svg
												className="reviewbird-benefit-icon"
												viewBox="0 0 24 24"
												fill="none"
												stroke="currentColor"
												strokeWidth="1.8"
												strokeLinecap="round"
												strokeLinejoin="round"
												aria-hidden="true"
												focusable="false"
											>
												{ benefit.icon }
											</svg>
											<div>
												<strong>
													{ benefit.title }
												</strong>
												<small>
													{ benefit.description }
												</small>
											</div>
										</li>
									) ) }
								</ul>

								<a
									className="reviewbird-button-primary"
									href={ registerUrl }
									target="_blank"
									rel="noopener noreferrer"
								>
									<span>
										{ __(
											'Get started free',
											'reviewbird'
										) }
										<span className="sr-only">
											{ __(
												'(opens in a new tab)',
												'reviewbird'
											) }
										</span>
									</span>
									<ExternalLinkIcon />
								</a>
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
													{ createInterpolateElement(
														// translators: Keep the <store> tags around the example store name.
														__(
															'Reviewbird <store>for Aster & Oak</store>',
															'reviewbird'
														),
														{
															store: (
																<span className="reviewbird-email-sender-store" />
															),
														}
													) }
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
												'Review request',
												'reviewbird'
											) }
										</span>
									</div>

									<div className="reviewbird-email-canvas">
										<div className="reviewbird-email-card">
											<h2>
												{ __(
													'How is your new blanket?',
													'reviewbird'
												) }
											</h2>
											<p>
												{ __(
													'Share your experience to help other shoppers choose.',
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
													'Linen Throw Blanket, Sage',
													'reviewbird'
												) }
											</strong>

											<div className="reviewbird-email-stars">
												☆☆☆☆☆
											</div>
											<div className="reviewbird-email-star-labels">
												<span>
													{ __(
														'Poor',
														'reviewbird'
													) }
												</span>
												<span>
													{ __(
														'Great',
														'reviewbird'
													) }
												</span>
											</div>

											<div className="reviewbird-email-footer">
												{ __(
													'You received this review request after your purchase from Aster & Oak.',
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
					) }
				</section>
			</div>
		</div>
	);
}
