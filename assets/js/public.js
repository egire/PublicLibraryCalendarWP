/**
 * Public-facing registration form submission via admin-ajax.
 *
 * Progressive enhancement: the form posts normally if JS is unavailable
 * (handled server-side is out of scope here, but the markup degrades gracefully).
 */
( function () {
	'use strict';

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	ready( function () {
		var forms = document.querySelectorAll( '.plc-register-form' );
		Array.prototype.forEach.call( forms, bindForm );

		var copyBtns = document.querySelectorAll( '.plc-copy-link' );
		Array.prototype.forEach.call( copyBtns, bindCopyLink );
	} );

	function bindCopyLink( btn ) {
		btn.addEventListener( 'click', function () {
			var url    = btn.getAttribute( 'data-url' ) || '';
			var copied = btn.getAttribute( 'data-copied' ) || 'Copied!';
			var done   = function () {
				var original = btn.textContent;
				btn.textContent = copied;
				setTimeout( function () { btn.textContent = original; }, 1500 );
			};
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( url ).then( done ).catch( done );
			} else {
				var ta = document.createElement( 'textarea' );
				ta.value = url;
				ta.style.position = 'fixed';
				ta.style.opacity = '0';
				document.body.appendChild( ta );
				ta.select();
				try { document.execCommand( 'copy' ); } catch ( e ) {}
				document.body.removeChild( ta );
				done();
			}
		} );
	}

	function bindForm( form ) {
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();

			var button  = form.querySelector( 'button[type="submit"]' );
			var message = form.querySelector( '.plc-form-message' );
			var data    = new FormData( form );

			data.append( 'action', 'plc_register' );
			data.append( 'nonce', PLC.nonce );

			setMessage( message, '', '' );
			button.disabled = true;
			var originalLabel = button.textContent;
			button.textContent = PLC.strings.submitting;

			fetch( PLC.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: data
			} )
				.then( function ( res ) {
					return res.json().then( function ( json ) {
						return { ok: res.ok, json: json };
					} );
				} )
				.then( function ( result ) {
					var payload = result.json || {};
					if ( result.ok && payload.success ) {
						form.reset();
						form.classList.add( 'plc-submitted' );
						replaceWithSuccess( form, payload.data.message, payload.data.status );
					} else {
						var msg = ( payload.data && payload.data.message ) ? payload.data.message : PLC.strings.error;
						setMessage( message, msg, 'error' );
						button.disabled = false;
						button.textContent = originalLabel;
					}
				} )
				.catch( function () {
					setMessage( message, PLC.strings.error, 'error' );
					button.disabled = false;
					button.textContent = originalLabel;
				} );
		} );
	}

	function setMessage( el, text, type ) {
		if ( ! el ) {
			return;
		}
		el.textContent = text;
		el.className = 'plc-form-message' + ( type ? ' plc-form-message-' + type : '' );
	}

	function replaceWithSuccess( form, text, status ) {
		var box = document.createElement( 'div' );
		box.className = 'plc-success' + ( status === 'waitlist' ? ' plc-success-wait' : '' );
		box.setAttribute( 'role', 'status' );
		box.innerHTML = '<span class="plc-success-icon" aria-hidden="true">✓</span>';
		var p = document.createElement( 'p' );
		p.textContent = text;
		box.appendChild( p );
		form.parentNode.replaceChild( box, form );
	}
} )();
