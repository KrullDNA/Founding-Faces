/**
 * Filtering, "load more" and page numbers for a member's notes list.
 *
 * All three talk to the same endpoint. Changing a filter replaces the list from
 * the start, a page number replaces it from that page, and the button appends
 * the next batch onto what is already there. Either way only the rows for that
 * one request come back, so a member with hundreds of notes never waits on one
 * long list.
 *
 * No dependencies, no build step.
 */
( function () {
	'use strict';

	/**
	 * Read the current filter values from a section's filter bar.
	 *
	 * @param {HTMLElement} section The notes section.
	 * @return {Object} Filter name => value.
	 */
	function readFilters( section ) {
		var values = {};
		var fields = section.querySelectorAll( '.ff-note-filter' );

		Array.prototype.forEach.call( fields, function ( field ) {
			values[ field.dataset.filter ] = field.value;
		} );

		return values;
	}

	/**
	 * Ask the server for a batch of rows.
	 *
	 * @param {HTMLElement} section The notes section.
	 * @param {HTMLElement} button  The load-more button (holds the request state).
	 * @param {number}      offset  Where to start.
	 * @return {Promise<Object>} The response payload.
	 */
	function request( section, button, offset ) {
		var body = new URLSearchParams();
		body.append( 'action', 'ff_load_notes' );
		body.append( 'nonce', button.dataset.nonce );
		body.append( 'offset', offset );
		body.append( 'per_page', button.dataset.perPage );
		body.append( 'link', button.dataset.link );
		body.append( 'show_product', button.dataset.showProduct );

		var filters = readFilters( section );
		Object.keys( filters ).forEach( function ( key ) {
			body.append( key, filters[ key ] );
		} );

		return fetch( ffNotesMore.ajaxUrl, {
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
				return result.data;
			} );
	}

	/**
	 * Turn returned markup into list items.
	 *
	 * Parsed inside a matching <ul> so the browser keeps them as list items.
	 *
	 * @param {string} html The returned rows.
	 * @return {HTMLCollection} The parsed rows.
	 */
	function parseRows( html ) {
		var parsed = document.createElement( 'div' );
		parsed.innerHTML = '<ul>' + html + '</ul>';
		return parsed.firstChild.children;
	}

	/**
	 * Show an error under the list, reusing one element.
	 *
	 * @param {HTMLElement} section The notes section.
	 */
	function showError( section ) {
		var note = section.querySelector( '.ff-notes-more-error' );
		if ( ! note ) {
			note = document.createElement( 'p' );
			note.className = 'ff-notes-more-error';
			section.appendChild( note );
		}
		note.textContent = ffNotesMore.error;
	}

	/**
	 * Clear any visible error.
	 *
	 * @param {HTMLElement} section The notes section.
	 */
	function clearError( section ) {
		var note = section.querySelector( '.ff-notes-more-error' );
		if ( note ) {
			note.parentNode.removeChild( note );
		}
	}

	/**
	 * Put a fresh set of rows in place of whatever is showing.
	 *
	 * @param {HTMLElement} results The results wrapper.
	 * @param {Object}      data    The response payload.
	 */
	function replaceRows( results, data ) {
		if ( data.empty ) {
			results.innerHTML = '<p class="ff-empty-note"></p>';
			results.firstChild.textContent = data.empty;
			return;
		}

		results.innerHTML = '<ul class="ff-history-list ff-notes-read-list"></ul>';

		var list = results.firstChild;
		var rows = parseRows( data.html );
		while ( rows.length ) {
			list.appendChild( rows[ 0 ] );
		}
	}

	/**
	 * Redraw the page numbers, and show or hide the "load more" button.
	 *
	 * The pager markup is built on the server, so the rule for which numbers
	 * appear lives in one place rather than two.
	 *
	 * @param {HTMLElement} section The notes section.
	 * @param {HTMLElement} wrap    The "load more" wrapper.
	 * @param {Object}      data    The response payload.
	 */
	function refreshControls( section, wrap, data ) {
		var pager = section.querySelector( '.ff-notes-pager' );
		if ( pager && undefined !== data.pager ) {
			pager.innerHTML = data.pager;
		}

		if ( wrap ) {
			// Numbers-only paging has no button to reveal.
			wrap.hidden = ( 'numbers' === section.dataset.paging ) || ! data.hasMore;
		}
	}

	/**
	 * Wire one notes section: its filters, its button and its page numbers.
	 *
	 * @param {HTMLElement} section The notes section.
	 */
	function wire( section ) {
		if ( section.dataset.ffBound ) {
			return;
		}
		section.dataset.ffBound = '1';

		var wrap    = section.querySelector( '.ff-notes-more' );
		var button  = section.querySelector( '.ff-notes-more-button' );
		var results = section.querySelector( '.ff-notes-results' );

		// No nonce means this is the editor's sample list: it is there to be
		// styled, not driven, so leave its button and filters inert rather than
		// letting a click return an error on the canvas.
		if ( ! button || ! results || ! button.dataset.nonce ) {
			return;
		}

		var label = button.textContent;

		// --- Load more: append the next batch. ---
		button.addEventListener( 'click', function () {
			if ( button.disabled ) {
				return;
			}

			var list = results.querySelector( '.ff-notes-read-list' );
			if ( ! list ) {
				return;
			}

			button.disabled = true;
			button.textContent = ffNotesMore.loading;
			clearError( section );

			request( section, button, button.dataset.offset )
				.then( function ( data ) {
					var rows = parseRows( data.html );
					while ( rows.length ) {
						list.appendChild( rows[ 0 ] );
					}

					button.dataset.offset = data.offset;
					button.disabled = false;
					button.textContent = label;
					refreshControls( section, wrap, data );
				} )
				.catch( function () {
					button.disabled = false;
					button.textContent = label;
					showError( section );
				} );
		} );

		// --- Filters: replace the list from the start. ---
		var fields = section.querySelectorAll( '.ff-note-filter' );
		Array.prototype.forEach.call( fields, function ( field ) {
			field.addEventListener( 'change', function () {
				section.classList.add( 'is-loading' );
				clearError( section );

				request( section, button, 0 )
					.then( function ( data ) {
						section.classList.remove( 'is-loading' );
						replaceRows( results, data );
						button.dataset.offset = data.offset;
						refreshControls( section, wrap, data );
					} )
					.catch( function () {
						section.classList.remove( 'is-loading' );
						showError( section );
					} );
			} );
		} );

		// --- Page numbers: jump straight to a page. ---
		// Delegated, because the pager redraws itself after every request and
		// a handler bound to today's buttons would not survive that.
		section.addEventListener( 'click', function ( e ) {
			var link = e.target.closest( '.ff-notes-page' );
			if ( ! link || link.classList.contains( 'is-current' ) ) {
				return;
			}

			var perPage = parseInt( button.dataset.perPage, 10 ) || 0;
			var page    = parseInt( link.dataset.page, 10 ) || 1;
			var offset  = ( page - 1 ) * perPage;

			section.classList.add( 'is-loading' );
			clearError( section );

			request( section, button, offset )
				.then( function ( data ) {
					section.classList.remove( 'is-loading' );
					replaceRows( results, data );
					button.dataset.offset = data.offset;
					refreshControls( section, wrap, data );

					// The list has moved under the reader's feet, so put its
					// top back where they can see it.
					section.scrollIntoView( { block: 'start', behavior: 'smooth' } );
				} )
				.catch( function () {
					section.classList.remove( 'is-loading' );
					showError( section );
				} );
		} );
	}

	function init() {
		var sections = document.querySelectorAll( '.ff-notes-section' );
		Array.prototype.forEach.call( sections, wire );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
