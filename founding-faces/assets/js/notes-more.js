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
	 * How many rows this screen should hold.
	 *
	 * The page arrives built at the desktop size, one document is served to
	 * every screen, and behind a page cache the same document is served to
	 * every visitor, so the server cannot decide this. Read at request time
	 * rather than stored, so a rotated tablet is right without any bookkeeping.
	 *
	 * @param {HTMLElement} button The load-more button, which holds the sizes.
	 * @return {number} Rows per page for this viewport.
	 */
	function devicePerPage( button ) {
		var desktop = parseInt( button.dataset.perPage, 10 ) || 0;
		var width   = window.innerWidth;

		var mobileBp = parseInt( button.dataset.bpMobile, 10 ) || 767;
		if ( width <= mobileBp ) {
			return parseInt( button.dataset.ppMobile, 10 ) || desktop;
		}

		var tabletBp = parseInt( button.dataset.bpTablet, 10 ) || 1024;
		if ( width <= tabletBp ) {
			return parseInt( button.dataset.ppTablet, 10 ) || desktop;
		}

		return desktop;
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
		body.append( 'per_page', devicePerPage( button ) );
		body.append( 'link', button.dataset.link );
		body.append( 'new_tab', button.dataset.newTab );
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

		// --- First page: cut it down to this device's size. ---
		// The rows for a desktop page are already here, so a smaller screen
		// only has to drop the surplus, no request, no flash of the wrong
		// length. A larger one does need fetching.
		( function fitFirstPage() {
			var rendered = parseInt( button.dataset.perPage, 10 ) || 0;
			var want     = devicePerPage( button );

			if ( ! rendered || ! want || want === rendered ) {
				return;
			}

			var list = results.querySelector( '.ff-notes-read-list' );

			if ( want < rendered && list ) {
				var trimmed = false;
				while ( list.children.length > want ) {
					list.removeChild( list.lastElementChild );
					trimmed = true;
				}

				// However many are left, that is where the next batch starts.
				// A member with three notes and a page size of ten still has
				// three, and nothing more to load.
				button.dataset.offset = list.children.length;

				if ( trimmed && wrap && 'numbers' !== section.dataset.paging ) {
					wrap.hidden = false;
				}

				// The numbers were counted in desktop pages, so they need
				// rebuilding, the rows themselves are already correct.
				if ( section.querySelector( '.ff-notes-pager' ) ) {
					request( section, button, 0 )
						.then( function ( data ) {
							refreshControls( section, wrap, data );
						} )
						.catch( function () {} );
				}
				return;
			}

			// This screen wants a longer page than was rendered.
			request( section, button, 0 )
				.then( function ( data ) {
					replaceRows( results, data );
					button.dataset.offset = data.offset;
					refreshControls( section, wrap, data );
				} )
				.catch( function () {} );
		} )();

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

			var perPage = devicePerPage( button );
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
