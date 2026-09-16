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
	// WooCommerce's status block runs the thank-you hook before its title.
	root.parentElement
		?.querySelector( ':scope.wc-block-order-confirmation-status > h1' )
		?.after( root );
	const status = root.querySelector( '[data-gcr-status]' );
	const retry = root.querySelector( '[data-gcr-retry]' );
	let busy = false;

	async function openGoogle() {
		if ( state.rendered ) {
			root.hidden = true;
			return;
		}
		if ( busy ) {
			return;
		}
		busy = true;
		root.setAttribute( 'aria-busy', 'true' );
		retry.disabled = true;
		retry.hidden = true;
		status.textContent = config.text.loading;
		try {
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
			root.hidden = false;
			status.textContent = config.text.error;
			retry.hidden = false;
		} finally {
			busy = false;
			root.removeAttribute( 'aria-busy' );
			retry.disabled = false;
			if ( ! retry.hidden ) {
				retry.focus();
			}
		}
	}
	retry.addEventListener( 'click', openGoogle );
	openGoogle();
}

function start() {
	document
		.querySelectorAll( '[data-reviewbird-gcr]' )
		.forEach( initGoogleCustomerReviews );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', start );
} else {
	start();
}
