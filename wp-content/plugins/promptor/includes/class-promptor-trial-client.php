<?php
/**
 * Promptor Trial Client
 *
 * Handles trial system storage and Supabase API communication.
 * Allows users to try Promptor without an OpenAI API key.
 *
 * @package Promptor
 * @since   1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Promptor_Trial_Client {

	/**
	 * Option name for trial data storage.
	 */
	private const OPTION_NAME = 'promptor_trial_data';

	/**
	 * Transient name for status cache (throttle).
	 */
	private const STATUS_CACHE_KEY = 'promptor_trial_status_cache';

	/**
	 * How long to cache trial status (seconds).
	 */
	private const STATUS_CACHE_TTL = 300; // 5 minutes

	/**
	 * Get the Supabase Edge Function base URL.
	 *
	 * Works out-of-the-box with the Promptor-managed backend.
	 * Define PROMPTOR_TRIAL_API_URL in wp-config.php only for staging/dev override.
	 *
	 * @return string Base URL (no trailing slash).
	 */
	private static function get_base_url(): string {
		if ( defined( 'PROMPTOR_TRIAL_API_URL' ) && PROMPTOR_TRIAL_API_URL ) {
			return rtrim( PROMPTOR_TRIAL_API_URL, '/' );
		}
		return 'https://yjkfsxbxpreannhzoyad.supabase.co/functions/v1';
	}

	// ──────────────────────────────────────────────
	// Trial Data Storage Helpers
	// ──────────────────────────────────────────────

	/**
	 * Get trial data from wp_options.
	 *
	 * @return array Trial data or empty array.
	 */
	public static function get_trial_data(): array {
		$data = get_option( self::OPTION_NAME, array() );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Set trial data in wp_options.
	 *
	 * @param array $data Trial data to store.
	 * @return bool True on success.
	 */
	public static function set_trial_data( array $data ): bool {
		return update_option( self::OPTION_NAME, $data, false );
	}

	/**
	 * Clear trial data.
	 *
	 * @return bool True on success.
	 */
	public static function clear_trial_data(): bool {
		delete_transient( self::STATUS_CACHE_KEY );
		return delete_option( self::OPTION_NAME );
	}

	/**
	 * Check if trial is currently active and usable.
	 *
	 * @return bool True if trial can be used for queries.
	 */
	public static function is_trial_active(): bool {
		$data = self::get_trial_data();

		if ( empty( $data['token'] ) || empty( $data['status'] ) ) {
			return false;
		}

		if ( 'active' !== $data['status'] ) {
			return false;
		}

		// Check local expiry
		if ( ! empty( $data['expires_at'] ) && time() > (int) $data['expires_at'] ) {
			// Update local status
			$data['status'] = 'expired';
			self::set_trial_data( $data );
			return false;
		}

		// Check remaining queries
		if ( isset( $data['remaining_queries'] ) && (int) $data['remaining_queries'] <= 0 ) {
			$data['status'] = 'exhausted';
			self::set_trial_data( $data );
			return false;
		}

		return true;
	}

	/**
	 * Check if a trial has ever been activated (regardless of status).
	 *
	 * @return bool True if trial data exists with a token.
	 */
	public static function has_trial(): bool {
		$data = self::get_trial_data();
		return ! empty( $data['token'] );
	}

	/**
	 * Get the trial token.
	 *
	 * @return string Token or empty string.
	 */
	public static function get_token(): string {
		$data = self::get_trial_data();
		return $data['token'] ?? '';
	}

	// ──────────────────────────────────────────────
	// Supabase API Methods
	// ──────────────────────────────────────────────

	/**
	 * Activate a new trial.
	 *
	 * @return array{success: bool, message: string, data?: array} Result.
	 */
	public static function activate_trial(): array {
		// Don't activate if user already has an API key
		$api_settings = get_option( 'promptor_api_settings', array() );
		if ( ! empty( $api_settings['api_key'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'You already have an API key configured. No trial needed.', 'promptor' ),
			);
		}

		// Don't re-activate if trial already exists
		if ( self::has_trial() ) {
			$data = self::get_trial_data();
			if ( 'active' === ( $data['status'] ?? '' ) ) {
				return array(
					'success' => true,
					'message' => __( 'Trial is already active.', 'promptor' ),
					'data'    => $data,
				);
			}
		}

		$endpoint = self::get_base_url() . '/trial-activate';

		// Build payload matching backend contract:
		// { installation_id, site_url_hash, home_url_hash, server_ip_hash? }
		global $wpdb;
		$site_url_raw    = site_url();
		$home_url_raw    = home_url();
		$installation_id = md5( $site_url_raw . '|' . $home_url_raw . '|' . $wpdb->prefix );

		$request_body = wp_json_encode( array(
			'installation_id' => $installation_id,
			'site_url_hash'   => hash( 'sha256', $site_url_raw ),
			'home_url_hash'   => hash( 'sha256', $home_url_raw ),
		) );

		$response = wp_remote_post( $endpoint, array(
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body'    => $request_body,
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			$wp_error_msg = $response->get_error_message();
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: technical error detail */
					__( 'Could not connect to trial service: %s', 'promptor' ),
					$wp_error_msg
				),
			);
		}

		$status_code   = wp_remote_retrieve_response_code( $response );
		$raw_body      = wp_remote_retrieve_body( $response );
		$body          = json_decode( $raw_body, true );

		if ( 200 !== $status_code && 201 !== $status_code ) {
			$error_msg = $body['error'] ?? $body['message'] ?? $raw_body;
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: 1: HTTP status code, 2: error detail */
					__( 'Trial activation failed (HTTP %1$d): %2$s', 'promptor' ),
					$status_code,
					sanitize_text_field( mb_substr( $error_msg, 0, 200 ) )
				),
			);
		}

		// Backend wraps response as { ok: true, data: { token, trial_id, expires_at, limits } }
		$data = $body['data'] ?? array();

		if ( empty( $data['token'] ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: raw response excerpt */
					__( 'Invalid response from trial service (no token). Body: %s', 'promptor' ),
					sanitize_text_field( mb_substr( $raw_body, 0, 200 ) )
				),
			);
		}

		$limits = $data['limits'] ?? array();

		// Store trial data locally
		$trial_data = array(
			'token'             => sanitize_text_field( $data['token'] ),
			'status'            => 'active',
			'remaining_queries' => isset( $limits['max_queries'] ) ? (int) $limits['max_queries'] : 10,
			'remaining_pages'   => isset( $limits['max_pages'] ) ? (int) $limits['max_pages'] : 3,
			'expires_at'        => ! empty( $data['expires_at'] ) ? (int) strtotime( $data['expires_at'] ) : ( time() + 7 * DAY_IN_SECONDS ),
			'activated_at'      => time(),
		);

		self::set_trial_data( $trial_data );

		// Mark onboarding step 1 as completed for trial
		set_transient( 'promptor_api_connection_success', true, WEEK_IN_SECONDS );

		return array(
			'success' => true,
			'message' => __( 'Trial activated successfully!', 'promptor' ),
			'data'    => $trial_data,
		);
	}

	/**
	 * Refresh trial status from Supabase (with throttle).
	 *
	 * @param bool $force Skip cache and force refresh.
	 * @return array{success: bool, data?: array, message?: string} Result.
	 */
	public static function get_status( bool $force = false ): array {
		$token = self::get_token();
		if ( empty( $token ) ) {
			return array(
				'success' => false,
				'message' => __( 'No trial active.', 'promptor' ),
			);
		}

		// Throttle: return cached data unless forced
		if ( ! $force ) {
			$cached = get_transient( self::STATUS_CACHE_KEY );
			if ( false !== $cached && is_array( $cached ) ) {
				return array(
					'success' => true,
					'data'    => $cached,
				);
			}
		}

		$response = wp_remote_get( self::get_base_url() . '/trial-status', array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
			),
			'timeout' => 10,
		) );

		if ( is_wp_error( $response ) ) {
			// On network error, return local data
			return array(
				'success' => true,
				'data'    => self::get_trial_data(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status_code ) {
			// If revoked or invalid token
			if ( 401 === $status_code || 403 === $status_code ) {
				$data = self::get_trial_data();
				$data['status'] = 'revoked';
				self::set_trial_data( $data );
			}
			return array(
				'success' => false,
				'message' => $body['error'] ?? __( 'Could not check trial status.', 'promptor' ),
			);
		}

		// Update local data from server — response is wrapped as { ok, data: { ... } }
		$server = $body['data'] ?? $body;
		$trial_data = self::get_trial_data();
		$trial_data['status']            = sanitize_key( $server['status'] ?? $trial_data['status'] ?? 'active' );
		$trial_data['remaining_queries'] = isset( $server['queries_remaining'] ) ? (int) $server['queries_remaining'] : ( $trial_data['remaining_queries'] ?? 0 );
		$trial_data['remaining_pages']   = isset( $server['pages_remaining'] ) ? (int) $server['pages_remaining'] : ( $trial_data['remaining_pages'] ?? 0 );

		if ( ! empty( $server['expires_at'] ) ) {
			$trial_data['expires_at'] = (int) strtotime( $server['expires_at'] );
		}

		self::set_trial_data( $trial_data );
		set_transient( self::STATUS_CACHE_KEY, $trial_data, self::STATUS_CACHE_TTL );

		return array(
			'success' => true,
			'data'    => $trial_data,
		);
	}

	/**
	 * Proxy a chat request through Supabase openai-proxy.
	 *
	 * Translates from OpenAI request format to proxy format on the way in,
	 * and translates the proxy response back to OpenAI format on the way out,
	 * so the rest of the chat handler works without changes.
	 *
	 * Proxy expects: { user_message, context?, page_identifier, history? }
	 * Proxy returns: { ok, data: { reply, usage } }
	 *
	 * @param array $request_body OpenAI-format request body (messages array, model, etc.).
	 * @param int   $timeout      Request timeout in seconds.
	 * @return array|WP_Error Fake HTTP response shaped like OpenAI, or WP_Error.
	 */
	public static function proxy_chat( array $request_body, int $timeout = 45 ) {
		$token = self::get_token();

		if ( empty( $token ) ) {
			return new WP_Error( 'no_trial_token', __( 'No active trial. Please activate a trial or enter your OpenAI API key.', 'promptor' ) );
		}

		// --- Translate OpenAI format → proxy format ---
		// The proxy expects: { user_message, context?, page_identifier, history? }
		// The plugin sends OpenAI format: { messages: [{role, content}, ...] }
		$messages     = $request_body['messages'] ?? array();
		$user_message = '';
		$context      = '';
		$history      = array();

		foreach ( $messages as $msg ) {
			$role    = $msg['role'] ?? '';
			$content = $msg['content'] ?? '';

			if ( 'system' === $role ) {
				// Accumulate system messages as context for the proxy
				$context .= $content . "\n";
			} elseif ( 'user' === $role ) {
				$user_message = $content; // Last user message wins
				$history[]    = array( 'role' => 'user', 'content' => $content );
			} elseif ( 'assistant' === $role ) {
				$history[] = array( 'role' => 'assistant', 'content' => $content );
			}
		}

		// Fallback: build_fallback_request() may embed the user query inside the
		// system prompt without a separate user message. Extract it so the proxy
		// gets a non-empty user_message (required field).
		if ( '' === $user_message && '' !== $context ) {
			// Try to pull the query from "The user asked: '...'" pattern
			if ( preg_match( "/The user asked:\\s*'([^']+)'/", $context, $m ) ) {
				$user_message = $m[1];
			} else {
				// Last resort — send a generic prompt so the proxy doesn't 400
				$user_message = 'Hello';
			}
		}

		// page_identifier is required by proxy — use site URL hash as a stable identifier
		$page_identifier = md5( home_url() );

		$proxy_body = array(
			'user_message'    => $user_message,
			'page_identifier' => $page_identifier,
		);
		if ( '' !== trim( $context ) ) {
			$proxy_body['context'] = mb_substr( trim( $context ), 0, 4000 );
		}
		// Send history without the last user message (already in user_message)
		if ( count( $history ) > 1 ) {
			$proxy_body['history'] = array_slice( $history, 0, -1 );
		}

		$response = wp_remote_post( self::get_base_url() . '/openai-proxy', array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $proxy_body ),
			'timeout' => $timeout,
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'proxy_request_failed', __( 'Failed to connect to AI service. Please try again.', 'promptor' ) );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );
		$body        = json_decode( $raw_body, true );

		// Handle trial-specific errors from proxy
		if ( 200 !== $status_code ) {
			$error = $body['error'] ?? '';

			if ( 429 === $status_code || 'exhausted' === $error || 'trial_exhausted' === $error || 'query_limit_reached' === $error ) {
				$data = self::get_trial_data();
				$data['status'] = 'exhausted';
				$data['remaining_queries'] = 0;
				self::set_trial_data( $data );
				delete_transient( self::STATUS_CACHE_KEY );

				return new WP_Error(
					'trial_exhausted',
					__( 'Your free trial queries have been used up. Please enter your OpenAI API key to continue using Promptor.', 'promptor' )
				);
			}

			if ( 403 === $status_code || 'expired' === $error || 'trial_expired' === $error ) {
				$data = self::get_trial_data();
				$data['status'] = 'expired';
				self::set_trial_data( $data );
				delete_transient( self::STATUS_CACHE_KEY );

				return new WP_Error(
					'trial_expired',
					__( 'Your free trial has expired. Please enter your OpenAI API key to continue using Promptor.', 'promptor' )
				);
			}

			if ( 401 === $status_code || 'revoked' === $error ) {
				$data = self::get_trial_data();
				$data['status'] = 'revoked';
				self::set_trial_data( $data );
				delete_transient( self::STATUS_CACHE_KEY );

				return new WP_Error(
					'trial_revoked',
					__( 'Your trial access has been revoked. Please enter your OpenAI API key.', 'promptor' )
				);
			}

			// Generic proxy error
			return new WP_Error(
				'proxy_request_failed',
				sprintf(
					/* translators: 1: HTTP status code, 2: error detail */
					__( 'AI service error (HTTP %1$d): %2$s', 'promptor' ),
					$status_code,
					sanitize_text_field( mb_substr( $error ?: $raw_body, 0, 200 ) )
				)
			);
		}

		// --- Translate proxy response → OpenAI format ---
		// Proxy returns: { ok: true, data: { reply: "...", usage: { queries_used, queries_remaining, ... } } }
		$proxy_data = $body['data'] ?? array();
		$reply      = $proxy_data['reply'] ?? '';
		$usage      = $proxy_data['usage'] ?? array();

		// Update local trial counters from proxy response
		$trial_data = self::get_trial_data();
		if ( isset( $usage['queries_remaining'] ) ) {
			$trial_data['remaining_queries'] = (int) $usage['queries_remaining'];
		} elseif ( isset( $trial_data['remaining_queries'] ) && (int) $trial_data['remaining_queries'] > 0 ) {
			$trial_data['remaining_queries'] = (int) $trial_data['remaining_queries'] - 1;
		}
		if ( isset( $usage['pages_remaining'] ) ) {
			$trial_data['remaining_pages'] = (int) $usage['pages_remaining'];
		}
		self::set_trial_data( $trial_data );
		delete_transient( self::STATUS_CACHE_KEY );

		// Build a fake OpenAI-shaped response body so process_ai_response() works unchanged
		$fake_openai_body = wp_json_encode( array(
			'choices' => array(
				array(
					'message' => array(
						'content' => $reply,
						'role'    => 'assistant',
					),
					'finish_reason' => 'stop',
				),
			),
			'usage' => array(
				'prompt_tokens'     => 0,
				'completion_tokens' => 0,
				'total_tokens'      => 0,
			),
		) );

		// Build a fake WP HTTP response array that wp_remote_retrieve_* functions can read
		return array(
			'headers'  => array(),
			'body'     => $fake_openai_body,
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
		);
	}

	/**
	 * Convert trial to own API key (notify Supabase).
	 *
	 * @return array{success: bool, message: string} Result.
	 */
	public static function convert_trial(): array {
		$token = self::get_token();

		if ( empty( $token ) ) {
			return array(
				'success' => true,
				'message' => __( 'No trial to convert.', 'promptor' ),
			);
		}

		// Notify Supabase (fire-and-forget, non-blocking)
		wp_remote_post( self::get_base_url() . '/trial-convert', array(
			'headers'  => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
			'body'     => wp_json_encode( array( 'action' => 'convert' ) ),
			'timeout'  => 5,
			'blocking' => false,
		) );

		// Update local status
		$data = self::get_trial_data();
		$data['status'] = 'converted';
		self::set_trial_data( $data );
		delete_transient( self::STATUS_CACHE_KEY );

		return array(
			'success' => true,
			'message' => __( 'Trial converted. You are now using your own API key.', 'promptor' ),
		);
	}

	// ──────────────────────────────────────────────
	// Trial Mini Knowledge Base (v1.4.0)
	// ──────────────────────────────────────────────

	/**
	 * Option name for trial context (extracted page content).
	 */
	private const CONTEXT_OPTION = 'promptor_trial_context';

	/**
	 * Max pages for trial KB.
	 */
	public const MAX_TRIAL_PAGES = 2;

	/**
	 * Max chars per page content.
	 */
	private const MAX_CHARS_PER_PAGE = 10000;

	/**
	 * Get stored trial context.
	 *
	 * @return array { pages: [ { id, title, url, content, word_count } ], suggested_questions: [] }
	 */
	public static function get_trial_context(): array {
		$ctx = get_option( self::CONTEXT_OPTION, array() );
		return is_array( $ctx ) ? $ctx : array();
	}

	/**
	 * Save trial context.
	 *
	 * @param array $context Context data.
	 * @return bool
	 */
	public static function set_trial_context( array $context ): bool {
		return update_option( self::CONTEXT_OPTION, $context, false );
	}

	/**
	 * Clear trial context.
	 *
	 * @return bool
	 */
	public static function clear_trial_context(): bool {
		return delete_option( self::CONTEXT_OPTION );
	}

	/**
	 * Extract clean text content from a post/page.
	 *
	 * @param int $post_id Post ID.
	 * @return array { id, title, url, content, word_count } or empty on failure.
	 */
	public static function extract_page_content( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return array();
		}

		// Render shortcodes and blocks without invoking the global 'the_content' filter
		// to avoid PHPCS PrefixAllGlobals false positive.
		$raw_content = do_shortcode( do_blocks( $post->post_content ) );

		// Strip scripts and styles first
		$content = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $raw_content );
		$content = preg_replace( '/<style\b[^>]*>.*?<\/style>/is', '', $content );

		// Strip all remaining HTML
		$content = wp_strip_all_tags( $content );

		// Normalize whitespace
		$content = preg_replace( '/\s+/', ' ', $content );
		$content = trim( $content );

		// Limit size
		$content = mb_substr( $content, 0, self::MAX_CHARS_PER_PAGE, 'UTF-8' );

		if ( empty( $content ) ) {
			return array();
		}

		return array(
			'id'         => $post_id,
			'title'      => get_the_title( $post_id ),
			'url'        => get_permalink( $post_id ),
			'content'    => $content,
			'word_count' => str_word_count( $content ),
		);
	}

	/**
	 * Build the combined context string from trial pages for use in proxy chat.
	 *
	 * @return string Combined context or empty string.
	 */
	public static function get_context_for_chat(): string {
		$ctx = self::get_trial_context();
		$pages = $ctx['pages'] ?? array();

		if ( empty( $pages ) ) {
			return '';
		}

		$parts = array();
		foreach ( $pages as $page ) {
			$title   = $page['title'] ?? '';
			$content = $page['content'] ?? '';
			if ( $content ) {
				$parts[] = sprintf( "--- %s ---\n%s", $title, $content );
			}
		}

		return implode( "\n\n", $parts );
	}

	/**
	 * Generate suggested questions based on trial context content.
	 * Uses a lightweight heuristic (no API call) to extract questions.
	 *
	 * @param array $pages Array of extracted page data.
	 * @return array Up to 2 suggested questions.
	 */
	public static function generate_suggested_questions( array $pages ): array {
		$questions = array();
		$site_name = get_bloginfo( 'name' );

		foreach ( $pages as $page ) {
			$title = $page['title'] ?? '';
			if ( empty( $title ) ) {
				continue;
			}

			// Generate a natural question from the page title
			$title_lower = mb_strtolower( $title, 'UTF-8' );

			// Common page patterns → questions
			if ( preg_match( '/about|hakkımızda|über uns|à propos/i', $title ) ) {
				$questions[] = sprintf(
					/* translators: %s: site name */
					__( 'What does %s do?', 'promptor' ),
					$site_name
				);
			} elseif ( preg_match( '/contact|iletişim|kontakt/i', $title ) ) {
				$questions[] = sprintf(
					/* translators: %s: site name */
					__( 'How can I contact %s?', 'promptor' ),
					$site_name
				);
			} elseif ( preg_match( '/service|hizmet|dienstleistung/i', $title ) ) {
				$questions[] = __( 'What services do you offer?', 'promptor' );
			} elseif ( preg_match( '/pric|fiyat|preis|tarif/i', $title ) ) {
				$questions[] = __( 'What are your prices?', 'promptor' );
			} elseif ( preg_match( '/faq|sss|häufig/i', $title ) ) {
				$questions[] = __( 'What are your frequently asked questions?', 'promptor' );
			} else {
				// Generic: "Tell me about [page title]"
				$questions[] = sprintf(
					/* translators: %s: page title */
					__( 'Tell me about %s', 'promptor' ),
					$title
				);
			}

			if ( count( $questions ) >= 2 ) {
				break;
			}
		}

		// Ensure at least one question
		if ( empty( $questions ) ) {
			$questions[] = sprintf(
				/* translators: %s: site name */
				__( 'What can you tell me about %s?', 'promptor' ),
				$site_name
			);
		}

		return array_slice( array_unique( $questions ), 0, 2 );
	}
}
