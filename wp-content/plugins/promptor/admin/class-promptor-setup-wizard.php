<?php
/**
 * Single-screen setup wizard for Promptor (v1.2.2)
 * Yoast-style wizard where user completes all 3 steps without leaving the page.
 *
 * @package Promptor
 * @since   1.2.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Promptor_Setup_Wizard {

	/**
	 * Render the setup wizard page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'promptor' ) );
		}

		// Note: Global variables ($title, $parent_file, etc.) are fixed in
		// Promptor_Admin::fix_admin_globals_before_header() via in_admin_header hook

		// Load onboarding helper
		require_once PROMPTOR_PATH . 'admin/class-promptor-onboarding.php';

		// Check if setup is already completed
		$setup_completed = Promptor_Onboarding::is_setup_completed();

		// Get current API settings
		$api_settings = get_option( 'promptor_api_settings', array() );
		$api_key      = $api_settings['api_key'] ?? '';
		$model        = $api_settings['model'] ?? 'gpt-4o';

		// Get contexts
		$contexts = get_option( 'promptor_contexts', array() );
		if ( empty( $contexts ) ) {
			$contexts['default'] = array(
				'name'        => 'Default',
				'source_type' => 'manual',
				'settings'    => array(
					'content_roles'      => array(),
					'example_questions'  => '',
				),
			);
			update_option( 'promptor_contexts', $contexts );
		}

		// Get step completion status
		$step_1_completed = Promptor_Onboarding::is_step_1_completed();
		$step_2_completed = Promptor_Onboarding::is_step_2_completed();
		$step_3_completed = Promptor_Onboarding::is_step_3_completed();

		?>
		<div class="wrap promptor-wizard-wrap">
			<div class="promptor-wizard-container">
				<!-- Wizard Header -->
				<div class="promptor-wizard-header">
					<div class="wizard-logo">
						<img src="<?php echo esc_url( PROMPTOR_URL . 'admin/assets/images/promptor-logo-welcome.png' ); ?>" alt="<?php esc_attr_e( 'Promptor Logo', 'promptor' ); ?>">
					</div>
					<h1><?php esc_html_e( 'Welcome to Promptor', 'promptor' ); ?></h1>
					<p class="wizard-subtitle"><?php esc_html_e( 'Set up your AI assistant in 3 easy steps', 'promptor' ); ?></p>
				</div>

				<!-- Progress Indicator -->
				<div class="promptor-wizard-progress">
					<div class="progress-step active" data-step="1">
						<div class="progress-circle">
							<span class="step-number">1</span>
							<span class="step-check dashicons dashicons-yes"></span>
						</div>
						<div class="progress-label"><?php esc_html_e( 'Connect OpenAI', 'promptor' ); ?></div>
					</div>
					<div class="progress-line"></div>
					<div class="progress-step" data-step="2">
						<div class="progress-circle">
							<span class="step-number">2</span>
							<span class="step-check dashicons dashicons-yes"></span>
						</div>
						<div class="progress-label"><?php esc_html_e( 'Choose Content', 'promptor' ); ?></div>
					</div>
					<div class="progress-line"></div>
					<div class="progress-step" data-step="3">
						<div class="progress-circle">
							<span class="step-number">3</span>
							<span class="step-check dashicons dashicons-yes"></span>
						</div>
						<div class="progress-label"><?php esc_html_e( 'Embed Widget', 'promptor' ); ?></div>
					</div>
				</div>

				<!-- Wizard Steps Container -->
				<div class="promptor-wizard-content">
					<!-- Step 1: Connect OpenAI -->
					<div class="wizard-step" id="wizard-step-1" style="display: block;">
						<div class="step-content">
							<h2><?php esc_html_e( 'Step 1: Connect AI', 'promptor' ); ?></h2>
							<p><?php esc_html_e( 'Choose how to power your AI assistant.', 'promptor' ); ?></p>

							<?php
							$trial_data   = class_exists( 'Promptor_Trial_Client' ) ? Promptor_Trial_Client::get_trial_data() : array();
							$trial_active = class_exists( 'Promptor_Trial_Client' ) && Promptor_Trial_Client::is_trial_active();
							$has_api_key  = ! empty( $api_key );
							?>

							<!-- Option A: Try Instantly (Trial) -->
							<div class="wizard-connection-option <?php echo ( $trial_active && ! $has_api_key ) ? 'selected' : ''; ?>" id="wizard-option-trial">
								<label class="wizard-option-label">
									<input type="radio" name="wizard_connection_type" value="trial" <?php checked( $trial_active && ! $has_api_key ); ?>>
									<div class="wizard-option-content">
										<strong><?php esc_html_e( 'Try Promptor instantly', 'promptor' ); ?></strong>
										<span class="wizard-option-badge"><?php esc_html_e( 'No API key needed', 'promptor' ); ?></span>
										<p><?php esc_html_e( 'Get 10 free AI queries to test Promptor. No sign-up required.', 'promptor' ); ?></p>
									</div>
								</label>
								<div class="wizard-trial-action" id="wizard-trial-section" style="<?php echo ( $trial_active && ! $has_api_key ) ? '' : 'display:none;'; ?>">
									<?php if ( $trial_active ) : ?>
										<div class="wizard-success-message">
											<span class="dashicons dashicons-yes-alt"></span>
											<?php
											printf(
												/* translators: %d: remaining queries */
												esc_html__( 'Trial active! %d queries remaining.', 'promptor' ),
												(int) ( $trial_data['remaining_queries'] ?? 0 )
											);
											?>
										</div>
									<?php else : ?>
										<button type="button" id="wizard-activate-trial-btn" class="button button-primary">
											<span class="dashicons dashicons-controls-play"></span>
											<?php esc_html_e( 'Start Free Trial', 'promptor' ); ?>
										</button>
										<span class="spinner" id="wizard-trial-spinner"></span>
										<p class="description" id="wizard-trial-status"></p>
									<?php endif; ?>
								</div>
							</div>

							<!-- Option B: Own API Key -->
							<div class="wizard-connection-option <?php echo $has_api_key ? 'selected' : ''; ?>" id="wizard-option-apikey">
								<label class="wizard-option-label">
									<input type="radio" name="wizard_connection_type" value="apikey" <?php checked( $has_api_key ); ?>>
									<div class="wizard-option-content">
										<strong><?php esc_html_e( 'Use your OpenAI API key', 'promptor' ); ?></strong>
										<p><?php esc_html_e( 'Unlimited queries with your own key from platform.openai.com', 'promptor' ); ?></p>
									</div>
								</label>
								<div class="wizard-apikey-action" id="wizard-apikey-section" style="<?php echo $has_api_key ? '' : 'display:none;'; ?>">
									<div class="wizard-form-group">
										<label for="wizard-api-key"><?php esc_html_e( 'OpenAI API Key', 'promptor' ); ?></label>
										<div class="api-key-input-group">
											<input type="password" id="wizard-api-key" class="regular-text" value="<?php echo esc_attr( $api_key ); ?>" placeholder="sk-..." autocomplete="off" spellcheck="false" autocapitalize="off">
											<button type="button" id="wizard-verify-api-btn" class="button button-secondary">
												<?php esc_html_e( 'Verify Key', 'promptor' ); ?>
											</button>
											<span class="spinner"></span>
										</div>
										<p class="description" id="wizard-api-status"></p>
									</div>

									<div class="wizard-form-group">
										<label for="wizard-model"><?php esc_html_e( 'GPT Model', 'promptor' ); ?></label>
										<select id="wizard-model" class="regular-text">
											<option value="gpt-4o" <?php selected( $model, 'gpt-4o' ); ?>><?php esc_html_e( 'GPT-4o (Recommended)', 'promptor' ); ?></option>
											<option value="gpt-4-turbo" <?php selected( $model, 'gpt-4-turbo' ); ?>><?php esc_html_e( 'GPT-4 Turbo', 'promptor' ); ?></option>
											<option value="gpt-3.5-turbo" <?php selected( $model, 'gpt-3.5-turbo' ); ?>><?php esc_html_e( 'GPT-3.5 Turbo', 'promptor' ); ?></option>
										</select>
										<p class="description"><?php esc_html_e( 'GPT-4o is recommended for its balance of cost, speed, and intelligence.', 'promptor' ); ?></p>
									</div>
								</div>
							</div>

							<?php if ( $step_1_completed ) : ?>
								<div class="wizard-success-message">
									<span class="dashicons dashicons-yes-alt"></span>
									<?php esc_html_e( 'AI connection configured successfully!', 'promptor' ); ?>
								</div>
							<?php endif; ?>
						</div>
					</div>

					<!-- Step 2: Choose Knowledge Base -->
					<div class="wizard-step" id="wizard-step-2" style="display: none;">
						<div class="step-content">
							<h2><?php esc_html_e( 'Step 2: Train Your AI', 'promptor' ); ?></h2>
							<p id="wizard-step2-desc"><?php esc_html_e( 'Select pages to train your AI assistant. Your AI will use this content to answer visitor questions.', 'promptor' ); ?></p>

							<div class="wizard-content-selection">
								<div class="wizard-kb-notice" id="wizard-kb-limit-notice">
									<span class="dashicons dashicons-info"></span>
									<span id="wizard-kb-limit-text"><?php esc_html_e( 'Free version: Select up to 3 items. Pro version: Unlimited.', 'promptor' ); ?></span>
								</div>

								<div id="wizard-content-container">
									<div class="wizard-loading">
										<span class="spinner is-active"></span>
										<span><?php esc_html_e( 'Loading content...', 'promptor' ); ?></span>
									</div>
								</div>
							</div>

							<!-- Trial training progress (hidden by default) -->
							<div id="wizard-trial-training" style="display: none;">
								<div class="wizard-training-status">
									<span class="spinner is-active" style="float: none; margin: 0 8px 0 0; vertical-align: middle;"></span>
									<span id="wizard-training-text"><?php esc_html_e( 'Training your AI on selected content...', 'promptor' ); ?></span>
								</div>
							</div>

							<!-- Trial training result + suggested questions -->
							<div id="wizard-trial-trained" style="display: none;">
								<div class="wizard-success-message">
									<span class="dashicons dashicons-yes-alt"></span>
									<span id="wizard-trained-message"></span>
								</div>
								<div id="wizard-suggested-questions" class="wizard-suggested-questions" style="display: none;">
									<h4><?php esc_html_e( 'Try asking:', 'promptor' ); ?></h4>
									<div id="wizard-questions-list"></div>
								</div>
							</div>

							<?php if ( $step_2_completed ) : ?>
								<div class="wizard-success-message">
									<span class="dashicons dashicons-yes-alt"></span>
									<?php esc_html_e( 'Knowledge base configured successfully!', 'promptor' ); ?>
								</div>
							<?php endif; ?>
						</div>
					</div>

					<!-- Step 3: Embed the Widget -->
					<div class="wizard-step" id="wizard-step-3" style="display: none;">
						<div class="step-content">
							<h2><?php esc_html_e( 'Step 3: Embed the Widget', 'promptor' ); ?></h2>
							<p><?php esc_html_e( 'Add the Promptor chat widget to your pages using the shortcode below.', 'promptor' ); ?></p>

							<div class="wizard-shortcode-box">
								<label><?php esc_html_e( 'Add this shortcode to any page or post:', 'promptor' ); ?></label>
								<div class="shortcode-input-group">
									<input type="text" readonly value="[promptor_search]" id="wizard-shortcode" class="regular-text">
									<button type="button" id="wizard-copy-shortcode" class="button button-secondary">
										<span class="dashicons dashicons-clipboard"></span>
										<?php esc_html_e( 'Copy', 'promptor' ); ?>
									</button>
								</div>
								<p class="description"><?php esc_html_e( 'You can customize the widget appearance in Settings > UI Settings after completing setup.', 'promptor' ); ?></p>
							</div>

							<div class="wizard-embed-instructions">
								<h3><?php esc_html_e( 'How to add the widget:', 'promptor' ); ?></h3>
								<ol>
									<li><?php esc_html_e( 'Edit any page or post in WordPress', 'promptor' ); ?></li>
									<li><?php esc_html_e( 'Add a Shortcode block (or paste directly in Classic Editor)', 'promptor' ); ?></li>
									<li><?php esc_html_e( 'Paste [promptor_search] and publish', 'promptor' ); ?></li>
									<li><?php esc_html_e( 'Visit your page to see the AI chat widget', 'promptor' ); ?></li>
								</ol>
							</div>

							<!-- Mini Test Chat -->
							<div class="wizard-test-chat">
								<h3>
									<span class="dashicons dashicons-format-chat"></span>
									<?php esc_html_e( 'Test Your AI Assistant', 'promptor' ); ?>
								</h3>
								<p class="wizard-test-chat-desc"><?php esc_html_e( 'Ask a question about your synced content to verify your AI assistant is working correctly.', 'promptor' ); ?></p>
								<div id="wizard-chat-messages" class="wizard-chat-messages"></div>
								<div class="wizard-chat-input-row">
									<input type="text" id="wizard-chat-input" class="regular-text" placeholder="<?php esc_attr_e( 'Ask a question about your content...', 'promptor' ); ?>" maxlength="500">
									<button type="button" id="wizard-chat-send" class="button button-primary">
										<span class="dashicons dashicons-arrow-right-alt2"></span>
										<?php esc_html_e( 'Send', 'promptor' ); ?>
									</button>
								</div>
							</div>

							<?php if ( $step_3_completed ) : ?>
								<div class="wizard-success-message">
									<span class="dashicons dashicons-yes-alt"></span>
									<?php esc_html_e( 'Setup complete! Your AI assistant is ready.', 'promptor' ); ?>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<!-- Wizard Navigation -->
				<div class="promptor-wizard-navigation">
					<button type="button" id="wizard-prev-btn" class="button button-secondary" style="display: none;">
						<span class="dashicons dashicons-arrow-left-alt2"></span>
						<?php esc_html_e( 'Previous', 'promptor' ); ?>
					</button>

					<div class="wizard-nav-center">
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?promptor_wizard_action=skip' ), 'promptor_skip_wizard' ) ); ?>" class="wizard-skip-link">
							<?php esc_html_e( 'Skip wizard', 'promptor' ); ?>
						</a>
					</div>

					<button type="button" id="wizard-next-btn" class="button button-primary" disabled>
						<?php esc_html_e( 'Next', 'promptor' ); ?>
						<span class="dashicons dashicons-arrow-right-alt2"></span>
					</button>

					<button type="button" id="wizard-complete-btn" class="button button-primary" style="display: none;">
						<span class="dashicons dashicons-yes"></span>
						<?php esc_html_e( 'Complete Setup', 'promptor' ); ?>
					</button>
				</div>
			</div>
		</div>

		<style>
		.promptor-wizard-wrap {
			background: #f0f0f1;
			margin: 0 0 0 -20px;
			padding: 40px 20px;
			min-height: calc(100vh - 32px);
		}

		.promptor-wizard-container {
			max-width: 800px;
			margin: 0 auto;
			background: #fff;
			border-radius: 8px;
			box-shadow: 0 2px 8px rgba(0,0,0,0.1);
			padding: 40px;
		}

		.promptor-wizard-header {
			text-align: center;
			margin-bottom: 40px;
		}

		.promptor-wizard-header .wizard-logo img {
			max-width: 200px;
			height: auto;
			margin-bottom: 20px;
		}

		.promptor-wizard-header h1 {
			font-size: 32px;
			margin: 0 0 10px;
			color: #1d2327;
		}

		.promptor-wizard-header .wizard-subtitle {
			font-size: 16px;
			color: #646970;
			margin: 0;
		}

		.promptor-wizard-progress {
			display: flex;
			align-items: center;
			justify-content: center;
			margin-bottom: 40px;
			padding: 0 40px;
		}

		.progress-step {
			display: flex;
			flex-direction: column;
			align-items: center;
			position: relative;
		}

		.progress-circle {
			width: 50px;
			height: 50px;
			border-radius: 50%;
			background: #f0f0f1;
			border: 3px solid #d0d0d0;
			display: flex;
			align-items: center;
			justify-content: center;
			position: relative;
			transition: all 0.3s ease;
		}

		.progress-step.active .progress-circle {
			background: #2271b1;
			border-color: #2271b1;
		}

		.progress-step.completed .progress-circle {
			background: #00a32a;
			border-color: #00a32a;
		}

		.progress-step .step-number {
			font-size: 18px;
			font-weight: 600;
			color: #646970;
		}

		.progress-step.active .step-number {
			color: #fff;
		}

		.progress-step .step-check {
			display: none;
			color: #fff;
			font-size: 24px;
		}

		.progress-step.completed .step-number {
			display: none;
		}

		.progress-step.completed .step-check {
			display: block;
		}

		.progress-label {
			margin-top: 10px;
			font-size: 13px;
			color: #646970;
			font-weight: 500;
		}

		.progress-step.active .progress-label {
			color: #2271b1;
			font-weight: 600;
		}

		.progress-line {
			width: 100px;
			height: 3px;
			background: #d0d0d0;
			margin: 0 10px 30px;
		}

		.promptor-wizard-content {
			min-height: 400px;
			margin-bottom: 30px;
		}

		.wizard-step {
			display: none;
		}

		.wizard-step.active {
			display: block;
		}

		.step-content h2 {
			font-size: 24px;
			margin: 0 0 15px;
			color: #1d2327;
		}

		.step-content > p {
			font-size: 15px;
			color: #646970;
			margin: 0 0 30px;
			line-height: 1.6;
		}

		.wizard-form-group {
			margin-bottom: 25px;
		}

		.wizard-form-group label {
			display: block;
			font-weight: 600;
			margin-bottom: 8px;
			color: #1d2327;
		}

		.api-key-input-group {
			display: flex;
			gap: 10px;
			align-items: center;
		}

		.api-key-input-group input {
			flex: 1;
		}

		.wizard-success-message {
			background: #d7f8e0;
			border-left: 4px solid #00a32a;
			padding: 12px 15px;
			margin-top: 20px;
			border-radius: 4px;
			display: flex;
			align-items: center;
			gap: 10px;
			color: #00a32a;
			font-weight: 500;
		}

		.wizard-kb-notice {
			background: #e5f5fa;
			border-left: 4px solid #2271b1;
			padding: 12px 15px;
			margin-bottom: 20px;
			border-radius: 4px;
			display: flex;
			align-items: center;
			gap: 10px;
			color: #135e96;
		}

		.wizard-content-selection {
			background: #f9f9f9;
			border: 1px solid #dcdcde;
			border-radius: 4px;
			padding: 20px;
			max-height: 400px;
			overflow-y: auto;
		}

		.wizard-loading {
			text-align: center;
			padding: 40px;
			color: #646970;
		}

		.wizard-shortcode-box {
			background: #f9f9f9;
			border: 1px solid #dcdcde;
			border-radius: 4px;
			padding: 20px;
			margin-bottom: 25px;
		}

		.wizard-shortcode-box label {
			display: block;
			font-weight: 600;
			margin-bottom: 10px;
			color: #1d2327;
		}

		.shortcode-input-group {
			display: flex;
			gap: 10px;
		}

		.shortcode-input-group input {
			flex: 1;
			font-family: monospace;
			background: #fff;
		}

		.wizard-embed-instructions h3 {
			font-size: 16px;
			margin: 0 0 15px;
			color: #1d2327;
		}

		.wizard-embed-instructions ol {
			margin: 0;
			padding-left: 20px;
		}

		.wizard-embed-instructions li {
			margin-bottom: 10px;
			color: #646970;
			line-height: 1.6;
		}

		/* Connection option cards (v1.4.0) */
		.wizard-connection-option {
			border: 2px solid #dcdcde;
			border-radius: 8px;
			padding: 20px;
			margin-bottom: 15px;
			cursor: pointer;
			transition: border-color 0.2s, background 0.2s;
		}
		.wizard-connection-option:hover {
			border-color: #2271b1;
		}
		.wizard-connection-option.selected {
			border-color: #2271b1;
			background: #f0f6fc;
		}
		.wizard-option-label {
			display: flex;
			gap: 12px;
			align-items: flex-start;
			cursor: pointer;
		}
		.wizard-option-label input[type="radio"] {
			margin-top: 4px;
		}
		.wizard-option-content strong {
			font-size: 15px;
			color: #1d2327;
		}
		.wizard-option-content p {
			margin: 5px 0 0 !important;
			font-size: 13px;
			color: #646970;
		}
		.wizard-option-badge {
			display: inline-block;
			background: #00a32a;
			color: #fff;
			font-size: 11px;
			font-weight: 600;
			padding: 2px 8px;
			border-radius: 10px;
			margin-left: 8px;
			vertical-align: middle;
		}
		.wizard-trial-action,
		.wizard-apikey-action {
			margin-top: 15px;
			padding-top: 15px;
			border-top: 1px solid #dcdcde;
		}
		#wizard-activate-trial-btn .dashicons {
			font-size: 16px;
			width: 16px;
			height: 16px;
			margin-right: 4px;
			vertical-align: text-bottom;
		}

		/* Trial training & suggested questions (v1.4.0) */
		.wizard-training-status {
			padding: 15px;
			background: #f0f6fc;
			border: 1px solid #2271b1;
			border-radius: 4px;
			margin-top: 15px;
			color: #135e96;
		}
		.wizard-suggested-questions {
			margin-top: 15px;
			padding: 15px;
			background: #f9f9f9;
			border: 1px solid #dcdcde;
			border-radius: 6px;
		}
		.wizard-suggested-questions h4 {
			margin: 0 0 10px;
			font-size: 14px;
			color: #1d2327;
		}
		.wizard-question-btn {
			display: block;
			width: 100%;
			text-align: left;
			padding: 10px 14px;
			margin-bottom: 8px;
			background: #fff;
			border: 1px solid #2271b1;
			border-radius: 4px;
			color: #2271b1;
			font-size: 14px;
			cursor: pointer;
			transition: background 0.15s, color 0.15s;
		}
		.wizard-question-btn:last-child { margin-bottom: 0; }
		.wizard-question-btn:hover {
			background: #2271b1;
			color: #fff;
		}
		.wizard-question-btn .dashicons {
			font-size: 14px;
			width: 14px;
			height: 14px;
			margin-right: 6px;
			vertical-align: text-bottom;
		}

		.promptor-wizard-navigation {
			display: flex;
			align-items: center;
			justify-content: space-between;
			padding-top: 30px;
			border-top: 1px solid #dcdcde;
		}

		.wizard-nav-center {
			flex: 1;
			text-align: center;
		}

		.wizard-skip-link {
			color: #646970;
			text-decoration: none;
			font-size: 14px;
		}

		.wizard-skip-link:hover {
			color: #2271b1;
		}

		#wizard-next-btn .dashicons,
		#wizard-complete-btn .dashicons {
			margin-left: 5px;
		}

		#wizard-prev-btn .dashicons {
			margin-right: 5px;
		}

		/* Content selection styles */
		.wizard-content-list {
			list-style: none;
			margin: 0;
			padding: 0;
		}

		.wizard-content-item {
			background: #fff;
			border: 1px solid #dcdcde;
			border-radius: 4px;
			padding: 12px;
			margin-bottom: 10px;
			display: flex;
			align-items: center;
			gap: 12px;
			cursor: pointer;
			transition: all 0.2s ease;
		}

		.wizard-content-item:hover {
			background: #f6f7f7;
			border-color: #2271b1;
		}

		.wizard-content-item input[type="checkbox"] {
			margin: 0;
			flex-shrink: 0;
		}

		.wizard-content-item-info {
			flex: 1;
		}

		.wizard-content-item-title {
			font-weight: 600;
			color: #1d2327;
			margin-bottom: 4px;
		}

		.wizard-content-item-meta {
			font-size: 13px;
			color: #646970;
		}
		
	/* Mini test chat styles */
	.wizard-test-chat {
		margin-top: 25px;
		border: 1px solid #dcdcde;
		border-radius: 4px;
		padding: 20px;
		background: #f9f9f9;
	}

	.wizard-test-chat h3 {
		font-size: 15px;
		margin: 0 0 5px;
		color: #1d2327;
		display: flex;
		align-items: center;
		gap: 6px;
	}

	.wizard-test-chat-desc {
		font-size: 13px;
		color: #646970;
		margin: 0 0 15px;
	}

	.wizard-chat-messages {
		min-height: 80px;
		max-height: 220px;
		overflow-y: auto;
		margin-bottom: 12px;
		display: flex;
		flex-direction: column;
		gap: 10px;
	}

	.wizard-chat-message {
		padding: 10px 14px;
		border-radius: 6px;
		font-size: 13px;
		line-height: 1.5;
		max-width: 90%;
	}

	.wizard-chat-message.user {
		background: #2271b1;
		color: #fff;
		align-self: flex-end;
	}

	.wizard-chat-message.assistant {
		background: #fff;
		border: 1px solid #dcdcde;
		color: #1d2327;
		align-self: flex-start;
	}

	.wizard-chat-message.loading {
		background: #fff;
		border: 1px solid #dcdcde;
		color: #646970;
		align-self: flex-start;
		font-style: italic;
	}

	.wizard-chat-input-row {
		display: flex;
		gap: 10px;
	}

	.wizard-chat-input-row input {
		flex: 1;
	}

	/* Sync progress styles */
	.wizard-sync-progress {
		background: #e5f5fa;
		border-left: 4px solid #2271b1;
		padding: 15px;
		margin-top: 15px;
		border-radius: 4px;
	}

	.wizard-sync-progress-bar-wrap {
		background: #dde8f0;
		border-radius: 3px;
		height: 8px;
		margin: 10px 0 5px;
		overflow: hidden;
	}

	.wizard-sync-progress-bar {
		height: 8px;
		background: #2271b1;
		border-radius: 3px;
		transition: width 0.3s ease;
		width: 0%;
	}

	.wizard-sync-status-text {
		font-size: 13px;
		color: #135e96;
	}
	
	</style>

		<script>
		jQuery(document).ready(function($) {
			let currentStep = 1;
			const totalSteps = 3;
			let step1Verified = <?php echo wp_json_encode( (bool) $step_1_completed ); ?>;
			let trialActive = <?php echo wp_json_encode( $trial_active ); ?>;
			let connectionType = <?php echo wp_json_encode( ( $trial_active && ! $has_api_key ) ? 'trial' : ( $has_api_key ? 'apikey' : '' ) ); ?>;
		const indexingNonce = '<?php echo esc_js( wp_create_nonce( 'promptor_indexing_nonce' ) ); ?>';
		const chatNonce = '<?php echo esc_js( wp_create_nonce( 'promptor_ai_query_nonce' ) ); ?>';
			let step2Completed = <?php echo wp_json_encode( (bool) $step_2_completed ); ?>;
			let contentLoaded = false;
			let selectedContent = [];
			const isPremium = <?php
				$is_premium_wizard = ( function_exists( 'promptor_fs' ) && method_exists( promptor_fs(), 'can_use_premium_code__premium_only' ) )
					? (bool) promptor_fs()->can_use_premium_code__premium_only()
					: false;
				echo wp_json_encode( $is_premium_wizard );
			?>;
			const maxFreeItems = 3;
			const maxTrialItems = <?php echo (int) Promptor_Trial_Client::MAX_TRIAL_PAGES; ?>;
			let suggestedQuestions = [];

			// Initialize
			updateProgress();
			updateNavButtons();

			// Load step 1 completion state
			if (step1Verified || trialActive) {
				$('#wizard-next-btn').prop('disabled', false);
			}

			// Connection type toggle (v1.4.0)
			$('input[name="wizard_connection_type"]').on('change', function() {
				connectionType = $(this).val();
				$('.wizard-connection-option').removeClass('selected');
				$(this).closest('.wizard-connection-option').addClass('selected');

				if (connectionType === 'trial') {
					$('#wizard-trial-section').slideDown(200);
					$('#wizard-apikey-section').slideUp(200);
					if (trialActive) {
						$('#wizard-next-btn').prop('disabled', false);
					}
				} else {
					$('#wizard-trial-section').slideUp(200);
					$('#wizard-apikey-section').slideDown(200);
					if (step1Verified) {
						$('#wizard-next-btn').prop('disabled', false);
					} else {
						$('#wizard-next-btn').prop('disabled', true);
					}
				}
			});

			// Click on option card selects the radio
			$('.wizard-connection-option').on('click', function(e) {
				if (!$(e.target).is('input[type="radio"]')) {
					$(this).find('input[type="radio"]').prop('checked', true).trigger('change');
				}
			});

			// Trial Activation (v1.4.0)
			$(document).on('click', '#wizard-activate-trial-btn', function() {
				const $btn = $(this);
				const $spinner = $('#wizard-trial-spinner');
				const $status = $('#wizard-trial-status');

				$btn.prop('disabled', true);
				$spinner.addClass('is-active');
				$status.html('');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'promptor_trial_activate',
						nonce: '<?php echo esc_js( wp_create_nonce( 'promptor_trial_activate_nonce' ) ); ?>'
					},
					success: function(response) {
						if (response.success) {
							trialActive = true;
							step1Verified = true;
							const remaining = response.data.trial ? response.data.trial.remaining_queries : 10;
							$('#wizard-trial-section').html(
								'<div class="wizard-success-message">' +
									'<span class="dashicons dashicons-yes-alt"></span> ' +
									'<?php echo esc_js( __( 'Trial active!', 'promptor' ) ); ?> ' + remaining + ' <?php echo esc_js( __( 'queries remaining.', 'promptor' ) ); ?>' +
								'</div>' +
								'<label style="display:flex;align-items:flex-start;gap:8px;margin-top:12px;font-size:13px;color:#646970;cursor:pointer;">' +
									'<input type="checkbox" id="wizard-telemetry-optin" style="margin-top:2px;">' +
									'<span><?php echo esc_js( __( 'Help improve Promptor by sharing anonymous usage insights (optional, no personal data)', 'promptor' ) ); ?></span>' +
								'</label>'
							);
							$(document).off('change', '#wizard-telemetry-optin').on('change', '#wizard-telemetry-optin', function() {
								$.post(ajaxurl, {
									action: 'promptor_wizard_telemetry_optin',
									nonce: '<?php echo esc_js( wp_create_nonce( 'promptor_wizard_telemetry_optin_nonce' ) ); ?>',
									enabled: $(this).is(':checked') ? '1' : '0'
								});
							});
							$('#wizard-next-btn').prop('disabled', false);
							$('.progress-step[data-step="1"]').addClass('completed');
						} else {
							$status.html('<span class="promptor-status-error"><span class="dashicons dashicons-dismiss"></span> ' + (response.data.message || '<?php esc_html_e( 'Activation failed.', 'promptor' ); ?>') + '</span>');
						}
					},
					error: function() {
						$status.html('<span class="promptor-status-error"><?php esc_html_e( 'Connection error. Please try again.', 'promptor' ); ?></span>');
					},
					complete: function() {
						$btn.prop('disabled', false);
						$spinner.removeClass('is-active');
					}
				});
			});

			// API Key Verification
			$('#wizard-verify-api-btn').on('click', function() {
				const $btn = $(this);
				const $spinner = $btn.siblings('.spinner');
				const $status = $('#wizard-api-status');
				const apiKey = $('#wizard-api-key').val().trim();
				const model = $('#wizard-model').val();

				if (!apiKey) {
					$status.html('<span class="promptor-status-error"><?php esc_html_e( 'Please enter an API key.', 'promptor' ); ?></span>');
					return;
				}

				$btn.prop('disabled', true);
				$spinner.addClass('is-active');
				$status.html('');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'promptor_verify_api_key',
						nonce: '<?php echo esc_js( wp_create_nonce( 'promptor_verify_api_key_nonce' ) ); ?>',
						api_key: apiKey,
						model: model
					},
					success: function(response) {
						if (response.success) {
							$status.html('<span class="promptor-status-ok"><span class="dashicons dashicons-yes-alt"></span> ' + response.data.message + '</span>');
							step1Verified = true;
							$('#wizard-next-btn').prop('disabled', false);
							$('.progress-step[data-step="1"]').addClass('completed');
						} else {
							$status.html('<span class="promptor-status-error"><span class="dashicons dashicons-dismiss"></span> ' + (response.data.message || '<?php esc_html_e( 'Verification failed.', 'promptor' ); ?>') + '</span>');
							step1Verified = false;
							$('#wizard-next-btn').prop('disabled', true);
						}
					},
					error: function() {
						$status.html('<span class="promptor-status-error"><?php esc_html_e( 'Connection error. Please try again.', 'promptor' ); ?></span>');
						step1Verified = false;
					},
					complete: function() {
						$btn.prop('disabled', false);
						$spinner.removeClass('is-active');
					}
				});
			});

			// Load content for Step 2
			function loadContent() {
				if (contentLoaded) return;

				// Update limit notice based on connection type
				if (connectionType === 'trial' && trialActive) {
					$('#wizard-kb-limit-text').text('<?php echo esc_js( sprintf( /* translators: %d: max pages */ __( 'Trial mode: Select up to %d pages to train your AI.', 'promptor' ), Promptor_Trial_Client::MAX_TRIAL_PAGES ) ); ?>');
				}

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'promptor_wizard_load_content',
						nonce: '<?php echo esc_js( wp_create_nonce( 'promptor_wizard_load_content_nonce' ) ); ?>'
					},
					success: function(response) {
						if (response.success && response.data.html) {
							$('#wizard-content-container').html(response.data.html);
							selectedContent = response.data.selected || [];
							contentLoaded = true;

							// Preselect homepage for trial if nothing selected
							if (connectionType === 'trial' && trialActive && selectedContent.length === 0) {
								var $first = $('.wizard-content-checkbox').first();
								if ($first.length) {
									$first.prop('checked', true).trigger('change');
								}
							}

							if (selectedContent.length > 0) {
								$('#wizard-next-btn').prop('disabled', false);
							}
						}
					}
				});
			}

			// Content selection change
			$(document).on('change', '.wizard-content-checkbox', function() {
				// Enforce item limit on frontend
				if ($(this).is(':checked')) {
					const checkedCount = $('.wizard-content-checkbox:checked').length;
					const currentMax = (connectionType === 'trial' && trialActive) ? maxTrialItems : (isPremium ? 999 : maxFreeItems);

					if (checkedCount > currentMax) {
						$(this).prop('checked', false);
						if (connectionType === 'trial' && trialActive) {
							alert('<?php echo esc_js( sprintf( /* translators: %d: max items */ __( 'Trial allows up to %d pages. Add your API key for more.', 'promptor' ), Promptor_Trial_Client::MAX_TRIAL_PAGES ) ); ?>');
						} else {
							alert('<?php echo esc_js( sprintf( /* translators: %d: max items */ __( 'Free version allows up to %d items. Please upgrade to Pro for unlimited selection.', 'promptor' ), 3 ) ); ?>');
						}
						return;
					}
				}

				selectedContent = [];
				$('.wizard-content-checkbox:checked').each(function() {
					selectedContent.push(parseInt($(this).val()));
				});

				if (selectedContent.length > 0) {
					$('#wizard-next-btn').prop('disabled', false);
				} else {
					$('#wizard-next-btn').prop('disabled', true);
				}
			});

			// Copy shortcode
			$('#wizard-copy-shortcode').on('click', function() {
				const $input = $('#wizard-shortcode');
				$input[0].select();
				document.execCommand('copy');

				const $btn = $(this);
				const originalText = $btn.html();
				$btn.html('<span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Copied!', 'promptor' ); ?>');

				setTimeout(function() {
					$btn.html(originalText);
				}, 2000);
			});

			// Navigation
			$('#wizard-next-btn').on('click', function() {
				if (currentStep === 1) {
					// Save API settings
					saveApiSettings().then(function() {
						nextStep();
					});
				} else if (currentStep === 2) {
					// Save and sync content
					saveContent().then(
						function() {
							nextStep();
						},
						function() {
							alert('<?php echo esc_js( __( 'An error occurred while saving. Please try again.', 'promptor' ) ); ?>');
						}
					);
				} else {
					nextStep();
				}
			});

			$('#wizard-prev-btn').on('click', function() {
				prevStep();
			});

			$('#wizard-complete-btn').on('click', function() {
				completeWizard();
			});

			function nextStep() {
				if (currentStep < totalSteps) {
					$('#wizard-step-' + currentStep).removeClass('active').fadeOut(200, function() {
						currentStep++;
						$('#wizard-step-' + currentStep).addClass('active').fadeIn(200);
						updateProgress();
						updateNavButtons();

						// Load content when entering step 2
						if (currentStep === 2) {
							loadContent();
						}
						// Show suggested questions when entering step 3
						if (currentStep === 3) {
							showSuggestedQuestionsInStep3();
						}
					});
				}
			}

			function prevStep() {
				if (currentStep > 1) {
					$('#wizard-step-' + currentStep).removeClass('active').fadeOut(200, function() {
						currentStep--;
						$('#wizard-step-' + currentStep).addClass('active').fadeIn(200);
						updateProgress();
						updateNavButtons();
					});
				}
			}

			function updateProgress() {
				$('.progress-step').removeClass('active');
				$('.progress-step[data-step="' + currentStep + '"]').addClass('active');
			}

			function updateNavButtons() {
				// Previous button
				if (currentStep === 1) {
					$('#wizard-prev-btn').hide();
				} else {
					$('#wizard-prev-btn').show();
				}

				// Next/Complete button
				if (currentStep === totalSteps) {
					$('#wizard-next-btn').hide();
					$('#wizard-complete-btn').show();
				} else {
					$('#wizard-next-btn').show();
					$('#wizard-complete-btn').hide();

					// Enable/disable next based on step completion
					if (currentStep === 1 && !step1Verified) {
						$('#wizard-next-btn').prop('disabled', true);
					} else if (currentStep === 2 && selectedContent.length === 0) {
						$('#wizard-next-btn').prop('disabled', true);
					} else {
						$('#wizard-next-btn').prop('disabled', false);
					}
				}
			}

			function saveApiSettings() {
				// In trial mode, skip saving API key
				if (connectionType === 'trial' && trialActive) {
					return $.Deferred().resolve().promise();
				}
				return $.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'promptor_wizard_save_api',
						nonce: '<?php echo esc_js( wp_create_nonce( 'promptor_wizard_save_api_nonce' ) ); ?>',
						api_key: $('#wizard-api-key').val(),
						model: $('#wizard-model').val()
					}
				});
			}

			function saveContent() {
				const $btn = $('#wizard-next-btn');
				const originalText = $btn.html();
				const deferred = $.Deferred();

				$btn.prop('disabled', true).html('<?php esc_html_e( 'Saving...', 'promptor' ); ?>');

				// Trial mode: use lightweight trial indexing
				if (connectionType === 'trial' && trialActive) {
					$('#wizard-trial-training').show();
					$btn.html('<?php esc_html_e( 'Training AI...', 'promptor' ); ?>');

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'promptor_trial_index_content',
							nonce: '<?php echo esc_js( wp_create_nonce( 'promptor_trial_index_nonce' ) ); ?>',
							content_ids: selectedContent
						},
						success: function(response) {
							$('#wizard-trial-training').hide();
							if (response.success) {
								step2Completed = true;
								$('.progress-step[data-step="2"]').addClass('completed');
								$('#wizard-trained-message').text(response.data.message);
								$('#wizard-trial-trained').show();

								// Store suggested questions
								if (response.data.suggested_questions && response.data.suggested_questions.length) {
									suggestedQuestions = response.data.suggested_questions;
									var $list = $('#wizard-questions-list');
									$list.empty();
									$.each(suggestedQuestions, function(i, q) {
										$list.append('<button type="button" class="wizard-question-btn" data-question="' + $('<span>').text(q).html() + '"><span class="dashicons dashicons-format-chat"></span>' + $('<span>').text(q).html() + '</button>');
									});
									$('#wizard-suggested-questions').show();
								}

								$btn.prop('disabled', false).html(originalText);
								deferred.resolve();
							} else {
								var errMsg = (response.data && response.data.message) ? response.data.message : '<?php echo esc_js( __( 'Training failed.', 'promptor' ) ); ?>';
								$('#wizard-trial-training').hide();
								alert(errMsg);
								$btn.prop('disabled', false).html(originalText);
								deferred.reject();
							}
						},
						error: function() {
							$('#wizard-trial-training').hide();
							$btn.prop('disabled', false).html(originalText);
							deferred.reject();
						}
					});

					return deferred.promise();
				}

				// Normal flow: save content selection + sync embeddings
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'promptor_wizard_save_content',
						nonce: '<?php echo esc_js( wp_create_nonce( 'promptor_wizard_save_content_nonce' ) ); ?>',
						content_ids: selectedContent
					},
					success: function(response) {
						if (response.success) {
							step2Completed = true;
							$('.progress-step[data-step="2"]').addClass('completed');
							// Now trigger sync so content is indexed
							startWizardSync(deferred, $btn, originalText);
						} else {
							$btn.prop('disabled', false).html(originalText);
							deferred.reject();
						}
					},
					error: function() {
						$btn.prop('disabled', false).html(originalText);
						deferred.reject();
					}
				});

				return deferred.promise();
			}

			function startWizardSync(deferred, $btn, originalText) {
				$btn.html('<?php esc_html_e( 'Syncing...', 'promptor' ); ?> <span class="spinner is-active" style="float:none;margin:0 0 0 5px;vertical-align:middle;"></span>');

				// Show sync progress in step 2
				let $progress = $('#wizard-sync-progress');
				if ($progress.length === 0) {
					$('#wizard-step-2 .step-content').append(
						'<div id="wizard-sync-progress" class="wizard-sync-progress">' +
							'<div class="wizard-sync-status-text"><?php esc_html_e( 'Indexing your content with AI...', 'promptor' ); ?></div>' +
							'<div class="wizard-sync-progress-bar-wrap"><div class="wizard-sync-progress-bar" id="wizard-sync-bar"></div></div>' +
						'</div>'
					);
					$progress = $('#wizard-sync-progress');
				}

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'promptor_start_sync',
						nonce: indexingNonce,
						context: 'default'
					},
					success: function(response) {
						if (response.success && response.data.items && response.data.items.length > 0) {
							processItemsSequentially(
								response.data.items,
								response.data.item_type || 'post',
								0,
								deferred,
								$btn,
								originalText,
								$progress
							);
						} else {
							// Nothing to sync (already up-to-date or empty)
							$progress.remove();
							$btn.prop('disabled', false).html(originalText);
							deferred.resolve();
						}
					},
					error: function() {
						// Sync failed — still allow proceeding
						$progress.remove();
						$btn.prop('disabled', false).html(originalText);
						deferred.resolve();
					}
				});
			}

			function processItemsSequentially(items, itemType, index, deferred, $btn, originalText, $progress) {
				if (index >= items.length) {
					$progress.remove();
					$btn.prop('disabled', false).html(originalText);
					deferred.resolve();
					return;
				}

				const percent = Math.round((index / items.length) * 100);
				$('#wizard-sync-bar').css('width', percent + '%');
				$progress.find('.wizard-sync-status-text').text(
					'<?php esc_html_e( 'Indexing', 'promptor' ); ?> ' + (index + 1) + ' / ' + items.length + '...'
				);

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'promptor_process_item',
						nonce: indexingNonce,
						context: 'default',
						item_type: itemType,
						item_id: items[index]
					},
					complete: function() {
						processItemsSequentially(items, itemType, index + 1, deferred, $btn, originalText, $progress);
					}
				});
			}

			function completeWizard() {
				const $btn = $('#wizard-complete-btn');
				$btn.prop('disabled', true);

				// Mark step 3 as completed
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'promptor_wizard_complete',
						nonce: '<?php echo esc_js( wp_create_nonce( 'promptor_wizard_complete_nonce' ) ); ?>'
					},
					success: function(response) {
						if (response.success) {
							window.location.href = '<?php echo esc_url( admin_url( 'admin.php?page=promptor' ) ); ?>';
						}
					}
				});
			}

			// Mini test chat
			function sendTestChatMessage() {
				const $input = $('#wizard-chat-input');
				const $messages = $('#wizard-chat-messages');
				const $sendBtn = $('#wizard-chat-send');
				const query = $input.val().trim();

				if (!query) return;

				// Show user message
				$messages.append('<div class="wizard-chat-message user">' + $('<span>').text(query).html() + '</div>');
				$input.val('');
				$sendBtn.prop('disabled', true);

				// Show loading indicator
				const $loading = $('<div class="wizard-chat-message loading"><?php esc_html_e( 'Thinking...', 'promptor' ); ?></div>');
				$messages.append($loading);
				$messages.scrollTop($messages[0].scrollHeight);

				// Build history array in the format expected by handle_ai_query()
				var historyPayload = JSON.stringify( [ { role: 'user', content: query } ] );

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'promptor_get_ai_suggestion',
						nonce: chatNonce,
						history: historyPayload,
						context: 'default'
					},
					success: function(response) {
						$loading.remove();
						var aiText = response.data && response.data.ai_data && response.data.ai_data.ai_explanation
							? response.data.ai_data.ai_explanation
							: '';
						if (response.success && aiText) {
							$messages.append('<div class="wizard-chat-message assistant">' + $('<span>').text(aiText).html() + '</div>');
						} else {
							const errMsg = (response.data && response.data.message)
								? response.data.message
								: '<?php esc_html_e( 'Sorry, could not get a response. Please check your API key and try again.', 'promptor' ); ?>';
							$messages.append('<div class="wizard-chat-message assistant">' + $('<span>').text(errMsg).html() + '</div>');
						}
					},
					error: function(jqXHR) {
						$loading.remove();
						var errDetail = '';
						try {
							var resp = jqXHR.responseJSON || JSON.parse(jqXHR.responseText || '{}');
							errDetail = (resp.data && resp.data.message) ? resp.data.message : '';
						} catch(e) {}
						var msg = errDetail || '<?php esc_html_e( 'Connection error. Please try again.', 'promptor' ); ?>';
						$messages.append('<div class="wizard-chat-message assistant">' + $('<span>').text(msg).html() + '</div>');
					},
					complete: function() {
						$sendBtn.prop('disabled', false);
						$messages.scrollTop($messages[0].scrollHeight);
					}
				});
			}

			$('#wizard-chat-send').on('click', function() {
				sendTestChatMessage();
			});

			$('#wizard-chat-input').on('keypress', function(e) {
				if (e.which === 13) {
					sendTestChatMessage();
				}
			});

			// Suggested question click — sends to test chat
			$(document).on('click', '.wizard-question-btn', function() {
				var question = $(this).data('question');
				if (question) {
					$('#wizard-chat-input').val(question);
					sendTestChatMessage();
					// Highlight the clicked button
					$(this).css({ background: '#2271b1', color: '#fff' });
				}
			});

			// When entering Step 3, show suggested questions if available
			function showSuggestedQuestionsInStep3() {
				if (suggestedQuestions.length > 0 && connectionType === 'trial') {
					var $container = $('#wizard-step-3 .step-content');
					if ($container.find('#wizard-step3-suggestions').length === 0) {
						var html = '<div id="wizard-step3-suggestions" class="wizard-suggested-questions" style="margin-bottom: 15px;">' +
							'<h4><?php echo esc_js( __( 'Try asking your AI:', 'promptor' ) ); ?></h4>' +
							'<div id="wizard-step3-questions-list"></div></div>';
						$container.find('.wizard-test-chat').before(html);
						var $list = $('#wizard-step3-questions-list');
						$.each(suggestedQuestions, function(i, q) {
							$list.append('<button type="button" class="wizard-question-btn" data-question="' + $('<span>').text(q).html() + '"><span class="dashicons dashicons-format-chat"></span>' + $('<span>').text(q).html() + '</button>');
						});
					}
				}
			}
		});
		</script>
		<?php
	}
}
