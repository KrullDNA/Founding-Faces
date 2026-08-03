/**
 * Filtering and "load more" for a member's notes list.
 *
 * Both talk to the same endpoint. Changing a filter replaces the list from the
 * start; the button appends the next batch. Either way only the rows for that
 * request come back, so a member with hundreds of notes never waits on one
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
	 * Wire one notes section: its filters and its button.
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

					if ( ! data.hasMore ) {
						wrap.hidden = true;
					}
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

						if ( data.empty ) {
							results.innerHTML = '<p class="ff-empty-note"></p>';
							results.firstChild.textContent = data.empty;
						} else {
							results.innerHTML = '<ul class="ff-history-list ff-notes-read-list"></ul>';
							var list = results.firstChild;
							var rows = parseRows( data.html );
							while ( rows.length ) {
								list.appendChild( rows[ 0 ] );
							}
						}

						button.dataset.offset = data.offset;
						wrap.hidden = ! data.hasMore;
					} )
					.catch( function () {
						section.classList.remove( 'is-loading' );
						showError( section );
					} );
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
