<?php
/**
 * The Campaign Monitor connector add-on.
 *
 * Implements the core FF_Connector contract against Campaign Monitor's REST
 * API. Campaign Monitor has no tags, so the member's group and number travel as
 * custom fields on the subscriber. Talks to the API with WordPress's own HTTP
 * functions, no third-party SDK bundled (governing principle 4: lean, always).
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_CM_Connector
 *
 * Subscribes and unsubscribes members on a Campaign Monitor list, carrying the
 * group and number as custom fields.
 */
class FF_CM_Connector extends FF_Connector {

	// Settings option keys for the API key and the target list id.
	const OPT_API_KEY = 'ff_cm_api_key';
	const OPT_LIST_ID = 'ff_cm_list_id';

	// Remembers that the two custom fields have been created on the list.
	const OPT_FIELDS_READY = 'ff_cm_fields_ready';

	// The Campaign Monitor API base.
	const API_BASE = 'https://api.createsend.com/api/v3.2';

	/**
	 * The machine id for this connector.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'campaign_monitor';
	}

	/**
	 * The human-readable name for the settings screen.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Campaign Monitor', 'founding-faces' );
	}

	/**
	 * Whether an API key and a list id are both saved.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return '' !== trim( (string) get_option( self::OPT_API_KEY, '' ) )
			&& '' !== trim( (string) get_option( self::OPT_LIST_ID, '' ) );
	}

	/**
	 * Add or update a member on the Campaign Monitor list.
	 *
	 * Group and number are sent as custom fields; the number is an empty string
	 * for The Circle. Resubscribe is on, so re-approving or re-consenting simply
	 * updates the existing subscriber.
	 *
	 * @param array $member The member payload.
	 * @return true|WP_Error
	 */
	public function subscribe( array $member ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'ff_cm_unconfigured', __( 'Campaign Monitor is not configured.', 'founding-faces' ) );
		}

		// Make sure the Group and Number custom fields exist on the list.
		$this->ensure_custom_fields();

		$list_id = get_option( self::OPT_LIST_ID );

		$body = array(
			'EmailAddress'   => $member['email'],
			'Name'           => $member['name'],
			'CustomFields'   => array(
				array(
					'Key'   => 'Group',
					'Value' => $member['group'],
				),
				array(
					'Key'   => 'Number',
					'Value' => ( '' === $member['number'] ) ? '' : (string) $member['number'],
				),
			),
			// We only ever reach here when consent is stored, so record it.
			'ConsentToTrack' => 'Yes',
			'Resubscribe'    => true,
		);

		return $this->request( 'POST', "/subscribers/{$list_id}.json", $body );
	}

	/**
	 * Unsubscribe an email address from the list.
	 *
	 * Used by the account-page consent toggle in a later stage, so turning
	 * consent off writes back to Campaign Monitor rather than only locally.
	 *
	 * @param string $email The email address.
	 * @return true|WP_Error
	 */
	public function unsubscribe( $email ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'ff_cm_unconfigured', __( 'Campaign Monitor is not configured.', 'founding-faces' ) );
		}

		$list_id = get_option( self::OPT_LIST_ID );

		return $this->request(
			'POST',
			"/subscribers/{$list_id}/unsubscribe.json",
			array( 'EmailAddress' => $email )
		);
	}

	/**
	 * Ensure the Group and Number custom fields exist on the list.
	 *
	 * Runs once: Campaign Monitor rejects a subscriber whose custom field keys
	 * don't exist yet. Creating a field that already exists is harmless (the API
	 * simply reports it), so the "already there" case is ignored and the ready
	 * flag is set so this doesn't run on every subscribe.
	 *
	 * @return void
	 */
	private function ensure_custom_fields() {
		if ( get_option( self::OPT_FIELDS_READY ) ) {
			return;
		}

		$list_id = get_option( self::OPT_LIST_ID );

		foreach ( array( 'Group', 'Number' ) as $field ) {
			// A failure here (including "already exists") is intentionally not
			// fatal; a genuine problem surfaces on the subscribe call.
			$this->request(
				'POST',
				"/lists/{$list_id}/customfields.json",
				array(
					'FieldName'                => $field,
					'DataType'                 => 'Text',
					'VisibleInPreferenceCenter' => false,
				)
			);
		}

		update_option( self::OPT_FIELDS_READY, 1 );
	}

	/**
	 * Make an authenticated request to the Campaign Monitor API.
	 *
	 * Authentication is HTTP Basic with the API key as the username. Uses
	 * WordPress's HTTP API rather than any bundled library.
	 *
	 * @param string     $method The HTTP method (GET/POST).
	 * @param string     $path   The API path, e.g. "/subscribers/{list}.json".
	 * @param array|null $body   The request body, JSON-encoded before sending.
	 * @return true|WP_Error True on a 2xx response, or a WP_Error otherwise.
	 */
	private function request( $method, $path, $body = null ) {
		$api_key = get_option( self::OPT_API_KEY );

		$args = array(
			'method'  => $method,
			'timeout' => 15,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $api_key . ':x' ),
				'Content-Type'  => 'application/json',
			),
			'body'    => ( null === $body ) ? null : wp_json_encode( $body ),
		);

		$response = wp_remote_request( self::API_BASE . $path, $args );

		// A transport-level failure (no network, DNS, etc.).
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			return true;
		}

		// Campaign Monitor returns a JSON body with a Message on error.
		$data    = json_decode( wp_remote_retrieve_body( $response ), true );
		$message = ( is_array( $data ) && isset( $data['Message'] ) )
			? $data['Message']
			: sprintf( /* translators: %d is an HTTP status code. */ __( 'Campaign Monitor returned HTTP %d.', 'founding-faces' ), $code );

		return new WP_Error( 'ff_cm_error', $message, array( 'status' => $code ) );
	}
}
