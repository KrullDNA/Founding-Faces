<?php
/**
 * Telling The 35 about a new note.
 *
 * The 35 see every note, including the ones held back from The Circle, so they
 * are the one group an announcement never has to be filtered for. The Circle is
 * left to the email platform, where a list of that size belongs.
 *
 * Nothing is sent on publishing. Publishing is how a note is saved, and a note
 * gets edited and republished, so an email on publish would go out on a typo
 * fix. It is a button, pressed once, with a record of when it was pressed.
 *
 * The send is spread over several runs rather than fired in one go. Thirty-five
 * identical emails leaving a shared host inside a second is the shape of a
 * spam run, and the receiving end treats it as one.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Note_Mail
 */
class FF_Note_Mail {

	/** Who is still waiting, as an array of user ids. */
	const META_QUEUE = '_ff_note_mail_queue';

	/** A record of each send: when it started, how many went, how many failed. */
	const META_LOG = '_ff_note_mail_log';

	/** The cron hook that works through the queue. */
	const CRON_HOOK = 'ff_send_note_batch';

	/** How many emails one batch sends. */
	const OPT_BATCH = 'ff_note_mail_batch';

	/** How many minutes to wait between batches. */
	const OPT_GAP = 'ff_note_mail_gap';

	/**
	 * Hook the cron worker and the admin side up.
	 */
	public static function register() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_batch' ), 10, 1 );

		if ( is_admin() ) {
			add_action( 'add_meta_boxes', array( __CLASS__, 'add_metabox' ) );
			add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
			add_action( 'admin_post_ff_send_note', array( __CLASS__, 'handle_send' ) );
			add_action( 'admin_post_ff_send_note_now', array( __CLASS__, 'handle_send_now' ) );
			add_action( 'admin_post_ff_stop_note_mail', array( __CLASS__, 'handle_stop' ) );
		}
	}

	/*
	 * -----------------------------------------------------------------------
	 * The settings behind the pacing.
	 * -----------------------------------------------------------------------
	 */

	/** @return int How many emails go out in one batch. */
	public static function batch_size() {
		$size = (int) get_option( self::OPT_BATCH, 10 );
		return max( 1, min( 50, $size ) );
	}

	/** @return int How many minutes between batches. */
	public static function gap_minutes() {
		$gap = (int) get_option( self::OPT_GAP, 5 );
		return max( 1, min( 120, $gap ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Who gets it.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * The 35, minus anyone who has unsubscribed.
	 *
	 * A number is what makes someone one of The 35, so the number is what is
	 * asked for. Consent is checked per member rather than in the query,
	 * because the consent gate is filterable and a platform unsubscribe has to
	 * be able to reach it.
	 *
	 * @return int[] User ids.
	 */
	public static function recipients() {
		$ids = get_users( array(
			'meta_key'     => FF_Members::META_NUMBER, // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_compare' => 'EXISTS',
			'fields'       => 'ID',
			'orderby'      => 'ID',
			'order'        => 'ASC',
		) );

		$out = array();
		foreach ( $ids as $id ) {
			$id   = (int) $id;
			$user = get_userdata( $id );
			if ( ! $user ) {
				continue;
			}

			// A test account carrying a real address is included on purpose:
			// it is how you read what The 35 is sent. One still holding its
			// generated placeholder is not, because mail to it bounces, and a
			// run of bounces is how a sending domain's reputation goes.
			if ( FF_Members::is_placeholder_email( $user->user_email ) ) {
				continue;
			}

			if ( ! FF_Emails::may_email( $id ) ) {
				continue;
			}

			$out[] = $id;
		}

		return $out;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Sending.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Queue a note for sending and start the first batch.
	 *
	 * @param int $note_id The note.
	 * @return array|WP_Error The opening log row, or why it cannot go.
	 */
	public static function start( $note_id ) {
		$note_id = absint( $note_id );
		$note    = get_post( $note_id );

		if ( ! $note || FF_Post_Types::NOTE_CPT !== $note->post_type ) {
			return new WP_Error( 'ff_no_note', __( 'That note no longer exists.', 'founding-faces' ) );
		}

		// A draft has no address to send anyone to.
		if ( 'publish' !== $note->post_status ) {
			return new WP_Error( 'ff_not_published', __( 'Publish the note first. An email about a draft sends everyone to a page they cannot open.', 'founding-faces' ) );
		}

		if ( self::queue( $note_id ) ) {
			return new WP_Error( 'ff_in_progress', __( 'This note is already going out. Wait for it to finish, or stop it.', 'founding-faces' ) );
		}

		$ids = self::recipients();
		if ( empty( $ids ) ) {
			return new WP_Error( 'ff_no_one', __( 'Nobody to send to. The 35 is either empty or everyone in it has unsubscribed.', 'founding-faces' ) );
		}

		update_post_meta( $note_id, self::META_QUEUE, $ids );

		$log   = self::log( $note_id );
		$log[] = array(
			'started'  => current_time( 'mysql' ),
			'by'       => get_current_user_id(),
			'queued'   => count( $ids ),
			'sent'     => 0,
			'failed'   => 0,
			'finished' => '',
		);
		update_post_meta( $note_id, self::META_LOG, $log );

		// The first batch goes now, so the button does something visible
		// rather than waiting on the next time somebody loads the site.
		self::run_batch( $note_id );

		$log = self::log( $note_id );

		return end( $log );
	}

	/**
	 * Send one batch and schedule the next, if anyone is left.
	 *
	 * @param int $note_id The note.
	 */
	public static function run_batch( $note_id ) {
		$note_id = absint( $note_id );
		$queue   = self::queue( $note_id );
		if ( empty( $queue ) ) {
			return;
		}

		$batch = array_splice( $queue, 0, self::batch_size() );
		$sent  = 0;
		$fail  = 0;

		foreach ( $batch as $user_id ) {
			// Consent is read again rather than trusted from the queue. A send
			// spread over an hour can outlive somebody's decision to leave,
			// and the last thing an unsubscribe should do is arrive late.
			if ( ! FF_Emails::may_email( (int) $user_id ) ) {
				continue;
			}

			if ( FF_Emails::send_note( (int) $user_id, $note_id ) ) {
				$sent++;
			} else {
				$fail++;
			}
		}

		if ( empty( $queue ) ) {
			delete_post_meta( $note_id, self::META_QUEUE );
		} else {
			update_post_meta( $note_id, self::META_QUEUE, $queue );
			wp_schedule_single_event(
				time() + ( self::gap_minutes() * MINUTE_IN_SECONDS ),
				self::CRON_HOOK,
				array( $note_id )
			);
		}

		self::record( $note_id, $sent, $fail, empty( $queue ) );
	}

	/**
	 * Add a batch's result to the note's last log row.
	 *
	 * @param int  $note_id The note.
	 * @param int  $sent    How many went.
	 * @param int  $failed  How many did not.
	 * @param bool $done    Whether the queue is now empty.
	 */
	private static function record( $note_id, $sent, $failed, $done ) {
		$log = self::log( $note_id );
		if ( empty( $log ) ) {
			return;
		}

		$last = count( $log ) - 1;
		$log[ $last ]['sent']   = (int) $log[ $last ]['sent'] + (int) $sent;
		$log[ $last ]['failed'] = (int) $log[ $last ]['failed'] + (int) $failed;
		if ( $done ) {
			$log[ $last ]['finished'] = current_time( 'mysql' );
		}

		update_post_meta( $note_id, self::META_LOG, $log );
	}

	/**
	 * Stop a send that is part way through.
	 *
	 * @param int $note_id The note.
	 */
	public static function stop( $note_id ) {
		$note_id = absint( $note_id );
		wp_clear_scheduled_hook( self::CRON_HOOK, array( $note_id ) );
		delete_post_meta( $note_id, self::META_QUEUE );
		self::record( $note_id, 0, 0, true );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Reading the state.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Who is still waiting on this note.
	 *
	 * @param int $note_id The note.
	 * @return int[]
	 */
	public static function queue( $note_id ) {
		$queue = get_post_meta( absint( $note_id ), self::META_QUEUE, true );
		return is_array( $queue ) ? array_values( array_filter( array_map( 'absint', $queue ) ) ) : array();
	}

	/**
	 * Every send this note has had.
	 *
	 * @param int $note_id The note.
	 * @return array[]
	 */
	public static function log( $note_id ) {
		$log = get_post_meta( absint( $note_id ), self::META_LOG, true );
		return is_array( $log ) ? $log : array();
	}

	/*
	 * -----------------------------------------------------------------------
	 * The note screen.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Say what the last button press did.
	 */
	public static function notice() {
		if ( empty( $_GET['ff_note_mail'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$code = sanitize_key( wp_unslash( $_GET['ff_note_mail'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$messages = array(
			'sent'            => array( 'success', __( 'On its way to The 35, a batch at a time. The box on the right shows how far it has got.', 'founding-faces' ) ),
			'batch'           => array( 'success', __( 'Batch sent.', 'founding-faces' ) ),
			'stopped'         => array( 'warning', __( 'Stopped. Anyone not yet reached will not be.', 'founding-faces' ) ),
			'ff_not_published' => array( 'error', __( 'Publish the note first. An email about a draft sends everyone to a page they cannot open.', 'founding-faces' ) ),
			'ff_in_progress'  => array( 'error', __( 'This note is already going out.', 'founding-faces' ) ),
			'ff_no_one'       => array( 'error', __( 'Nobody to send to. The 35 is either empty or everyone in it has unsubscribed.', 'founding-faces' ) ),
			'ff_no_note'      => array( 'error', __( 'That note no longer exists.', 'founding-faces' ) ),
		);

		if ( ! isset( $messages[ $code ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $messages[ $code ][0] ),
			esc_html( $messages[ $code ][1] )
		);
	}

	/**
	 * Add the send box to the note edit screen.
	 */
	public static function add_metabox() {
		add_meta_box(
			'ff_note_mail',
			__( 'Tell The 35', 'founding-faces' ),
			array( __CLASS__, 'render_metabox' ),
			FF_Post_Types::NOTE_CPT,
			'side',
			'default'
		);
	}

	/**
	 * The send box.
	 *
	 * @param WP_Post $post The note.
	 */
	public static function render_metabox( $post ) {
		$queue = self::queue( $post->ID );
		$log   = self::log( $post->ID );
		$last  = ! empty( $log ) ? end( $log ) : null;
		$url   = admin_url( 'admin-post.php' );

		echo '<p class="description">' . esc_html__( 'An email to The 35 with a link to this note. The Circle is not included: a list that size belongs in the email platform.', 'founding-faces' ) . '</p>';

		if ( $last ) {
			echo '<p><strong>' . esc_html__( 'Last send', 'founding-faces' ) . '</strong><br />';
			echo esc_html( sprintf(
				/* translators: 1: a date, 2: how many were sent, 3: how many were queued. */
				__( '%1$s, %2$d of %3$d sent', 'founding-faces' ),
				mysql2date( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ), $last['started'] ),
				(int) $last['sent'],
				(int) $last['queued']
			) );
			if ( ! empty( $last['failed'] ) ) {
				echo '<br />' . esc_html( sprintf(
					/* translators: %d is how many emails the server refused. */
					__( '%d refused by the mail server', 'founding-faces' ),
					(int) $last['failed']
				) );
			}
			echo '</p>';
		}

		if ( ! empty( $queue ) ) {
			echo '<p><strong>' . esc_html( sprintf(
				/* translators: 1: how many are left, 2: how many minutes between batches. */
				_n( 'Sending: %1$d still to go, a batch every %2$d minutes.', 'Sending: %1$d still to go, a batch every %2$d minutes.', count( $queue ), 'founding-faces' ),
				count( $queue ),
				self::gap_minutes()
			) ) . '</strong></p>';

			echo '<p>';
			echo '<a class="button" href="' . esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'ff_send_note_now', 'note' => $post->ID ), $url ), 'ff_send_note_' . $post->ID ) ) . '">'
				. esc_html__( 'Send the next batch now', 'founding-faces' ) . '</a> ';
			echo '<a class="button button-link-delete" href="' . esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'ff_stop_note_mail', 'note' => $post->ID ), $url ), 'ff_send_note_' . $post->ID ) ) . '">'
				. esc_html__( 'Stop', 'founding-faces' ) . '</a>';
			echo '</p>';
			return;
		}

		if ( 'publish' !== $post->post_status ) {
			echo '<p>' . esc_html__( 'Publish the note first. An email about a draft sends everyone to a page they cannot open.', 'founding-faces' ) . '</p>';
			return;
		}

		$label = $last ? __( 'Send it again', 'founding-faces' ) : __( 'Send to The 35', 'founding-faces' );
		$ask   = $last
			? __( 'This note has already gone out once. Send it again?', 'founding-faces' )
			: __( 'Send this note to The 35?', 'founding-faces' );

		echo '<p><a class="button button-primary" href="' . esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'ff_send_note', 'note' => $post->ID ), $url ), 'ff_send_note_' . $post->ID ) ) . '" onclick="return confirm(' . esc_attr( wp_json_encode( $ask ) ) . ');">'
			. esc_html( $label ) . '</a></p>';

		echo '<p class="description">' . esc_html( sprintf(
			/* translators: 1: how many will get it, 2: batch size, 3: minutes between batches. */
			__( '%1$d members will get it, %2$d at a time, a batch every %3$d minutes. Anyone who has unsubscribed is left out.', 'founding-faces' ),
			count( self::recipients() ),
			self::batch_size(),
			self::gap_minutes()
		) ) . '</p>';
	}

	/*
	 * -----------------------------------------------------------------------
	 * The buttons.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Check the request and return the note id, or stop.
	 *
	 * @return int
	 */
	private static function checked_note() {
		$note_id = isset( $_GET['note'] ) ? absint( $_GET['note'] ) : 0;

		if ( ! $note_id || ! current_user_can( 'edit_post', $note_id ) ) {
			wp_die( esc_html__( 'You cannot send that note.', 'founding-faces' ) );
		}
		check_admin_referer( 'ff_send_note_' . $note_id );

		return $note_id;
	}

	/**
	 * Start a send.
	 */
	public static function handle_send() {
		$note_id = self::checked_note();
		$result  = self::start( $note_id );
		$notice  = is_wp_error( $result ) ? $result->get_error_code() : 'sent';

		self::go_back( $note_id, $notice );
	}

	/**
	 * Push the next batch out without waiting for the clock.
	 */
	public static function handle_send_now() {
		$note_id = self::checked_note();
		wp_clear_scheduled_hook( self::CRON_HOOK, array( $note_id ) );
		self::run_batch( $note_id );

		self::go_back( $note_id, 'batch' );
	}

	/**
	 * Stop a send part way through.
	 */
	public static function handle_stop() {
		$note_id = self::checked_note();
		self::stop( $note_id );

		self::go_back( $note_id, 'stopped' );
	}

	/**
	 * Back to the note, carrying what happened.
	 *
	 * @param int    $note_id The note.
	 * @param string $notice  A key the notice below understands.
	 */
	private static function go_back( $note_id, $notice ) {
		wp_safe_redirect( add_query_arg( 'ff_note_mail', $notice, get_edit_post_link( $note_id, 'raw' ) ) );
		exit;
	}
}
