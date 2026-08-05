/**
 * Founding Faces, application form enhancements.
 *
 * Progressive enhancement only. The server is the authority on validation;
 * this just makes the postcode field pleasant to fill in and gives instant
 * feedback before submitting. No libraries, plain vanilla JavaScript.
 */
( function () {
	'use strict';

	// Run once the page has loaded.
	document.addEventListener( 'DOMContentLoaded', function () {
		// Keep the postcode field to four digits, numbers only, as the person types.
		var postcode = document.getElementById( 'ff-postcode' );
		if ( postcode ) {
			postcode.addEventListener( 'input', function () {
				// Strip anything that isn't a digit, then cap at four characters.
				this.value = this.value.replace( /\D/g, '' ).slice( 0, 4 );
			} );
		}
	} );
} )();
