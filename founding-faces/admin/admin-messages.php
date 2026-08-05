<?php
/**
 * The admin "Messages" screen, Nick's side of the concierge channel.
 *
 * Lists every member thread newest-first with an unread bubble, opens a thread
 * to read the full conversation, and lets Nick reply. A reply emails the member
 * and lights up the "new message" flag in their portal.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Admin_Messages
 */
class FF_Admin_Messages {

	const PAGE_SLUG = 'founding-faces-messages';
	const ACTION    = 'ff_admin_msg_reply';

	/**
	 * Wire up the menu and the reply handler.
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_reply' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Add the Messages submenu, with an unread-count bubble.
	 */
	public static function add_menu() {
		$unread = FF_Messages::unread_for_admin();
		$bubble = $unread
			? ' <span class="awaiting-mod count-' . (int) $unread . '"><span class="pending-count">' . (int) $unread . '</span></span>'
			: '';

		add_submenu_page(
			FF_Admin_Applications::PAGE_SLUG,
			__( 'Messages', 'founding-faces' ),
			__( 'Messages', 'founding-faces' ) . $bubble,
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Load the shared admin stylesheet on this screen.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public static function enqueue( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE_SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'ff-admin', FF_URL . 'admin/admin-style.css', array(), FF_VERSION );
	}

	/**
	 * Render the inbox, or a single thread.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$thread = isset( $_GET['thread'] ) ? absint( wp_unslash( $_GET['thread'] ) ) : 0;

		echo '<div class="wrap ff-admin">';
		echo '<h1>' . esc_html__( 'Founding Faces Messages', 'founding-faces' ) . '</h1>';

		if ( isset( $_GET['ff_sent'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Reply sent.', 'founding-faces' ) . '</p></div>';
		}
		if ( isset( $_GET['ff_err'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Reply not sent: attachments must be a JPG, PNG, GIF or PDF under 8 MB.', 'founding-faces' ) . '</p></div>';
		}

		if ( $thread ) {
			self::render_thread( $thread );
		} else {
			self::render_inbox();
		}

		echo '</div>';
	}

	/**
	 * Render the list of all threads.
	 */
	private static function render_inbox() {
		$threads = FF_Messages::all_threads();

		if ( empty( $threads ) ) {
			echo '<p class="ff-empty">' . esc_html__( 'No messages yet.', 'founding-faces' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped ff-queue"><thead><tr>';
		echo '<th>' . esc_html__( 'Member', 'founding-faces' ) . '</th>';
		echo '<th>' . esc_html__( 'Subject', 'founding-faces' ) . '</th>';
		echo '<th>' . esc_html__( 'Updated', 'founding-faces' ) . '</th>';
		echo '<th></th></tr></thead><tbody>';

		foreach ( $threads as $t ) {
			$who = FF_Members::admin_label( (int) $t->member_id );
			$url = add_query_arg( array( 'page' => self::PAGE_SLUG, 'thread' => (int) $t->id ), admin_url( 'admin.php' ) );

			echo '<tr>';
			echo '<td><strong>' . esc_html( $who ) . '</strong>';
			if ( (int) $t->unread_admin > 0 ) {
				echo ' <span class="ff-badge ff-badge--pending">' . esc_html__( 'New', 'founding-faces' ) . '</span>';
			}
			echo '</td>';
			echo '<td><a href="' . esc_url( $url ) . '">' . esc_html( FF_Messages::thread_title( $t ) ) . '</a></td>';
			echo '<td>' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $t->created_at ) ) . '</td>';
			echo '<td><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Open', 'founding-faces' ) . '</a></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Render a single thread with a reply box. Opening it marks it read.
	 *
	 * @param int $thread_id The thread id.
	 */
	private static function render_thread( $thread_id ) {
		$root = FF_Messages::thread_root( $thread_id );
		if ( ! $root ) {
			echo '<p class="ff-empty">' . esc_html__( 'That conversation could not be found.', 'founding-faces' ) . '</p>';
			return;
		}

		// Reading it clears the unread flag for Nick.
		FF_Messages::mark_read_by_admin( $thread_id );

		$who      = FF_Members::admin_label( (int) $root->member_id );
		$member   = get_userdata( (int) $root->member_id );
		$messages = FF_Messages::thread_messages( $thread_id );
		$back     = add_query_arg( array( 'page' => self::PAGE_SLUG ), admin_url( 'admin.php' ) );

		echo '<p><a href="' . esc_url( $back ) . '">&larr; ' . esc_html__( 'All messages', 'founding-faces' ) . '</a></p>';
		echo '<h2>' . esc_html( FF_Messages::thread_title( $root ) ) . '</h2>';
		echo '<p class="description">' . esc_html( $who );
		if ( $member ) {
			echo ' · <a href="mailto:' . esc_attr( $member->user_email ) . '">' . esc_html( $member->user_email ) . '</a>';
		}

		// The note this feedback is about, linked, so reading a thread never
		// means guessing which note the member meant.
		if ( ! empty( $root->reference_id ) ) {
			$ref_id    = (int) $root->reference_id;
			$ref_title = get_the_title( $ref_id );
			$ref_edit  = get_edit_post_link( $ref_id );
			if ( $ref_title ) {
				echo ' · ' . esc_html__( 'About:', 'founding-faces' ) . ' ';
				echo $ref_edit // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					? '<a href="' . esc_url( $ref_edit ) . '">' . esc_html( $ref_title ) . '</a>'
					: esc_html( $ref_title );
			}
		}
		echo '</p>';

		echo '<div class="ff-admin-thread">';
		foreach ( $messages as $m ) {
			$is_admin = ( 'admin' === $m->sender );
			$label    = $is_admin ? __( 'You (Nick)', 'founding-faces' ) : $who;
			echo '<div class="ff-admin-message ff-admin-message--' . ( $is_admin ? 'admin' : 'member' ) . '" style="margin:0 0 12px;padding:12px 14px;border:1px solid #dcdcde;border-radius:6px;' . ( $is_admin ? 'background:#f0f6fc;' : 'background:#fff;' ) . '">';
			echo '<div style="font-size:12px;color:#646970;margin-bottom:4px;"><strong>' . esc_html( $label ) . '</strong> · ' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $m->created_at ) ) . '</div>';
			if ( '' !== trim( (string) $m->body ) ) {
				echo '<div>' . nl2br( esc_html( $m->body ) ) . '</div>';
			}
			$att = FF_Messages::attachment_link( $m );
			if ( '' !== $att ) {
				echo '<p style="margin:6px 0 0;"><a href="' . esc_url( $att ) . '" target="_blank" rel="noopener">&#128206; ' . esc_html( $m->attachment_name ? $m->attachment_name : __( 'View attachment', 'founding-faces' ) ) . '</a></p>';
			}
			echo '</div>';
		}
		echo '</div>';

		// Reply form.
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="max-width:640px;">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />';
		echo '<input type="hidden" name="thread" value="' . esc_attr( $thread_id ) . '" />';
		wp_nonce_field( self::ACTION . '_' . $thread_id );
		echo '<p><label for="ff-admin-reply"><strong>' . esc_html__( 'Your reply', 'founding-faces' ) . '</strong></label></p>';
		echo '<textarea id="ff-admin-reply" name="ff_body" rows="5" class="large-text"></textarea>';
		echo '<p><label for="ff-admin-file">' . esc_html__( 'Attach an image or PDF (optional):', 'founding-faces' ) . '</label> ';
		echo '<input type="file" id="ff-admin-file" name="ff_file" accept="' . esc_attr( FF_Messages::upload_accept_attr() ) . '" /></p>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Send reply', 'founding-faces' ) . '</button>';
		echo ' <span class="description">' . esc_html__( 'Emails the member and shows in their portal.', 'founding-faces' ) . '</span></p>';
		echo '</form>';
	}

	/**
	 * Process an admin reply.
	 */
	public static function handle_reply() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'founding-faces' ) );
		}
		$thread_id = isset( $_POST['thread'] ) ? absint( wp_unslash( $_POST['thread'] ) ) : 0;
		check_admin_referer( self::ACTION . '_' . $thread_id );

		$body       = isset( $_POST['ff_body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ff_body'] ) ) : '';
		$attachment = FF_Messages::handle_upload();

		$args = array( 'page' => self::PAGE_SLUG, 'thread' => $thread_id );
		if ( is_array( $attachment ) && isset( $attachment['error'] ) ) {
			$args['ff_err'] = 1;
		} elseif ( $thread_id && ( '' !== trim( $body ) || is_array( $attachment ) ) ) {
			FF_Messages::add_reply( $thread_id, 'admin', $body, $attachment );
			$args['ff_sent'] = 1;
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}
