/**
 * Founding Faces, note admin: the image gallery picker.
 *
 * Uses the built-in WordPress media library (wp.media) to pick images and
 * video for a note. The chosen attachment ids are stored, comma-separated, in
 * a hidden field the metabox saves. Video sits in the same list as the images
 * rather than a field of its own, so the order of the gallery is the order
 * they were chosen in. No third-party library.
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
				title: 'Select note images and video',
				button: { text: 'Use these' },
				multiple: true,
				library: { type: [ 'image', 'video' ] }
			} );

			// When images are chosen, store their ids and rebuild the preview.
			frame.on( 'select', function () {
				var ids = [];
				var html = '';
				frame.state().get( 'selection' ).each( function ( attachment ) {
					var a = attachment.toJSON();
					ids.push( a.id );

					// A video shows its poster frame if it has one, and its
					// file name if it does not. Either way the thumbnail is
					// marked so the order of a mixed gallery is readable.
					var src = ( a.sizes && a.sizes.thumbnail ) ? a.sizes.thumbnail.url : '';
					if ( a.type === 'video' ) {
						src = a.image && a.image.src ? a.image.src : src;
						html += '<span class="ff-gallery-thumb ff-gallery-thumb--video">'
							+ ( src ? '<img src="' + src + '" alt="" />' : '' )
							+ '<span class="ff-gallery-thumb-label">' + ( a.filename || 'Video' ) + '</span>'
							+ '</span>';
						return;
					}

					html += '<span class="ff-gallery-thumb"><img src="' + ( src || a.url ) + '" alt="" /></span>';
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
