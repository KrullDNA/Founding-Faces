<?php
/**
 * The anonymous members map.
 *
 * A dot per member, placed from their postcode via a bundled Australian
 * postcode-to-coordinates table — no external API call, and the postcode is
 * the only location the map ever reads. No names, no labels, nothing clickable.
 * It shows the programme is real and national without exposing a single person.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Map
 *
 * Builds the anonymised list of dots on the server (coordinates and tier only)
 * and hands it to Leaflet on the map page. Nothing that could identify a member
 * ever reaches the browser.
 */
class FF_Map {

	// Settings option keys for the map.
	const OPT_TILE_URL         = 'ff_map_tile_url';
	const OPT_TILE_ATTRIBUTION = 'ff_map_tile_attribution';
	const OPT_35_COLOR         = 'ff_map_35_color';
	const OPT_35_SIZE          = 'ff_map_35_size';
	const OPT_CIRCLE_COLOR     = 'ff_map_circle_color';
	const OPT_CIRCLE_SIZE      = 'ff_map_circle_size';

	// Cached postcode lookup table, loaded once per request.
	private static $lookup = null;

	/**
	 * Register the map shortcode.
	 */
	public static function register() {
		add_shortcode( 'ff_members_map', array( __CLASS__, 'shortcode' ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Setting defaults.
	 * The tile source is a single setting so it can be repointed later (e.g. to
	 * a higher-reliability provider) without touching the rest of the build.
	 * -----------------------------------------------------------------------
	 */

	/** @return string The default pale-grey Positron tile URL (no key needed). */
	public static function default_tile_url() {
		return 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png';
	}

	/** @return string The default tile attribution. */
	public static function default_tile_attribution() {
		return '&copy; OpenStreetMap contributors &copy; CARTO';
	}

	/**
	 * Get the map settings, each falling back to its default.
	 *
	 * @return array
	 */
	public static function settings() {
		return array(
			'tile_url'     => get_option( self::OPT_TILE_URL, self::default_tile_url() ),
			'attribution'  => get_option( self::OPT_TILE_ATTRIBUTION, self::default_tile_attribution() ),
			'c35_color'    => get_option( self::OPT_35_COLOR, '#2b2d33' ),
			'c35_size'     => (int) get_option( self::OPT_35_SIZE, 8 ),
			'circle_color' => get_option( self::OPT_CIRCLE_COLOR, '#9aa0a6' ),
			'circle_size'  => (int) get_option( self::OPT_CIRCLE_SIZE, 6 ),
		);
	}

	/*
	 * -----------------------------------------------------------------------
	 * The postcode lookup.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Load the bundled postcode-to-coordinates table (once).
	 *
	 * @return array Map of postcode string => [lat, lng].
	 */
	private static function lookup() {
		if ( null === self::$lookup ) {
			$path = FF_PATH . 'assets/data/au-postcodes.json';
			$json = is_readable( $path ) ? file_get_contents( $path ) : '';
			$data = $json ? json_decode( $json, true ) : array();
			self::$lookup = is_array( $data ) ? $data : array();
		}
		return self::$lookup;
	}

	/**
	 * Look up the coordinates for a postcode.
	 *
	 * @param string $postcode A four-digit postcode.
	 * @return array|null [lat, lng], or null if the postcode isn't in the table.
	 */
	private static function coords_for( $postcode ) {
		$postcode = trim( (string) $postcode );
		$lookup   = self::lookup();
		return isset( $lookup[ $postcode ] ) ? $lookup[ $postcode ] : null;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Building the anonymous dots.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Build the list of dots: coordinates and tier only, nothing identifying.
	 *
	 * Reads each active member's postcode (never their address), converts it to
	 * coordinates via the bundled table, and applies a tiny deterministic jitter
	 * so members who share a postcode spread into a soft glow rather than a
	 * single stacked dot. The output contains no names, ids or postcodes.
	 *
	 * @return array A list of [lat, lng, tier] where tier is 35 or 0.
	 */
	public static function build_points() {
		// Active members only (skip deactivated and test accounts).
		$users = get_users( array(
			'meta_key'   => FF_Members::META_GROUP, // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_compare' => 'EXISTS',
			'fields'     => array( 'ID' ),
		) );

		$points = array();

		foreach ( $users as $user ) {
			$uid = (int) $user->ID;

			if ( get_user_meta( $uid, FF_Members::META_DEACTIVATED, true ) ) {
				continue;
			}
			if ( get_user_meta( $uid, FF_Members::META_IS_TEST, true ) ) {
				continue;
			}

			$postcode = get_user_meta( $uid, FF_Members::META_POSTCODE, true );
			if ( '' === $postcode ) {
				continue;
			}

			$coords = self::coords_for( $postcode );
			if ( ! $coords ) {
				continue;
			}

			// A small, stable jitter seeded from the member id, so a shared
			// postcode reads as a cluster of soft dots, not one hard dot. This
			// is cosmetic only and never reveals a real location.
			list( $jlat, $jlng ) = self::jitter( $uid );

			$is_35 = ( 'the-35' === get_user_meta( $uid, FF_Members::META_GROUP, true ) );

			$points[] = array(
				round( $coords[0] + $jlat, 4 ),
				round( $coords[1] + $jlng, 4 ),
				$is_35 ? 35 : 0,
			);
		}

		return $points;
	}

	/**
	 * A small deterministic jitter in degrees, seeded from an id.
	 *
	 * @param int $seed The member id.
	 * @return array [lat_offset, lng_offset], roughly within +/- 0.03 degrees.
	 */
	private static function jitter( $seed ) {
		// Two independent pseudo-random values from the id, no randomness so the
		// map is stable between loads.
		$a = ( ( $seed * 2654435761 ) % 1000 ) / 1000; // 0..1
		$b = ( ( $seed * 40503 ) % 1000 ) / 1000;       // 0..1
		$lat = ( $a - 0.5 ) * 0.06;
		$lng = ( $b - 0.5 ) * 0.06;
		return array( $lat, $lng );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Rendering.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * The [ff_members_map] shortcode.
	 *
	 * @param array $atts Shortcode attributes (height in px, optional).
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'height' => 520 ), $atts, 'ff_members_map' );

		// The map is part of the members area, so it's members-only too.
		if ( ! FF_Gating::is_member() ) {
			return FF_Display::members_only_notice();
		}

		self::enqueue();

		$height = absint( $atts['height'] );

		return '<div class="ff-map-wrap"><div id="ff-members-map" class="ff-members-map" style="height:' . esc_attr( $height ) . 'px;"></div></div>';
	}

	/**
	 * Enqueue Leaflet (bundled locally) and the map script, only on this page.
	 *
	 * Passes the anonymous points and the style settings to the script. Leaflet
	 * is the one deliberate external library, and it loads solely here.
	 */
	private static function enqueue() {
		wp_enqueue_style( 'founding-faces', FF_URL . 'assets/css/founding-faces.css', array(), FF_VERSION );

		// Leaflet, bundled with the plugin — no CDN, no external JS dependency.
		wp_enqueue_style( 'leaflet', FF_URL . 'assets/vendor/leaflet/leaflet.css', array(), '1.9.4' );
		wp_enqueue_script( 'leaflet', FF_URL . 'assets/vendor/leaflet/leaflet.js', array(), '1.9.4', true );

		wp_enqueue_script( 'ff-map', FF_URL . 'assets/js/map.js', array( 'leaflet' ), FF_VERSION, true );

		$settings = self::settings();

		wp_localize_script( 'ff-map', 'ffMap', array(
			'points'      => self::build_points(),
			'tileUrl'     => $settings['tile_url'],
			'attribution' => $settings['attribution'],
			'tiers'       => array(
				'35'     => array(
					'color' => $settings['c35_color'],
					'size'  => $settings['c35_size'],
				),
				'circle' => array(
					'color' => $settings['circle_color'],
					'size'  => $settings['circle_size'],
				),
			),
		) );
	}
}
