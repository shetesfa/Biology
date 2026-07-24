<?php
/**
 * HTML Email Template Wrapper.
 *
 * Wraps plain-text notification bodies in a responsive, branded HTML layout
 * with inline CSS for maximum email-client compatibility.
 *
 * @package Promptor
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Promptor_Email_Template {

	/**
	 * Wrap email body in branded HTML template.
	 *
	 * @param string $subject Email subject (used in preheader).
	 * @param string $body    Plain-text or simple HTML body.
	 * @param string $type    Email type: info | warning | success | error.
	 * @return string Full HTML email.
	 */
	public static function wrap( $subject, $body, $type = 'info' ) {
		$colors = self::get_type_colors( $type );
		$site   = get_bloginfo( 'name' );
		$year   = gmdate( 'Y' );
		$body   = wpautop( wp_kses_post( $body ) );

		ob_start();
		?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_locale() ); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title><?php echo esc_html( $subject ); ?></title>
<!--[if mso]>
<style>table,td{font-family:Arial,sans-serif!important;}</style>
<![endif]-->
</head>
<body style="margin:0;padding:0;background-color:#f4f6f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',Arial,sans-serif;-webkit-font-smoothing:antialiased;">

<!-- Preheader (hidden preview text) -->
<div style="display:none;font-size:1px;color:#f4f6f9;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">
	<?php echo esc_html( wp_strip_all_tags( $subject ) ); ?>
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f9;">
<tr><td align="center" style="padding:30px 15px;">

<!-- Container -->
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

	<!-- Header -->
	<tr>
		<td style="background:linear-gradient(135deg,<?php echo esc_attr( $colors['gradient_start'] ); ?> 0%,<?php echo esc_attr( $colors['gradient_end'] ); ?> 100%);padding:28px 40px;text-align:left;">
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td style="color:#ffffff;font-size:22px;font-weight:700;letter-spacing:-0.5px;">
						<?php echo esc_html( $site ); ?>
					</td>
					<td align="right" style="color:rgba(255,255,255,0.85);font-size:13px;font-weight:400;">
						Promptor
					</td>
				</tr>
			</table>
		</td>
	</tr>

	<!-- Accent Bar -->
	<tr>
		<td style="height:4px;background-color:<?php echo esc_attr( $colors['accent'] ); ?>;font-size:0;line-height:0;">&nbsp;</td>
	</tr>

	<!-- Body -->
	<tr>
		<td style="padding:32px 40px;color:#1d2327;font-size:15px;line-height:1.65;">
			<?php echo wp_kses_post( $body ); ?>
		</td>
	</tr>

	<!-- Footer -->
	<tr>
		<td style="padding:20px 40px;background-color:#f8fafc;border-top:1px solid #e2e8f0;text-align:center;">
			<p style="margin:0;color:#94a3b8;font-size:12px;line-height:1.5;">
				<?php
				printf(
					/* translators: 1: site name, 2: current year */
					esc_html__( '%1$s &copy; %2$s — Powered by Promptor', 'promptor' ),
					esc_html( $site ),
					esc_html( $year )
				);
				?>
			</p>
			<p style="margin:8px 0 0;color:#94a3b8;font-size:11px;">
				<?php esc_html_e( 'This is an automated notification. Please do not reply directly to this email.', 'promptor' ); ?>
			</p>
		</td>
	</tr>

</table>
<!-- /Container -->

</td></tr>
</table>

</body>
</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get color scheme for email type.
	 *
	 * @param string $type info | warning | success | error.
	 * @return array
	 */
	private static function get_type_colors( $type ) {
		$schemes = array(
			'info'    => array(
				'gradient_start' => '#15b6ff',
				'gradient_end'   => '#0a8fd4',
				'accent'         => '#15b6ff',
			),
			'success' => array(
				'gradient_start' => '#10B981',
				'gradient_end'   => '#059669',
				'accent'         => '#10B981',
			),
			'warning' => array(
				'gradient_start' => '#F59E0B',
				'gradient_end'   => '#D97706',
				'accent'         => '#F59E0B',
			),
			'error'   => array(
				'gradient_start' => '#EF4444',
				'gradient_end'   => '#DC2626',
				'accent'         => '#EF4444',
			),
		);

		return $schemes[ $type ] ?? $schemes['info'];
	}
}
