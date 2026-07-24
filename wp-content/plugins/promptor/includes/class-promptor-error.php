<?php
/**
 * Standardized Error Handling for Promptor
 *
 * Provides a consistent error response interface across all AJAX handlers.
 *
 * @package Promptor
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Promptor_Error
 *
 * Standardized error creation and response for AJAX handlers.
 */
class Promptor_Error {

	/**
	 * Error code to HTTP status code mapping.
	 *
	 * @var array
	 */
	private static $status_map = array(
		'nonce_failed'           => 403,
		'unauthorized'           => 403,
		'rate_limited'           => 429,
		'validation_error'       => 400,
		'invalid_input'          => 400,
		'empty_query'            => 400,
		'not_found'              => 404,
		'api_error'              => 502,
		'api_key_missing'        => 500,
		'api_request_failed'     => 502,
		'embedding_request_failed' => 502,
		'quota_exceeded'         => 429,
		'daily_limit_exceeded'   => 429,
		'monthly_limit_exceeded' => 429,
		'limit_reached'          => 429,
		'server_error'           => 500,
		'woocommerce_inactive'   => 400,
		'product_unavailable'    => 400,
		'save_failed'            => 500,
		'configuration_error'    => 500,
		'invalid_history'        => 400,
		// Trial system error codes (v1.4.0) — use 403 so frontend error handler
		// can extract the message from jqXHR.responseJSON.data.message
		'trial_exhausted'        => 403,
		'trial_expired'          => 403,
		'trial_revoked'          => 403,
		'trial_unknown'          => 403,
		'no_trial_token'         => 403,
		'proxy_request_failed'   => 502,
	);

	/**
	 * Known error codes for validation.
	 *
	 * @var array
	 */
	private static $known_codes;

	/**
	 * Create a WP_Error instance with standardized structure.
	 *
	 * @param string $code    Error code (e.g., 'validation_error', 'api_error').
	 * @param string $message Human-readable error message.
	 * @param array  $data    Optional additional data.
	 * @return WP_Error
	 */
	public static function create( string $code, string $message, array $data = array() ): WP_Error {
		$error_data = array_merge(
			array(
				'error_type' => $code,
				'status'     => self::get_status( $code ),
			),
			$data
		);

		return new WP_Error( $code, $message, $error_data );
	}

	/**
	 * Send a JSON error response and terminate.
	 *
	 * @param string $code       Error code.
	 * @param string $message    Human-readable error message.
	 * @param array  $extra_data Optional additional data to include in the response.
	 * @return void Sends JSON and exits.
	 */
	public static function send( string $code, string $message, array $extra_data = array() ): void {
		$status = self::get_status( $code );

		$response_data = array_merge(
			array(
				'message'    => $message,
				'error_type' => $code,
			),
			$extra_data
		);

		wp_send_json_error( $response_data, $status );
	}

	/**
	 * Send a JSON error response from a WP_Error object.
	 *
	 * @param WP_Error $error      WP_Error instance.
	 * @param array    $extra_data Optional additional data.
	 * @return void Sends JSON and exits.
	 */
	public static function send_from_wp_error( WP_Error $error, array $extra_data = array() ): void {
		$code    = $error->get_error_code();
		$message = $error->get_error_message();

		self::send( $code, $message, $extra_data );
	}

	/**
	 * Get HTTP status code for an error code.
	 *
	 * @param string $code Error code.
	 * @return int HTTP status code (defaults to 500).
	 */
	public static function get_status( string $code ): int {
		return self::$status_map[ $code ] ?? 500;
	}

	/**
	 * Check if an error code is known/registered.
	 *
	 * @param string $code Error code to check.
	 * @return bool
	 */
	public static function is_known_code( string $code ): bool {
		if ( null === self::$known_codes ) {
			self::$known_codes = array_keys( self::$status_map );
		}

		return in_array( $code, self::$known_codes, true );
	}

	/**
	 * Log error if WP_DEBUG is enabled.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @return void
	 */
	public static function log( string $code, string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'Promptor [%s]: %s', $code, $message ) );
		}
	}
}
