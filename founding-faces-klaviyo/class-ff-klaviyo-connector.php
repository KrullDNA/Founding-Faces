<?php
/**
 * The Klaviyo connector add-on.
 *
 * Implements the same core FF_Connector contract as Campaign Monitor, against
 * Klaviyo's REST API. Unlike Campaign Monitor, Klaviyo supports richer
 * segmentation, so the member's group is sent BOTH as a profile property and as
 * a tag (a "tags" array property Klaviyo can segment on), alongside their name,
 * email and number. Talks to the API with WordPress's own HTTP functions, no
 * third-party SDK bundled.
 *
 * Only one connector is ever active at a time; the core plugin doesn't care
 * which. Build this when Klaviyo is purchased and select it on the settings
 * page.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Klaviyo_Connector
 *
 * Upserts a member as a Klaviyo profile and (optionally) adds them to a list.
 */
class FF_Klaviyo_Connector extends FF_Connector {

	// Settings option keys.
	const OPT_API_KEY = 'ff_klaviyo_api_key';
	const OPT_LIST_ID = 'ff_klaviyo_list_id';

	// The Klaviyo API base and the API revision this add-on is written against.
	const API_BASE = 'https://a.klaviyo.com/api';
	const REVISION = '2024-10-15';

	/**
	 * The machine id for this connector.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'klaviyo';
	}

	/**
	 * The human-readable name for the settings screen.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Klaviyo', 'founding-faces' );
	}

	/**
	 * Klaviyo supports tags, so the group travels as a tag as well as a property.
	 *
	 * @return bool
	 */
	public function supports_tags() {
		return true;
	}

	/**
	 * Whether a private API key is saved.
	 *
	 * A list id is optional (a profile property is enough to segment on), so
	 * only the key is required to be "configured".
	 *
	 * @return bool
	 */
	public function is_configured() {
		return '' !== trim( (string) get_option( self::OPT_API_KEY, '' ) );
	}

	/**
	 * Add or update a member as a Klaviyo profile.
	 *
	 * Upserts the profile (create, or update if it already exists), setting the
	 * group as both a "group" property and a "tags" array property, plus the
	 * number and name. If a list id is configured, the profile is also added to
	 * that list.
	 *
	 * @param array $member The member payload.
	 * @return true|WP_Error
	 */
	public function subscribe( array $member ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'ff_klaviyo_unconfigured', __( 'Klaviyo is not configured.', 'founding-faces' ) );
		}

		// First name for personalisation; group as both property and tag.
		$first_name = self::first_name( $member['name'] );
		$properties = array(
			'group' => $member['group'],
			'tags'  => array( $member['group'] ), // The group as a tag.
		);
		if ( '' !== $member['number'] ) {
			$properties['number'] = (int) $member['number'];
		}

		$attributes = array(
			'email'      => $member['email'],
			'first_name' => $first_name,
			'properties' => $properties,
		);

		// Try to create the profile.
		$create = $this->api( 'POST', '/profiles/', array(
			'data' => array(
				'type'       => 'profile',
				'attributes' => $attributes,
			),
		) );

		if ( is_wp_error( $create ) ) {
			return $create;
		}

		$profile_id = '';

		if ( 201 === $create['code'] && isset( $create['json']['data']['id'] ) ) {
			// Newly created.
			$profile_id = $create['json']['data']['id'];
		} elseif ( 409 === $create['code'] ) {
			// Already exists: Klaviyo returns the existing id, so update it.
			$profile_id = isset( $create['json']['errors'][0]['meta']['duplicate_profile_id'] )
				? $create['json']['errors'][0]['meta']['duplicate_profile_id']
				: '';

			if ( '' === $profile_id ) {
				return new WP_Error( 'ff_klaviyo_no_id', __( 'Klaviyo did not return the existing profile id.', 'founding-faces' ) );
			}

			$patch = $this->api( 'PATCH', '/profiles/' . $profile_id . '/', array(
				'data' => array(
					'type'       => 'profile',
					'id'         => $profile_id,
					'attributes' => array(
						'first_name' => $first_name,
						'properties' => $properties,
					),
				),
			) );

			if ( is_wp_error( $patch ) ) {
				return $patch;
			}
			if ( $patch['code'] < 200 || $patch['code'] >= 300 ) {
				return $this->error_from( $patch );
			}
		} else {
			return $this->error_from( $create );
		}

		// Optionally add the profile to a list.
		$list_id = trim( (string) get_option( self::OPT_LIST_ID, '' ) );
		if ( '' !== $list_id && '' !== $profile_id ) {
			$this->api( 'POST', '/lists/' . $list_id . '/relationships/profiles/', array(
				'data' => array(
					array(
						'type' => 'profile',
						'id'   => $profile_id,
					),
				),
			) );
		}

		return true;
	}

	/**
	 * Unsubscribe an email address from Klaviyo email marketing.
	 *
	 * Used by the account-page consent toggle so turning consent off writes back
	 * to Klaviyo, not just locally.
	 *
	 * @param string $email The email address.
	 * @return true|WP_Error
	 */
	public function unsubscribe( $email ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'ff_klaviyo_unconfigured', __( 'Klaviyo is not configured.', 'founding-faces' ) );
		}

		$result = $this->api( 'POST', '/profile-subscription-bulk-delete-jobs/', array(
			'data' => array(
				'type'       => 'profile-subscription-bulk-delete-job',
				'attributes' => array(
					'profiles' => array(
						'data' => array(
							array(
								'type'       => 'profile',
								'attributes' => array( 'email' => $email ),
							),
						),
					),
				),
			),
		) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( $result['code'] < 200 || $result['code'] >= 300 ) {
			return $this->error_from( $result );
		}
		return true;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Helpers.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Make an authenticated request to the Klaviyo API.
	 *
	 * @param string     $method The HTTP method.
	 * @param string     $path   The API path, e.g. "/profiles/".
	 * @param array|null $body   The request body (JSON:API structure).
	 * @return array|WP_Error ['code'=>int,'json'=>array|null] or a transport error.
	 */
	private function api( $method, $path, $body = null ) {
		$key = get_option( self::OPT_API_KEY );

		$response = wp_remote_request( self::API_BASE . $path, array(
			'method'  => $method,
			'timeout' => 15,
			'headers' => array(
				'Authorization' => 'Klaviyo-API-Key ' . $key,
				'revision'      => self::REVISION,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
			'body'    => ( null === $body ) ? null : wp_json_encode( $body ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'code' => (int) wp_remote_retrieve_response_code( $response ),
			'json' => json_decode( wp_remote_retrieve_body( $response ), true ),
		);
	}

	/**
	 * Turn a non-2xx API response into a readable WP_Error.
	 *
	 * @param array $result The api() result.
	 * @return WP_Error
	 */
	private function error_from( $result ) {
		$message = isset( $result['json']['errors'][0]['detail'] )
			? $result['json']['errors'][0]['detail']
			: sprintf( /* translators: %d is an HTTP status code. */ __( 'Klaviyo returned HTTP %d.', 'founding-faces' ), (int) $result['code'] );
		return new WP_Error( 'ff_klaviyo_error', $message, array( 'status' => $result['code'] ) );
	}

	/**
	 * Pull a first name out of a full name.
	 *
	 * @param string $name The full name.
	 * @return string
	 */
	private static function first_name( $name ) {
		$name = trim( (string) $name );
		if ( '' === $name ) {
			return '';
		}
		$parts = preg_split( '/\s+/', $name );
		return $parts[0];
	}
}
