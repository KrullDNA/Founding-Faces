<?php
/**
 * The admin moderation queue for applications.
 *
 * Registers the "Founding Faces" admin menu and renders the queue where Nick
 * reviews each application and approves it into The 35 or The Circle, declines
 * it, withdraws a member, or resends a welcome email. All the work is done by
 * FF_Members; this file is the screen.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Admin_Applications
 *
 * Owns the admin menu and the moderation-queue screen.
 */
class FF_Admin_Applications {

	// The slug of the admin page (also the parent menu slug).
	const PAGE_SLUG = 'founding-faces';

	/**
	 * Wire up the admin menu and the queue-only stylesheet.
	 *
	 * Called once on plugin load (its hooks only fire in the admin area).
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Add the top-level "Founding Faces" menu with the queue as its main page.
	 *
	 * The menu title carries a count bubble of pending applications, the same
	 * visual cue WordPress uses for comments awaiting moderation.
	 */
	public static function add_menu() {
		$pending = self::count_by_status( 'pending' );
		$bubble  = $pending
			? ' <span class="awaiting-mod count-' . (int) $pending . '"><span class="pending-count">' . (int) $pending . '</span></span>'
			: '';

		add_menu_page(
			__( 'Founding Faces', 'founding-faces' ),
			__( 'Founding Faces', 'founding-faces' ) . $bubble,
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_queue' ),
			'dashicons-groups',
			25
		);

		// A named submenu so the top item reads sensibly once more are added.
		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Applications', 'founding-faces' ),
			__( 'Applications', 'founding-faces' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_queue' )
		);
	}

	/**
	 * Load the admin stylesheet, but only on the moderation queue screen.
	 *
	 * @param string $hook The current admin page's hook suffix.
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'ff-admin',
			FF_URL . 'admin/admin-style.css',
			array(),
			FF_VERSION
		);
	}

	/**
	 * Count the applications in a given status.
	 *
	 * @param string $status One of the ff_applications status values.
	 * @return int The number of matching applications.
	 */
	private static function count_by_status( $status ) {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ff_applications WHERE status = %s",
				$status
			)
		);
	}

	/**
	 * Render the moderation queue screen.
	 *
	 * Shows a set of status tabs, then the matching applications with their
	 * details and the moderation buttons for each row.
	 */
	public static function render_queue() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Which status tab is showing; pending is the default working view.
		$view = isset( $_GET['ff_status'] ) ? sanitize_key( wp_unslash( $_GET['ff_status'] ) ) : 'pending';

		echo '<div class="wrap ff-admin">';
		echo '<h1>' . esc_html__( 'Founding Faces — Applications', 'founding-faces' ) . '</h1>';

		// Show the outcome of the last action, if any.
		self::render_notice();

		// Status tabs.
		self::render_tabs( $view );

		// The matching applications.
		$rows = self::get_applications( $view );

		if ( empty( $rows ) ) {
			echo '<p class="ff-empty">' . esc_html__( 'No applications in this view.', 'founding-faces' ) . '</p>';
			echo '</div>';
			return;
		}

		echo '<table class="widefat striped ff-queue">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Applicant', 'founding-faces' ) . '</th>';
		echo '<th>' . esc_html__( 'Details', 'founding-faces' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'founding-faces' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'founding-faces' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $rows as $app ) {
			self::render_row( $app );
		}

		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * Render the status tabs across the top of the queue.
	 *
	 * @param string $current The currently selected view.
	 */
	private static function render_tabs( $current ) {
		$tabs = array(
			'pending'  => __( 'Pending', 'founding-faces' ),
			'approved' => __( 'Approved', 'founding-faces' ),
			'declined' => __( 'Declined', 'founding-faces' ),
			'withdrawn' => __( 'Withdrawn', 'founding-faces' ),
			'all'      => __( 'All', 'founding-faces' ),
		);

		echo '<ul class="subsubsub ff-tabs">';
		$i    = 0;
		$last = count( $tabs ) - 1;
		foreach ( $tabs as $key => $label ) {
			$url    = add_query_arg(
				array(
					'page'      => self::PAGE_SLUG,
					'ff_status' => $key,
				),
				admin_url( 'admin.php' )
			);
			$class  = ( $key === $current ) ? 'current' : '';
			$sep    = ( $i === $last ) ? '' : ' | ';
			echo '<li><a href="' . esc_url( $url ) . '" class="' . esc_attr( $class ) . '">'
				. esc_html( $label ) . '</a>' . esc_html( $sep ) . '</li>';
			$i++;
		}
		echo '</ul>';
	}

	/**
	 * Fetch the applications for a given view, newest first.
	 *
	 * @param string $view The status tab ('pending', 'approved', etc.).
	 * @return array The matching rows.
	 */
	private static function get_applications( $view ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ff_applications';

		switch ( $view ) {
			case 'approved':
				// Both groups count as approved.
				return $wpdb->get_results(
					"SELECT * FROM {$table} WHERE status IN ('approved-35','approved-circle') ORDER BY id DESC"
				);
			case 'declined':
				return $wpdb->get_results(
					"SELECT * FROM {$table} WHERE status = 'declined' ORDER BY id DESC"
				);
			case 'withdrawn':
				return $wpdb->get_results(
					"SELECT * FROM {$table} WHERE status = 'withdrawn' ORDER BY id DESC"
				);
			case 'all':
				return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC" );
			case 'pending':
			default:
				return $wpdb->get_results(
					"SELECT * FROM {$table} WHERE status = 'pending' ORDER BY id DESC"
				);
		}
	}

	/**
	 * Render one application row, with its details and moderation buttons.
	 *
	 * @param object $app The ff_applications row.
	 */
	private static function render_row( $app ) {
		echo '<tr>';

		// Applicant column: name, email, and any Founding number.
		echo '<td class="ff-applicant">';
		echo '<strong>' . esc_html( $app->name ) . '</strong><br />';
		echo '<a href="mailto:' . esc_attr( $app->email ) . '">' . esc_html( $app->email ) . '</a>';
		if ( ! empty( $app->assigned_number ) ) {
			echo '<br /><span class="ff-number">'
				. sprintf( esc_html__( 'Founding Face %d', 'founding-faces' ), (int) $app->assigned_number )
				. '</span>';
		}
		echo '</td>';

		// Details column: postcode, Instagram, concerns and answers.
		echo '<td class="ff-details">';
		echo '<div><span class="ff-label">' . esc_html__( 'Postcode:', 'founding-faces' ) . '</span> ' . esc_html( $app->postcode ) . '</div>';
		if ( ! empty( $app->instagram ) ) {
			echo '<div><span class="ff-label">' . esc_html__( 'Instagram:', 'founding-faces' ) . '</span> ' . esc_html( $app->instagram ) . '</div>';
		}
		if ( ! empty( $app->skin_concerns ) ) {
			echo '<div><span class="ff-label">' . esc_html__( 'Concerns:', 'founding-faces' ) . '</span> ' . esc_html( $app->skin_concerns ) . '</div>';
		}
		if ( ! empty( $app->answers ) ) {
			echo '<div><span class="ff-label">' . esc_html__( 'Notes:', 'founding-faces' ) . '</span> ' . nl2br( esc_html( $app->answers ) ) . '</div>';
		}
		echo '<div class="ff-consent">'
			. ( $app->consent
				? esc_html__( 'Consented to emails', 'founding-faces' )
				: esc_html__( 'No email consent', 'founding-faces' ) )
			. ' · ' . esc_html( mysql2date( get_option( 'date_format' ), $app->created_at ) )
			. '</div>';
		echo '</td>';

		// Status column.
		echo '<td class="ff-status"><span class="ff-badge ff-badge--' . esc_attr( $app->status ) . '">'
			. esc_html( self::status_label( $app->status ) ) . '</span></td>';

		// Actions column: buttons appropriate to the current status.
		echo '<td class="ff-actions">';
		self::render_actions( $app );
		echo '</td>';

		echo '</tr>';
	}

	/**
	 * Render the moderation buttons appropriate to an application's status.
	 *
	 * @param object $app The ff_applications row.
	 */
	private static function render_actions( $app ) {
		if ( 'pending' === $app->status ) {
			self::action_button( $app->id, 'approve_35', __( 'Approve → The 35', 'founding-faces' ), 'primary' );
			self::action_button( $app->id, 'approve_circle', __( 'Approve → The Circle', 'founding-faces' ) );
			self::action_button( $app->id, 'decline', __( 'Decline', 'founding-faces' ), 'link-delete', true );
			return;
		}

		if ( in_array( $app->status, array( 'approved-35', 'approved-circle' ), true ) ) {
			// A Circle member can be elevated into The 35 at any time.
			if ( 'approved-circle' === $app->status ) {
				self::action_button( $app->id, 'promote_35', __( 'Promote → The 35', 'founding-faces' ), 'primary', true );
			}
			self::action_button( $app->id, 'resend', __( 'Resend welcome', 'founding-faces' ) );
			self::action_button( $app->id, 'withdraw', __( 'Withdraw', 'founding-faces' ), 'link-delete', true );
			return;
		}

		echo '<span class="ff-muted">' . esc_html__( 'No actions', 'founding-faces' ) . '</span>';
	}

	/**
	 * Render a single moderation button as its own nonce-protected mini form.
	 *
	 * @param int    $app_id  The application id.
	 * @param string $sub     The sub-action ('approve_35', 'decline', etc.).
	 * @param string $label   The button text.
	 * @param string $variant Optional button style ('primary', 'link-delete').
	 * @param bool   $confirm Whether to ask for confirmation before submitting.
	 */
	private static function action_button( $app_id, $sub, $label, $variant = '', $confirm = false ) {
		$classes = 'button';
		if ( 'primary' === $variant ) {
			$classes .= ' button-primary';
		} elseif ( 'link-delete' === $variant ) {
			$classes = 'button-link button-link-delete';
		}

		$onclick = $confirm
			? ' onclick="return confirm(\'' . esc_js( __( 'Are you sure? This can\'t be undone.', 'founding-faces' ) ) . '\');"'
			: '';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ff-action-form">';
		echo '<input type="hidden" name="action" value="' . esc_attr( FF_Members::MODERATE_ACTION ) . '" />';
		echo '<input type="hidden" name="ff_sub" value="' . esc_attr( $sub ) . '" />';
		echo '<input type="hidden" name="ff_app_id" value="' . esc_attr( $app_id ) . '" />';
		wp_nonce_field( FF_Members::MODERATE_ACTION . '_' . $app_id );
		// The button text is a fixed, translated string, so it's safe to print.
		echo '<button type="submit" class="' . esc_attr( $classes ) . '"' . $onclick . '>' . esc_html( $label ) . '</button>';
		echo '</form>';
	}

	/**
	 * Turn a stored status value into a readable label.
	 *
	 * @param string $status The stored status.
	 * @return string A human-friendly label.
	 */
	private static function status_label( $status ) {
		$labels = array(
			'pending'          => __( 'Pending', 'founding-faces' ),
			'approved-35'      => __( 'The 35', 'founding-faces' ),
			'approved-circle'  => __( 'The Circle', 'founding-faces' ),
			'declined'         => __( 'Declined', 'founding-faces' ),
			'withdrawn'        => __( 'Withdrawn', 'founding-faces' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * Render the notice describing the outcome of the last moderation action.
	 *
	 * The message is chosen from a fixed map keyed by the code in the URL, so no
	 * user-supplied text is ever echoed.
	 */
	private static function render_notice() {
		if ( ! isset( $_GET['ff_msg'] ) ) {
			return;
		}
		$code = sanitize_key( wp_unslash( $_GET['ff_msg'] ) );

		$success = array(
			'approved_35'     => __( 'Approved into The 35 and the member was created.', 'founding-faces' ),
			'approved_circle' => __( 'Approved into The Circle and the member was created.', 'founding-faces' ),
			'promoted_35'     => __( 'Member promoted into The 35 and sent the congratulations email.', 'founding-faces' ),
			'declined'        => __( 'Application declined.', 'founding-faces' ),
			'withdrawn'       => __( 'Member withdrawn; their number has been retired.', 'founding-faces' ),
			'resent'          => __( 'Welcome email resent.', 'founding-faces' ),
		);

		$errors = array(
			'ff_not_found'        => __( 'That application could not be found.', 'founding-faces' ),
			'ff_already'          => __( 'That application has already been approved.', 'founding-faces' ),
			'ff_email_exists'     => __( 'A WordPress user already exists with that email address.', 'founding-faces' ),
			'ff_no_member'        => __( 'That application has no member yet.', 'founding-faces' ),
			'ff_already_35'       => __( 'That member is already one of The 35.', 'founding-faces' ),
			'ff_deactivated_member' => __( 'That member is no longer active.', 'founding-faces' ),
			'error'               => __( 'Something went wrong. Please try again.', 'founding-faces' ),
		);

		if ( isset( $success[ $code ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $success[ $code ] ) . '</p></div>';
		} elseif ( isset( $errors[ $code ] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $errors[ $code ] ) . '</p></div>';
		}
	}
}
