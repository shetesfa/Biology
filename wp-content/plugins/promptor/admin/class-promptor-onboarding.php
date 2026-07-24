<?php
/**
 * Onboarding helper class for tracking setup progress.
 *
 * @link       https://promptorai.com
 * @since      1.2.1
 *
 * @package    Promptor
 * @subpackage Promptor/admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Promptor_Onboarding {

	/**
	 * Check if Step 1 (Connect OpenAI) is completed.
	 * Completed when API key is saved AND connection test is successful.
	 *
	 * @since 1.2.1
	 * @return bool True if step is completed.
	 */
	public static function is_step_1_completed() {
		$api_settings = get_option( 'promptor_api_settings', array() );

		// Check if API key exists and is not empty
		if ( ! empty( $api_settings['api_key'] ) ) {
			// Check if connection was tested successfully
			$last_test = get_transient( 'promptor_api_connection_success' );
			return ! empty( $last_test );
		}

		// Also consider trial mode as completing step 1 (v1.4.0)
		if ( class_exists( 'Promptor_Trial_Client' ) && Promptor_Trial_Client::is_trial_active() ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if Step 2 (Choose Knowledge Base) is completed.
	 * Completed when at least one Knowledge Base exists AND it contains at least one selected/indexed content item.
	 *
	 * @since 1.2.1
	 * @return bool True if step is completed.
	 */
	public static function is_step_2_completed() {
		global $wpdb;

		$contexts = get_option( 'promptor_contexts', array() );

		// Must have at least one context
		if ( empty( $contexts ) || ! is_array( $contexts ) ) {
			return false;
		}

		// Check if any context has selected content with embeddings
		$embeddings_table = $wpdb->prefix . 'promptor_embeddings';

		// Check if table exists
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$embeddings_table
			)
		);

		if ( $table_exists !== $embeddings_table ) {
			return false;
		}

		// Check if there's at least one embedding
		// Table name is constructed from $wpdb->prefix (safe) + hardcoded string
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$wpdb->prefix}promptor_embeddings` LIMIT %d",
				1
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return intval( $count ) > 0;
	}

	/**
	 * Check if Step 3 (Embed the Widget) is completed.
	 * Completed when the widget is enabled OR a shortcode/embed setting flag is saved.
	 *
	 * @since 1.2.1
	 * @return bool True if step is completed.
	 */
	public static function is_step_3_completed() {
		// Check if user has visited UI settings (simple flag approach for v1.2.1)
		$embed_completed = get_option( 'promptor_embed_step_completed', false );

		if ( $embed_completed ) {
			return true;
		}

		// Alternative: Check if UI settings have been saved
		$ui_settings = get_option( 'promptor_ui_settings', array() );

		// If UI settings exist and have been configured, consider it completed
		return ! empty( $ui_settings );
	}

	/**
	 * Mark Step 3 as completed manually.
	 * This can be called when user visits UI settings or embeds the widget.
	 *
	 * @since 1.2.1
	 * @return void
	 */
	public static function mark_step_3_completed() {
		update_option( 'promptor_embed_step_completed', true, false );

		// Track in telemetry (v1.2.1)
		if ( class_exists( 'Promptor_Telemetry' ) ) {
			Promptor_Telemetry::track_step_completion( 3 );
		}
	}

	/**
	 * Get the number of completed steps (0-3).
	 *
	 * @since 1.2.1
	 * @return int Number of completed steps.
	 */
	public static function get_completed_steps_count() {
		$count = 0;

		if ( self::is_step_1_completed() ) {
			$count++;
		}
		if ( self::is_step_2_completed() ) {
			$count++;
		}
		if ( self::is_step_3_completed() ) {
			$count++;
		}

		return $count;
	}

	/**
	 * Check if all setup steps are completed.
	 *
	 * @since 1.2.1
	 * @return bool True if all steps are completed.
	 */
	public static function is_setup_completed() {
		return self::get_completed_steps_count() === 3;
	}

	/**
	 * Mark API connection as successful (for Step 1 tracking).
	 * Should be called after successful API test.
	 *
	 * @since 1.2.1
	 * @return void
	 */
	public static function mark_api_connection_success() {
		set_transient( 'promptor_api_connection_success', true, WEEK_IN_SECONDS );

		// Track in telemetry (v1.2.1)
		if ( class_exists( 'Promptor_Telemetry' ) ) {
			Promptor_Telemetry::track_step_completion( 1 );
		}
	}

	/**
	 * Track that user has visited admin pages (for review prompt trigger).
	 *
	 * @since 1.2.1
	 * @return void
	 */
	public static function track_admin_visit() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$visit_count = (int) get_user_meta( $user_id, 'promptor_admin_visits', true );
		update_user_meta( $user_id, 'promptor_admin_visits', $visit_count + 1 );

		// Track first visit date
		$first_visit = get_user_meta( $user_id, 'promptor_first_admin_visit', true );
		if ( empty( $first_visit ) ) {
			update_user_meta( $user_id, 'promptor_first_admin_visit', time() );
		}
	}

	/**
	 * Check if user should see the review prompt.
	 *
	 * Trigger A: First lead/submission is captured (first time only)
	 * OR
	 * Trigger B: Active 3+ days + visited 2+ times + setup complete
	 *
	 * @since 1.2.1
	 * @return bool True if review prompt should be shown.
	 */
	public static function should_show_review_prompt() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		// Check if user has already dismissed or completed review
		$dismissed = get_user_meta( $user_id, 'promptor_review_prompt_dismissed', true );
		if ( $dismissed ) {
			return false;
		}

		// Check Trigger A: First lead captured
		$first_lead_captured = get_option( 'promptor_first_lead_captured', false );
		if ( $first_lead_captured ) {
			// Check if we've already shown the prompt for this trigger
			$shown_for_lead = get_user_meta( $user_id, 'promptor_review_shown_for_lead', true );
			if ( ! $shown_for_lead ) {
				return true;
			}
		}

		// Check Trigger B: Active 3+ days + visited 2+ times + setup complete
		$first_visit = get_user_meta( $user_id, 'promptor_first_admin_visit', true );
		$visit_count = (int) get_user_meta( $user_id, 'promptor_admin_visits', true );

		if ( empty( $first_visit ) ) {
			return false;
		}

		$days_active = ( time() - intval( $first_visit ) ) / DAY_IN_SECONDS;

		if ( $days_active >= 3 && $visit_count >= 2 && self::is_setup_completed() ) {
			return true;
		}

		return false;
	}

	/**
	 * Mark that first lead was captured (Trigger A for review prompt).
	 *
	 * @since 1.2.1
	 * @return void
	 */
	public static function mark_first_lead_captured() {
		$already_captured = get_option( 'promptor_first_lead_captured', false );
		if ( ! $already_captured ) {
			update_option( 'promptor_first_lead_captured', true, false );
		}
	}

	/**
	 * Mark review prompt as shown for lead trigger.
	 *
	 * @since 1.2.1
	 * @return void
	 */
	public static function mark_review_shown_for_lead() {
		$user_id = get_current_user_id();
		if ( $user_id ) {
			update_user_meta( $user_id, 'promptor_review_shown_for_lead', true );
		}
	}

	/**
	 * Dismiss the review prompt.
	 *
	 * @since 1.2.1
	 * @return void
	 */
	public static function dismiss_review_prompt() {
		$user_id = get_current_user_id();
		if ( $user_id ) {
			update_user_meta( $user_id, 'promptor_review_prompt_dismissed', true );
		}
	}
}
