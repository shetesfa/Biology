<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Promptor_Settings_Tab_Api {

    public function register_settings() {
        register_setting('promptor_api_options_group', 'promptor_api_settings', array($this, 'sanitize_api_settings'));
    }

public function render() {
    // Trial status card (v1.4.0)
    $this->render_trial_status_card();
    ?>
    <form method="post" action="options.php">
        <?php settings_fields('promptor_api_options_group'); ?>

        <div class="postbox">
            <h2 class="hndle"><span class="dashicons dashicons-admin-network promptor-hndle-icon"></span><?php esc_html_e('Core API Configuration', 'promptor'); ?></h2>
            <div class="inside">
                <p><?php esc_html_e("Enter your OpenAI API credentials. This is the minimum required setup.", 'promptor'); ?></p>
                <table class="form-table">
                    <?php
                    $this->render_api_key_field();
                    $this->render_model_selection_field();
                    ?>
                </table>
            </div>
        </div>

        <div class="postbox">
            <h2 class="hndle"><span class="dashicons dashicons-admin-tools promptor-hndle-icon"></span><?php esc_html_e('Model Behavior', 'promptor'); ?></h2>
            <div class="inside">
                <p><?php esc_html_e("Control how the AI generates responses, its personality, creativity, and length.", 'promptor'); ?></p>

                <div class="promptor-grid-2-col">

                    <div class="promptor-col">
                        <table class="form-table">
                            <?php $this->render_slider_field_row('temperature', __('Temperature', 'promptor'), array('default' => 0.5, 'min' => 0, 'max' => 2, 'step' => 0.1, 'desc' => __('Controls randomness. Lower values make responses more focused. Higher values make them more creative.', 'promptor'))); ?>
                            <?php $this->render_text_field_row('max_tokens', __('Maximum Tokens', 'promptor'), array('default' => 1024, 'type' => 'number', 'desc' => __('The maximum number of tokens for the AI response. Helps control costs.', 'promptor'))); ?>
                        </table>
                    </div>

                    <div class="promptor-col">
                        <table class="form-table">
                            <?php $this->render_behavior_fields(); ?>
                        </table>
                    </div>

                </div>

                <?php $this->render_behavior_preview(); ?>
                <?php $this->render_kb_overrides(); ?>
                <?php $this->render_advanced_prompt_section(); ?>
            </div>
        </div>
	<div class="promptor-grid-2-col" style="margin-bottom:20px;">
        <div class="postbox">
            <h2 class="hndle"><span class="dashicons dashicons-search promptor-hndle-icon"></span><?php esc_html_e('Semantic Search Engine', 'promptor'); ?></h2>
            <div class="inside">
                 <p><strong><?php esc_html_e("This is the core engine of your AI assistant. Configure how accurately the AI understands the user's intent.", 'promptor'); ?></strong></p>
                <table class="form-table">
                    <?php
                    $similarity_desc = __(
                        "Determines how semantically similar a content chunk must be to the user's query to be included in the AI's context. A higher value (e.g., 0.75) is stricter and more accurate, but may find fewer results. A lower value (e.g., 0.65) is more lenient and finds more results, but they might be less relevant.",
                        'promptor'
                    );
                    $this->render_slider_field_row('similarity_threshold', __('Similarity Threshold', 'promptor'), array(
                        'default' => 0.7,
                        'min' => 0.5,
                        'max' => 0.9,
                        'step' => 0.01,
                        'desc' => $similarity_desc
                    ));
                    ?>
                </table>
            </div>
        </div>
        <div class="postbox">
            <h2 class="hndle"><span class="dashicons dashicons-format-chat promptor-hndle-icon"></span><?php esc_html_e('Conversation Management', 'promptor'); ?></h2>
            <div class="inside">
                <p><?php esc_html_e("Manage long conversations to optimize performance and cost.", 'promptor'); ?></p>
                <table class="form-table">
                    <?php $this->render_checkbox_field_row('enable_summarization', __('Enable Conversation Summarization', 'promptor'), array('desc' => __('Automatically summarize long conversations to save tokens and stay within context limits.', 'promptor'))); ?>
                    <?php $this->render_text_field_row('summarization_threshold', __('Summarization Threshold', 'promptor'), array('default' => 6, 'type' => 'number', 'desc' => __('Summarize the conversation after this many messages (user + AI).', 'promptor'))); ?>
                    <?php $this->render_textarea_field_row('summarization_prompt', __('Summarization Prompt', 'promptor'), array('default' => 'Briefly summarize the key points of the preceding conversation.', 'desc' => __('The system prompt used to ask the AI to create a summary.', 'promptor'))); ?>
                </table>
            </div>
        </div>
</div>
        <?php submit_button(); ?>
    </form>
    <?php
}

    /**
     * Render persona and tone dropdown fields.
     *
     * @since 1.3.0
     */
    private function render_behavior_fields() {
        $options       = get_option( 'promptor_api_settings', array() );
        $persona       = $options['persona'] ?? 'sales_assistant';
        $tone          = $options['tone'] ?? 'professional';
        $personas      = class_exists( 'Promptor_Behavior' ) ? Promptor_Behavior::get_personas() : array();
        $tones         = class_exists( 'Promptor_Behavior' ) ? Promptor_Behavior::get_tones() : array();
        $is_pro        = class_exists( 'Promptor_Behavior' ) ? Promptor_Behavior::is_pro() : false;
        $allowed_tones = class_exists( 'Promptor_Behavior' ) ? Promptor_Behavior::get_allowed_tones( $is_pro ) : array_keys( $tones );

        // If saved tone is not allowed in current tier, fall back for display
        if ( ! in_array( $tone, $allowed_tones, true ) ) {
            $tone = 'professional';
        }
        ?>
        <tr>
            <th scope="row">
                <label for="promptor_persona" class="promptor-field-label">
                    <span class="dashicons dashicons-businessperson promptor-field-icon"></span>
                    <?php esc_html_e( 'Persona', 'promptor' ); ?>
                </label>
            </th>
            <td>
                <select id="promptor_persona" name="promptor_api_settings[persona]" class="promptor-select-full">
                    <?php foreach ( $personas as $key => $data ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $persona, $key ); ?>><?php echo esc_html( $data['label'] ); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e( 'The role your AI assistant adopts.', 'promptor' ); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="promptor_tone" class="promptor-field-label">
                    <span class="dashicons dashicons-format-status promptor-field-icon"></span>
                    <?php esc_html_e( 'Tone', 'promptor' ); ?>
                </label>
            </th>
            <td>
                <select id="promptor_tone" name="promptor_api_settings[tone]" class="promptor-select-full">
                    <?php foreach ( $tones as $key => $data ) :
                        $is_allowed = in_array( $key, $allowed_tones, true );
                        $label      = $data['label'];
                        if ( ! $is_allowed ) {
                            /* translators: %s: tone label */
                            $label = sprintf( __( '%s (Pro)', 'promptor' ), $data['label'] );
                        }
                    ?>
                        <option value="<?php echo esc_attr( $key ); ?>"
                            <?php selected( $tone, $key ); ?>
                            <?php if ( ! $is_allowed ) : ?>
                                disabled="disabled"
                                class="promptor-tone-pro-only"
                            <?php endif; ?>
                        ><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e( 'The communication style used in responses.', 'promptor' ); ?></p>
            </td>
        </tr>
        <?php
    }

    /**
     * Render behavior preview section.
     *
     * @since 1.3.0
     */
    private function render_behavior_preview() {
        $options              = get_option( 'promptor_api_settings', array() );
        $persona              = $options['persona'] ?? 'sales_assistant';
        $tone                 = $options['tone'] ?? 'professional';
        $custom_prompt_active = ! empty( $options['enable_custom_prompt'] ) && ! empty( $options['system_prompt'] );
        $is_pro               = class_exists( 'Promptor_Behavior' ) ? Promptor_Behavior::is_pro() : false;
        $allowed_tones        = class_exists( 'Promptor_Behavior' ) ? Promptor_Behavior::get_allowed_tones( $is_pro ) : array();

        // Tier-aware tone fallback for preview
        if ( class_exists( 'Promptor_Behavior' ) ) {
            $tone = Promptor_Behavior::validate_tone( $tone );
        }

        $preview_text = class_exists( 'Promptor_Behavior' )
            ? Promptor_Behavior::generate_preview( $persona, $tone, $custom_prompt_active )
            : '';

        // Pass persona/tone data to JS for dynamic preview
        $personas_js = array();
        $tones_js    = array();
        if ( class_exists( 'Promptor_Behavior' ) ) {
            foreach ( Promptor_Behavior::get_personas() as $key => $data ) {
                $personas_js[ $key ] = array(
                    'label'     => $data['label'],
                    'prompt'    => $data['prompt'],
                    'behaviors' => isset( $data['behaviors'] ) ? $data['behaviors'] : array(),
                );
            }
            foreach ( Promptor_Behavior::get_tones() as $key => $data ) {
                $tones_js[ $key ] = array(
                    'label'  => $data['label'],
                    'prompt' => $data['prompt'],
                    'tier'   => isset( $data['tier'] ) ? $data['tier'] : 'free',
                );
            }
        }

        $personas_data = class_exists( 'Promptor_Behavior' ) ? Promptor_Behavior::get_personas() : array();
        $tones_data    = class_exists( 'Promptor_Behavior' ) ? Promptor_Behavior::get_tones() : array();
        $persona_label = isset( $personas_data[ $persona ] ) ? $personas_data[ $persona ]['label'] : $persona;
        $tone_label    = isset( $tones_data[ $tone ] ) ? $tones_data[ $tone ]['label'] : $tone;
        $behaviors     = isset( $personas_data[ $persona ]['behaviors'] ) ? $personas_data[ $persona ]['behaviors'] : array();
        ?>
        <div class="promptor-behavior-preview" style="margin-top:16px;">
            <h4 class="promptor-preview-title">
                <span class="dashicons dashicons-visibility promptor-field-icon"></span>
                <?php esc_html_e( 'AI Behavior Preview', 'promptor' ); ?>
            </h4>
            <div class="promptor-preview-card <?php echo $custom_prompt_active ? 'promptor-preview-card--manual' : ''; ?>">
                <div class="promptor-preview-meta" id="promptor-behavior-preview-meta">
                    <?php if ( $custom_prompt_active ) : ?>
                        <span class="promptor-preview-badge promptor-preview-badge--manual">
                            <span class="dashicons dashicons-editor-code"></span>
                            <?php esc_html_e( 'Manual system prompt is active', 'promptor' ); ?>
                        </span>
                    <?php else : ?>
                        <span class="promptor-preview-badge">
                            <span class="dashicons dashicons-businessperson"></span>
                            <?php
                            /* translators: %s: persona label */
                            printf( esc_html__( 'Persona: %s', 'promptor' ), esc_html( $persona_label ) );
                            ?>
                        </span>
                        <span class="promptor-preview-separator">&middot;</span>
                        <span class="promptor-preview-badge">
                            <span class="dashicons dashicons-format-status"></span>
                            <?php
                            /* translators: %s: tone label */
                            printf( esc_html__( 'Tone: %s', 'promptor' ), esc_html( $tone_label ) );
                            ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div id="promptor-behavior-preview-box" class="promptor-preview-box">
                    <?php if ( $custom_prompt_active ) : ?>
                        <?php echo esc_html( $preview_text ); ?>
                    <?php else : ?>
                        <span class="promptor-preview-behavior-label">
                            <span class="dashicons dashicons-lightbulb"></span>
                            <?php esc_html_e( 'Behavior', 'promptor' ); ?>
                        </span>
                        <ul class="promptor-preview-behavior-list" id="promptor-behavior-bullets">
                            <?php foreach ( $behaviors as $behavior ) : ?>
                                <li><?php echo esc_html( $behavior ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <span class="promptor-preview-behavior-label promptor-preview-style-label">
                            <span class="dashicons dashicons-format-status"></span>
                            <?php esc_html_e( 'Style', 'promptor' ); ?>
                        </span>
                        <ul class="promptor-preview-behavior-list" id="promptor-style-bullets"></ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <script type="text/javascript">
            window.promptorBehaviorData = {
                personas: <?php echo wp_json_encode( $personas_js ); ?>,
                tones: <?php echo wp_json_encode( $tones_js ); ?>,
                isPro: <?php echo wp_json_encode( $is_pro ); ?>,
                allowedTones: <?php echo wp_json_encode( array_values( $allowed_tones ) ); ?>,
                toneStyles: <?php echo wp_json_encode( class_exists( 'Promptor_Behavior' ) ? Promptor_Behavior::get_tone_styles() : array() ); ?>,
                i18n: {
                    personaLabel: <?php echo wp_json_encode( __( 'Persona', 'promptor' ) ); ?>,
                    toneLabel: <?php echo wp_json_encode( __( 'Tone', 'promptor' ) ); ?>,
                    manualPromptActive: <?php echo wp_json_encode( __( 'Manual system prompt is active. The AI will use your custom prompt instead of the generated behavior.', 'promptor' ) ); ?>,
                    manualBadge: <?php echo wp_json_encode( __( 'Manual system prompt is active', 'promptor' ) ); ?>,
                    personaMeta: <?php
						/* translators: %s: persona name */
						echo wp_json_encode( __( 'Persona: %s', 'promptor' ) );
					?>,
                    toneMeta: <?php
						/* translators: %s: tone name */
						echo wp_json_encode( __( 'Tone: %s', 'promptor' ) );
					?>,
                    usesGlobalSettings: <?php echo wp_json_encode( __( 'Uses global settings', 'promptor' ) ); ?>,
                    customBehavior: <?php echo wp_json_encode( __( 'Custom behavior', 'promptor' ) ); ?>,
                    behaviorLabel: <?php echo wp_json_encode( __( 'Behavior', 'promptor' ) ); ?>,
                    styleLabel: <?php echo wp_json_encode( __( 'Style', 'promptor' ) ); ?>
                }
            };
        </script>
        <?php
    }

    /**
     * Render KB-specific override controls.
     *
     * @since 1.3.0
     */
    private function render_kb_overrides() {
        $options       = get_option( 'promptor_api_settings', array() );
        $contexts      = get_option( 'promptor_contexts', array() );
        $kb_overrides  = $options['kb_overrides'] ?? array();
        $personas      = class_exists( 'Promptor_Behavior' ) ? Promptor_Behavior::get_personas() : array();
        $tones         = class_exists( 'Promptor_Behavior' ) ? Promptor_Behavior::get_tones() : array();
        $is_pro        = class_exists( 'Promptor_Behavior' ) ? Promptor_Behavior::is_pro() : false;
        $allowed_tones = class_exists( 'Promptor_Behavior' ) ? Promptor_Behavior::get_allowed_tones( $is_pro ) : array_keys( $tones );

        if ( empty( $contexts ) ) {
            return;
        }
        ?>
        <div class="promptor-kb-overrides">
            <div class="promptor-collapsible-header" id="promptor-kb-overrides-toggle">
                <span class="dashicons dashicons-arrow-right-alt2 promptor-collapse-icon"></span>
                <span class="dashicons dashicons-database promptor-section-icon"></span>
                <strong><?php esc_html_e( 'Knowledge Base Overrides', 'promptor' ); ?></strong>
            </div>
            <div id="promptor-kb-overrides-content" class="promptor-collapsible-content" style="display:none;">
                <p class="description" style="margin:0 0 12px;">
                    <?php esc_html_e( 'Assign different persona and tone settings to individual knowledge bases.', 'promptor' ); ?>
                </p>
                <div class="promptor-kb-override-list">
                    <?php foreach ( $contexts as $ctx_key => $ctx_data ) :
                        $ctx_name    = ! empty( $ctx_data['name'] ) ? $ctx_data['name'] : $ctx_key;
                        $override    = $kb_overrides[ $ctx_key ] ?? array();
                        $is_enabled  = ! empty( $override['enabled'] );
                        $kb_persona  = $override['persona'] ?? 'sales_assistant';
                        $kb_tone     = $override['tone'] ?? 'professional';
                    ?>
                    <div class="promptor-kb-override-row <?php echo $is_enabled ? 'promptor-kb-override-row--active' : ''; ?>">
                        <div class="promptor-kb-override-header">
                            <label class="promptor-kb-override-label">
                                <input type="checkbox"
                                       class="promptor-kb-override-toggle"
                                       name="promptor_api_settings[kb_overrides][<?php echo esc_attr( $ctx_key ); ?>][enabled]"
                                       value="1"
                                       <?php checked( $is_enabled ); ?>
                                       data-kb="<?php echo esc_attr( $ctx_key ); ?>" />
                                <span class="dashicons dashicons-book-alt promptor-kb-icon"></span>
                                <span class="promptor-kb-name"><?php echo esc_html( $ctx_name ); ?></span>
                            </label>
                            <span class="promptor-kb-status" data-kb="<?php echo esc_attr( $ctx_key ); ?>">
                                <?php if ( $is_enabled ) : ?>
                                    <?php esc_html_e( 'Custom behavior', 'promptor' ); ?>
                                <?php else : ?>
                                    <?php esc_html_e( 'Uses global settings', 'promptor' ); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="promptor-kb-override-fields" data-kb="<?php echo esc_attr( $ctx_key ); ?>" style="<?php echo $is_enabled ? '' : 'display:none;'; ?>">
                            <div class="promptor-kb-override-controls">
                                <div class="promptor-kb-control">
                                    <label class="promptor-kb-control-label">
                                        <span class="dashicons dashicons-businessperson promptor-field-icon"></span>
                                        <?php esc_html_e( 'Persona', 'promptor' ); ?>
                                    </label>
                                    <select name="promptor_api_settings[kb_overrides][<?php echo esc_attr( $ctx_key ); ?>][persona]" class="promptor-kb-persona-select">
                                        <?php foreach ( $personas as $key => $data ) : ?>
                                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $kb_persona, $key ); ?>><?php echo esc_html( $data['label'] ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="promptor-kb-control">
                                    <label class="promptor-kb-control-label">
                                        <span class="dashicons dashicons-format-status promptor-field-icon"></span>
                                        <?php esc_html_e( 'Tone', 'promptor' ); ?>
                                    </label>
                                    <select name="promptor_api_settings[kb_overrides][<?php echo esc_attr( $ctx_key ); ?>][tone]" class="promptor-kb-tone-select">
                                        <?php foreach ( $tones as $key => $data ) :
                                            $is_allowed = in_array( $key, $allowed_tones, true );
                                            $label      = $data['label'];
                                            if ( ! $is_allowed ) {
                                                /* translators: %s: tone label */
                                                $label = sprintf( __( '%s (Pro)', 'promptor' ), $data['label'] );
                                            }
                                        ?>
                                            <option value="<?php echo esc_attr( $key ); ?>"
                                                <?php selected( $kb_tone, $key ); ?>
                                                <?php if ( ! $is_allowed ) : ?>
                                                    disabled="disabled"
                                                    class="promptor-tone-pro-only"
                                                <?php endif; ?>
                                            ><?php echo esc_html( $label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the advanced manual system prompt section.
     * Converts the existing system prompt textarea into an advanced/collapsible mode.
     *
     * @since 1.3.0
     */
    private function render_advanced_prompt_section() {
        $options               = get_option( 'promptor_api_settings', array() );
        $enable_custom_prompt  = ! empty( $options['enable_custom_prompt'] );
        $default_system_prompt = "You are a professional, helpful, and friendly sales assistant for this website. Your main goal is to understand the user's needs and, based ONLY on the context provided to you, recommend the most relevant services or products from the context.\n\nKey instructions:\n- Always be polite and professional.\n- Your answers must be based strictly on the provided context. Do not use any external knowledge.\n- If the answer is not in the context, state that you don't have information on that topic.\n- Keep your explanations concise and to the point.";
        $system_prompt         = ! empty( $options['system_prompt'] ) ? $options['system_prompt'] : $default_system_prompt;
        ?>
        <div class="promptor-advanced-prompt">
            <div class="promptor-collapsible-header" id="promptor-advanced-prompt-toggle">
                <span class="dashicons dashicons-arrow-right-alt2 promptor-collapse-icon"></span>
                <span class="dashicons dashicons-editor-code promptor-section-icon"></span>
                <strong><?php esc_html_e( 'Advanced: Manual System Prompt', 'promptor' ); ?></strong>
            </div>
            <div id="promptor-advanced-prompt-content" class="promptor-collapsible-content" style="display:none;">
                <p class="description" style="margin:0 0 12px;">
                    <?php esc_html_e( 'When enabled, this custom prompt fully overrides persona and tone settings.', 'promptor' ); ?>
                </p>
                <label class="promptor-advanced-toggle-label">
                    <input type="checkbox"
                           id="promptor_enable_custom_prompt"
                           name="promptor_api_settings[enable_custom_prompt]"
                           value="1"
                           <?php checked( $enable_custom_prompt ); ?> />
                    <?php esc_html_e( 'Enable manual system prompt', 'promptor' ); ?>
                </label>
                <div id="promptor-custom-prompt-wrapper" class="promptor-custom-prompt-wrapper" style="<?php echo $enable_custom_prompt ? '' : 'display:none;'; ?>">
                    <textarea id="system_prompt"
                              name="promptor_api_settings[system_prompt]"
                              rows="5"
                              class="large-text"><?php echo esc_textarea( $system_prompt ); ?></textarea>
                    <p class="description">
                        <?php esc_html_e( 'Write the full system prompt. Leave empty to fall back to automatic behavior.', 'promptor' ); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    public function sanitize_api_settings($input) {
        $sanitized_input = array();
        $options = get_option('promptor_api_settings', array());
        $in = is_array($input) ? wp_unslash($input) : array();

        // 1) API Key (unslash + sanitize + format check)
        if ( isset( $in['api_key'] ) ) {
            $api_key = sanitize_text_field( $in['api_key'] );
            $api_key = trim( $api_key );

            if ( $api_key === '' ) {
                $sanitized_input['api_key'] = $options['api_key'] ?? '';
            } elseif ( preg_match( '/^(sk|gsk)[\-\w_]/i', $api_key ) ) {
                $sanitized_input['api_key'] = $api_key;
                if ( ( $options['api_key'] ?? '' ) !== $api_key ) {
                    add_settings_error( 'promptor_api_settings', 'api_key_valid', __( 'API Key saved.', 'promptor' ), 'success' );
                    // Convert trial when user switches to own API key (v1.4.0)
                    if ( class_exists( 'Promptor_Trial_Client' ) && Promptor_Trial_Client::has_trial() ) {
                        Promptor_Trial_Client::convert_trial();
                    }
                }
            } else {
                $sanitized_input['api_key'] = $options['api_key'] ?? '';
                add_settings_error( 'promptor_api_settings', 'invalid_api_key', __( 'The API key format looks unusual. Your previous key has been kept.', 'promptor' ), 'error' );
            }
        } else {
            $sanitized_input['api_key'] = $options['api_key'] ?? '';
        }

        // 2) Model (whitelist)
        $allowed_models = array('gpt-4o','gpt-4-turbo','gpt-3.5-turbo');
        $model = isset($in['model']) ? sanitize_key($in['model']) : ($options['model'] ?? 'gpt-4o');
        $sanitized_input['model'] = in_array($model, $allowed_models, true) ? $model : 'gpt-4o';

        // 3) Temperature (float clamp 0..2)
        if (isset($in['temperature'])) {
            $sanitized_input['temperature'] = max(0, min(2, (float) $in['temperature']));
        } else {
            $sanitized_input['temperature'] = $options['temperature'] ?? 0.5;
        }

        // 4) Max tokens (int clamp ≤ 8192)
        if (isset($in['max_tokens'])) {
            $sanitized_input['max_tokens'] = min(8192, absint($in['max_tokens']));
        } else {
            $sanitized_input['max_tokens'] = $options['max_tokens'] ?? 1024;
        }

        // 5) Persona (validated against known list) + Tone (tier-aware validation)
        if ( class_exists( 'Promptor_Behavior' ) ) {
            $sanitized_input['persona'] = Promptor_Behavior::validate_persona( $in['persona'] ?? ( $options['persona'] ?? 'sales_assistant' ) );
            $sanitized_input['tone']    = Promptor_Behavior::validate_tone( $in['tone'] ?? ( $options['tone'] ?? 'professional' ), true );
        } else {
            $sanitized_input['persona'] = $options['persona'] ?? 'sales_assistant';
            $sanitized_input['tone']    = $options['tone'] ?? 'professional';
        }

        // 6) Enable custom prompt (checkbox)
        $sanitized_input['enable_custom_prompt'] = ! empty( $in['enable_custom_prompt'] ) ? 1 : 0;

        // 7) System prompt (textarea sanitize) — preserved for backward compat & advanced mode
        $default_system_prompt = "You are a professional, helpful, and friendly sales assistant for this website. Your main goal is to understand the user's needs and, based ONLY on the context provided to you, recommend the most relevant services or products from the context.\n\nKey instructions:\n- Always be polite and professional.\n- Your answers must be based strictly on the provided context. Do not use any external knowledge.\n- If the answer is not in the context, state that you don't have information on that topic.\n- Keep your explanations concise and to the point.";
        if ( isset( $in['system_prompt'] ) ) {
            $sanitized = sanitize_textarea_field( $in['system_prompt'] );
            $sanitized_input['system_prompt'] = ! empty( $sanitized ) ? $sanitized : ( ! empty( $options['system_prompt'] ) ? $options['system_prompt'] : $default_system_prompt );
        } else {
            $sanitized_input['system_prompt'] = ! empty( $options['system_prompt'] ) ? $options['system_prompt'] : $default_system_prompt;
        }

        // 8) KB overrides (sanitize per-KB persona/tone)
        $sanitized_input['kb_overrides'] = array();
        if ( ! empty( $in['kb_overrides'] ) && is_array( $in['kb_overrides'] ) ) {
            $contexts = get_option( 'promptor_contexts', array() );
            foreach ( $in['kb_overrides'] as $ctx_key => $override ) {
                $ctx_key = sanitize_key( $ctx_key );
                if ( ! isset( $contexts[ $ctx_key ] ) ) {
                    continue; // skip overrides for non-existent KBs
                }
                $sanitized_override = array(
                    'enabled' => ! empty( $override['enabled'] ) ? 1 : 0,
                );
                if ( class_exists( 'Promptor_Behavior' ) ) {
                    $sanitized_override['persona'] = Promptor_Behavior::validate_persona( $override['persona'] ?? 'sales_assistant' );
                    $sanitized_override['tone']    = Promptor_Behavior::validate_tone( $override['tone'] ?? 'professional', true );
                } else {
                    $sanitized_override['persona'] = sanitize_key( $override['persona'] ?? 'sales_assistant' );
                    $sanitized_override['tone']    = sanitize_key( $override['tone'] ?? 'professional' );
                }
                $sanitized_input['kb_overrides'][ $ctx_key ] = $sanitized_override;
            }
        } else {
            // Preserve existing overrides if form section wasn't submitted
            $sanitized_input['kb_overrides'] = $options['kb_overrides'] ?? array();
        }

        // 9) Similarity threshold (float clamp 0.5..0.9)
        if (isset($in['similarity_threshold'])) {
            $sanitized_input['similarity_threshold'] = max(0.5, min(0.9, (float) $in['similarity_threshold']));
        } else {
            $sanitized_input['similarity_threshold'] = $options['similarity_threshold'] ?? 0.7;
        }

        // 10) Summarization on/off
        $sanitized_input['enable_summarization'] = !empty($in['enable_summarization']) ? 1 : 0;

        // 11) Summarization threshold (int min 2)
        if (isset($in['summarization_threshold'])) {
            $sanitized_input['summarization_threshold'] = max(2, absint($in['summarization_threshold']));
        } else {
            $sanitized_input['summarization_threshold'] = $options['summarization_threshold'] ?? 6;
        }

        // 12) Summarization prompt (textarea sanitize)
        $default_summarization_prompt = 'Briefly summarize the key points of the preceding conversation.';
        if ( isset( $in['summarization_prompt'] ) ) {
            $sanitized = sanitize_textarea_field( $in['summarization_prompt'] );
            $sanitized_input['summarization_prompt'] = ! empty( $sanitized ) ? $sanitized : ( ! empty( $options['summarization_prompt'] ) ? $options['summarization_prompt'] : $default_summarization_prompt );
        } else {
            $sanitized_input['summarization_prompt'] = ! empty( $options['summarization_prompt'] ) ? $options['summarization_prompt'] : $default_summarization_prompt;
        }

        return $sanitized_input;
    }

    private function render_api_key_field() {
        $options = get_option('promptor_api_settings', array());
        $value = $options['api_key'] ?? '';
        ?>
        <tr>
            <th scope="row"><label for="api_key"><?php esc_html_e('OpenAI API Key', 'promptor'); ?></label></th>
            <td>
                <div class="api-key-wrapper">
                    <input type="password" id="api_key" name="promptor_api_settings[api_key]" value="<?php echo esc_attr( $value ); ?>" class="regular-text"
       autocomplete="off" spellcheck="false" autocapitalize="off" />
                    <button type="button" id="promptor-verify-api-key-btn" class="button button-secondary"><?php esc_html_e('Verify Key', 'promptor'); ?></button>
                    <span class="spinner"></span>
                </div>
                <p class="description" id="api-key-status"></p>
            </td>
        </tr>
        <?php
    }

    private function render_model_selection_field() {
        $options = get_option('promptor_api_settings', array());
        $selected_model = $options['model'] ?? 'gpt-4o';
        $models = array(
            'gpt-4o'        => esc_html__( 'GPT-4o (Recommended)', 'promptor' ),
            'gpt-4-turbo'   => esc_html__( 'GPT-4 Turbo', 'promptor' ),
            'gpt-3.5-turbo' => esc_html__( 'GPT-3.5 Turbo', 'promptor' ),
        );

        ?>
        <tr>
            <th scope="row"><label for="model"><?php esc_html_e('GPT Model', 'promptor'); ?></label></th>
            <td>
                <select id="model" name="promptor_api_settings[model]">
                    <?php foreach ($models as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($selected_model, $value); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e('GPT-4o is recommended for its balance of cost, speed, and intelligence.','promptor');?></p>
            </td>
        </tr>
        <?php
    }

    /**
     * Render trial status card above API settings (v1.4.0).
     */
    private function render_trial_status_card() {
        if ( ! class_exists( 'Promptor_Trial_Client' ) ) {
            return;
        }

        $trial_data = Promptor_Trial_Client::get_trial_data();
        $api_settings = get_option( 'promptor_api_settings', array() );
        $has_api_key = ! empty( $api_settings['api_key'] );

        // Show trial card only if trial exists (any status) and no API key
        if ( empty( $trial_data['token'] ) && ! $has_api_key ) {
            // Show activate trial prompt
            ?>
            <div class="postbox promptor-trial-card">
                <h2 class="hndle"><span class="dashicons dashicons-controls-play promptor-hndle-icon"></span><?php esc_html_e( 'Free Trial', 'promptor' ); ?></h2>
                <div class="inside">
                    <p><?php esc_html_e( 'Try Promptor without an API key! Get 10 free AI queries to test the plugin.', 'promptor' ); ?></p>
                    <button type="button" id="promptor-activate-trial-btn" class="button button-primary">
                        <?php esc_html_e( 'Start Free Trial', 'promptor' ); ?>
                    </button>
                    <span class="spinner" id="promptor-trial-spinner"></span>
                    <p class="description" id="promptor-trial-activate-status"></p>
                </div>
            </div>
            <script>
            jQuery(document).ready(function($) {
                $('#promptor-activate-trial-btn').on('click', function() {
                    var $btn = $(this), $spinner = $('#promptor-trial-spinner'), $status = $('#promptor-trial-activate-status');
                    $btn.prop('disabled', true);
                    $spinner.addClass('is-active');
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: { action: 'promptor_trial_activate', nonce: '<?php echo esc_js( wp_create_nonce( 'promptor_trial_activate_nonce' ) ); ?>' },
                        success: function(r) {
                            if (r.success) { location.reload(); }
                            else { $status.html('<span style="color:#d63638;">' + (r.data.message || '<?php esc_html_e( 'Activation failed.', 'promptor' ); ?>') + '</span>'); }
                        },
                        error: function() { $status.html('<span style="color:#d63638;"><?php esc_html_e( 'Connection error.', 'promptor' ); ?></span>'); },
                        complete: function() { $btn.prop('disabled', false); $spinner.removeClass('is-active'); }
                    });
                });
            });
            </script>
            <?php
            return;
        }

        if ( empty( $trial_data['token'] ) ) {
            return; // Has API key, no trial — nothing to show
        }

        // Hide trial card when converted and user has API key
        if ( 'converted' === ( $trial_data['status'] ?? '' ) && $has_api_key ) {
            return;
        }

        $status = $trial_data['status'] ?? 'unknown';
        $remaining = (int) ( $trial_data['remaining_queries'] ?? 0 );
        $expires_at = (int) ( $trial_data['expires_at'] ?? 0 );
        $is_active = Promptor_Trial_Client::is_trial_active();

        $status_labels = array(
            'active'    => __( 'Active', 'promptor' ),
            'exhausted' => __( 'Queries Used Up', 'promptor' ),
            'expired'   => __( 'Expired', 'promptor' ),
            'converted' => __( 'Converted to API Key', 'promptor' ),
            'revoked'   => __( 'Revoked', 'promptor' ),
        );
        $status_label = $status_labels[ $status ] ?? ucfirst( $status );

        $status_colors = array(
            'active'    => '#00a32a',
            'exhausted' => '#dba617',
            'expired'   => '#d63638',
            'converted' => '#2271b1',
            'revoked'   => '#d63638',
        );
        $status_color = $status_colors[ $status ] ?? '#646970';
        ?>
        <div class="postbox promptor-trial-card">
            <h2 class="hndle"><span class="dashicons dashicons-controls-play promptor-hndle-icon"></span><?php esc_html_e( 'Free Trial Status', 'promptor' ); ?></h2>
            <div class="inside">
                <table class="form-table promptor-trial-status-table">
                    <tr>
                        <th><?php esc_html_e( 'Status', 'promptor' ); ?></th>
                        <td><strong style="color: <?php echo esc_attr( $status_color ); ?>;"><?php echo esc_html( $status_label ); ?></strong></td>
                    </tr>
                    <?php if ( $is_active ) : ?>
                    <tr>
                        <th><?php esc_html_e( 'Remaining Queries', 'promptor' ); ?></th>
                        <td><strong><?php echo esc_html( $remaining ); ?></strong> / 10</td>
                    </tr>
                    <?php endif; ?>
                    <?php if ( $expires_at > 0 ) : ?>
                    <tr>
                        <th><?php esc_html_e( 'Expires', 'promptor' ); ?></th>
                        <td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $expires_at ) ); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
                <?php if ( ! $is_active && ! $has_api_key ) : ?>
                    <p class="description" style="margin-top: 10px; color: #d63638;">
                        <span class="dashicons dashicons-warning" style="font-size: 16px; width: 16px; height: 16px;"></span>
                        <?php esc_html_e( 'Enter your OpenAI API key below to continue using Promptor.', 'promptor' ); ?>
                    </p>
                <?php elseif ( $has_api_key ) : ?>
                    <p class="description" style="margin-top: 10px; color: #00a32a;">
                        <span class="dashicons dashicons-yes-alt" style="font-size: 16px; width: 16px; height: 16px;"></span>
                        <?php esc_html_e( 'You are using your own API key. Trial is no longer needed.', 'promptor' ); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function render_text_field_row($id, $title, $args) {
        $options = get_option('promptor_api_settings', array());
        $value = $options[$id] ?? $args['default'] ?? '';
        $type = $args['type'] ?? 'text';
        $class = $type === 'number' ? 'small-text' : 'regular-text';
        ?>
        <tr>
            <th scope="row"><label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($title); ?></label></th>
            <td>
                <input type="<?php echo esc_attr($type); ?>" id="<?php echo esc_attr($id); ?>" name="promptor_api_settings[<?php echo esc_attr($id); ?>]" value="<?php echo esc_attr($value); ?>" class="<?php echo esc_attr($class); ?>" />
                <?php if (isset($args['desc'])) { echo '<p class="description">' . esc_html($args['desc']) . '</p>'; } ?>
            </td>
        </tr>
        <?php
    }

    private function render_textarea_field_row($id, $title, $args) {
        $options = get_option('promptor_api_settings', array());
        $value = ! empty( $options[ $id ] ) ? $options[ $id ] : ( $args['default'] ?? '' );
         ?>
        <tr>
            <th scope="row"><label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($title); ?></label></th>
            <td>
                <textarea id="<?php echo esc_attr($id); ?>" name="promptor_api_settings[<?php echo esc_attr($id); ?>]" rows="5" class="large-text"><?php echo esc_textarea($value); ?></textarea>
                <?php if (isset($args['desc'])) { echo '<p class="description">' . esc_html($args['desc']) . '</p>'; } ?>
            </td>
        </tr>
        <?php
    }

    private function render_slider_field_row($id, $title, $args) {
        $options = get_option('promptor_api_settings', array());
        $value = $options[$id] ?? $args['default'] ?? 0.5;
        ?>
        <tr>
            <th scope="row"><label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($title); ?></label></th>
            <td>
                <div class="promptor-slider-container">
                    <input type="range" class="promptor-slider-input" id="<?php echo esc_attr($id); ?>" name="promptor_api_settings[<?php echo esc_attr($id); ?>]"
                           value="<?php echo esc_attr($value); ?>"
                           min="<?php echo esc_attr($args['min']); ?>"
                           max="<?php echo esc_attr($args['max']); ?>"
                           step="<?php echo esc_attr($args['step']); ?>">
                    <span class="promptor-slider-value"><?php echo esc_html($value); ?></span>
                </div>
                <?php if ( isset( $args['desc'] ) ) { echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>'; } ?>
            </td>
        </tr>
        <?php
    }

    private function render_checkbox_field_row($id, $title, $args) {
        $options = get_option('promptor_api_settings', array());
        $checked = !empty($options[$id]);
        ?>
         <tr>
            <th scope="row"><?php echo esc_html($title); ?></th>
            <td>
                <label>
                    <input type="checkbox" id="<?php echo esc_attr($id); ?>" name="promptor_api_settings[<?php echo esc_attr($id); ?>]" value="1" <?php checked($checked, true); ?>>
                    <?php if (isset($args['desc'])) { echo ' ' . esc_html($args['desc']); } ?>
                </label>
            </td>
        </tr>
        <?php
    }
}
