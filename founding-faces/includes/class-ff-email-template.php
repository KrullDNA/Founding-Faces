<?php
/**
 * The branded HTML email shell.
 *
 * Every programme email — welcome, promotion, password reset, application
 * received — is wrapped in one consistent, on-brand HTML layout: an optional
 * logo, a heading, the message body, an optional call-to-action button, and a
 * footer. The colours, logo and footer text are editable in Settings, so the
 * whole look can be tuned without touching code.
 *
 * Email HTML is deliberately old-fashioned: tables and inline styles, because
 * that is what mail clients (Outlook especially) reliably render.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Email_Template
 */
class FF_Email_Template {

	// Editable design options.
	const OPT_LOGO         = 'ff_email_logo';          // Logo image URL, above the card.
	const OPT_ACCENT       = 'ff_email_accent';        // Link colour in the body.
	const OPT_HEADING_BG   = 'ff_email_heading_bg';    // The heading band's fill.
	const OPT_HEADING_TEXT = 'ff_email_heading_text';  // The heading's own colour.
	const OPT_BG           = 'ff_email_bg';            // Page background behind the card.
	const OPT_BUTTON_BG    = 'ff_email_button_bg';     // CTA button background.
	const OPT_BUTTON_TEXT  = 'ff_email_button_text';   // CTA button text colour.
	const OPT_FOOTER       = 'ff_email_footer';        // Footer text, inside the card.
	const OPT_DISCLAIMER   = 'ff_email_disclaimer';    // Small print, below the card.

	/**
	 * The shipped design defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			self::OPT_LOGO         => '',
			self::OPT_ACCENT       => '#2b2d33',
			self::OPT_HEADING_BG   => '#2b2d33',
			self::OPT_HEADING_TEXT => '#ffffff',
			self::OPT_BG           => '#f6f7f8',
			self::OPT_BUTTON_BG    => '#3a3d44',
			self::OPT_BUTTON_TEXT  => '#ffffff',
			self::OPT_FOOTER       => get_bloginfo( 'name' ),
			self::OPT_DISCLAIMER   => self::default_disclaimer(),
		);
	}

	/**
	 * The shipped small print.
	 *
	 * @return string
	 */
	public static function default_disclaimer() {
		return __(
			'This message was sent as part of the Founding Faces programme, between {site_name} and the addressee named above. If it has reached you by mistake, we would be grateful if you told us, deleted it from your mailbox, and did not forward it or any part of it to anyone else. Thank you for your understanding.',
			'founding-faces'
		);
	}

	/**
	 * Read a single design option, falling back to its default.
	 *
	 * @param string $key One of the OPT_* constants.
	 * @return string
	 */
	public static function option( $key ) {
		$defaults = self::defaults();
		$default  = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
		$value    = get_option( $key, $default );
		return ( '' === $value || null === $value ) ? $default : $value;
	}

	/**
	 * Build a complete branded HTML email.
	 *
	 * @param array $args {
	 *     @type string $heading   The large heading at the top of the card.
	 *     @type string $body_html The message body, already valid HTML.
	 *     @type array  $cta         Optional ['label' => string, 'url' => string].
	 *     @type string $preheader   Optional hidden preview line.
	 *     @type string $unsubscribe Optional unsubscribe URL. The link only
	 *                               appears when one is given, so an applicant
	 *                               who has no account is never offered a way
	 *                               out of a list they are not on.
	 * }
	 * @return string The full HTML document.
	 */
	public static function build( $args ) {
		$heading   = isset( $args['heading'] ) ? $args['heading'] : '';
		$body_html = isset( $args['body_html'] ) ? $args['body_html'] : '';
		$cta       = isset( $args['cta'] ) && is_array( $args['cta'] ) ? $args['cta'] : array();
		$preheader = isset( $args['preheader'] ) ? $args['preheader'] : '';
		$unsub     = isset( $args['unsubscribe'] ) ? $args['unsubscribe'] : '';

		$logo      = self::option( self::OPT_LOGO );
		$accent    = self::option( self::OPT_ACCENT );
		$head_bg   = self::option( self::OPT_HEADING_BG );
		$head_text = self::option( self::OPT_HEADING_TEXT );
		$bg        = self::option( self::OPT_BG );
		$btn_bg    = self::option( self::OPT_BUTTON_BG );
		$btn_text  = self::option( self::OPT_BUTTON_TEXT );
		$footer    = self::option( self::OPT_FOOTER );

		$site = get_bloginfo( 'name' );

		// The small print takes {site_name} so it needs no editing between
		// staging and live.
		$disclaimer = strtr(
			(string) self::option( self::OPT_DISCLAIMER ),
			array( '{site_name}' => $site )
		);

		// No quoted font names anywhere in the stack. A quoted name inside an
		// inline style attribute has to survive being escaped, re-escaped and
		// decoded again on its way to a preview pane or a mail client, and if it
		// doesn't the whole font-family declaration is thrown away and the email
		// lands in the reader's default serif. Montserrat, then Arial, needs no
		// quotes at all.
		$family = 'Montserrat, Arial, Helvetica, sans-serif';

		// Montserrat has to be fetched, because mail clients don't embed fonts.
		// Apple Mail, iOS Mail and Samsung Mail honour the link; Gmail and
		// Outlook ignore it and fall to Arial, which is the point of the stack.
		$font_css = 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap';

		ob_start();
		?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="color-scheme" content="light only" />
	<title><?php echo esc_html( $heading ? $heading : $site ); ?></title>
	<!--[if !mso]><!-->
	<link href="<?php echo esc_url( $font_css ); ?>" rel="stylesheet" type="text/css" />
	<style type="text/css">
		@import url('<?php echo esc_url( $font_css ); ?>');
		body, table, td, p, a, span, h1, li { font-family: <?php echo esc_html( $family ); ?>; }
		/* Links in the body take the heading colour. The button sets its own
		   colour inline, which wins over this. */
		a { color: <?php echo esc_html( $accent ); ?>; }
	</style>
	<!--<![endif]-->
	<!--[if mso]>
	<style type="text/css">
		body, table, td, p, a, span, h1, li {
			font-family: Arial, Helvetica, sans-serif !important;
		}
	</style>
	<![endif]-->
</head>
<body style="margin:0; padding:0; background:<?php echo esc_attr( $bg ); ?>; font-family:<?php echo esc_attr( $family ); ?>; color:#2b2d33;">
	<?php if ( '' !== trim( (string) $preheader ) ) : ?>
		<div style="display:none; max-height:0; overflow:hidden; opacity:0;"><?php echo esc_html( $preheader ); ?></div>
	<?php endif; ?>
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:<?php echo esc_attr( $bg ); ?>;">
		<tr>
			<td align="center" style="padding:28px 16px;">

				<?php if ( '' !== trim( (string) $logo ) ) : ?>
				<!-- The logo sits above the card, on the page, not inside it. -->
				<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:100%;">
					<tr>
						<td align="center" style="padding:0 16px 26px;">
							<img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $site ); ?>" style="max-height:70px; max-width:80%; height:auto; display:block;" />
						</td>
					</tr>
				</table>
				<?php endif; ?>

				<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:100%; background:#ffffff; border-radius:10px; overflow:hidden;">
					<?php if ( '' !== trim( (string) $heading ) ) : ?>
					<!-- The heading band: its own fill, its own text colour, full
					     width of the card. -->
					<tr>
						<td align="center" bgcolor="<?php echo esc_attr( $head_bg ); ?>" style="padding:34px 32px; background:<?php echo esc_attr( $head_bg ); ?>;">
							<h1 style="margin:0; font-family:<?php echo esc_attr( $family ); ?>; font-size:26px; line-height:1.3; font-weight:700; color:<?php echo esc_attr( $head_text ); ?>;">
								<?php echo esc_html( $heading ); ?>
							</h1>
						</td>
					</tr>
					<?php endif; ?>
					<tr>
						<td style="padding:34px 40px 4px; font-family:<?php echo esc_attr( $family ); ?>; font-size:15px; line-height:1.7; color:#2b2d33;">
							<?php echo $body_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Body is pre-built, escaped HTML. ?>
						</td>
					</tr>
					<?php if ( ! empty( $cta['url'] ) && ! empty( $cta['label'] ) ) : ?>
					<tr>
						<td style="padding:12px 40px 8px;">
							<table role="presentation" cellpadding="0" cellspacing="0">
								<tr>
									<td align="center" bgcolor="<?php echo esc_attr( $btn_bg ); ?>" style="border-radius:6px; background:<?php echo esc_attr( $btn_bg ); ?>;">
										<a href="<?php echo esc_url( $cta['url'] ); ?>" style="display:inline-block; padding:13px 28px; font-family:<?php echo esc_attr( $family ); ?>; font-size:15px; font-weight:600; color:<?php echo esc_attr( $btn_text ); ?>; text-decoration:none; border-radius:6px;">
											<?php echo esc_html( $cta['label'] ); ?>
										</a>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<?php endif; ?>
					<?php if ( '' !== trim( (string) $footer ) ) : ?>
					<tr>
						<td align="center" style="padding:18px 40px 34px;">
							<p style="margin:0; font-family:<?php echo esc_attr( $family ); ?>; font-size:13px; line-height:1.6; color:#6b7280; text-align:center;">
								<?php echo nl2br( esc_html( $footer ) ); ?>
							</p>
						</td>
					</tr>
					<?php endif; ?>
				</table>

				<?php if ( '' !== trim( (string) $disclaimer ) || '' !== trim( (string) $unsub ) ) : ?>
				<!-- The small print and the way out, below the card. -->
				<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:100%;">
					<?php if ( '' !== trim( (string) $disclaimer ) ) : ?>
					<tr>
						<td align="center" style="padding:20px 24px 0;">
							<p style="margin:0; font-family:<?php echo esc_attr( $family ); ?>; font-size:11px; line-height:1.55; color:#8a9099; text-align:center;">
								<?php echo nl2br( esc_html( $disclaimer ) ); ?>
							</p>
						</td>
					</tr>
					<?php endif; ?>
					<?php if ( '' !== trim( (string) $unsub ) ) : ?>
					<tr>
						<td align="center" style="padding:14px 24px 8px;">
							<a href="<?php echo esc_url( $unsub ); ?>" style="font-family:<?php echo esc_attr( $family ); ?>; font-size:11px; line-height:1.55; color:#8a9099; text-decoration:underline;">
								<?php esc_html_e( 'Unsubscribe', 'founding-faces' ); ?>
							</a>
						</td>
					</tr>
					<?php endif; ?>
				</table>
				<?php endif; ?>

			</td>
		</tr>
	</table>
</body>
</html>
		<?php
		return ob_get_clean();
	}
}
