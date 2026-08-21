<?php
/**
 * Registers the plugin's content types: the Products and Notes custom post
 * types, and the Group taxonomy applied to members (WordPress users).
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Post_Types
 *
 * Defines the shape of the members-area content:
 *  - ff_product : one entry per product in development (e.g. The Serum).
 *  - ff_note    : a formulation note, test result or decision, linked to a
 *                 product.
 *  - ff_group   : the two membership groups, The 35 and The Circle, applied to
 *                 users rather than posts, driving gating and email segmentation.
 *
 * Notes and products are written in the admin and read in the members area.
 * Each has a single URL so an Elementor template can style it once, and each is
 * gated server-side before it renders, kept out of site search, out of the
 * sitemap and marked noindex. Both are registered "public", which in WordPress
 * means only that the type has a front end, the protection is the gate, not
 * the flag. Neither is exposed over REST: that would be a path straight around
 * the gate.
 */
class FF_Post_Types {

	// The slug used for the Products post type.
	const PRODUCT_CPT = 'ff_product';

	// The slug used for the Notes post type.
	const NOTE_CPT = 'ff_note';

	// The slug used for the Group taxonomy (applied to users).
	const GROUP_TAXONOMY = 'ff_group';

	// Post meta keys for a note's structured fields.
	const META_NOTE_PRODUCT = 'ff_note_product';
	const META_NOTE_DATE    = 'ff_note_date';
	// The version number shown on a note. Named for what the field used to be
	// called (Trial number, renamed in 1.0.78) because the stored key cannot
	// change without orphaning the numbers already on published notes.
	const META_NOTE_TRIAL   = 'ff_note_trial';
	// The two measured figures. Kept as strings, because an empty string means
	// "not measured on this version" and 0 does not: a natural origin of zero is
	// a real answer, and it must not read as a blank.
	const META_NOTE_PH      = 'ff_note_ph';
	const META_NOTE_NATURAL = 'ff_note_natural';
	const META_NOTE_STAGE   = 'ff_note_stage';
	const META_NOTE_GALLERY = 'ff_note_gallery';
	const META_NOTE_AUDIENCE = 'ff_note_audience';

	// The label a member carries after giving feedback on this note.
	const META_NOTE_TAG = 'ff_note_tag';

	// Post meta keys for a product's own fields (used by the product header).
	const META_PRODUCT_STAGE  = 'ff_product_stage';
	const META_PRODUCT_STATUS = 'ff_product_status';

	/**
	 * Register everything this class owns.
	 *
	 * Called on "init" and again during activation. Order matters: the Products
	 * and Notes types first, then the Group taxonomy, then the note meta fields.
	 */
	public static function register() {
		self::register_product_cpt();
		self::register_note_cpt();
		self::register_group_taxonomy();
		self::register_note_meta();

		// Let Elementor list the plugin's content types in Theme Builder.
		add_filter( 'elementor/utils/get_public_post_types', array( __CLASS__, 'elementor_post_types' ) );
	}

	/**
	 * Add this plugin's content types to Elementor's list of public post types.
	 *
	 * Elementor builds that list from post types flagged show_in_nav_menus, and
	 * it drives the Theme Builder preview picker and the display conditions.
	 * Notes now carry that flag, so they arrive on their own, this is the belt
	 * to that braces, and it covers any Elementor list built through this
	 * filter rather than from the flag. Nothing is added twice: an entry
	 * already present is left alone.
	 *
	 * Only types WordPress considers viewable are offered: a type with no front
	 * end has no single view for a template to preview or target. Notes and
	 * products both pass that test now, and both carry the flag, so this is
	 * belt and braces for either.
	 *
	 * @param array $post_types Slug => label, as Elementor built it.
	 * @return array
	 */
	public static function elementor_post_types( $post_types ) {
		if ( ! is_array( $post_types ) ) {
			return $post_types;
		}

		foreach ( array( self::NOTE_CPT, self::PRODUCT_CPT ) as $slug ) {
			if ( isset( $post_types[ $slug ] ) ) {
				continue;
			}

			$object = get_post_type_object( $slug );
			if ( $object && is_post_type_viewable( $object ) ) {
				$post_types[ $slug ] = $object->label;
			}
		}

		return $post_types;
	}

	/**
	 * The list of development stages a note can be in.
	 *
	 * Returns the stored key => human-readable label, used both for the admin
	 * select and for the frontend stage badge in a later stage.
	 *
	 * @return array
	 */
	/**
	 * The tag a member carries after giving feedback on this note.
	 *
	 * A version is what feedback is actually about. "Everyone who fed back on
	 * version 12" is a real email to write, and it cannot be written from a
	 * single label saying somebody once said something.
	 *
	 * Short by default, because every tag a member has shares one
	 * 250-character field on Campaign Monitor: feedback-v12 rather than the
	 * whole note title. Where two products both reach version 12 the note's own
	 * field is there to tell them apart.
	 *
	 * Returning an empty string from the filter switches the tagging off
	 * entirely, for anyone who would rather the platform knew none of this.
	 *
	 * @param int $note_id The note the feedback was about.
	 * @return string An empty string when there is nothing to tag.
	 */
	public static function note_tag( $note_id ) {
		$note_id = (int) $note_id;
		$custom  = trim( (string) get_post_meta( $note_id, self::META_NOTE_TAG, true ) );
		$version = trim( (string) get_post_meta( $note_id, self::META_NOTE_TRIAL, true ) );

		if ( '' !== $custom ) {
			$slug = sanitize_title( $custom );
		} elseif ( '' !== $version ) {
			$slug = 'v' . sanitize_title( $version );
		} else {
			$post = get_post( $note_id );
			$slug = ( $post && '' !== $post->post_name ) ? $post->post_name : '';
		}

		if ( strlen( $slug ) > 40 ) {
			$cut  = substr( $slug, 0, 40 );
			$last = strrpos( $cut, '-' );
			$slug = ( false !== $last && $last > 20 ) ? substr( $cut, 0, $last ) : $cut;
			$slug = trim( $slug, '-' ) . '-' . $note_id;
		}

		$tag = ( '' !== $slug ) ? 'feedback-' . $slug : '';

		/**
		 * Filter the tag a member carries after giving feedback on a note.
		 *
		 * @param string $tag     The tag, or an empty string for none.
		 * @param int    $note_id The note.
		 */
		return (string) apply_filters( 'ff_feedback_tag', $tag, $note_id );
	}

	/**
	 * A measured figure: a number as typed, or an empty string.
	 *
	 * @param mixed $value The submitted value.
	 * @return string
	 */
	public static function sanitize_measure( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value || ! is_numeric( $value ) ) {
			return '';
		}
		return (string) ( 0 + $value );
	}

	public static function note_stages() {
		return array(
			'in_development'    => __( 'In development', 'founding-faces' ),
			'stability_testing' => __( 'Stability testing', 'founding-faces' ),
			'passed'            => __( 'Passed', 'founding-faces' ),
			'failed'            => __( 'Failed', 'founding-faces' ),
		);
	}

	/**
	 * The stages a product can be in.
	 *
	 * A superset of the note stages: a product carries the same four while it is
	 * being worked on, and then two a note never has, the formula is settled,
	 * and the whole thing is finished. Kept as its own list so those two don't
	 * appear on the note editor or as filter chips on a notes page, where there
	 * is nothing for them to describe.
	 *
	 * @return array
	 */
	public static function product_stages() {
		return self::note_stages() + array(
			'formula_finalised' => __( 'Formula finalised', 'founding-faces' ),
			'complete'          => __( 'Complete', 'founding-faces' ),
		);
	}

	/**
	 * Every stage key there is, for turning a stored key into a label.
	 *
	 * The badge renderer doesn't know or care whether it was handed a note's
	 * stage or a product's, so it looks the label up here.
	 *
	 * @return array
	 */
	public static function all_stages() {
		return self::product_stages();
	}

	/**
	 * The audiences a note can be shown to.
	 *
	 * @return array
	 */
	public static function note_audiences() {
		return array(
			'everyone'    => __( 'Everyone (all members)', 'founding-faces' ),
			'the-35-only' => __( 'The 35 only (the vault)', 'founding-faces' ),
		);
	}

	/**
	 * Register the Products post type (ff_product).
	 *
	 * Each product is a container for its own set of formulation notes. It has
	 * a title and an editor for a short description, and a featured image. Since
	 * 1.0.79 it also has a single URL, so a product page can be built once as an
	 * Elementor template rather than by hand for each product, gated, kept out
	 * of search and out of the sitemap, exactly as a note is.
	 */
	public static function register_product_cpt() {
		$labels = array(
			'name'               => __( 'Products', 'founding-faces' ),
			'singular_name'      => __( 'Product', 'founding-faces' ),
			'add_new'            => __( 'Add New', 'founding-faces' ),
			'add_new_item'       => __( 'Add New Product', 'founding-faces' ),
			'edit_item'          => __( 'Edit Product', 'founding-faces' ),
			'new_item'           => __( 'New Product', 'founding-faces' ),
			'view_item'          => __( 'View Product', 'founding-faces' ),
			'search_items'       => __( 'Search Products', 'founding-faces' ),
			'not_found'          => __( 'No products found', 'founding-faces' ),
			'not_found_in_trash' => __( 'No products found in Trash', 'founding-faces' ),
			'all_items'          => __( 'All Products', 'founding-faces' ),
			'menu_name'          => __( 'Products', 'founding-faces' ),
		);

		$args = array(
			'labels'              => $labels,
			// Public in the one sense WordPress means by it: this type has a
			// front end. That is now true, and the flag has to say so, the
			// editor screen reads it directly to decide whether to show the
			// permalink row, so with it false a product had a working URL that
			// was nowhere on screen and whose slug could not be edited.
			//
			// It does not loosen anything. Every consequence people associate
			// with "public" is turned off explicitly below or handled
			// elsewhere: out of site search, no archive, out of the sitemap,
			// noindex on the page itself, and the gate that redirects anyone
			// who isn't a member before a single line of it renders.
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_icon'           => 'dashicons-products',
			'menu_position'       => 26,
			// Publicly queryable for the same reason notes are: it gives a
			// product a single URL, which is the only thing Elementor Theme
			// Builder can hang a Single template and its display conditions on.
			// The view is gated server-side (FF_Gating::gate_single_product), so
			// a logged-out visitor is sent to log in and a non-member away.
			'publicly_queryable'  => true,
			'exclude_from_search' => true,
			'has_archive'         => false,
			// Deliberately not "product": WooCommerce owns that slug, and these
			// are formulations in development, not things anyone can buy.
			'rewrite'             => array(
				'slug'       => 'formulation',
				'with_front' => false,
			),
			// The flag Elementor reads directly to decide what its Theme Builder
			// may preview and target. Same trade as notes: a product becomes an
			// addable item in Appearance -> Menus, where every item still
			// carries a Founding Faces visibility rule.
			'show_in_nav_menus'   => true,
			'hierarchical'        => false,
			'supports'            => array( 'title', 'editor', 'thumbnail' ),
			// Deliberately kept out of the REST API: these are members-only
			// records surfaced through the plugin's own gated components, and
			// exposing them via REST would be a path around the gating.
			'show_in_rest'        => false,
		);

		register_post_type( self::PRODUCT_CPT, $args );
	}

	/**
	 * Register the Notes post type (ff_note).
	 *
	 * A note is a single formulation entry linked to a product. Its structured
	 * fields (version number, stage, gallery, audience flag) are added in a later
	 * stage; here we register the type itself. Like products, it is admin-only
	 * and surfaced through the plugin's gated frontend components.
	 */
	public static function register_note_cpt() {
		$labels = array(
			'name'               => __( 'Notes', 'founding-faces' ),
			'singular_name'      => __( 'Note', 'founding-faces' ),
			'add_new'            => __( 'Add New', 'founding-faces' ),
			'add_new_item'       => __( 'Add New Note', 'founding-faces' ),
			'edit_item'          => __( 'Edit Note', 'founding-faces' ),
			'new_item'           => __( 'New Note', 'founding-faces' ),
			'view_item'          => __( 'View Note', 'founding-faces' ),
			'search_items'       => __( 'Search Notes', 'founding-faces' ),
			'not_found'          => __( 'No notes found', 'founding-faces' ),
			'not_found_in_trash' => __( 'No notes found in Trash', 'founding-faces' ),
			'all_items'          => __( 'All Notes', 'founding-faces' ),
			'menu_name'          => __( 'Notes', 'founding-faces' ),
		);

		$args = array(
			'labels'              => $labels,
			// True for the same reason as products above: a note has a front
			// end, and the editor screen only offers the permalink row and the
			// slug editor for a type that says so. Search, archives, the
			// sitemap and the audience gate are all handled explicitly.
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_icon'           => 'dashicons-editor-ul',
			'menu_position'       => 27,
			// Publicly queryable so a note has a single URL and Elementor Theme
			// Builder can target it with a Single template and display rules.
			// The single view is gated server-side (see FF_Gating::gate_single_note),
			// so 35-only notes still can't be reached by the wrong viewer.
			'publicly_queryable'  => true,
			'exclude_from_search' => true,
			'has_archive'         => false,
			// Elementor decides which post types its Theme Builder can preview
			// and target by reading this flag directly, not through any list a
			// plugin can filter. Notes need a Single template, so it has to be
			// true, this is what makes them appear in both the preview picker
			// and the "Where do you want to display your Template?" conditions.
			//
			// Its own meaning, "may be added to a nav menu", is the cost: a
			// note now appears as an addable item in Appearance -> Menus. That
			// is a link nobody should add, but adding one is a deliberate act,
			// and every menu item carries a Founding Faces visibility rule, so
			// a note linked by mistake is still gated for the wrong viewer.
			'show_in_nav_menus'   => true,
			'rewrite'             => array(
				'slug'       => 'note',
				'with_front' => false,
			),
			'hierarchical'        => false,
			'supports'            => array( 'title', 'editor', 'thumbnail' ),
			// Still kept out of the REST API so 35-only note content can never be
			// pulled by an unauthorised browser through the API.
			'show_in_rest'        => false,
		);

		register_post_type( self::NOTE_CPT, $args );
	}

	/**
	 * Register the Group taxonomy (ff_group), applied to users.
	 *
	 * This is a taxonomy on the "user" object rather than a post type, which is
	 * how a member is sorted into The 35 or The Circle. It drives content gating
	 * and email segmentation. It is not public and has no front-end archive; it
	 * exists purely as an internal classification.
	 */
	public static function register_group_taxonomy() {
		$labels = array(
			'name'          => __( 'Groups', 'founding-faces' ),
			'singular_name' => __( 'Group', 'founding-faces' ),
			'search_items'  => __( 'Search Groups', 'founding-faces' ),
			'all_items'     => __( 'All Groups', 'founding-faces' ),
			'edit_item'     => __( 'Edit Group', 'founding-faces' ),
			'update_item'   => __( 'Update Group', 'founding-faces' ),
			'add_new_item'  => __( 'Add New Group', 'founding-faces' ),
			'new_item_name' => __( 'New Group Name', 'founding-faces' ),
			'menu_name'     => __( 'Groups', 'founding-faces' ),
		);

		$args = array(
			'labels'            => $labels,
			// Attach this taxonomy to users, not posts.
			'public'            => false,
			'show_ui'           => true,
			'show_in_menu'      => false,
			'show_admin_column' => false,
			'hierarchical'      => false,
			'query_var'         => false,
			'rewrite'           => false,
			'show_in_rest'      => false,
		);

		// The object type "user" makes this a taxonomy of members.
		register_taxonomy( self::GROUP_TAXONOMY, 'user', $args );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Note meta fields.
	 * A note is a structured record, not just a block of text: it carries the
	 * product it belongs to, a date, a version number, a development stage, an
	 * image gallery and an audience flag. Registering the meta keeps them typed
	 * and sanitised; they are intentionally kept out of REST with the CPT.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Register the note meta keys with their types and sanitisers.
	 */
	public static function register_note_meta() {
		$edit = function () {
			return current_user_can( 'edit_posts' );
		};

		register_post_meta( self::NOTE_CPT, self::META_NOTE_PRODUCT, array(
			'type'              => 'integer',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => 'absint',
			'auth_callback'     => $edit,
		) );
		register_post_meta( self::NOTE_CPT, self::META_NOTE_DATE, array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => $edit,
		) );
		register_post_meta( self::NOTE_CPT, self::META_NOTE_TRIAL, array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => $edit,
		) );
		register_post_meta( self::NOTE_CPT, self::META_NOTE_PH, array(
			'type'         => 'string',
			'single'       => true,
			'show_in_rest' => false,
		) );
		register_post_meta( self::NOTE_CPT, self::META_NOTE_NATURAL, array(
			'type'         => 'string',
			'single'       => true,
			'show_in_rest' => false,
		) );
		register_post_meta( self::NOTE_CPT, self::META_NOTE_TAG, array(
			'type'         => 'string',
			'single'       => true,
			'show_in_rest' => false,
		) );
		register_post_meta( self::NOTE_CPT, self::META_NOTE_STAGE, array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => 'sanitize_key',
			'auth_callback'     => $edit,
		) );
		register_post_meta( self::NOTE_CPT, self::META_NOTE_GALLERY, array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => array( __CLASS__, 'sanitize_id_list' ),
			'auth_callback'     => $edit,
		) );
		register_post_meta( self::NOTE_CPT, self::META_NOTE_AUDIENCE, array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => 'sanitize_key',
			'auth_callback'     => $edit,
		) );

		// Product's own fields for the header.
		register_post_meta( self::PRODUCT_CPT, self::META_PRODUCT_STAGE, array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => 'sanitize_key',
			'auth_callback'     => $edit,
		) );
		register_post_meta( self::PRODUCT_CPT, self::META_PRODUCT_STATUS, array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => $edit,
		) );
	}

	/**
	 * Sanitise a comma-separated list of attachment ids down to clean integers.
	 *
	 * @param string $value The raw CSV of ids.
	 * @return string A CSV of positive integers only.
	 */
	public static function sanitize_id_list( $value ) {
		$ids = array_filter( array_map( 'absint', explode( ',', (string) $value ) ) );
		return implode( ',', $ids );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Note admin: the metabox where Nick fills in the structured fields.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Wire up the note metabox, its save handler and its assets.
	 *
	 * Called in the admin area only.
	 */
	public static function register_admin() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_note_metabox' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_product_metabox' ) );
		add_action( 'save_post_' . self::NOTE_CPT, array( __CLASS__, 'save_note_meta' ), 10, 2 );
		add_action( 'save_post_' . self::PRODUCT_CPT, array( __CLASS__, 'save_product_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_note_admin' ) );

		// The notes list, which is the screen this plugin is worked from.
		add_filter( 'manage_' . self::NOTE_CPT . '_posts_columns', array( __CLASS__, 'note_columns' ) );
		add_action( 'manage_' . self::NOTE_CPT . '_posts_custom_column', array( __CLASS__, 'note_column' ), 10, 2 );
		add_filter( 'manage_edit-' . self::NOTE_CPT . '_sortable_columns', array( __CLASS__, 'note_sortable_columns' ) );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'note_filters' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_notes_by_product' ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * The notes list table.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Add a Product column, next to the title where it is read.
	 *
	 * @param array $columns The existing columns.
	 * @return array
	 */
	public static function note_columns( $columns ) {
		$out = array();

		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;

			// Straight after the title, because "which product" is the second
			// thing anybody wants to know about a note and the first thing they
			// scan for.
			if ( 'title' === $key ) {
				$out['ff_product'] = __( 'Product', 'founding-faces' );
				$out['ff_version'] = __( 'Version', 'founding-faces' );
			}
		}

		return $out;
	}

	/**
	 * Fill the Product column.
	 *
	 * The product name is a link that filters the list to that product, since
	 * seeing one note for a product is usually the moment you want the rest.
	 *
	 * @param string $column  The column key.
	 * @param int    $post_id The note.
	 */
	public static function note_column( $column, $post_id ) {
		if ( 'ff_version' === $column ) {
			$version = trim( (string) get_post_meta( $post_id, self::META_NOTE_TRIAL, true ) );

			if ( '' === $version ) {
				echo '<span aria-hidden="true">&#8212;</span><span class="screen-reader-text">'
					. esc_html__( 'No version number', 'founding-faces' ) . '</span>';
				return;
			}

			echo esc_html( $version );
			return;
		}

		if ( 'ff_product' !== $column ) {
			return;
		}

		$product_id = (int) get_post_meta( $post_id, self::META_NOTE_PRODUCT, true );
		if ( ! $product_id ) {
			echo '<span aria-hidden="true">&#8212;</span><span class="screen-reader-text">'
				. esc_html__( 'No product', 'founding-faces' ) . '</span>';
			return;
		}

		$title = get_the_title( $product_id );
		$url   = add_query_arg(
			array(
				'post_type'      => self::NOTE_CPT,
				'ff_product_id'  => $product_id,
			),
			admin_url( 'edit.php' )
		);

		echo '<a href="' . esc_url( $url ) . '">' . esc_html( $title ? $title : __( '(untitled product)', 'founding-faces' ) ) . '</a>';
	}

	/**
	 * Make the Product column sortable.
	 *
	 * Sorted by the stored product id rather than the name, which groups a
	 * product's notes together, which is the point of sorting it.
	 *
	 * @param array $columns The sortable columns.
	 * @return array
	 */
	public static function note_sortable_columns( $columns ) {
		$columns['ff_product'] = 'ff_product';
		$columns['ff_version'] = 'ff_version';
		return $columns;
	}

	/**
	 * The product dropdown above the notes list.
	 *
	 * @param string $post_type The post type being listed.
	 */
	public static function note_filters( $post_type ) {
		if ( self::NOTE_CPT !== $post_type ) {
			return;
		}

		$products = get_posts( array(
			'post_type'      => self::PRODUCT_CPT,
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
		) );

		if ( empty( $products ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification
		$current = isset( $_GET['ff_product_id'] ) ? (int) $_GET['ff_product_id'] : 0;
		?>
		<label class="screen-reader-text" for="ff_product_id"><?php esc_html_e( 'Filter by product', 'founding-faces' ); ?></label>
		<select name="ff_product_id" id="ff_product_id">
			<option value="0"><?php esc_html_e( 'All products', 'founding-faces' ); ?></option>
			<?php foreach ( $products as $product ) : ?>
				<option value="<?php echo esc_attr( $product->ID ); ?>" <?php selected( $current, $product->ID ); ?>>
					<?php echo esc_html( $product->post_title ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Apply the product filter, and the product sort.
	 *
	 * @param WP_Query $query The query about to run.
	 */
	public static function filter_notes_by_product( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( self::NOTE_CPT !== $query->get( 'post_type' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification
		$product_id = isset( $_GET['ff_product_id'] ) ? (int) $_GET['ff_product_id'] : 0;

		if ( $product_id ) {
			$query->set( 'meta_query', array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'key'   => self::META_NOTE_PRODUCT,
					'value' => $product_id,
				),
			) );
		}

		$orderby = $query->get( 'orderby' );

		if ( 'ff_product' === $orderby ) {
			$query->set( 'meta_key', self::META_NOTE_PRODUCT ); // phpcs:ignore WordPress.DB.SlowDBQuery
			$query->set( 'orderby', 'meta_value_num' );
		}

		if ( 'ff_version' === $orderby ) {
			// Sorted as a number, so 10 comes after 9 rather than after 1. The
			// field is free text because a version can be 2.1 or 4b, and MySQL
			// reads the leading number out of those and ignores the rest, which
			// is the right order in every case that matters.
			$query->set( 'meta_key', self::META_NOTE_TRIAL ); // phpcs:ignore WordPress.DB.SlowDBQuery
			$query->set( 'orderby', 'meta_value_num' );
		}
	}

	/**
	 * Add the "Note details" metabox to the note edit screen.
	 */
	public static function add_note_metabox() {
		add_meta_box(
			'ff_note_details',
			__( 'Note details', 'founding-faces' ),
			array( __CLASS__, 'render_note_metabox' ),
			self::NOTE_CPT,
			'normal',
			'high'
		);
	}

	/**
	 * Add the "Product details" metabox to the product edit screen.
	 */
	public static function add_product_metabox() {
		add_meta_box(
			'ff_product_details',
			__( 'Product details', 'founding-faces' ),
			array( __CLASS__, 'render_product_metabox' ),
			self::PRODUCT_CPT,
			'side',
			'default'
		);
	}

	/**
	 * Render the product metabox: the current stage and a short status line.
	 *
	 * @param WP_Post $post The product being edited.
	 */
	public static function render_product_metabox( $post ) {
		wp_nonce_field( 'ff_save_product', 'ff_product_nonce' );

		$stage  = get_post_meta( $post->ID, self::META_PRODUCT_STAGE, true );
		$status = get_post_meta( $post->ID, self::META_PRODUCT_STATUS, true );
		?>
		<p>
			<label for="ff_product_stage"><strong><?php esc_html_e( 'Current stage', 'founding-faces' ); ?></strong></label><br />
			<select name="ff_product_stage" id="ff_product_stage" style="width:100%;">
				<option value=""><?php esc_html_e( 'None', 'founding-faces' ); ?></option>
				<?php foreach ( self::product_stages() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $stage, $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="ff_product_status"><strong><?php esc_html_e( 'Where it\'s up to', 'founding-faces' ); ?></strong></label><br />
			<input type="text" name="ff_product_status" id="ff_product_status" style="width:100%;" value="<?php echo esc_attr( $status ); ?>" placeholder="<?php esc_attr_e( 'e.g. Version 3 in stability testing', 'founding-faces' ); ?>" />
		</p>
		<?php
	}

	/**
	 * Save the product metabox fields.
	 *
	 * @param int     $post_id The product id.
	 * @param WP_Post $post    The product object.
	 */
	public static function save_product_meta( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['ff_product_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['ff_product_nonce'] ), 'ff_save_product' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$stage = isset( $_POST['ff_product_stage'] ) ? sanitize_key( wp_unslash( $_POST['ff_product_stage'] ) ) : '';
		if ( '' !== $stage && ! array_key_exists( $stage, self::product_stages() ) ) {
			$stage = '';
		}
		update_post_meta( $post_id, self::META_PRODUCT_STAGE, $stage );
		update_post_meta( $post_id, self::META_PRODUCT_STATUS, isset( $_POST['ff_product_status'] ) ? sanitize_text_field( wp_unslash( $_POST['ff_product_status'] ) ) : '' );
	}

	/**
	 * Load the media picker and the note admin script on the note edit screen.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public static function enqueue_note_admin( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || self::NOTE_CPT !== $screen->post_type ) {
			return;
		}

		// The gallery picker uses the WordPress media library.
		wp_enqueue_media();
		wp_enqueue_style( 'ff-admin', FF_URL . 'admin/admin-style.css', array(), FF_VERSION );
		wp_enqueue_script( 'ff-notes-admin', FF_URL . 'assets/js/notes-admin.js', array( 'jquery' ), FF_VERSION, true );
	}

	/**
	 * Render the note metabox fields.
	 *
	 * @param WP_Post $post The note being edited.
	 */
	public static function render_note_metabox( $post ) {
		// A nonce so the save handler can trust the submission.
		wp_nonce_field( 'ff_save_note', 'ff_note_nonce' );

		$product  = (int) get_post_meta( $post->ID, self::META_NOTE_PRODUCT, true );
		$date     = get_post_meta( $post->ID, self::META_NOTE_DATE, true );
		$trial    = get_post_meta( $post->ID, self::META_NOTE_TRIAL, true );
		$ph       = get_post_meta( $post->ID, self::META_NOTE_PH, true );
		$natural  = get_post_meta( $post->ID, self::META_NOTE_NATURAL, true );
		$stage    = get_post_meta( $post->ID, self::META_NOTE_STAGE, true );
		$gallery  = get_post_meta( $post->ID, self::META_NOTE_GALLERY, true );
		$audience = get_post_meta( $post->ID, self::META_NOTE_AUDIENCE, true );

		// Default the date to today for a brand-new note.
		if ( '' === $date ) {
			$date = current_time( 'Y-m-d' );
		}
		if ( '' === $audience ) {
			$audience = 'everyone';
		}

		// All products to choose the parent from.
		$products = get_posts( array(
			'post_type'      => self::PRODUCT_CPT,
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
		) );
		?>
		<table class="form-table ff-note-meta" role="presentation">
			<tr>
				<th scope="row"><label for="ff_note_product"><?php esc_html_e( 'Product', 'founding-faces' ); ?></label></th>
				<td>
					<select name="ff_note_product" id="ff_note_product">
						<option value="0"><?php esc_html_e( 'Select a product', 'founding-faces' ); ?></option>
						<?php foreach ( $products as $p ) : ?>
							<option value="<?php echo esc_attr( $p->ID ); ?>" <?php selected( $product, $p->ID ); ?>>
								<?php echo esc_html( $p->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ff_note_date"><?php esc_html_e( 'Date', 'founding-faces' ); ?></label></th>
				<td><input type="date" name="ff_note_date" id="ff_note_date" value="<?php echo esc_attr( $date ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="ff_note_trial"><?php esc_html_e( 'Version number', 'founding-faces' ); ?></label></th>
				<td><input type="text" name="ff_note_trial" id="ff_note_trial" value="<?php echo esc_attr( $trial ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="ff_note_tag"><?php esc_html_e( 'Email marketing tag', 'founding-faces' ); ?></label></th>
				<td>
					<input type="text" name="ff_note_tag" id="ff_note_tag" class="regular-text" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_NOTE_TAG, true ) ); ?>" />
					<p class="description">
						<?php
						$note_tag = self::note_tag( $post->ID );
						if ( '' !== $note_tag ) {
							printf(
								/* translators: %s: the tag as it will be sent. */
								esc_html__( 'The label everyone who gives feedback on this note carries on your email platform, so you can write back to exactly the people who commented on this version. Left empty it comes from the version number. As it stands: %s', 'founding-faces' ),
								'<code>' . esc_html( $note_tag ) . '</code>'
							);
						} else {
							esc_html_e( 'The label everyone who gives feedback on this note carries on your email platform. Fill in a version number above, or type a tag here, and it appears.', 'founding-faces' );
						}
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ff_note_ph"><?php esc_html_e( 'Final pH', 'founding-faces' ); ?></label></th>
				<td>
					<input type="number" step="0.01" min="0" max="14" name="ff_note_ph" id="ff_note_ph" value="<?php echo esc_attr( $ph ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( 'Leave empty on a version where it was not measured. The change from the last version that has a figure is worked out and shown for you, so there is nothing to keep in step by hand.', 'founding-faces' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ff_note_natural"><?php esc_html_e( 'Natural origin', 'founding-faces' ); ?></label></th>
				<td>
					<input type="number" step="0.1" min="0" max="100" name="ff_note_natural" id="ff_note_natural" value="<?php echo esc_attr( $natural ); ?>" class="small-text" /> %
					<p class="description"><?php esc_html_e( 'As a percentage, ISO 16128 or however you are calculating it. Same again: leave it empty where it was not calculated.', 'founding-faces' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ff_note_stage"><?php esc_html_e( 'Stage', 'founding-faces' ); ?></label></th>
				<td>
					<select name="ff_note_stage" id="ff_note_stage">
						<?php foreach ( self::note_stages() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $stage, $key ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ff_note_audience"><?php esc_html_e( 'Audience', 'founding-faces' ); ?></label></th>
				<td>
					<select name="ff_note_audience" id="ff_note_audience">
						<?php foreach ( self::note_audiences() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $audience, $key ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'The 35 only keeps this note in the vault, Circle members never receive it.', 'founding-faces' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Image gallery', 'founding-faces' ); ?></th>
				<td>
					<input type="hidden" name="ff_note_gallery" id="ff_note_gallery" value="<?php echo esc_attr( $gallery ); ?>" />
					<div id="ff_note_gallery_preview" class="ff-gallery-preview">
						<?php echo self::gallery_preview_html( $gallery ); // Escaped inside the helper. ?>
					</div>
					<button type="button" class="button" id="ff_note_gallery_add"><?php esc_html_e( 'Add / edit images', 'founding-faces' ); ?></button>
					<button type="button" class="button" id="ff_note_gallery_clear"><?php esc_html_e( 'Clear', 'founding-faces' ); ?></button>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Build the thumbnail preview HTML for a gallery CSV of attachment ids.
	 *
	 * @param string $gallery A CSV of attachment ids.
	 * @return string Safe HTML of thumbnails.
	 */
	public static function gallery_preview_html( $gallery ) {
		$ids  = array_filter( array_map( 'absint', explode( ',', (string) $gallery ) ) );
		$html = '';
		foreach ( $ids as $id ) {
			$img = wp_get_attachment_image( $id, 'thumbnail' );
			if ( $img ) {
				$html .= '<span class="ff-gallery-thumb">' . $img . '</span>';
			}
		}
		return $html;
	}

	/**
	 * Save the note metabox fields.
	 *
	 * Checks the nonce, the user's capability and skips autosaves, then stores
	 * each sanitised field.
	 *
	 * @param int     $post_id The note id.
	 * @param WP_Post $post    The note object.
	 */
	public static function save_note_meta( $post_id, $post ) {
		// Bail on autosave, missing nonce, or insufficient permission.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['ff_note_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['ff_note_nonce'] ), 'ff_save_note' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Product link.
		update_post_meta( $post_id, self::META_NOTE_PRODUCT, isset( $_POST['ff_note_product'] ) ? absint( $_POST['ff_note_product'] ) : 0 );

		// Date, kept only if it looks like YYYY-MM-DD.
		$date = isset( $_POST['ff_note_date'] ) ? sanitize_text_field( wp_unslash( $_POST['ff_note_date'] ) ) : '';
		if ( '' !== $date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$date = '';
		}
		update_post_meta( $post_id, self::META_NOTE_DATE, $date );

		// Version number (free text so "12a" is allowed). The meta key is still
		// ff_note_trial: the field was called Trial number until 1.0.78, and
		// renaming the key would have detached every number already written.
		update_post_meta( $post_id, self::META_NOTE_TRIAL, isset( $_POST['ff_note_trial'] ) ? sanitize_text_field( wp_unslash( $_POST['ff_note_trial'] ) ) : '' );

		// A blank stays blank rather than becoming zero: not measured and
		// measured at zero are different answers.
		update_post_meta( $post_id, self::META_NOTE_TAG, isset( $_POST['ff_note_tag'] ) ? sanitize_title( wp_unslash( $_POST['ff_note_tag'] ) ) : '' );
		update_post_meta( $post_id, self::META_NOTE_PH, self::sanitize_measure( isset( $_POST['ff_note_ph'] ) ? wp_unslash( $_POST['ff_note_ph'] ) : '' ) );
		update_post_meta( $post_id, self::META_NOTE_NATURAL, self::sanitize_measure( isset( $_POST['ff_note_natural'] ) ? wp_unslash( $_POST['ff_note_natural'] ) : '' ) );

		// Stage, only if it's one of the known keys.
		$stage = isset( $_POST['ff_note_stage'] ) ? sanitize_key( wp_unslash( $_POST['ff_note_stage'] ) ) : '';
		if ( ! array_key_exists( $stage, self::note_stages() ) ) {
			$stage = 'in_development';
		}
		update_post_meta( $post_id, self::META_NOTE_STAGE, $stage );

		// Audience, only if it's one of the known keys.
		$audience = isset( $_POST['ff_note_audience'] ) ? sanitize_key( wp_unslash( $_POST['ff_note_audience'] ) ) : '';
		if ( ! array_key_exists( $audience, self::note_audiences() ) ) {
			$audience = 'everyone';
		}
		update_post_meta( $post_id, self::META_NOTE_AUDIENCE, $audience );

		// Gallery CSV of attachment ids.
		update_post_meta( $post_id, self::META_NOTE_GALLERY, self::sanitize_id_list( isset( $_POST['ff_note_gallery'] ) ? wp_unslash( $_POST['ff_note_gallery'] ) : '' ) );
	}
}
