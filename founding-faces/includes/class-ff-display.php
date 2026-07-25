<?php
/**
 * The frontend display layer.
 *
 * This is where product and note data reaches a page. The single most important
 * idea: a note is designed once and rendered automatically. Every note — single
 * or in a list — goes through the same render_note_card() template, so
 * publishing a new note is filling in the fields and hitting publish; it appears
 * on the frontend already styled and on-brand, with no page-building.
 *
 * All components respect the 35-only gate: an unauthorised member never
 * receives gated content, because gated notes are filtered out server-side
 * before any markup is produced.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Display
 *
 * The four display components, delivered as shortcodes so they work on free
 * Elementor (via the Shortcode widget), in the block editor, or in a classic
 * theme. If Elementor Pro is present, a Theme Builder dynamic template can take
 * over the note markup through the 'ff_render_note' filter — either path gives
 * the same publish-and-it-appears result.
 */
class FF_Display {

	/**
	 * Register the display shortcodes.
	 */
	public static function register() {
		add_shortcode( 'ff_note', array( __CLASS__, 'sc_note' ) );
		add_shortcode( 'ff_notes', array( __CLASS__, 'sc_notes' ) );
		add_shortcode( 'ff_product_header', array( __CLASS__, 'sc_product_header' ) );
		add_shortcode( 'ff_home', array( __CLASS__, 'sc_home' ) );
	}

	/**
	 * Enqueue the frontend stylesheet (only when a component is on the page).
	 */
	private static function enqueue() {
		wp_enqueue_style( 'founding-faces', FF_URL . 'assets/css/founding-faces.css', array(), FF_VERSION );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Components.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Single note: [ff_note id="123"].
	 *
	 * Lays out a note through the one template, gated automatically. A member
	 * who isn't allowed to see it gets a quiet "vault" message, never the
	 * content. Records a first-view on the interaction spine.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function sc_note( $atts ) {
		self::enqueue();

		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'ff_note' );
		$id   = absint( $atts['id'] );

		// Everything here is members-only.
		if ( ! FF_Gating::is_member() ) {
			return self::members_only_notice();
		}

		$note = $id ? get_post( $id ) : null;
		if ( ! $note || FF_Post_Types::NOTE_CPT !== $note->post_type || 'publish' !== $note->post_status ) {
			return '<div class="ff-notice">' . esc_html__( 'That note could not be found.', 'founding-faces' ) . '</div>';
		}

		// Gate: a member who can't view a 35-only note sees a message, not content.
		if ( ! FF_Gating::can_view_note( $id ) ) {
			return '<div class="ff-notice ff-vault">' . esc_html__( 'This note is part of The 35 vault.', 'founding-faces' ) . '</div>';
		}

		// Record the first time this member views this note.
		FF_Interactions::log_once( get_current_user_id(), 'note_viewed', $id );

		return '<div class="ff-notes-single">' . self::render_note_card( $note ) . '</div>';
	}

	/**
	 * Notes list by product: [ff_notes product="123" stage="passed" limit="20"].
	 *
	 * The workhorse: a product's notes, newest first, optionally filtered by
	 * stage, with filter chips so a member can jump to "all failed batches" or
	 * "everything in stability testing". Gated notes the member can't see are
	 * never included.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function sc_notes( $atts ) {
		self::enqueue();

		$atts = shortcode_atts(
			array(
				'product' => 0,
				'stage'   => '',
				'limit'   => 50,
				'filters' => 'yes',
			),
			$atts,
			'ff_notes'
		);

		if ( ! FF_Gating::is_member() ) {
			return self::members_only_notice();
		}

		$product_id = absint( $atts['product'] );

		// A stage filter can come from the shortcode or from the URL chip click.
		$stage = sanitize_key( $atts['stage'] );
		if ( isset( $_GET['ff_stage'] ) ) {
			$stage = sanitize_key( wp_unslash( $_GET['ff_stage'] ) );
		}
		if ( '' !== $stage && ! array_key_exists( $stage, FF_Post_Types::note_stages() ) ) {
			$stage = '';
		}

		$notes = self::get_viewable_notes( $product_id, $stage, absint( $atts['limit'] ) );

		$out = '<div class="ff-notes-list">';

		// Optional stage filter chips.
		if ( 'yes' === $atts['filters'] ) {
			$out .= self::stage_filter_chips( $stage );
		}

		if ( empty( $notes ) ) {
			$out .= '<p class="ff-empty-note">' . esc_html__( 'No notes to show here yet.', 'founding-faces' ) . '</p>';
		} else {
			foreach ( $notes as $note ) {
				$out .= self::render_note_card( $note );
			}
		}

		$out .= '</div>';
		return $out;
	}

	/**
	 * Product header: [ff_product_header product="123"].
	 *
	 * The product's name, current stage and where it's up to, sitting above its
	 * notes.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function sc_product_header( $atts ) {
		self::enqueue();

		$atts = shortcode_atts( array( 'product' => 0 ), $atts, 'ff_product_header' );

		if ( ! FF_Gating::is_member() ) {
			return self::members_only_notice();
		}

		$product = absint( $atts['product'] ) ? get_post( absint( $atts['product'] ) ) : null;
		if ( ! $product || FF_Post_Types::PRODUCT_CPT !== $product->post_type ) {
			return '<div class="ff-notice">' . esc_html__( 'That product could not be found.', 'founding-faces' ) . '</div>';
		}

		$stage  = get_post_meta( $product->ID, FF_Post_Types::META_PRODUCT_STAGE, true );
		$status = get_post_meta( $product->ID, FF_Post_Types::META_PRODUCT_STATUS, true );

		$out  = '<div class="ff-product-header">';
		$out .= '<h2 class="ff-product-name">' . esc_html( get_the_title( $product ) ) . '</h2>';
		$meta = array();
		if ( $stage ) {
			$meta[] = self::stage_badge( $stage );
		}
		if ( $status ) {
			$meta[] = '<span class="ff-product-status">' . esc_html( $status ) . '</span>';
		}
		if ( $meta ) {
			$out .= '<div class="ff-product-meta">' . implode( ' ', $meta ) . '</div>';
		}
		if ( trim( $product->post_content ) !== '' ) {
			$out .= '<div class="ff-product-intro">' . wpautop( wp_kses_post( $product->post_content ) ) . '</div>';
		}
		$out .= '</div>';

		return $out;
	}

	/**
	 * Hybrid members home: [ff_home].
	 *
	 * A latest-notes feed across everything at the top, so a returning member
	 * immediately sees what's new, with the list of products beneath to dive
	 * into a single product's full history. Everything is gated.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function sc_home( $atts ) {
		self::enqueue();

		$atts = shortcode_atts( array( 'latest' => 8 ), $atts, 'ff_home' );

		if ( ! FF_Gating::is_member() ) {
			return self::members_only_notice();
		}

		$out = '<div class="ff-home">';

		// Latest notes across all products the member may see.
		$latest = self::get_viewable_notes( 0, '', absint( $atts['latest'] ) );
		$out   .= '<section class="ff-home-latest">';
		$out   .= '<h2 class="ff-home-heading">' . esc_html__( 'Latest notes', 'founding-faces' ) . '</h2>';
		if ( empty( $latest ) ) {
			$out .= '<p class="ff-empty-note">' . esc_html__( 'Nothing new just yet — check back soon.', 'founding-faces' ) . '</p>';
		} else {
			foreach ( $latest as $note ) {
				$out .= self::render_note_card( $note );
			}
		}
		$out .= '</section>';

		// The products list beneath, each with its current stage.
		$out .= '<section class="ff-home-products">';
		$out .= '<h2 class="ff-home-heading">' . esc_html__( 'Products', 'founding-faces' ) . '</h2>';
		$out .= self::products_list_html();
		$out .= '</section>';

		$out .= '</div>';
		return $out;
	}

	/*
	 * -----------------------------------------------------------------------
	 * The one note template.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Render a single note through the one designed-once template.
	 *
	 * Both the single-note and list components call this, so a note looks the
	 * same everywhere and the styling lives in one place. The output is passed
	 * through the 'ff_render_note' filter, which is where an Elementor Pro Theme
	 * Builder template can substitute its own markup if desired.
	 *
	 * @param WP_Post $note The note.
	 * @return string
	 */
	public static function render_note_card( $note ) {
		$stage    = get_post_meta( $note->ID, FF_Post_Types::META_NOTE_STAGE, true );
		$trial    = get_post_meta( $note->ID, FF_Post_Types::META_NOTE_TRIAL, true );
		$gallery  = get_post_meta( $note->ID, FF_Post_Types::META_NOTE_GALLERY, true );
		$audience = get_post_meta( $note->ID, FF_Post_Types::META_NOTE_AUDIENCE, true );
		$date     = self::note_date( $note );

		$out  = '<article class="ff-note">';
		$out .= '<header class="ff-note-head">';
		$out .= '<h3 class="ff-note-title">' . esc_html( get_the_title( $note ) ) . '</h3>';

		$meta = array();
		if ( $stage ) {
			$meta[] = self::stage_badge( $stage );
		}
		if ( '' !== (string) $trial ) {
			$meta[] = '<span class="ff-note-trial">' . sprintf( /* translators: %s is a trial number. */ esc_html__( 'Trial %s', 'founding-faces' ), esc_html( $trial ) ) . '</span>';
		}
		if ( $date ) {
			$meta[] = '<span class="ff-note-date">' . esc_html( $date ) . '</span>';
		}
		if ( 'the-35-only' === $audience ) {
			$meta[] = '<span class="ff-note-vault">' . esc_html__( 'The 35 vault', 'founding-faces' ) . '</span>';
		}
		if ( $meta ) {
			$out .= '<div class="ff-note-meta">' . implode( '', $meta ) . '</div>';
		}
		$out .= '</header>';

		$out .= '<div class="ff-note-body">' . wpautop( wp_kses_post( $note->post_content ) ) . '</div>';

		$out .= self::gallery_html( $gallery );

		$out .= '</article>';

		/**
		 * Filter the rendered HTML for a single note.
		 *
		 * @param string  $out  The default template output.
		 * @param WP_Post $note The note being rendered.
		 */
		return apply_filters( 'ff_render_note', $out, $note );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Helpers.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Get the notes a viewer may see, newest first, optionally by product/stage.
	 *
	 * Runs the gate on every note, so a member never receives one they aren't
	 * allowed to see. Sorted by the note's own date, newest first.
	 *
	 * @param int    $product_id A product id, or 0 for all products.
	 * @param string $stage      A stage key to filter by, or '' for all.
	 * @param int    $limit      Maximum notes to return.
	 * @return WP_Post[]
	 */
	private static function get_viewable_notes( $product_id, $stage, $limit ) {
		$args = array(
			'post_type'      => FF_Post_Types::NOTE_CPT,
			'post_status'    => 'publish',
			// Over-fetch a little because the gate may remove some, then trim.
			'posts_per_page' => 200,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$meta_query = array();
		if ( $product_id ) {
			$meta_query[] = array(
				'key'   => FF_Post_Types::META_NOTE_PRODUCT,
				'value' => $product_id,
			);
		}
		if ( '' !== $stage ) {
			$meta_query[] = array(
				'key'   => FF_Post_Types::META_NOTE_STAGE,
				'value' => $stage,
			);
		}
		if ( $meta_query ) {
			$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery
		}

		$notes = get_posts( $args );

		// Apply the gate.
		$notes = array_values( array_filter( $notes, function ( $note ) {
			return FF_Gating::can_view_note( $note->ID );
		} ) );

		// Sort by the note's own date (falling back to publish date), newest first.
		usort( $notes, function ( $a, $b ) {
			return strcmp( self::note_sort_key( $b ), self::note_sort_key( $a ) );
		} );

		return array_slice( $notes, 0, max( 1, $limit ) );
	}

	/**
	 * The sortable date key for a note: its own date, or its publish date.
	 *
	 * @param WP_Post $note The note.
	 * @return string A YYYY-MM-DD string.
	 */
	private static function note_sort_key( $note ) {
		$date = get_post_meta( $note->ID, FF_Post_Types::META_NOTE_DATE, true );
		return $date ? $date : get_post_time( 'Y-m-d', false, $note );
	}

	/**
	 * The display date for a note, formatted to the site's date format.
	 *
	 * @param WP_Post $note The note.
	 * @return string
	 */
	private static function note_date( $note ) {
		$date = get_post_meta( $note->ID, FF_Post_Types::META_NOTE_DATE, true );
		if ( $date ) {
			return mysql2date( get_option( 'date_format' ), $date );
		}
		return get_the_date( '', $note );
	}

	/**
	 * The stage badge markup for a stage key.
	 *
	 * @param string $stage The stage key.
	 * @return string
	 */
	private static function stage_badge( $stage ) {
		$stages = FF_Post_Types::note_stages();
		$label  = isset( $stages[ $stage ] ) ? $stages[ $stage ] : $stage;
		return '<span class="ff-badge ff-stage ff-stage--' . esc_attr( $stage ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * The stage filter chips for a notes list.
	 *
	 * @param string $current The currently selected stage, or ''.
	 * @return string
	 */
	private static function stage_filter_chips( $current ) {
		$base = remove_query_arg( 'ff_stage' );

		$chips  = '<div class="ff-stage-filters">';
		$all_cls = ( '' === $current ) ? 'is-active' : '';
		$chips  .= '<a class="ff-chip ' . esc_attr( $all_cls ) . '" href="' . esc_url( $base ) . '">' . esc_html__( 'All', 'founding-faces' ) . '</a>';

		foreach ( FF_Post_Types::note_stages() as $key => $label ) {
			$url = add_query_arg( 'ff_stage', $key, $base );
			$cls = ( $current === $key ) ? 'is-active' : '';
			$chips .= '<a class="ff-chip ' . esc_attr( $cls ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		$chips .= '</div>';
		return $chips;
	}

	/**
	 * The image gallery markup for a note's gallery CSV.
	 *
	 * @param string $gallery A CSV of attachment ids.
	 * @return string
	 */
	private static function gallery_html( $gallery ) {
		$ids = array_filter( array_map( 'absint', explode( ',', (string) $gallery ) ) );
		if ( empty( $ids ) ) {
			return '';
		}

		$out = '<div class="ff-note-gallery">';
		foreach ( $ids as $id ) {
			$img = wp_get_attachment_image( $id, 'medium', false, array( 'class' => 'ff-gallery-img', 'loading' => 'lazy' ) );
			if ( $img ) {
				$full = wp_get_attachment_image_url( $id, 'full' );
				$out .= '<a class="ff-gallery-item" href="' . esc_url( $full ) . '" target="_blank" rel="noopener">' . $img . '</a>';
			}
		}
		$out .= '</div>';
		return $out;
	}

	/**
	 * The products list markup for the home screen.
	 *
	 * @return string
	 */
	private static function products_list_html() {
		$products = get_posts( array(
			'post_type'      => FF_Post_Types::PRODUCT_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		if ( empty( $products ) ) {
			return '<p class="ff-empty-note">' . esc_html__( 'No products yet.', 'founding-faces' ) . '</p>';
		}

		$out = '<ul class="ff-products">';
		foreach ( $products as $product ) {
			$stage = get_post_meta( $product->ID, FF_Post_Types::META_PRODUCT_STAGE, true );
			$out  .= '<li class="ff-product-item">';
			$out  .= '<span class="ff-product-item-name">' . esc_html( get_the_title( $product ) ) . '</span>';
			if ( $stage ) {
				$out .= ' ' . self::stage_badge( $stage );
			}
			$out .= '</li>';
		}
		$out .= '</ul>';
		return $out;
	}

	/**
	 * The "members only" notice shown to non-members.
	 *
	 * Public so other components (like the personal-history page) can show the
	 * same consistent prompt.
	 *
	 * @return string
	 */
	public static function members_only_notice() {
		return '<div class="ff-notice ff-members-only">'
			. esc_html__( 'This area is for Founding Faces members.', 'founding-faces' )
			. ' <a href="' . esc_url( wp_login_url( self::current_url() ) ) . '">' . esc_html__( 'Log in', 'founding-faces' ) . '</a>'
			. '</div>';
	}

	/**
	 * The URL of the current page, for a post-login redirect.
	 *
	 * @return string
	 */
	private static function current_url() {
		global $wp;
		return home_url( add_query_arg( array(), $wp->request ) );
	}
}
