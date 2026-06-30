/**
 * Admin helpers for the event editor.
 * Auto-fills the end time to one hour after the start when start changes
 * and end is empty, as a small convenience.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		bindCopyButtons();
		bindEndTimeHelper();
	} );

	/* Copy-to-clipboard for shortcode snippets on the settings page. */
	function bindCopyButtons() {
		var buttons = document.querySelectorAll( '.plc-copy' );
		Array.prototype.forEach.call( buttons, function ( btn ) {
			btn.addEventListener( 'click', function () {
				var text = btn.getAttribute( 'data-plc-copy' ) || '';
				copyText( text ).then( function () {
					var original = btn.textContent;
					btn.textContent = '✓ Copied';
					setTimeout( function () {
						btn.textContent = original;
					}, 1500 );
				} );
			} );
		} );
	}

	function copyText( text ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			return navigator.clipboard.writeText( text );
		}
		// Fallback for older browsers / insecure contexts.
		return new Promise( function ( resolve ) {
			var ta = document.createElement( 'textarea' );
			ta.value = text;
			ta.style.position = 'fixed';
			ta.style.opacity = '0';
			document.body.appendChild( ta );
			ta.select();
			try {
				document.execCommand( 'copy' );
			} catch ( e ) {}
			document.body.removeChild( ta );
			resolve();
		} );
	}

	/* When the start is set and the end is still empty, default the end to one
	   hour later (filling the separate end date + time fields). */
	function bindEndTimeHelper() {
		var sDate = document.getElementById( 'plc_start_date' );
		var sTime = document.getElementById( 'plc_start_time' );
		var eDate = document.getElementById( 'plc_end_date' );
		var eTime = document.getElementById( 'plc_end_time' );

		if ( ! sDate || ! sTime || ! eDate || ! eTime ) {
			return;
		}

		function maybeFill() {
			if ( eDate.value || eTime.value || ! sDate.value || ! sTime.value ) {
				return;
			}
			var d = new Date( sDate.value + 'T' + sTime.value );
			if ( isNaN( d.getTime() ) ) {
				return;
			}
			d.setHours( d.getHours() + 1 );
			eDate.value = d.getFullYear() + '-' + pad( d.getMonth() + 1 ) + '-' + pad( d.getDate() );
			eTime.value = pad( d.getHours() ) + ':' + pad( d.getMinutes() );
		}

		sDate.addEventListener( 'change', maybeFill );
		sTime.addEventListener( 'change', maybeFill );
	}

	function pad( n ) {
		return ( n < 10 ? '0' : '' ) + n;
	}
} )();
