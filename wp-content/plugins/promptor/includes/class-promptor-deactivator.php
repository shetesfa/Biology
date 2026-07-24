<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles actions to perform when the plugin is deactivated.
 *
 * @since      1.0.0
 * @package    Promptor
 * @subpackage Promptor/includes
 */
class Promptor_Deactivator {

	/**
	 * Runs on plugin deactivation.
	 *
	 * @since 1.0.0
	 */
	public static function deactivate() {
		// Remove transient set during activation.
		delete_transient( 'promptor_activation_redirect' );

		// Placeholder for future cleanup operations.
		// delete_option( 'promptor_temp_data' );
		// wp_clear_scheduled_hook( 'promptor_cron_event' );
	}
}