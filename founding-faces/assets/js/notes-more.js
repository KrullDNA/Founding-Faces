/**
 * "Load more" for a member's notes list.
 *
 * Fetches only the next page of rows and appends them, so a member with
 * hundreds of notes never waits on a single huge list. The button removes
 * itself once the last page has been loaded.
 *
 * No dependencies, no build step.
 */
( function () {
	'use strict';

	/**
	 * Wire a single "load more" button.
	 *
	 * @param {HTMLElement} button The button element.
	 */
	function wire( button ) {
		if ( button.dataset.ffBound ) {
			return;
		}
		button.dataset.ffBound = '1';

		var section = button.closest( '.ff-notes-section' );
		var list    = section ? section.querySelector( '.ff-notes-read-list' ) : null;
		if ( ! list ) {
			return;
		}

		var label = button.textContent;

		button.addEventListener( 'click', function () {
			if ( button.disabled ) {
				return;
			}

			button.disabled = true;
			button.textContent = ffNotesMore.loading;

			var body = new URLSearchParams();
			body.append( 'action', 'ff_load_notes' );
			body.append( 'nonce', button.dataset.nonce );
			body.append( 'offset', button.dataset.offset );
			body.append( 'per_page', button.dataset.perPage );
			body.append( 'link', button.dataset.link );

			fetch( ffNotesMore.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString()
			} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( result ) {
					if ( ! result || ! result.success ) {
						throw new Error( 'failed' );
					}

					// Parse the returned <li> rows inside a matching <ul>, so the
					// browser keeps them as list items, then move them across.
					var parsed = document.createElement( 'div' );
					parsed.innerHTML = '<ul>' + result.data.html + '</ul>';

					var rows = parsed.firstChild.children;
					while ( rows.length ) {
						list.appendChild( rows[ 0 ] );
					}

					button.dataset.offset = result.data.offset;

					if ( result.data.hasMore ) {
						button.disabled = false;
						button.textContent = label;
					} else {
						// Nothing left: take the button away rather than leave a
						// dead control on the page.
						var wrap = button.parentNode;
						if ( wrap && wrap.classList.contains( 'ff-notes-more' ) ) {
							wrap.parentNode.removeChild( wrap );
						} else {
							button.parentNode.removeChild( button );
						}
					}
				} )
				.catch( function () {
					button.disabled = false;
					button.textContent = label;

					var note = section.querySelector( '.ff-notes-more-error' );
					if ( ! note ) {
						note = document.createElement( 'p' );
						note.className = 'ff-notes-more-error';
						button.parentNode.appendChild( note );
					}
					note.textContent = ffNotesMore.error;
				} );
		} );
	}

	function init() {
		var buttons = document.querySelectorAll( '.ff-notes-more-button' );
		Array.prototype.forEach.call( buttons, wire );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
