<?php
/**
 * The email-platform connector interface and the connector manager.
 *
 * The core plugin talks to exactly one email platform through a single, simple
 * contract. Campaign Monitor and (later) Klaviyo each implement that contract
 * as an add-on. The core doesn't care which one is active, it just asks the
 * active connector to subscribe or unsubscribe a member.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Connector
 *
 * The abstract contract every email-platform add-on must fulfil. Keeping this
 * tiny is deliberate: name, email, number and group is all the programme ever
 * pushes outward.
 */
abstract class FF_Connector {

	/**
	 * A short, stable machine id for this connector, e.g. 'campaign_monitor'.
	 *
	 * @return string
	 */
	abstract public function get_id();

	/**
	 * A human-readable name for the settings screen, e.g. 'Campaign Monitor'.
	 *
	 * @return string
	 */
	abstract public function get_label();

	/**
	 * Whether this connector has everything it needs (API key, list) to run.
	 *
	 * @return bool
	 */
	abstract public function is_configured();

	/**
	 * Add or update a member on the platform, in the correct list/segment.
	 *
	 * @param array $member A payload: email, name, number, group, group_slug.
	 * @return true|WP_Error True on success, or a WP_Error describing the failure.
	 */
	abstract public function subscribe( array $member );

	/**
	 * Unsubscribe an email address on the platform.
	 *
	 * @param string $email The email address to unsubscribe.
	 * @return true|WP_Error
	 */
	abstract public function unsubscribe( $email );

	/**
	 * The addresses that have unsubscribed at the platform since a moment.
	 *
	 * Optional. A connector that cannot answer returns an empty array and the
	 * site simply carries on trusting its own record, which is the behaviour
	 * every connector had before this existed.
	 *
	 * @param string $since A MySQL datetime in the site's timezone.
	 * @return array|WP_Error A list of email addresses.
	 */
	public function fetch_unsubscribes( $since ) {
		return array();
	}

	/**
	 * Whether the platform supports tags (Klaviyo does, Campaign Monitor doesn't).
	 *
	 * Defaults to false; a connector overrides this if it can use tags.
	 *
	 * @return bool
	 */
	public function supports_tags() {
		return false;
	}
}

/**
 * Class FF_Connectors
 *
 * The manager. Holds the registered connectors, knows which one is active, and
 * pushes a member to it on approval, but only when the member has consented.
 */
class FF_Connectors {

	// Option storing which connector is active (only one at a time).
	const OPT_ACTIVE = 'ff_active_connector';

	// Option storing the last sync error, for a hint on the settings screen.
	const OPT_LAST_ERROR = 'ff_last_sync_error';

	// When the platform was last asked who had unsubscribed, and the hook name
	// of the schedule that asks.
	const OPT_LAST_PULL = 'ff_last_unsub_pull';
	const CRON_HOOK     = 'ff_pull_unsubscribes';

	// The registered connector instances, keyed by id.
	protected static $connectors = array();

	/**
	 * Let the installed add-on plugins register their connectors, and hook
	 * member approval.
	 *
	 * The connectors are shipped as separate plugins now, so the core doesn't
	 * reference them directly. Each add-on hooks 'ff_register_connectors' and
	 * calls FF_Connectors::add(). Only one connector is active at a time (chosen
	 * on the settings page); the core doesn't care which, or whether any are
	 * installed at all.
	 *
	 * Called once on plugin load.
	 */
	public static function register() {
		/**
		 * Fires so connector add-on plugins can register themselves.
		 *
		 * An add-on hooks this, requires its connector class (which extends
		 * FF_Connector, now guaranteed to exist), and calls FF_Connectors::add().
		 */
		do_action( 'ff_register_connectors' );

		// Sync a member to the active platform the moment they're approved.
		add_action( 'ff_member_approved', array( __CLASS__, 'sync_member' ), 10, 1 );

		// Ask the platform, hourly, who has unsubscribed over there.
		add_action( self::CRON_HOOK, array( __CLASS__, 'pull_unsubscribes' ) );
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::CRON_HOOK );
		}

		// The "Check now" button on the settings page.
		add_action( 'admin_post_ff_pull_unsubscribes', array( __CLASS__, 'handle_manual_pull' ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Coming back the other way.
	 *
	 * A member can unsubscribe in two places: the link at the foot of one of
	 * our own emails, and the platform's own link at the foot of a campaign.
	 * The first is ours to handle and always was. The second happens entirely
	 * outside WordPress, and until the site is told about it the site carries
	 * on emailing somebody who has asked it not to. So the platform is asked,
	 * on a schedule, who has left.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Ask the active platform who has unsubscribed, and honour it here.
	 *
	 * @return int|WP_Error How many members were switched off, or the error.
	 */
	public static function pull_unsubscribes() {
		$connector = self::get_active();
		if ( ! $connector || ! $connector->is_configured() ) {
			return 0;
		}

		// A generous overlap on the first run and on every run after it. Asking
		// again about somebody already switched off costs nothing, and missing
		// one because two clocks disagree costs a member an unwanted email.
		$since = (string) get_option( self::OPT_LAST_PULL, '' );
		if ( '' === $since ) {
			$since = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
		} else {
			$since = gmdate( 'Y-m-d H:i:s', strtotime( $since ) - HOUR_IN_SECONDS );
		}

		$emails = $connector->fetch_unsubscribes( $since );

		if ( is_wp_error( $emails ) ) {
			update_option(
				self::OPT_LAST_ERROR,
				array(
					'message' => $emails->get_error_message(),
					'email'   => '',
					'time'    => current_time( 'mysql' ),
				)
			);
			return $emails;
		}

		$changed = 0;

		foreach ( (array) $emails as $email ) {
			if ( ! is_email( $email ) ) {
				continue;
			}

			$user = get_user_by( 'email', $email );
			if ( ! $user ) {
				continue; // Somebody on the list who was never a member here.
			}

			if ( FF_Unsubscribe::apply( $user->ID, 'platform' ) ) {
				$changed++;
			}
		}

		// Stamped only on a clean run, so a failed one is retried in full.
		update_option( self::OPT_LAST_PULL, current_time( 'mysql' ) );

		return $changed;
	}

	/**
	 * The "Check now" button on the settings page.
	 */
	public static function handle_manual_pull() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'founding-faces' ) );
		}
		check_admin_referer( 'ff_pull_unsubscribes' );

		$result = self::pull_unsubscribes();

		$back = add_query_arg(
			array(
				'page'      => 'founding-faces-settings',
				'ff_pulled' => is_wp_error( $result ) ? 'error' : (int) $result,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $back );
		exit;
	}

	/**
	 * Register a connector instance.
	 *
	 * @param FF_Connector $connector The connector to add.
	 * @return void
	 */
	public static function add( FF_Connector $connector ) {
		self::$connectors[ $connector->get_id() ] = $connector;
	}

	/**
	 * Get all registered connectors, keyed by id.
	 *
	 * @return FF_Connector[]
	 */
	public static function available() {
		return self::$connectors;
	}

	/**
	 * Get the currently active connector, or null if none is available.
	 *
	 * Defaults to Campaign Monitor, the connector that ships first.
	 *
	 * @return FF_Connector|null
	 */
	public static function get_active() {
		$id = get_option( self::OPT_ACTIVE, 'campaign_monitor' );
		if ( isset( self::$connectors[ $id ] ) ) {
			return self::$connectors[ $id ];
		}
		// Fall back to the first registered connector if the stored id is stale.
		return empty( self::$connectors ) ? null : reset( self::$connectors );
	}

	/**
	 * Sync a member to the active platform, if consent allows.
	 *
	 * Skips silently when there's no configured connector, when the member
	 * hasn't consented, or when this is a test account (so testing never
	 * pollutes the live list). Any platform error is recorded for the settings
	 * screen rather than shown to the member.
	 *
	 * @param int $user_id The member's WordPress user id.
	 * @return void
	 */
	public static function sync_member( $user_id ) {
		$connector = self::get_active();
		if ( ! $connector || ! $connector->is_configured() ) {
			return;
		}

		// Consent is the precondition for any external sync.
		if ( ! get_user_meta( $user_id, FF_Members::META_CONSENT, true ) ) {
			return;
		}

		// Never sync test accounts to the live platform.
		if ( get_user_meta( $user_id, FF_Members::META_IS_TEST, true ) ) {
			return;
		}

		$member = self::build_member_payload( $user_id );
		$result = $connector->subscribe( $member );

		// Record any failure so Nick can see it on the settings screen.
		if ( is_wp_error( $result ) ) {
			update_option(
				self::OPT_LAST_ERROR,
				array(
					'message' => $result->get_error_message(),
					'email'   => $member['email'],
					'time'    => current_time( 'mysql' ),
				)
			);
		}
	}

	/**
	 * Unsubscribe a member from the active platform.
	 *
	 * Used by the account page's consent toggle so that turning consent off
	 * writes back to Campaign Monitor, not only locally. Skips silently if there
	 * is no configured connector.
	 *
	 * @param int $user_id The member's WordPress user id.
	 * @return void
	 */
	public static function unsubscribe_member( $user_id ) {
		$connector = self::get_active();
		if ( ! $connector || ! $connector->is_configured() ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$result = $connector->unsubscribe( $user->user_email );
		if ( is_wp_error( $result ) ) {
			update_option(
				self::OPT_LAST_ERROR,
				array(
					'message' => $result->get_error_message(),
					'email'   => $user->user_email,
					'time'    => current_time( 'mysql' ),
				)
			);
		}
	}

	/**
	 * Build the outward payload for a member from their stored data.
	 *
	 * Only the four fields the programme ever shares: name, email, number and
	 * group. The number is an empty string for The Circle.
	 *
	 * @param int $user_id The member's WordPress user id.
	 * @return array
	 */
	public static function build_member_payload( $user_id ) {
		$user       = get_userdata( $user_id );
		$number     = get_user_meta( $user_id, FF_Members::META_NUMBER, true );
		$group_slug = get_user_meta( $user_id, FF_Members::META_GROUP, true );
		$group      = ( 'the-35' === $group_slug )
			? __( 'The 35', 'founding-faces' )
			: __( 'The Circle', 'founding-faces' );

		$status   = FF_Members::status( $user_id );
		$tier     = FF_Members::display_tier( $user_id );
		$tiers    = FF_Members::display_tiers();
		$app_date = self::application_date( $user_id );

		return array(
			'user_id'            => (int) $user_id,
			'email'              => $user ? $user->user_email : '',
			'name'               => get_user_meta( $user_id, FF_Members::META_REAL_NAME, true ),
			'number'             => $number ? (int) $number : '',
			'group'              => $group,
			'group_slug'         => $group_slug,

			// The structural state. Each of these is its own field on the
			// platform, because each one is something a journey branches on and
			// a branch cannot be asked to read a label out of a list.
			'status'             => FF_Members::status_label( $status ),
			'status_slug'        => $status,
			'display_preference' => isset( $tiers[ $tier ] ) ? $tiers[ $tier ] : $tier,
			'application_date'   => $app_date,
			'postcode'           => (string) get_user_meta( $user_id, FF_Members::META_POSTCODE, true ),

			// The loose labels, as a real array. Each connector writes them out
			// in whatever shape its platform understands: a pipe-wrapped string
			// for Campaign Monitor, a list property for Klaviyo.
			'tags'               => FF_Members::tags( $user_id ),
		);
	}

	/**
	 * The same shape again, for somebody who has applied and nothing more.
	 *
	 * An applicant has no WordPress account, only a row in ff_applications, but
	 * they are exactly who a "thanks for applying" journey is for. So the
	 * payload is built from the application instead, with the fields a member
	 * would have and an applicant hasn't left empty rather than absent.
	 *
	 * @param object $app     An ff_applications row.
	 * @param string $status  A FF_Members::statuses() key.
	 * @return array
	 */
	public static function build_applicant_payload( $app, $status = 'applicant' ) {
		return array(
			'user_id'            => 0,
			'email'              => isset( $app->email ) ? $app->email : '',
			'name'               => isset( $app->name ) ? $app->name : '',
			'number'             => '',
			'group'              => '',
			'group_slug'         => '',
			'status'             => FF_Members::status_label( $status ),
			'status_slug'        => $status,
			'display_preference' => '',
			'application_date'   => isset( $app->created_at ) ? substr( (string) $app->created_at, 0, 10 ) : '',
			'postcode'           => isset( $app->postcode ) ? (string) $app->postcode : '',
			'tags'               => array(),
		);
	}

	/**
	 * Push an applicant, or a declined applicant, to the platform.
	 *
	 * Consent is the same gate as everywhere else: the box on the application
	 * form, and nothing goes anywhere without it. A test application never
	 * reaches the live list.
	 *
	 * @param object $app    An ff_applications row.
	 * @param string $status A FF_Members::statuses() key.
	 * @return void
	 */
	public static function sync_applicant( $app, $status = 'applicant' ) {
		$connector = self::get_active();
		if ( ! $connector || ! $connector->is_configured() ) {
			return;
		}

		if ( empty( $app ) || empty( $app->email ) || empty( $app->consent ) ) {
			return;
		}

		$result = $connector->subscribe( self::build_applicant_payload( $app, $status ) );

		if ( is_wp_error( $result ) ) {
			update_option(
				self::OPT_LAST_ERROR,
				array(
					'message' => $result->get_error_message(),
					'email'   => $app->email,
					'time'    => current_time( 'mysql' ),
				)
			);
		}
	}

	/**
	 * The date a member first applied, as Y-m-d.
	 *
	 * @param int $user_id The member's user id.
	 * @return string An empty string when there is no application behind them.
	 */
	private static function application_date( $user_id ) {
		global $wpdb;

		$app_id = (int) get_user_meta( $user_id, FF_Members::META_APP_ID, true );
		if ( ! $app_id ) {
			return '';
		}

		$table = $wpdb->prefix . 'ff_applications';
		$date  = $wpdb->get_var( $wpdb->prepare( "SELECT created_at FROM {$table} WHERE id = %d", $app_id ) ); // phpcs:ignore WordPress.DB

		return $date ? substr( (string) $date, 0, 10 ) : '';
	}
}
