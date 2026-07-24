<?php
/**
 * Telemetry settings tab for Promptor admin.
 *
 * @package Promptor
 * @since   1.2.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Promptor_Settings_Tab_Telemetry {

	/**
	 * Initialize.
	 */
	public function __construct() {
		add_action( 'admin_post_promptor_save_telemetry', array( $this, 'save_telemetry_settings' ) );
		add_action( 'admin_post_promptor_reset_telemetry_id', array( $this, 'reset_telemetry_id' ) );
		add_action( 'admin_post_promptor_clear_telemetry_queue', array( $this, 'clear_telemetry_queue' ) );
	}

	/**
	 * Register settings (if needed).
	 */
	public function register_settings() {
		// No WP settings API needed - using custom save handler
	}

	/**
	 * Save telemetry settings.
	 */
	public function save_telemetry_settings() {
		// Authority check
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'promptor' ) );
		}

		// Nonce verification
		$nonce = isset( $_POST['promptor_telemetry_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['promptor_telemetry_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'promptor_telemetry_save_action' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'promptor' ) );
		}

		// Get checkbox value
		$enabled = ! empty( $_POST['promptor_telemetry_enabled'] );

		// Load telemetry class
		require_once PROMPTOR_PATH . 'admin/class-promptor-telemetry.php';

		// Enable/disable
		if ( $enabled ) {
			Promptor_Telemetry::enable();
			// Send immediately when first enabled
			Promptor_Telemetry::send_telemetry();
		} else {
			Promptor_Telemetry::disable();
		}

		// Success message
		add_settings_error( 'promptor-settings', 'settings_updated', __( 'Telemetry settings saved.', 'promptor' ), 'updated' );
		$errors = get_settings_errors( 'promptor-settings' );
		set_transient( 'settings_errors', $errors, 45 );

		// Redirect
		$redirect_url = add_query_arg(
			array(
				'page'              => 'promptor-settings',
				'tab'               => 'telemetry',
				'settings-updated'  => 'true',
				'promptor_nonce'    => wp_create_nonce( 'promptor_settings_tabs_action' ),
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Reset telemetry ID.
	 */
	public function reset_telemetry_id() {
		// Authority check
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'promptor' ) );
		}

		// Nonce verification
		$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'promptor_reset_telemetry_id' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'promptor' ) );
		}

		// Reset ID
		require_once PROMPTOR_PATH . 'admin/class-promptor-telemetry.php';
		Promptor_Telemetry::reset_id();

		// Success message
		add_settings_error( 'promptor-settings', 'id_reset', __( 'Telemetry ID reset successfully.', 'promptor' ), 'updated' );
		$errors = get_settings_errors( 'promptor-settings' );
		set_transient( 'settings_errors', $errors, 45 );

		// Redirect
		$redirect_url = add_query_arg(
			array(
				'page'              => 'promptor-settings',
				'tab'               => 'telemetry',
				'settings-updated'  => 'true',
				'promptor_nonce'    => wp_create_nonce( 'promptor_settings_tabs_action' ),
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Clear telemetry queue.
	 */
	public function clear_telemetry_queue() {
		// Authority check
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'promptor' ) );
		}

		// Nonce verification
		$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'promptor_clear_telemetry_queue' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'promptor' ) );
		}

		// Clear queue
		require_once PROMPTOR_PATH . 'admin/class-promptor-telemetry.php';
		Promptor_Telemetry::clear_queue();

		// Success message
		add_settings_error( 'promptor-settings', 'queue_cleared', __( 'Telemetry queue cleared successfully.', 'promptor' ), 'updated' );
		$errors = get_settings_errors( 'promptor-settings' );
		set_transient( 'settings_errors', $errors, 45 );

		// Redirect
		$redirect_url = add_query_arg(
			array(
				'page'              => 'promptor-settings',
				'tab'               => 'telemetry',
				'settings-updated'  => 'true',
				'promptor_nonce'    => wp_create_nonce( 'promptor_settings_tabs_action' ),
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Render settings page.
	 */
	public function render() {
		// Authority check
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'promptor' ) );
		}

		// Load telemetry class
		require_once PROMPTOR_PATH . 'admin/class-promptor-telemetry.php';

		$enabled = Promptor_Telemetry::is_enabled();
		$id      = Promptor_Telemetry::get_id();
		$queue   = Promptor_Telemetry::get_queue();

		?>
		<div class="postbox">
			<h2 class="hndle"><span class="dashicons dashicons-chart-pie promptor-hndle-icon"></span><span><?php esc_html_e( 'Anonymous Usage Data', 'promptor' ); ?></span></h2>
			<div class="inside">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="promptor_save_telemetry" />
					<?php wp_nonce_field( 'promptor_telemetry_save_action', 'promptor_telemetry_nonce' ); ?>

					<table class="form-table">
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Anonymous Usage Data', 'promptor' ); ?></th>
							<td>
								<label>
									<input
										type="checkbox"
										name="promptor_telemetry_enabled"
										value="1"
										<?php checked( $enabled, true ); ?>
									/>
									<?php esc_html_e( 'Enable anonymous usage data collection', 'promptor' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'This is optional and disabled by default. When enabled, anonymous usage data can be shared to help improve Promptor.', 'promptor' ); ?>
									<br>
									<strong><?php esc_html_e( 'What we collect:', 'promptor' ); ?></strong>
									<?php esc_html_e( 'Setup progress, KB counts, query/lead totals (numbers only), plugin/WP/PHP versions.', 'promptor' ); ?>
									<br>
									<strong><?php esc_html_e( 'What we do NOT collect:', 'promptor' ); ?></strong>
									<?php esc_html_e( 'Site URLs, IPs, emails, usernames, content, queries, or any personally identifiable information.', 'promptor' ); ?>
								</p>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Save Telemetry Settings', 'promptor' ) ); ?>
				</form>
			</div>
		</div>

		<?php if ( $enabled ) : ?>
			<div class="postbox">
				<h2 class="hndle"><span class="dashicons dashicons-info promptor-hndle-icon"></span><span><?php esc_html_e( 'Telemetry Information', 'promptor' ); ?></span></h2>
				<div class="inside">
					<table class="form-table">
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Anonymous ID', 'promptor' ); ?></th>
							<td>
								<code style="font-size: 13px; background: #f0f0f1; padding: 4px 8px; border-radius: 3px;"><?php echo esc_html( $id ); ?></code>
								<p class="description">
									<?php esc_html_e( 'This random UUID identifies your installation anonymously. No personal data is linked to it.', 'promptor' ); ?>
								</p>
								<p>
									<a
										href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=promptor_reset_telemetry_id' ), 'promptor_reset_telemetry_id', 'nonce' ) ); ?>"
										class="button button-secondary"
										onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to generate a new telemetry ID?', 'promptor' ) ); ?>');"
									>
										<?php esc_html_e( 'Reset Telemetry ID', 'promptor' ); ?>
									</a>
								</p>
							</td>
						</tr>

						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Current Queue', 'promptor' ); ?></th>
							<td>
								<?php if ( empty( $queue ) ) : ?>
									<p><?php esc_html_e( 'No telemetry data in queue.', 'promptor' ); ?></p>
								<?php else : ?>
									<details style="background: #f0f0f1; padding: 10px; border-radius: 4px;">
										<summary style="cursor: pointer; font-weight: 600; margin-bottom: 10px;">
											<?php esc_html_e( 'View Queued Data', 'promptor' ); ?>
										</summary>
										<pre style="background: #fff; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 12px;"><?php echo esc_html( wp_json_encode( $queue, JSON_PRETTY_PRINT ) ); ?></pre>
									</details>
									<p>
										<a
											href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=promptor_clear_telemetry_queue' ), 'promptor_clear_telemetry_queue', 'nonce' ) ); ?>"
											class="button button-secondary"
											onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to clear the telemetry queue?', 'promptor' ) ); ?>');"
										>
											<?php esc_html_e( 'Clear Telemetry Queue', 'promptor' ); ?>
										</a>
									</p>
								<?php endif; ?>
							</td>
						</tr>

						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Sending Schedule', 'promptor' ); ?></th>
							<td>
								<?php
								$next_run = wp_next_scheduled( Promptor_Telemetry::CRON_HOOK );
								if ( $next_run ) {
									$next_date = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $next_run ), get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
									?>
									<p>
										<?php esc_html_e( 'Next scheduled send:', 'promptor' ); ?>
										<strong><?php echo esc_html( $next_date ); ?></strong>
									</p>
									<?php
								} else {
									esc_html_e( 'No cron job scheduled.', 'promptor' );
								}
								?>
								<p class="description">
									<?php esc_html_e( 'Telemetry data is sent automatically twice daily via WP-Cron.', 'promptor' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</div>
			</div>
		<?php endif; ?>

		<div class="postbox">
			<h2 class="hndle"><span class="dashicons dashicons-shield promptor-hndle-icon"></span><span><?php esc_html_e( 'Privacy & Transparency', 'promptor' ); ?></span></h2>
			<div class="inside">
				<h3><?php esc_html_e( 'Our Commitment', 'promptor' ); ?></h3>
				<p><?php esc_html_e( 'Promptor respects your privacy. Telemetry is completely optional and OFF by default.', 'promptor' ); ?></p>

				<h4><?php esc_html_e( 'What Gets Collected (If Opted-In)', 'promptor' ); ?></h4>
				<ul style="list-style: disc; margin-left: 20px;">
					<li><?php esc_html_e( 'Setup completion status (which steps completed)', 'promptor' ); ?></li>
					<li><?php esc_html_e( 'Knowledge base count and total indexed items', 'promptor' ); ?></li>
					<li><?php esc_html_e( 'Total queries sent and leads captured (counts only)', 'promptor' ); ?></li>
					<li><?php esc_html_e( 'Plugin version, WordPress version, PHP version', 'promptor' ); ?></li>
				</ul>

				<h4><?php esc_html_e( 'What We NEVER Collect', 'promptor' ); ?></h4>
				<ul style="list-style: disc; margin-left: 20px;">
					<li><?php esc_html_e( 'Site URLs or domain names', 'promptor' ); ?></li>
					<li><?php esc_html_e( 'IP addresses', 'promptor' ); ?></li>
					<li><?php esc_html_e( 'Admin emails or usernames', 'promptor' ); ?></li>
					<li><?php esc_html_e( 'Content from your site', 'promptor' ); ?></li>
					<li><?php esc_html_e( 'Query text or message content', 'promptor' ); ?></li>
					<li><?php esc_html_e( 'Any personally identifiable information (PII)', 'promptor' ); ?></li>
				</ul>

				<p>
					<strong><?php esc_html_e( 'You have full control:', 'promptor' ); ?></strong>
					<?php esc_html_e( 'Disable telemetry anytime, reset your ID, or clear the queue. No questions asked.', 'promptor' ); ?>
				</p>
			</div>
		</div>
		<?php
	}
}
