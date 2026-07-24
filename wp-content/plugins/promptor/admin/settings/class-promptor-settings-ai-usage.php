<?php
/**
 * AI Usage & Cost Guardrails settings tab.
 *
 * Free: limit configuration + current usage counters.
 * Pro:  adds visual dashboard widget (handled in Dashboard page).
 *
 * @package Promptor
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Promptor_Settings_Tab_Ai_Usage {

	/**
	 * Register WordPress settings for this tab.
	 */
	public function register_settings() {
		register_setting(
			'promptor_ai_usage_options_group',
			'promptor_cost_limits',
			array( $this, 'sanitize_cost_limits' )
		);
	}

	/**
	 * Sanitize cost-limit settings coming from the form.
	 *
	 * @param array $input Raw form input.
	 * @return array Sanitized settings.
	 */
	public function sanitize_cost_limits( $input ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$clean = array(
			'daily_enabled'   => ! empty( $input['daily_enabled'] ),
			'daily_limit'     => max( 1000, absint( $input['daily_limit'] ?? 100000 ) ),
			'monthly_enabled' => ! empty( $input['monthly_enabled'] ),
			'monthly_limit'   => max( 10000, absint( $input['monthly_limit'] ?? 2000000 ) ),
			'on_limit_action' => in_array( $input['on_limit_action'] ?? '', array( 'pause', 'fallback', 'warn' ), true )
				? $input['on_limit_action']
				: 'fallback',
		);

		add_settings_error(
			'promptor_cost_limits',
			'cost_limits_saved',
			__( 'AI usage settings saved.', 'promptor' ),
			'success'
		);

		return $clean;
	}

	/**
	 * Render the AI Usage tab content.
	 */
	public function render() {
		$limits  = Promptor_Cost_Guard::get_limits();
		$daily   = Promptor_Cost_Guard::get_daily_summary();
		$monthly = Promptor_Cost_Guard::get_monthly_summary();
		?>
		<!-- Current Usage Summary — two separate boxes side by side -->
		<div class="promptor-secondary-metrics" style="margin-top: 15px;">
			<div class="promptor-info-box">
				<h3>
					<span class="dashicons dashicons-clock"></span>
					<?php esc_html_e( 'Today', 'promptor' ); ?>
					<small><?php echo esc_html( gmdate( 'M j, Y' ) ); ?></small>
				</h3>
				<div class="info-box-content">
					<?php $this->render_usage_bar( $daily ); ?>
				</div>
			</div>

			<div class="promptor-info-box">
				<h3>
					<span class="dashicons dashicons-calendar-alt"></span>
					<?php esc_html_e( 'This Month', 'promptor' ); ?>
					<small><?php echo esc_html( gmdate( 'F Y' ) ); ?></small>
				</h3>
				<div class="info-box-content">
					<?php $this->render_usage_bar( $monthly ); ?>
				</div>
			</div>
		</div>

		<p class="description" style="margin: 12px 0 20px;">
			<?php
			printf(
				/* translators: %s: token explanation */
				esc_html__( 'Tip: 1 conversation typically uses 500-1,000 tokens. Estimated cost is based on model pricing (GPT-4o: $5/$15 per 1M tokens, GPT-3.5: $0.50/$1.50 per 1M tokens).', 'promptor' )
			);
			?>
		</p>

		<!-- Cost Limit Settings Form -->
		<form method="post" action="options.php">
			<?php settings_fields( 'promptor_ai_usage_options_group' ); ?>

			<div class="postbox">
				<h2 class="hndle">
					<span class="dashicons dashicons-shield promptor-hndle-icon"></span>
					<?php esc_html_e( 'Spending Limits', 'promptor' ); ?>
				</h2>
				<div class="inside">
					<p><?php esc_html_e( 'Set daily and monthly token limits to prevent surprise OpenAI bills. When a limit is reached, the action you choose below will be applied.', 'promptor' ); ?></p>

					<table class="form-table">
						<!-- Daily Limit -->
						<tr>
							<th scope="row"><?php esc_html_e( 'Daily Limit', 'promptor' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="promptor_cost_limits[daily_enabled]" value="1" <?php checked( $limits['daily_enabled'] ); ?> />
									<?php esc_html_e( 'Enable daily token limit', 'promptor' ); ?>
								</label>
								<br /><br />
								<input type="number" name="promptor_cost_limits[daily_limit]" value="<?php echo esc_attr( $limits['daily_limit'] ); ?>" min="1000" step="1000" class="regular-text" />
								<p class="description">
									<?php
									printf(
										/* translators: %s: estimated cost */
										esc_html__( 'Tokens per day. Default: 100,000 (~%s/day with GPT-4o).', 'promptor' ),
										'$2.00'
									);
									?>
								</p>
							</td>
						</tr>

						<!-- Monthly Limit -->
						<tr>
							<th scope="row"><?php esc_html_e( 'Monthly Limit', 'promptor' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="promptor_cost_limits[monthly_enabled]" value="1" <?php checked( $limits['monthly_enabled'] ); ?> />
									<?php esc_html_e( 'Enable monthly token limit', 'promptor' ); ?>
								</label>
								<br /><br />
								<input type="number" name="promptor_cost_limits[monthly_limit]" value="<?php echo esc_attr( $limits['monthly_limit'] ); ?>" min="10000" step="10000" class="regular-text" />
								<p class="description">
									<?php
									printf(
										/* translators: %s: estimated cost */
										esc_html__( 'Tokens per month. Default: 2,000,000 (~%s/month with GPT-4o).', 'promptor' ),
										'$40'
									);
									?>
								</p>
							</td>
						</tr>

						<!-- On Limit Action -->
						<tr>
							<th scope="row"><?php esc_html_e( 'When Limit Reached', 'promptor' ); ?></th>
							<td>
								<fieldset>
									<label>
										<input type="radio" name="promptor_cost_limits[on_limit_action]" value="fallback" <?php checked( $limits['on_limit_action'], 'fallback' ); ?> />
										<?php esc_html_e( 'Fallback to GPT-3.5-turbo (recommended — 10x cheaper, chat stays active)', 'promptor' ); ?>
									</label><br />
									<label>
										<input type="radio" name="promptor_cost_limits[on_limit_action]" value="pause" <?php checked( $limits['on_limit_action'], 'pause' ); ?> />
										<?php esc_html_e( 'Pause AI chat (visitors see a friendly "temporarily unavailable" message)', 'promptor' ); ?>
									</label><br />
									<label>
										<input type="radio" name="promptor_cost_limits[on_limit_action]" value="warn" <?php checked( $limits['on_limit_action'], 'warn' ); ?> />
										<?php esc_html_e( 'Warn only (log warning but continue using the configured model)', 'promptor' ); ?>
									</label>
								</fieldset>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<!-- Warning Thresholds Info -->
			<div class="postbox">
				<h2 class="hndle">
					<span class="dashicons dashicons-email-alt promptor-hndle-icon"></span>
					<?php esc_html_e( 'Email Alerts', 'promptor' ); ?>
				</h2>
				<div class="inside">
					<p>
						<?php
						printf(
							/* translators: %s: admin email */
							esc_html__( 'When limits are enabled, warning emails are sent to %s at these thresholds:', 'promptor' ),
							'<strong>' . esc_html( get_option( 'admin_email' ) ) . '</strong>'
						);
						?>
					</p>
					<table class="widefat striped" style="max-width: 500px;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Threshold', 'promptor' ); ?></th>
								<th><?php esc_html_e( 'Action', 'promptor' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><span class="promptor-status-warn" style="font-weight: bold;">80%</span></td>
								<td><?php esc_html_e( 'Warning email sent to admin', 'promptor' ); ?></td>
							</tr>
							<tr>
								<td><span class="promptor-status-error" style="font-weight: bold;">90%</span></td>
								<td><?php esc_html_e( 'Urgent warning email sent', 'promptor' ); ?></td>
							</tr>
							<tr>
								<td><span class="promptor-status-error" style="font-weight: bold;">100%</span></td>
								<td><?php esc_html_e( 'Limit reached — chosen action applied', 'promptor' ); ?></td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>

			<?php submit_button( __( 'Save AI Usage Settings', 'promptor' ) ); ?>
		</form>

		<?php if ( promptor_is_pro() ) : ?>
		<!-- Pro: Usage History Teaser (actual charts in Dashboard) -->
		<div class="postbox">
			<h2 class="hndle">
				<span class="dashicons dashicons-chart-bar promptor-hndle-icon"></span>
				<?php esc_html_e( 'Usage Analytics', 'promptor' ); ?>
			</h2>
			<div class="inside">
				<p><?php esc_html_e( 'Detailed usage charts, model breakdown, and cost trends are available on the Dashboard.', 'promptor' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=promptor-dashboard' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'View Dashboard', 'promptor' ); ?>
				</a>
			</div>
		</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render a usage progress bar.
	 *
	 * @param array $summary Usage summary from Cost_Guard.
	 */
	private function render_usage_bar( array $summary ) {
		$tokens     = $summary['tokens'] ?? 0;
		$cost       = $summary['cost'] ?? 0.0;
		$limit      = $summary['limit'] ?? 0;
		$percentage = $summary['percentage'] ?? 0;
		$enabled    = $summary['enabled'] ?? false;

		// Choose colour based on percentage.
		if ( $percentage >= 90 ) {
			$bar_color = '#d63638';
		} elseif ( $percentage >= 80 ) {
			$bar_color = '#dba617';
		} else {
			$bar_color = '#00a32a';
		}
		?>
		<div class="promptor-usage-meter" style="margin: 8px 0;">
			<div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
				<span>
					<strong><?php echo esc_html( number_format( $tokens ) ); ?></strong>
					<?php esc_html_e( 'tokens', 'promptor' ); ?>
				</span>
				<span>
					<?php if ( $enabled && $limit > 0 ) : ?>
						<?php echo esc_html( number_format( $limit ) ); ?> <?php esc_html_e( 'limit', 'promptor' ); ?>
					<?php else : ?>
						<em><?php esc_html_e( 'No limit set', 'promptor' ); ?></em>
					<?php endif; ?>
				</span>
			</div>

			<?php if ( $enabled && $limit > 0 ) : ?>
			<div class="promptor-usage-progress-bg-tall">
				<div class="promptor-usage-progress-fill" style="background: <?php echo esc_attr( $bar_color ); ?>; width: <?php echo esc_attr( min( 100, $percentage ) ); ?>%;"></div>
			</div>
			<div style="display: flex; justify-content: space-between; margin-top: 4px;">
				<span><?php echo esc_html( $percentage ); ?>%</span>
				<span>
					~$<?php echo esc_html( number_format( $cost, 2 ) ); ?>
					<?php esc_html_e( 'estimated cost', 'promptor' ); ?>
				</span>
			</div>
			<?php else : ?>
			<p class="description">
				~$<?php echo esc_html( number_format( $cost, 2 ) ); ?> <?php esc_html_e( 'estimated cost', 'promptor' ); ?>
			</p>
			<?php endif; ?>
		</div>
		<?php
	}
}
