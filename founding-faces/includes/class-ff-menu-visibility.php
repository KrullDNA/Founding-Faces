<?php
/**
 * Per-menu-item visibility for WordPress nav menus.
 *
 * Adds a "Founding Faces visibility" choice to each item in Appearance → Menus
 * (Everyone / Logged-out / All members / The 35 / The Circle), and hides items
 * on the front end that the current viewer isn't allowed to see — so a Circle
 * member never sees a menu item meant for The 35. Uses the same audience rules
 * as the Elementor "Show to" control.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Menu_Visibility
 */
class FF_Menu_Visibility {

	// Meta key stored on each nav menu item.
	const META = '_ff_menu_visibility';

	/**
	 * Wire up the editor field, the save handler and the front-end filter.
	 */
	public static function register() {
		add_action( 'wp_nav_menu_item_custom_fields', array( __CLASS__, 'render_field' ), 10, 4 );
		add_action( 'wp_update_nav_menu_item', array( __CLASS__, 'save_field' ), 10, 2 );
		add_filter( 'wp_nav_menu_objects', array( __CLASS__, 'filter_items' ), 10, 2 );
	}

	/**
	 * Render the visibility select in the menu-item editor.
	 *
	 * @param int    $item_id The menu item id.
	 * @param object $item    The menu item.
	 * @param int    $depth   Depth (unused).
	 * @param object $args    Args (unused).
	 */
	public static function render_field( $item_id, $item, $depth, $args ) {
		$value = get_post_meta( $item_id, self::META, true );
		?>
		<p class="field-ff-visibility description description-wide">
			<label for="ff-menu-visibility-<?php echo esc_attr( $item_id ); ?>">
				<?php esc_html_e( 'Founding Faces visibility', 'founding-faces' ); ?><br />
				<select name="ff_menu_visibility[<?php echo esc_attr( $item_id ); ?>]"
					id="ff-menu-visibility-<?php echo esc_attr( $item_id ); ?>" class="widefat">
					<?php foreach ( FF_Gating::visibility_options() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>
		</p>
		<?php
	}

	/**
	 * Save the visibility choice for a menu item.
	 *
	 * Runs on the core nav-menu save (already nonce-checked by WordPress).
	 *
	 * @param int $menu_id           The menu id (unused).
	 * @param int $menu_item_db_id   The menu item id.
	 */
	public static function save_field( $menu_id, $menu_item_db_id ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}
		// Only act when our field was submitted for this item.
		if ( ! isset( $_POST['ff_menu_visibility'][ $menu_item_db_id ] ) ) {
			return;
		}

		$value = sanitize_key( wp_unslash( $_POST['ff_menu_visibility'][ $menu_item_db_id ] ) );
		if ( ! array_key_exists( $value, FF_Gating::visibility_options() ) ) {
			$value = '';
		}

		if ( '' === $value ) {
			delete_post_meta( $menu_item_db_id, self::META );
		} else {
			update_post_meta( $menu_item_db_id, self::META, $value );
		}
	}

	/**
	 * Remove menu items the current viewer isn't allowed to see.
	 *
	 * A hidden item takes its descendants with it. Administrators see the full
	 * menu (they can always see everything); everyone else is filtered by their
	 * group.
	 *
	 * @param array $items The menu item objects.
	 * @param object $args  The menu args (unused).
	 * @return array
	 */
	public static function filter_items( $items, $args ) {
		if ( is_admin() || current_user_can( 'manage_options' ) ) {
			return $items;
		}

		// Map each item's rule and parent.
		$rules   = array();
		$parents = array();
		foreach ( $items as $item ) {
			$rules[ $item->ID ]   = get_post_meta( $item->ID, self::META, true );
			$parents[ $item->ID ] = (int) $item->menu_item_parent;
		}

		$kept = array();
		foreach ( $items as $item ) {
			$hide = false;
			$cur  = $item->ID;
			$seen = array();

			// Walk up the ancestor chain (including self); hide if any link is hidden.
			while ( $cur && ! isset( $seen[ $cur ] ) ) {
				$seen[ $cur ] = true;
				$rule = isset( $rules[ $cur ] ) ? $rules[ $cur ] : '';
				if ( '' !== $rule && ! FF_Gating::audience_allows( $rule ) ) {
					$hide = true;
					break;
				}
				$cur = isset( $parents[ $cur ] ) ? $parents[ $cur ] : 0;
			}

			if ( ! $hide ) {
				$kept[] = $item;
			}
		}

		return $kept;
	}
}
