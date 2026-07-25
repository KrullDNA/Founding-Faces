/**
 * Founding Faces — the anonymous members map.
 *
 * Draws a soft, semi-transparent dot per member on a pale base map. Each map on
 * the page reads its own behaviour and styling options from a data attribute
 * (set by the shortcode or the Elementor widget); the dot data — coordinates
 * and tier only, no names, ids or postcodes — is shared in ffMapData.
 *
 * The dots are deliberately non-interactive: nothing clickable, no hover labels,
 * no "members near you". It stays ambient.
 */
( function () {
	'use strict';

	// Rough bounding box of Australia, used when panning is locked.
	var AU_BOUNDS = [ [ -44.0, 112.0 ], [ -10.0, 154.0 ] ];

	// Initialise one map element from its data-ffmap config.
	function initMap( el ) {
		if ( typeof L === 'undefined' || typeof ffMapData === 'undefined' ) {
			return;
		}

		var cfg;
		try {
			cfg = JSON.parse( el.getAttribute( 'data-ffmap' ) );
		} catch ( e ) {
			return;
		}

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
		var bounds2 = [];

		// One soft dot per member.
		( ffMapData.points || [] ).forEach( function ( p ) {
			var is35 = ( p[ 2 ] === 35 );
			var tier = is35 ? t35 : tc;

			L.circleMarker( [ p[ 0 ], p[ 1 ] ], {
				radius: tier.size,
				fillColor: tier.color,
				fillOpacity: cfg.opacity,
				// Optional border/stroke; otherwise no outline.
				color: stroke.on ? stroke.color : tier.color,
				weight: stroke.on ? stroke.width : 0,
				opacity: stroke.on ? 1 : 0,
				interactive: false // Nothing clickable — ambient only.
			} ).addTo( map );

			bounds2.push( [ p[ 0 ], p[ 1 ] ] );
		} );

		// Frame all the dots if we have any; otherwise use the configured view.
		if ( bounds2.length > 0 ) {
			map.fitBounds( bounds2, { padding: [ 40, 40 ], maxZoom: Math.min( 7, cfg.maxZoom ) } );
		}

		// Optional legend.
		if ( cfg.legend && cfg.legend.on ) {
			addLegend( map, cfg );
		}
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

	document.addEventListener( 'DOMContentLoaded', function () {
		var maps = document.querySelectorAll( '.ff-members-map[data-ffmap]' );
		Array.prototype.forEach.call( maps, initMap );
	} );
} )();
