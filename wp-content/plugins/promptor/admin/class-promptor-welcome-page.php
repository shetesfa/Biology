<?php
/**
 * The welcome page for the Promptor plugin.
 * Command Center layout with WordPress-native admin cards.
 *
 * @link       https://promptorai.com
 * @since      1.0.0
 *
 * @package    Promptor
 * @subpackage Promptor/admin
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Promptor_Welcome_Page {

	public function __construct() {}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'promptor' ) );
		}

		$is_pro        = function_exists( 'promptor_is_pro' ) && promptor_is_pro();
		$changelog_url = admin_url( 'admin.php?page=promptor-changelog' );

		// Direct links to settings tabs (tab navigation does not require nonces).
		$api_settings_url = admin_url( 'admin.php?page=promptor-settings&tab=api_settings' );
		$kb_settings_url  = admin_url( 'admin.php?page=promptor-settings&tab=knowledge_bases' );
		$ui_settings_url  = admin_url( 'admin.php?page=promptor-settings&tab=ui_settings' );

		// Load onboarding helper for step tracking (v1.2.1).
		require_once PROMPTOR_PATH . 'admin/class-promptor-onboarding.php';

		// Check onboarding steps completion
		$step_1_completed = Promptor_Onboarding::is_step_1_completed();
		$step_2_completed = Promptor_Onboarding::is_step_2_completed();
		$step_3_completed = Promptor_Onboarding::is_step_3_completed();
		$completed_count  = Promptor_Onboarding::get_completed_steps_count();
		$setup_completed  = Promptor_Onboarding::is_setup_completed();

		// Check if onboarding is dismissed by user
		$onboarding_dismissed = get_user_meta( get_current_user_id(), 'promptor_onboarding_dismissed', true );
		?>
		<div class="wrap promptor-wrap">
			<!-- Header with Logo and Welcome Text -->
			<div class="promptor-welcome-header">
				<div class="header-logo">
					<img src="<?php echo esc_url( PROMPTOR_URL . 'admin/assets/images/promptor-logo-welcome.png' ); ?>" alt="<?php esc_attr_e( 'Promptor Logo', 'promptor' ); ?>">
				</div>
				<div class="header-content">
					<h1><?php esc_html_e( 'Welcome to Promptor', 'promptor' ); ?></h1>
					<p class="about-text"><?php esc_html_e( 'Your AI-powered command center for turning website content into intelligent conversations that capture leads.', 'promptor' ); ?></p>
				</div>
			</div>

			<!-- License Status Card -->
			<div class="promptor-status-card">
				<?php if ( $is_pro && function_exists( 'promptor_fs' ) && promptor_fs()->is_registered() && promptor_fs()->is_premium() ) : ?>
					<?php
						/* translators: %s: Freemius plan title */
						$plan_msg   = __( 'You are using the %s plan. Thank you!', 'promptor' );
						$plan_obj   = ( function_exists( 'promptor_fs' ) && method_exists( promptor_fs(), 'get_plan' ) ) ? promptor_fs()->get_plan() : null;
						$plan_title = ( is_object( $plan_obj ) && isset( $plan_obj->title ) ) ? (string) $plan_obj->title : __( 'Pro', 'promptor' );
					?>
					<div class="status-info">
						<span class="dashicons dashicons-shield-alt"></span>
						<span class="status-text">
							<?php
								echo wp_kses_post(
									sprintf(
										$plan_msg,
										'<strong>' . esc_html( $plan_title ) . '</strong>'
									)
								);
							?>
						</span>
					</div>
					<div class="status-actions">
						<a href="<?php echo esc_url( promptor_fs()->get_account_url() ); ?>" class="button button-secondary"><?php esc_html_e( 'Manage Account', 'promptor' ); ?></a>
					</div>
				<?php else : ?>
					<div class="status-info">
						<span class="dashicons dashicons-shield-alt"></span>
						<span class="status-text">
							<strong><?php esc_html_e( 'Promptor Lite', 'promptor' ); ?></strong> &mdash;
							<span><?php esc_html_e( 'Free version active', 'promptor' ); ?></span>
						</span>
					</div>
					<?php if ( function_exists( 'promptor_fs' ) ) : ?>
						<div class="status-actions">
							<a href="<?php echo esc_url( promptor_fs()->get_upgrade_url() ); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade to Pro', 'promptor' ); ?></a>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>

			<?php $this->render_trial_status_card(); ?>

			<!-- Setup Progress Section (v1.2.1) -->
			<?php if ( ! $onboarding_dismissed ) : ?>
				<div class="promptor-onboarding-section" id="promptor-onboarding-section">
					<div class="promptor-progress-header">
						<div class="promptor-progress-header-left">
							<h2><?php esc_html_e( 'Setup Progress', 'promptor' ); ?></h2>
							<div class="promptor-progress-indicator">
								<?php
								/* translators: %1$d: completed steps count, %2$d: total steps count */
								printf( esc_html__( '%1$d / %2$d completed', 'promptor' ), (int) $completed_count, 3 );
								?>
							</div>
						</div>
						<div class="promptor-progress-header-right">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=promptor-setup-wizard' ) ); ?>" class="button button-secondary">
								<span class="dashicons dashicons-admin-generic"></span>
								<?php esc_html_e( 'Setup Wizard', 'promptor' ); ?>
							</a>
							<?php if ( $setup_completed ) : ?>
								<button type="button" class="promptor-dismiss-onboarding button button-link" id="promptor-dismiss-onboarding">
									<span class="dashicons dashicons-dismiss"></span>
									<?php esc_html_e( 'Dismiss', 'promptor' ); ?>
								</button>
							<?php endif; ?>
						</div>
					</div>

					<div class="promptor-onboarding-steps-grid">
					<!-- Step 1: Connect OpenAI -->
					<div class="promptor-step-card <?php echo esc_attr( $step_1_completed ? 'completed' : '' ); ?>">
						<div class="promptor-step-card-icon">
							<?php if ( $step_1_completed ) : ?>
								<span class="dashicons dashicons-yes"></span>
							<?php else : ?>
								<span class="promptor-step-number">1</span>
							<?php endif; ?>
						</div>
						<h3><?php esc_html_e( 'Connect AI', 'promptor' ); ?></h3>
						<p><?php esc_html_e( 'Enter your API key or start a free trial', 'promptor' ); ?></p>
						<?php if ( ! $step_1_completed ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=promptor-setup-wizard' ) ); ?>" class="button button-primary button-small"><?php esc_html_e( 'Configure', 'promptor' ); ?></a>
						<?php else : ?>
							<span class="promptor-step-complete-badge"><?php esc_html_e( 'Completed', 'promptor' ); ?></span>
						<?php endif; ?>
					</div>

					<!-- Step 2: Choose Knowledge Base -->
					<div class="promptor-step-card <?php echo esc_attr( $step_2_completed ? 'completed' : '' ); ?>">
						<div class="promptor-step-card-icon">
							<?php if ( $step_2_completed ) : ?>
								<span class="dashicons dashicons-yes"></span>
							<?php else : ?>
								<span class="promptor-step-number">2</span>
							<?php endif; ?>
						</div>
						<h3><?php esc_html_e( 'Choose Knowledge Base', 'promptor' ); ?></h3>
						<p><?php esc_html_e( 'Select pages and posts to train your AI assistant', 'promptor' ); ?></p>
						<?php if ( ! $step_2_completed ) : ?>
							<a href="<?php echo esc_url( $kb_settings_url ); ?>" class="button button-primary button-small"><?php esc_html_e( 'Manage KB', 'promptor' ); ?></a>
						<?php else : ?>
							<span class="promptor-step-complete-badge"><?php esc_html_e( 'Completed', 'promptor' ); ?></span>
						<?php endif; ?>
					</div>

					<!-- Step 3: Embed the Widget -->
					<div class="promptor-step-card <?php echo esc_attr( $step_3_completed ? 'completed' : '' ); ?>">
						<div class="promptor-step-card-icon">
							<?php if ( $step_3_completed ) : ?>
								<span class="dashicons dashicons-yes"></span>
							<?php else : ?>
								<span class="promptor-step-number">3</span>
							<?php endif; ?>
						</div>
						<h3><?php esc_html_e( 'Embed the Widget', 'promptor' ); ?></h3>
						<p>
							<?php
							/* translators: %s: shortcode */
							echo wp_kses_post( sprintf( __( 'Use %s shortcode to add chat to your pages', 'promptor' ), '<code>[promptor_search]</code>' ) );
							?>
							</p>
						<?php if ( ! $step_3_completed ) : ?>
							<a href="<?php echo esc_url( $ui_settings_url ); ?>" class="button button-primary button-small"><?php esc_html_e( 'View Options', 'promptor' ); ?></a>
						<?php else : ?>
							<span class="promptor-step-complete-badge"><?php esc_html_e( 'Completed', 'promptor' ); ?></span>
						<?php endif; ?>
					</div>
				</div>

				<?php if ( $setup_completed ) : ?>
					<div class="promptor-setup-complete-message">
						<span class="dashicons dashicons-yes-alt"></span>
						<strong><?php esc_html_e( 'Setup Complete!', 'promptor' ); ?></strong>
						<span><?php esc_html_e( 'Your AI assistant is ready to engage with visitors!', 'promptor' ); ?></span>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

			<!-- Key Features Section - Show ALL features with PRO badges where applicable -->
			<h2><?php esc_html_e( 'Key Features', 'promptor' ); ?></h2>
			<div class="promptor-feature-highlights">
				<div class="promptor-feature-card">
					<div class="feature-icon-wrapper"><span class="dashicons dashicons-analytics"></span></div>
					<h3><?php esc_html_e( 'Semantic AI Search', 'promptor' ); ?></h3>
					<p><?php esc_html_e( 'Understands user intent and finds the most relevant content to provide accurate, human-like answers.', 'promptor' ); ?></p>
				</div>
				<div class="promptor-feature-card">
					<div class="feature-icon-wrapper"><span class="dashicons dashicons-tag"></span></div>
					<h3><?php esc_html_e( 'Knowledge Base', 'promptor' ); ?></h3>
					<p><?php esc_html_e( 'Tag any page or post as Service, Blog, or FAQ within each knowledge base for ultimate flexibility.', 'promptor' ); ?></p>
				</div>
				<div class="promptor-feature-card">
					<div class="feature-icon-wrapper"><span class="dashicons dashicons-id-alt"></span></div>
					<h3><?php esc_html_e( 'Lead Capture', 'promptor' ); ?></h3>
					<p><?php esc_html_e( 'Capture inquiries from the chat and manage them with statuses like pending or converted.', 'promptor' ); ?></p>
				</div>
				<div class="promptor-feature-card">
					<div class="feature-icon-wrapper"><span class="dashicons dashicons-admin-customizer"></span></div>
					<h3><?php esc_html_e( 'Full UI Customization', 'promptor' ); ?></h3>
					<p><?php esc_html_e( 'Match the chat widget to your brand with colors, texts, avatars, and position settings.', 'promptor' ); ?></p>
				</div>
				<div class="promptor-feature-card">
					<div class="feature-icon-wrapper"><span class="dashicons dashicons-shield"></span></div>
					<h3><?php esc_html_e( 'AI Cost Guardrails', 'promptor' ); ?></h3>
					<p><?php esc_html_e( 'Control API spending with daily & monthly limits, email alerts, and automatic model fallback.', 'promptor' ); ?></p>
				</div>
				<div class="promptor-feature-card">
					<div class="feature-icon-wrapper"><span class="dashicons dashicons-universal-access-alt"></span></div>
					<h3><?php esc_html_e( 'Accessibility', 'promptor' ); ?></h3>
					<p><?php esc_html_e( 'Full keyboard navigation, ARIA labels, focus trap in popup mode, and screen reader support.', 'promptor' ); ?></p>
				</div>
				<div class="promptor-feature-card">
					<div class="feature-icon-wrapper"><span class="dashicons dashicons-cart"></span></div>
					<h3>
						<?php esc_html_e( 'WooCommerce', 'promptor' ); ?>
						<?php if ( ! $is_pro ) : ?>
							<span class="pro-badge"><?php esc_html_e( 'PRO', 'promptor' ); ?></span>
						<?php endif; ?>
					</h3>
					<p><?php esc_html_e( 'Inventory-aware AI recommendations, product comparison tables, stock badges, and cart reminders.', 'promptor' ); ?></p>
				</div>
				<div class="promptor-feature-card">
					<div class="feature-icon-wrapper"><span class="dashicons dashicons-chart-line"></span></div>
					<h3>
						<?php esc_html_e( 'Lead Scoring', 'promptor' ); ?>
						<?php if ( ! $is_pro ) : ?>
							<span class="pro-badge"><?php esc_html_e( 'PRO', 'promptor' ); ?></span>
						<?php endif; ?>
					</h3>
					<p><?php esc_html_e( 'Automatic Hot/Warm/Cold classification with detailed scoring breakdown.', 'promptor' ); ?></p>
				</div>
			</div>

			<!-- Pro Upsell (conditional based on setup completion - v1.2.1) -->
			<?php if ( ! $is_pro && function_exists( 'promptor_fs' ) ) : ?>
				<?php if ( $setup_completed ) : ?>
					<!-- Strong upsell after setup complete -->
					<div class="promptor-upgrade-cta-strong">
						<h3><?php esc_html_e( 'Ready to Unlock Pro Features?', 'promptor' ); ?></h3>
						<p><?php esc_html_e( 'Upgrade to Promptor Pro to unlock webhooks, lead scoring, and unlimited knowledge base items.', 'promptor' ); ?></p>
						<a href="<?php echo esc_url( promptor_fs()->get_upgrade_url() ); ?>" class="button button-primary button-hero" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade to Pro', 'promptor' ); ?></a>
					</div>
				<?php endif; ?>
			<?php elseif ( $is_pro && $setup_completed ) : ?>
				<div class="promptor-upgrade-cta">
					<p><?php esc_html_e( 'You have access to all Pro features. Explore the settings to customize your experience.', 'promptor' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=promptor-settings' ) ); ?>" class="button button-primary button-hero"><?php esc_html_e( 'Explore Features', 'promptor' ); ?></a>
				</div>
			<?php endif; ?>

			<!-- Core Features Section -->
			<h2><?php esc_html_e( 'Complete Feature List', 'promptor' ); ?></h2>
			<div class="promptor-features-columns">
				<!-- Free Features Column -->
				<div class="promptor-features-column">
					<h3><?php esc_html_e( 'Included in Free', 'promptor' ); ?></h3>
					<ul class="promptor-features-list">
						<li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'AI Chat Widget (customizable UI)', 'promptor' ); ?></li>
						<li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Instant AI Trial (no API key required)', 'promptor' ); ?></li>
						<li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Mini Knowledge Base (2 pages for testing)', 'promptor' ); ?></li>
						<li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Suggested Questions for instant testing', 'promptor' ); ?></li>
						<li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Lead Capture inside chat', 'promptor' ); ?></li>
						<li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'AI Cost Guardrails', 'promptor' ); ?></li>
						<li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Dark Mode (Light / Dark / Auto)', 'promptor' ); ?></li>
						<li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Accessibility support', 'promptor' ); ?></li>
					</ul>
				</div>

				<!-- Pro Features Column -->
				<div class="promptor-features-column">
					<h3><?php esc_html_e( 'Pro Features', 'promptor' ); ?></h3>
					<ul class="promptor-features-list">
						<li><span class="dashicons dashicons-star-filled"></span> <?php esc_html_e( 'Unlimited Knowledge Base', 'promptor' ); ?></li>
						<li><span class="dashicons dashicons-star-filled"></span> <?php esc_html_e( 'AI Behavior Control (Persona + Tone)', 'promptor' ); ?></li>
						<li><span class="dashicons dashicons-star-filled"></span> <?php esc_html_e( 'WooCommerce AI (recommendations, add-to-cart, inventory)', 'promptor' ); ?></li>
						<li><span class="dashicons dashicons-star-filled"></span> <?php esc_html_e( 'Lead Scoring (Hot / Warm / Cold)', 'promptor' ); ?></li>
						<li><span class="dashicons dashicons-star-filled"></span> <?php esc_html_e( 'Webhooks & integrations', 'promptor' ); ?></li>
						<li><span class="dashicons dashicons-star-filled"></span> <?php esc_html_e( 'Analytics dashboard', 'promptor' ); ?></li>
						<li><span class="dashicons dashicons-star-filled"></span> <?php esc_html_e( 'Email & Slack notifications', 'promptor' ); ?></li>
						<li><span class="dashicons dashicons-star-filled"></span> <?php esc_html_e( 'Submissions archive & filters', 'promptor' ); ?></li>
					</ul>
				</div>
			</div>

			<!-- What's New & Support Section -->
			<div class="promptor-news-grid">
				<!-- What's New Column -->
				<div class="promptor-news-column">
					<h2><?php esc_html_e( "What's New", 'promptor' ); ?></h2>
					<div class="promptor-card">
						<h4><?php esc_html_e( 'v1.4.0 — No API Key Required', 'promptor' ); ?></h4>
						<ul class="promptor-whats-new-list">
							<li>
								<strong><?php esc_html_e( 'Instant AI Trial', 'promptor' ); ?></strong>
								<span><?php esc_html_e( 'Start using Promptor without an OpenAI API key. Test with your own content instantly.', 'promptor' ); ?></span>
							</li>
							<li>
								<strong><?php esc_html_e( 'Smart Trial Experience', 'promptor' ); ?></strong>
								<span><?php esc_html_e( 'Select up to 2 pages and get real AI answers based on your site content.', 'promptor' ); ?></span>
							</li>
							<li>
								<strong><?php esc_html_e( 'AI Behavior System (Pro)', 'promptor' ); ?></strong>
								<span><?php esc_html_e( 'Control how your AI communicates with persona and tone settings.', 'promptor' ); ?></span>
							</li>
							<li>
								<strong><?php esc_html_e( 'Faster Onboarding', 'promptor' ); ?></strong>
								<span><?php esc_html_e( 'Go from install to first answer in minutes.', 'promptor' ); ?></span>
							</li>
						</ul>
						<p>
							<a href="<?php echo esc_url( $changelog_url ); ?>" class="button button-secondary"><?php esc_html_e( 'View Full Changelog', 'promptor' ); ?></a>
						</p>
					</div>
				</div>

				<!-- Support & Resources Column -->
				<div class="promptor-news-column">
					<h2><?php esc_html_e( 'Support & Resources', 'promptor' ); ?></h2>
					<div class="promptor-card">
						<h4><?php esc_html_e( 'Need Help?', 'promptor' ); ?></h4>
						<p><?php esc_html_e( 'Visit our official website for documentation, support, and the latest updates.', 'promptor' ); ?></p>
						<p class="promptor-support-buttons">
							<a href="<?php echo esc_url( 'https://docs.corrplus.net/docs/promptor/' ); ?>" class="button button-secondary" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Visit Documentation', 'promptor' ); ?></a>
							<?php if ( $is_pro ) : ?>
								<a href="<?php echo esc_url( 'https://support.corrplus.net/' ); ?>" class="button button-secondary" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get Support', 'promptor' ); ?></a>
							<?php else : ?>
								<a href="<?php echo esc_url( 'https://wordpress.org/support/plugin/promptor/' ); ?>" class="button button-secondary" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get Support', 'promptor' ); ?></a>
							<?php endif; ?>
							<a href="<?php echo esc_url( $changelog_url ); ?>" class="button button-secondary"><?php esc_html_e( 'Changelog', 'promptor' ); ?></a>
						</p>

						<h4 style="margin-top: 20px;"><?php esc_html_e( 'Coming Soon', 'promptor' ); ?></h4>
						<ul class="promptor-roadmap-list">
							<li><span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Gutenberg block integration', 'promptor' ); ?></li>
							<li><span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Zapier & Make integrations', 'promptor' ); ?></li>
							<li><span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Advanced analytics & funnel insights', 'promptor' ); ?></li>
						</ul>
						<p class="promptor-roadmap-note">
							<em><?php esc_html_e( 'Note: Roadmap items may change based on user feedback and priorities.', 'promptor' ); ?></em>
						</p>
					</div>
				</div>
			</div>

			<!-- Onboarding Dismiss Script (v1.2.1) -->
			<?php if ( ! $onboarding_dismissed && $setup_completed ) : ?>
				<script>
				jQuery(document).ready(function($) {
					$('#promptor-dismiss-onboarding').on('click', function(e) {
						e.preventDefault();
						var $button = $(this);
						$button.prop('disabled', true);

						$.ajax({
							url: ajaxurl,
							type: 'POST',
							data: {
								action: 'promptor_dismiss_onboarding',
								nonce: '<?php echo esc_js( wp_create_nonce( 'promptor_dismiss_onboarding_nonce' ) ); ?>'
							},
							success: function(response) {
								if (response.success) {
									$('#promptor-onboarding-section').fadeOut(400, function() {
										$(this).remove();
									});
								}
							},
							error: function() {
								alert('<?php echo esc_js( __( 'Failed to dismiss. Please try again.', 'promptor' ) ); ?>');
								$button.prop('disabled', false);
							}
						});
					});
				});
				</script>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render trial status card on the welcome page (v1.4.0).
	 */
	private function render_trial_status_card() {
		if ( ! class_exists( 'Promptor_Trial_Client' ) ) {
			return;
		}

		$trial_data  = Promptor_Trial_Client::get_trial_data();
		$api_settings = get_option( 'promptor_api_settings', array() );
		$has_api_key = ! empty( $api_settings['api_key'] );

		// Show nothing if no trial and user has API key
		if ( empty( $trial_data['token'] ) && $has_api_key ) {
			return;
		}

		// Hide trial card entirely when converted and user has API key
		$status = $trial_data['status'] ?? '';
		if ( 'converted' === $status && $has_api_key ) {
			return;
		}

		// Show "start trial" prompt if no trial and no API key
		if ( empty( $trial_data['token'] ) && ! $has_api_key ) {
			?>
			<div class="promptor-trial-welcome-card" style="background: #f0f6fc; border: 1px solid #2271b1; border-radius: 6px; padding: 20px; margin-bottom: 20px;">
				<div style="display: flex; align-items: center; gap: 15px;">
					<span class="dashicons dashicons-controls-play" style="font-size: 32px; width: 32px; height: 32px; color: #2271b1;"></span>
					<div style="flex: 1;">
						<strong style="font-size: 15px;"><?php esc_html_e( 'Try Promptor without an API key', 'promptor' ); ?></strong>
						<p style="margin: 5px 0 0; color: #646970;"><?php esc_html_e( 'Get 10 free AI queries to test the plugin. No sign-up required.', 'promptor' ); ?></p>
					</div>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=promptor-setup-wizard' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Start Free Trial', 'promptor' ); ?>
					</a>
				</div>
			</div>
			<?php
			return;
		}

		// Show trial status if trial exists
		$status    = $trial_data['status'] ?? 'unknown';
		$remaining = (int) ( $trial_data['remaining_queries'] ?? 0 );
		$is_active = Promptor_Trial_Client::is_trial_active();

		$status_config = array(
			'active'    => array( 'color' => '#00a32a', 'icon' => 'dashicons-yes-alt',     'label' => __( 'Trial Active', 'promptor' ) ),
			'exhausted' => array( 'color' => '#dba617', 'icon' => 'dashicons-warning',      'label' => __( 'Trial Queries Used Up', 'promptor' ) ),
			'expired'   => array( 'color' => '#d63638', 'icon' => 'dashicons-clock',        'label' => __( 'Trial Expired', 'promptor' ) ),
			'converted' => array( 'color' => '#2271b1', 'icon' => 'dashicons-yes-alt',     'label' => __( 'Using Own API Key', 'promptor' ) ),
			'revoked'   => array( 'color' => '#d63638', 'icon' => 'dashicons-dismiss',      'label' => __( 'Trial Revoked', 'promptor' ) ),
		);
		$cfg = $status_config[ $status ] ?? array( 'color' => '#646970', 'icon' => 'dashicons-info', 'label' => ucfirst( $status ) );
		?>
		<div class="promptor-trial-welcome-card" style="background: #fff; border: 1px solid #dcdcde; border-left: 4px solid <?php echo esc_attr( $cfg['color'] ); ?>; border-radius: 4px; padding: 15px 20px; margin-bottom: 20px;">
			<div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
				<span class="dashicons <?php echo esc_attr( $cfg['icon'] ); ?>" style="font-size: 20px; width: 20px; height: 20px; color: <?php echo esc_attr( $cfg['color'] ); ?>;"></span>
				<strong style="color: <?php echo esc_attr( $cfg['color'] ); ?>;"><?php echo esc_html( $cfg['label'] ); ?></strong>
				<?php if ( $is_active ) : ?>
					<span style="color: #646970; margin-left: 10px;">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: remaining queries */
								__( '%d queries remaining', 'promptor' ),
								(int) $remaining
							)
						);
						?>
					</span>
				<?php endif; ?>
				<?php if ( ! $is_active && ! $has_api_key ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=promptor-settings&tab=api_settings' ) ); ?>" class="button button-small" style="margin-left: auto;">
						<?php esc_html_e( 'Add API Key', 'promptor' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
