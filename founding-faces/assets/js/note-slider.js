/**
 * Founding Faces — the note image slider.
 *
 * Dependency-free. Moves a flex track by the offset of the slide it should
 * land on, which keeps it right whatever the gap, the widths or the number of
 * images on show — all of which are CSS, set per device by the widget's
 * controls, and read back here rather than duplicated in JavaScript.
 *
 * How many images are on show comes from the --ff-slides custom property, so
 * one page of markup behaves correctly at every breakpoint: the arrows and
 * dots take themselves away on any screen where every image already fits, and
 * come back when it doesn't.
 *
 * Nothing here decides how anything looks. It sets a transform, toggles
 * is-current on a dot, and adds ff-slider--static when there is nothing to
 * scroll. Every other appearance is a control in Elementor.
 */
( function () {
	'use strict';

	// How many images are meant to be on show at this screen width.
	function perView( el ) {
		var raw = window.getComputedStyle( el ).getPropertyValue( '--ff-slides' );
		var n = parseInt( raw, 10 );
		return ( n > 0 ) ? n : 1;
	}

	// Whether the member has asked their device to keep motion to a minimum.
	function reducedMotion() {
		return window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	}

	function setup( el ) {
		if ( ! el || el.getAttribute( 'data-ff-ready' ) === '1' ) {
			return;
		}

		var track = el.querySelector( '.ff-slider-track' );
		if ( ! track ) {
			return;
		}

		el.setAttribute( 'data-ff-ready', '1' );

		var slides = Array.prototype.slice.call( track.children );
		var prev = el.querySelector( '.ff-slider-prev' );
		var next = el.querySelector( '.ff-slider-next' );
		var dots = Array.prototype.slice.call( el.querySelectorAll( '.ff-slider-dot' ) );
		var autoplay = parseInt( el.getAttribute( 'data-autoplay' ), 10 ) || 0;
		var index = 0;
		var timer = null;

		// The furthest left the track can go: the last position where the
		// viewport is still full of images.
		function maxIndex() {
			return Math.max( 0, slides.length - perView( el ) );
		}

		// Move the track and bring the controls into line with where it is.
		function apply() {
			var max = maxIndex();
			var still = ( max === 0 );

			if ( index > max ) {
				index = max;
			}

			var slide = slides[ index ];
			track.style.transform = 'translateX(' + ( slide ? -slide.offsetLeft : 0 ) + 'px)';

			// Nothing to scroll: the arrows and dots have no job to do.
			el.classList.toggle( 'ff-slider--static', still );

			for ( var i = 0; i < dots.length; i++ ) {
				// One dot per resting position, so extras go when more than one
				// image is on show at a time.
				dots[ i ].hidden = ( i > max );
				dots[ i ].classList.toggle( 'is-current', i === index );
				if ( i === index ) {
					dots[ i ].setAttribute( 'aria-current', 'true' );
				} else {
					dots[ i ].removeAttribute( 'aria-current' );
				}
			}
		}

		// Go to a position, wrapping round the ends: the slider never dead-ends.
		function go( to ) {
			var max = maxIndex();
			if ( to < 0 ) {
				to = max;
			} else if ( to > max ) {
				to = 0;
			}
			index = to;
			apply();
		}

		function stop() {
			if ( timer ) {
				window.clearInterval( timer );
				timer = null;
			}
		}

		function start() {
			stop();
			if ( autoplay > 0 && ! reducedMotion() && maxIndex() > 0 ) {
				timer = window.setInterval( function () {
					go( index + 1 );
				}, autoplay );
			}
		}

		if ( prev ) {
			prev.addEventListener( 'click', function () {
				go( index - 1 );
				start();
			} );
		}
		if ( next ) {
			next.addEventListener( 'click', function () {
				go( index + 1 );
				start();
			} );
		}

		dots.forEach( function ( dot, i ) {
			dot.addEventListener( 'click', function () {
				go( i );
				start();
			} );
		} );

		// A swipe on a touch screen does what the arrows do.
		var startX = null;
		var startY = null;
		el.addEventListener( 'touchstart', function ( e ) {
			if ( e.touches && e.touches.length === 1 ) {
				startX = e.touches[ 0 ].clientX;
				startY = e.touches[ 0 ].clientY;
			}
		}, { passive: true } );
		el.addEventListener( 'touchend', function ( e ) {
			if ( startX === null || ! e.changedTouches || ! e.changedTouches.length ) {
				return;
			}
			var dx = e.changedTouches[ 0 ].clientX - startX;
			var dy = e.changedTouches[ 0 ].clientY - startY;
			startX = null;
			// Sideways only — a vertical swipe is the member scrolling the page.
			if ( Math.abs( dx ) > 40 && Math.abs( dx ) > Math.abs( dy ) ) {
				go( index + ( dx < 0 ? 1 : -1 ) );
				start();
			}
		}, { passive: true } );

		// Autoplay waits while the slider is being looked at or used.
		el.addEventListener( 'mouseenter', stop );
		el.addEventListener( 'mouseleave', start );
		el.addEventListener( 'focusin', stop );
		el.addEventListener( 'focusout', start );

		// Widths change on resize; images change the layout as they arrive.
		var resizeTimer = null;
		window.addEventListener( 'resize', function () {
			window.clearTimeout( resizeTimer );
			resizeTimer = window.setTimeout( apply, 150 );
		} );

		Array.prototype.forEach.call( el.querySelectorAll( 'img' ), function ( img ) {
			if ( ! img.complete ) {
				img.addEventListener( 'load', apply );
			}
		} );

		apply();
		start();
	}

	function initAll( root ) {
		var scope = ( root && root.querySelectorAll ) ? root : document;
		Array.prototype.forEach.call( scope.querySelectorAll( '[data-ff-slider]' ), setup );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () { initAll( document ); } );
	} else {
		initAll( document );
	}

	// Elementor injects widgets after load, in the editor and on the front end.
	window.addEventListener( 'elementor/frontend/init', function () {
		if ( typeof elementorFrontend === 'undefined' || ! elementorFrontend.hooks ) {
			return;
		}
		elementorFrontend.hooks.addAction( 'frontend/element_ready/ff_note_gallery.default', function ( $scope ) {
			initAll( $scope && $scope[ 0 ] ? $scope[ 0 ] : document );
		} );
	} );
} )();
