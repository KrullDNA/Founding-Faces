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
	const OPT_LOGO        = 'ff_email_logo';         // Logo image URL.
	const OPT_ACCENT      = 'ff_email_accent';       // Heading / accent colour.
	const OPT_BG          = 'ff_email_bg';           // Page background behind the card.
	const OPT_BUTTON_BG   = 'ff_email_button_bg';    // CTA button background.
	const OPT_BUTTON_TEXT = 'ff_email_button_text';  // CTA button text colour.
	const OPT_FOOTER      = 'ff_email_footer';       // Footer text (plain).

	/**
	 * The shipped design defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			self::OPT_LOGO        => '',
			self::OPT_ACCENT      => '#2b2d33',
			self::OPT_BG          => '#f6f7f8',
			self::OPT_BUTTON_BG   => '#3a3d44',
			self::OPT_BUTTON_TEXT => '#ffffff',
			self::OPT_FOOTER      => get_bloginfo( 'name' ),
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
	 *     @type array  $cta       Optional ['label' => string, 'url' => string].
	 *     @type string $preheader Optional hidden preview line.
	 * }
	 * @return string The full HTML document.
	 */
	public static function build( $args ) {
		$heading   = isset( $args['heading'] ) ? $args['heading'] : '';
		$body_html = isset( $args['body_html'] ) ? $args['body_html'] : '';
		$cta       = isset( $args['cta'] ) && is_array( $args['cta'] ) ? $args['cta'] : array();
		$preheader = isset( $args['preheader'] ) ? $args['preheader'] : '';

		$logo      = self::option( self::OPT_LOGO );
		$accent    = self::option( self::OPT_ACCENT );
		$bg        = self::option( self::OPT_BG );
		$btn_bg    = self::option( self::OPT_BUTTON_BG );
		$btn_text  = self::option( self::OPT_BUTTON_TEXT );
		$footer    = self::option( self::OPT_FOOTER );

		$site = get_bloginfo( 'name' );

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
				<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:100%; background:#ffffff; border-radius:8px; overflow:hidden; border:1px solid #e6e8ea;">
					<?php if ( '' !== trim( (string) $logo ) ) : ?>
					<tr>
						<td align="center" style="padding:28px 32px 0;">
							<img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $site ); ?>" style="max-height:56px; max-width:70%; height:auto; display:block;" />
						</td>
					</tr>
					<?php endif; ?>
					<?php if ( '' !== trim( (string) $heading ) ) : ?>
					<tr>
						<td style="padding:28px 32px 0;">
							<h1 style="margin:0; font-family:<?php echo esc_attr( $family ); ?>; font-size:22px; line-height:1.3; font-weight:700; color:<?php echo esc_attr( $accent ); ?>;">
								<?php echo esc_html( $heading ); ?>
							</h1>
						</td>
					</tr>
					<?php endif; ?>
					<tr>
						<td style="padding:18px 32px 4px; font-family:<?php echo esc_attr( $family ); ?>; font-size:15px; line-height:1.65; color:#2b2d33;">
							<?php echo $body_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Body is pre-built, escaped HTML. ?>
						</td>
					</tr>
					<?php if ( ! empty( $cta['url'] ) && ! empty( $cta['label'] ) ) : ?>
					<tr>
						<td style="padding:12px 32px 8px;">
							<table role="presentation" cellpadding="0" cellspacing="0">
								<tr>
									<td align="center" style="border-radius:6px; background:<?php echo esc_attr( $btn_bg ); ?>;">
										<a href="<?php echo esc_url( $cta['url'] ); ?>" style="display:inline-block; padding:13px 28px; font-family:<?php echo esc_attr( $family ); ?>; font-size:15px; font-weight:600; color:<?php echo esc_attr( $btn_text ); ?>; text-decoration:none; border-radius:6px;">
											<?php echo esc_html( $cta['label'] ); ?>
										</a>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<?php endif; ?>
					<tr>
						<td style="padding:24px 32px 30px;">
							<hr style="border:none; border-top:1px solid #e6e8ea; margin:0 0 16px;" />
							<p style="margin:0; font-family:<?php echo esc_attr( $family ); ?>; font-size:12px; line-height:1.6; color:#6b7280;">
								<?php echo nl2br( esc_html( $footer ) ); ?>
							</p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
		<?php
		return ob_get_clean();
	}
}
