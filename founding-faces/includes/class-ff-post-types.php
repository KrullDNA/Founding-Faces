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

	// Post meta keys for a note's structured fields.
	const META_NOTE_PRODUCT = 'ff_note_product';
	const META_NOTE_DATE    = 'ff_note_date';
	const META_NOTE_TRIAL   = 'ff_note_trial';
	const META_NOTE_STAGE   = 'ff_note_stage';
	const META_NOTE_GALLERY = 'ff_note_gallery';
	const META_NOTE_AUDIENCE = 'ff_note_audience';

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
	}

	/**
	 * The list of development stages a note can be in.
	 *
	 * Returns the stored key => human-readable label, used both for the admin
	 * select and for the frontend stage badge in a later stage.
	 *
	 * @return array
	 */
	public static function note_stages() {
		return array(
			'in_development'    => __( 'In development', 'founding-faces' ),
			'stability_testing' => __( 'Stability testing', 'founding-faces' ),
			'passed'            => __( 'Passed', 'founding-faces' ),
			'failed'            => __( 'Failed', 'founding-faces' ),
		);
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
			// Kept out of the REST API so 35-only note content can never be
			// pulled by an unauthorised browser through the API. Notes reach a
			// page only through the plugin's gated renderer.
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
	 * product it belongs to, a date, a trial number, a development stage, an
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
		add_action( 'save_post_' . self::NOTE_CPT, array( __CLASS__, 'save_note_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_note_admin' ) );
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
						<option value="0"><?php esc_html_e( '— Select a product —', 'founding-faces' ); ?></option>
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
				<th scope="row"><label for="ff_note_trial"><?php esc_html_e( 'Trial number', 'founding-faces' ); ?></label></th>
				<td><input type="text" name="ff_note_trial" id="ff_note_trial" value="<?php echo esc_attr( $trial ); ?>" class="regular-text" /></td>
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
					<p class="description"><?php esc_html_e( 'The 35 only keeps this note in the vault — Circle members never receive it.', 'founding-faces' ); ?></p>
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

		// Trial number (free text so "12a" is allowed).
		update_post_meta( $post_id, self::META_NOTE_TRIAL, isset( $_POST['ff_note_trial'] ) ? sanitize_text_field( wp_unslash( $_POST['ff_note_trial'] ) ) : '' );

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
