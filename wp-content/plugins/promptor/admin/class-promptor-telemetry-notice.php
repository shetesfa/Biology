<?php
/**
 * Telemetry opt-in notice with Pro coupon incentive.
 *
 * @package Promptor
 * @since   1.2.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Promptor_Telemetry_Notice {

	/**
	 * Initialize telemetry notice display hooks.
	 *
	 * Note: handle_notice_actions is registered on admin_init directly in the loader
	 * (before current_screen fires) to ensure GET actions are processed correctly.
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'show_opt_in_notice' ) );
		add_action( 'admin_notices', array( __CLASS__, 'show_coupon_received_notice' ) );
		// admin_init hook for handle_notice_actions is added in class-promptor-loader.php
		// to ensure it fires before current_screen (admin_init fires before current_screen).
	}

	/**
	 * Check if notice should be shown.
	 *
	 * @return bool
	 */
	private static function should_show_notice() {
		// Don't show if already enabled
		if ( Promptor_Telemetry::is_enabled() ) {
			return false;
		}

		// Don't show if dismissed
		if ( get_option( 'promptor_telemetry_notice_dismissed', false ) ) {
			return false;
		}

		// Only show on Promptor admin pages
		$screen = get_current_screen();
		if ( ! is_object( $screen ) || ! isset( $screen->id ) || strpos( $screen->id, 'promptor' ) === false ) {
			return false;
		}

		return true;
	}

	/**
	 * Show telemetry opt-in notice with Pro coupon incentive.
	 */
	public static function show_opt_in_notice() {
		if ( ! self::should_show_notice() ) {
			return;
		}

		$enable_url = wp_nonce_url(
			add_query_arg(
				array(
					'promptor_action' => 'enable_telemetry',
				)
			),
			'promptor_enable_telemetry'
		);

		$dismiss_url = wp_nonce_url(
			add_query_arg(
				array(
					'promptor_action' => 'dismiss_telemetry_notice',
				)
			),
			'promptor_dismiss_telemetry'
		);

		?>
		<div class="notice notice-info is-dismissible promptor-telemetry-notice">
			<h3 style="margin-top: 0;">🎁 <?php esc_html_e( 'Help Us Improve Promptor & Get 30% Off Pro!', 'promptor' ); ?></h3>
			<p style="margin: 10px 0;">
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: %s: link to settings page */
						__( 'Enable <strong>anonymous usage insights</strong> (no personal data) to help us prioritize features and bug fixes. As a thank you, you can use coupon code <strong>PRO30</strong> for 30%% off Promptor Pro!', 'promptor' )
					)
				);
				?>
			</p>
			<p style="margin: 10px 0; font-size: 13px; color: #666;">
				<strong><?php esc_html_e( 'What we collect:', 'promptor' ); ?></strong>
				<?php esc_html_e( 'Setup progress, KB counts, query/lead totals (numbers only). ', 'promptor' ); ?>
				<strong><?php esc_html_e( 'What we DON\'T collect:', 'promptor' ); ?></strong>
				<?php esc_html_e( 'Site URLs, IPs, emails, content, or any personal data.', 'promptor' ); ?>
			</p>
			<p style="margin: 15px 0 5px;">
				<a href="<?php echo esc_url( $enable_url ); ?>" class="button button-primary">
					✓ <?php esc_html_e( 'Enable Telemetry & Use PRO30 Coupon', 'promptor' ); ?>
				</a>
				<a href="<?php echo esc_url( $dismiss_url ); ?>" class="button">
					<?php esc_html_e( 'No Thanks', 'promptor' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=promptor-settings&tab=telemetry' ) ); ?>" style="margin-left: 10px;">
					<?php esc_html_e( 'Learn More', 'promptor' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Show coupon notice (disabled - using static PRO30 coupon).
	 */
	public static function show_coupon_received_notice() {
		// Disabled: Using static PRO30 coupon code instead of dynamic backend coupons
		return;
	}

	/**
	 * Handle notice actions (enable, dismiss).
	 */
	public static function handle_notice_actions() {
		if ( ! isset( $_GET['promptor_action'] ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_GET['promptor_action'] ) );

		// Enable telemetry
		if ( $action === 'enable_telemetry' && check_admin_referer( 'promptor_enable_telemetry' ) ) {
			Promptor_Telemetry::enable();
			update_option( 'promptor_telemetry_notice_dismissed', true );

			// Redirect to avoid resubmission
			wp_safe_redirect( remove_query_arg( array( 'promptor_action', '_wpnonce' ) ) );
			exit;
		}

		// Dismiss notice
		if ( $action === 'dismiss_telemetry_notice' && check_admin_referer( 'promptor_dismiss_telemetry' ) ) {
			update_option( 'promptor_telemetry_notice_dismissed', true );

			wp_safe_redirect( remove_query_arg( array( 'promptor_action', '_wpnonce' ) ) );
			exit;
		}

		// Dismiss coupon notice
		if ( $action === 'dismiss_coupon_notice' && check_admin_referer( 'promptor_dismiss_coupon' ) ) {
			update_option( 'promptor_telemetry_coupon_notice_dismissed', true );

			wp_safe_redirect( remove_query_arg( array( 'promptor_action', '_wpnonce' ) ) );
			exit;
		}
	}
}
