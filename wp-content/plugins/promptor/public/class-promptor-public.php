<?php
/**
 * Public UI/render logic for Promptor.
 *
 * @package Promptor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Promptor_Public {

	/** @var string */
	private $plugin_name;

	/** @var string */
	private $version;

	/** @var bool */
	private static $assets_enqueued = false;

	/** @var array<string> */
	private static $localized_contexts = array();

	/**
	 * @param string $plugin_name
	 * @param string $version
	 */
	public function __construct( $plugin_name = 'promptor', $version = PROMPTOR_VERSION ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		if ( did_action( 'init' ) ) {
			$this->register_shortcodes();
		} else {
			add_action( 'init', array( $this, 'register_shortcodes' ) );
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );

		$ui_settings   = get_option( 'promptor_ui_settings', array() );
		$chat_position = isset( $ui_settings['chat_position'] ) ? $ui_settings['chat_position'] : 'inline';

		if ( $this->is_pro() && 'inline' !== $chat_position ) {
			add_action( 'wp_footer', array( $this, 'render_floating_widget_container' ) );
		}
	}

	/**
	 * Freemius Pro kontrolü.
	 */
	private function is_pro() {
		return function_exists( 'promptor_fs' ) && promptor_fs()->can_use_premium_code();
	}

	/**
	 * Sayfada herhangi bir Promptor shortcode'u var mı?
	 */
	private function page_has_any_promptor_shortcode() {
		$post_obj = get_post();

		if ( ! $post_obj instanceof WP_Post ) {
			return false;
		}

		$content = (string) $post_obj->post_content;

		if (
			has_shortcode( $content, 'promptor' )
			|| has_shortcode( $content, 'promptor_search' )
			|| has_shortcode( $content, 'promptor_results' )
		) {
			return true;
		}

		// Dinamik varyasyonlar (promptor_[slug], promptor_search_[slug], promptor_results_[slug]).
		return (bool) preg_match( '/\[(promptor|promptor_search|promptor_results)_[A-Za-z0-9_-]+\b/', $content );
	}

	/**
	 * Shortcode kayıtları.
	 */
	public function register_shortcodes() {
		if ( ! shortcode_exists( 'promptor' ) ) {
			add_shortcode( 'promptor', array( $this, 'render_shortcode_content' ) );
		}
		if ( ! shortcode_exists( 'promptor_search' ) ) {
			add_shortcode( 'promptor_search', array( $this, 'render_shortcode_content' ) );
		}
		if ( ! shortcode_exists( 'promptor_results' ) ) {
			add_shortcode( 'promptor_results', array( $this, 'render_shortcode_content' ) );
		}

		// Her bir context için dinamik shortcode'lar.
		$contexts = get_option( 'promptor_contexts', array() );
		if ( is_array( $contexts ) && ! empty( $contexts ) ) {
			$that = $this;
			foreach ( $contexts as $key => $_ctx ) {
				$key_s = sanitize_key( $key );
				foreach ( array( 'promptor_', 'promptor_search_', 'promptor_results_' ) as $prefix ) {
					$tag = $prefix . $key_s;
					if ( shortcode_exists( $tag ) ) {
						continue;
					}
					add_shortcode(
						$tag,
						/**
						 * @param array  $atts
						 * @param string $content
						 * @param string $tag_name
						 */
						function ( $atts = array(), $content = null, $tag_name = '' ) use ( $that, $key ) {
							$atts = shortcode_atts(
								array( 'context' => $key ),
								(array) $atts,
								$tag_name
							);
							return $that->render_shortcode_content( $atts, $content, $tag_name );
						}
					);
				}
			}
		}
	}

	/**
	 * Geriye dönük uyumluluk.
	 */
	public function register_shortcode() {
		return $this->register_shortcodes();
	}

	/**
	 * Gerekirse varlıkları sıraya al.
	 */
	public function maybe_enqueue_assets() {
		$ui_settings     = get_option( 'promptor_ui_settings', array() );
		$chat_position   = isset( $ui_settings['chat_position'] ) ? $ui_settings['chat_position'] : 'inline';
		$is_popup_active = $this->is_pro() && 'inline' !== $chat_position;
		$has_shortcode   = $this->page_has_any_promptor_shortcode();

		if ( $is_popup_active || $has_shortcode ) {
			$this->enqueue_assets();
		}
	}

	/**
	 * CSS/JS enqueue.
	 */
	private function enqueue_assets() {
		if ( self::$assets_enqueued || is_admin() ) {
			return;
		}

		wp_enqueue_style( 'dashicons' );

		wp_enqueue_style(
			$this->plugin_name,
			esc_url( trailingslashit( PROMPTOR_URL ) . 'public/assets/css/promptor-public.css' ),
			array( 'dashicons' ),
			$this->version,
			'all'
		);

		// Module scripts (dependency chain: dom-helpers → error-handler, api, services, ui → main).
		wp_enqueue_script(
			'promptor-dom-helpers',
			esc_url( trailingslashit( PROMPTOR_URL ) . 'public/assets/js/utils/dom-helpers.js' ),
			array(),
			$this->version,
			true
		);

		wp_enqueue_script(
			'promptor-error-handler',
			esc_url( trailingslashit( PROMPTOR_URL ) . 'public/assets/js/utils/error-handler.js' ),
			array(),
			$this->version,
			true
		);

		wp_enqueue_script(
			'promptor-conversation',
			esc_url( trailingslashit( PROMPTOR_URL ) . 'public/assets/js/modules/conversation.js' ),
			array(),
			$this->version,
			true
		);

		wp_enqueue_script(
			'promptor-api',
			esc_url( trailingslashit( PROMPTOR_URL ) . 'public/assets/js/modules/api.js' ),
			array( 'jquery' ),
			$this->version,
			true
		);

		wp_enqueue_script(
			'promptor-services',
			esc_url( trailingslashit( PROMPTOR_URL ) . 'public/assets/js/modules/services.js' ),
			array( 'promptor-dom-helpers' ),
			$this->version,
			true
		);

		wp_enqueue_script(
			'promptor-ui',
			esc_url( trailingslashit( PROMPTOR_URL ) . 'public/assets/js/modules/ui.js' ),
			array( 'jquery', 'promptor-dom-helpers' ),
			$this->version,
			true
		);

		wp_enqueue_script(
			$this->plugin_name,
			esc_url( trailingslashit( PROMPTOR_URL ) . 'public/assets/js/promptor-public.js' ),
			array( 'jquery', 'wp-i18n', 'promptor-dom-helpers', 'promptor-error-handler', 'promptor-api', 'promptor-conversation', 'promptor-services', 'promptor-ui' ),
			$this->version,
			true
		);

		// Note: We don't use wp_set_script_translations() because all translations
		// are embedded directly via wp_localize_script's i18n object. This ensures
		// translations work regardless of plugin folder name or JSON file paths.

		self::$assets_enqueued = true;
	}

	/**
	 * UI için CSS değişkenleri oluştur.
	 *
	 * @param array $settings
	 * @return string
	 */
	private function generate_dynamic_styles( $settings ) {
		$defaults = array(
			'primary_color'     => '#15b6ff',
			'widget_bg_color'   => '#ffffff',
			'user_bubble_color' => '#15b6ff',
			'user_text_color'   => '#ffffff',
			'ai_bubble_color'   => '#f1f5f9',
			'ai_text_color'     => '#0f172a',
			'input_focus_color' => '#15b6ff',
			'font_size'         => 16,
			'border_radius'     => 12,
			'hide_header'       => 0,
		);

		$settings = wp_parse_args( (array) $settings, $defaults );

		// Filtre: geliştiriciler UI ayarlarını son bir kez düzenleyebilsin.
		$settings = apply_filters( 'promptor/public/ui_settings', $settings );

		return implode(
			' ',
			array(
				'--promptor-primary-color:' . esc_attr( $settings['primary_color'] ) . ';',
				'--promptor-widget-bg-color:' . esc_attr( $settings['widget_bg_color'] ) . ';',
				'--promptor-user-bubble-color:' . esc_attr( $settings['user_bubble_color'] ) . ';',
				'--promptor-user-text-color:' . esc_attr( $settings['user_text_color'] ) . ';',
				'--promptor-ai-bubble-color:' . esc_attr( $settings['ai_bubble_color'] ) . ';',
				'--promptor-ai-text-color:' . esc_attr( $settings['ai_text_color'] ) . ';',
				'--promptor-input-focus-color:' . esc_attr( $settings['input_focus_color'] ) . ';',
				'--promptor-font-size:' . (int) $settings['font_size'] . 'px;',
				'--promptor-border-radius:' . (int) $settings['border_radius'] . 'px;',
				'--promptor-header-display:' . ( ! empty( $settings['hide_header'] ) ? 'none' : 'flex' ) . ';',
			)
		);
	}

	/**
	 * Frontend JS için localized veri.
	 *
	 * @param array  $settings
	 * @param string $context_key
	 */
	private function localize_script_data( $settings, $context_key ) {
		// Null safety: Ensure context_key is a string
		if ( ! is_string( $context_key ) || '' === $context_key ) {
			return;
		}

		if ( in_array( $context_key, self::$localized_contexts, true ) ) {
			return;
		}

		$all_contexts = get_option( 'promptor_contexts', array() );
		$has_contexts = is_array( $all_contexts ) && ! empty( $all_contexts );

		if ( 'global_popup' !== $context_key && $has_contexts && ! isset( $all_contexts[ $context_key ] ) ) {
			return;
		}

		$example_questions = array();
		if ( isset( $all_contexts[ $context_key ]['settings']['example_questions'] ) ) {
			$questions_raw     = (string) $all_contexts[ $context_key ]['settings']['example_questions'];
			$example_questions = array_filter( array_map( 'trim', explode( "\n", $questions_raw ) ) );
		}

		// Filtre: örnek sorular son hal.
		$example_questions = apply_filters( 'promptor/public/example_questions', $example_questions, $context_key );

		$defaults = array(
			'header_title'      => __( 'AI Assistant', 'promptor' ),
			'header_subtitle'   => __( 'Typically replies in minutes', 'promptor' ),
			'input_placeholder' => __( 'Ask a question...', 'promptor' ),
			'default_avatar'    => trailingslashit( PROMPTOR_URL ) . 'admin/assets/images/promptor-logo-icon.png',
		);
		$settings = wp_parse_args( (array) $settings, $defaults );

		$localization_object_name = 'promptor_data_' . str_replace( '-', '_', $context_key );

		// En azından script handle'ı sıraya alınmış olsun.
		if ( ! wp_script_is( $this->plugin_name, 'enqueued' ) && ! wp_script_is( $this->plugin_name, 'registered' ) ) {
			$this->enqueue_assets();
		}

		// Get conversation memory setting (default: enabled)
		$enable_memory = isset( $settings['enable_conversation_memory'] ) ? (int) $settings['enable_conversation_memory'] : 1;

		wp_localize_script(
			$this->plugin_name,
			$localization_object_name,
			array(
				'ajax_url'                   => admin_url( 'admin-ajax.php' ),
				'ai_query_nonce'             => wp_create_nonce( 'promptor_ai_query_nonce' ),
				'form_nonce'                 => wp_create_nonce( 'promptor_form_submit_nonce' ),
				'add_to_cart_nonce'          => wp_create_nonce( 'promptor_add_to_cart_nonce' ),
				'feedback_nonce'             => wp_create_nonce( 'promptor_feedback_nonce' ),
				'context'                    => $context_key,
				'example_questions'          => array_values( $example_questions ),
				'enable_conversation_memory' => $enable_memory,
				'ui'                         => array(
					'header_title'      => (string) $settings['header_title'],
					'header_subtitle'   => (string) $settings['header_subtitle'],
					'input_placeholder' => (string) $settings['input_placeholder'],
					'bot_avatar_url'    => esc_url( $settings['default_avatar'] ),
				),
				'i18n'                       => array(
					// Cart & Feedback
					'addToCart'                       => __( 'Add to Cart', 'promptor' ),
					'isHelpful'                       => __( 'Is this conversation helpful so far?', 'promptor' ),
					'goodResponse'                    => __( 'Good response', 'promptor' ),
					'badResponse'                     => __( 'Bad response', 'promptor' ),
					'thankYou'                        => __( 'Thank you for your feedback!', 'promptor' ),

					// Service Recommendations
					'conversationRecommendedService'  => __( 'Based on our conversation, here is the recommended service:', 'promptor' ),
					'conversationRecommendedServices' => __( 'Based on our conversation so far, you need the following services:', 'promptor' ),
					'servicesTypicallyTogether'       => __( 'These services are typically handled together.', 'promptor' ),
					'clickServiceDetails'             => __( 'Click on a service to see details and select:', 'promptor' ),

					// Service Selection & Quote
					'selectServiceQuote'              => __( 'Select a service to request a quote', 'promptor' ),
					'requestQuote'                    => __( 'Request Quote', 'promptor' ),
					'requestQuoteForAll'              => __( 'Request Quote for All', 'promptor' ),
					'requestAQuote'                   => __( 'Request a Quote', 'promptor' ),
					'servicesInterestedIn'            => __( 'Services you are interested in:', 'promptor' ),

					// Form Labels
					'yourName'                        => __( 'Your Name', 'promptor' ),
					'yourEmail'                       => __( 'Your Email', 'promptor' ),
					'yourPhone'                       => __( 'Your Phone', 'promptor' ),
					'anythingElse'                    => __( 'Anything else you would like to add?', 'promptor' ),
					'submitInquiry'                   => __( 'Submit Inquiry', 'promptor' ),

					// Drawer & Products
					'selectedServices'                => __( 'Selected Services', 'promptor' ),
					'toggleDrawer'                    => __( 'Toggle drawer', 'promptor' ),
					'recommendedProducts'             => __( 'Recommended Products', 'promptor' ),
					'relatedArticles'                 => __( 'Related Articles', 'promptor' ),
					'download'                        => __( 'Download', 'promptor' ),
					'off'                             => __( 'OFF', 'promptor' ),
					'price'                           => __( 'Price', 'promptor' ),

					// Errors & Validation
					'chatLoadError'                   => __( 'Chat could not be loaded. (Context Error)', 'promptor' ),
					'securityValidationFailed'        => __( 'Security validation failed. Please refresh the page.', 'promptor' ),
					'configError'                     => __( 'Configuration error. Please contact administrator.', 'promptor' ),
					'messageTooLong'                  => __( 'Your message is too long. Please shorten it and try again.', 'promptor' ),
					'networkError'                    => __( 'Network error. Please check your connection.', 'promptor' ),
					'genericError'                    => __( 'Sorry, an error occurred. Please try again.', 'promptor' ),
					'feedbackSaveError'               => __( 'An error occurred while saving feedback.', 'promptor' ),
					'added'                           => __( 'Added', 'promptor' ),
					'errorOccurred'                   => __( 'An error occurred.', 'promptor' ),
					'networkErrorOccurred'            => __( 'A network error occurred.', 'promptor' ),

					// Form Validation
					'selectAtLeastOne'                => __( 'Please select at least one service.', 'promptor' ),
					'fillNameEmail'                   => __( 'Please fill in your name and email.', 'promptor' ),
					'validEmail'                      => __( 'Please enter a valid email address.', 'promptor' ),
					'sending'                         => __( 'Sending...', 'promptor' ),
					'thankYouReceived'                => __( 'Thank you! We received your inquiry.', 'promptor' ),
					'errorTryAgain'                   => __( 'An error occurred. Please try again.', 'promptor' ),
					'networkErrorTryAgain'            => __( 'A network error occurred. Please try again.', 'promptor' ),
					'failedInitChat'                  => __( 'Failed to initialize chat. Please refresh the page.', 'promptor' ),

					// New Conversation Confirmation
					'confirmNewConversation'          => __( 'Are you sure you want to start a new conversation? This will clear your chat history and selected services.', 'promptor' ),

					// Chat UX
					'exampleQuestionsHeader'          => __( 'Here are some ideas to get you started:', 'promptor' ),
					'justNow'                         => __( 'just now', 'promptor' ),
					'minAgo'                          => __( ' min ago', 'promptor' ),
					'hrAgo'                           => __( ' hr ago', 'promptor' ),
					'dAgo'                            => __( ' d ago', 'promptor' ),
					'thinkingIndicator'               => __( 'Promptor is thinking', 'promptor' ),
				),
			)
		);

		self::$localized_contexts[] = $context_key;
	}

	/**
	 * Shortcode render.
	 *
	 * @param array  $atts
	 * @param string $content
	 * @param string $tag
	 * @return string
	 */
	public function render_shortcode_content( $atts = array(), $content = null, $tag = '' ) {
		$attributes        = shortcode_atts( array( 'context' => 'default' ), (array) $atts, $tag ? $tag : 'promptor' );
		$requested_context = array_key_exists( 'context', (array) $atts );
		$context_key       = sanitize_key( $attributes['context'] );

		$all_contexts = get_option( 'promptor_contexts', array() );
		$has_contexts = is_array( $all_contexts ) && ! empty( $all_contexts );

		if ( $requested_context && ( ! $has_contexts || ! isset( $all_contexts[ $context_key ] ) ) ) {
			// İstenen context yoksa hiçbir çıktı verme.
			return '';
		}

		// Get UI settings: context-specific (if requested and has ui_settings) or global
		// FREE: Context parameter rarely used, always falls back to global
		// PRO: Contexts can have their own UI customizations
		if ( $requested_context && $has_contexts && isset( $all_contexts[ $context_key ] ) && ! empty( $all_contexts[ $context_key ]['ui_settings'] ) ) {
			$settings = (array) $all_contexts[ $context_key ]['ui_settings'];
		} else {
			$settings = (array) get_option( 'promptor_ui_settings', array() );
		}

		$this->enqueue_assets();
		$this->localize_script_data( $settings, $context_key );

		$style_vars   = $this->generate_dynamic_styles( $settings );
		$container_id = wp_unique_id( 'promptor-app-container-' . $context_key . '-' );

		// Build inline styles for this specific container
		// We output these directly in the shortcode because wp_add_inline_style() doesn't work
		// when called during shortcode rendering (after wp_head has already executed)
		$inline_style  = '#' . esc_attr( $container_id ) . ' { ' . $style_vars . ' }';
		$inline_style .= "\n#" . esc_attr( $container_id ) . ' .promptor-header { background-color: var(--promptor-primary-color) !important; }';
		$inline_style .= "\n#" . esc_attr( $container_id ) . ' .promptor-ask-btn { background-color: var(--promptor-primary-color) !important; }';
		$inline_style .= "\n#" . esc_attr( $container_id ) . ' .promptor-ask-btn:hover { background-color: var(--promptor-primary-color) !important; opacity: 0.85; }';
		$inline_style .= "\n#" . esc_attr( $container_id ) . ' .user-message .promptor-message-bubble { background-color: var(--promptor-user-bubble-color) !important; color: var(--promptor-user-text-color) !important; }';
		$inline_style .= "\n#" . esc_attr( $container_id ) . ' .ai-message .promptor-message-bubble { background-color: var(--promptor-ai-bubble-color) !important; color: var(--promptor-ai-text-color) !important; }';
		$inline_style .= "\n#" . esc_attr( $container_id ) . ' .promptor-query-input:focus { border-color: var(--promptor-input-focus-color) !important; }';
		$inline_style .= "\n#" . esc_attr( $container_id ) . ' .promptor-container { background: var(--promptor-widget-bg-color) !important; }';
		$inline_style .= "\n#" . esc_attr( $container_id ) . ' .promptor-container { font-size: var(--promptor-font-size) !important; border-radius: var(--promptor-border-radius) !important; }';

		// Generate unique wrapper ID for this instance
		$wrapper_id = 'promptor-wrapper-' . wp_unique_id();

		ob_start();
		?>
		<style><?php echo $inline_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></style>
		<div
			id="<?php echo esc_attr( $wrapper_id ); ?>"
			class="promptor-app-shortcode-wrapper"
			style="all: initial !important; display: block !important; overflow: hidden !important; border: 1px solid #e0e0e0 !important; border-radius: 12px !important; box-shadow: 0 6px 18px rgba(0, 0, 0, 0.07) !important; height: 75vh !important; min-height: 500px !important; max-height: 800px !important; margin: 20px 0 !important; padding: 0 !important; box-sizing: border-box !important; position: relative !important; isolation: isolate !important; contain: layout style paint !important; transform: translateZ(0) !important;"
		>
			<?php $this->render_chat_app_html( $container_id, $context_key, $style_vars ); ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Footer’daki yüzen widget.
	 */
	public function render_floating_widget_container() {
		$settings             = (array) get_option( 'promptor_ui_settings', array() );
		$position             = isset( $settings['chat_position'] ) ? $settings['chat_position'] : 'bottom_right';
		$animation            = isset( $settings['animation'] ) ? $settings['animation'] : 'fade';
		$context_key          = 'global_popup';
		$popup_source_context = isset( $settings['popup_context_source'] ) ? sanitize_key( $settings['popup_context_source'] ) : 'default';
		$container_id         = 'promptor-app-container-' . esc_attr( $context_key );

		$all_contexts   = get_option( 'promptor_contexts', array() );
		$popup_settings = isset( $all_contexts[ $popup_source_context ]['ui_settings'] ) ? (array) $all_contexts[ $popup_source_context ]['ui_settings'] : $settings;

		$this->localize_script_data( $popup_settings, $popup_source_context );
		$style_vars = $this->generate_dynamic_styles( $popup_settings );

		// Ensure style handle is enqueued before adding inline styles
		if ( ! wp_style_is( $this->plugin_name, 'enqueued' ) ) {
			wp_enqueue_style( $this->plugin_name );
		}

		$inline_style  = '#' . esc_attr( $container_id ) . ' { ' . $style_vars . ' }';
		$inline_style .= "\n#" . esc_attr( $container_id ) . ' .promptor-header { background-color: var(--promptor-primary-color) !important; }';
		$inline_style .= "\n#" . esc_attr( $container_id ) . ' .promptor-ask-btn { background-color: var(--promptor-primary-color) !important; }';
		$inline_style .= "\n#" . esc_attr( $container_id ) . ' .user-message .promptor-message-bubble { background-color: var(--promptor-user-bubble-color) !important; color: var(--promptor-user-text-color) !important; }';
		$inline_style .= "\n#" . esc_attr( $container_id ) . ' .ai-message .promptor-message-bubble { background-color: var(--promptor-ai-bubble-color) !important; color: var(--promptor-ai-text-color) !important; }';

		wp_add_inline_style( $this->plugin_name, $inline_style );
		?>
		<div id="promptor-popup-container" class="promptor-popup-container position-<?php echo esc_attr( $position ); ?>">
			<div class="promptor-chat-window animation-<?php echo esc_attr( $animation ); ?>">
				<?php $this->render_chat_app_html( $container_id, $popup_source_context, $style_vars ); ?>
			</div>
			<button
				id="promptor-popup-toggle"
				class="promptor-trigger-button"
				aria-label="<?php echo esc_attr__( 'Toggle Chat Window', 'promptor' ); ?>"
				style="background-color: <?php echo esc_attr( isset( $popup_settings['primary_color'] ) ? $popup_settings['primary_color'] : '#15b6ff' ); ?>;"
			>
				<span class="promptor-icon-chat" aria-hidden="true">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" role="img" focusable="false"><path d="M0 0h24v24H0z" fill="none"/><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
				</span>
				<span class="promptor-icon-close" style="display:none;" aria-hidden="true">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" role="img" focusable="false"><path d="M0 0h24v24H0z" fill="none"/><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
				</span>
			</button>
		</div>
		<?php
	}

	/**
	 * Ortak chat HTML şablonu.
	 *
	 * @param string $container_id
	 * @param string $context_key
	 * @param string $style_vars
	 */
	private function render_chat_app_html( $container_id, $context_key, $style_vars = '' ) {
		// Critical inline styles to prevent theme conflicts (especially Neve theme)
		// These layout properties must be inline to override any theme CSS
		$critical_inline = 'display: flex !important; flex-direction: column !important; width: 100% !important; max-width: 100% !important; box-sizing: border-box !important;';
		if ( $style_vars ) {
			$critical_inline .= ' ' . $style_vars;
		}
		?>
		<div
			id="<?php echo esc_attr( $container_id ); ?>"
			class="promptor-app"
			data-context-key="<?php echo esc_attr( $context_key ); ?>"
			style="<?php echo esc_attr( $critical_inline ); ?>"
		>
			<div class="promptor-header">
				<img src="" alt="<?php echo esc_attr__( 'Bot Avatar', 'promptor' ); ?>" class="promptor-header-avatar" />
				<div class="promptor-header-text">
					<div class="promptor-header-title"></div>
					<div class="promptor-header-subtitle"></div>
				</div>
				<button
					type="button"
					class="promptor-new-conversation-btn"
					title="<?php echo esc_attr__( 'Start New Conversation', 'promptor' ); ?>"
					aria-label="<?php echo esc_attr__( 'Clear chat history and start over', 'promptor' ); ?>"
				>
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" focusable="false">
						<path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0118.8-4.3M22 12.5a10 10 0 01-18.8 4.2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</button>
			</div>

			<div class="promptor-chat-log"></div>

			<form class="promptor-search-form">
				<label class="screen-reader-text" for="<?php echo esc_attr( $container_id ); ?>-input">
					<?php echo esc_html__( 'Ask a question', 'promptor' ); ?>
				</label>
				<input
					id="<?php echo esc_attr( $container_id ); ?>-input"
					type="text"
					class="promptor-query-input"
					placeholder=""
					required
					autocomplete="off"
				/>
				<button type="submit" class="promptor-ask-btn" aria-label="<?php echo esc_attr__( 'Ask', 'promptor' ); ?>">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" focusable="false">
						<path d="M4.49998 12.0001L19.5 5.25006L13.05 11.2501L19.5 18.7501L4.49998 12.0001Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</button>
			</form>

			<?php if ( ! ( function_exists( 'promptor_is_pro' ) && promptor_is_pro() ) ) : ?>
				<div class="promptor-branding">
					<a href="<?php echo esc_url( 'https://promptorai.com' ); ?>" target="_blank" rel="nofollow noopener noreferrer">
						<?php esc_html_e( 'Powered by Promptor', 'promptor' ); ?>
					</a>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}