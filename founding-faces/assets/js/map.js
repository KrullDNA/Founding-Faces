/**
 * Founding Faces — the anonymous members map.
 *
 * Draws a soft, semi-transparent dot per member on a pale Positron base map.
 * The data handed in is coordinates and tier only — no names, ids or postcodes.
 * The dots are deliberately non-interactive: nothing is clickable, there are no
 * hover labels, and there is no "members near you". It stays ambient.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var el = document.getElementById( 'ff-members-map' );
		if ( ! el || typeof L === 'undefined' || typeof ffMap === 'undefined' ) {
			return;
		}

		// Centre roughly on Australia to start.
		var map = L.map( el, {
			zoomControl: true,
			attributionControl: true,
			scrollWheelZoom: false // Gentle: don't hijack page scrolling.
		} ).setView( [ -28.2, 133.8 ], 4 );

		// The base map imagery (what the map shows), from the settings tile URL.
		L.tileLayer( ffMap.tileUrl, {
			attribution: ffMap.attribution,
			subdomains: 'abcd',
			maxZoom: 18
		} ).addTo( map );

		var tier35 = ffMap.tiers[ '35' ] || { color: '#2b2d33', size: 8 };
		var tierCircle = ffMap.tiers.circle || { color: '#9aa0a6', size: 6 };

		var bounds = [];

		// One soft dot per member.
		( ffMap.points || [] ).forEach( function ( p ) {
			var lat = p[ 0 ];
			var lng = p[ 1 ];
			var is35 = ( p[ 2 ] === 35 );
			var tier = is35 ? tier35 : tierCircle;

			L.circleMarker( [ lat, lng ], {
				radius: tier.size,
				color: tier.color,
				fillColor: tier.color,
				fillOpacity: 0.45, // Semi-transparent so dense areas glow.
				weight: 0,
				interactive: false // Nothing clickable — ambient only.
			} ).addTo( map );

			bounds.push( [ lat, lng ] );
		} );

		// Frame all the dots if we have any.
		if ( bounds.length > 0 ) {
			map.fitBounds( bounds, { padding: [ 40, 40 ], maxZoom: 7 } );
		}
	} );
} )();
