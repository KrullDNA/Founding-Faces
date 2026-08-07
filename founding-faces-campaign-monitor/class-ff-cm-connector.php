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

	// Remembers which set of custom fields has been created on the list. Held as
	// a version rather than a flag: adding a field to the list below has to make
	// an install that already ran this go round again.
	const OPT_FIELDS_READY = 'ff_cm_fields_ready';
	const FIELDS_VERSION   = '2';

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

		$fields = array();
		foreach ( self::field_values( $member ) as $key => $value ) {
			$fields[] = array(
				'Key'   => $key,
				'Value' => $value,
			);
		}

		$body = array(
			'EmailAddress'   => $member['email'],
			'Name'           => $member['name'],
			'CustomFields'   => $fields,
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
	 * Who has unsubscribed at Campaign Monitor since a given moment.
	 *
	 * Two lists are read, not one. An unsubscribe and a spam complaint are
	 * different acts with the same meaning for us: stop emailing this person.
	 * Campaign Monitor keeps them apart, so both are asked for and merged.
	 *
	 * @param string $since A MySQL datetime.
	 * @return array|WP_Error A list of email addresses.
	 */
	public function fetch_unsubscribes( $since ) {
		if ( ! $this->is_configured() ) {
			return array();
		}

		$list_id = get_option( self::OPT_LIST_ID );
		$emails  = array();

		foreach ( array( 'unsubscribed', 'spam' ) as $which ) {
			$page = 1;

			// Paged, because a big list's first run could be more than one page
			// and a truncated answer would leave people quietly still subscribed.
			do {
				$result = $this->fetch(
					"/lists/{$list_id}/{$which}.json?date=" . rawurlencode( $since )
					. '&page=' . (int) $page . '&pagesize=1000&orderfield=date&orderdirection=asc'
				);

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				$results = isset( $result['Results'] ) && is_array( $result['Results'] ) ? $result['Results'] : array();

				foreach ( $results as $row ) {
					if ( ! empty( $row['EmailAddress'] ) ) {
						$emails[] = $row['EmailAddress'];
					}
				}

				$pages = isset( $result['NumberOfPages'] ) ? (int) $result['NumberOfPages'] : 1;
				$page++;
			} while ( $page <= $pages && $page <= 20 );
		}

		return array_values( array_unique( $emails ) );
	}

	/**
	 * The custom fields this connector keeps on the list.
	 *
	 * Every one of them is a plain field of its own rather than a multi-select.
	 * A multi-select in Campaign Monitor carries a fixed list of options defined
	 * on the field, so a new option means another API call that can fail
	 * separately from the one that matters, on a subscriber save. Text, number
	 * and date fields have no such list to keep in step.
	 *
	 * @return array Map of field name => Campaign Monitor data type.
	 */
	public static function fields() {
		return array(
			'Group'             => 'Text',
			'Number'            => 'Number',
			'Status'            => 'Text',
			'DisplayPreference' => 'Text',
			'ApplicationDate'   => 'Date',
			'Postcode'          => 'Text',
			'Tags'              => 'Text',
		);
	}

	/**
	 * The values for those fields, for one member or applicant.
	 *
	 * @param array $member The payload from FF_Connectors.
	 * @return array Map of field name => value.
	 */
	public static function field_values( array $member ) {
		return array(
			'Group'             => isset( $member['group'] ) ? $member['group'] : '',
			'Number'            => ( '' === $member['number'] ) ? '' : (string) $member['number'],
			'Status'            => isset( $member['status'] ) ? $member['status'] : '',
			'DisplayPreference' => isset( $member['display_preference'] ) ? $member['display_preference'] : '',
			'ApplicationDate'   => isset( $member['application_date'] ) ? $member['application_date'] : '',
			'Postcode'          => isset( $member['postcode'] ) ? $member['postcode'] : '',
			'Tags'              => self::tag_string( isset( $member['tags'] ) ? $member['tags'] : array() ),
		);
	}

	/**
	 * The tags as one delimited string, each one wrapped in pipes.
	 *
	 * Campaign Monitor segments text with "contains", and the pipes are what
	 * make that safe: segmenting on "founding" would otherwise also catch
	 * "founding-circle" and the segment would be quietly wrong for months.
	 * Written as |poll-01|feedback-r2| it is segmented on |poll-01| and matches
	 * that tag and nothing else.
	 *
	 * @param array $tags The tags.
	 * @return string An empty string when there are none, not a bare pipe.
	 */
	public static function tag_string( $tags ) {
		$tags = array_filter( array_map( 'strval', (array) $tags ) );

		return empty( $tags ) ? '' : '|' . implode( '|', $tags ) . '|';
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
		if ( self::FIELDS_VERSION === (string) get_option( self::OPT_FIELDS_READY ) ) {
			return;
		}

		$list_id = get_option( self::OPT_LIST_ID );

		foreach ( self::fields() as $field => $type ) {
			// A failure here (including "already exists") is intentionally not
			// fatal; a genuine problem surfaces on the subscribe call.
			$this->request(
				'POST',
				"/lists/{$list_id}/customfields.json",
				array(
					'FieldName'                 => $field,
					'DataType'                  => $type,
					'VisibleInPreferenceCenter' => false,
				)
			);
		}

		update_option( self::OPT_FIELDS_READY, self::FIELDS_VERSION );
	}

	/**
	 * A GET that hands back the decoded body.
	 *
	 * request() answers true or an error, which is everything a write needs and
	 * nothing a read does.
	 *
	 * @param string $path The API path.
	 * @return array|WP_Error
	 */
	private function fetch( $path ) {
		$api_key = get_option( self::OPT_API_KEY );

		$response = wp_remote_get( self::API_BASE . $path, array(
			'timeout' => 20,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $api_key . ':x' ),
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$message = ( is_array( $data ) && isset( $data['Message'] ) )
				? $data['Message']
				: sprintf( /* translators: %d is an HTTP status code. */ __( 'Campaign Monitor returned HTTP %d.', 'founding-faces' ), $code );

			return new WP_Error( 'ff_cm_error', $message, array( 'status' => $code ) );
		}

		return is_array( $data ) ? $data : array();
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
