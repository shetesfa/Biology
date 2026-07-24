<?php
/**
 * AI Cost Guardrails for Promptor
 *
 * Tracks token usage, enforces spending limits, and handles model fallback.
 *
 * @package Promptor
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Promptor_Cost_Guard
 *
 * Provides token tracking, cost estimation, limit enforcement, and model fallback.
 */
class Promptor_Cost_Guard {

	/**
	 * Option key for usage data.
	 *
	 * @var string
	 */
	private const USAGE_OPTION = 'promptor_ai_usage';

	/**
	 * Option key for cost limit settings.
	 *
	 * @var string
	 */
	private const LIMITS_OPTION = 'promptor_cost_limits';

	/**
	 * OpenAI pricing per million tokens (as of Jan 2026).
	 *
	 * @var array
	 */
	private const PRICING = array(
		'gpt-4o'        => array( 'input' => 5.00, 'output' => 15.00 ),
		'gpt-4-turbo'   => array( 'input' => 10.00, 'output' => 30.00 ),
		'gpt-3.5-turbo' => array( 'input' => 0.50, 'output' => 1.50 ),
	);

	/**
	 * Fallback model when limits are reached.
	 *
	 * @var string
	 */
	private const FALLBACK_MODEL = 'gpt-3.5-turbo';

	/**
	 * Warning thresholds (percentage).
	 *
	 * @var array
	 */
	private const WARNING_THRESHOLDS = array( 80, 90, 100 );

	/**
	 * Get default cost limit settings.
	 *
	 * @return array Default settings.
	 */
	public static function get_defaults(): array {
		return array(
			'daily_enabled'   => false,
			'daily_limit'     => 100000,
			'monthly_enabled' => false,
			'monthly_limit'   => 2000000,
			'on_limit_action' => 'fallback', // 'pause', 'fallback', 'warn'
		);
	}

	/**
	 * Get current cost limit settings.
	 *
	 * @return array Merged settings with defaults.
	 */
	public static function get_limits(): array {
		$saved = get_option( self::LIMITS_OPTION, array() );
		return wp_parse_args( (array) $saved, self::get_defaults() );
	}

	/**
	 * Save cost limit settings.
	 *
	 * @param array $settings Settings to save.
	 * @return bool True on success.
	 */
	public static function save_limits( array $settings ): bool {
		$clean = array(
			'daily_enabled'   => ! empty( $settings['daily_enabled'] ),
			'daily_limit'     => max( 1000, absint( $settings['daily_limit'] ?? 100000 ) ),
			'monthly_enabled' => ! empty( $settings['monthly_enabled'] ),
			'monthly_limit'   => max( 10000, absint( $settings['monthly_limit'] ?? 2000000 ) ),
			'on_limit_action' => in_array( $settings['on_limit_action'] ?? '', array( 'pause', 'fallback', 'warn' ), true )
				? $settings['on_limit_action']
				: 'fallback',
		);

		return update_option( self::LIMITS_OPTION, $clean );
	}

	/**
	 * Get the full usage data array.
	 *
	 * @return array Usage data.
	 */
	public static function get_usage(): array {
		$usage = get_option( self::USAGE_OPTION, array() );
		if ( ! is_array( $usage ) ) {
			$usage = array();
		}
		if ( ! isset( $usage['daily'] ) || ! is_array( $usage['daily'] ) ) {
			$usage['daily'] = array();
		}
		if ( ! isset( $usage['monthly'] ) || ! is_array( $usage['monthly'] ) ) {
			$usage['monthly'] = array();
		}
		return $usage;
	}

	/**
	 * Calculate cost for a given model and token counts.
	 *
	 * @param string $model       Model name.
	 * @param int    $input_tokens  Number of input (prompt) tokens.
	 * @param int    $output_tokens Number of output (completion) tokens.
	 * @return float Cost in USD.
	 */
	public static function calculate_cost( string $model, int $input_tokens, int $output_tokens ): float {
		if ( ! isset( self::PRICING[ $model ] ) ) {
			return 0.0;
		}

		$pricing = self::PRICING[ $model ];

		return ( $input_tokens / 1000000 ) * $pricing['input']
			+ ( $output_tokens / 1000000 ) * $pricing['output'];
	}

	/**
	 * Record token usage after an API call.
	 *
	 * @param string $model         Model name used.
	 * @param int    $input_tokens  Prompt tokens.
	 * @param int    $output_tokens Completion tokens.
	 * @param string $usage_type    Type: 'conversation', 'kb_indexing', 'embedding'.
	 * @return void
	 */
	public static function track_usage( string $model, int $input_tokens, int $output_tokens, string $usage_type = 'conversation' ): void {
		$total  = $input_tokens + $output_tokens;
		$cost   = self::calculate_cost( $model, $input_tokens, $output_tokens );
		$today  = gmdate( 'Y-m-d' );
		$month  = gmdate( 'Y-m' );

		$usage = self::get_usage();

		// --- Daily ---
		if ( ! isset( $usage['daily'][ $today ] ) ) {
			$usage['daily'][ $today ] = self::empty_bucket();
		}

		$usage['daily'][ $today ]['tokens']                      += $total;
		$usage['daily'][ $today ]['cost']                        += $cost;
		$usage['daily'][ $today ][ $model . '_tokens' ]          = ( $usage['daily'][ $today ][ $model . '_tokens' ] ?? 0 ) + $total;
		$usage['daily'][ $today ][ $usage_type . '_tokens' ]     = ( $usage['daily'][ $today ][ $usage_type . '_tokens' ] ?? 0 ) + $total;

		// --- Monthly ---
		if ( ! isset( $usage['monthly'][ $month ] ) ) {
			$usage['monthly'][ $month ] = self::empty_bucket();
		}

		$usage['monthly'][ $month ]['tokens']                    += $total;
		$usage['monthly'][ $month ]['cost']                      += $cost;
		$usage['monthly'][ $month ][ $model . '_tokens' ]        = ( $usage['monthly'][ $month ][ $model . '_tokens' ] ?? 0 ) + $total;
		$usage['monthly'][ $month ][ $usage_type . '_tokens' ]   = ( $usage['monthly'][ $month ][ $usage_type . '_tokens' ] ?? 0 ) + $total;

		// Keep only last 90 days of daily data
		$cutoff = gmdate( 'Y-m-d', strtotime( '-90 days' ) );
		foreach ( array_keys( $usage['daily'] ) as $date_key ) {
			if ( $date_key < $cutoff ) {
				unset( $usage['daily'][ $date_key ] );
			}
		}

		// Keep only last 12 months
		$month_cutoff = gmdate( 'Y-m', strtotime( '-12 months' ) );
		foreach ( array_keys( $usage['monthly'] ) as $month_key ) {
			if ( $month_key < $month_cutoff ) {
				unset( $usage['monthly'][ $month_key ] );
			}
		}

		update_option( self::USAGE_OPTION, $usage, false );

		// Check thresholds and send warnings
		self::check_warning_thresholds( $usage );
	}

	/**
	 * Check if a query is allowed based on current limits.
	 *
	 * Returns one of: 'allowed', 'fallback', 'paused', 'warn'.
	 *
	 * @return string Status: 'allowed' | 'fallback' | 'paused' | 'warn'.
	 */
	public static function check_limits(): string {
		$limits = self::get_limits();

		// If no limits enabled, always allowed.
		if ( ! $limits['daily_enabled'] && ! $limits['monthly_enabled'] ) {
			return 'allowed';
		}

		$usage = self::get_usage();
		$today = gmdate( 'Y-m-d' );
		$month = gmdate( 'Y-m' );

		// Check daily limit.
		if ( $limits['daily_enabled'] ) {
			$daily_tokens = $usage['daily'][ $today ]['tokens'] ?? 0;

			if ( $daily_tokens >= $limits['daily_limit'] ) {
				return self::resolve_limit_action( $limits['on_limit_action'] );
			}
		}

		// Check monthly limit.
		if ( $limits['monthly_enabled'] ) {
			$monthly_tokens = $usage['monthly'][ $month ]['tokens'] ?? 0;

			if ( $monthly_tokens >= $limits['monthly_limit'] ) {
				return self::resolve_limit_action( $limits['on_limit_action'] );
			}
		}

		return 'allowed';
	}

	/**
	 * Get the model to use, considering fallback.
	 *
	 * If limit status is 'fallback', returns the fallback model.
	 *
	 * @param string $configured_model The model configured in settings.
	 * @return array [ 'model' => string, 'is_fallback' => bool ]
	 */
	public static function resolve_model( string $configured_model ): array {
		$status = self::check_limits();

		if ( 'fallback' === $status && $configured_model !== self::FALLBACK_MODEL ) {
			return array(
				'model'       => self::FALLBACK_MODEL,
				'is_fallback' => true,
			);
		}

		return array(
			'model'       => $configured_model,
			'is_fallback' => false,
		);
	}

	/**
	 * Get today's usage summary.
	 *
	 * @return array Summary with keys: tokens, cost, limit, percentage.
	 */
	public static function get_daily_summary(): array {
		$limits = self::get_limits();
		$usage  = self::get_usage();
		$today  = gmdate( 'Y-m-d' );
		$tokens = $usage['daily'][ $today ]['tokens'] ?? 0;
		$cost   = $usage['daily'][ $today ]['cost'] ?? 0.0;
		$limit  = $limits['daily_enabled'] ? $limits['daily_limit'] : 0;

		return array(
			'tokens'     => $tokens,
			'cost'       => round( $cost, 4 ),
			'limit'      => $limit,
			'percentage' => $limit > 0 ? min( 100, round( ( $tokens / $limit ) * 100, 1 ) ) : 0,
			'enabled'    => $limits['daily_enabled'],
		);
	}

	/**
	 * Get current month's usage summary.
	 *
	 * @return array Summary with keys: tokens, cost, limit, percentage.
	 */
	public static function get_monthly_summary(): array {
		$limits = self::get_limits();
		$usage  = self::get_usage();
		$month  = gmdate( 'Y-m' );
		$tokens = $usage['monthly'][ $month ]['tokens'] ?? 0;
		$cost   = $usage['monthly'][ $month ]['cost'] ?? 0.0;
		$limit  = $limits['monthly_enabled'] ? $limits['monthly_limit'] : 0;

		return array(
			'tokens'     => $tokens,
			'cost'       => round( $cost, 4 ),
			'limit'      => $limit,
			'percentage' => $limit > 0 ? min( 100, round( ( $tokens / $limit ) * 100, 1 ) ) : 0,
			'enabled'    => $limits['monthly_enabled'],
		);
	}

	/**
	 * Reset daily usage (called by cron or manually).
	 *
	 * @return void
	 */
	public static function reset_daily(): void {
		delete_transient( 'promptor_80_warning_daily' );
		delete_transient( 'promptor_90_warning_daily' );
		delete_transient( 'promptor_100_warning_daily' );
	}

	/**
	 * Reset monthly usage warnings (called on 1st of month).
	 *
	 * @return void
	 */
	public static function reset_monthly(): void {
		delete_transient( 'promptor_80_warning_monthly' );
		delete_transient( 'promptor_90_warning_monthly' );
		delete_transient( 'promptor_100_warning_monthly' );
	}

	// -----------------------------------------------------------------------
	// Private helpers
	// -----------------------------------------------------------------------

	/**
	 * Empty usage bucket template.
	 *
	 * @return array
	 */
	private static function empty_bucket(): array {
		return array(
			'tokens' => 0,
			'cost'   => 0.0,
		);
	}

	/**
	 * Resolve limit action string to status.
	 *
	 * @param string $action Configured action ('pause', 'fallback', 'warn').
	 * @return string 'paused' | 'fallback' | 'warn'.
	 */
	private static function resolve_limit_action( string $action ): string {
		switch ( $action ) {
			case 'pause':
				return 'paused';
			case 'warn':
				return 'warn';
			case 'fallback':
			default:
				return 'fallback';
		}
	}

	/**
	 * Check warning thresholds and send admin notifications.
	 *
	 * @param array $usage Usage data.
	 * @return void
	 */
	private static function check_warning_thresholds( array $usage ): void {
		$limits = self::get_limits();

		// Check monthly thresholds.
		if ( $limits['monthly_enabled'] ) {
			$month         = gmdate( 'Y-m' );
			$monthly_used  = $usage['monthly'][ $month ]['tokens'] ?? 0;
			$monthly_limit = $limits['monthly_limit'];

			if ( $monthly_limit > 0 ) {
				$percent = ( $monthly_used / $monthly_limit ) * 100;

				foreach ( self::WARNING_THRESHOLDS as $threshold ) {
					if ( $percent >= $threshold ) {
						$transient_key = 'promptor_' . $threshold . '_warning_monthly';

						if ( ! get_transient( $transient_key ) ) {
							set_transient( $transient_key, true, DAY_IN_SECONDS );
							self::send_threshold_notice( 'monthly', $threshold, $monthly_used, $monthly_limit );
						}
					}
				}
			}
		}

		// Check daily thresholds.
		if ( $limits['daily_enabled'] ) {
			$today       = gmdate( 'Y-m-d' );
			$daily_used  = $usage['daily'][ $today ]['tokens'] ?? 0;
			$daily_limit = $limits['daily_limit'];

			if ( $daily_limit > 0 ) {
				$percent = ( $daily_used / $daily_limit ) * 100;

				foreach ( self::WARNING_THRESHOLDS as $threshold ) {
					if ( $percent >= $threshold ) {
						$transient_key = 'promptor_' . $threshold . '_warning_daily';

						if ( ! get_transient( $transient_key ) ) {
							set_transient( $transient_key, true, DAY_IN_SECONDS );
							self::send_threshold_notice( 'daily', $threshold, $daily_used, $daily_limit );
						}
					}
				}
			}
		}
	}

	/**
	 * Send a threshold warning notice to admin email.
	 *
	 * @param string $period    'daily' or 'monthly'.
	 * @param int    $threshold Percentage threshold.
	 * @param int    $used      Tokens used.
	 * @param int    $limit     Token limit.
	 * @return void
	 */
	private static function send_threshold_notice( string $period, int $threshold, int $used, int $limit ): void {
		$admin_email = get_option( 'admin_email' );

		if ( empty( $admin_email ) ) {
			return;
		}

		$site_name = get_bloginfo( 'name' );

		$subject = sprintf(
			/* translators: 1: threshold percentage, 2: period (daily/monthly), 3: site name */
			__( '[Promptor] %1$d%% of %2$s AI token limit reached on %3$s', 'promptor' ),
			$threshold,
			$period,
			$site_name
		);

		$body = sprintf(
			/* translators: 1: threshold percentage, 2: period, 3: used tokens, 4: limit tokens */
			__(
				"Hello,\n\nYour Promptor AI assistant has used %1\$d%% of its %2\$s token limit.\n\nUsage: %3\$s / %4\$s tokens\n\nYou can adjust your limits in Promptor > Settings > AI Usage.\n\nRegards,\nPromptor",
				'promptor'
			),
			$threshold,
			$period,
			number_format( $used ),
			number_format( $limit )
		);

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( class_exists( 'Promptor_Email_Template' ) ) {
			$body = Promptor_Email_Template::wrap( $subject, $body, 'warning' );
		}

		wp_mail( $admin_email, $subject, $body, $headers );

		// Log it.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'Promptor [cost_guard]: %d%% %s threshold reached (%s / %s tokens)', $threshold, $period, number_format( $used ), number_format( $limit ) ) );
		}
	}
}
