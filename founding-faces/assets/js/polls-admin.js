/**
 * Founding Faces, poll editor (admin).
 *
 * Adds and removes option rows, and lets each option pick an image from the
 * WordPress media library. New rows are cloned from a hidden template; PHP
 * assigns each a stable id on save. No third-party library.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		var $list = $( '#ff-poll-options' );
		var rowTemplate = $( '#ff-poll-row-template' ).html();

		// Add a new blank option row.
		$( '#ff-poll-add-option' ).on( 'click', function ( e ) {
			e.preventDefault();
			$list.append( rowTemplate );
		} );

		// Remove an option row.
		$list.on( 'click', '.ff-poll-remove', function ( e ) {
			e.preventDefault();
			$( this ).closest( '.ff-poll-row' ).remove();
		} );

		// Pick (or change) the image for a row.
		$list.on( 'click', '.ff-poll-pick-image', function ( e ) {
			e.preventDefault();
			var $row = $( this ).closest( '.ff-poll-row' );

			var frame = wp.media( {
				title: 'Select option image',
				button: { text: 'Use this image' },
				multiple: false,
				library: { type: 'image' }
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				var src = ( attachment.sizes && attachment.sizes.thumbnail ) ? attachment.sizes.thumbnail.url : attachment.url;
				$row.find( '.ff-poll-opt-image' ).val( attachment.id );
				$row.find( '.ff-poll-row-image' ).html( '<img src="' + src + '" alt="" />' );
			} );

			frame.open();
		} );
	} );
} )( jQuery );
