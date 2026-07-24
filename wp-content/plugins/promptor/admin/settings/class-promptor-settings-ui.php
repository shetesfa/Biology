<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Promptor_Settings_Tab_Ui {

	private $all_contexts;
	private $is_pro;

	public function __construct() {
		$ctx                = get_option( 'promptor_contexts', array() );
		$this->all_contexts = is_array( $ctx ) ? $ctx : array();

		// Safe Pro detection
		$this->is_pro = (
			function_exists( 'promptor_fs' )
			&& (
				( method_exists( promptor_fs(), 'can_use_premium_code__premium_only' ) && promptor_fs()->can_use_premium_code__premium_only() )
				|| ( method_exists( promptor_fs(), 'can_use_premium_code' ) && promptor_fs()->can_use_premium_code() )
			)
		);

		add_action( 'admin_post_promptor_save_ui_settings', array( $this, 'save_ui_settings_manually' ) );
	}

	public function register_settings() {}

	public function save_ui_settings_manually() {
		// 1. Authority check (first - faster)
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'promptor' ) );
		}

		// 2. Nonce verification
		$nonce = isset( $_POST['promptor_ui_save_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['promptor_ui_save_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'promptor_ui_save_action' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'promptor' ) );
		}

		// 3. Get raw input (without sanitizing yet)
		$raw_input = isset( $_POST['promptor_ui_settings'] ) && is_array( $_POST['promptor_ui_settings'] )
			? wp_unslash( $_POST['promptor_ui_settings'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: array();

		$edited_context = isset( $_POST['promptor_edited_context'] )
			? sanitize_key( wp_unslash( $_POST['promptor_edited_context'] ) )
			: 'global_popup';

		$expected_fields = array(
			'chat_position'              => 'key',
			'hide_header'                => 'checkbox',
			'enable_conversation_memory' => 'checkbox',
			'primary_color'              => 'color',
			'widget_bg_color'            => 'color',
			'user_bubble_color'          => 'color',
			'user_text_color'            => 'color',
			'ai_bubble_color'            => 'color',
			'ai_text_color'              => 'color',
			'input_focus_color'          => 'color',
			'font_size'                  => 'int',
			'border_radius'              => 'int',
			'default_avatar'             => 'url',
			'header_title'               => 'text',
			'header_subtitle'            => 'text',
			'input_placeholder'          => 'text',
			'animation'                  => 'key',
			'popup_context_source'       => 'key',
		);

		$sanitized_data = array();

		// 4. Sanitize each area individually
		foreach ( $expected_fields as $key => $type ) {
			// Checkbox special case
			if ( 'checkbox' === $type ) {
				$sanitized_data[ $key ] = ! empty( $raw_input[ $key ] ) ? 1 : 0;
				continue;
			}

			// If there is no value, skip it
			if ( ! isset( $raw_input[ $key ] ) ) {
				continue;
			}

			$raw_value = $raw_input[ $key ];

			// Type checking (array injection prevention)
			if ( ! is_string( $raw_value ) && ! is_numeric( $raw_value ) ) {
				continue;
			}

			switch ( $type ) {
				case 'key':
					$sanitized_data[ $key ] = sanitize_key( $raw_value );
					break;

				case 'int':
					$sanitized_data[ $key ] = absint( $raw_value );
					break;

				case 'color':
					$clean = trim( (string) $raw_value );
					// Skip empty color values — keep existing saved value.
					if ( '' === $clean ) {
						break;
					}
					// If the # symbol is missing, add it.
					if ( '#' !== substr( $clean, 0, 1 ) ) {
						$clean = '#' . $clean;
					}
					$c = sanitize_hex_color( $clean );
					if ( $c ) {
						$sanitized_data[ $key ] = $c;
					}
					break;

				case 'url':
					$url = esc_url_raw( $raw_value );
					// Check if the URL is valid
					if ( ! empty( $url ) && filter_var( $url, FILTER_VALIDATE_URL ) ) {
						$sanitized_data[ $key ] = set_url_scheme( $url, 'https' );
					} else {
						$sanitized_data[ $key ] = '';
					}
					break;

				case 'text':
				default:
					$sanitized_data[ $key ] = sanitize_text_field( $raw_value );
					break;
			}
		}

		// --- Additional verifications / whitelist & clamp ---

		// 1) chat_position whitelist (+ Lite restriction)
		if ( isset( $sanitized_data['chat_position'] ) ) {
			$allowed_positions = array( 'inline', 'bottom_right', 'bottom_left' );
			if ( ! in_array( $sanitized_data['chat_position'], $allowed_positions, true ) ) {
				$sanitized_data['chat_position'] = 'inline';
			}
			if ( ! $this->is_pro && 'inline' !== $sanitized_data['chat_position'] ) {
				$sanitized_data['chat_position'] = 'inline';
			}
		}

		// 2) animation whitelist
		if ( isset( $sanitized_data['animation'] ) ) {
			$allowed_animations = array( 'none', 'fade', 'slide' );
			if ( ! in_array( $sanitized_data['animation'], $allowed_animations, true ) ) {
				$sanitized_data['animation'] = 'none';
			}
		}

		// 3) popup_context_source must be an existing KB key
		if ( isset( $sanitized_data['popup_context_source'] ) ) {
			$kb_key   = $sanitized_data['popup_context_source'];
			$contexts = is_array( $this->all_contexts ) ? $this->all_contexts : array();
			if ( ! isset( $contexts[ $kb_key ] ) ) {
				// Get the first available KB or ‘default’
				$first_key                                 = array_key_first( $contexts );
				$sanitized_data['popup_context_source'] = $first_key ? $first_key : 'default';
			}
		}

		// 4) Number ranges (10-32px font, 0-30px radius)
		if ( isset( $sanitized_data['font_size'] ) ) {
			$sanitized_data['font_size'] = max( 10, min( 32, (int) $sanitized_data['font_size'] ) );
		}
		if ( isset( $sanitized_data['border_radius'] ) ) {
			$sanitized_data['border_radius'] = max( 0, min( 30, (int) $sanitized_data['border_radius'] ) );
		}

		// 4.1) Avatar extension check (png, jpg, jpeg, gif, svg)
		if ( ! empty( $sanitized_data['default_avatar'] ) ) {
			$path = wp_parse_url( $sanitized_data['default_avatar'], PHP_URL_PATH );
			// Invalid or false extension check
			if ( false === $path || ( is_string( $path ) && ! preg_match( '/\.(png|jpe?g|gif|svg)$/i', $path ) ) ) {
				$sanitized_data['default_avatar'] = '';
			}
		}

		// 5) Lite → Pro server-side lock (against client manipulation)
		if ( ! $this->is_pro ) {
			$sanitized_data['chat_position'] = 'inline';
			$sanitized_data['hide_header']    = 0;
		}

		// --- Save to database ---
		// FREE FIX (v1.2.1): In Free version, ALWAYS save to global promptor_ui_settings
		// because shortcode [promptor_search] without context uses global settings as fallback.
		$current_settings = get_option( 'promptor_ui_settings', array() );
		$current_settings = is_array( $current_settings ) ? $current_settings : array();
		$updated_settings = wp_parse_args( $sanitized_data, $current_settings );

		if ( false === get_option( 'promptor_ui_settings', false ) ) {
			add_option( 'promptor_ui_settings', $updated_settings, '', 'no' );
		} else {
			update_option( 'promptor_ui_settings', $updated_settings );
		}

		// Save dark mode setting.
		if ( isset( $_POST['promptor_admin_dark_mode'] ) ) {
			$dark_mode = sanitize_key( wp_unslash( $_POST['promptor_admin_dark_mode'] ) );
			if ( in_array( $dark_mode, array( 'light', 'dark', 'auto' ), true ) ) {
				update_option( 'promptor_admin_dark_mode', $dark_mode );
			}
		}

		// Mark Step 3 (Embed the Widget) as completed for onboarding tracking (v1.2.1).
		require_once PROMPTOR_PATH . 'admin/class-promptor-onboarding.php';
		Promptor_Onboarding::mark_step_3_completed();

		// Clear any caches to ensure fresh data loads
		wp_cache_flush();

		// Success message
		add_settings_error( 'promptor-settings', 'settings_updated', __( 'Settings saved successfully.', 'promptor' ), 'updated' );
		$errors = get_settings_errors( 'promptor-settings' );
		set_transient( 'settings_errors', $errors, 45 );

		// Redirect back to the same context that was being edited
		$redirect_url = add_query_arg(
			array(
				'page'              => 'promptor-settings',
				'tab'               => 'ui_settings',
				'settings-updated'  => 'true',
				'edited_context'    => $edited_context,
				'promptor_nonce'    => wp_create_nonce( 'promptor_settings_tabs_action' ),
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	public function render() {
		// Authorization control
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'promptor' ) );
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_media();

		$global_ui = get_option( 'promptor_ui_settings', array() );
		$global_ui = is_array( $global_ui ) ? $global_ui : array();

		// Build JS settings object with global settings as fallback for all contexts
		$js_settings = array( 'global_popup' => $global_ui );
		foreach ( $this->all_contexts as $key => $data ) {
			// Merge global settings with context-specific settings (context takes priority)
			$context_settings = isset( $data['ui_settings'] ) && is_array( $data['ui_settings'] ) ? $data['ui_settings'] : array();
			$js_settings[ $key ] = array_merge( $global_ui, $context_settings );
		}
		// assumes that the promptor script has been previously registered/enqueued
		wp_localize_script( 'promptor', 'promptor_all_ui_settings', $js_settings );

		// Determine default context
		// FREE: Always use global_popup (no context switching)
		// PRO: Check URL parameter first, then default to global_popup
		if ( ! $this->is_pro ) {
			$default_context = 'global_popup';
		} else {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only URL parameter for UI state
			$url_context = isset( $_GET['edited_context'] ) ? sanitize_key( wp_unslash( $_GET['edited_context'] ) ) : '';
			if ( $url_context && ( 'global_popup' === $url_context || isset( $this->all_contexts[ $url_context ] ) ) ) {
				$default_context = $url_context;
			} else {
				$default_context = 'global_popup';
			}
		}
		$default_context_settings = isset( $js_settings[ $default_context ] ) ? $js_settings[ $default_context ] : $global_ui;

		// Initial values for form fields (uses default context settings)
		$val = static function ( $id, $default = '' ) use ( $default_context_settings ) {
			return isset( $default_context_settings[ $id ] ) ? $default_context_settings[ $id ] : $default;
		};

		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="promptor-ui-settings-form">
			<input type="hidden" name="action" value="promptor_save_ui_settings" />
			<?php wp_nonce_field( 'promptor_ui_save_action', 'promptor_ui_save_nonce' ); ?>

			<?php if ( $this->is_pro ) : ?>
			<div class="postbox">
				<h2 class="hndle"><span class="dashicons dashicons-screenoptions promptor-hndle-icon"></span><span><?php esc_html_e( 'UI Customization Target', 'promptor' ); ?></span></h2>
				<div class="inside">
					<p><?php esc_html_e( 'Select which chat interface you want to customize.', 'promptor' ); ?></p>
					<?php
					$default_context = 'global_popup';
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only URL parameter for UI state
					$url_context = isset( $_GET['edited_context'] ) ? sanitize_key( wp_unslash( $_GET['edited_context'] ) ) : '';
					if ( $url_context && ( 'global_popup' === $url_context || isset( $this->all_contexts[ $url_context ] ) ) ) {
						$default_context = $url_context;
					}
					?>
					<select id="promptor-context-selector" name="promptor_context_selector" class="promptor-wide-select">
						<option value="global_popup" <?php selected( $default_context, 'global_popup' ); ?>><?php esc_html_e( 'Global Popup Settings', 'promptor' ); ?></option>
						<?php foreach ( $this->all_contexts as $key => $context ) : ?>
							<?php
							$context_name = isset( $context['name'] ) && is_string( $context['name'] )
								? $context['name']
								: sanitize_text_field( $key );
							?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $default_context, $key ); ?>>
								<?php
								/* translators: %s: Knowledge base name */
								printf( esc_html__( 'Inline: %s Knowledge Base', 'promptor' ), esc_html( $context_name ) );
								?>
							</option>
						<?php endforeach; ?>
					</select>
					<input type="hidden" id="promptor_edited_context" name="promptor_edited_context" value="<?php echo esc_attr( $default_context ); ?>" />
				</div>
			</div>
		<?php else : ?>
			<!-- Free version: Always use global settings, no selector -->
			<input type="hidden" id="promptor_edited_context" name="promptor_edited_context" value="global_popup" />
			<input type="hidden" id="promptor-context-selector" value="global_popup" />
		<?php endif; ?>

			<?php if ( ! $this->is_pro ) : ?>
				<div class="notice notice-info">
					<p>
						<?php
						$upgrade_url = ( function_exists( 'promptor_fs' ) && method_exists( promptor_fs(), 'get_upgrade_url' ) )
							? promptor_fs()->get_upgrade_url()
							: 'https://promptorai.com/pricing/';
						printf(
							/* translators: %s: Upgrade URL */
							wp_kses_post( __( 'Advanced customization options are available in the Pro version. <a href="%s" target="_blank" rel="noopener noreferrer"><strong>Upgrade to Next Level</strong></a> to unlock all features!', 'promptor' ) ),
							esc_url( $upgrade_url )
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<div class="promptor-ui-layout">
				<div class="promptor-ui-settings-column">
					<div class="postbox">
						<h2 class="hndle"><span class="dashicons dashicons-welcome-widgets-menus promptor-hndle-icon"></span><span><?php esc_html_e( 'Chat Widget Mode', 'promptor' ); ?></span></h2>
						<div class="inside">
							<?php if ( ! $this->is_pro ) : ?>
								<div class="notice notice-warning inline" style="margin: 0 0 15px 0;">
									<p>
										<?php
										$upgrade_url = ( function_exists( 'promptor_fs' ) && method_exists( promptor_fs(), 'get_upgrade_url' ) )
											? promptor_fs()->get_upgrade_url()
											: 'https://promptorai.com/pricing/';
										printf(
											/* translators: %s: Upgrade URL */
											wp_kses_post( __( '<strong>Floating popup widget is a Pro feature.</strong> <a href="%s" target="_blank" rel="noopener noreferrer">Upgrade to unlock</a> site-wide chat popups with custom positioning.', 'promptor' ) ),
											esc_url( $upgrade_url )
										);
										?>
									</p>
								</div>
							<?php endif; ?>
							<p><?php esc_html_e( 'Choose how the chat assistant will appear on your site. Use a shortcode to display it on a specific page, or set up a site-wide floating popup (Pro).', 'promptor' ); ?></p>
							<table class="form-table">
								<?php if ( $this->is_pro ) : ?>
									<tr class="promptor-global-setting">
										<th scope="row"><label for="ui_popup_context_source"><?php esc_html_e( 'Knowledge Base for Popup', 'promptor' ); ?></label></th>
										<td>
											<select id="ui_popup_context_source" name="promptor_ui_settings[popup_context_source]" class="promptor-preview-input">
												<?php
												$popup_kb = $val( 'popup_context_source', 'default' );
												foreach ( $this->all_contexts as $key => $context ) :
													$context_name = isset( $context['name'] ) && is_string( $context['name'] )
														? $context['name']
														: sanitize_text_field( $key );
													?>
													<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $popup_kb ); ?>>
														<?php echo esc_html( $context_name ); ?>
													</option>
												<?php endforeach; ?>
											</select>
											<p class="description"><?php esc_html_e( 'Select which knowledge base the floating popup chat will use.', 'promptor' ); ?></p>
										</td>
									</tr>
								<?php endif; ?>
								<?php
								$disabled_opts = $this->is_pro ? array() : array( 'bottom_right', 'bottom_left' );
								$this->render_field(
									'chat_position',
									__( 'Chat Position', 'promptor' ),
									'select',
									array(
										'value'            => $val( 'chat_position', 'inline' ),
										'options'          => array(
											'inline'       => __( 'Inline (via Shortcode)', 'promptor' ),
											'bottom_right' => __( 'Popup - Bottom Right (Pro)', 'promptor' ),
											'bottom_left'  => __( 'Popup - Bottom Left (Pro)', 'promptor' ),
										),
										'desc'             => __( 'Choose how the chat widget appears. Popup mode is a Pro feature.', 'promptor' ),
										'disabled_options' => $disabled_opts,
									)
								);
								?>
								<tr class="promptor-inline-setting" style="display:none;">
									<th scope="row"><?php esc_html_e( 'Hide Header (Inline only)', 'promptor' ); ?></th>
									<td>
										<label>
											<input type="checkbox" id="ui_hide_header" name="promptor_ui_settings[hide_header]" value="1" class="promptor-preview-input" <?php checked( (int) $val( 'hide_header', 0 ), 1 ); ?> <?php disabled( ! $this->is_pro, true ); ?> />
											<?php esc_html_e( 'Hide the header for a more compact view.', 'promptor' ); ?>
										</label>
									</td>
								</tr>
							</table>
						</div>
					</div>

					<div class="promptor-grid-2-col" style="margin-bottom:20px;">
						<div class="postbox">
							<h2 class="hndle"><span class="dashicons dashicons-art promptor-hndle-icon"></span><span><?php esc_html_e( 'General Appearance', 'promptor' ); ?></span></h2>
							<div class="inside">
								<p><?php esc_html_e( 'Adjust general appearance settings like font size and corner radius to match your sites design.', 'promptor' ); ?></p>
								<table class="form-table">
									<?php $this->render_field( 'font_size', __( 'Font Size', 'promptor' ), 'number_with_unit', array( 'unit' => 'px', 'value' => $val( 'font_size', '' ) ) ); ?>
									<?php $this->render_field( 'border_radius', __( 'Border Radius', 'promptor' ), 'number_with_unit', array( 'unit' => 'px', 'value' => $val( 'border_radius', '' ) ) ); ?>
								</table>
							</div>
						</div>
						<div class="postbox">
							<h2 class="hndle"><span class="dashicons dashicons-admin-users promptor-hndle-icon"></span><span><?php esc_html_e( 'Avatars', 'promptor' ); ?></span></h2>
							<div class="inside">
								<p><?php esc_html_e( 'Customize the avatars to give your AI assistant more personality.', 'promptor' ); ?></p>
								<table class="form-table">
									<?php $this->render_field( 'default_avatar', __( 'Bot Avatar', 'promptor' ), 'media', array( 'value' => $val( 'default_avatar', '' ) ) ); ?>
								</table>
							</div>
						</div>
					</div>

					<div class="postbox">
						<h2 class="hndle"><span class="dashicons dashicons-admin-appearance promptor-hndle-icon"></span><span><?php esc_html_e( 'Colors', 'promptor' ); ?></span></h2>
						<div class="inside">
							<p><?php esc_html_e( 'Set the chat widgets color palette to perfectly match your brand identity.', 'promptor' ); ?></p>
							<div class="promptor-grid-2-col">
								<div>
									<table class="form-table">
										<?php $this->render_field( 'primary_color', __( 'Primary Color', 'promptor' ), 'color', array( 'value' => $val( 'primary_color', '' ), 'desc' => __( 'Header, Ask button.', 'promptor' ) ) ); ?>
										<?php $this->render_field( 'user_bubble_color', __( 'User Message Bubble', 'promptor' ), 'color', array( 'value' => $val( 'user_bubble_color', '' ) ) ); ?>
										<?php $this->render_field( 'user_text_color', __( 'User Message Text', 'promptor' ), 'color', array( 'value' => $val( 'user_text_color', '' ) ) ); ?>
									</table>
								</div>
								<div>
									<table class="form-table">
										<?php $this->render_field( 'widget_bg_color', __( 'Widget Background', 'promptor' ), 'color', array( 'value' => $val( 'widget_bg_color', '' ) ) ); ?>
										<?php $this->render_field( 'ai_bubble_color', __( 'AI Message Bubble', 'promptor' ), 'color', array( 'value' => $val( 'ai_bubble_color', '' ) ) ); ?>
										<?php $this->render_field( 'ai_text_color', __( 'AI Message Text', 'promptor' ), 'color', array( 'value' => $val( 'ai_text_color', '' ) ) ); ?>
										<?php $this->render_field( 'input_focus_color', __( 'Input Focus Border', 'promptor' ), 'color', array( 'value' => $val( 'input_focus_color', '' ) ) ); ?>
									</table>
								</div>
							</div>
						</div>
					</div>

					<div class="postbox">
						<h2 class="hndle"><span class="dashicons dashicons-translation promptor-hndle-icon"></span><span><?php esc_html_e( 'Content & Language', 'promptor' ); ?></span></h2>
						<div class="inside">
							<p><?php esc_html_e( 'Edit the default text that appears in sections of the chat widget, such as the header and input placeholder.', 'promptor' ); ?></p>
							<table class="form-table">
								<?php $this->render_field( 'header_title', __( 'Header Title', 'promptor' ), 'text', array( 'value' => $val( 'header_title', '' ) ) ); ?>
								<?php $this->render_field( 'header_subtitle', __( 'Header Subtitle', 'promptor' ), 'text', array( 'value' => $val( 'header_subtitle', '' ) ) ); ?>
								<?php $this->render_field( 'input_placeholder', __( 'Input Placeholder', 'promptor' ), 'text', array( 'value' => $val( 'input_placeholder', '' ) ) ); ?>
								<?php
								$this->render_field(
									'animation',
									__( 'Popup Animation', 'promptor' ),
									'select',
									array(
										'value'   => $val( 'animation', 'none' ),
										'options' => array(
											'none'  => __( 'None', 'promptor' ),
											'fade'  => __( 'Fade', 'promptor' ),
											'slide' => __( 'Slide Up', 'promptor' ),
										),
										'desc'    => esc_html__( 'Popup window open/close animation style.', 'promptor' ),
									)
								);
								?>
							</table>
						</div>
					</div>

					<div class="postbox">
						<h2 class="hndle"><span class="dashicons dashicons-format-chat promptor-hndle-icon"></span><span><?php esc_html_e( 'Conversation Settings', 'promptor' ); ?></span></h2>
						<div class="inside">
							<p><?php esc_html_e( 'Configure how the AI handles conversation context and memory.', 'promptor' ); ?></p>
							<table class="form-table">
								<tr valign="top">
									<th scope="row"><?php esc_html_e( 'Conversation Memory', 'promptor' ); ?></th>
									<td>
										<label>
											<input type="checkbox" id="ui_enable_conversation_memory" name="promptor_ui_settings[enable_conversation_memory]" value="1" class="promptor-preview-input" <?php checked( (int) $val( 'enable_conversation_memory', 1 ), 1 ); ?> />
											<?php esc_html_e( 'Enable conversation memory - AI remembers previous messages in the conversation.', 'promptor' ); ?>
										</label>
										<p class="description">
											<?php esc_html_e( 'When enabled, the AI will use the last 15 messages as context for better responses. Note: This increases API token usage.', 'promptor' ); ?>
										</p>
									</td>
								</tr>
							</table>
						</div>
					</div>
					<div class="postbox">
						<h2 class="hndle"><span class="dashicons dashicons-visibility promptor-hndle-icon"></span><span><?php esc_html_e( 'Admin Dark Mode', 'promptor' ); ?></span></h2>
						<div class="inside">
							<p><?php esc_html_e( 'Choose the color scheme for the Promptor admin panel.', 'promptor' ); ?></p>
							<?php
							$dark_mode = get_option( 'promptor_admin_dark_mode', 'auto' );
							?>
							<fieldset>
								<label style="margin-right: 20px;">
									<input type="radio" name="promptor_admin_dark_mode" value="light" <?php checked( $dark_mode, 'light' ); ?> />
									<span class="dashicons dashicons-admin-customizer" style="vertical-align: middle;"></span>
									<?php esc_html_e( 'Light', 'promptor' ); ?>
								</label>
								<label style="margin-right: 20px;">
									<input type="radio" name="promptor_admin_dark_mode" value="dark" <?php checked( $dark_mode, 'dark' ); ?> />
									<span class="dashicons dashicons-welcome-view-site" style="vertical-align: middle;"></span>
									<?php esc_html_e( 'Dark', 'promptor' ); ?>
								</label>
								<label>
									<input type="radio" name="promptor_admin_dark_mode" value="auto" <?php checked( $dark_mode, 'auto' ); ?> />
									<span class="dashicons dashicons-desktop" style="vertical-align: middle;"></span>
									<?php esc_html_e( 'Auto (System)', 'promptor' ); ?>
								</label>
							</fieldset>
							<p class="description"><?php esc_html_e( 'Auto mode follows your operating system preference.', 'promptor' ); ?></p>
						</div>
					</div>
				</div>

				<div class="promptor-ui-preview-column">
					<div class="postbox" id="preview-postbox">
						<h2 class="hndle"><span class="dashicons dashicons-visibility promptor-hndle-icon"></span><span><?php esc_html_e( 'Live Preview', 'promptor' ); ?></span></h2>
						<div class="inside">
							<div id="preview-container">
								<div id="promptor-preview-widget">
									<div id="promptor-preview-header">
										<img id="promptor-preview-avatar" src="" alt="<?php esc_attr_e( 'Bot Avatar', 'promptor' ); ?>" />
										<div id="promptor-preview-header-text">
											<div id="promptor-preview-title"></div>
											<div id="promptor-preview-subtitle"></div>
										</div>
									</div>
									<div id="promptor-preview-body">
										<div class="promptor-preview-message ai"><?php echo esc_html__( 'Hello! How can I help you today?', 'promptor' ); ?></div>
										<div class="promptor-preview-message user">
											<span><?php echo esc_html__( 'I have a question about your services.', 'promptor' ); ?></span>
										</div>
									</div>
									<div id="promptor-preview-footer">
										<input type="text" placeholder="" readonly />
										<button type="button" id="promptor-preview-button" aria-label="<?php esc_attr_e( 'Send', 'promptor' ); ?>">➤</button>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	private function render_field( $id, $title, $type, $args = array() ) {
		$option_name = "promptor_ui_settings[{$id}]";
		$desc        = $args['desc'] ?? '';
		$value       = $args['value'] ?? '';

		$allowed_lite_fields = array(
			'primary_color',
			'widget_bg_color',
			'user_bubble_color',
			'user_text_color',
			'ai_bubble_color',
			'ai_text_color',
			'input_focus_color',
			'header_title',
			'header_subtitle',
			'input_placeholder',
		);

		$is_disabled = ( ! $this->is_pro && ! in_array( $id, $allowed_lite_fields, true ) && 'chat_position' !== $id );

		?>
		<tr valign="top" class="<?php echo esc_attr( $is_disabled ? 'promptor-pro-feature' : '' ); ?>">
			<th scope="row"><label for="ui_<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $title ); ?></label></th>
			<td>
				<?php
				switch ( $type ) {
					case 'select':
						$options          = $args['options'] ?? array();
						$disabled_options = $args['disabled_options'] ?? array();
						?>
						<select id="ui_<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $option_name ); ?>" class="promptor-preview-input" <?php disabled( $is_disabled ); ?>>
							<?php foreach ( $options as $val => $label ) : ?>
								<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $value, $val, true ); ?> <?php disabled( in_array( $val, $disabled_options, true ), true, true ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<?php
						break;

					case 'color':
						?>
						<input type="text" id="ui_<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $option_name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="promptor-color-picker promptor-preview-input" <?php disabled( $is_disabled ); ?> />
						<?php
						break;

					case 'number_with_unit':
						?>
						<input type="number" id="ui_<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $option_name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="small-text promptor-preview-input" <?php disabled( $is_disabled ); ?> />
						<span class="unit"><?php echo esc_html( $args['unit'] ?? '' ); ?></span>
						<?php
						break;

					case 'media':
						$filename = '';
						if ( ! empty( $value ) ) {
							$filename = basename( wp_parse_url( $value, PHP_URL_PATH ) );
						}
						?>
						<div class="promptor-image-uploader">
							<input type="hidden" name="<?php echo esc_attr( $option_name ); ?>" value="<?php echo esc_attr( $value ); ?>" id="ui_<?php echo esc_attr( $id ); ?>" class="promptor-preview-input" />
							<span class="promptor-avatar-filename" id="ui_<?php echo esc_attr( $id ); ?>_display">
								<?php if ( ! empty( $filename ) ) : ?>
									<span class="dashicons dashicons-format-image" style="vertical-align: middle; color: var(--promptor-success);"></span>
									<?php echo esc_html( $filename ); ?>
								<?php else : ?>
									<em><?php esc_html_e( 'No file selected', 'promptor' ); ?></em>
								<?php endif; ?>
							</span>
							<button type="button" class="button promptor-upload-button" data-target-input="<?php echo esc_attr( '#ui_' . $id ); ?>" <?php disabled( $is_disabled ); ?>>
								<?php esc_html_e( 'Upload', 'promptor' ); ?>
							</button>
							<?php if ( ! empty( $value ) ) : ?>
								<button type="button" class="button promptor-remove-avatar-btn" data-target-input="<?php echo esc_attr( '#ui_' . $id ); ?>" <?php disabled( $is_disabled ); ?>>
									<?php esc_html_e( 'Remove', 'promptor' ); ?>
								</button>
							<?php endif; ?>
						</div>
						<?php
						break;

					default: // text
						?>
						<input type="text" id="ui_<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $option_name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text promptor-preview-input" <?php disabled( $is_disabled ); ?> />
						<?php
						break;
				}

				if ( ! empty( $desc ) ) {
					echo '<p class="description">' . wp_kses_post( $desc ) . '</p>';
				}

				if ( $is_disabled ) {
					echo '<span style="color:#a00;font-weight:bold;margin-left:10px;">PRO</span>';
				}
				?>
			</td>
		</tr>
		<?php
	}
}