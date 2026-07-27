<?php
/**
 * The private member <-> admin concierge channel.
 *
 * A member can give feedback on a note, or ask Nick a question. Either starts a
 * private thread. Nick reads and replies from an admin screen; the member is
 * emailed the reply and sees a "new message" flag in their portal, where they
 * can read it and reply again. It is strictly private and one relationship deep:
 * always a member and Nick, never member-to-member, never public. This keeps the
 * "publication, not conversation" principle intact while letting members feel
 * genuinely heard.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Messages
 */
class FF_Messages {

	// Admin-post actions used by the member-facing forms.
	const ACTION_SUBMIT = 'ff_msg_submit';
	const ACTION_REPLY  = 'ff_msg_reply';

	/**
	 * The messages table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'ff_messages';
	}

	/**
	 * Wire up the shortcodes, the member form handlers and the widgets.
	 */
	public static function register() {
		add_shortcode( 'ff_feedback', array( __CLASS__, 'sc_feedback' ) );
		add_shortcode( 'ff_ask', array( __CLASS__, 'sc_ask' ) );
		add_shortcode( 'ff_messages', array( __CLASS__, 'sc_messages' ) );

		// Members are logged in, so only the priv handlers are needed.
		add_action( 'admin_post_' . self::ACTION_SUBMIT, array( __CLASS__, 'handle_submit' ) );
		add_action( 'admin_post_' . self::ACTION_REPLY, array( __CLASS__, 'handle_reply' ) );

		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widgets' ) );
	}

	/**
	 * A member's feedback threads (roots), newest first.
	 *
	 * Used by the personal-history "Feedback you've shared" section, so feedback
	 * shows in one consistent place — read straight from this channel.
	 *
	 * @param int $member_id The member's user id.
	 * @return array Root feedback message rows.
	 */
	public static function feedback_threads_for_member( $member_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM " . self::table() . " WHERE member_id = %d AND thread_id = id AND context = 'feedback' ORDER BY id DESC",
			$member_id
		) );
	}

	/**
	 * Register the three Elementor widgets.
	 *
	 * @param object $widgets_manager Elementor's widgets manager.
	 */
	public static function register_widgets( $widgets_manager ) {
		require_once FF_PATH . 'includes/class-ff-messages-widgets.php';
		$widgets_manager->register( new FF_Feedback_Widget() );
		$widgets_manager->register( new FF_Ask_Widget() );
		$widgets_manager->register( new FF_Messages_Widget() );
	}

	/**
	 * The URL of the member's portal (where they read messages).
	 *
	 * Filterable so the reply-notification button can point at the right page.
	 * Defaults to the site home.
	 *
	 * @return string
	 */
	public static function portal_url() {
		return apply_filters( 'ff_portal_url', home_url( '/' ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Data API.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Start a new thread from a member (feedback or a question).
	 *
	 * @param int    $member_id The member's user id.
	 * @param string $context   'feedback' or 'question'.
	 * @param int    $reference_id The note/product id for feedback, else 0.
	 * @param string $subject   Optional subject.
	 * @param string $body      The message text.
	 * @return int|false The new message id, or false on failure.
	 */
	public static function start_thread( $member_id, $context, $reference_id, $subject, $body ) {
		global $wpdb;

		$context = in_array( $context, array( 'feedback', 'question' ), true ) ? $context : 'question';
		$body    = trim( (string) $body );
		if ( '' === $body ) {
			return false;
		}

		$now = current_time( 'mysql' );
		$ok  = $wpdb->insert(
			self::table(),
			array(
				'member_id'    => (int) $member_id,
				'thread_id'    => 0,
				'sender'       => 'member',
				'context'      => $context,
				'reference_id' => $reference_id ? (int) $reference_id : null,
				'subject'      => mb_substr( $subject, 0, 200 ),
				'body'         => $body,
				'member_read'  => 1, // The member wrote it; they've seen it.
				'admin_read'   => 0, // New for Nick.
				'created_at'   => $now,
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s' )
		);

		if ( ! $ok ) {
			return false;
		}

		$id = (int) $wpdb->insert_id;
		// The root of a thread points at itself.
		$wpdb->update( self::table(), array( 'thread_id' => $id ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );

		// Log feedback on the spine for provenance (brief: "feedback submitted").
		if ( 'feedback' === $context && class_exists( 'FF_Interactions' ) ) {
			FF_Interactions::log( $member_id, 'feedback_submitted', $id );
		}

		self::notify_admin( $id );
		return $id;
	}

	/**
	 * Add a reply to a thread, from either side.
	 *
	 * @param int    $thread_id The thread (root message) id.
	 * @param string $sender    'member' or 'admin'.
	 * @param string $body      The reply text.
	 * @return int|false The new message id, or false on failure.
	 */
	public static function add_reply( $thread_id, $sender, $body ) {
		global $wpdb;

		$root = self::thread_root( $thread_id );
		$body = trim( (string) $body );
		if ( ! $root || '' === $body ) {
			return false;
		}

		$sender = ( 'admin' === $sender ) ? 'admin' : 'member';
		$now    = current_time( 'mysql' );

		$ok = $wpdb->insert(
			self::table(),
			array(
				'member_id'    => (int) $root->member_id,
				'thread_id'    => (int) $root->id,
				'sender'       => $sender,
				'context'      => $root->context,
				'reference_id' => $root->reference_id ? (int) $root->reference_id : null,
				'subject'      => '',
				'body'         => $body,
				// If Nick replies, it's unread by the member (and vice-versa).
				'member_read'  => ( 'admin' === $sender ) ? 0 : 1,
				'admin_read'   => ( 'admin' === $sender ) ? 1 : 0,
				'created_at'   => $now,
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s' )
		);

		if ( ! $ok ) {
			return false;
		}

		$id = (int) $wpdb->insert_id;
		if ( 'admin' === $sender ) {
			self::notify_member( $id );
		} else {
			self::notify_admin( $id );
		}
		return $id;
	}

	/**
	 * Fetch the root row of a thread.
	 *
	 * @param int $thread_id The thread id.
	 * @return object|null
	 */
	public static function thread_root( $thread_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::table() . " WHERE id = %d AND thread_id = %d", $thread_id, $thread_id ) );
	}

	/**
	 * All messages in a thread, oldest first.
	 *
	 * @param int $thread_id The thread id.
	 * @return array
	 */
	public static function thread_messages( $thread_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . self::table() . " WHERE thread_id = %d ORDER BY id ASC", $thread_id ) );
	}

	/**
	 * A member's threads (roots), most recently active first.
	 *
	 * @param int $member_id The member's user id.
	 * @return array
	 */
	public static function threads_for_member( $member_id ) {
		global $wpdb;
		$t = self::table();
		// Order roots by the newest message in their thread.
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT r.*, ( SELECT MAX(m.id) FROM {$t} m WHERE m.thread_id = r.id ) AS last_id
			 FROM {$t} r
			 WHERE r.member_id = %d AND r.thread_id = r.id
			 ORDER BY last_id DESC",
			$member_id
		) );
	}

	/**
	 * All threads across all members, newest activity first (admin inbox).
	 *
	 * @return array
	 */
	public static function all_threads() {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_results(
			"SELECT r.*,
				( SELECT MAX(m.id) FROM {$t} m WHERE m.thread_id = r.id ) AS last_id,
				( SELECT COUNT(*) FROM {$t} m WHERE m.thread_id = r.id AND m.sender = 'member' AND m.admin_read = 0 ) AS unread_admin
			 FROM {$t} r
			 WHERE r.thread_id = r.id
			 ORDER BY last_id DESC"
		);
	}

	/**
	 * Count of unread (by the member) admin replies for a member.
	 *
	 * @param int $member_id The member's user id.
	 * @return int
	 */
	public static function unread_for_member( $member_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM " . self::table() . " WHERE member_id = %d AND sender = 'admin' AND member_read = 0",
			$member_id
		) );
	}

	/**
	 * Count of unread (by admin) member messages across everyone.
	 *
	 * @return int
	 */
	public static function unread_for_admin() {
		global $wpdb;
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM " . self::table() . " WHERE sender = 'member' AND admin_read = 0"
		);
	}

	/**
	 * Mark every admin message in a thread as read by the member.
	 *
	 * @param int $thread_id The thread id.
	 * @param int $member_id The member's user id (ownership guard).
	 * @return void
	 */
	public static function mark_read_by_member( $thread_id, $member_id ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			"UPDATE " . self::table() . " SET member_read = 1 WHERE thread_id = %d AND member_id = %d AND sender = 'admin'",
			$thread_id, $member_id
		) );
	}

	/**
	 * Mark every member message in a thread as read by admin.
	 *
	 * @param int $thread_id The thread id.
	 * @return void
	 */
	public static function mark_read_by_admin( $thread_id ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			"UPDATE " . self::table() . " SET admin_read = 1 WHERE thread_id = %d AND sender = 'member'",
			$thread_id
		) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Member form handlers.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Handle a member starting a thread (feedback or question).
	 *
	 * @return void
	 */
	public static function handle_submit() {
		$redirect = isset( $_POST['ff_return'] ) ? esc_url_raw( wp_unslash( $_POST['ff_return'] ) ) : home_url();

		if ( ! is_user_logged_in() || ! FF_Gating::is_member() ) {
			wp_die( esc_html__( 'You need to be a logged-in member to do that.', 'founding-faces' ) );
		}
		check_admin_referer( self::ACTION_SUBMIT );

		$member_id = get_current_user_id();
		$context   = isset( $_POST['ff_context'] ) ? sanitize_key( wp_unslash( $_POST['ff_context'] ) ) : 'question';
		$reference = isset( $_POST['ff_reference'] ) ? absint( wp_unslash( $_POST['ff_reference'] ) ) : 0;
		$subject   = isset( $_POST['ff_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['ff_subject'] ) ) : '';
		$body      = isset( $_POST['ff_body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ff_body'] ) ) : '';

		$sent = self::start_thread( $member_id, $context, $reference, $subject, $body );

		wp_safe_redirect( add_query_arg( 'ff_msg', $sent ? 'sent' : 'error', $redirect ) );
		exit;
	}

	/**
	 * Handle a member replying within their own thread.
	 *
	 * @return void
	 */
	public static function handle_reply() {
		$redirect = isset( $_POST['ff_return'] ) ? esc_url_raw( wp_unslash( $_POST['ff_return'] ) ) : home_url();

		if ( ! is_user_logged_in() || ! FF_Gating::is_member() ) {
			wp_die( esc_html__( 'You need to be a logged-in member to do that.', 'founding-faces' ) );
		}

		$thread_id = isset( $_POST['ff_thread'] ) ? absint( wp_unslash( $_POST['ff_thread'] ) ) : 0;
		check_admin_referer( self::ACTION_REPLY . '_' . $thread_id );

		$member_id = get_current_user_id();
		$root      = self::thread_root( $thread_id );

		// A member may only reply within their own thread.
		if ( $root && (int) $root->member_id === (int) $member_id ) {
			$body = isset( $_POST['ff_body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ff_body'] ) ) : '';
			self::add_reply( $thread_id, 'member', $body );
		}

		wp_safe_redirect( add_query_arg( 'ff_msg', 'sent', $redirect ) );
		exit;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Notifications (branded email, both directions).
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Email Nick that a member has sent a new message.
	 *
	 * @param int $message_id The message row id.
	 * @return void
	 */
	private static function notify_admin( $message_id ) {
		global $wpdb;
		$msg = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::table() . " WHERE id = %d", $message_id ) );
		if ( ! $msg ) {
			return;
		}

		$member  = get_userdata( $msg->member_id );
		$who     = $member ? FF_Members::admin_label( $msg->member_id ) : __( 'A member', 'founding-faces' );
		$label   = ( 'feedback' === $msg->context ) ? __( 'feedback', 'founding-faces' ) : __( 'a question', 'founding-faces' );
		$ref     = $msg->reference_id ? get_the_title( (int) $msg->reference_id ) : '';
		$admin_url = admin_url( 'admin.php?page=founding-faces-messages&thread=' . (int) $msg->thread_id );

		$body  = '<p>' . sprintf(
			/* translators: 1: member identity, 2: "feedback" or "a question". */
			esc_html__( '%1$s has sent %2$s.', 'founding-faces' ),
			esc_html( $who ),
			esc_html( $label )
		) . '</p>';
		if ( $ref ) {
			$body .= '<p><strong>' . esc_html__( 'On:', 'founding-faces' ) . '</strong> ' . esc_html( $ref ) . '</p>';
		}
		$body .= '<blockquote style="margin:0;padding:12px 16px;border-left:3px solid #d5d8dd;">' . nl2br( esc_html( $msg->body ) ) . '</blockquote>';

		$html = FF_Email_Template::build( array(
			'heading'   => __( 'New member message', 'founding-faces' ),
			'body_html' => $body,
			'cta'       => array( 'label' => __( 'Read and reply', 'founding-faces' ), 'url' => $admin_url ),
			'preheader' => __( 'A member has been in touch.', 'founding-faces' ),
		) );

		wp_mail(
			get_option( 'admin_email' ),
			sprintf( __( '[Founding Faces] New message from %s', 'founding-faces' ), $who ),
			$html,
			array( 'Content-Type: text/html; charset=UTF-8' )
		);
	}

	/**
	 * Email the member that Nick has replied.
	 *
	 * @param int $message_id The reply message row id.
	 * @return void
	 */
	private static function notify_member( $message_id ) {
		global $wpdb;
		$msg = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::table() . " WHERE id = %d", $message_id ) );
		if ( ! $msg ) {
			return;
		}
		$member = get_userdata( $msg->member_id );
		if ( ! $member ) {
			return;
		}

		$body  = '<p>' . esc_html__( 'You have a new reply in your Founding Faces portal.', 'founding-faces' ) . '</p>';
		$body .= '<blockquote style="margin:0;padding:12px 16px;border-left:3px solid #d5d8dd;">' . nl2br( esc_html( $msg->body ) ) . '</blockquote>';
		$body .= '<p>' . esc_html__( 'Sign in to read it in full and reply.', 'founding-faces' ) . '</p>';

		$html = FF_Email_Template::build( array(
			'heading'   => __( 'A reply from Founding Faces', 'founding-faces' ),
			'body_html' => $body,
			'cta'       => array( 'label' => __( 'Open my portal', 'founding-faces' ), 'url' => self::portal_url() ),
			'preheader' => __( 'You have a new message.', 'founding-faces' ),
		) );

		wp_mail(
			$member->user_email,
			__( 'You have a new message in Founding Faces', 'founding-faces' ),
			$html,
			array( 'Content-Type: text/html; charset=UTF-8' )
		);
	}

	/*
	 * -----------------------------------------------------------------------
	 * Shortcodes / front-end renderers.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Enqueue the plugin stylesheet.
	 */
	private static function enqueue() {
		wp_enqueue_style( 'founding-faces', FF_URL . 'assets/css/founding-faces.css', array(), FF_VERSION );
	}

	/**
	 * The [ff_feedback] shortcode: give feedback on a note.
	 *
	 * @param array $atts Optional 'reference' (note id); defaults to the current note.
	 * @return string
	 */
	public static function sc_feedback( $atts = array() ) {
		$atts = shortcode_atts( array( 'reference' => 0 ), $atts );
		$ref  = absint( $atts['reference'] );
		if ( ! $ref ) {
			$qid = get_queried_object_id();
			if ( $qid && 'ff_note' === get_post_type( $qid ) ) {
				$ref = $qid;
			}
		}
		return self::render_compose_form( 'feedback', $ref );
	}

	/**
	 * The [ff_ask] shortcode: ask Nick a private question.
	 *
	 * @return string
	 */
	public static function sc_ask() {
		return self::render_compose_form( 'question', 0 );
	}

	/**
	 * Render a compose form for feedback or a question.
	 *
	 * @param string $context 'feedback' or 'question'.
	 * @param int    $reference The note id for feedback.
	 * @return string
	 */
	public static function render_compose_form( $context, $reference ) {
		self::enqueue();

		// Members compose here; in the Elementor editor we still render the form
		// (with no live data) so it can be styled before there are members.
		if ( ! FF_Gating::is_member() && ! FF_History::is_editor() ) {
			return FF_Display::members_only_notice();
		}

		$is_feedback = ( 'feedback' === $context );
		$sent        = isset( $_GET['ff_msg'] ) && 'sent' === sanitize_key( wp_unslash( $_GET['ff_msg'] ) );

		ob_start();
		if ( $sent ) {
			echo '<div class="ff-notice ff-notice--success">' . esc_html( $is_feedback
				? __( 'Thank you — your feedback has been sent.', 'founding-faces' )
				: __( 'Thank you — your message has been sent. We\'ll reply in your portal.', 'founding-faces' ) ) . '</div>';
		}
		?>
		<form class="ff-form ff-form--full ff-message-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_SUBMIT ); ?>" />
			<input type="hidden" name="ff_context" value="<?php echo esc_attr( $context ); ?>" />
			<input type="hidden" name="ff_reference" value="<?php echo esc_attr( $reference ); ?>" />
			<input type="hidden" name="ff_return" value="<?php echo esc_url( self::current_url() ); ?>" />
			<?php wp_nonce_field( self::ACTION_SUBMIT ); ?>

			<?php if ( ! $is_feedback ) : ?>
				<p class="ff-field">
					<label for="ff-msg-subject"><?php esc_html_e( 'Subject', 'founding-faces' ); ?></label>
					<input type="text" id="ff-msg-subject" name="ff_subject" />
				</p>
			<?php endif; ?>

			<p class="ff-field">
				<label for="ff-msg-body">
					<?php echo esc_html( $is_feedback
						? __( 'Your feedback', 'founding-faces' )
						: __( 'Your message', 'founding-faces' ) ); ?>
					<span class="ff-required">*</span>
				</label>
				<textarea id="ff-msg-body" name="ff_body" rows="5" required></textarea>
				<span class="ff-hint"><?php echo esc_html( $is_feedback
					? __( 'Private — this goes to Nick only, never shown to other members.', 'founding-faces' )
					: __( 'Private — this goes to Nick only. You\'ll get the reply in your portal and by email.', 'founding-faces' ) ); ?></span>
			</p>

			<p class="ff-submit">
				<button type="submit"><?php echo esc_html( $is_feedback
					? __( 'Send feedback', 'founding-faces' )
					: __( 'Send message', 'founding-faces' ) ); ?></button>
			</p>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * The [ff_messages] shortcode: the member's private message centre.
	 *
	 * @return string
	 */
	public static function sc_messages() {
		self::enqueue();

		if ( ! FF_Gating::is_member() ) {
			if ( FF_History::is_editor() ) {
				return self::render_sample_messages();
			}
			return FF_Display::members_only_notice();
		}

		$member_id = get_current_user_id();

		// Opening a thread marks its admin replies as read.
		$open = isset( $_GET['ff_thread'] ) ? absint( wp_unslash( $_GET['ff_thread'] ) ) : 0;
		if ( $open ) {
			$root = self::thread_root( $open );
			if ( $root && (int) $root->member_id === (int) $member_id ) {
				self::mark_read_by_member( $open, $member_id );
			} else {
				$open = 0;
			}
		}

		ob_start();
		echo '<div class="ff-messages">';

		if ( $open ) {
			echo self::render_thread_view( $open ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo self::render_thread_list( $member_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * A static sample of the message centre, for the Elementor editor.
	 *
	 * @return string
	 */
	private static function render_sample_messages() {
		$rows = array(
			array( __( 'Feedback: Trial 12 — stability at 40°C', 'founding-faces' ), true ),
			array( __( 'Your question', 'founding-faces' ), false ),
		);
		$out  = '<div class="ff-messages"><h3 class="ff-history-heading">' . esc_html__( 'Your messages', 'founding-faces' ) . '</h3>';
		$out .= '<ul class="ff-history-list ff-message-threads">';
		foreach ( $rows as $r ) {
			$out .= '<li class="ff-history-item ff-message-thread' . ( $r[1] ? ' is-unread' : '' ) . '">';
			$out .= '<div class="ff-history-item-body"><span class="ff-history-item-main"><a href="#">' . esc_html( $r[0] ) . '</a>';
			if ( $r[1] ) {
				$out .= ' <span class="ff-message-badge">' . esc_html__( 'New message', 'founding-faces' ) . '</span>';
			}
			$out .= '</span></div><span class="ff-history-item-date">' . esc_html( self::format_date( current_time( 'mysql' ) ) ) . '</span></li>';
		}
		$out .= '</ul></div>';
		return $out;
	}

	/**
	 * Render a member's list of threads, with unread flags.
	 *
	 * @param int $member_id The member's user id.
	 * @return string
	 */
	private static function render_thread_list( $member_id ) {
		$threads = self::threads_for_member( $member_id );

		$out  = '<h3 class="ff-history-heading">' . esc_html__( 'Your messages', 'founding-faces' ) . '</h3>';

		if ( empty( $threads ) ) {
			$out .= '<p class="ff-empty-note">' . esc_html__( 'You have no messages yet.', 'founding-faces' ) . '</p>';
			return $out;
		}

		// A summary line at the top when there's an unread reply.
		$unread_total = self::unread_for_member( $member_id );
		if ( $unread_total > 0 ) {
			$out .= '<p class="ff-message-summary"><span class="ff-message-badge">' . esc_html(
				sprintf(
					/* translators: %d is the number of new messages. */
					_n( '%d new message', '%d new messages', $unread_total, 'founding-faces' ),
					$unread_total
				)
			) . '</span></p>';
		}

		$out .= '<ul class="ff-history-list ff-message-threads">';
		foreach ( $threads as $t ) {
			$unread = self::thread_unread_for_member( (int) $t->id, $member_id );
			$title  = self::thread_title( $t );
			$url    = add_query_arg( 'ff_thread', (int) $t->id, self::current_url() );

			$out .= '<li class="ff-history-item ff-message-thread' . ( $unread ? ' is-unread' : '' ) . '">';
			$out .= '<div class="ff-history-item-body">';
			$out .= '<span class="ff-history-item-main"><a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a>';
			if ( $unread ) {
				$out .= ' <span class="ff-message-badge">' . esc_html__( 'New message', 'founding-faces' ) . '</span>';
			}
			$out .= '</span>';
			$out .= '</div>';
			$out .= '<span class="ff-history-item-date">' . esc_html( self::format_date( $t->created_at ) ) . '</span>';
			$out .= '</li>';
		}
		$out .= '</ul>';
		return $out;
	}

	/**
	 * Render a single thread with its messages and a reply box.
	 *
	 * @param int $thread_id The thread id.
	 * @return string
	 */
	private static function render_thread_view( $thread_id ) {
		$root     = self::thread_root( $thread_id );
		$messages = self::thread_messages( $thread_id );
		$back     = remove_query_arg( 'ff_thread', self::current_url() );

		$out  = '<p class="ff-message-back"><a href="' . esc_url( $back ) . '">&larr; ' . esc_html__( 'All messages', 'founding-faces' ) . '</a></p>';
		$out .= '<h3 class="ff-history-heading">' . esc_html( self::thread_title( $root ) ) . '</h3>';

		$out .= '<div class="ff-message-thread-view">';
		foreach ( $messages as $m ) {
			$mine = ( 'member' === $m->sender );
			$who  = $mine ? __( 'You', 'founding-faces' ) : get_bloginfo( 'name' );
			$out .= '<div class="ff-message ff-message--' . ( $mine ? 'member' : 'admin' ) . '">';
			$out .= '<div class="ff-message-meta"><strong>' . esc_html( $who ) . '</strong> · ' . esc_html( self::format_date( $m->created_at ) ) . '</div>';
			$out .= '<div class="ff-message-body">' . nl2br( esc_html( $m->body ) ) . '</div>';
			$out .= '</div>';
		}
		$out .= '</div>';

		// Reply box.
		$out .= '<form class="ff-form ff-form--full ff-message-reply" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$out .= '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_REPLY ) . '" />';
		$out .= '<input type="hidden" name="ff_thread" value="' . esc_attr( $thread_id ) . '" />';
		$out .= '<input type="hidden" name="ff_return" value="' . esc_url( add_query_arg( 'ff_thread', $thread_id, self::current_url() ) ) . '" />';
		$out .= wp_nonce_field( self::ACTION_REPLY . '_' . $thread_id, '_wpnonce', true, false );
		$out .= '<p class="ff-field"><label for="ff-reply-body">' . esc_html__( 'Reply', 'founding-faces' ) . '</label>';
		$out .= '<textarea id="ff-reply-body" name="ff_body" rows="4" required></textarea></p>';
		$out .= '<p class="ff-submit"><button type="submit">' . esc_html__( 'Send reply', 'founding-faces' ) . '</button></p>';
		$out .= '</form>';

		return $out;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Small helpers.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Count of unread (by member) admin messages in one thread.
	 *
	 * @param int $thread_id The thread id.
	 * @param int $member_id The member's user id.
	 * @return int
	 */
	private static function thread_unread_for_member( $thread_id, $member_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM " . self::table() . " WHERE thread_id = %d AND member_id = %d AND sender = 'admin' AND member_read = 0",
			$thread_id, $member_id
		) );
	}

	/**
	 * A readable title for a thread.
	 *
	 * @param object $root The root message row.
	 * @return string
	 */
	public static function thread_title( $root ) {
		if ( ! $root ) {
			return __( 'Message', 'founding-faces' );
		}
		if ( 'feedback' === $root->context ) {
			$ref = $root->reference_id ? get_the_title( (int) $root->reference_id ) : '';
			return $ref
				? sprintf( __( 'Feedback: %s', 'founding-faces' ), $ref )
				: __( 'Feedback', 'founding-faces' );
		}
		return '' !== trim( (string) $root->subject ) ? $root->subject : __( 'Your question', 'founding-faces' );
	}

	/**
	 * Format a stored datetime to the site's date format.
	 *
	 * @param string $datetime A stored MySQL datetime.
	 * @return string
	 */
	private static function format_date( $datetime ) {
		return mysql2date( get_option( 'date_format' ), $datetime );
	}

	/**
	 * The URL of the current page.
	 *
	 * @return string
	 */
	private static function current_url() {
		global $wp;
		return home_url( add_query_arg( array(), $wp->request ) );
	}
}
