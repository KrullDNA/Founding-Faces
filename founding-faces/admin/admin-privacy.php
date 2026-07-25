<?php
/**
 * The Privacy & Tools admin screen.
 *
 * Per-member CSV export and data deletion, a consent audit view, and the
 * test-mode system: create test accounts, and a heavily guarded reset that
 * deletes all test accounts and returns numbering to a true zero state.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Admin_Privacy
 *
 * The admin screen behind Founding Faces → Privacy & Tools.
 */
class FF_Admin_Privacy {

	// The page slug.
	const PAGE_SLUG = 'founding-faces-privacy';

	// The word Nick must type to confirm the reset.
	const CONFIRM_WORD = 'RESET';

	/**
	 * Wire up the menu and the action handlers.
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_post_ff_admin_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_ff_admin_delete', array( __CLASS__, 'handle_delete' ) );
		add_action( 'admin_post_ff_create_test', array( __CLASS__, 'handle_create_test' ) );
		add_action( 'admin_post_ff_test_reset', array( __CLASS__, 'handle_test_reset' ) );
	}

	/**
	 * Add the Privacy & Tools submenu.
	 */
	public static function add_menu() {
		add_submenu_page(
			FF_Admin_Applications::PAGE_SLUG,
			__( 'Privacy & Tools', 'founding-faces' ),
			__( 'Privacy & Tools', 'founding-faces' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Render the Privacy & Tools page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap ff-admin">';
		echo '<h1>' . esc_html__( 'Founding Faces — Privacy & Tools', 'founding-faces' ) . '</h1>';

		self::render_notice();
		self::render_members_table();
		self::render_test_tools();

		echo '</div>';
	}

	/*
	 * -----------------------------------------------------------------------
	 * Members: export, delete, and the consent audit (one table).
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Render the members table with export/delete actions and consent audit.
	 */
	private static function render_members_table() {
		$members = get_users( array(
			'meta_key'     => FF_Members::META_GROUP, // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_compare' => 'EXISTS',
			'orderby'      => 'ID',
			'order'        => 'ASC',
		) );

		echo '<h2>' . esc_html__( 'Members — export, delete & consent audit', 'founding-faces' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Export a member\'s full record as CSV, delete their personal data (their number is retired, never reused), and see who has consented to emails and when.', 'founding-faces' ) . '</p>';

		if ( empty( $members ) ) {
			echo '<p class="ff-empty">' . esc_html__( 'No members yet.', 'founding-faces' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Number', 'founding-faces' ) . '</th>';
		echo '<th>' . esc_html__( 'Member', 'founding-faces' ) . '</th>';
		echo '<th>' . esc_html__( 'Group', 'founding-faces' ) . '</th>';
		echo '<th>' . esc_html__( 'Consent', 'founding-faces' ) . '</th>';
		echo '<th>' . esc_html__( 'Consent recorded', 'founding-faces' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'founding-faces' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $members as $member ) {
			$uid     = $member->ID;
			$number  = get_user_meta( $uid, FF_Members::META_NUMBER, true );
			$group   = get_user_meta( $uid, FF_Members::META_GROUP, true );
			$real    = get_user_meta( $uid, FF_Members::META_REAL_NAME, true );
			$consent = (int) get_user_meta( $uid, FF_Members::META_CONSENT, true );
			$is_test = (int) get_user_meta( $uid, FF_Members::META_IS_TEST, true );
			$deact   = (int) get_user_meta( $uid, FF_Members::META_DEACTIVATED, true );
			$app     = FF_Privacy::get_application( $uid );

			echo '<tr>';
			echo '<td>' . ( $number ? esc_html( $number ) : '—' ) . '</td>';
			echo '<td>' . esc_html( $real ? $real : $member->display_name ) . '<br /><span class="ff-consent">' . esc_html( $member->user_email ) . '</span>';
			if ( $is_test ) {
				echo ' <span class="ff-badge">' . esc_html__( 'TEST', 'founding-faces' ) . '</span>';
			}
			if ( $deact ) {
				echo ' <span class="ff-badge ff-badge--withdrawn">' . esc_html__( 'inactive', 'founding-faces' ) . '</span>';
			}
			echo '</td>';
			echo '<td>' . esc_html( 'the-35' === $group ? __( 'The 35', 'founding-faces' ) : __( 'The Circle', 'founding-faces' ) ) . '</td>';
			echo '<td>' . ( $consent ? '<span style="color:#1e5631;font-weight:600;">' . esc_html__( 'Yes', 'founding-faces' ) . '</span>' : '<span style="color:#8a1f1f;">' . esc_html__( 'No', 'founding-faces' ) . '</span>' ) . '</td>';
			echo '<td>' . esc_html( $app && $app->consent_at ? $app->consent_at : '—' ) . '</td>';
			echo '<td class="ff-actions">';
			self::action_button( 'ff_admin_export', $uid, __( 'Export CSV', 'founding-faces' ) );
			self::action_button( 'ff_admin_delete', $uid, __( 'Delete data', 'founding-faces' ), true );
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Render a single admin action button as its own nonce-protected form.
	 *
	 * @param string $action  The admin-post action name.
	 * @param int    $uid     The member id.
	 * @param string $label   The button label.
	 * @param bool   $confirm Whether to ask for confirmation.
	 */
	private static function action_button( $action, $uid, $label, $confirm = false ) {
		$onclick = $confirm
			? ' onclick="return confirm(\'' . esc_js( __( 'Delete this member\'s personal data? Their number is retired, never reused. This can\'t be undone.', 'founding-faces' ) ) . '\');"'
			: '';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ff-action-form">';
		echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '" />';
		echo '<input type="hidden" name="uid" value="' . esc_attr( $uid ) . '" />';
		wp_nonce_field( $action . '_' . $uid );
		echo '<button type="submit" class="button' . ( $confirm ? ' button-link-delete' : '' ) . '"' . $onclick . '>' . esc_html( $label ) . '</button>';
		echo '</form>';
	}

	/*
	 * -----------------------------------------------------------------------
	 * Test-mode tools.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Render the test-mode tools: create a test member, and the guarded reset.
	 */
	private static function render_test_tools() {
		$real_exists  = FF_Members::has_real_numbered_member();
		$test_count   = count( get_users( array(
			'meta_key'   => FF_Members::META_IS_TEST, // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery
			'fields'     => 'ID',
		) ) );
		$sequence     = (int) get_option( FF_Members::OPT_SEQUENCE, 0 );
		$retired      = (array) get_option( FF_Members::OPT_RETIRED, array() );

		echo '<hr style="margin:2.5em 0;" />';
		echo '<h2>' . esc_html__( 'Test mode', 'founding-faces' ) . '</h2>';

		echo '<p class="description">' . sprintf(
			/* translators: 1: last issued number, 2: count of retired numbers, 3: count of test accounts. */
			esc_html__( 'Sequence is at %1$d; %2$d retired number(s); %3$d test account(s).', 'founding-faces' ),
			$sequence,
			count( $retired ),
			$test_count
		) . '</p>';

		// --- Create test member ---
		echo '<h3>' . esc_html__( 'Create a test member', 'founding-faces' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Test accounts take real numbers so you can exercise the whole flow. Clear them with the reset below before going live.', 'founding-faces' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="ff_create_test" />';
		wp_nonce_field( 'ff_create_test' );
		echo '<select name="group">';
		echo '<option value="the-35">' . esc_html__( 'The 35', 'founding-faces' ) . '</option>';
		echo '<option value="the-circle">' . esc_html__( 'The Circle', 'founding-faces' ) . '</option>';
		echo '</select> ';
		echo '<button type="submit" class="button">' . esc_html__( 'Create test member', 'founding-faces' ) . '</button>';
		echo '</form>';

		// --- Guarded reset ---
		echo '<h3 style="margin-top:2em;color:#8a1f1f;">' . esc_html__( 'Reset to a true zero state', 'founding-faces' ) . '</h3>';
		echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'This deletes ALL test accounts, sets the numbering sequence back to zero, and clears the retired-numbers list — all together — so the next approved member of The 35 becomes 01. This is an "empty the database" class action and cannot be undone.', 'founding-faces' ) . '</p></div>';

		if ( $real_exists ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'The reset is unavailable because at least one real (non-test) numbered member exists. It physically cannot run while a real Founding Face is present.', 'founding-faces' ) . '</p></div>';
			return;
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'' . esc_js( __( 'Really reset to zero? All test accounts will be deleted.', 'founding-faces' ) ) . '\');">';
		echo '<input type="hidden" name="action" value="ff_test_reset" />';
		wp_nonce_field( 'ff_test_reset' );
		echo '<p>';
		echo '<label>' . sprintf(
			/* translators: %s is the confirmation word to type. */
			esc_html__( 'Type %s to confirm: ', 'founding-faces' ),
			'<code>' . esc_html( self::CONFIRM_WORD ) . '</code>'
		);
		echo ' <input type="text" name="confirm" value="" autocomplete="off" style="width:120px;" /></label> ';
		echo '<button type="submit" class="button button-primary" style="background:#8a1f1f;border-color:#8a1f1f;">' . esc_html__( 'Run reset', 'founding-faces' ) . '</button>';
		echo '</p>';
		echo '</form>';
	}

	/*
	 * -----------------------------------------------------------------------
	 * Handlers.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Handle a per-member CSV export.
	 */
	public static function handle_export() {
		self::guard();
		$uid = isset( $_POST['uid'] ) ? absint( wp_unslash( $_POST['uid'] ) ) : 0;
		check_admin_referer( 'ff_admin_export_' . $uid );

		// Streams the CSV and exits.
		FF_Privacy::stream_export( $uid );
	}

	/**
	 * Handle a per-member data deletion.
	 */
	public static function handle_delete() {
		self::guard();
		$uid = isset( $_POST['uid'] ) ? absint( wp_unslash( $_POST['uid'] ) ) : 0;
		check_admin_referer( 'ff_admin_delete_' . $uid );

		$result = FF_Privacy::delete_member( $uid );
		self::redirect( is_wp_error( $result ) ? 'error' : 'deleted' );
	}

	/**
	 * Handle creating a test member.
	 */
	public static function handle_create_test() {
		self::guard();
		check_admin_referer( 'ff_create_test' );

		$group  = isset( $_POST['group'] ) ? sanitize_key( wp_unslash( $_POST['group'] ) ) : 'the-circle';
		$group  = in_array( $group, array( 'the-35', 'the-circle' ), true ) ? $group : 'the-circle';
		$result = FF_Members::create_test_member( $group );
		self::redirect( is_wp_error( $result ) ? 'error' : 'test_created' );
	}

	/**
	 * Handle the guarded test reset.
	 */
	public static function handle_test_reset() {
		self::guard();
		check_admin_referer( 'ff_test_reset' );

		// Require the confirmation word, not a single click.
		$typed = isset( $_POST['confirm'] ) ? strtoupper( trim( sanitize_text_field( wp_unslash( $_POST['confirm'] ) ) ) ) : '';
		if ( self::CONFIRM_WORD !== $typed ) {
			self::redirect( 'reset_unconfirmed' );
		}

		$result = FF_Members::run_test_reset();
		if ( is_wp_error( $result ) ) {
			self::redirect( 'reset_refused' );
		}
		self::redirect( 'reset_done' );
	}

	/**
	 * Confirm the current user may manage the plugin.
	 */
	private static function guard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'founding-faces' ) );
		}
	}

	/**
	 * Redirect back to this page with a message code.
	 *
	 * @param string $code The message code.
	 */
	private static function redirect( $code ) {
		wp_safe_redirect( add_query_arg(
			array(
				'page'    => self::PAGE_SLUG,
				'ff_tool' => $code,
			),
			admin_url( 'admin.php' )
		) );
		exit;
	}

	/**
	 * Render the notice for the current ff_tool code, if any.
	 */
	private static function render_notice() {
		if ( ! isset( $_GET['ff_tool'] ) ) {
			return;
		}
		$code = sanitize_key( wp_unslash( $_GET['ff_tool'] ) );

		$messages = array(
			'deleted'           => array( 'success', __( 'The member\'s personal data was deleted; their number is retired.', 'founding-faces' ) ),
			'test_created'      => array( 'success', __( 'Test member created.', 'founding-faces' ) ),
			'reset_done'        => array( 'success', __( 'Reset complete — numbering is back to zero and test accounts are gone.', 'founding-faces' ) ),
			'reset_refused'     => array( 'error', __( 'Reset refused: a real numbered member exists.', 'founding-faces' ) ),
			'reset_unconfirmed' => array( 'error', __( 'Reset not run: the confirmation word didn\'t match.', 'founding-faces' ) ),
			'error'             => array( 'error', __( 'Something went wrong. Please try again.', 'founding-faces' ) ),
		);

		if ( ! isset( $messages[ $code ] ) ) {
			return;
		}
		list( $type, $text ) = $messages[ $code ];
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
	}
}
