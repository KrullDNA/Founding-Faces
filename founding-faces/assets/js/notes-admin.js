/**
 * Founding Faces — note admin: the image gallery picker.
 *
 * Uses the built-in WordPress media library (wp.media) to pick multiple images
 * for a note. The chosen attachment ids are stored, comma-separated, in a
 * hidden field the metabox saves. No third-party library.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		var frame;
		var $field   = $( '#ff_note_gallery' );
		var $preview = $( '#ff_note_gallery_preview' );

		// Open the media library, pre-selecting whatever's already chosen.
		$( '#ff_note_gallery_add' ).on( 'click', function ( e ) {
			e.preventDefault();

			// Reuse the frame if it's already been built.
			if ( frame ) {
				frame.open();
				return;
			}

			frame = wp.media( {
				title: 'Select note images',
				button: { text: 'Use these images' },
				multiple: true,
				library: { type: 'image' }
			} );

			// When images are chosen, store their ids and rebuild the preview.
			frame.on( 'select', function () {
				var ids = [];
				var html = '';
				frame.state().get( 'selection' ).each( function ( attachment ) {
					var a = attachment.toJSON();
					ids.push( a.id );
					var src = ( a.sizes && a.sizes.thumbnail ) ? a.sizes.thumbnail.url : a.url;
					html += '<span class="ff-gallery-thumb"><img src="' + src + '" alt="" /></span>';
				} );
				$field.val( ids.join( ',' ) );
				$preview.html( html );
			} );

			// Pre-select the current images when the frame opens.
			frame.on( 'open', function () {
				var selection = frame.state().get( 'selection' );
				var current = $field.val();
				if ( ! current ) {
					return;
				}
				current.split( ',' ).forEach( function ( id ) {
					id = parseInt( id, 10 );
					if ( id ) {
						selection.add( wp.media.attachment( id ) );
					}
				} );
			} );

			frame.open();
		} );

		// Clear the whole gallery.
		$( '#ff_note_gallery_clear' ).on( 'click', function ( e ) {
			e.preventDefault();
			$field.val( '' );
			$preview.html( '' );
		} );
	} );
} )( jQuery );
