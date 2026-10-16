<?php
/**
 * Postal addresses for The 35.
 *
 * The 35 receive samples, so Nick needs somewhere to post them. The Circle do
 * not, so they are never asked: a programme that collects an address it has no
 * use for is collecting it for no reason, and the brief is lean on purpose.
 *
 * The member enters it themselves, in their own account area, and can change
 * it whenever they move. A change is emailed to Nick rather than discovered
 * when a parcel comes back, because the gap between "they moved" and "we post
 * the next round" is where a sample goes missing.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Address
 */
class FF_Address {

	const META_LINE1    = 'ff_addr_line1';
	const META_LINE2    = 'ff_addr_line2';
	const META_SUBURB   = 'ff_addr_suburb';
	const META_STATE    = 'ff_addr_state';
	const META_POSTCODE = 'ff_addr_postcode';
	const META_COUNTRY  = 'ff_addr_country';

	/** When it was last changed, as a GMT Unix timestamp. */
	const META_UPDATED = 'ff_addr_updated';

	/**
	 * Every field, in the order it is written on an envelope.
	 *
	 * @return array Meta key => label.
	 */
	public static function fields() {
		return array(
			self::META_LINE1    => __( 'Street address', 'founding-faces' ),
			self::META_LINE2    => __( 'Unit, level or care of', 'founding-faces' ),
			self::META_SUBURB   => __( 'Suburb', 'founding-faces' ),
			self::META_STATE    => __( 'State', 'founding-faces' ),
			self::META_POSTCODE => __( 'Postcode', 'founding-faces' ),
			self::META_COUNTRY  => __( 'Country', 'founding-faces' ),
		);
	}

	/**
	 * The Australian states and territories.
	 *
	 * @return array
	 */
	public static function states() {
		return array(
			'ACT' => __( 'Australian Capital Territory', 'founding-faces' ),
			'NSW' => __( 'New South Wales', 'founding-faces' ),
			'NT'  => __( 'Northern Territory', 'founding-faces' ),
			'QLD' => __( 'Queensland', 'founding-faces' ),
			'SA'  => __( 'South Australia', 'founding-faces' ),
			'TAS' => __( 'Tasmania', 'founding-faces' ),
			'VIC' => __( 'Victoria', 'founding-faces' ),
			'WA'  => __( 'Western Australia', 'founding-faces' ),
		);
	}

	/**
	 * A member's address as an array of its parts.
	 *
	 * @param int $user_id The member.
	 * @return array Meta key => value.
	 */
	public static function get( $user_id ) {
		$out = array();
		foreach ( array_keys( self::fields() ) as $key ) {
			$out[ $key ] = (string) get_user_meta( $user_id, $key, true );
		}

		return $out;
	}

	/**
	 * Whether a member has given enough of an address to post to.
	 *
	 * Street, suburb, state and postcode. A second line is optional, and a
	 * country that is blank means Australia, which is where everyone is.
	 *
	 * @param int $user_id The member.
	 * @return bool
	 */
	public static function complete( $user_id ) {
		$a = self::get( $user_id );

		foreach ( array( self::META_LINE1, self::META_SUBURB, self::META_STATE, self::META_POSTCODE ) as $key ) {
			if ( '' === trim( $a[ $key ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * The address on one line, for a list or a column.
	 *
	 * @param int $user_id The member.
	 * @return string
	 */
	public static function one_line( $user_id ) {
		$a     = self::get( $user_id );
		$parts = array_filter( array(
			trim( $a[ self::META_LINE2 ] ),
			trim( $a[ self::META_LINE1 ] ),
			trim( $a[ self::META_SUBURB ] ),
			trim( $a[ self::META_STATE ] ) . ' ' . trim( $a[ self::META_POSTCODE ] ),
			trim( $a[ self::META_COUNTRY ] ),
		) );

		return trim( implode( ', ', array_map( 'trim', $parts ) ), ', ' );
	}

	/*
	 * -----------------------------------------------------------------------
	 * The account form.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * The address block for the account area.
	 *
	 * The 35 only. Returns nothing at all for everyone else, so The Circle
	 * never sees a field asking for something that will never be used.
	 *
	 * @param int $user_id The member.
	 * @return string
	 */
	public static function fields_html( $user_id ) {
		if ( ! FF_Gating::is_the_35( $user_id ) ) {
			return '';
		}

		$a   = self::get( $user_id );
		$out = '<div class="ff-account-block ff-address">';
		$out .= '<h3>' . esc_html__( 'Where we post your samples', 'founding-faces' ) . '</h3>';
		$out .= '<p class="ff-hint">' . esc_html__( 'The 35 receive samples through the post, so this is the one thing we need beyond your email. It is used for posting and nothing else, it never appears anywhere on the site, and the members map still only ever reads your postcode.', 'founding-faces' ) . '</p>';

		$out .= '<p class="ff-field"><label for="ff-addr-line1">' . esc_html__( 'Street address', 'founding-faces' ) . '</label>';
		$out .= '<input type="text" id="ff-addr-line1" name="ff_addr_line1" autocomplete="address-line1" value="' . esc_attr( $a[ self::META_LINE1 ] ) . '" /></p>';

		$out .= '<p class="ff-field"><label for="ff-addr-line2">' . esc_html__( 'Unit, level or care of', 'founding-faces' ) . '</label>';
		$out .= '<input type="text" id="ff-addr-line2" name="ff_addr_line2" autocomplete="address-line2" value="' . esc_attr( $a[ self::META_LINE2 ] ) . '" />';
		$out .= '<span class="ff-hint">' . esc_html__( 'Optional. For a level or suite number, a building name, or a care-of address. A unit number usually goes in the line above, as 3/45 Smith Street.', 'founding-faces' ) . '</span></p>';

		$out .= '<p class="ff-field"><label for="ff-addr-suburb">' . esc_html__( 'Suburb', 'founding-faces' ) . '</label>';
		$out .= '<input type="text" id="ff-addr-suburb" name="ff_addr_suburb" autocomplete="address-level2" value="' . esc_attr( $a[ self::META_SUBURB ] ) . '" /></p>';

		$out .= '<p class="ff-field"><label for="ff-addr-state">' . esc_html__( 'State', 'founding-faces' ) . '</label>';
		$out .= '<select id="ff-addr-state" name="ff_addr_state" autocomplete="address-level1">';
		$out .= '<option value="">' . esc_html__( 'Choose a state', 'founding-faces' ) . '</option>';
		foreach ( self::states() as $code => $label ) {
			$out .= '<option value="' . esc_attr( $code ) . '"' . selected( $a[ self::META_STATE ], $code, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$out .= '</select></p>';

		$out .= '<p class="ff-field"><label for="ff-addr-postcode">' . esc_html__( 'Postcode', 'founding-faces' ) . '</label>';
		$out .= '<input type="text" id="ff-addr-postcode" name="ff_addr_postcode" inputmode="numeric" autocomplete="postal-code" maxlength="4" value="' . esc_attr( $a[ self::META_POSTCODE ] ) . '" /></p>';

		$country = '' !== trim( $a[ self::META_COUNTRY ] ) ? $a[ self::META_COUNTRY ] : __( 'Australia', 'founding-faces' );
		$out    .= '<p class="ff-field"><label for="ff-addr-country">' . esc_html__( 'Country', 'founding-faces' ) . '</label>';
		$out    .= '<input type="text" id="ff-addr-country" name="ff_addr_country" autocomplete="country-name" value="' . esc_attr( $country ) . '" /></p>';

		$out .= '</div>';

		return $out;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Saving.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Save a submitted address, and tell Nick if it changed.
	 *
	 * Called from the account form's own handler, which has already checked
	 * the nonce and that the member is who they say they are.
	 *
	 * @param int   $user_id The member.
	 * @param array $posted  The raw $_POST.
	 * @return void
	 */
	public static function save( $user_id, $posted ) {
		if ( ! FF_Gating::is_the_35( $user_id ) ) {
			return;
		}

		$before = self::get( $user_id );
		$after  = array();

		foreach ( array_keys( self::fields() ) as $key ) {
			// The form field carries the meta key's own name, so the two
			// cannot drift apart as fields are added.
			$value = isset( $posted[ $key ] ) ? sanitize_text_field( wp_unslash( $posted[ $key ] ) ) : '';

			if ( self::META_STATE === $key ) {
				$value = isset( self::states()[ $value ] ) ? $value : '';
			}
			if ( self::META_POSTCODE === $key ) {
				$value = preg_replace( '/[^0-9]/', '', $value );
			}

			$after[ $key ] = $value;
			update_user_meta( $user_id, $key, $value );
		}

		if ( $before === $after ) {
			return;
		}

		update_user_meta( $user_id, self::META_UPDATED, time() );

		// The map reads its own postcode field, so a member who moves moves on
		// the map too, rather than staying where they used to live.
		if ( '' !== $after[ self::META_POSTCODE ] ) {
			update_user_meta( $user_id, FF_Members::META_POSTCODE, $after[ self::META_POSTCODE ] );
		}

		self::notify_admin( $user_id, $before, $after );
	}

	/**
	 * Tell Nick an address has changed, with both versions.
	 *
	 * Both, because the useful question is never "what is it now" on its own.
	 * It is "which of the labels I have already written is wrong".
	 *
	 * @param int   $user_id The member.
	 * @param array $before  The address as it was.
	 * @param array $after   The address as it is now.
	 * @return void
	 */
	private static function notify_admin( $user_id, $before, $after ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$number = get_user_meta( $user_id, FF_Members::META_NUMBER, true );
		$real   = get_user_meta( $user_id, FF_Members::META_REAL_NAME, true );
		$name   = '' !== trim( (string) $real ) ? $real : $user->display_name;

		$was_empty = ( '' === trim( implode( '', $before ) ) );

		$subject = $was_empty
			/* translators: %d is a founding number. */
			? sprintf( __( 'Founding Face %d has added a postal address', 'founding-faces' ), (int) $number )
			/* translators: %d is a founding number. */
			: sprintf( __( 'Founding Face %d has changed their postal address', 'founding-faces' ), (int) $number );

		$lines = array(
			sprintf(
				/* translators: 1: member name, 2: founding number. */
				__( '%1$s, Founding Face %2$d', 'founding-faces' ),
				$name,
				(int) $number
			),
			$user->user_email,
			'',
			__( 'New address', 'founding-faces' ),
			self::block( $after ),
		);

		if ( ! $was_empty ) {
			$lines[] = '';
			$lines[] = __( 'Previous address', 'founding-faces' );
			$lines[] = self::block( $before );
			$lines[] = '';
			$lines[] = __( 'Worth checking before the next sendout, in case a label has already been written.', 'founding-faces' );
		}

		$html = FF_Email_Template::build( array(
			'heading'   => $was_empty
				? __( 'A postal address has been added', 'founding-faces' )
				: __( 'A postal address has changed', 'founding-faces' ),
			'body_html' => wpautop( nl2br( esc_html( implode( "\n", $lines ) ) ) ),
			'preheader' => $name,
		) );

		wp_mail( get_option( 'admin_email' ), $subject, $html, FF_Emails::headers( true ) );
	}

	/**
	 * An address as it would be written on an envelope.
	 *
	 * @param array $a The address parts.
	 * @return string
	 */
	public static function block( $a ) {
		$lines = array_filter( array(
			trim( $a[ self::META_LINE2 ] ),
			trim( $a[ self::META_LINE1 ] ),
			trim( $a[ self::META_SUBURB ] ),
			trim( trim( $a[ self::META_STATE ] ) . ' ' . trim( $a[ self::META_POSTCODE ] ) ),
			trim( $a[ self::META_COUNTRY ] ),
		) );

		return '' === implode( '', $lines )
			? __( '(none given)', 'founding-faces' )
			: implode( "\n", $lines );
	}

	/*
	 * -----------------------------------------------------------------------
	 * The posting list.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Stream The 35 as a CSV, for the sendout.
	 *
	 * One row per active member of The 35, in number order, with everything
	 * needed to write a label and nothing that is not. Members with no address
	 * yet are included rather than quietly dropped: an empty row is how you
	 * find out who still has to be asked.
	 *
	 * @return void Exits.
	 */
	public static function stream_list() {
		$ids = get_users( array(
			'meta_key'     => FF_Members::META_NUMBER, // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_compare' => 'EXISTS',
			'fields'       => 'ID',
		) );

		$rows = array();
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( get_user_meta( $id, FF_Members::META_DEACTIVATED, true ) ) {
				continue;
			}

			$user = get_userdata( $id );
			if ( ! $user ) {
				continue;
			}

			$a       = self::get( $id );
			$real    = (string) get_user_meta( $id, FF_Members::META_REAL_NAME, true );
			$updated = (int) get_user_meta( $id, self::META_UPDATED, true );

			// What they wrote when they applied, which is the other half of
			// knowing who you are posting to.
			$app = FF_Privacy::get_application( $id );

			$rows[] = array(
				(int) get_user_meta( $id, FF_Members::META_NUMBER, true ),
				'' !== trim( $real ) ? $real : $user->display_name,
				$user->user_email,
				$a[ self::META_LINE2 ],
				$a[ self::META_LINE1 ],
				$a[ self::META_SUBURB ],
				$a[ self::META_STATE ],
				$a[ self::META_POSTCODE ],
				'' !== trim( $a[ self::META_COUNTRY ] ) ? $a[ self::META_COUNTRY ] : __( 'Australia', 'founding-faces' ),
				self::complete( $id ) ? __( 'Yes', 'founding-faces' ) : __( 'No', 'founding-faces' ),
				$updated ? wp_date( 'Y-m-d', $updated ) : '',
				$app && isset( $app->instagram ) ? $app->instagram : '',
				$app && isset( $app->skin_concerns ) ? $app->skin_concerns : '',
				$app && isset( $app->answers ) ? $app->answers : '',
				get_user_meta( $id, FF_Members::META_CONSENT, true ) ? __( 'Yes', 'founding-faces' ) : __( 'No', 'founding-faces' ),
				$app && ! empty( $app->created_at ) ? substr( (string) $app->created_at, 0, 10 ) : '',
				get_user_meta( $id, FF_Members::META_IS_TEST, true ) ? __( 'Test account', 'founding-faces' ) : '',
			);
		}

		usort( $rows, function ( $a, $b ) {
			return $a[0] <=> $b[0];
		} );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=the-35-addresses-' . gmdate( 'Y-m-d' ) . '.csv' );

		$handle = fopen( 'php://output', 'w' );

		// A byte-order mark, so Excel opens it as UTF-8 rather than mangling
		// any name with an accent in it.
		fwrite( $handle, "\xEF\xBB\xBF" );

		// The label columns first, so the left of the sheet is ready for a mail
		// merge, and everything else after it.
		fputcsv( $handle, array(
			__( 'Number', 'founding-faces' ),
			__( 'Name', 'founding-faces' ),
			__( 'Email', 'founding-faces' ),
			__( 'Unit, level or care of', 'founding-faces' ),
			__( 'Street address', 'founding-faces' ),
			__( 'Suburb', 'founding-faces' ),
			__( 'State', 'founding-faces' ),
			__( 'Postcode', 'founding-faces' ),
			__( 'Country', 'founding-faces' ),
			__( 'Postable', 'founding-faces' ),
			__( 'Address updated', 'founding-faces' ),
			__( 'Instagram', 'founding-faces' ),
			__( 'Skin concerns', 'founding-faces' ),
			__( 'About their skin and why they applied', 'founding-faces' ),
			__( 'Email consent', 'founding-faces' ),
			__( 'Applied', 'founding-faces' ),
			__( 'Note', 'founding-faces' ),
		) );

		foreach ( $rows as $row ) {
			fputcsv( $handle, $row );
		}

		fclose( $handle );
		exit;
	}

	/**
	 * Remove every address field, for the data-deletion flow.
	 *
	 * @param int $user_id The member.
	 * @return void
	 */
	public static function forget( $user_id ) {
		foreach ( array_keys( self::fields() ) as $key ) {
			delete_user_meta( $user_id, $key );
		}
		delete_user_meta( $user_id, self::META_UPDATED );
	}
}
