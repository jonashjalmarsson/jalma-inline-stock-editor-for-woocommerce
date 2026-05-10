/**
 * jhlsq-purchase upsell JS — collapse/expand behavior + LSQ Checkout.Success
 * polling against the LSQ Order Bridge endpoint to grab the license key from
 * the order and auto-submit it.
 *
 * All PHP-side parameters arrive via the `jhlsqUpsell` global, populated by
 * wp_localize_script in JHLSQ\Purchase::enqueue_lemon_js().
 */
(function () {
	if ( typeof window.jhlsqUpsell !== 'object' || ! window.jhlsqUpsell ) {
		return;
	}
	var cfg = window.jhlsqUpsell;

	var status     = document.getElementById( 'jhlsq-pro-status' );
	var pasteBlock = document.getElementById( 'jhlsq-pro-paste' );
	var keyInput   = document.getElementById( 'jhlsq-license-key-input' );
	var card       = document.getElementById( 'jhlsq-pro-upsell' );
	var collapseBtn = document.getElementById( 'jhlsq-pro-collapse' );
	var content    = document.getElementById( 'jhlsq-pro-content' );
	var pill       = document.getElementById( 'jhlsq-pro-pill' );

	if ( ! card ) {
		return; // Upsell not on this page.
	}

	// Collapse-to-pill behavior, persisted in localStorage. Per-browser by
	// design — paid-plugin upsells should stay rediscoverable, not
	// permanently invisible across all sessions.
	var COLLAPSE_KEY = 'jhlsqProUpsellCollapsed';

	function setCollapsed( yes ) {
		if ( yes ) {
			content.style.display     = 'none';
			collapseBtn.style.display = 'none';
			pill.style.cursor         = 'pointer';
			pill.title                = pill.dataset.expandTitle || '';
			pill.style.marginBottom   = '0';
			card.style.padding        = '.5em .85em';
		} else {
			content.style.display     = '';
			collapseBtn.style.display = '';
			pill.style.cursor         = '';
			pill.title                = '';
			pill.style.marginBottom   = '.6em';
			card.style.padding        = '1em 1.25em';
		}
	}

	if ( pill ) {
		pill.dataset.expandTitle = cfg.expandLabel || '';
	}
	try {
		if ( localStorage.getItem( COLLAPSE_KEY ) === '1' ) {
			setCollapsed( true );
		}
	} catch ( e ) { /* localStorage unavailable in some sandboxed admins */ }

	if ( collapseBtn ) {
		collapseBtn.addEventListener( 'click', function () {
			setCollapsed( true );
			try { localStorage.setItem( COLLAPSE_KEY, '1' ); } catch ( e ) {}
		} );
	}
	if ( pill ) {
		pill.addEventListener( 'click', function () {
			if ( content && content.style.display === 'none' ) {
				setCollapsed( false );
				try { localStorage.removeItem( COLLAPSE_KEY ); } catch ( e ) {}
			}
		} );
	}

	function setStatus( msg ) {
		if ( status ) {
			status.hidden = false;
			status.textContent = msg;
		}
	}
	function autoSubmit( key ) {
		if ( keyInput && keyInput.form ) {
			keyInput.value = key;
			keyInput.form.submit();
		}
	}

	function pollOrder( orderId ) {
		var attempts = 0;
		var max      = 10;
		(function tick() {
			fetch( cfg.bridgeBase + '/order/' + encodeURIComponent( orderId ), { credentials: 'omit' } )
				.then( function ( r ) { return r.ok ? r.json() : null; } )
				.then( function ( data ) {
					if ( data && data.found && data.license_key ) {
						setStatus( cfg.text.licenseFound );
						autoSubmit( data.license_key );
						return;
					}
					if ( ++attempts < max ) {
						setTimeout( tick, 500 );
					} else {
						setStatus( cfg.text.pollFailed );
						if ( pasteBlock ) { pasteBlock.open = true; }
						if ( keyInput ) { keyInput.focus(); }
					}
				} )
				.catch( function () {
					if ( ++attempts < max ) {
						setTimeout( tick, 500 );
					} else {
						setStatus( cfg.text.pollNetwork );
						if ( pasteBlock ) { pasteBlock.open = true; }
						if ( keyInput ) { keyInput.focus(); }
					}
				} );
		})();
	}

	function init() {
		if ( ! window.LemonSqueezy ) {
			return;
		}
		LemonSqueezy.Setup( {
			eventHandler: function ( event ) {
				if ( event && event.event === 'Checkout.Success' ) {
					setStatus( cfg.text.thanks );
					var orderId = event.data && event.data.order && ( event.data.order.identifier || event.data.order.id );
					if ( orderId ) {
						pollOrder( String( orderId ) );
					} else {
						setStatus( cfg.text.pastePrompt );
						if ( pasteBlock ) { pasteBlock.open = true; }
						if ( keyInput ) { keyInput.focus(); }
					}
				}
			}
		} );
	}

	if ( document.readyState === 'complete' ) {
		init();
	} else {
		window.addEventListener( 'load', init );
	}
})();
