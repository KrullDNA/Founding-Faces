<?php
/**
 * The anonymous members map.
 *
 * A dot per member, placed from their postcode via a bundled Australian
 * postcode-to-coordinates table — no external API call, and the postcode is
 * the only location the map ever reads. No names, no labels, nothing clickable.
 *
 * Delivered two ways over one shared renderer: the [ff_members_map] shortcode
 * (the original, kept as a fallback) and a native Elementor Atomic widget that
 * exposes the map's behaviour and styling as panel controls. Both call
 * FF_Map::render_map(); the widget only gathers its controls into the same
 * options array.
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
 * and hands it, plus a per-instance options set, to Leaflet on the map page.
 * Nothing that could identify a member ever reaches the browser.
 */
class FF_Map {

	// Settings option keys for the map (plugin-level defaults).
	const OPT_TILE_URL         = 'ff_map_tile_url';
	const OPT_TILE_ATTRIBUTION = 'ff_map_tile_attribution';
	const OPT_35_COLOR         = 'ff_map_35_color';
	const OPT_35_SIZE          = 'ff_map_35_size';
	const OPT_CIRCLE_COLOR     = 'ff_map_circle_color';
	const OPT_CIRCLE_SIZE      = 'ff_map_circle_size';

	// The geographic centre of Australia, the default map centre.
	const AU_CENTER_LAT = -25.2744;
	const AU_CENTER_LNG = 133.7751;

	// Cached postcode lookup table, loaded once per request.
	private static $lookup = null;

	// Counter so each map instance on a page gets a unique id.
	private static $instance = 0;

	/**
	 * Register the shortcode, the Elementor widget, and the asset handles.
	 */
	public static function register() {
		add_shortcode( 'ff_members_map', array( __CLASS__, 'shortcode' ) );

		// Register the asset handles so the widget can declare them as
		// dependencies (Elementor then loads them in the editor too).
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'elementor/frontend/after_register_scripts', array( __CLASS__, 'register_assets' ) );

		// The Elementor widget (harmless if Elementor isn't installed).
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widget' ) );
	}

	/**
	 * Register the widget with Elementor.
	 *
	 * @param object $widgets_manager Elementor's widgets manager.
	 */
	public static function register_widget( $widgets_manager ) {
		require_once FF_PATH . 'includes/class-ff-map-widget.php';
		$widgets_manager->register( new FF_Map_Widget() );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Setting defaults (plugin-level).
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
	 * Get the plugin-level map settings, each falling back to its default.
	 *
	 * @return array
	 */
	public static function settings() {
		// Fall back to the default if the stored value is empty OR looks broken
		// (missing the {z}/{x}/{y} placeholders — e.g. mangled by an old save).
		$tile = trim( (string) get_option( self::OPT_TILE_URL, '' ) );
		if ( '' === $tile || false === strpos( $tile, '{z}' ) || false === strpos( $tile, '{x}' ) || false === strpos( $tile, '{y}' ) ) {
			$tile = self::default_tile_url();
		}
		$attribution = get_option( self::OPT_TILE_ATTRIBUTION, self::default_tile_attribution() );
		if ( '' === trim( (string) $attribution ) ) {
			$attribution = self::default_tile_attribution();
		}

		return array(
			'tile_url'     => $tile,
			'attribution'  => $attribution,
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
	/**
	 * The points to draw, standing in samples while designing.
	 *
	 * With no approved members yet — or none with a postcode — the map would be
	 * an empty grey rectangle in the Elementor editor, leaving the dot colour,
	 * size and opacity controls with nothing to preview. Sample dots across the
	 * Australian capitals fill that gap; they never appear on the live site.
	 *
	 * @return array
	 */
	public static function points_for_render() {
		$points = self::build_points();

		if ( ! empty( $points ) || ! FF_History::is_editor() ) {
			return $points;
		}

		// Roughly the capitals, so the spread looks like a real membership.
		$capitals = array(
			array( -33.87, 151.21 ), array( -37.81, 144.96 ), array( -27.47, 153.03 ),
			array( -31.95, 115.86 ), array( -34.93, 138.60 ), array( -42.88, 147.33 ),
			array( -35.28, 149.13 ), array( -12.46, 130.84 ), array( -33.43, 151.34 ),
			array( -38.15, 144.36 ), array( -28.00, 153.43 ), array( -32.93, 151.78 ),
		);

		// Same positional shape as build_points(): [lat, lng, 35 or 0].
		$sample = array();
		foreach ( $capitals as $i => $pair ) {
			$sample[] = array(
				$pair[0],
				$pair[1],
				// A realistic mix: roughly a third in The 35.
				( 0 === $i % 3 ) ? 35 : 0,
			);
		}

		return $sample;
	}

	public static function build_points() {
		$users = get_users( array(
			'meta_key'     => FF_Members::META_GROUP, // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_compare' => 'EXISTS',
			'fields'       => array( 'ID' ),
		) );

		$points = array();

		foreach ( $users as $user ) {
			$uid = (int) $user->ID;

			// Deactivated members drop off the map. Test members DO appear (they
			// exist to exercise the whole flow, including the map, and are
			// cleared by the guarded reset before launch); they are only kept
			// out of the external email sync.
			if ( get_user_meta( $uid, FF_Members::META_DEACTIVATED, true ) ) {
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
		$a = ( ( $seed * 2654435761 ) % 1000 ) / 1000;
		$b = ( ( $seed * 40503 ) % 1000 ) / 1000;
		return array( ( $a - 0.5 ) * 0.06, ( $b - 0.5 ) * 0.06 );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Rendering.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * The [ff_members_map] shortcode (kept as a fallback).
	 *
	 * @param array $atts Shortcode attributes (height in px, optional).
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'height' => 520 ), $atts, 'ff_members_map' );
		return self::render_map( array( 'height' => absint( $atts['height'] ) ) );
	}

	/**
	 * Render one map instance from an options array.
	 *
	 * Shared by the shortcode and the Elementor widget. Any option not supplied
	 * falls back to a sensible default (the plugin-level settings for tiles and
	 * tier colours, the centre of Australia for the view). The behaviour options
	 * are standard Leaflet options passed straight through to the map.
	 *
	 * @param array $args Options (see the defaults below).
	 * @return string
	 */
	public static function render_map( $args = array() ) {
		$s = self::settings();

		$defaults = array(
			'center'       => array( self::AU_CENTER_LAT, self::AU_CENTER_LNG ),
			'zoom'         => 4,
			'min_zoom'     => 3,
			'max_zoom'     => 12,
			'scroll_zoom'  => false,
			'dragging'     => true,
			'lock_bounds'  => false,
			'zoom_control' => true,
			'height'       => 520,
			'tile_url'     => $s['tile_url'],
			'attribution'  => $s['attribution'],
			'tiers'        => array(
				'35'     => array( 'color' => $s['c35_color'], 'size' => $s['c35_size'] ),
				'circle' => array( 'color' => $s['circle_color'], 'size' => $s['circle_size'] ),
			),
			'opacity'      => 0.45,
			'stroke'       => array( 'on' => false, 'color' => '#ffffff', 'width' => 1 ),
			'legend'       => array(
				'on'           => false,
				'position'     => 'bottomright',
				'label_35'     => __( 'The 35', 'founding-faces' ),
				'label_circle' => __( 'The Circle', 'founding-faces' ),
			),
		);

		$args = wp_parse_args( $args, $defaults );

		// The map is fully anonymous — only coordinates and tier, no names — so
		// it is safe to show to anyone, and it is not gated here. Restrict where
		// it appears, if you want to, with the Elementor "Show to" control or the
		// page-level access setting.
		self::enqueue_assets();

		self::$instance++;
		$id     = 'ff-members-map-' . self::$instance;
		$height = absint( $args['height'] );

		// The config handed to Leaflet. Points are coordinates and tier only —
		// no names, ids or postcodes. They travel in this instance's own data
		// attribute (rather than a shared global) so the map initialises the
		// same way on the front end and inside the Elementor editor.
		$config = array(
			'points'      => self::points_for_render(),
			'center'      => array( (float) $args['center'][0], (float) $args['center'][1] ),
			'zoom'        => (int) $args['zoom'],
			'minZoom'     => (int) $args['min_zoom'],
			'maxZoom'     => (int) $args['max_zoom'],
			'scrollZoom'  => (bool) $args['scroll_zoom'],
			'dragging'    => (bool) $args['dragging'],
			'lockBounds'  => (bool) $args['lock_bounds'],
			'zoomControl' => (bool) $args['zoom_control'],
			'tileUrl'     => $args['tile_url'],
			'attribution' => $args['attribution'],
			'tiers'       => array(
				'35'     => array( 'color' => $args['tiers']['35']['color'], 'size' => (int) $args['tiers']['35']['size'] ),
				'circle' => array( 'color' => $args['tiers']['circle']['color'], 'size' => (int) $args['tiers']['circle']['size'] ),
			),
			'opacity'     => (float) $args['opacity'],
			'stroke'      => array(
				'on'    => (bool) $args['stroke']['on'],
				'color' => $args['stroke']['color'],
				'width' => (int) $args['stroke']['width'],
			),
			'legend'      => array(
				'on'          => (bool) $args['legend']['on'],
				'position'    => $args['legend']['position'],
				// Filtered here rather than escaped in the browser, so a legend
				// label can carry a bold word like every other field can.
				'label35'     => FF_Text::inline( $args['legend']['label_35'] ),
				'labelCircle' => FF_Text::inline( $args['legend']['label_circle'] ),
			),
		);

		return '<div id="' . esc_attr( $id ) . '" class="ff-members-map" data-ffmap="'
			. esc_attr( wp_json_encode( $config ) ) . '" style="height:' . $height . 'px;"></div>';
	}

	/**
	 * Register the map asset handles (so the widget can depend on them).
	 */
	public static function register_assets() {
		if ( ! wp_style_is( 'leaflet', 'registered' ) ) {
			wp_register_style( 'leaflet', FF_URL . 'assets/vendor/leaflet/leaflet.css', array(), '1.9.4' );
		}
		if ( ! wp_style_is( 'founding-faces', 'registered' ) ) {
			wp_register_style( 'founding-faces', FF_URL . 'assets/css/founding-faces.css', array(), FF_VERSION );
		}
		if ( ! wp_script_is( 'leaflet', 'registered' ) ) {
			wp_register_script( 'leaflet', FF_URL . 'assets/vendor/leaflet/leaflet.js', array(), '1.9.4', true );
		}
		if ( ! wp_script_is( 'ff-map', 'registered' ) ) {
			wp_register_script( 'ff-map', FF_URL . 'assets/js/map.js', array( 'leaflet' ), FF_VERSION, true );
		}
	}

	/**
	 * Enqueue Leaflet (bundled locally) and the map script — only when a map is
	 * actually rendered, so nothing loads on pages without one.
	 *
	 * Each map's data (points and options) travels in its own data attribute, so
	 * there's nothing to localise here.
	 */
	private static function enqueue_assets() {
		self::register_assets();

		wp_enqueue_style( 'founding-faces' );
		wp_enqueue_style( 'leaflet' );
		wp_enqueue_script( 'leaflet' );
		wp_enqueue_script( 'ff-map' );
	}

	/**
	 * Whether we're rendering inside the Elementor builder or its preview.
	 *
	 * @return bool
	 */
	private static function is_builder() {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return false;
		}
		$p    = \Elementor\Plugin::$instance;
		$edit = isset( $p->editor ) && $p->editor->is_edit_mode();
		$prev = isset( $p->preview ) && method_exists( $p->preview, 'is_preview_mode' ) && $p->preview->is_preview_mode();
		return $edit || $prev;
	}
}
