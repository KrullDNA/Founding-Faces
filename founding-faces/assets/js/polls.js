/**
 * Founding Faces — front-end poll voting.
 *
 * When a member clicks an option, the vote is sent over AJAX and the poll's
 * inner content is swapped for the aggregate results returned by the server.
 * Aggregates only — no identities ever reach the browser. Plain vanilla JS.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		// Delegate clicks so it works for any poll on the page.
		document.addEventListener( 'click', function ( e ) {
			var button = e.target.closest( '.ff-poll-option' );
			if ( ! button ) {
				return;
			}

			var poll = button.closest( '.ff-poll' );
			if ( ! poll ) {
				return;
			}

			e.preventDefault();
			castVote( poll, button );
		} );
	} );

	// Send the vote and reveal the results.
	function castVote( poll, button ) {
		var pollId = poll.getAttribute( 'data-poll' );
		var optionId = button.getAttribute( 'data-option' );
		var errorBox = poll.querySelector( '.ff-poll-error' );

		// Prevent double-clicks while the request is in flight.
		var buttons = poll.querySelectorAll( '.ff-poll-option' );
		buttons.forEach( function ( b ) { b.disabled = true; } );

		var body = new URLSearchParams();
		body.append( 'action', ffPolls.action );
		body.append( 'nonce', ffPolls.nonce );
		body.append( 'poll_id', pollId );
		body.append( 'option_id', optionId );

		// Carry this poll's wording back to the server, so the results that
		// replace the buttons use the same words the widget was set to.
		Object.keys( poll.dataset ).forEach( function ( key ) {
			if ( 0 === key.indexOf( 'text' ) && key.length > 4 ) {
				var name = key.slice( 4 ).replace( /([A-Z])/g, '_$1' ).toLowerCase();
				body.append( 'text' + name, poll.dataset[ key ] );
			}
		} );

		fetch( ffPolls.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		} )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) {
				if ( res && res.success && res.data && res.data.html ) {
					var inner = poll.querySelector( '.ff-poll-inner' );
					if ( inner ) {
						inner.outerHTML = res.data.html;
					}
				} else {
					showError( errorBox, ( res && res.data && res.data.message ) || 'Sorry, your vote could not be recorded.' );
					buttons.forEach( function ( b ) { b.disabled = false; } );
				}
			} )
			.catch( function () {
				showError( errorBox, 'Sorry, something went wrong. Please try again.' );
				buttons.forEach( function ( b ) { b.disabled = false; } );
			} );
	}

	// Show an inline error message.
	function showError( box, message ) {
		if ( box ) {
			box.textContent = message;
			box.style.display = 'block';
		}
	}
} )();
