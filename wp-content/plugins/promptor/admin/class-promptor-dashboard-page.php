<?php
/**
 * Promptor Dashboard Page Class
 *
 * Handles the dashboard page rendering and data fetching
 * with proper WordPress security and caching standards.
 *
 * @package Promptor
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Promptor_Dashboard_Page
 *
 * Manages the dashboard interface and metrics display.
 */
class Promptor_Dashboard_Page {

	/**
	 * Render the dashboard page
	 *
	 * @return void
	 */
	public function render_page() {
		// 1) Capability check (early return if unauthorized).
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'promptor' ) );
		}

		// 2) Safe premium detection: avoid fatals if Freemius is not available.
		$is_premium = false;
		if ( function_exists( 'promptor_fs' ) && method_exists( promptor_fs(), 'can_use_premium_code__premium_only' ) ) {
			$is_premium = (bool) promptor_fs()->can_use_premium_code__premium_only();
		}

		if ( ! $is_premium ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Dashboard', 'promptor' ) . '</h1>';
			echo '<div class="promptor-inline-notice notice-info"><span class="dashicons dashicons-info"></span><p>';

			// Build Upgrade URL; escape on output.
			$upgrade_url = ( function_exists( 'promptor_fs' ) && method_exists( promptor_fs(), 'get_upgrade_url' ) )
				? promptor_fs()->get_upgrade_url()
				: 'https://promptorai.com/pricing/';

			echo wp_kses_post(
				sprintf(
					/* translators: %s: Upgrade to Pro URL */
					__( 'Advanced analytics and performance metrics are available in the Pro version. <a href="%s" target="_blank" rel="noopener noreferrer"><strong>Upgrade to Pro</strong></a> to unlock the dashboard!', 'promptor' ),
					esc_url( $upgrade_url )
				)
			);

			echo '</p></div></div>';
			return;
		}

		// Security: changing `period` or triggering `force_refresh` requires a nonce.
		$allowed_periods = array( 'daily', 'weekly', 'monthly', 'yearly' );
		$has_action      = ( isset( $_GET['period'] ) || isset( $_GET['force_refresh'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $has_action ) {
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'promptor_dashboard_action' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'promptor' ) );
			}
		}

		$period = isset( $_GET['period'] ) ? sanitize_key( wp_unslash( $_GET['period'] ) ) : 'monthly'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $period, $allowed_periods, true ) ) {
			$period = 'monthly';
		}

		// Only honor `force_refresh` when the nonce is valid.
		if ( $has_action && isset( $_GET['force_refresh'] ) && 'true' === sanitize_text_field( wp_unslash( $_GET['force_refresh'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_cache_delete( 'promptor_dashboard_data_' . $period, 'promptor' );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Dashboard cache for the selected period has been cleared. Data is being regenerated.', 'promptor' ) . '</p></div>';
		}

		$cache_key      = 'promptor_dashboard_data_' . $period;
		$dashboard_data = wp_cache_get( $cache_key, 'promptor' );

		if ( false === $dashboard_data ) {
			$dashboard_data = $this->fetch_dashboard_data( $period );
			wp_cache_set( $cache_key, $dashboard_data, 'promptor', 3 * HOUR_IN_SECONDS );
		}

		// Extract all dashboard data.
		$current_period         = $dashboard_data['current_period'];
		$total_queries          = $dashboard_data['total_queries'];
		$total_submissions      = $dashboard_data['total_submissions'];
		$conversion_rate        = $dashboard_data['conversion_rate'];
		$satisfaction_rate      = $dashboard_data['satisfaction_rate'];
		$avg_response_time_ms   = $dashboard_data['avg_response_time_ms'];
		$total_revenue          = $dashboard_data['total_revenue'];
		$total_products_in_cart = $dashboard_data['total_products_in_cart'];
		$crawled_pages          = $dashboard_data['crawled_pages'];
		$woo_products           = $dashboard_data['woo_products'];
		$chart_labels           = $dashboard_data['chart_labels'];
		$chart_queries_data     = $dashboard_data['chart_queries_data'];
		$chart_submissions_data = $dashboard_data['chart_submissions_data'];
		$chart_revenue_data     = $dashboard_data['chart_revenue_data'];
		$lead_pipeline_data     = $dashboard_data['lead_pipeline_data'];
		$service_totals         = $dashboard_data['service_totals'];
		$top_selling_products   = $dashboard_data['top_selling_products'];
		$recent_submissions     = $dashboard_data['recent_submissions'];
		$recent_queries         = $dashboard_data['recent_queries'];
		$system_status          = $dashboard_data['system_status'];

		$avg_response_time_s = $avg_response_time_ms > 0 ? number_format( $avg_response_time_ms / 1000, 2 ) . 's' : 'N/A';
		$pipeline_labels     = array(
			__( 'Pending', 'promptor' ),
			__( 'Contacted', 'promptor' ),
			__( 'Converted', 'promptor' ),
			__( 'Rejected', 'promptor' ),
		);
		$pipeline_values     = array(
			isset( $lead_pipeline_data['pending'] ) ? $lead_pipeline_data['pending']->count : 0,
			isset( $lead_pipeline_data['contacted'] ) ? $lead_pipeline_data['contacted']->count : 0,
			isset( $lead_pipeline_data['converted'] ) ? $lead_pipeline_data['converted']->count : 0,
			isset( $lead_pipeline_data['rejected'] ) ? $lead_pipeline_data['rejected']->count : 0,
		);

		wp_localize_script(
			'promptor',
			'promptor_dashboard_ajax',
			array(
				'activity' => array(
					'labels'       => $chart_labels,
					'queries'      => $chart_queries_data,
					'submissions'  => $chart_submissions_data,
					'revenue'      => $chart_revenue_data,
					'satisfaction' => $chart_satisfaction_data,
				),
				'pipeline' => array(
					'labels' => $pipeline_labels,
					'values' => $pipeline_values,
				),
				'i18n'     => array(
					'queries'      => __( 'Queries', 'promptor' ),
					'satisfaction' => __( 'Satisfaction (%)', 'promptor' ),
					'submissions' => __( 'Submissions', 'promptor' ),
					'revenue'     => __( 'Revenue', 'promptor' ),
				),
			)
		);

		$base_url     = admin_url( 'admin.php?page=promptor-dashboard' );
		$refresh_link = wp_nonce_url(
			add_query_arg(
				array(
					'period'        => $period,
					'force_refresh' => 'true',
				),
				$base_url
			),
			'promptor_dashboard_action'
		);
		?>
		<div class="wrap promptor-dashboard-wrap">
			<div class="promptor-dashboard-header">
				<div>
					<h1><?php esc_html_e( 'Performance Dashboard', 'promptor' ); ?></h1>
					<p class="subtitle">
						<?php
						echo wp_kses_post(
							sprintf(
								/* translators: %s: current period label */
								__( 'Showing data for: %s', 'promptor' ),
								'<strong>' . esc_html( $current_period['label'] ) . '</strong>'
							)
						);
						?>
						<a href="<?php echo esc_url( $refresh_link ); ?>" class="promptor-refresh-link">
							(<?php esc_html_e( 'Refresh Data', 'promptor' ); ?>)
						</a>
					</p>
				</div>
				<div class="promptor-period-filter">
					<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'period', 'daily', $base_url ), 'promptor_dashboard_action' ) ); ?>" 
						class="button <?php echo esc_attr( 'daily' === $period ? 'button-primary' : '' ); ?>">
						<?php esc_html_e( 'Daily', 'promptor' ); ?>
					</a>
					<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'period', 'weekly', $base_url ), 'promptor_dashboard_action' ) ); ?>" 
						class="button <?php echo esc_attr( 'weekly' === $period ? 'button-primary' : '' ); ?>">
						<?php esc_html_e( 'Weekly', 'promptor' ); ?>
					</a>
					<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'period', 'monthly', $base_url ), 'promptor_dashboard_action' ) ); ?>" 
						class="button <?php echo esc_attr( 'monthly' === $period ? 'button-primary' : '' ); ?>">
						<?php esc_html_e( 'Monthly', 'promptor' ); ?>
					</a>
					<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'period', 'yearly', $base_url ), 'promptor_dashboard_action' ) ); ?>" 
						class="button <?php echo esc_attr( 'yearly' === $period ? 'button-primary' : '' ); ?>">
						<?php esc_html_e( 'Yearly', 'promptor' ); ?>
					</a>
				</div>
			</div>

			<div class="promptor-kpi-grid">
				<div class="promptor-kpi-card kpi-queries">
					<div class="kpi-icon"><span class="dashicons dashicons-lightbulb"></span></div>
					<div class="kpi-content">
						<div class="kpi-value"><?php echo esc_html( number_format_i18n( $total_queries ) ); ?></div>
						<div class="kpi-label"><?php esc_html_e( 'Total AI Queries', 'promptor' ); ?></div>
					</div>
				</div>
				<div class="promptor-kpi-card kpi-leads">
					<div class="kpi-icon"><span class="dashicons dashicons-format-aside"></span></div>
					<div class="kpi-content">
						<div class="kpi-value"><?php echo esc_html( number_format_i18n( $total_submissions ) ); ?></div>
						<div class="kpi-label"><?php esc_html_e( 'Total Leads', 'promptor' ); ?></div>
					</div>
				</div>
				<div class="promptor-kpi-card kpi-conversion">
					<div class="kpi-icon"><span class="dashicons dashicons-chart-pie"></span></div>
					<div class="kpi-content">
						<div class="kpi-value"><?php echo esc_html( number_format( $conversion_rate, 1 ) ); ?>%</div>
						<div class="kpi-label"><?php esc_html_e( 'Lead Conversion Rate', 'promptor' ); ?></div>
					</div>
				</div>
				<div class="promptor-kpi-card kpi-cart">
					<div class="kpi-icon"><span class="dashicons dashicons-cart"></span></div>
					<div class="kpi-content">
						<div class="kpi-value"><?php echo esc_html( number_format_i18n( $total_products_in_cart ) ); ?></div>
						<div class="kpi-label"><?php esc_html_e( 'Products Added to Cart', 'promptor' ); ?></div>
					</div>
				</div>
				<div class="promptor-kpi-card kpi-revenue">
					<div class="kpi-icon"><span class="dashicons dashicons-money-alt"></span></div>
					<div class="kpi-content">
						<div class="kpi-value">
							<?php echo class_exists( 'WooCommerce' ) ? wp_kses_post( wc_price( $total_revenue ) ) : 'N/A'; ?>
						</div>
						<div class="kpi-label"><?php esc_html_e( 'AI-driven Revenue', 'promptor' ); ?></div>
					</div>
				</div>
				<div class="promptor-kpi-card kpi-satisfaction">
					<div class="kpi-icon"><span class="dashicons dashicons-smiley"></span></div>
					<div class="kpi-content">
						<div class="kpi-value">
							<?php echo $satisfaction_rate ? esc_html( number_format( $satisfaction_rate, 1 ) ) . '%' : 'N/A'; ?>
						</div>
						<div class="kpi-label"><?php esc_html_e( 'Satisfaction Rate', 'promptor' ); ?></div>
					</div>
				</div>
				<div class="promptor-kpi-card kpi-response">
					<div class="kpi-icon"><span class="dashicons dashicons-clock"></span></div>
					<div class="kpi-content">
						<div class="kpi-value"><?php echo esc_html( $avg_response_time_s ); ?></div>
						<div class="kpi-label"><?php esc_html_e( 'Avg. Response Time', 'promptor' ); ?></div>
					</div>
				</div>
				<div class="promptor-kpi-card kpi-docs">
					<div class="kpi-icon"><span class="dashicons dashicons-admin-page"></span></div>
					<div class="kpi-content">
						<div class="kpi-value"><?php echo esc_html( number_format_i18n( $crawled_pages ) ); ?></div>
						<div class="kpi-label"><?php esc_html_e( 'Indexed Docs', 'promptor' ); ?></div>
					</div>
				</div>
				<div class="promptor-kpi-card kpi-woo">
					<div class="kpi-icon"><span class="dashicons dashicons-products"></span></div>
					<div class="kpi-content">
						<div class="kpi-value"><?php echo esc_html( number_format_i18n( $woo_products ) ); ?></div>
						<div class="kpi-label"><?php esc_html_e( 'WooCommerce Products', 'promptor' ); ?></div>
					</div>
				</div>
			</div>

			<div id="dashboard-widgets-wrap">
				<div class="promptor-dashboard-charts-grid">
					<div class="postbox chart-box">
						<h2 class="hndle">
							<span>
								<span class="dashicons dashicons-chart-line"></span>
								<?php esc_html_e( 'Daily Performance', 'promptor' ); ?>
							</span>
						</h2>
						<div class="inside">
							<div class="promptor-chart-container">
								<canvas id="promptorActivityChart"></canvas>
							</div>
						</div>
					</div>
					<div class="postbox chart-box">
						<h2 class="hndle">
							<span>
								<span class="dashicons dashicons-chart-pie"></span>
								<?php esc_html_e( 'Lead Pipeline Distribution', 'promptor' ); ?>
							</span>
						</h2>
						<div class="inside">
							<div class="promptor-chart-container">
								<canvas id="promptorLeadPipelineChart"></canvas>
							</div>
						</div>
					</div>
				</div>

				<div class="promptor-dashboard-lists-grid promptor-mt-20">
					<div class="postbox">
						<h2 class="hndle">
							<span>
								<span class="dashicons dashicons-performance"></span>
								<?php esc_html_e( 'Top Converting Services (Manual Leads)', 'promptor' ); ?>
							</span>
						</h2>
						<div class="inside">
							<?php if ( count( $service_totals ) > 0 ) : ?>
								<ol class="performing-list">
									<?php foreach ( $service_totals as $service => $count ) : ?>
										<li>
											<?php echo esc_html( $service ); ?>
											<span>
												(<?php echo esc_html( $count ); ?>
												<?php esc_html_e( 'conversions', 'promptor' ); ?>)
											</span>
										</li>
									<?php endforeach; ?>
								</ol>
							<?php else : ?>
								<p><?php esc_html_e( 'No service conversions recorded yet.', 'promptor' ); ?></p>
							<?php endif; ?>
						</div>
					</div>

					<?php if ( class_exists( 'WooCommerce' ) ) : ?>
					<div class="postbox">
						<h2 class="hndle">
							<span>
								<span class="dashicons dashicons-products"></span>
								<?php esc_html_e( 'Top Selling Products via AI', 'promptor' ); ?>
							</span>
						</h2>
						<div class="inside">
							<?php if ( count( $top_selling_products ) > 0 ) : ?>
								<ol class="performing-list">
									<?php
									foreach ( $top_selling_products as $item ) :
										$pid          = absint( $item->product_id );
										$raw_name     = ! empty( $item->product_name ) ? $item->product_name : get_the_title( $pid );
										$product_name = is_string( $raw_name ) ? $raw_name : '';
										?>
										<li>
											<a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>" 
											   target="_blank" 
											   rel="noopener noreferrer">
												<?php echo esc_html( $product_name ); ?>
											</a>
											<span>
												<?php echo esc_html( (string) $item->sales_count ); ?>
												<?php esc_html_e( 'sales', 'promptor' ); ?>
												(<?php echo wp_kses_post( wc_price( (float) $item->total_revenue ) ); ?>)
											</span>
										</li>
									<?php endforeach; ?>
								</ol>
							<?php else : ?>
								<p><?php esc_html_e( 'No AI-driven sales recorded in this period yet.', 'promptor' ); ?></p>
							<?php endif; ?>
						</div>
					</div>
					<?php endif; ?>

					<div class="postbox">
						<h2 class="hndle">
							<span>
								<span class="dashicons dashicons-star-filled"></span>
								<?php esc_html_e( 'Recent Submissions', 'promptor' ); ?>
							</span>
						</h2>
						<div class="inside no-padding">
							<table class="wp-list-table widefat striped">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Name', 'promptor' ); ?></th>
										<th><?php esc_html_e( 'Status', 'promptor' ); ?></th>
										<th><?php esc_html_e( 'Date', 'promptor' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php if ( count( $recent_submissions ) > 0 ) : ?>
										<?php foreach ( $recent_submissions as $submission ) : ?>
											<tr>
												<td><strong><?php echo esc_html( $submission['name'] ); ?></strong></td>
												<td>
													<span class="promptor-status-badge status-is-<?php echo esc_attr( $submission['status'] ); ?>">
														<?php echo esc_html( $this->get_status_label( $submission['status'] ) ); ?>
													</span>
												</td>
												<td>
													<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $submission['submitted_at'] ) ) ); ?>
												</td>
											</tr>
										<?php endforeach; ?>
									<?php else : ?>
										<tr>
											<td colspan="3"><?php esc_html_e( 'No submissions yet.', 'promptor' ); ?></td>
										</tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
						<div class="view-all-link">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=promptor-submissions' ) ); ?>" 
							   class="button button-secondary">
								<?php esc_html_e( 'View All Submissions', 'promptor' ); ?>
							</a>
						</div>
					</div>

					<div class="postbox">
						<h2 class="hndle">
							<span>
								<span class="dashicons dashicons-format-chat"></span>
								<?php esc_html_e( 'Recent Queries', 'promptor' ); ?>
							</span>
						</h2>
						<div class="inside no-padding">
							<table class="wp-list-table widefat striped">
								<thead>
									<tr>
										<th><?php esc_html_e( 'User Query', 'promptor' ); ?></th>
										<th><?php esc_html_e( 'Date', 'promptor' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php if ( count( $recent_queries ) > 0 ) : ?>
										<?php foreach ( $recent_queries as $query ) : ?>
											<tr>
												<td><?php echo esc_html( wp_trim_words( $query['user_query'], 10, '...' ) ); ?></td>
												<td>
													<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $query['query_timestamp'] ) ) ); ?>
												</td>
											</tr>
										<?php endforeach; ?>
									<?php else : ?>
										<tr>
											<td colspan="2"><?php esc_html_e( 'No queries yet.', 'promptor' ); ?></td>
										</tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
						<div class="view-all-link">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=promptor-queries' ) ); ?>" 
							   class="button button-secondary">
								<?php esc_html_e( 'View All Queries', 'promptor' ); ?>
							</a>
						</div>
					</div>
				</div>
			</div>

			<div class="promptor-system-status-bar">
				<span class="status-bar-title"><?php esc_html_e( 'System Status:', 'promptor' ); ?></span>
				<span class="status-item <?php echo esc_attr( $system_status['ai_assistant'] ? 'active' : 'inactive' ); ?>">
					<span class="dashicons <?php echo esc_attr( $system_status['ai_assistant'] ? 'dashicons-yes-alt' : 'dashicons-no-alt' ); ?>"></span>
					<?php esc_html_e( 'AI Assistant', 'promptor' ); ?>
				</span>
				<span class="status-item <?php echo esc_attr( $system_status['woocommerce'] ? 'active' : 'inactive' ); ?>">
					<span class="dashicons <?php echo esc_attr( $system_status['woocommerce'] ? 'dashicons-yes-alt' : 'dashicons-no-alt' ); ?>"></span>
					<?php esc_html_e( 'WooCommerce', 'promptor' ); ?>
				</span>
				<span class="status-item <?php echo esc_attr( $system_status['knowledge_base'] ? 'active' : 'inactive' ); ?>">
					<span class="dashicons <?php echo esc_attr( $system_status['knowledge_base'] ? 'dashicons-yes-alt' : 'dashicons-no-alt' ); ?>"></span>
					<?php esc_html_e( 'Knowledge Base', 'promptor' ); ?>
				</span>
				<span class="status-item <?php echo esc_attr( $system_status['analytics'] ? 'active' : 'inactive' ); ?>">
					<span class="dashicons <?php echo esc_attr( $system_status['analytics'] ? 'dashicons-yes-alt' : 'dashicons-no-alt' ); ?>"></span>
					<?php esc_html_e( 'Analytics Live', 'promptor' ); ?>
				</span>
			</div>
		</div>
		<?php
	}

	/**
	 * Get human-readable status label
	 *
	 * @param string $status Status key.
	 * @return string Translated status label.
	 */
	private function get_status_label( $status ) {
		$labels = array(
			'pending'   => __( 'Pending', 'promptor' ),
			'contacted' => __( 'Contacted', 'promptor' ),
			'converted' => __( 'Converted', 'promptor' ),
			'rejected'  => __( 'Rejected', 'promptor' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( $status );
	}

	/**
	 * Fetch dashboard data for given period
	 *
	 * @param string $period Period key (daily, weekly, monthly, yearly).
	 * @return array Dashboard data array.
	 */
	private function fetch_dashboard_data( $period ) {
		global $wpdb;

		// Prepare table names - these will be used in queries with proper escaping.
		$queries_table     = $wpdb->prefix . 'promptor_queries';
		$submissions_table = $wpdb->prefix . 'promptor_submissions';
		$embeddings_table  = $wpdb->prefix . 'promptor_embeddings';

		$period_map = array(
			'daily'   => array(
				'interval' => '0 days',
				'label'    => __( '(Today)', 'promptor' ),
			),
			'weekly'  => array(
				'interval' => '6 days',
				'label'    => __( '(Last 7 Days)', 'promptor' ),
			),
			'monthly' => array(
				'interval' => '29 days',
				'label'    => __( '(Last 30 Days)', 'promptor' ),
			),
			'yearly'  => array(
				'interval' => '364 days',
				'label'    => __( '(Last Year)', 'promptor' ),
			),
		);

		$current_period = $period_map[ $period ];
		$start_date_str = gmdate( 'Y-m-d 00:00:00', strtotime( '-' . $current_period['interval'] ) );
		$cache_suffix   = $period . '_' . md5( $start_date_str );

		// Total Queries.
		$total_queries = wp_cache_get( "dashboard_total_queries_$cache_suffix", 'promptor' );
		if ( false === $total_queries ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$sql           = "SELECT COUNT(*) FROM `{$queries_table}` WHERE query_timestamp >= %s";
			$total_queries = (int) $wpdb->get_var( $wpdb->prepare( $sql, $start_date_str ) );
			// phpcs:enable
			wp_cache_set( "dashboard_total_queries_$cache_suffix", $total_queries, 'promptor', 5 * MINUTE_IN_SECONDS );
		}

		// Total Submissions.
		$total_submissions = wp_cache_get( "dashboard_total_submissions_$cache_suffix", 'promptor' );
		if ( false === $total_submissions ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$sql               = "SELECT COUNT(*) FROM `{$submissions_table}` WHERE submitted_at >= %s";
			$total_submissions = (int) $wpdb->get_var( $wpdb->prepare( $sql, $start_date_str ) );
			// phpcs:enable
			wp_cache_set( "dashboard_total_submissions_$cache_suffix", $total_submissions, 'promptor', 5 * MINUTE_IN_SECONDS );
		}

		$conversion_rate = ( $total_queries > 0 ) ? ( $total_submissions / $total_queries ) * 100 : 0;

		// Satisfaction Rate.
		$satisfaction_rate = wp_cache_get( "dashboard_satisfaction_rate_$cache_suffix", 'promptor' );
		if ( false === $satisfaction_rate ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$sql               = "SELECT AVG(CASE WHEN feedback = 1 THEN 100 WHEN feedback = -1 THEN 0 END) 
					FROM `{$queries_table}` 
					WHERE query_timestamp >= %s AND feedback IS NOT NULL";
			$satisfaction_rate = (float) $wpdb->get_var( $wpdb->prepare( $sql, $start_date_str ) );
			// phpcs:enable
			wp_cache_set( "dashboard_satisfaction_rate_$cache_suffix", $satisfaction_rate, 'promptor', 5 * MINUTE_IN_SECONDS );
		}

		// Average Response Time.
		$avg_response_time_ms = wp_cache_get( "dashboard_avg_response_time_ms_$cache_suffix", 'promptor' );
		if ( false === $avg_response_time_ms ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$sql                  = "SELECT AVG(response_time_ms) 
					FROM `{$queries_table}` 
					WHERE response_time_ms IS NOT NULL AND query_timestamp >= %s";
			$avg_response_time_ms = (int) $wpdb->get_var( $wpdb->prepare( $sql, $start_date_str ) );
			// phpcs:enable
			wp_cache_set( "dashboard_avg_response_time_ms_$cache_suffix", $avg_response_time_ms, 'promptor', 5 * MINUTE_IN_SECONDS );
		}

		// Revenue from Submissions.
		$revenue_from_submissions = wp_cache_get( "dashboard_revenue_from_submissions_$cache_suffix", 'promptor' );
		if ( false === $revenue_from_submissions ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$sql                      = "SELECT SUM(order_value) 
					FROM `{$submissions_table}` 
					WHERE status = 'converted' AND order_value IS NOT NULL AND submitted_at >= %s";
			$revenue_from_submissions = (float) $wpdb->get_var( $wpdb->prepare( $sql, $start_date_str ) );
			// phpcs:enable
			wp_cache_set( "dashboard_revenue_from_submissions_$cache_suffix", $revenue_from_submissions, 'promptor', 5 * MINUTE_IN_SECONDS );
		}

		// Revenue from Queries.
		$revenue_from_queries = wp_cache_get( "dashboard_revenue_from_queries_$cache_suffix", 'promptor' );
		if ( false === $revenue_from_queries ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$sql                  = "SELECT SUM(order_value) 
					FROM `{$queries_table}` 
					WHERE linked_order_id > 0 AND order_value IS NOT NULL AND query_timestamp >= %s";
			$revenue_from_queries = (float) $wpdb->get_var( $wpdb->prepare( $sql, $start_date_str ) );
			// phpcs:enable
			wp_cache_set( "dashboard_revenue_from_queries_$cache_suffix", $revenue_from_queries, 'promptor', 5 * MINUTE_IN_SECONDS );
		}

		$total_revenue = $revenue_from_submissions + $revenue_from_queries;

		// Products in Cart.
		$products_in_cart_raw = wp_cache_get( "dashboard_products_in_cart_raw_$cache_suffix", 'promptor' );
		if ( false === $products_in_cart_raw ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$sql                  = "SELECT products_added_to_cart 
					FROM `{$queries_table}` 
					WHERE products_added_to_cart IS NOT NULL 
					AND products_added_to_cart != '' 
					AND query_timestamp >= %s";
			$products_in_cart_raw = $wpdb->get_col( $wpdb->prepare( $sql, $start_date_str ) );
			// phpcs:enable
			wp_cache_set( "dashboard_products_in_cart_raw_$cache_suffix", $products_in_cart_raw, 'promptor', 5 * MINUTE_IN_SECONDS );
		}

		$total_products_in_cart = 0;
		foreach ( $products_in_cart_raw as $csv ) {
			$total_products_in_cart += count( array_filter( explode( ',', $csv ) ) );
		}

		// Crawled Pages.
		$crawled_pages = wp_cache_get( "dashboard_crawled_pages_$cache_suffix", 'promptor' );
		if ( false === $crawled_pages ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$sql           = "SELECT COUNT(DISTINCT post_id) FROM `{$embeddings_table}`";
			$crawled_pages = (int) $wpdb->get_var( $sql );
			// phpcs:enable
			wp_cache_set( "dashboard_crawled_pages_$cache_suffix", $crawled_pages, 'promptor', 5 * MINUTE_IN_SECONDS );
		}

		// WooCommerce Products.
		$woo_products = wp_cache_get( "dashboard_woo_products_$cache_suffix", 'promptor' );
		if ( false === $woo_products ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$woo_products = (int) $wpdb->get_var(
				"SELECT COUNT(*) 
				FROM `{$wpdb->posts}` 
				WHERE post_type = 'product' AND post_status = 'publish'"
			);
			// phpcs:enable
			wp_cache_set( "dashboard_woo_products_$cache_suffix", $woo_products, 'promptor', 5 * MINUTE_IN_SECONDS );
		}

		// Chart Data: Query counts by day.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$sql                 = "SELECT DATE(query_timestamp) as date, COUNT(*) as count 
				FROM `{$queries_table}` 
				WHERE query_timestamp >= %s 
				GROUP BY DATE(query_timestamp) 
				ORDER BY date ASC";
		$query_counts_by_day = $wpdb->get_results( $wpdb->prepare( $sql, $start_date_str ), OBJECT_K );

		// Chart Data: Submission counts by day.
		$sql                      = "SELECT DATE(submitted_at) as date, COUNT(*) as count 
				FROM `{$submissions_table}` 
				WHERE submitted_at >= %s 
				GROUP BY DATE(submitted_at) 
				ORDER BY date ASC";
		$submission_counts_by_day = $wpdb->get_results( $wpdb->prepare( $sql, $start_date_str ), OBJECT_K );

		// Chart Data: Revenue by day from submissions.
		$sql              = "SELECT DATE(submitted_at) as date, SUM(order_value) as total 
				FROM `{$submissions_table}` 
				WHERE status = 'converted' AND order_value IS NOT NULL AND submitted_at >= %s 
				GROUP BY DATE(submitted_at) 
				ORDER BY date ASC";
		$revenue_by_day_s = $wpdb->get_results( $wpdb->prepare( $sql, $start_date_str ), OBJECT_K );

		// Chart Data: Revenue by day from queries.
		$sql              = "SELECT DATE(query_timestamp) as date, SUM(order_value) as total 
				FROM `{$queries_table}` 
				WHERE linked_order_id > 0 AND order_value IS NOT NULL AND query_timestamp >= %s 
				GROUP BY DATE(query_timestamp) 
				ORDER BY date ASC";
		$revenue_by_day_q = $wpdb->get_results( $wpdb->prepare( $sql, $start_date_str ), OBJECT_K );

		// Chart Data: Satisfaction rate by day.
		$sql                 = "SELECT DATE(query_timestamp) as date,
				AVG(CASE WHEN feedback = 1 THEN 100 WHEN feedback = -1 THEN 0 END) as satisfaction
				FROM `{$queries_table}`
				WHERE feedback IS NOT NULL AND query_timestamp >= %s
				GROUP BY DATE(query_timestamp)
				ORDER BY date ASC";
		$satisfaction_by_day = $wpdb->get_results( $wpdb->prepare( $sql, $start_date_str ), OBJECT_K );
		// phpcs:enable

		// Build chart arrays.
		$chart_labels            = array();
		$chart_queries_data      = array();
		$chart_submissions_data  = array();
		$chart_revenue_data      = array();
		$chart_satisfaction_data = array();

		$end_date     = new DateTime();
		$current_date = new DateTime( $start_date_str );

		while ( $current_date <= $end_date ) {
			$day_str = $current_date->format( 'Y-m-d' );

			$chart_labels[]           = date_i18n( 'M j', $current_date->getTimestamp() );
			$chart_queries_data[]     = isset( $query_counts_by_day[ $day_str ] ) ? $query_counts_by_day[ $day_str ]->count : 0;
			$chart_submissions_data[] = isset( $submission_counts_by_day[ $day_str ] ) ? $submission_counts_by_day[ $day_str ]->count : 0;

			$daily_revenue        = 0;
			$daily_revenue       += isset( $revenue_by_day_s[ $day_str ] ) ? (float) $revenue_by_day_s[ $day_str ]->total : 0;
			$daily_revenue       += isset( $revenue_by_day_q[ $day_str ] ) ? (float) $revenue_by_day_q[ $day_str ]->total : 0;
			$chart_revenue_data[] = $daily_revenue;

			$daily_satisfaction        = isset( $satisfaction_by_day[ $day_str ] ) ? (float) $satisfaction_by_day[ $day_str ]->satisfaction : null;
			$chart_satisfaction_data[] = $daily_satisfaction;

			$current_date->add( new DateInterval( 'P1D' ) );
		}

		// Lead Pipeline Data.
		$lead_pipeline_data = wp_cache_get( "dashboard_lead_pipeline_data_$cache_suffix", 'promptor' );
		if ( false === $lead_pipeline_data ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$sql                = "SELECT status, COUNT(*) as count FROM `{$submissions_table}` GROUP BY status";
			$lead_pipeline_data = $wpdb->get_results( $sql, OBJECT_K );
			// phpcs:enable
			wp_cache_set( "dashboard_lead_pipeline_data_$cache_suffix", $lead_pipeline_data, 'promptor', 5 * MINUTE_IN_SECONDS );
		}

		// Top Service Conversions.
		$top_service_conversions_raw = wp_cache_get( "dashboard_top_service_conversions_raw_$cache_suffix", 'promptor' );
		if ( false === $top_service_conversions_raw ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$sql                         = "SELECT s.recommendations, q.suggested_service_titles
					FROM `{$submissions_table}` s
					LEFT JOIN `{$queries_table}` q ON s.query_id = q.id
					WHERE s.status = 'converted' AND s.submitted_at >= %s";
			$top_service_conversions_raw = $wpdb->get_results( $wpdb->prepare( $sql, $start_date_str ) );
			// phpcs:enable
			wp_cache_set( "dashboard_top_service_conversions_raw_$cache_suffix", $top_service_conversions_raw, 'promptor', 5 * MINUTE_IN_SECONDS );
		}

		$service_totals = array();
		if ( count( $top_service_conversions_raw ) > 0 ) {
			foreach ( $top_service_conversions_raw as $item ) {
				$services_str = '';

				if ( ! empty( $item->recommendations ) && false !== strpos( $item->recommendations, 'Services:' ) ) {
					if ( preg_match( '/Services:(.*?)(Products:|$)/s', $item->recommendations, $matches ) && isset( $matches[1] ) ) {
						$services_str = trim( $matches[1] );
					}
				} elseif ( ! empty( $item->suggested_service_titles ) ) {
					$services_str = $item->suggested_service_titles;
				}

				if ( ! empty( $services_str ) ) {
					$services = array_map( 'trim', explode( ',', $services_str ) );
					foreach ( $services as $srv ) {
						if ( empty( $srv ) ) {
							continue;
						}
						if ( ! isset( $service_totals[ $srv ] ) ) {
							$service_totals[ $srv ] = 0;
						}
						$service_totals[ $srv ]++;
					}
				}
			}
		}

		arsort( $service_totals );
		$service_totals = array_slice( $service_totals, 0, 5, true );

		// Top Selling Products.
		$top_selling_products = array();
		if ( class_exists( 'WooCommerce' ) ) {
			$top_selling_products = wp_cache_get( "dashboard_top_selling_products_$cache_suffix", 'promptor' );
			if ( false === $top_selling_products ) {
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$sql                  = "SELECT 
							woim_product.meta_value as product_id,
							woi.order_item_name as product_name,
							COUNT(q.id) as sales_count,
							SUM(woim_total.meta_value) as total_revenue
						FROM `{$queries_table}` q
						JOIN `{$wpdb->prefix}woocommerce_order_items` woi ON q.linked_order_id = woi.order_id
						JOIN `{$wpdb->prefix}woocommerce_order_itemmeta` woim_product 
							ON woi.order_item_id = woim_product.order_item_id AND woim_product.meta_key = '_product_id'
						JOIN `{$wpdb->prefix}woocommerce_order_itemmeta` woim_total 
							ON woi.order_item_id = woim_total.order_item_id AND woim_total.meta_key = '_line_total'
						WHERE q.linked_order_id > 0 
							AND woi.order_item_type = 'line_item' 
							AND q.query_timestamp >= %s
						GROUP BY woim_product.meta_value 
						ORDER BY sales_count DESC, total_revenue DESC 
						LIMIT 5";
				$top_selling_products = $wpdb->get_results( $wpdb->prepare( $sql, $start_date_str ) );
				// phpcs:enable
				wp_cache_set( "dashboard_top_selling_products_$cache_suffix", $top_selling_products, 'promptor', 5 * MINUTE_IN_SECONDS );
			}
		}

		// Recent Submissions.
		$recent_submissions = wp_cache_get( "dashboard_recent_submissions_$cache_suffix", 'promptor' );
		if ( false === $recent_submissions ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$sql                = "SELECT name, status, submitted_at 
					FROM `{$submissions_table}` 
					WHERE submitted_at >= %s 
					ORDER BY submitted_at DESC 
					LIMIT 5";
			$recent_submissions = $wpdb->get_results( $wpdb->prepare( $sql, $start_date_str ), ARRAY_A );
			// phpcs:enable
			wp_cache_set( "dashboard_recent_submissions_$cache_suffix", $recent_submissions, 'promptor', 5 * MINUTE_IN_SECONDS );
		}

		// Recent Queries.
		$recent_queries = wp_cache_get( "dashboard_recent_queries_$cache_suffix", 'promptor' );
		if ( false === $recent_queries ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$sql            = "SELECT user_query, query_timestamp 
					FROM `{$queries_table}` 
					WHERE query_timestamp >= %s 
					ORDER BY query_timestamp DESC 
					LIMIT 5";
			$recent_queries = $wpdb->get_results( $wpdb->prepare( $sql, $start_date_str ), ARRAY_A );
			// phpcs:enable
			wp_cache_set( "dashboard_recent_queries_$cache_suffix", $recent_queries, 'promptor', 5 * MINUTE_IN_SECONDS );
		}

		// System Status.
		$api_settings  = get_option( 'promptor_api_settings' );
		$system_status = array(
			'ai_assistant'   => ! empty( $api_settings['api_key'] ),
			'woocommerce'    => class_exists( 'WooCommerce' ),
			'knowledge_base' => false,
			'analytics'      => true,
		);

		$kb_status = wp_cache_get( "dashboard_knowledge_base_$cache_suffix", 'promptor' );
		if ( false === $kb_status ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$sql       = "SELECT COUNT(*) FROM `{$embeddings_table}`";
			$kb_status = (int) $wpdb->get_var( $sql ) > 0;
			// phpcs:enable
			wp_cache_set( "dashboard_knowledge_base_$cache_suffix", $kb_status, 'promptor', 5 * MINUTE_IN_SECONDS );
		}
		$system_status['knowledge_base'] = $kb_status;

		return compact(
			'current_period',
			'total_queries',
			'total_submissions',
			'conversion_rate',
			'satisfaction_rate',
			'avg_response_time_ms',
			'total_revenue',
			'total_products_in_cart',
			'crawled_pages',
			'woo_products',
			'chart_labels',
			'chart_queries_data',
			'chart_submissions_data',
			'chart_revenue_data',
			'lead_pipeline_data',
			'service_totals',
			'top_selling_products',
			'recent_submissions',
			'recent_queries',
			'system_status'
		);
	}
}