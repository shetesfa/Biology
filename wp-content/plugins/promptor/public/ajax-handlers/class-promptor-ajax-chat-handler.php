<?php
/**
 * Promptor AJAX Chat Handler
 *
 * Handles AI-powered chat interactions with OpenAI API
 *
 * @package Promptor
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Promptor_Ajax_Chat_Handler
 *
 * Manages AJAX requests for AI chat functionality
 */
class Promptor_Ajax_Chat_Handler {

    /**
     * Cache group name
     *
     * @var string
     */
    private const CACHE_GROUP = 'promptor';

    /**
     * Maximum message history length
     *
     * @var int
     */
    private const MAX_HISTORY_LENGTH = 20;

    /**
     * Maximum content length per message
     *
     * @var int
     */
    private const MAX_MESSAGE_LENGTH = 4000;

    /**
     * Maximum query length
     *
     * @var int
     */
    private const MAX_QUERY_LENGTH = 4000;

    /**
     * Allowed OpenAI models
     *
     * @var array
     */
    private const ALLOWED_MODELS = array( 'gpt-4o', 'gpt-4-turbo', 'gpt-3.5-turbo' );

    /**
     * OpenAI API pricing per million tokens
     *
     * @var array
     */
    private const PRICING = array(
        'gpt-4o'        => array( 'input' => 5.00, 'output' => 15.00 ),
        'gpt-4-turbo'   => array( 'input' => 10.00, 'output' => 30.00 ),
        'gpt-3.5-turbo' => array( 'input' => 0.50, 'output' => 1.50 ),
    );

    /**
     * Language mapping
     *
     * @var array
     */
    private const LANGUAGE_MAP = array(
        'tr_TR' => 'Turkish',
        'en_US' => 'English',
        'de_DE' => 'German',
        'fr_FR' => 'French',
        'es_ES' => 'Spanish',
        'it_IT' => 'Italian',
    );

    /**
     * Convert language name to ISO 639-1 code
     *
     * @param string $language_name Full language name (e.g., "Turkish", "English").
     * @return string ISO 639-1 language code (e.g., "tr", "en").
     */
    private function get_language_code( string $language_name ): string {
        $language_codes = array(
            'Turkish'  => 'tr',
            'English'  => 'en',
            'German'   => 'de',
            'French'   => 'fr',
            'Spanish'  => 'es',
            'Italian'  => 'it',
            'Portuguese' => 'pt',
            'Dutch'    => 'nl',
            'Russian'  => 'ru',
            'Chinese'  => 'zh',
            'Japanese' => 'ja',
            'Korean'   => 'ko',
            'Arabic'   => 'ar',
        );

        $normalized = ucfirst( strtolower( trim( $language_name ) ) );
        return $language_codes[ $normalized ] ?? 'en';
    }

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'wp_ajax_promptor_get_ai_suggestion', array( $this, 'handle_ai_query' ) );
        add_action( 'wp_ajax_nopriv_promptor_get_ai_suggestion', array( $this, 'handle_ai_query' ) );
        add_action( 'wp_ajax_promptor_save_feedback', array( $this, 'handle_save_feedback' ) );
        add_action( 'wp_ajax_nopriv_promptor_save_feedback', array( $this, 'handle_save_feedback' ) );
    }

    /**
     * Calculate API cost based on token usage
     *
     * @param string $model Model name.
     * @param int    $prompt_tokens Number of prompt tokens.
     * @param int    $completion_tokens Number of completion tokens.
     * @return float|null Cost in USD or null if model not found.
     */
    private function calculate_cost( string $model, int $prompt_tokens, int $completion_tokens ): ?float {
        if ( ! isset( self::PRICING[ $model ] ) ) {
            return null;
        }

        $pricing = self::PRICING[ $model ];
        $input_cost = ( $prompt_tokens / 1000000 ) * $pricing['input'];
        $output_cost = ( $completion_tokens / 1000000 ) * $pricing['output'];

        return $input_cost + $output_cost;
    }

    /**
     * Get embedding vector from OpenAI
     *
     * @param string $text Text to embed.
     * @param string $api_key OpenAI API key.
     * @return array|WP_Error Embedding vector or error.
     */
    private function get_embedding( string $text, string $api_key ) {
        if ( empty( $text ) || empty( $api_key ) ) {
            return new WP_Error( 'invalid_params', __( 'Invalid parameters for embedding.', 'promptor' ) );
        }

        $cache_key = 'promptor_emb_' . md5( $text );
        $cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
        
        if ( false !== $cached && is_array( $cached ) ) {
            return $cached;
        }

        $args = array(
            'headers'     => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'        => wp_json_encode( array(
                'input' => $text,
                'model' => 'text-embedding-3-small',
            ) ),
            'timeout'     => 45,
            'httpversion' => '1.1',
            'sslverify'   => true,
        );

        $response = wp_remote_post( 'https://api.openai.com/v1/embeddings', $args );

        if ( is_wp_error( $response ) ) {
            $this->log_error( 'Embedding API request failed: ' . $response->get_error_message() );
            return new WP_Error( 'embedding_request_failed', __( 'Failed to connect to embedding service.', 'promptor' ) );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $status_code ) {
            $error_body = wp_remote_retrieve_body( $response );
            $this->log_error( sprintf( 'Embedding API error (%d): %s', $status_code, $error_body ) );
            return new WP_Error( 'embedding_api_error', __( 'Embedding service returned an error.', 'promptor' ) );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( ! isset( $body['data'][0]['embedding'] ) || ! is_array( $body['data'][0]['embedding'] ) ) {
            $this->log_error( 'Invalid embedding response structure' );
            return new WP_Error( 'invalid_embedding_response', __( 'Invalid response from embedding API.', 'promptor' ) );
        }

        $embedding = $body['data'][0]['embedding'];
        wp_cache_set( $cache_key, $embedding, self::CACHE_GROUP, HOUR_IN_SECONDS );

        return $embedding;
    }

    /**
     * Calculate cosine similarity between two vectors
     *
     * @param array $vec_a First vector.
     * @param array $vec_b Second vector.
     * @return float Similarity score (0-1).
     */
    private function cosine_similarity( array $vec_a, array $vec_b ): float {
        $length = min( count( $vec_a ), count( $vec_b ) );

        if ( 0 === $length ) {
            return 0.0;
        }

        $dot_product = 0.0;
        $norm_a = 0.0;
        $norm_b = 0.0;

        for ( $i = 0; $i < $length; $i++ ) {
            $a = $vec_a[ $i ] ?? 0;
            $b = $vec_b[ $i ] ?? 0;
            
            $dot_product += $a * $b;
            $norm_a += $a * $a;
            $norm_b += $b * $b;
        }

        $denominator = sqrt( $norm_a ) * sqrt( $norm_b );
        
        return ( 0 == $denominator ) ? 0.0 : ( $dot_product / $denominator );
    }

    /**
     * Generate client fingerprint for rate limiting
     *
     * @return string MD5 hash of client identifiers.
     */
    private function get_client_fingerprint(): string {
        $ip_address = $this->get_client_ip();
        $user_agent = $this->get_user_agent();
        $user_id = is_user_logged_in() ? get_current_user_id() : 0;

        return md5( sprintf( '%s|%s|%d', $ip_address, $user_agent, $user_id ) );
    }

    /**
     * Get sanitized client IP address
     *
     * @return string IP address.
     */
    private function get_client_ip(): string {
        $ip = '0.0.0.0';

        if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }

        // Validate IP format
        if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            $ip = '0.0.0.0';
        }

        return $ip;
    }

    /**
     * Get sanitized user agent
     *
     * @return string User agent string.
     */
    private function get_user_agent(): string {
        if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
            return 'unknown';
        }

        $user_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
        return substr( $user_agent, 0, 190 );
    }

    /**
     * Rate limiting guard
     *
     * @param string $bucket Rate limit bucket name.
     * @param int    $limit Maximum requests allowed.
     * @param int    $window Time window in seconds.
     * @return void Sends JSON error and exits if limit exceeded.
     */
    private function rate_limit_guard( string $bucket, int $limit, int $window ): void {
        $fingerprint = $this->get_client_fingerprint();
        $key = 'promptor_rl_' . md5( $bucket . '|' . $fingerprint );
        $now = time();
        
        $payload = get_transient( $key );

        if ( ! is_array( $payload ) || empty( $payload['reset_at'] ) || $payload['reset_at'] < $now ) {
            $payload = array(
                'count'    => 1,
                'reset_at' => $now + $window,
            );
            set_transient( $key, $payload, $window );
            return;
        }

        if ( $payload['count'] >= $limit ) {
            $retry = max( 1, $payload['reset_at'] - $now );
            
            Promptor_Error::send(
                'rate_limited',
                sprintf(
                    /* translators: %d: seconds to wait */
                    __( 'Too many requests. Please wait %d seconds and try again.', 'promptor' ),
                    $retry
                ),
                array(
                    'retry_in'     => $retry,
                    'rate_limited' => true,
                )
            );
        }

        $payload['count']++;
        set_transient( $key, $payload, $payload['reset_at'] - $now );
    }

    /**
     * Handle feedback submission
     *
     * @return void
     */
    public function handle_save_feedback(): void {
        // Tek noktada request al
        $request = isset( $_POST ) ? (array) wp_unslash( $_POST ) : array();

        // Nonce: 'nonce' gelmezse '_ajax_nonce' veya 'security' de kabul et
        $nonce = sanitize_text_field( $request['nonce'] ?? ( $request['_ajax_nonce'] ?? ( $request['security'] ?? '' ) ) );
        if ( ! wp_verify_nonce( $nonce, 'promptor_feedback_nonce' ) ) {
            Promptor_Error::send( 'nonce_failed', __( 'Permission denied (invalid nonce).', 'promptor' ) );
        }

        $query_id = isset( $request['query_id'] ) ? absint( $request['query_id'] ) : 0;
        $feedback = isset( $request['feedback'] ) ? (int) $request['feedback'] : 0;


        // Validate input
        if ( empty( $query_id ) || ! in_array( $feedback, array( 1, -1 ), true ) ) {
            Promptor_Error::send( 'validation_error', __( 'Invalid data provided.', 'promptor' ) );
        }

        // Check if feedback already submitted
        $fingerprint = $this->get_client_fingerprint();
        $guard_key = 'promptor_fb_' . md5( $query_id . '|' . $fingerprint );
        
        if ( get_transient( $guard_key ) ) {
            wp_send_json_success( array(
                'message' => __( 'Thank you, we already received your feedback.', 'promptor' ),
                'already' => true,
            ) );
        }

        set_transient( $guard_key, 1, 5 * MINUTE_IN_SECONDS );

        // Save feedback to database
        $result = $this->save_feedback_to_db( $query_id, $feedback );

        if ( false === $result ) {
            Promptor_Error::send( 'save_failed', __( 'Could not save feedback.', 'promptor' ) );
        }

        // Get user query for notifications
        $user_query = $this->get_user_query( $query_id );

        if ( ! empty( $user_query ) ) {
            $this->send_feedback_notifications( $feedback, $query_id, $user_query );
        }

        wp_send_json_success( array( 'message' => __( 'Feedback saved successfully.', 'promptor' ) ) );
    }

    /**
     * Save feedback to database
     *
     * @param int $query_id Query ID.
     * @param int $feedback Feedback value (1 or -1).
     * @return int|false Number of rows affected or false on error.
     */
    private function save_feedback_to_db( int $query_id, int $feedback ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'promptor_queries';
        
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            $table,
            array( 'feedback' => $feedback ),
            array( 'id' => $query_id ),
            array( '%d' ),
            array( '%d' )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        if ( false !== $result ) {
            wp_cache_delete( 'promptor_q_' . $query_id, self::CACHE_GROUP );
        }

        return $result;
    }

    /**
     * Get user query from database
     *
     * @param int $query_id Query ID.
     * @return string|null User query or null if not found.
     */
    private function get_user_query( int $query_id ): ?string {
        global $wpdb;
        
        $cache_key = 'promptor_q_' . $query_id;
        $user_query = wp_cache_get( $cache_key, self::CACHE_GROUP );
        
        if ( false !== $user_query ) {
            return $user_query;
        }

        $table = $wpdb->prefix . 'promptor_queries';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $user_query = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_query FROM {$wpdb->prefix}promptor_queries WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $query_id
            )
        );

        if ( ! empty( $user_query ) ) {
            wp_cache_set( $cache_key, $user_query, self::CACHE_GROUP, HOUR_IN_SECONDS );
        }

        return $user_query;
    }

    /**
     * Send feedback notifications
     *
     * @param int    $feedback Feedback value.
     * @param int    $query_id Query ID.
     * @param string $user_query User's query.
     * @return void
     */
    private function send_feedback_notifications( int $feedback, int $query_id, string $user_query ): void {
        $notification_settings = get_option( 'promptor_notification_settings', array() );
        
        $placeholders = array(
            '{site_name}'  => get_bloginfo( 'name' ),
            '{user_query}' => $user_query,
            '{query_id}'   => $query_id,
        );

        $trigger = ( 1 === $feedback ) ? 'good_feedback' : 'bad_feedback';

        // Send email notification
        if ( ! empty( $notification_settings['triggers'][ $trigger ]['email'] ) ) {
            $this->send_dynamic_email( $trigger, $placeholders );
        }

        // Send Slack notification
        if ( ! empty( $notification_settings['triggers'][ $trigger ]['slack'] ) ) {
            if ( 1 === $feedback ) {
                $this->send_good_feedback_slack( $query_id, $user_query );
            } else {
                $this->send_bad_feedback_slack( $query_id, $user_query );
            }
        }
    }

    /**
     * Detect language of text using OpenAI
     *
     * @param string $text Text to analyze.
     * @param string $api_key OpenAI API key.
     * @param string $model Model to use.
     * @return string Detected language name.
     */
    private function detect_language( string $text, string $api_key, string $model ): string {
        if ( empty( $text ) || empty( $api_key ) ) {
            return 'English';
        }

        $prompt = sprintf(
            "What language is the following text? Respond with only the language name in English (e.g., Turkish, English, French). Text: '%s'",
            esc_html( substr( $text, 0, 200 ) )
        );

        $messages = array( array( 'role' => 'system', 'content' => $prompt ) );
        
        $request_body = array(
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => 0,
            'max_tokens'  => 10,
        );

        $response = $this->call_openai_api( $api_key, $request_body, 10 );

        if ( is_wp_error( $response ) ) {
            return 'English';
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        return trim( $body['choices'][0]['message']['content'] ?? 'English' );
    }

    /**
     * Translate text using OpenAI
     *
     * @param string $text Text to translate.
     * @param string $target_language Target language.
     * @param string $api_key OpenAI API key.
     * @param string $model Model to use.
     * @return string Translated text or original on error.
     */
    private function translate_text( string $text, string $target_language, string $api_key, string $model ): string {
        if ( empty( $text ) || empty( $target_language ) || empty( $api_key ) ) {
            return $text;
        }

        $prompt = sprintf( "Translate the following text to %s:\n\n%s", esc_html( $target_language ), $text );
        $messages = array( array( 'role' => 'system', 'content' => $prompt ) );
        
        $request_body = array(
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => 0,
            'max_tokens'  => 200,
        );

        $response = $this->call_openai_api( $api_key, $request_body, 20 );

        if ( is_wp_error( $response ) ) {
            return $text;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        return trim( $body['choices'][0]['message']['content'] ?? $text );
    }

    /**
     * Main AI query handler
     *
     * @return void
     */
    public function handle_ai_query(): void {
        // Check free tier limits
        if ( ! $this->check_query_limits() ) {
            Promptor_Error::send(
                'limit_reached',
                __( 'Monthly limit reached (100 queries). Upgrade to Pro for unlimited queries and advanced features!', 'promptor' ),
                array( 'actionable' => true )
            );
            return; // wp_send_json_error calls wp_die(), but return is explicit fallback.
        }

        check_ajax_referer( 'promptor_ai_query_nonce', 'nonce' );

        // Rate limiting
        $this->rate_limit_guard( 'ai_query_minute', 20, MINUTE_IN_SECONDS );
        $this->rate_limit_guard( 'ai_query_hour', 200, HOUR_IN_SECONDS );

        // >>> YENİ: Request'i tek yerde topla (unslash) ve aşağıya parametre ver
        $request = isset( $_POST ) ? (array) wp_unslash( $_POST ) : array();

        // Parse and validate history (artık $_POST yok)
        $history = $this->parse_query_history( $request );

        if ( is_wp_error( $history ) ) {
            Promptor_Error::send( 'invalid_input', $history->get_error_message() );
            return;
        }

        if ( empty( $history ) ) {
            Promptor_Error::send( 'empty_query', __( 'Please type your question above and press Send to get started!', 'promptor' ) );
            return;
        }

        // Get API settings
        $api_settings = $this->get_api_settings();

        if ( is_wp_error( $api_settings ) ) {
            $error_code = $api_settings->get_error_code();

            // When no API key and no trial, suggest starting a trial
            if ( 'no_api_key' === $error_code && class_exists( 'Promptor_Trial_Client' ) ) {
                $trial_data = Promptor_Trial_Client::get_trial_data();
                $trial_status = $trial_data['status'] ?? '';

                if ( in_array( $trial_status, array( 'exhausted', 'expired', 'revoked' ), true ) ) {
                    $friendly_message = __( 'Please enter your OpenAI API key to continue using Promptor.', 'promptor' );
                } else {
                    $friendly_message = __( 'Please set up your OpenAI API key or start a free trial in the plugin settings.', 'promptor' );
                }
            } else {
                $friendly_message = 'no_api_key' === $error_code
                    ? __( 'API configuration needed. Please contact the site administrator to set up the OpenAI API key.', 'promptor' )
                    : $api_settings->get_error_message();
            }

            Promptor_Error::send( 'configuration_error', $friendly_message, array( 'actionable' => true ) );
            return;
        }

        // Get context (artık parametre ile)
        $context_name = $this->get_context_name( $request );

        // Extract user query
        $user_query = $this->extract_user_query( $history );

        if ( empty( $user_query ) ) {
            Promptor_Error::send( 'empty_query', __( 'Please type your question above and press Send to get started!', 'promptor' ) );
            return;
        }

        // Process the AI query
        $result = $this->process_ai_query(
            $user_query,
            $history,
            $context_name,
            $api_settings
        );

        if ( is_wp_error( $result ) ) {
            $error_code    = $result->get_error_code();
            $error_message = $result->get_error_message();

            $friendly_messages = array(
                'api_request_failed'       => __( 'Connection issue. Please check your internet connection and try again.', 'promptor' ),
                'api_error'                => sprintf(
                    /* translators: %s: original error message */
                    __( 'AI service error: %s. Please try again or contact support if this persists.', 'promptor' ),
                    $error_message
                ),
                'embedding_request_failed' => __( 'Unable to process your query. Please check your connection and try again.', 'promptor' ),
                'trial_exhausted'          => __( 'Your free trial queries have been used up. Add your OpenAI API key in settings to continue.', 'promptor' ),
                'trial_expired'            => __( 'Your free trial has expired. Add your OpenAI API key in settings to continue.', 'promptor' ),
                'trial_revoked'            => __( 'Your trial access is no longer available. Please add your OpenAI API key.', 'promptor' ),
                'no_trial_token'           => __( 'Please set up your OpenAI API key or start a free trial in the plugin settings.', 'promptor' ),
                'proxy_request_failed'     => __( 'Connection issue. Please try again.', 'promptor' ),
            );

            $friendly_message = $friendly_messages[ $error_code ] ?? $error_message;

            Promptor_Error::send( $error_code, $friendly_message, array( 'actionable' => true ) );
            return;
        }

        // Increment query count for free tier
        if ( ! promptor_fs()->can_use_premium_code__premium_only() ) {
            $this->increment_query_count();
        }

        // Log query to database
        $query_id = $this->log_query_to_db( $user_query, $result );

        // Track query in telemetry (v1.2.1)
        if ( class_exists( 'Promptor_Telemetry' ) ) {
            Promptor_Telemetry::track_query();
        }

        wp_send_json_success( array(
            'ai_data'  => $result['data'],
            'query_id' => $query_id,
        ) );
    }

    /**
     * Check if user has reached query limits
     *
     * @return bool True if can continue, false if limit reached.
     */
    private function check_query_limits(): bool {
        if ( promptor_fs()->can_use_premium_code__premium_only() ) {
            return true;
        }

        $query_count = (int) get_option( 'promptor_query_count', 0 );
        
        return $query_count < 100;
    }

    /**
    * Parse and validate query history
    *
    * @param array $request Sanitized request array from handler.
    * @return array|WP_Error Validated history array or error.
    */
    private function parse_query_history( array $request ) {
        // Use the raw (already wp_unslash'd) JSON string; applying sanitize_textarea_field()
        // to a JSON string can corrupt it (e.g. strip content via strip_tags or
        // htmlspecialchars_decode when the JSON contains HTML-like characters).
        // Content sanitization happens per-message inside the foreach below.
        $history_raw = ( isset( $request['history'] ) && is_string( $request['history'] ) )
            ? $request['history']
            : '[]';
        
        if ( ! is_string( $history_raw ) ) {
            return new WP_Error( 'invalid_history', __( 'Invalid history format.', 'promptor' ) );
            }
            
            $history = json_decode( $history_raw, true );

if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $history ) ) {
    return new WP_Error( 'invalid_history', __( 'Invalid history format.', 'promptor' ) );
    }

    // Limit and sanitize history
        $history = array_slice( $history, -self::MAX_HISTORY_LENGTH );

        foreach ( $history as &$message ) {
            if ( isset( $message['content'] ) ) {
                $message['content'] = $this->sanitize_message_content( $message['content'] );
            }
        }
        unset( $message );

        return $history;
    }

    /**
     * Sanitize message content
     *
     * @param mixed $content Content to sanitize.
     * @return string Sanitized content.
     */
    private function sanitize_message_content( $content ): string {
        if ( ! is_string( $content ) ) {
            $content = '';
        }

        $content = wp_strip_all_tags( $content );
        $content = trim( preg_replace( '/\s+/', ' ', $content ) );
        
        return mb_substr( $content, 0, self::MAX_MESSAGE_LENGTH, 'UTF-8' );
    }

    /**
     * Get and validate API settings
     *
     * Falls back to trial mode when no API key is configured.
     *
     * @return array|WP_Error API settings or error.
     */
    private function get_api_settings() {
        $settings = get_option( 'promptor_api_settings', array() );

        if ( empty( $settings['api_key'] ) ) {
            // Check if trial is active as fallback
            if ( class_exists( 'Promptor_Trial_Client' ) && Promptor_Trial_Client::is_trial_active() ) {
                $settings['api_key']    = '';
                $settings['trial_mode'] = true;
                $settings['model']      = 'gpt-4o';
                return $settings;
            }

            return new WP_Error( 'no_api_key', __( 'API Key is not set.', 'promptor' ) );
        }

        // Validate and sanitize model
        $model = isset( $settings['model'] ) ? sanitize_text_field( $settings['model'] ) : 'gpt-4o';

        if ( ! in_array( $model, self::ALLOWED_MODELS, true ) ) {
            $model = 'gpt-4o';
        }

        $settings['model']      = $model;
        $settings['trial_mode'] = false;

        return $settings;
    }

    /**
    * Get context name from request
    *
    * @param array $request Sanitized request array from handler.
    * @return string Context name.
    */
    private function get_context_name( array $request ): string {
        $context = isset( $request['context'] ) ? sanitize_key( $request['context'] ) : 'default';

        if ( 'global_popup' === $context ) {
            $ui_settings = get_option( 'promptor_ui_settings', array() );
            $fallback = isset( $ui_settings['popup_context_source'] )
                ? sanitize_key( $ui_settings['popup_context_source'] )
                : 'default';

            $context = $fallback ?: 'default';
        }

        return $context;
    }


    /**
     * Extract user query from history
     *
     * @param array $history Message history.
     * @return string User query.
     */
    private function extract_user_query( array $history ): string {
        $last_message = end( $history );
        $query_raw = is_array( $last_message ) ? ( $last_message['content'] ?? '' ) : '';
        
        return $this->sanitize_message_content( $query_raw );
    }

    /**
     * Check if user query is unclear and needs clarification
     *
     * @param string $user_query User's query.
     * @param string $detected_language Detected language.
     * @param string $context_name Context name.
     * @return array Array with 'is_unclear' and 'clarifying_message'.
     */
    private function check_if_unclear_query( string $user_query, string $detected_language, string $context_name ): array {
        $query_lower = strtolower( trim( $user_query ) );
        $word_count = str_word_count( $query_lower );

        // Common unclear query patterns (multilingual)
        $unclear_patterns = array(
            'en' => array( 'help', 'hello', 'hi', 'hey', 'info', 'tell me more', 'what do you do', 'services', 'products' ),
            'tr' => array( 'merhaba', 'selam', 'yardım', 'bilgi', 'daha fazla', 'ne yapıyorsunuz', 'hizmetler', 'ürünler' ),
            'de' => array( 'hallo', 'hilfe', 'info', 'mehr', 'dienstleistungen', 'produkte' ),
            'fr' => array( 'bonjour', 'salut', 'aide', 'info', 'services', 'produits' ),
            'es' => array( 'hola', 'ayuda', 'información', 'servicios', 'productos' ),
        );

        // Detect if query is too short or matches unclear patterns
        $is_unclear = false;
        if ( $word_count <= 2 && strlen( $query_lower ) < 15 ) {
            foreach ( $unclear_patterns as $patterns ) {
                if ( in_array( $query_lower, $patterns, true ) ) {
                    $is_unclear = true;
                    break;
                }
            }
        }

        if ( ! $is_unclear ) {
            return array(
                'is_unclear'          => false,
                'clarifying_message'  => '',
            );
        }

        // Get available content types to suggest
        $all_contexts = get_option( 'promptor_contexts', array() );
        $content_roles = $all_contexts[ $context_name ]['settings']['content_roles'] ?? array();

        $available_types = array();
        if ( ! empty( $content_roles ) ) {
            foreach ( $content_roles as $post_id => $role ) {
                if ( ! in_array( $role, $available_types, true ) ) {
                    $available_types[] = $role;
                }
            }
        }

        // Build clarifying message based on language and available content
        $clarifying_messages = array(
            'en' => "I'd be happy to help! Could you be more specific about what you're looking for? For example, you can ask about our %s.",
            'tr' => "Size yardımcı olmaktan mutluluk duyarım! Ne aradığınız hakkında daha spesifik olabilir misiniz? Örneğin, %s hakkında sorabilirsiniz.",
            'de' => "Ich helfe Ihnen gerne! Könnten Sie genauer angeben, wonach Sie suchen? Sie können zum Beispiel nach unseren %s fragen.",
            'fr' => "Je serais ravi de vous aider ! Pourriez-vous être plus précis sur ce que vous recherchez ? Par exemple, vous pouvez poser des questions sur nos %s.",
            'es' => "¡Estaré encantado de ayudarte! ¿Podrías ser más específico sobre lo que buscas? Por ejemplo, puedes preguntar sobre nuestros %s.",
        );

        $type_translations = array(
            'en' => array( 'service' => 'services', 'product' => 'products', 'blog' => 'articles', 'faq' => 'frequently asked questions' ),
            'tr' => array( 'service' => 'hizmetlerimiz', 'product' => 'ürünlerimiz', 'blog' => 'makalelerimiz', 'faq' => 'sık sorulan sorular' ),
            'de' => array( 'service' => 'Dienstleistungen', 'product' => 'Produkte', 'blog' => 'Artikel', 'faq' => 'häufig gestellte Fragen' ),
            'fr' => array( 'service' => 'services', 'product' => 'produits', 'blog' => 'articles', 'faq' => 'questions fréquentes' ),
            'es' => array( 'service' => 'servicios', 'product' => 'productos', 'blog' => 'artículos', 'faq' => 'preguntas frecuentes' ),
        );

        $lang_code = strtolower( substr( $detected_language, 0, 2 ) );
        $message_template = $clarifying_messages[ $lang_code ] ?? $clarifying_messages['en'];
        $translations = $type_translations[ $lang_code ] ?? $type_translations['en'];

        $available_labels = array();
        foreach ( $available_types as $type ) {
            if ( isset( $translations[ $type ] ) ) {
                $available_labels[] = $translations[ $type ];
            }
        }

        $types_list = ! empty( $available_labels )
            ? implode( ', ', $available_labels )
            : ( $translations['service'] ?? 'our services and products' );

        return array(
            'is_unclear'         => true,
            'clarifying_message' => sprintf( $message_template, $types_list ),
        );
    }

    /**
     * Process AI query with context and embeddings
     *
     * @param string $user_query User's query.
     * @param array  $history Message history.
     * @param string $context_name Context name.
     * @param array  $api_settings API settings.
     * @return array|WP_Error Result data or error.
     */
    private function process_ai_query( string $user_query, array $history, string $context_name, array $api_settings ) {
        $start_time = microtime( true );

        $api_key = $api_settings['api_key'];
        $model = $api_settings['model'];

        // --- Cost Guardrails: check limits and resolve model ---
        $cost_status = Promptor_Cost_Guard::check_limits();

        if ( 'paused' === $cost_status ) {
            return new WP_Error(
                'daily_limit_exceeded',
                __( 'Our AI assistant is temporarily unavailable due to high usage. Please try again later or contact us directly.', 'promptor' )
            );
        }

        $model_resolution = Promptor_Cost_Guard::resolve_model( $model );
        $model = $model_resolution['model'];

        // Detect language
        $site_language = self::LANGUAGE_MAP[ get_locale() ] ?? 'English';
        $is_trial = ! empty( $api_settings['trial_mode'] );

        // In trial mode, skip language detection API call (no API key available)
        $detected_language = $is_trial
            ? $site_language
            : $this->detect_language( $user_query, $api_key, $model );

        // Check if query is unclear and needs clarification
        $unclear_check = $this->check_if_unclear_query( $user_query, $detected_language, $context_name );
        if ( $unclear_check['is_unclear'] ) {
            return array(
                'data' => array(
                    'ai_explanation' => $unclear_check['clarifying_message'],
                    'services'       => array(),
                    'products'       => array(),
                    'articles'       => array(),
                    'faqs'           => array(),
                ),
                'recommended_titles'  => array(),
                'response_time_ms'    => round( ( microtime( true ) - $start_time ) * 1000 ),
                'similarity_score'    => 0,
                'total_tokens'        => 0,
                'query_cost'          => 0,
                'status_code'         => 200,
            );
        }

        // In trial mode, skip embedding — use trial context if available, else fallback
        if ( $is_trial ) {
            $trial_context = class_exists( 'Promptor_Trial_Client' ) ? Promptor_Trial_Client::get_context_for_chat() : '';

            if ( ! empty( $trial_context ) ) {
                $request_data = $this->build_trial_context_request( $user_query, $detected_language, $trial_context, $model, $api_settings );
            } else {
                $request_data = $this->build_fallback_request( $user_query, $detected_language, $context_name, $model, $api_settings );
            }
            $confidence_score = 0;
        } else {
            // Translate if necessary
            $query_for_embedding = $user_query;
            if ( strtolower( $detected_language ) !== strtolower( $site_language ) ) {
                $query_for_embedding = $this->translate_text( $user_query, $site_language, $api_key, $model );
            }

            // Get embedding
            $query_vector = $this->get_embedding( $query_for_embedding, $api_key );

            if ( is_wp_error( $query_vector ) ) {
                return $query_vector;
            }

            // Find similar chunks
            $similar_chunks = $this->find_similar_chunks( $query_vector, $context_name );

            // Calculate confidence score from similar chunks
            $confidence_score = ! empty( $similar_chunks ) ? $similar_chunks[0]['score'] : 0;

            // Build AI request
            if ( empty( $similar_chunks ) ) {
                $request_data = $this->build_fallback_request( $user_query, $detected_language, $context_name, $model, $api_settings );
            } else {
                $request_data = $this->build_context_request(
                    $user_query,
                    $query_for_embedding,
                    $detected_language,
                    $similar_chunks,
                    $api_settings,
                    $confidence_score,
                    $context_name
                );
            }
        }

        // Call OpenAI API (direct or via trial proxy)
        $is_trial = ! empty( $api_settings['trial_mode'] );

        if ( $is_trial && class_exists( 'Promptor_Trial_Client' ) ) {
            // Sync trial status before request (throttled)
            Promptor_Trial_Client::get_status();

            if ( ! Promptor_Trial_Client::is_trial_active() ) {
                $trial_data = Promptor_Trial_Client::get_trial_data();
                $status = $trial_data['status'] ?? 'unknown';
                $messages = array(
                    'exhausted' => __( 'Your free trial queries have been used up. Please enter your OpenAI API key to continue using Promptor.', 'promptor' ),
                    'expired'   => __( 'Your free trial has expired. Please enter your OpenAI API key to continue using Promptor.', 'promptor' ),
                    'revoked'   => __( 'Your trial access has been revoked. Please enter your OpenAI API key.', 'promptor' ),
                );
                $msg = $messages[ $status ] ?? __( 'Trial is no longer available. Please enter your OpenAI API key.', 'promptor' );
                return new WP_Error( 'trial_' . $status, $msg );
            }

            $response = Promptor_Trial_Client::proxy_chat( $request_data['request_body'], 45 );
        } else {
            $response = $this->call_openai_api( $api_key, $request_data['request_body'], 45 );
        }

        if ( is_wp_error( $response ) ) {
            $this->send_error_notifications( $user_query, $response->get_error_message() );
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $response_body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $status_code ) {
            // Check both proxy-style { error: "..." } and OpenAI-style { error: { message: "..." } }
            $error_message = $response_body['error']['message']
                ?? ( is_string( $response_body['error'] ?? null ) ? $response_body['error'] : null )
                ?? wp_remote_retrieve_response_message( $response );
            $this->send_error_notifications( $user_query, $error_message );
            return new WP_Error( 'api_error', $error_message );
        }

        // Process response
        $result = $this->process_ai_response(
            $response_body,
            $request_data['source_post_ids'],
            $request_data['all_recommended_titles'],
            $context_name,
            $model,
            microtime( true ) - $start_time
        );

        // --- Cost Guardrails: track token usage ---
        $prompt_tokens     = $response_body['usage']['prompt_tokens'] ?? 0;
        $completion_tokens = $response_body['usage']['completion_tokens'] ?? 0;

        if ( $prompt_tokens > 0 || $completion_tokens > 0 ) {
            Promptor_Cost_Guard::track_usage( $model, $prompt_tokens, $completion_tokens, 'conversation' );
        }

        if ( $model_resolution['is_fallback'] ) {
            $result['is_fallback'] = true;
        }

        return $result;
    }

    /**
     * Find similar content chunks using embeddings
     *
     * @param array  $query_vector Query embedding vector.
     * @param string $context_name Context name.
     * @return array Sorted array of similar chunks.
     */
    private function find_similar_chunks( array $query_vector, string $context_name ): array {
        global $wpdb;
        
        $chunks_cache_key = 'promptor_chunks_' . $context_name;
        $chunks = wp_cache_get( $chunks_cache_key, self::CACHE_GROUP );
        
        if ( false === $chunks ) {
            $table = $wpdb->prefix . 'promptor_embeddings';
            
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $chunks = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT post_id, content_chunk, vector_data 
                    FROM {$wpdb->prefix}promptor_embeddings 
                    WHERE context_name = %s AND vector_data IS NOT NULL", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $context_name
                ),
                ARRAY_A
            );
            
            wp_cache_set( $chunks_cache_key, $chunks, self::CACHE_GROUP, HOUR_IN_SECONDS );
        }

        $similar_chunks = array();
        
        if ( ! empty( $chunks ) ) {
            foreach ( $chunks as $chunk ) {
                $chunk_vector = json_decode( $chunk['vector_data'], true );
                
                if ( is_array( $chunk_vector ) ) {
                    $score = $this->cosine_similarity( $query_vector, $chunk_vector );
                    
                    if ( $score >= 0.35 ) {
                        $similar_chunks[] = array(
                            'post_id' => $chunk['post_id'],
                            'content' => $chunk['content_chunk'],
                            'score'   => $score,
                        );
                    }
                }
            }
            
            usort( $similar_chunks, fn( $a, $b ) => $b['score'] <=> $a['score'] );
        }

        return array_slice( $similar_chunks, 0, 10 );
    }

    /**
     * Build fallback request when no context found
     *
     * @param string $user_query User's query.
     * @param string $detected_language Detected language.
     * @param string $context_name Context name.
     * @param string $model Model to use.
     * @return array Request data.
     */
    private function build_fallback_request( string $user_query, string $detected_language, string $context_name, string $model, array $api_settings = array() ): array {
        global $wpdb;

        $titles_cache_key = 'promptor_titles_' . $context_name;
        $titles_query = wp_cache_get( $titles_cache_key, self::CACHE_GROUP );

        if ( false === $titles_query ) {
            $table = $wpdb->prefix . 'promptor_embeddings';

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $titles_query = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DISTINCT post_id FROM {$wpdb->prefix}promptor_embeddings WHERE context_name = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $context_name
                ),
                ARRAY_A
            );

            wp_cache_set( $titles_cache_key, $titles_query, self::CACHE_GROUP, HOUR_IN_SECONDS );
        }

        $valid_titles = array_filter( array_map( 'get_the_title', array_column( $titles_query, 'post_id' ) ) );

        // Check if Knowledge Base is completely empty
        if ( empty( $valid_titles ) ) {
            $empty_kb_messages = array(
                'en' => '📚 The Knowledge Base is currently empty. The website administrator needs to add content (pages, posts, products, or FAQs) and sync them to the Knowledge Base. Once content is added, I\'ll be able to help answer your questions!',
                'tr' => '📚 Bilgi Tabanı şu anda boş. Web sitesi yöneticisinin içerik eklemesi (sayfalar, yazılar, ürünler veya SSS) ve bunları Bilgi Tabanı ile senkronize etmesi gerekiyor. İçerik eklendikten sonra sorularınızı yanıtlayabileceğim!',
                'de' => '📚 Die Wissensdatenbank ist derzeit leer. Der Website-Administrator muss Inhalte (Seiten, Beiträge, Produkte oder FAQs) hinzufügen und mit der Wissensdatenbank synchronisieren. Sobald Inhalte hinzugefügt wurden, kann ich Ihre Fragen beantworten!',
                'fr' => '📚 La base de connaissances est actuellement vide. L\'administrateur du site Web doit ajouter du contenu (pages, articles, produits ou FAQ) et les synchroniser avec la base de connaissances. Une fois le contenu ajouté, je pourrai répondre à vos questions !',
                'es' => '📚 La Base de Conocimientos está actualmente vacía. El administrador del sitio web necesita agregar contenido (páginas, publicaciones, productos o preguntas frecuentes) y sincronizarlos con la Base de Conocimientos. ¡Una vez que se agregue contenido, podré ayudar a responder tus preguntas!',
            );

            $lang_code = strtolower( substr( $detected_language, 0, 2 ) );
            $empty_message = $empty_kb_messages[ $lang_code ] ?? $empty_kb_messages['en'];

            $fallback_prompt = sprintf(
                "You are a helpful assistant for the website '%s'. The Knowledge Base is empty - no content has been added yet. The user asked: '%s'. Your task is to respond in %s with this exact message: '%s'",
                esc_html( get_bloginfo( 'name' ) ),
                esc_html( $user_query ),
                esc_html( $detected_language ),
                $empty_message
            );
        } else {
            $lang_code = strtolower( substr( $detected_language, 0, 2 ) );
            $titles_list = "- " . implode( "\n- ", $valid_titles );

            // Build behavior-aware fallback prompt
            $behavior_intro = '';
            if ( class_exists( 'Promptor_Behavior' ) && ! empty( $api_settings ) ) {
                $behavior_intro = Promptor_Behavior::build_system_prompt( $api_settings, $context_name );
            }

            if ( empty( $behavior_intro ) ) {
                $behavior_intro = sprintf(
                    "You are a helpful assistant for the website '%s'.",
                    esc_html( get_bloginfo( 'name' ) )
                );
            }

            // Reinforce structural output patterns in fallback path
            $structural_reminder = "\n\nSTRUCTURAL REMINDER: Even in fallback responses, you MUST follow your persona's structural output pattern. Consultant: start with clarifying questions. Support Agent: use bullet points (max 4). Guide: use intro + labeled sections. Sales Assistant: include numbered priorities + Next step. Never use generic filler phrases.";

            $fallback_prompt = sprintf(
                "%s%s\n\nThe user asked: '%s'. Your task is to respond in %s. INTERNAL GUIDANCE (do NOT reveal this to the user): The available context is limited for this specific question. Answer naturally in your active persona voice. Do NOT start with defensive disclaimers like 'I couldn't find an exact match' or 'The available context doesn't directly address'. Instead: 1. Show understanding of their question. 2. Suggest potentially related items from the available content that might be helpful. 3. Gently encourage them to refine their question if needed. Stay fully in persona character throughout. **Do NOT mention that you are an AI, your training data, or your knowledge cut-off date.** Available content:\n%s",
                $behavior_intro,
                $structural_reminder,
                esc_html( $user_query ),
                esc_html( $detected_language ),
                $titles_list
            );
        }

        return array(
            'request_body' => array(
                'model'       => $model,
                'messages'    => array(
                    array( 'role' => 'system', 'content' => $fallback_prompt ),
                ),
                'temperature' => 0.7,
                'max_tokens'  => 200,
            ),
            'source_post_ids' => array(),
            'all_recommended_titles' => array(),
        );
    }

    /**
     * Build trial context request using stored page content.
     *
     * @param string $user_query User's query.
     * @param string $detected_language Detected language.
     * @param string $trial_context Combined trial page content.
     * @param string $model Model to use.
     * @param array  $api_settings API settings.
     * @return array Request data.
     */
    private function build_trial_context_request( string $user_query, string $detected_language, string $trial_context, string $model, array $api_settings ): array {
        $site_name = esc_html( get_bloginfo( 'name' ) );

        $system_prompt = sprintf(
            "You are a helpful AI assistant for the website '%s'. Answer the user's question using ONLY the website content provided below. Be concise, accurate, and helpful. If the content doesn't contain enough information to answer, say so honestly. IMPORTANT: Always respond in the same language as the user's message.\n\nWebsite content:\n%s",
            $site_name,
            mb_substr( $trial_context, 0, 8000, 'UTF-8' )
        );

        return array(
            'request_body' => array(
                'model'       => $model,
                'messages'    => array(
                    array( 'role' => 'system', 'content' => $system_prompt ),
                    array( 'role' => 'user', 'content' => $user_query ),
                ),
                'temperature' => 0.7,
                'max_tokens'  => 500,
            ),
            'source_post_ids'        => array(),
            'all_recommended_titles' => array(),
        );
    }

    /**
     * Build context-aware request
     *
     * @param string $user_query Original user query.
     * @param string $query_for_embedding Translated query.
     * @param string $detected_language Detected language.
     * @param array  $similar_chunks Similar content chunks.
     * @param array  $api_settings API settings.
     * @param float  $confidence_score Confidence score from similarity matching.
     * @return array Request data.
     */
    private function build_context_request( string $user_query, string $query_for_embedding, string $detected_language, array $similar_chunks, array $api_settings, float $confidence_score = 0.5, string $context_name = '' ): array {
        $context = implode( "\n\n---\n\n", array_column( $similar_chunks, 'content' ) );
        $source_post_ids = array_unique( array_column( $similar_chunks, 'post_id' ) );
        $valid_titles = array_filter( array_map( 'get_the_title', $source_post_ids ) );
        $titles_list = "- " . implode( "\n- ", $valid_titles );

        // Determine confidence level for internal guidance (not user-facing)
        $confidence_level = 'high';
        if ( $confidence_score >= 0.70 ) {
            $confidence_level = 'high';
        } elseif ( $confidence_score >= 0.50 ) {
            $confidence_level = 'medium';
        } else {
            $confidence_level = 'low';
        }

        // Build system prompt using behavior system (v1.3.0) with legacy fallback
        $base_prompt = '';
        if ( class_exists( 'Promptor_Behavior' ) ) {
            $base_prompt = Promptor_Behavior::build_system_prompt( $api_settings, $context_name );
        }

        // Legacy fallback: if behavior system is unavailable or returned empty, use old logic
        if ( empty( $base_prompt ) ) {
            $default_prompt = sprintf(
                "You are a helpful assistant for the website '%s'. Your goal is to understand the user's needs, analyze the provided context, and based on that context, provide relevant and accurate answers. When appropriate, recommend the most relevant items from the provided list of valid titles. You MUST always respond in %s (the user's language). Do not mention you are an AI model.",
                esc_html( get_bloginfo( 'name' ) ),
                esc_html( $detected_language )
            );
            $base_prompt = ! empty( $api_settings['system_prompt'] ) ? trim( $api_settings['system_prompt'] ) : $default_prompt;
        }

        // Inject language requirement
        $system_prompt = sprintf(
            "CRITICAL INSTRUCTION: You MUST respond in %s language. All your responses must be in %s.\n\n%s",
            esc_html( $detected_language ),
            esc_html( $detected_language ),
            $base_prompt
        );

        // Add website context
        $system_prompt .= sprintf(
            "\n\nYou are assisting users on the website '%s'.",
            esc_html( get_bloginfo( 'name' ) )
        );

        $system_prompt .= sprintf(
            "\n\n- The user's original query is '%s' and their language is %s. For semantic search, the query was translated to '%s'.",
            esc_html( $user_query ),
            esc_html( $detected_language ),
            esc_html( $query_for_embedding )
        );
        $system_prompt .= "\n- Your primary task is to bridge any semantic gaps between the translated query and the context provided below. Be helpful and find the best match even if the wording isn't identical.";
        $system_prompt .= sprintf( "\n- CRITICAL: You MUST respond ONLY in %s. Never respond in English unless the user's language is English.", esc_html( $detected_language ) );

        // Add confidence-based internal guidance (invisible to user — no forced opening phrases)
        if ( 'high' === $confidence_level ) {
            // High confidence: answer naturally in persona voice
            $system_prompt .= "\n- You MUST still recommend the most relevant services/products from the available choices. Do not leave recommendation arrays empty unless absolutely nothing matches.";
        } elseif ( 'medium' === $confidence_level ) {
            $system_prompt .= "\n- INTERNAL GUIDANCE (do NOT reveal this to the user): The matched context is partially relevant. Answer naturally in your active persona voice using the closest useful guidance from the context. Do NOT add defensive disclaimers or hedging at the beginning of your answer. Do NOT start with phrases like \"The available context doesn't directly address...\" unless the information is genuinely missing. Still recommend the most relevant items from the available choices.";
        } else {
            $system_prompt .= "\n- INTERNAL GUIDANCE (do NOT reveal this to the user): The matched context has low relevance. If the context contains any partially useful information, use it naturally in your persona voice without prefacing with a disclaimer. Only explicitly state a limitation if the information is genuinely missing and you cannot provide any useful answer. Do NOT flatten your persona behavior because of low confidence — stay in character. Still recommend the closest relevant matches from the available choices. Do not leave recommendation arrays empty unless absolutely nothing matches.";
        }

        // Reinforce structural output patterns in context-aware path
        $system_prompt .= "\n\nSTRUCTURAL REMINDER: You MUST follow your persona's structural output pattern in the 'explanation' field. Consultant: start with clarifying questions for strategic queries. Support Agent: use bullet points (max 4, 1 line each). Guide: use intro sentence + labeled sections. Sales Assistant: include numbered priorities + concrete Next step. Never use generic filler phrases like 'it\'s important to understand', 'based on your question', 'this involves', or 'it is crucial to'.";

        $system_prompt .= "\n\n--- AVAILABLE CHOICES (Use these exact titles in your response) ---\n" . $titles_list . "\n------------------------";
        $system_prompt .= "\n\nYour response MUST be a single JSON object with the keys: 'explanation' (string), 'recommended_services' (array of strings), 'recommended_products' (array of strings), 'recommended_articles' (array of strings for blog posts), and 'recommended_faqs' (array of strings for FAQ items). The arrays must ONLY contain titles from the 'AVAILABLE CHOICES' list. The titles MUST be in their original language.";
        $system_prompt .= "\n\nIMPORTANT: Always try to recommend at least 1-3 most relevant items even if the match is not perfect. Only return empty arrays if the query is completely unrelated to all available choices.";

        $messages = array(
            array( 'role' => 'system', 'content' => $system_prompt ),
            array(
                'role'    => 'user',
                'content' => sprintf(
                    "CONTEXT:\n\"\"\"\n%s\"\"\"\n\nMY (TRANSLATED) QUESTION:\n\"%s\"",
                    $context,
                    esc_html( $query_for_embedding )
                ),
            ),
        );

        return array(
            'request_body' => array(
                'model'           => $api_settings['model'],
                'messages'        => $messages,
                'temperature'     => (float) ( $api_settings['temperature'] ?? 0.5 ),
                'max_tokens'      => (int) ( $api_settings['max_tokens'] ?? 1024 ),
                'response_format' => array( 'type' => 'json_object' ),
            ),
            'source_post_ids' => $source_post_ids,
            'all_recommended_titles' => array(),
        );
    }

    /**
     * Call OpenAI API
     *
     * @param string $api_key API key.
     * @param array  $request_body Request body.
     * @param int    $timeout Request timeout.
     * @return array|WP_Error Response or error.
     */
    private function call_openai_api( string $api_key, array $request_body, int $timeout = 45 ) {
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $request_body ),
            'timeout' => $timeout,
        );

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', $args );

        if ( is_wp_error( $response ) ) {
            $this->log_error( 'OpenAI API request failed: ' . $response->get_error_message() );
            return new WP_Error( 'api_request_failed', __( 'Failed to connect to AI service.', 'promptor' ) );
        }

        return $response;
    }

    /**
     * Process AI response and format data
     *
     * @param array  $response_body API response body.
     * @param array  $source_post_ids Source post IDs.
     * @param array  $all_recommended_titles Recommended titles.
     * @param string $context_name Context name.
     * @param string $model Model used.
     * @param float  $response_time Response time in seconds.
     * @return array Processed result data.
     */
    private function process_ai_response( array $response_body, array $source_post_ids, array $all_recommended_titles, string $context_name, string $model, float $response_time ): array {
        $content_raw = $response_body['choices'][0]['message']['content'] ?? '';
        $ai_data = json_decode( $content_raw, true );

        $final_data = array(
            'ai_explanation' => '',
            'services'       => array(),
            'products'       => array(),
            'articles'       => array(),
            'faqs'           => array(),
        );

        if ( is_array( $ai_data ) && isset( $ai_data['explanation'] ) ) {
            $final_data = $this->format_recommendations( $ai_data, $source_post_ids, $context_name );
            
            $all_recommended_titles = array_unique( array_merge(
                $ai_data['recommended_services'] ?? array(),
                $ai_data['recommended_products'] ?? array(),
                $ai_data['recommended_articles'] ?? array(),
                $ai_data['recommended_faqs'] ?? array()
            ) );
        } else {
            $final_data['ai_explanation'] = $content_raw;
        }

        // Calculate tokens and cost
        $prompt_tokens = $response_body['usage']['prompt_tokens'] ?? 0;
        $completion_tokens = $response_body['usage']['completion_tokens'] ?? 0;
        $total_tokens = $prompt_tokens + $completion_tokens;
        $query_cost = $this->calculate_cost( $model, $prompt_tokens, $completion_tokens );

        return array(
            'data'                    => $final_data,
            'recommended_titles'      => $all_recommended_titles,
            'response_time_ms'        => round( $response_time * 1000 ),
            'similarity_score'        => ! empty( $source_post_ids ) ? 0.5 : 0,
            'total_tokens'            => $total_tokens,
            'query_cost'              => $query_cost,
            'status_code'             => 200,
        );
    }

    /**
     * Format AI recommendations into structured data
     *
     * @param array  $ai_data AI response data.
     * @param array  $source_post_ids Source post IDs.
     * @param string $context_name Context name.
     * @return array Formatted recommendations.
     */
    private function format_recommendations( array $ai_data, array $source_post_ids, string $context_name ): array {
        $all_contexts = get_option( 'promptor_contexts', array() );
        $content_roles = $all_contexts[ $context_name ]['settings']['content_roles'] ?? array();

        $all_titles = array_unique( array_merge(
            $ai_data['recommended_services'] ?? array(),
            $ai_data['recommended_products'] ?? array(),
            $ai_data['recommended_articles'] ?? array(),
            $ai_data['recommended_faqs'] ?? array()
        ) );

        $formatted = array(
            'ai_explanation' => $ai_data['explanation'],
            'services'       => array(),
            'products'       => array(),
            'articles'       => array(),
            'faqs'           => array(),
        );

        foreach ( $all_titles as $title ) {
            $post = $this->find_post_by_title( $title, $source_post_ids );
            
            if ( ! $post ) {
                continue;
            }

            $role = $content_roles[ $post->ID ] ?? $this->get_default_role( $post );
            $item = $this->format_recommendation_item( $post, $role );
            
            if ( $item ) {
                switch ( $role ) {
                    case 'service':
                        $formatted['services'][] = $item;
                        break;
                    case 'product':
                        $formatted['products'][] = $item;
                        break;
                    case 'faq':
                        $formatted['faqs'][] = $item;
                        break;
                    case 'blog':
                    default:
                        $formatted['articles'][] = $item;
                        break;
                }
            }
        }

        return $formatted;
    }

    /**
     * Find post by title
     *
     * @param string $title Post title.
     * @param array  $post_ids Allowed post IDs.
     * @return WP_Post|null Post object or null.
     */
    private function find_post_by_title( string $title, array $post_ids ): ?WP_Post {
        if ( empty( $title ) || empty( $post_ids ) ) {
            return null;
        }

        $normalized_title = trim( $title );

        // First try: exact title match within allowed post IDs
        foreach ( $post_ids as $post_id ) {
            $post = get_post( $post_id );
            if ( $post && trim( $post->post_title ) === $normalized_title ) {
                return $post;
            }
        }

        // Second try: case-insensitive match
        foreach ( $post_ids as $post_id ) {
            $post = get_post( $post_id );
            if ( $post && strcasecmp( trim( $post->post_title ), $normalized_title ) === 0 ) {
                return $post;
            }
        }

        // Third try: partial match (contains title)
        foreach ( $post_ids as $post_id ) {
            $post = get_post( $post_id );
            if ( $post && stripos( trim( $post->post_title ), $normalized_title ) !== false ) {
                return $post;
            }
        }

        return null;
    }

    /**
     * Get default role for post
     *
     * @param WP_Post $post Post object.
     * @return string Role name.
     */
    private function get_default_role( WP_Post $post ): string {
        if ( 'product' === $post->post_type ) {
            return 'product';
        }

        if ( 'page' === $post->post_type ) {
            return 'service';
        }

        return 'blog';
    }

    /**
     * Format recommendation item
     *
     * @param WP_Post $post Post object.
     * @param string  $role Content role.
     * @return array|null Formatted item or null.
     */
    private function format_recommendation_item( WP_Post $post, string $role ): ?array {
        $description = $this->get_post_description( $post );

        $item = array(
            'title'       => $post->post_title,
            'description' => $description,
            'link'        => get_permalink( $post->ID ),
        );

        // PDF Attachment handling
        if ( 'attachment' === $post->post_type && 'application/pdf' === $post->post_mime_type ) {
            $file_path = get_attached_file( $post->ID );

            if ( $file_path && file_exists( $file_path ) ) {
                $item['type'] = 'pdf';
                $item['file_size'] = size_format( filesize( $file_path ) );
                $item['link'] = wp_get_attachment_url( $post->ID );
            }
        } elseif ( 'product' === $role && class_exists( 'WooCommerce' ) && 'product' === $post->post_type ) {
            $product = wc_get_product( $post->ID );

            if ( $product ) {
                $item['id'] = $product->get_id();
                $item['price'] = $product->get_price_html();
                $item['image'] = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' );
            }
        } elseif ( 'blog' === $role ) {
            $item['image'] = get_the_post_thumbnail_url( $post->ID, 'medium' );
        }

        return $item;
    }

    /**
     * Get post description
     *
     * @param WP_Post $post Post object.
     * @return string Description text.
     */
    private function get_post_description( WP_Post $post ): string {
        if ( has_excerpt( $post->ID ) ) {
            return get_the_excerpt( $post->ID );
        }
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter
        $content = apply_filters( 'the_content', get_the_content( null, false, $post->ID ) );
        $content = wp_strip_all_tags( strip_shortcodes( $content ) );

        return ! empty( $content ) ? wp_trim_words( $content, 25, '...' ) : '';
    }

    /**
     * Log query to database
     *
     * @param string $user_query User's query.
     * @param array  $result Query result data.
     * @return int Query ID.
     */
    private function log_query_to_db( string $user_query, array $result ): int {
        global $wpdb;
        
        $table = $wpdb->prefix . 'promptor_queries';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->insert(
            $table,
            array(
                'user_query'               => $user_query,
                'status_code'              => $result['status_code'],
                'ai_response_raw'          => wp_json_encode( $result['data'] ),
                'suggested_service_titles' => implode( ', ', $result['recommended_titles'] ),
                'query_timestamp'          => current_time( 'mysql' ),
                'response_time_ms'         => $result['response_time_ms'],
                'similarity_score'         => $result['similarity_score'],
                'tokens_used'              => $result['total_tokens'],
                'query_cost'               => $result['query_cost'],
            ),
            array( '%s', '%d', '%s', '%s', '%s', '%d', '%f', '%d', '%f' )
        );

        return $wpdb->insert_id;
    }

    /**
     * Increment query count for free tier
     *
     * @return void
     */
    private function increment_query_count(): void {
        $current_count = (int) get_option( 'promptor_query_count', 0 );
        update_option( 'promptor_query_count', $current_count + 1 );
    }

    /**
     * Send error notifications
     *
     * @param string $user_query User's query.
     * @param string $error_message Error message.
     * @return void
     */
    private function send_error_notifications( string $user_query, string $error_message ): void {
        $notification_settings = get_option( 'promptor_notification_settings', array() );
        
        $placeholders = array(
            '{site_name}'     => get_bloginfo( 'name' ),
            '{user_query}'    => $user_query,
            '{error_message}' => $error_message,
        );

        if ( ! empty( $notification_settings['triggers']['query_error']['email'] ) ) {
            $this->send_dynamic_email( 'query_error', $placeholders );
        }

        if ( ! empty( $notification_settings['triggers']['query_error']['slack'] ) ) {
            $this->send_query_error_slack( $user_query, $error_message );
        }
    }

    /**
     * Send dynamic email notification
     *
     * @param string $template_key Template key.
     * @param array  $placeholders Placeholder values.
     * @return bool True on success, false on failure.
     */
    private function send_dynamic_email( string $template_key, array $placeholders ): bool {
        $settings = get_option( 'promptor_notification_settings', array() );
        $email_settings = $settings['email'] ?? array();
        $templates_config = ( new Promptor_Settings_Tab_Notifications() )->get_email_templates_config();

        $template = $email_settings['templates'][ $template_key ] ?? $templates_config[ $template_key ];

        $raw_to = ! empty( $email_settings['recipients'] )
            ? $email_settings['recipients']
            : get_option( 'admin_email' );

        $to_list = is_array( $raw_to )
            ? $raw_to
            : explode( ',', (string) $raw_to );

        $to_list = array_values(
            array_filter(
                array_map( 'sanitize_email', array_map( 'trim', $to_list ) )
            )
        );

        if ( empty( $to_list ) ) {
            $to_list = array( get_option( 'admin_email' ) );
        }

        $subject = wp_strip_all_tags( strtr( $template['subject'], $placeholders ) );
        $body = strtr( $template['body'], $placeholders );
        $body = wpautop( wp_kses_post( $body ) );

        return $this->send_email( $to_list, $subject, $body, $email_settings, true );
    }

    /**
     * Send bad feedback Slack notification
     *
     * @param int    $query_id Query ID.
     * @param string $user_query User's query.
     * @return void
     */
    private function send_bad_feedback_slack( int $query_id, string $user_query ): void {
        $settings = get_option( 'promptor_notification_settings', array() );
        $slack_settings = $settings['slack'] ?? array();

        if ( empty( $slack_settings['enabled'] ) || empty( $slack_settings['webhook_url'] ) ) {
            return;
        }

        $message = sprintf(
            "👎 *Bad AI Feedback on %s*\n> *User Query:* `%s`\n> _Check the Queries Log for full details._",
            esc_html( get_bloginfo( 'name' ) ),
            esc_html( $user_query )
        );

        $this->send_slack( $message, $slack_settings );
    }

    /**
     * Send good feedback Slack notification
     *
     * @param int    $query_id Query ID.
     * @param string $user_query User's query.
     * @return void
     */
    private function send_good_feedback_slack( int $query_id, string $user_query ): void {
        $settings = get_option( 'promptor_notification_settings', array() );
        $slack_settings = $settings['slack'] ?? array();

        if ( empty( $slack_settings['enabled'] ) || empty( $slack_settings['webhook_url'] ) ) {
            return;
        }

        $message = sprintf(
            "👍 *Good AI Feedback on %s*\n> *User Query:* `%s`",
            esc_html( get_bloginfo( 'name' ) ),
            esc_html( $user_query )
        );

        $this->send_slack( $message, $slack_settings );
    }

    /**
     * Send query error Slack notification
     *
     * @param string $user_query User's query.
     * @param string $error_message Error message.
     * @return void
     */
    private function send_query_error_slack( string $user_query, string $error_message ): void {
        $settings = get_option( 'promptor_notification_settings', array() );
        $slack_settings = $settings['slack'] ?? array();

        if ( empty( $slack_settings['enabled'] ) || empty( $slack_settings['webhook_url'] ) ) {
            return;
        }

        $message = sprintf(
            "🚨 *AI Query Error on %s*\n> *User Query:* `%s`\n> *Error:* `%s`\n> _Please check your API settings and OpenAI status page._",
            esc_html( get_bloginfo( 'name' ) ),
            esc_html( $user_query ),
            esc_html( $error_message )
        );

        $this->send_slack( $message, $slack_settings );
    }

    /**
     * Send email
     *
     * @param array  $to Recipients.
     * @param string $subject Email subject.
     * @param string $body Email body.
     * @param array  $email_settings Email settings.
     * @param bool   $is_html Whether email is HTML.
     * @return bool True on success, false on failure.
     */
    private function send_email( array $to, string $subject, string $body, array $email_settings, bool $is_html = false ): bool {
        $server_name = isset( $_SERVER['SERVER_NAME'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) )
            : wp_parse_url( home_url(), PHP_URL_HOST );

        $domain = preg_replace( '#^www\.#', '', strtolower( (string) $server_name ) );

        $from_name = ! empty( $email_settings['from_name'] )
            ? wp_strip_all_tags( $email_settings['from_name'] )
            : wp_strip_all_tags( get_bloginfo( 'name' ) );

        $from_email = ! empty( $email_settings['from_email'] )
            ? sanitize_email( $email_settings['from_email'] )
            : 'wordpress@' . $domain;

        if ( ! is_email( $from_email ) ) {
            $from_email = 'wordpress@' . $domain;
        }

        // Wrap body in branded HTML template.
        if ( class_exists( 'Promptor_Email_Template' ) ) {
            $body    = Promptor_Email_Template::wrap( $subject, $body, 'info' );
            $is_html = true;
        }

        $headers = array(
            "From: {$from_name} <{$from_email}>",
            $is_html ? 'Content-Type: text/html; charset=UTF-8' : 'Content-Type: text/plain; charset=UTF-8',
        );

        return wp_mail( $to, $subject, $body, $headers );
    }

    /**
     * Send Slack notification
     *
     * @param string $message Message text.
     * @param array  $slack_settings Slack settings.
     * @return void
     */
    private function send_slack( string $message, array $slack_settings ): void {
        $payload = array( 'text' => $message );

        if ( ! empty( $slack_settings['channel'] ) ) {
            $payload['channel'] = $slack_settings['channel'];
        }

        if ( ! empty( $slack_settings['bot_name'] ) ) {
            $payload['username'] = $slack_settings['bot_name'];
        }

        $webhook = isset( $slack_settings['webhook_url'] )
            ? esc_url_raw( $slack_settings['webhook_url'] )
            : '';

        if ( empty( $webhook ) || 'https' !== wp_parse_url( $webhook, PHP_URL_SCHEME ) ) {
            return;
        }

        wp_remote_post(
            $webhook,
            array(
                'body'    => wp_json_encode( $payload ),
                'headers' => array( 'Content-Type' => 'application/json' ),
                'timeout' => 15,
            )
        );
    }

    /**
     * Log error message
     *
     * @param string $message Error message.
     * @return void
     */
    private function log_error( string $message ): void {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'Promptor: ' . $message );
        }
    }

    /**
     * Invalidate cache for context
     *
     * @param string $context_name Context name.
     * @return void
     */
    public function invalidate_context_cache( string $context_name ): void {
        wp_cache_delete( 'promptor_chunks_' . $context_name, self::CACHE_GROUP );
        wp_cache_delete( 'promptor_titles_' . $context_name, self::CACHE_GROUP );
    }
}