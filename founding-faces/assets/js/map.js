/**
 * Founding Faces — the anonymous members map.
 *
 * Draws a soft, semi-transparent dot per member on a pale base map. Each map on
 * the page reads its own options AND its dot data from a single data attribute
 * (set by the shortcode or the Elementor widget). The dot data is coordinates
 * and tier only — no names, ids or postcodes.
 *
 * The dots are deliberately non-interactive: nothing clickable, no hover labels,
 * no "members near you". It stays ambient.
 *
 * Initialisation runs both on normal page load (for the shortcode) and through
 * Elementor's element-ready hook (for the widget in the editor and live), since
 * Elementor injects widgets after the page's load event has already fired.
 */
( function () {
	'use strict';

	// Rough bounding box of Australia, used when panning is locked.
	var AU_BOUNDS = [ [ -44.0, 112.0 ], [ -10.0, 154.0 ] ];

	// Initialise one map element from its data-ffmap config.
	function initMap( el ) {
		if ( ! el || typeof L === 'undefined' ) {
			return;
		}
		// Guard against being initialised twice (load + element_ready).
		if ( el.getAttribute( 'data-ff-ready' ) === '1' ) {
			return;
		}

		var cfg;
		try {
			cfg = JSON.parse( el.getAttribute( 'data-ffmap' ) );
		} catch ( e ) {
			return;
		}

		el.setAttribute( 'data-ff-ready', '1' );

		var map = L.map( el, {
			center: cfg.center,
			zoom: cfg.zoom,
			minZoom: cfg.minZoom,
			maxZoom: cfg.maxZoom,
			zoomControl: !! cfg.zoomControl,
			scrollWheelZoom: !! cfg.scrollZoom,
			dragging: !! cfg.dragging,
			attributionControl: true
		} );

		// Optionally lock panning to Australia's bounds.
		if ( cfg.lockBounds ) {
			var bounds = L.latLngBounds( AU_BOUNDS );
			map.setMaxBounds( bounds );
			map.on( 'drag', function () {
				map.panInsideBounds( bounds, { animate: false } );
			} );
		}

		// The base map imagery (what the map shows).
		L.tileLayer( cfg.tileUrl, {
			attribution: cfg.attribution,
			subdomains: 'abcd',
			maxZoom: cfg.maxZoom
		} ).addTo( map );

		var t35 = cfg.tiers[ '35' ];
		var tc = cfg.tiers.circle;
		var stroke = cfg.stroke || { on: false };
		var latlngs = [];

		// One soft dot per member.
		( cfg.points || [] ).forEach( function ( p ) {
			var is35 = ( p[ 2 ] === 35 );
			var tier = is35 ? t35 : tc;

			L.circleMarker( [ p[ 0 ], p[ 1 ] ], {
				radius: tier.size,
				fillColor: tier.color,
				fillOpacity: cfg.opacity,
				color: stroke.on ? stroke.color : tier.color,
				weight: stroke.on ? stroke.width : 0,
				opacity: stroke.on ? 1 : 0,
				interactive: false // Nothing clickable — ambient only.
			} ).addTo( map );

			latlngs.push( [ p[ 0 ], p[ 1 ] ] );
		} );

		// Frame all the dots if we have any; otherwise frame the whole of
		// Australia, so an empty map still shows the country rather than sitting
		// zoomed in on empty desert.
		if ( latlngs.length > 0 ) {
			map.fitBounds( latlngs, { padding: [ 40, 40 ], maxZoom: Math.min( 7, cfg.maxZoom ) } );
		} else {
			map.fitBounds( AU_BOUNDS, { padding: [ 20, 20 ] } );
		}

		// Optional legend.
		if ( cfg.legend && cfg.legend.on ) {
			addLegend( map, cfg );
		}

		// The container may have been sized after Leaflet measured it (common in
		// the Elementor editor), so recalculate once on the next tick.
		setTimeout( function () {
			map.invalidateSize();
		}, 200 );
	}

	// Add a small non-interactive legend of the two tiers.
	function addLegend( map, cfg ) {
		var control = L.control( { position: cfg.legend.position || 'bottomright' } );
		control.onAdd = function () {
			var div = L.DomUtil.create( 'div', 'ff-map-legend' );
			div.innerHTML =
				'<span class="ff-map-legend-item"><i style="background:' + cfg.tiers[ '35' ].color + '"></i>' + escapeHtml( cfg.legend.label35 ) + '</span>' +
				'<span class="ff-map-legend-item"><i style="background:' + cfg.tiers.circle.color + '"></i>' + escapeHtml( cfg.legend.labelCircle ) + '</span>';
			return div;
		};
		control.addTo( map );
	}

	// Escape any text before putting it into the legend.
	function escapeHtml( s ) {
		return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	// Init every map currently in the DOM (used for the shortcode / plain load).
	function initAll( root ) {
		var scope = root && root.querySelectorAll ? root : document;
		var maps = scope.querySelectorAll( '.ff-members-map[data-ffmap]' );
		Array.prototype.forEach.call( maps, initMap );
	}

	// Front end (shortcode, or Elementor without JS hooks): init on load.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () { initAll( document ); } );
	} else {
		initAll( document );
	}

	// Elementor (editor + live): widgets are injected after load, so init each
	// as it becomes ready.
	window.addEventListener( 'elementor/frontend/init', function () {
		if ( typeof elementorFrontend === 'undefined' || ! elementorFrontend.hooks ) {
			return;
		}
		elementorFrontend.hooks.addAction( 'frontend/element_ready/ff_members_map.default', function ( $scope ) {
			var el = $scope && $scope[ 0 ] ? $scope[ 0 ].querySelector( '.ff-members-map[data-ffmap]' ) : null;
			initMap( el );
		} );
	} );
} )();
