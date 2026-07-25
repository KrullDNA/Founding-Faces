<?php
/**
 * The email-platform connector interface and the connector manager.
 *
 * The core plugin talks to exactly one email platform through a single, simple
 * contract. Campaign Monitor and (later) Klaviyo each implement that contract
 * as an add-on. The core doesn't care which one is active — it just asks the
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
 * pushes a member to it on approval — but only when the member has consented.
 */
class FF_Connectors {

	// Option storing which connector is active (only one at a time).
	const OPT_ACTIVE = 'ff_active_connector';

	// Option storing the last sync error, for a hint on the settings screen.
	const OPT_LAST_ERROR = 'ff_last_sync_error';

	// The registered connector instances, keyed by id.
	protected static $connectors = array();

	/**
	 * Register the built-in connectors and hook member approval.
	 *
	 * Called once on plugin load.
	 */
	public static function register() {
		// Register each available add-on. Only one is active at a time (chosen
		// on the settings page); the core doesn't care which.
		if ( class_exists( 'FF_CM_Connector' ) ) {
			self::add( new FF_CM_Connector() );
		}
		if ( class_exists( 'FF_Klaviyo_Connector' ) ) {
			self::add( new FF_Klaviyo_Connector() );
		}

		// Sync a member to the active platform the moment they're approved.
		add_action( 'ff_member_approved', array( __CLASS__, 'sync_member' ), 10, 1 );
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

		return array(
			'user_id'    => (int) $user_id,
			'email'      => $user ? $user->user_email : '',
			'name'       => get_user_meta( $user_id, FF_Members::META_REAL_NAME, true ),
			'number'     => $number ? (int) $number : '',
			'group'      => $group,
			'group_slug' => $group_slug,
		);
	}
}
