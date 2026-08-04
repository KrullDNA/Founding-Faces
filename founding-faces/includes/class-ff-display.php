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
	 * Register the display shortcodes and the Elementor widgets.
	 */
	public static function register() {
		add_shortcode( 'ff_note', array( __CLASS__, 'sc_note' ) );
		add_shortcode( 'ff_notes', array( __CLASS__, 'sc_notes' ) );
		add_shortcode( 'ff_notes_archive', array( __CLASS__, 'sc_notes_archive' ) );
		add_shortcode( 'ff_product_header', array( __CLASS__, 'sc_product_header' ) );
		add_shortcode( 'ff_home', array( __CLASS__, 'sc_home' ) );
		add_shortcode( 'ff_note_gallery', array( __CLASS__, 'sc_note_gallery' ) );

		// A note is read when its own page is opened, whatever drew that page:
		// the Single Note widget, an Elementor template of dynamic tags, or a
		// theme template. Priority 20 so the gate (priority 10) has already had
		// its say — a viewer who is redirected away never counts as having read.
		add_action( 'template_redirect', array( __CLASS__, 'record_single_note_view' ), 20 );

		// The slider script, registered on both the front end and inside the
		// Elementor editor (which loads its scripts through its own hook).
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_slider_assets' ) );
		add_action( 'elementor/frontend/after_register_scripts', array( __CLASS__, 'register_slider_assets' ) );

		// Native Elementor widgets that wrap the same components (harmless if
		// Elementor isn't installed).
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widgets' ) );
	}

	/**
	 * Register the display widgets with Elementor.
	 *
	 * @param object $widgets_manager Elementor's widgets manager.
	 */
	public static function register_widgets( $widgets_manager ) {
		require_once FF_PATH . 'includes/class-ff-display-widgets.php';
		$widgets_manager->register( new FF_Notes_Widget() );
		$widgets_manager->register( new FF_Notes_Archive_Widget() );
		$widgets_manager->register( new FF_Note_Widget() );
		$widgets_manager->register( new FF_Product_Header_Widget() );
		$widgets_manager->register( new FF_Home_Widget() );

		require_once FF_PATH . 'includes/class-ff-note-gallery-widget.php';
		$widgets_manager->register( new FF_Note_Gallery_Widget() );
	}

	/**
	 * Record that this member has read the note whose page they are on.
	 *
	 * Kept out of the renderers deliberately. sc_note() used to be the only
	 * place a view was recorded, which was right when the only way to read a
	 * note was through that shortcode — but a note has had its own URL since
	 * 1.0.9, and a Single Note template built from dynamic tags never calls it.
	 * The request itself is the honest signal, so that is what is listened to.
	 *
	 * @return void
	 */
	public static function record_single_note_view() {
		if ( is_admin() || ! is_singular( FF_Post_Types::NOTE_CPT ) ) {
			return;
		}

		$member_id = get_current_user_id();
		if ( ! $member_id ) {
			return;
		}

		// Designing the template is not reading the note, so the Elementor
		// preview iframe doesn't mark Nick's own record.
		if ( FF_History::is_editor() ) {
			return;
		}

		$note_id = get_queried_object_id();
		if ( ! $note_id || ! FF_Gating::can_view_note( $note_id ) ) {
			return;
		}

		FF_Interactions::log_once( $member_id, 'note_viewed', $note_id );
	}

	/**
	 * Products as id => title, for a widget dropdown.
	 *
	 * @param bool $with_placeholder Whether to prepend a "choose" option.
	 * @return array
	 */
	public static function product_choices( $with_placeholder = true ) {
		$choices  = $with_placeholder ? array( 0 => __( '— Select a product —', 'founding-faces' ) ) : array();
		$products = get_posts( array(
			'post_type'      => FF_Post_Types::PRODUCT_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
		foreach ( $products as $product ) {
			$choices[ $product->ID ] = $product->post_title ? $product->post_title : sprintf( __( 'Product #%d', 'founding-faces' ), $product->ID );
		}
		return $choices;
	}

	/**
	 * Notes as id => "Product — Title", for a widget dropdown.
	 *
	 * @return array
	 */
	public static function note_choices() {
		$choices = array( 0 => __( '— Select a note —', 'founding-faces' ) );
		$notes   = get_posts( array(
			'post_type'      => FF_Post_Types::NOTE_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );
		foreach ( $notes as $note ) {
			$product_id = (int) get_post_meta( $note->ID, FF_Post_Types::META_NOTE_PRODUCT, true );
			$prefix     = $product_id ? ( get_the_title( $product_id ) . ' — ' ) : '';
			$choices[ $note->ID ] = $prefix . ( $note->post_title ? $note->post_title : sprintf( __( 'Note #%d', 'founding-faces' ), $note->ID ) );
		}
		return $choices;
	}

	/**
	 * The stage choices for a widget dropdown (with an "all" option).
	 *
	 * @return array
	 */
	public static function stage_choices() {
		return array( '' => __( 'All stages', 'founding-faces' ) ) + FF_Post_Types::note_stages();
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

		$atts = shortcode_atts( array( 'id' => 0, 'listing' => 0 ), $atts, 'ff_note' );
		$id   = absint( $atts['id'] );

		// Everything here is members-only.
		if ( ! FF_Gating::can_view_members_area() ) {
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

		// A JetEngine listing template takes over the layout when chosen; if it
		// returns nothing (JetEngine gone, listing deleted) we fall back to the
		// built-in card, so the note is never simply missing.
		$listing = absint( $atts['listing'] );
		if ( $listing ) {
			$html = FF_JetEngine::render_single( $listing, $note );
			if ( '' !== trim( $html ) ) {
				return '<div class="ff-notes-single ff-notes-single--jet">' . $html . '</div>';
			}
		}

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
				'product'       => 0,
				'stage'         => '',
				'limit'         => 50,
				'filters'       => 'yes',
				'view_all_text' => '',
				'view_all_url'  => '',
			),
			$atts,
			'ff_notes'
		);

		if ( ! FF_Gating::can_view_members_area() ) {
			return self::members_only_notice();
		}

		$product_id = self::product_context_id( $atts['product'] );

		// Asked to follow the page's product and there isn't one: show the empty
		// state rather than quietly listing every note there is.
		if ( 'auto' === $atts['product'] && ! $product_id ) {
			return '<div class="ff-notes-list"><p class="ff-empty-note">' . esc_html__( 'No notes to show here yet.', 'founding-faces' ) . '</p></div>';
		}

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
			$out .= '<div class="ff-notes-cards">';
			foreach ( $notes as $note ) {
				$out .= self::render_note_card( $note );
			}
			$out .= '</div>';
			// Optional "view all" link, e.g. on a hub page's latest-notes block.
			if ( '' !== trim( (string) $atts['view_all_url'] ) ) {
				$label = '' !== trim( (string) $atts['view_all_text'] ) ? $atts['view_all_text'] : __( 'View all notes', 'founding-faces' );
				$out  .= '<p class="ff-notes-viewall"><a class="ff-notes-viewall-link" href="' . esc_url( $atts['view_all_url'] ) . '">' . FF_Text::inline( $label ) . '</a></p>';
			}
		}

		$out .= '</div>';
		return $out;
	}

	/**
	 * Filterable notes archive: [ff_notes_archive limit="30"].
	 *
	 * For the dedicated notes page: a filter bar (product, stage/type and a
	 * newest/oldest sort) driving a gated list of every note the member may see.
	 * The filters live in the URL, so a chosen view can be bookmarked or shared.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function sc_notes_archive( $atts ) {
		self::enqueue();

		$atts = shortcode_atts(
			array(
				'limit'          => 30,
				'show_product'   => 'yes',
				'show_stage'     => 'yes',
				'show_sort'      => 'yes',
			),
			$atts,
			'ff_notes_archive'
		);

		if ( ! FF_Gating::can_view_members_area() ) {
			return self::members_only_notice();
		}

		// Read the current filters from the URL, validating each.
		$product = isset( $_GET['ff_product'] ) ? absint( wp_unslash( $_GET['ff_product'] ) ) : 0;
		if ( $product && FF_Post_Types::PRODUCT_CPT !== get_post_type( $product ) ) {
			$product = 0;
		}
		$stage = isset( $_GET['ff_stage'] ) ? sanitize_key( wp_unslash( $_GET['ff_stage'] ) ) : '';
		if ( '' !== $stage && ! array_key_exists( $stage, FF_Post_Types::note_stages() ) ) {
			$stage = '';
		}
		$order = ( isset( $_GET['ff_order'] ) && 'oldest' === sanitize_key( wp_unslash( $_GET['ff_order'] ) ) ) ? 'ASC' : 'DESC';

		$notes = self::get_viewable_notes( $product, $stage, absint( $atts['limit'] ), $order );

		$out  = '<div class="ff-notes-archive ff-notes-list">';
		$out .= self::archive_filter_bar( $atts, $product, $stage, $order );

		if ( empty( $notes ) ) {
			$out .= '<p class="ff-empty-note">' . esc_html__( 'No notes match these filters.', 'founding-faces' ) . '</p>';
		} else {
			$out .= '<div class="ff-notes-cards">';
			foreach ( $notes as $note ) {
				$out .= self::render_note_card( $note );
			}
			$out .= '</div>';
		}
		$out .= '</div>';

		return $out;
	}

	/**
	 * The filter bar for the notes archive: product, stage and sort selects.
	 *
	 * A single GET form so all three filters combine in the URL. Preserves other
	 * query args as hidden fields.
	 *
	 * @param array  $atts    Which filters to show.
	 * @param int    $product The current product filter.
	 * @param string $stage   The current stage filter.
	 * @param string $order   'ASC' or 'DESC'.
	 * @return string
	 */
	private static function archive_filter_bar( $atts, $product, $stage, $order ) {
		$out  = '<form class="ff-notes-filters" method="get">';

		if ( 'yes' === $atts['show_product'] ) {
			$out .= '<label class="ff-filter"><span>' . esc_html__( 'Product', 'founding-faces' ) . '</span><select name="ff_product">';
			foreach ( self::product_choices_all() as $id => $label ) {
				$out .= '<option value="' . esc_attr( $id ) . '" ' . selected( $product, $id, false ) . '>' . esc_html( $label ) . '</option>';
			}
			$out .= '</select></label>';
		}

		if ( 'yes' === $atts['show_stage'] ) {
			$out .= '<label class="ff-filter"><span>' . esc_html__( 'Type', 'founding-faces' ) . '</span><select name="ff_stage">';
			foreach ( self::stage_choices() as $key => $label ) {
				$out .= '<option value="' . esc_attr( $key ) . '" ' . selected( $stage, $key, false ) . '>' . esc_html( $label ) . '</option>';
			}
			$out .= '</select></label>';
		}

		if ( 'yes' === $atts['show_sort'] ) {
			$out .= '<label class="ff-filter"><span>' . esc_html__( 'Sort', 'founding-faces' ) . '</span><select name="ff_order">';
			$out .= '<option value="newest" ' . selected( $order, 'DESC', false ) . '>' . esc_html__( 'Newest first', 'founding-faces' ) . '</option>';
			$out .= '<option value="oldest" ' . selected( $order, 'ASC', false ) . '>' . esc_html__( 'Oldest first', 'founding-faces' ) . '</option>';
			$out .= '</select></label>';
		}

		// Keep any unrelated query args on submit (but not our own filter keys).
		foreach ( $_GET as $key => $val ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$key = sanitize_key( $key );
			if ( in_array( $key, array( 'ff_product', 'ff_stage', 'ff_order' ), true ) || is_array( $val ) ) {
				continue;
			}
			$out .= '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( wp_unslash( $val ) ) . '" />';
		}

		$out .= '<button type="submit" class="ff-filter-apply">' . esc_html__( 'Apply', 'founding-faces' ) . '</button>';
		$out .= '</form>';
		return $out;
	}

	/**
	 * Products as id => title with an "All products" first option.
	 *
	 * @return array
	 */
	public static function product_choices_all() {
		$choices  = array( 0 => __( 'All products', 'founding-faces' ) );
		$products = get_posts( array(
			'post_type'      => FF_Post_Types::PRODUCT_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
		foreach ( $products as $product ) {
			$choices[ $product->ID ] = $product->post_title ? $product->post_title : sprintf( __( 'Product #%d', 'founding-faces' ), $product->ID );
		}
		return $choices;
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

		if ( ! FF_Gating::can_view_members_area() ) {
			return self::members_only_notice();
		}

		$product_id = self::product_context_id( $atts['product'] );
		$product    = $product_id ? get_post( $product_id ) : null;
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

		$atts = shortcode_atts(
			array(
				'latest'           => 8,
				'latest_heading'   => '',
				'products_heading' => '',
				'show_latest'      => 'yes',
				'show_products'    => 'yes',
				// A JetEngine listing template per section, each optional.
				'latest_listing'   => 0,
				'products_listing' => 0,
				'latest_columns'   => 1,
				'products_columns' => 1,
			),
			$atts,
			'ff_home'
		);

		if ( ! FF_Gating::can_view_members_area() ) {
			return self::members_only_notice();
		}

		$out = '<div class="ff-home">';

		// Latest notes across all products the member may see.
		if ( 'no' !== $atts['show_latest'] ) {
			$heading = '' !== trim( (string) $atts['latest_heading'] ) ? $atts['latest_heading'] : __( 'Latest notes', 'founding-faces' );
			$out    .= '<section class="ff-home-latest">';
			$out    .= '<h2 class="ff-home-heading ff-home-heading--latest">' . FF_Text::inline( $heading ) . '</h2>';

			$jet = FF_JetEngine::render_grid( $atts['latest_listing'], absint( $atts['latest'] ), $atts['latest_columns'] );
			if ( '' !== trim( $jet ) ) {
				$out .= $jet;
			} else {
				$latest = self::get_viewable_notes( 0, '', absint( $atts['latest'] ) );
				if ( empty( $latest ) ) {
					$out .= '<p class="ff-empty-note">' . esc_html__( 'Nothing new just yet — check back soon.', 'founding-faces' ) . '</p>';
				} else {
					$out .= '<div class="ff-notes-cards">';
					foreach ( $latest as $note ) {
						$out .= self::render_note_card( $note );
					}
					$out .= '</div>';
				}
			}
			$out .= '</section>';
		}

		// The products list beneath, each with its current stage.
		if ( 'no' !== $atts['show_products'] ) {
			$heading = '' !== trim( (string) $atts['products_heading'] ) ? $atts['products_heading'] : __( 'Products', 'founding-faces' );
			$out    .= '<section class="ff-home-products">';
			$out    .= '<h2 class="ff-home-heading ff-home-heading--products">' . FF_Text::inline( $heading ) . '</h2>';

			$jet = FF_JetEngine::render_grid( $atts['products_listing'], 50, $atts['products_columns'] );
			$out .= ( '' !== trim( $jet ) ) ? $jet : self::products_list_html();
			$out .= '</section>';
		}

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
			$meta[] = '<span class="ff-note-trial">' . sprintf( /* translators: %s is a version number. */ esc_html__( 'Version %s', 'founding-faces' ), esc_html( $trial ) ) . '</span>';
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
	 * A note counts as read only when the member opens its own page, not when a
	 * card appears in a list. Marking on list render looked reasonable, but it
	 * emptied the unread list the moment a member landed on the hub — the feed
	 * there would have silently marked everything read before they had chosen to
	 * read anything. sc_note() records the view instead, so "unread" keeps
	 * meaning something and the count bubble stays honest.
	 */

	/*
	 * -----------------------------------------------------------------------
	 * Editor dummy content.
	 *
	 * In the Elementor editor a widget may have nothing real to show yet — no
	 * note chosen, no products created, or the designer isn't a member — which
	 * would leave nothing to style. These build representative markup using the
	 * exact same classes as the live output, so every element can be styled up
	 * front and looks identical once real content arrives.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * A sample note card, in the real template's markup.
	 *
	 * @param string $title The sample title.
	 * @param string $stage The sample stage key.
	 * @param string $trial The sample version number.
	 * @param bool   $vault Whether to show the "The 35 vault" chip.
	 * @return string
	 */
	public static function sample_note_card( $title = '', $stage = 'stability_testing', $trial = '4', $vault = false ) {
		$title = '' !== $title ? $title : __( 'Sample note — batch reformulation', 'founding-faces' );

		$out  = '<article class="ff-note">';
		$out .= '<header class="ff-note-head">';
		$out .= '<h3 class="ff-note-title">' . esc_html( $title ) . '</h3>';
		$out .= '<div class="ff-note-meta">';
		$out .= self::stage_badge( $stage );
		$out .= '<span class="ff-note-trial">' . sprintf( /* translators: %s is a version number. */ esc_html__( 'Version %s', 'founding-faces' ), esc_html( $trial ) ) . '</span>';
		$out .= '<span class="ff-note-date">' . esc_html( date_i18n( get_option( 'date_format' ) ) ) . '</span>';
		if ( $vault ) {
			$out .= '<span class="ff-note-vault">' . esc_html__( 'The 35 vault', 'founding-faces' ) . '</span>';
		}
		$out .= '</div></header>';
		$out .= '<div class="ff-note-body"><p>' . esc_html__( 'This is sample text so you can style the note body before a real note is chosen. It uses exactly the same markup as a published note, so what you design here is what members will see.', 'founding-faces' ) . '</p></div>';
		$out .= '</article>';

		return $out;
	}

	/**
	 * A grid of sample note cards.
	 *
	 * @param int $count How many cards.
	 * @return string
	 */
	public static function sample_note_cards( $count = 3 ) {
		$samples = array(
			array( __( 'Sample note — batch reformulation', 'founding-faces' ), 'stability_testing', '4', false ),
			array( __( 'Sample note — actives at 2%', 'founding-faces' ), 'passed', '3', true ),
			array( __( 'Sample note — texture test', 'founding-faces' ), 'in_development', '2', false ),
			array( __( 'Sample note — preservative swap', 'founding-faces' ), 'failed', '1', false ),
		);

		$out = '<div class="ff-notes-cards">';
		for ( $i = 0; $i < max( 1, (int) $count ); $i++ ) {
			$s    = $samples[ $i % count( $samples ) ];
			$out .= self::sample_note_card( $s[0], $s[1], $s[2], $s[3] );
		}
		$out .= '</div>';
		return $out;
	}

	/**
	 * A sample products list, in the real markup.
	 *
	 * @return string
	 */
	public static function sample_products_list() {
		$rows = array(
			array( __( 'Sample product — Renewal Serum', 'founding-faces' ), 'stability_testing' ),
			array( __( 'Sample product — Barrier Cream', 'founding-faces' ), 'passed' ),
			array( __( 'Sample product — Gentle Cleanser', 'founding-faces' ), 'in_development' ),
		);

		$out = '<ul class="ff-products">';
		foreach ( $rows as $row ) {
			$out .= '<li class="ff-product-item">';
			$out .= '<span class="ff-product-item-name">' . esc_html( $row[0] ) . '</span> ';
			$out .= self::stage_badge( $row[1] );
			$out .= '</li>';
		}
		$out .= '</ul>';
		return $out;
	}

	/**
	 * A sample product header, in the real markup.
	 *
	 * @return string
	 */
	public static function sample_product_header() {
		$out  = '<div class="ff-product-header">';
		$out .= '<h2 class="ff-product-name">' . esc_html__( 'Sample product — Renewal Serum', 'founding-faces' ) . '</h2>';
		$out .= '<div class="ff-product-meta">' . self::stage_badge( 'stability_testing' );
		$out .= '<span class="ff-product-status">' . esc_html__( 'Currently in eight-week stability testing', 'founding-faces' ) . '</span></div>';
		$out .= '<div class="ff-product-intro"><p>' . esc_html__( 'Sample introduction copy so the product header can be styled before a real product is chosen.', 'founding-faces' ) . '</p></div>';
		$out .= '</div>';
		return $out;
	}

	/**
	 * The stage filter chips, with nothing selected — for the editor preview.
	 *
	 * @return string
	 */
	public static function sample_filter_chips() {
		return self::stage_filter_chips( '' );
	}

	/**
	 * The archive filter bar, with nothing selected — for the editor preview.
	 *
	 * @param array $atts Which filters to show.
	 * @return string
	 */
	public static function sample_filter_bar( $atts ) {
		$atts = wp_parse_args( $atts, array( 'show_product' => 'yes', 'show_stage' => 'yes', 'show_sort' => 'yes' ) );
		return self::archive_filter_bar( $atts, 0, '', 'DESC' );
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
	private static function get_viewable_notes( $product_id, $stage, $limit, $order = 'DESC' ) {
		$order = ( 'ASC' === strtoupper( (string) $order ) ) ? 'ASC' : 'DESC';
		$args  = array(
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

		// Sort by the note's own date (falling back to publish date).
		usort( $notes, function ( $a, $b ) use ( $order ) {
			$cmp = strcmp( self::note_sort_key( $b ), self::note_sort_key( $a ) );
			return ( 'ASC' === $order ) ? -$cmp : $cmp;
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
		// The full list: this renders a product's stage as well as a note's,
		// and a product has two stages a note never has.
		$stages = FF_Post_Types::all_stages();
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

	/*
	 * -----------------------------------------------------------------------
	 * The note image slider.
	 * The gallery already stored against a note, shown one (or a few) images at
	 * a time with arrows. Separate from the grid inside the note card, because
	 * on a single-note template the images are usually wanted somewhere the
	 * card is not.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Which note a component on this page is about.
	 *
	 * An explicit id always wins. Otherwise the note being viewed, and failing
	 * that the note the current loop is on — a Theme Builder template renders
	 * per note, where the queried object is the archive rather than the row.
	 *
	 * @param int $explicit A note id chosen in the widget, or 0 for automatic.
	 * @return int The note id, or 0 if this page isn't about a note.
	 */
	public static function note_context_id( $explicit = 0 ) {
		$explicit = absint( $explicit );
		if ( $explicit ) {
			return $explicit;
		}

		$queried = get_queried_object_id();
		if ( $queried && FF_Post_Types::NOTE_CPT === get_post_type( $queried ) ) {
			return (int) $queried;
		}

		$current = get_the_ID();
		if ( $current && FF_Post_Types::NOTE_CPT === get_post_type( $current ) ) {
			return (int) $current;
		}

		return 0;
	}

	/**
	 * Which product a component on this page is about.
	 *
	 * The counterpart of note_context_id(): an explicit id wins, then the
	 * product being viewed, then the product the current loop is on. This is
	 * what lets one Single Product template serve every product.
	 *
	 * A note answers the question too, through the product it belongs to. On a
	 * Single Note template "the product on this page" is unambiguous — it is the
	 * one that note is about — and that is what makes a "more from this
	 * formulation" block possible without naming a product anywhere.
	 *
	 * @param int|string $explicit A product id, or 'auto'/0 for automatic.
	 * @return int The product id, or 0.
	 */
	public static function product_context_id( $explicit = 0 ) {
		if ( 'auto' !== $explicit ) {
			$explicit = absint( $explicit );
			if ( $explicit ) {
				return $explicit;
			}
			return 0;
		}

		foreach ( array( get_queried_object_id(), get_the_ID() ) as $post_id ) {
			if ( ! $post_id ) {
				continue;
			}

			$type = get_post_type( $post_id );
			if ( FF_Post_Types::PRODUCT_CPT === $type ) {
				return (int) $post_id;
			}
			if ( FF_Post_Types::NOTE_CPT === $type ) {
				$product = (int) get_post_meta( $post_id, FF_Post_Types::META_NOTE_PRODUCT, true );
				if ( $product ) {
					return $product;
				}
			}
		}

		return 0;
	}

	/**
	 * Register the slider script.
	 *
	 * Kept separate from enqueueing so it exists by the time a widget renders,
	 * in the editor as well as on the front end.
	 */
	public static function register_slider_assets() {
		if ( ! wp_script_is( 'ff-note-slider', 'registered' ) ) {
			wp_register_script( 'ff-note-slider', FF_URL . 'assets/js/note-slider.js', array(), FF_VERSION, true );
		}
	}

	/**
	 * The note gallery slider: [ff_note_gallery].
	 *
	 * With no id it uses the note the page is about, so the shortcode can sit
	 * in a single-note template and follow whichever note is being read.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function sc_note_gallery( $atts ) {
		$a = shortcode_atts( array(
			'id'       => 0,
			'size'     => 'large',
			'link'     => 'none',
			'caption'  => '0',
			'dots'     => '0',
			'autoplay' => 0,
		), $atts, 'ff_note_gallery' );

		return self::note_gallery_html( self::note_context_id( $a['id'] ), array(
			'size'     => sanitize_key( $a['size'] ),
			'link'     => sanitize_key( $a['link'] ),
			'caption'  => in_array( strtolower( (string) $a['caption'] ), array( '1', 'yes', 'true' ), true ),
			'dots'     => in_array( strtolower( (string) $a['dots'] ), array( '1', 'yes', 'true' ), true ),
			'autoplay' => absint( $a['autoplay'] ),
		) );
	}

	/**
	 * The slider for one note's gallery.
	 *
	 * Returns an empty string — not a message — when there is nothing to show
	 * or the viewer isn't allowed the note. A gallery with no images should
	 * leave no trace on the page.
	 *
	 * @param int   $note_id The note.
	 * @param array $args    Slider options.
	 * @return string
	 */
	public static function note_gallery_html( $note_id, $args = array() ) {
		$note_id = absint( $note_id );
		if ( ! $note_id ) {
			return '';
		}

		// The single-note page is gated already, but a gallery widget can be
		// pointed at any note from any page, so the gate is checked here too.
		if ( ! FF_Gating::can_view_note( $note_id ) ) {
			return '';
		}

		$ids = array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $note_id, FF_Post_Types::META_NOTE_GALLERY, true ) ) ) );
		if ( empty( $ids ) ) {
			return '';
		}

		$args   = self::slider_args( $args );
		$slides = array();

		foreach ( $ids as $id ) {
			$img = wp_get_attachment_image( $id, $args['size'], false, array(
				'class'   => 'ff-slide-img',
				'loading' => 'lazy',
			) );
			if ( ! $img ) {
				continue;
			}

			$caption = $args['caption'] ? FF_Text::inline( wp_get_attachment_caption( $id ) ) : '';
			$slides[] = self::slide_html( $img, wp_get_attachment_image_url( $id, 'full' ), $caption, $args );
		}

		if ( empty( $slides ) ) {
			return '';
		}

		return self::slider_html( $slides, $args );
	}

	/**
	 * A slider of placeholder images, for designing against in the editor.
	 *
	 * Five of them, so the arrows, the dots and the movement are all there to
	 * style before a note has any images on it.
	 *
	 * @param array $args Slider options.
	 * @return string
	 */
	public static function sample_gallery_html( $args = array() ) {
		$args   = self::slider_args( $args );
		$slides = array();

		for ( $i = 1; $i <= 5; $i++ ) {
			$src = self::placeholder_image_src( $i );
			$img = '<img class="ff-slide-img" src="' . esc_url( $src ) . '" alt="" width="800" height="600" />';
			/* translators: %d is the position of a sample image in the slider. */
			$caption  = $args['caption'] ? sprintf( __( 'Sample caption %d — the image caption from the media library shows here.', 'founding-faces' ), $i ) : '';
			$slides[] = self::slide_html( $img, $src, esc_html( $caption ), $args );
		}

		return self::slider_html( $slides, $args );
	}

	/**
	 * Fill in the slider options that weren't given.
	 *
	 * @param array $args Partial options.
	 * @return array
	 */
	private static function slider_args( $args ) {
		return wp_parse_args( $args, array(
			// Which registered image size to render.
			'size'     => 'large',
			// none | file | lightbox.
			'link'     => 'none',
			'caption'  => false,
			'dots'     => false,
			// Milliseconds between slides; 0 is off.
			'autoplay' => 0,
			// The arrow markup, so the widget can hand over a chosen icon.
			'prev'     => '',
			'next'     => '',
			'prev_label' => __( 'Previous image', 'founding-faces' ),
			'next_label' => __( 'Next image', 'founding-faces' ),
		) );
	}

	/**
	 * One slide: the image, optionally linked, optionally captioned.
	 *
	 * @param string $img     The <img> markup.
	 * @param string $full    URL of the full-size file.
	 * @param string $caption Caption HTML, already filtered.
	 * @param array  $args    Slider options.
	 * @return string
	 */
	private static function slide_html( $img, $full, $caption, $args ) {
		$inner = $img;

		if ( 'lightbox' === $args['link'] && $full ) {
			// Elementor's own lightbox, opened by its data attributes, so the
			// images share one gallery and the site keeps a single lightbox.
			$inner = '<a class="ff-slide-link" href="' . esc_url( $full ) . '" data-elementor-open-lightbox="yes" data-elementor-lightbox-slideshow="ff-note-gallery">' . $img . '</a>';
		} elseif ( 'file' === $args['link'] && $full ) {
			$inner = '<a class="ff-slide-link" href="' . esc_url( $full ) . '" target="_blank" rel="noopener">' . $img . '</a>';
		}

		$out = '<div class="ff-slide"><figure class="ff-slide-figure">' . $inner;
		if ( '' !== $caption ) {
			$out .= '<figcaption class="ff-slide-caption">' . $caption . '</figcaption>';
		}
		$out .= '</figure></div>';

		return $out;
	}

	/**
	 * Wrap prepared slides in the slider, with arrows and dots if they earn it.
	 *
	 * One image gets no arrows and no dots at all — there is nowhere to go, so
	 * nothing is drawn. From two upwards the controls appear and the slider
	 * loops, and the script hides them again at any screen width where every
	 * image is already on show.
	 *
	 * @param array $slides Slide markup.
	 * @param array $args   Slider options.
	 * @return string
	 */
	private static function slider_html( $slides, $args ) {
		self::enqueue();
		self::register_slider_assets();
		wp_enqueue_script( 'ff-note-slider' );

		$count = count( $slides );
		$many  = $count > 1;

		$out  = '<div class="ff-note-slider' . ( $many ? '' : ' ff-note-slider--single' ) . '"';
		$out .= ' data-ff-slider="1"';
		if ( $many && $args['autoplay'] > 0 ) {
			$out .= ' data-autoplay="' . esc_attr( (int) $args['autoplay'] ) . '"';
		}
		$out .= '>';

		$out .= '<div class="ff-slider-viewport"><div class="ff-slider-track">' . implode( '', $slides ) . '</div></div>';

		if ( $many ) {
			$prev = '' !== $args['prev'] ? $args['prev'] : self::arrow_svg( 'prev' );
			$next = '' !== $args['next'] ? $args['next'] : self::arrow_svg( 'next' );

			$out .= '<button type="button" class="ff-slider-arrow ff-slider-prev" aria-label="' . esc_attr( $args['prev_label'] ) . '">' . $prev . '</button>';
			$out .= '<button type="button" class="ff-slider-arrow ff-slider-next" aria-label="' . esc_attr( $args['next_label'] ) . '">' . $next . '</button>';

			if ( $args['dots'] ) {
				$out .= '<div class="ff-slider-dots">';
				for ( $i = 0; $i < $count; $i++ ) {
					/* translators: %d is a slide number. */
					$label = sprintf( __( 'Go to image %d', 'founding-faces' ), $i + 1 );
					$out  .= '<button type="button" class="ff-slider-dot' . ( 0 === $i ? ' is-current' : '' ) . '" aria-label="' . esc_attr( $label ) . '"></button>';
				}
				$out .= '</div>';
			}
		}

		$out .= '</div>';

		return $out;
	}

	/**
	 * The default arrow: a plain chevron in the current text colour.
	 *
	 * Deliberately unstyled — no background, no border, no size of its own —
	 * so everything about how it looks comes from the widget's controls. The
	 * widget can replace it with any icon from the library.
	 *
	 * @param string $dir 'prev' or 'next'.
	 * @return string
	 */
	private static function arrow_svg( $dir ) {
		$d = ( 'prev' === $dir ) ? 'M15 4 7 12l8 8' : 'M9 4l8 8-8 8';

		return '<svg class="ff-slider-arrow-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="' . $d . '"/></svg>';
	}

	/**
	 * A neutral placeholder image for the editor samples.
	 *
	 * Drawn here as an SVG data URI rather than loaded from anywhere: it costs
	 * no request, and numbering each one makes the slider's movement obvious
	 * while the design is being made.
	 *
	 * @param int $n The slide number.
	 * @return string A data URI.
	 */
	private static function placeholder_image_src( $n ) {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 600" width="800" height="600">'
			. '<rect width="800" height="600" fill="#eceae6"/>'
			. '<text x="400" y="345" font-family="Helvetica,Arial,sans-serif" font-size="140" fill="#b6afa5" text-anchor="middle">' . (int) $n . '</text>'
			. '</svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
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
