/**
 * Founding Faces, the note image slider.
 *
 * Dependency-free, and it moves one way. Forward always slides left and back
 * always slides right, at every point in the gallery: reaching the last image
 * and pressing forward again carries on in the same direction into the first,
 * rather than rewinding the whole strip to get there.
 *
 * That is done by rotating the track rather than scrolling it. The strip never
 * has an end to rewind from, after each step the slide that has just left is
 * moved to the other end of the track while the transition is off, so the next
 * step starts from the same place the last one did. Nothing is cloned, which
 * keeps the lightbox gallery to the images the note actually has and asks the
 * browser for each of them once.
 *
 * How many images are on show comes from the --ff-slides custom property, so
 * one page of markup behaves correctly at every breakpoint: the arrows and dots
 * take themselves away on any screen where every image already fits.
 *
 * Nothing here decides how anything looks. It sets a transform, toggles
 * is-current on a dot, and adds ff-slider--static when there is nothing to
 * rotate. Every other appearance is a control in Elementor.
 */
( function () {
	'use strict';

	// How many images are meant to be on show at this screen width.
	function perView( el ) {
		var raw = window.getComputedStyle( el ).getPropertyValue( '--ff-slides' );
		var n = parseInt( raw, 10 );
		return ( n > 0 ) ? n : 1;
	}

	// How long a step takes, in milliseconds, as the CSS has it.
	function speed( track ) {
		var raw = window.getComputedStyle( track ).transitionDuration || '0s';
		var first = raw.split( ',' )[ 0 ].trim();
		var ms = parseFloat( first ) * ( first.indexOf( 'ms' ) > -1 ? 1 : 1000 );
		return ( ms > 0 ) ? ms : 0;
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

		var count = track.children.length;
		var prev = el.querySelector( '.ff-slider-prev' );
		var next = el.querySelector( '.ff-slider-next' );
		var dots = Array.prototype.slice.call( el.querySelectorAll( '.ff-slider-dot' ) );
		var autoplay = parseInt( el.getAttribute( 'data-autoplay' ), 10 ) || 0;

		// Which image is at the front, counted in the order they were published
		// rather than the order the track happens to hold them in.
		var current = 0;
		var timer = null;
		var moving = false;

		// Nothing to rotate when every image is already on show.
		function still() {
			return count <= perView( el );
		}

		// The width of one step: the distance between two slides' left edges,
		// which covers the gap without having to read it.
		function step() {
			if ( track.children.length < 2 ) {
				return 0;
			}
			return track.children[ 1 ].offsetLeft - track.children[ 0 ].offsetLeft;
		}

		// Bring the controls into line with where the gallery is.
		function refresh() {
			el.classList.toggle( 'ff-slider--static', still() );

			for ( var i = 0; i < dots.length; i++ ) {
				dots[ i ].classList.toggle( 'is-current', i === current );
				if ( i === current ) {
					dots[ i ].setAttribute( 'aria-current', 'true' );
				} else {
					dots[ i ].removeAttribute( 'aria-current' );
				}
			}
		}

		// Run a function with the transition off, so a jump is not animated.
		function withoutTransition( fn ) {
			track.style.transition = 'none';
			fn();
			// Reading a layout property forces the browser to apply the change
			// before the transition is put back, which is what stops the jump
			// from animating.
			void track.offsetHeight;
			track.style.transition = '';
		}

		// Forward: slide left by one, then move the slide that has left to the
		// back of the track. The track is then exactly where it started, so the
		// next step goes the same way.
		function forward() {
			if ( moving || still() ) {
				return;
			}
			moving = true;

			var distance = step();
			var ms = speed( track );

			var settle = function () {
				withoutTransition( function () {
					track.appendChild( track.firstElementChild );
					track.style.transform = 'translateX(0px)';
				} );
				current = ( current + 1 ) % count;
				moving = false;
				refresh();
			};

			if ( ms <= 0 ) {
				settle();
				return;
			}

			track.style.transform = 'translateX(' + ( -distance ) + 'px)';
			window.setTimeout( settle, ms + 20 );
		}

		// Back: put the last slide at the front and offset the track by one step
		// with the transition off, then animate back to nothing, which slides
		// right, from a slide that was already in place.
		function back() {
			if ( moving || still() ) {
				return;
			}
			moving = true;

			var ms = speed( track );

			withoutTransition( function () {
				track.insertBefore( track.lastElementChild, track.firstElementChild );
				track.style.transform = 'translateX(' + ( -step() ) + 'px)';
			} );

			track.style.transform = 'translateX(0px)';
			current = ( current - 1 + count ) % count;
			refresh();

			window.setTimeout( function () {
				moving = false;
			}, ( ms > 0 ? ms : 0 ) + 20 );
		}

		// Jump straight to one image, for a dot. Rotating a step at a time would
		// be a long walk across a gallery of any size, so this one does move.
		function goTo( target ) {
			if ( moving || still() || target === current ) {
				return;
			}

			var steps = ( target - current + count ) % count;

			withoutTransition( function () {
				for ( var i = 0; i < steps; i++ ) {
					track.appendChild( track.firstElementChild );
				}
				track.style.transform = 'translateX(0px)';
			} );

			current = target;
			refresh();
		}

		if ( prev ) {
			prev.addEventListener( 'click', function () {
				back();
				start();
			} );
		}
		if ( next ) {
			next.addEventListener( 'click', function () {
				forward();
				start();
			} );
		}

		dots.forEach( function ( dot, i ) {
			dot.addEventListener( 'click', function () {
				goTo( i );
				start();
			} );
		} );

		function stop() {
			if ( timer ) {
				window.clearInterval( timer );
				timer = null;
			}
		}

		function start() {
			stop();
			if ( autoplay > 0 && ! reducedMotion() && ! still() ) {
				timer = window.setInterval( forward, autoplay );
			}
		}

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
			// Sideways only, a vertical swipe is the member scrolling the page.
			if ( Math.abs( dx ) > 40 && Math.abs( dx ) > Math.abs( dy ) ) {
				if ( dx < 0 ) {
					forward();
				} else {
					back();
				}
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
			resizeTimer = window.setTimeout( refresh, 150 );
		} );

		Array.prototype.forEach.call( el.querySelectorAll( 'img' ), function ( img ) {
			if ( ! img.complete ) {
				img.addEventListener( 'load', refresh );
			}
		} );

		refresh();
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
