import '../scss/google-customer-reviews.scss';

// Share the load and render state if more than one checkout hook adds the card.
const state = window.reviewbirdGcrState || { loading: null, rendered: false };
window.reviewbirdGcrState = state;

function loadGoogle() {
	if ( state.loading ) {
		return state.loading;
	}
	state.loading = new Promise( ( resolve, reject ) => {
		const script = document.createElement( 'script' );
		const timer = setTimeout( fail, 15000 );
		function fail() {
			clearTimeout( timer );
			script.remove();
			reject( new Error( 'Google could not load.' ) );
		}
		function loadModule() {
			if ( ! window.gapi?.load ) {
				fail();
				return;
			}
			window.gapi.load( 'surveyoptin', {
				callback: () => {
					clearTimeout( timer );
					if ( window.gapi.surveyoptin?.render ) {
						resolve( window.gapi.surveyoptin );
					} else {
						fail();
					}
				},
				onerror: fail,
				timeout: 15000,
				ontimeout: fail,
			} );
		}
		if ( window.gapi?.load ) {
			loadModule();
			return;
		}
		window.reviewbirdGcrLoaded = loadModule;
		script.src =
			'https://apis.google.com/js/platform.js?onload=reviewbirdGcrLoaded';
		script.async = true;
		script.onerror = fail;
		document.head.appendChild( script );
	} ).catch( ( error ) => {
		state.loading = null;
		throw error;
	} );
	return state.loading;
}

export function initGoogleCustomerReviews( root ) {
	if ( root.dataset.gcrReady ) {
		return;
	}
	const config = JSON.parse( root.dataset.reviewbirdGcr );
	root.dataset.gcrReady = 'true';
	const yes = root.querySelector( '[data-gcr-yes]' );
	const no = root.querySelector( '[data-gcr-no]' );
	const status = root.querySelector( '[data-gcr-status]' );
	const retry = root.querySelector( '[data-gcr-retry]' );
	let busy = false;
	let saved = config.mode === 'direct';

	async function open() {
		if ( state.rendered ) {
			root.hidden = true;
			return;
		}
		if ( busy ) {
			return;
		}
		busy = true;
		root.setAttribute( 'aria-busy', 'true' );
		[ yes, no, retry ].forEach( ( button ) => {
			if ( button ) {
				button.disabled = true;
			}
		} );
		retry.hidden = true;
		status.textContent = config.text.loading;
		try {
			if ( ! saved ) {
				const controller = new window.AbortController();
				const timeout = setTimeout( () => controller.abort(), 15000 );
				const response = await fetch( config.choiceUrl, {
					method: 'POST',
					credentials: 'same-origin',
					signal: controller.signal,
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify( { token: config.token } ),
				} ).finally( () => clearTimeout( timeout ) );
				if ( ! response.ok ) {
					throw new Error( 'The choice could not be saved.' );
				}
				saved = true;
			}
			const google = await loadGoogle();
			if ( ! state.rendered ) {
				google.render( {
					...config.google,
					merchant_id: Number( config.google.merchant_id ),
				} );
				state.rendered = true;
			}
			root.hidden = config.mode !== 'direct';
			status.textContent = config.text.opened;
		} catch {
			status.textContent = config.text.error;
			retry.hidden = false;
		} finally {
			busy = false;
			root.removeAttribute( 'aria-busy' );
			[ yes, no, retry ].forEach( ( button ) => {
				if ( button ) {
					button.disabled = false;
				}
			} );
			if ( ! retry.hidden ) {
				retry.focus();
			}
			if ( saved ) {
				if ( yes ) {
					yes.hidden = true;
				}
				if ( no ) {
					no.hidden = true;
				}
			}
		}
	}
	yes?.addEventListener( 'click', open );
	no?.addEventListener( 'click', () => {
		root.hidden = true;
	} );
	retry.addEventListener( 'click', open );
	if ( config.mode === 'direct' ) {
		open();
	}
}

function start() {
	document
		.querySelectorAll( '[data-reviewbird-gcr]' )
		.forEach( initGoogleCustomerReviews );
	document.querySelectorAll( '[data-gcr-copy]' ).forEach( ( button ) => {
		button.addEventListener( 'click', async () => {
			const status = button.parentElement.querySelector(
				'[data-gcr-copy-status]'
			);
			try {
				await window.navigator.clipboard.writeText(
					button.dataset.gcrUrl
				);
				if ( status ) {
					status.textContent =
						button.dataset.gcrCopied || 'Link copied.';
				}
			} catch {
				button.parentElement
					.querySelector( 'input[readonly]' )
					?.select();
				if ( status ) {
					status.textContent =
						button.dataset.gcrCopyError ||
						'Select and copy the link.';
				}
			}
		} );
	} );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', start );
} else {
	start();
}
