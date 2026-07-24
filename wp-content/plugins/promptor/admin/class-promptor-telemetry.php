<?php
/**
 * Anonymous telemetry system for Promptor (opt-in only).
 *
 * Collects anonymous feature usage metrics to help improve the plugin.
 * NO personal data (URLs, IPs, emails, content, queries) is collected.
 *
 * @package Promptor
 * @since   1.2.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Promptor_Telemetry {

	/**
	 * Telemetry endpoint URL (Promptor-managed backend).
	 * Works out-of-the-box. Define PROMPTOR_TELEMETRY_ENDPOINT in wp-config.php only for staging/dev override.
	 *
	 * @var string
	 */
	const ENDPOINT = 'https://yjkfsxbxpreannhzoyad.supabase.co/functions/v1/telemetry-collect';

	/**
	 * Built-in HMAC signing key for telemetry requests.
	 * Works out-of-the-box. Define PROMPTOR_TELEMETRY_HMAC_SECRET in wp-config.php only for staging/dev override.
	 *
	 * @var string
	 */
	const HMAC_SECRET = 'ptr_hmac_Kx8vQ2mN7jL4wR9s';

	/**
	 * Cron hook name.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'promptor_telemetry_send';

	/**
	 * Initialize telemetry system.
	 */
	public static function init() {
		// Schedule cron if enabled
		add_action( 'init', array( __CLASS__, 'maybe_schedule_cron' ) );

		// Cron action
		add_action( self::CRON_HOOK, array( __CLASS__, 'send_telemetry' ) );

		// Privacy policy
		add_action( 'admin_init', array( __CLASS__, 'add_privacy_policy' ) );

		// Track events
		add_action( 'promptor_query_sent', array( __CLASS__, 'track_query' ) );
		add_action( 'promptor_lead_captured', array( __CLASS__, 'track_lead' ) );
		add_action( 'promptor_step_completed', array( __CLASS__, 'track_step_completion' ), 10, 1 );
		add_action( 'promptor_kb_updated', array( __CLASS__, 'track_kb_update' ) );
	}

	/**
	 * Check if telemetry is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return (bool) get_option( 'promptor_telemetry_enabled', false );
	}

	/**
	 * Get or create anonymous telemetry ID (UUID v4).
	 *
	 * @return string
	 */
	public static function get_id() {
		$id = get_option( 'promptor_telemetry_id', '' );

		if ( empty( $id ) ) {
			$id = self::generate_uuid_v4();
			update_option( 'promptor_telemetry_id', $id );
		}

		return $id;
	}

	/**
	 * Generate UUID v4.
	 *
	 * @return string
	 */
	private static function generate_uuid_v4() {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}

		// Fallback: manual UUID v4 generation
		$data    = openssl_random_pseudo_bytes( 16 );
		$data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 ); // Version 4
		$data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 ); // Variant

		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
	}

	/**
	 * Reset telemetry ID (generate new UUID).
	 */
	public static function reset_id() {
		delete_option( 'promptor_telemetry_id' );
		return self::get_id();
	}

	/**
	 * Get current telemetry queue.
	 *
	 * @return array
	 */
	public static function get_queue() {
		$queue = get_option( 'promptor_telemetry_queue', array() );
		return is_array( $queue ) ? $queue : array();
	}

	/**
	 * Update telemetry queue with current metrics.
	 */
	public static function update_queue() {
		$queue = array(
			'timestamp'              => gmdate( 'Y-m-d H:i:s' ),
			'telemetry_id'           => self::get_id(),
			'plugin_version'         => PROMPTOR_VERSION,
			'wp_version'             => get_bloginfo( 'version' ),
			'php_version'            => phpversion(),
			'setup_step_1_completed' => (bool) get_option( 'promptor_telemetry_step_1_completed', false ),
			'setup_step_2_completed' => (bool) get_option( 'promptor_telemetry_step_2_completed', false ),
			'setup_step_3_completed' => (bool) get_option( 'promptor_telemetry_step_3_completed', false ),
			'kb_count'               => (int) get_option( 'promptor_telemetry_kb_count', 0 ),
			'kb_items_total'         => (int) get_option( 'promptor_telemetry_kb_items_total', 0 ),
			'first_query_sent'       => (bool) get_option( 'promptor_telemetry_first_query_sent', false ),
			'queries_total'          => (int) get_option( 'promptor_telemetry_queries_total', 0 ),
			'first_lead_captured'    => (bool) get_option( 'promptor_telemetry_first_lead_captured', false ),
			'leads_total'            => (int) get_option( 'promptor_telemetry_leads_total', 0 ),
		);

		update_option( 'promptor_telemetry_queue', $queue );
		return $queue;
	}

	/**
	 * Clear telemetry queue.
	 */
	public static function clear_queue() {
		delete_option( 'promptor_telemetry_queue' );
	}

	/**
	 * Track query sent.
	 */
	public static function track_query() {
		if ( ! self::is_enabled() ) {
			return;
		}

		// Mark first query if not already marked
		if ( ! get_option( 'promptor_telemetry_first_query_sent', false ) ) {
			update_option( 'promptor_telemetry_first_query_sent', true );
		}

		// Increment query count
		$count = (int) get_option( 'promptor_telemetry_queries_total', 0 );
		update_option( 'promptor_telemetry_queries_total', $count + 1 );
	}

	/**
	 * Track lead captured.
	 */
	public static function track_lead() {
		if ( ! self::is_enabled() ) {
			return;
		}

		// Mark first lead if not already marked
		if ( ! get_option( 'promptor_telemetry_first_lead_captured', false ) ) {
			update_option( 'promptor_telemetry_first_lead_captured', true );
		}

		// Increment lead count
		$count = (int) get_option( 'promptor_telemetry_leads_total', 0 );
		update_option( 'promptor_telemetry_leads_total', $count + 1 );
	}

	/**
	 * Track step completion.
	 *
	 * @param int $step_number Step number (1, 2, or 3).
	 */
	public static function track_step_completion( $step_number ) {
		if ( ! self::is_enabled() ) {
			return;
		}

		$option_key = 'promptor_telemetry_step_' . (int) $step_number . '_completed';
		$already_completed = get_option( $option_key, false );

		update_option( $option_key, true );

		// Send telemetry immediately when setup is completed (all 3 steps)
		if ( $step_number === 3 && ! $already_completed ) {
			self::send_telemetry( true ); // Force send
		}
	}

	/**
	 * Track KB update (count and total items).
	 *
	 * @param bool $send_immediately Whether to send telemetry immediately.
	 */
	public static function track_kb_update( $send_immediately = false ) {
		if ( ! self::is_enabled() ) {
			return;
		}

		global $wpdb;

		// Count KBs
		$contexts = get_option( 'promptor_contexts', array() );
		$kb_count = is_array( $contexts ) ? count( $contexts ) : 0;
		update_option( 'promptor_telemetry_kb_count', $kb_count );

		// Count total indexed items
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_items = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->prefix}promptor_embeddings"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$old_total = get_option( 'promptor_telemetry_kb_items_total', 0 );
		update_option( 'promptor_telemetry_kb_items_total', $total_items );

		// Send immediately if this is first KB sync or requested
		if ( $send_immediately || ( $total_items > 0 && $old_total === 0 ) ) {
			self::send_telemetry( true ); // Force send
		}
	}

	/**
	 * Schedule cron job if telemetry is enabled.
	 */
	public static function maybe_schedule_cron() {
		$enabled   = self::is_enabled();
		$scheduled = wp_next_scheduled( self::CRON_HOOK );

		if ( $enabled && ! $scheduled ) {
			// Schedule twice daily
			wp_schedule_event( time(), 'twicedaily', self::CRON_HOOK );
		} elseif ( ! $enabled && $scheduled ) {
			// Unschedule if disabled
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}
	}

	/**
	 * Send telemetry data to endpoint.
	 *
	 * @param bool $force_send Force send even if recently sent (for event triggers).
	 */
	public static function send_telemetry( $force_send = false ) {
		if ( ! self::is_enabled() ) {
			return false;
		}

		// Rate limit: Don't send more than once per hour (unless forced)
		if ( ! $force_send ) {
			$last_send = get_option( 'promptor_telemetry_last_send', 0 );
			if ( ( time() - $last_send ) < HOUR_IN_SECONDS ) {
				return false;
			}
		}

		// Update queue with latest metrics
		$data = self::update_queue();

		// Resolve endpoint and HMAC secret.
		// Built-in defaults work out-of-the-box; wp-config.php constants are optional staging/dev overrides.
		$endpoint = defined( 'PROMPTOR_TELEMETRY_ENDPOINT' )
			? PROMPTOR_TELEMETRY_ENDPOINT
			: self::ENDPOINT;

		$hmac_secret = defined( 'PROMPTOR_TELEMETRY_HMAC_SECRET' )
			? PROMPTOR_TELEMETRY_HMAC_SECRET
			: self::HMAC_SECRET;

		// Compute HMAC-SHA256 signature
		$json_body = wp_json_encode( $data );
		$signature = 'sha256=' . hash_hmac( 'sha256', $json_body, $hmac_secret );

		$headers = array(
			'Content-Type'         => 'application/json',
			'User-Agent'           => 'Promptor/' . PROMPTOR_VERSION,
			'X-Promptor-Signature' => $signature,
		);

		// Send to endpoint
		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 10,
				'headers' => $headers,
				'body'    => $json_body,
			)
		);

		// Log errors only (debug mode)
		if ( is_wp_error( $response ) && defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Promptor Telemetry: Failed to send - ' . $response->get_error_message() );
		}

		// Clear queue and update last send time after successful send
		if ( ! is_wp_error( $response ) ) {
			$code = wp_remote_retrieve_response_code( $response );
			if ( $code >= 200 && $code < 300 ) {
				self::clear_queue();
				update_option( 'promptor_telemetry_last_send', time() );
				return true;
			}
		}

		return false;
	}

	/**
	 * Add privacy policy content.
	 */
	public static function add_privacy_policy() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = sprintf(
			'<h2>%s</h2><p>%s</p><h3>%s</h3><p>%s</p><ul><li>%s</li><li>%s</li><li>%s</li><li>%s</li></ul><h3>%s</h3><p>%s</p><ul><li>%s</li><li>%s</li><li>%s</li><li>%s</li><li>%s</li></ul>',
			esc_html__( 'Promptor Anonymous Telemetry', 'promptor' ),
			esc_html__( 'Promptor includes an optional anonymous telemetry feature to help us understand how the plugin is used and improve it. This feature is OFF by default and requires your explicit opt-in.', 'promptor' ),
			esc_html__( 'What We Collect (If You Opt-In)', 'promptor' ),
			esc_html__( 'If you choose to enable anonymous telemetry, we collect only the following non-personal usage metrics:', 'promptor' ),
			esc_html__( 'Setup completion status (which onboarding steps completed)', 'promptor' ),
			esc_html__( 'Knowledge base count and total indexed items', 'promptor' ),
			esc_html__( 'Total number of queries and leads (counts only, no content)', 'promptor' ),
			esc_html__( 'Plugin version, WordPress version, and PHP version', 'promptor' ),
			esc_html__( 'What We Do NOT Collect', 'promptor' ),
			esc_html__( 'We explicitly do NOT collect:', 'promptor' ),
			esc_html__( 'Site URLs or domain names', 'promptor' ),
			esc_html__( 'IP addresses', 'promptor' ),
			esc_html__( 'Admin emails or usernames', 'promptor' ),
			esc_html__( 'Any content, message text, or queries', 'promptor' ),
			esc_html__( 'Any personally identifiable information (PII)', 'promptor' )
		);

		wp_add_privacy_policy_content( 'Promptor', $content );
	}

	/**
	 * Enable telemetry (opt-in).
	 *
	 * Immediately sends first telemetry payload for instant validation.
	 */
	public static function enable() {
		update_option( 'promptor_telemetry_enabled', true );
		update_option( 'promptor_telemetry_enabled_at', time() ); // Track when enabled
		self::get_id(); // Ensure ID exists
		self::sync_current_state(); // Sync existing setup state
		self::maybe_schedule_cron();

		// Immediately send first telemetry payload
		self::send_telemetry();
	}

	/**
	 * Disable telemetry (opt-out).
	 */
	public static function disable() {
		update_option( 'promptor_telemetry_enabled', false );
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Sync current installation state to telemetry.
	 *
	 * IMPORTANT: This only runs AFTER the user explicitly enables telemetry.
	 * It checks the current setup status (completed steps, existing queries/leads)
	 * to provide accurate metrics. No data is sent until the user opts in.
	 *
	 * @since 1.2.1
	 */
	public static function sync_current_state() {
		// Load onboarding class for state checks
		require_once PROMPTOR_PATH . 'admin/class-promptor-onboarding.php';

		// Sync setup steps
		if ( Promptor_Onboarding::is_step_1_completed() ) {
			update_option( 'promptor_telemetry_step_1_completed', true );
		}
		if ( Promptor_Onboarding::is_step_2_completed() ) {
			update_option( 'promptor_telemetry_step_2_completed', true );
		}
		if ( Promptor_Onboarding::is_step_3_completed() ) {
			update_option( 'promptor_telemetry_step_3_completed', true );
		}

		// Sync KB data
		self::track_kb_update();

		// Sync query/lead data from database
		global $wpdb;

		// Count queries
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$queries_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}promptor_queries"
		);
		if ( $queries_count > 0 ) {
			update_option( 'promptor_telemetry_first_query_sent', true );
			update_option( 'promptor_telemetry_queries_total', $queries_count );
		}

		// Count leads
		$leads_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}promptor_submissions"
		);
		if ( $leads_count > 0 ) {
			update_option( 'promptor_telemetry_first_lead_captured', true );
			update_option( 'promptor_telemetry_leads_total', $leads_count );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
