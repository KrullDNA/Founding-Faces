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
 *  - ff_note    : a formulation note, trial result or decision, linked to a
 *                 product.
 *  - ff_group   : the two membership groups, The 35 and The Circle, applied to
 *                 users rather than posts, driving gating and email segmentation.
 *
 * These content types are admin-facing only. The members area renders them
 * through the plugin's own gated components, not through public single-post
 * templates, so they are registered as non-public to stay lean and safe.
 */
class FF_Post_Types {

	// The slug used for the Products post type.
	const PRODUCT_CPT = 'ff_product';

	// The slug used for the Notes post type.
	const NOTE_CPT = 'ff_note';

	// The slug used for the Group taxonomy (applied to users).
	const GROUP_TAXONOMY = 'ff_group';

	/**
	 * Register everything this class owns.
	 *
	 * Called on "init" and again during activation. Order matters: the Products
	 * and Notes types first, then the Group taxonomy.
	 */
	public static function register() {
		self::register_product_cpt();
		self::register_note_cpt();
		self::register_group_taxonomy();
	}

	/**
	 * Register the Products post type (ff_product).
	 *
	 * Each product is a container for its own set of formulation notes. It has
	 * a title and an editor for a short description, and a featured image. It is
	 * admin-only: not publicly queryable and with no public single view, because
	 * products are surfaced through the plugin's gated frontend components.
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
			// Admin-only: the members area renders products through gated
			// components, so there is no public-facing single-post view.
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_icon'           => 'dashicons-products',
			'menu_position'       => 26,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'hierarchical'        => false,
			'supports'            => array( 'title', 'editor', 'thumbnail' ),
			// Expose to the REST API so future admin screens and Elementor can
			// read products cleanly.
			'show_in_rest'        => true,
		);

		register_post_type( self::PRODUCT_CPT, $args );
	}

	/**
	 * Register the Notes post type (ff_note).
	 *
	 * A note is a single formulation entry linked to a product. Its structured
	 * fields (trial number, stage, gallery, audience flag) are added in a later
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
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_icon'           => 'dashicons-editor-ul',
			'menu_position'       => 27,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'hierarchical'        => false,
			'supports'            => array( 'title', 'editor', 'thumbnail' ),
			'show_in_rest'        => true,
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
}
