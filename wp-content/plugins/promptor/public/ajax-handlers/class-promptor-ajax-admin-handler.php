<?php
/**
 * Promptor Ajax Admin Handler
 *
 * Handles all AJAX requests for the Promptor plugin admin area.
 *
 * @package Promptor
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Promptor_Ajax_Admin_Handler
 *
 * Manages AJAX operations for submissions, notifications, contexts, and indexing.
 */
class Promptor_Ajax_Admin_Handler {

	/**
	 * Handle submission status update via AJAX.
	 *
	 * @return void
	 */
	public function handle_update_submission_status() {
		check_ajax_referer( 'promptor_update_status_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'promptor' ) ) );
		}

		$id     = isset( $_POST['submission_id'] ) ? absint( $_POST['submission_id'] ) : 0;
		$status = isset( $_POST['new_status'] ) ? sanitize_key( $_POST['new_status'] ) : '';

		$valid_statuses = array( 'pending', 'contacted', 'converted', 'rejected' );
		if ( empty( $id ) || ! in_array( $status, $valid_statuses, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data provided.', 'promptor' ) ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'promptor_submissions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table with cache invalidation.
		$updated = $wpdb->update(
			$table_name,
			array( 'status' => $status ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		// Clear submission status counts cache when status is updated.
		wp_cache_delete( 'promptor_submission_status_counts', 'promptor' );
		wp_cache_delete( 'promptor_submission_' . $id, 'promptor' );

		if ( 'converted' === $status ) {
			$this->handle_lead_conversion( $id );
		}

		$status_text = $this->get_status_text( $status );

		wp_send_json_success( array( 'new_status_text' => $status_text ) );
	}

	/**
	 * Handle lead conversion notifications.
	 *
	 * @param int $submission_id The submission ID.
	 * @return void
	 */
	private function handle_lead_conversion( $submission_id ) {
		$notification_settings = get_option( 'promptor_notification_settings', array() );

		// Check cache first.
		$cache_key       = 'promptor_submission_' . $submission_id;
		$submission_data = wp_cache_get( $cache_key, 'promptor' );

		if ( false === $submission_data ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table with caching implemented.
			$submission_data = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}promptor_submissions WHERE id = %d",
					$submission_id
				),
				ARRAY_A
			);

			if ( $submission_data ) {
				// Cache for 1 hour.
				wp_cache_set( $cache_key, $submission_data, 'promptor', HOUR_IN_SECONDS );
			}
		}

		if ( ! $submission_data ) {
			return;
		}

		$placeholders = array(
			'{site_name}'       => get_bloginfo( 'name' ),
			'{user_name}'       => isset( $submission_data['name'] ) ? $submission_data['name'] : '',
			'{user_email}'      => isset( $submission_data['email'] ) ? $submission_data['email'] : '',
			'{recommendations}' => isset( $submission_data['recommendations'] ) ? $submission_data['recommendations'] : '',
		);

		if ( ! empty( $notification_settings['triggers']['lead_converted']['email'] ) ) {
			$this->send_dynamic_email( 'lead_converted', $placeholders );
		}

		if ( ! empty( $notification_settings['triggers']['lead_converted']['slack'] ) ) {
			$this->send_lead_converted_slack( $placeholders );
		}
	}

	/**
	 * Get translated status text.
	 *
	 * @param string $status The status key.
	 * @return string The translated status text.
	 */
	private function get_status_text( $status ) {
		$statuses = array(
			'pending'   => __( 'Pending', 'promptor' ),
			'contacted' => __( 'Contacted', 'promptor' ),
			'converted' => __( 'Converted', 'promptor' ),
			'rejected'  => __( 'Rejected', 'promptor' ),
		);

		return isset( $statuses[ $status ] ) ? $statuses[ $status ] : $statuses['rejected'];
	}

	/**
	 * Handle sending a test email via AJAX.
	 *
	 * @return void
	 */
	public function handle_send_test_email() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'promptor_test_email_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'promptor' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission Denied.', 'promptor' ) ) );
		}

		global $promptor_mail_errors;
		$promptor_mail_errors = array();

		$placeholders = array(
			'{site_name}'         => get_bloginfo( 'name' ),
			'{user_name}'         => 'John Doe (Test)',
			'{user_email}'        => 'test@example.com',
			'{user_phone}'        => '555-1234',
			'{selected_services}' => 'Test Service 1, Test Service 2',
			'{selected_products}' => 'Test Product A',
			'{user_notes}'        => 'This is a test note from the new dynamic template system.',
		);

		$was_sent = $this->send_dynamic_email( 'new_lead', $placeholders );

		$settings = get_option( 'promptor_notification_settings', array() );
		$to       = ! empty( $settings['email']['recipients'] ) ? $settings['email']['recipients'] : get_option( 'admin_email' );

		if ( $was_sent ) {
			$recipient_display = is_array( $to ) ? implode( ', ', $to ) : $to;
			wp_send_json_success(
				array(
					'message' => sprintf(/* translators: %s: Recipient email address(es) */
						esc_html__( 'Test email sent successfully to %s', 'promptor' ),
						esc_html( $recipient_display )
					),
				)
			);
		} else {
			$error_message = __( 'Failed to send test email.', 'promptor' );
			if ( ! empty( $promptor_mail_errors ) ) {
				$error_message .= ' ' . esc_html( implode( ', ', $promptor_mail_errors ) );
			}
			wp_send_json_error( array( 'message' => $error_message ) );
		}
	}

	/**
	 * Handle sending a test Slack notification via AJAX.
	 *
	 * @return void
	 */
	public function handle_send_test_slack() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'promptor_test_slack_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'promptor' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission Denied.', 'promptor' ) ) );
		}

		$settings       = get_option( 'promptor_notification_settings', array() );
		$slack_settings = isset( $settings['slack'] ) ? $settings['slack'] : array();

		$webhook = isset( $slack_settings['webhook_url'] ) ? esc_url_raw( $slack_settings['webhook_url'] ) : '';

		if ( empty( $slack_settings['enabled'] ) || empty( $webhook ) || 'https' !== wp_parse_url( $webhook, PHP_URL_SCHEME ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Slack notifications are disabled or Webhook URL is invalid.', 'promptor' ) ) );
		}

		$message = '✅ This is a test notification from Promptor for your site: ' . get_bloginfo( 'name' );

		$result = $this->send_slack( $message, array_merge( $slack_settings, array( 'webhook_url' => $webhook ) ) );

		if ( true === $result ) {
			wp_send_json_success( array( 'message' => esc_html__( 'Test notification sent successfully to Slack!', 'promptor' ) ) );
		} else {
			wp_send_json_error( array( 'message' => $result ) );
		}
	}

	/**
	 * Handle saving notification settings via AJAX.
	 *
	 * @return void
	 */
	public function handle_save_notification_settings() {
		check_ajax_referer( 'promptor_save_notifications_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission Denied.', 'promptor' ) ) );
		}

		if ( ! isset( $_POST['promptor_notification_settings'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No settings data received.', 'promptor' ) ) );
		}

		// Sanitize the settings input first.
		$raw_settings = isset( $_POST['promptor_notification_settings'] ) && is_array( $_POST['promptor_notification_settings'] )
			? map_deep( wp_unslash( $_POST['promptor_notification_settings'] ), 'sanitize_text_field' )
			: null;

		if ( null === $raw_settings ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid settings payload.', 'promptor' ) ) );
		}

		if ( ! class_exists( 'Promptor_Settings_Tab_Notifications' ) ) {
			require_once PROMPTOR_PATH . 'admin/settings/class-promptor-settings-notifications.php';
		}

		$settings_handler = new Promptor_Settings_Tab_Notifications();

		$sanitized_settings = $settings_handler->sanitize_settings( $raw_settings );
		if ( ! is_array( $sanitized_settings ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Settings could not be sanitized.', 'promptor' ) ) );
		}

		update_option( 'promptor_notification_settings', $sanitized_settings );
		wp_send_json_success( array( 'message' => esc_html__( 'Settings saved.', 'promptor' ) ) );
	}

	/**
	 * Handle API key verification via AJAX.
	 *
	 * @return void
	 */
	public function handle_verify_api_key() {
		check_ajax_referer( 'promptor_verify_api_key_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission Denied.', 'promptor' ) ) );
		}

		$api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'API Key cannot be empty.', 'promptor' ) ) );
		}

		$response = wp_remote_get(
			'https://api.openai.com/v1/models',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Connection Error: ', 'promptor' ) . esc_html( $response->get_error_message() ),
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 === $status_code ) {
			// Mark API connection as successful for onboarding step tracking (v1.2.1).
			require_once PROMPTOR_PATH . 'admin/class-promptor-onboarding.php';
			Promptor_Onboarding::mark_api_connection_success();

			wp_send_json_success( array( 'message' => esc_html__( 'Success! Your API Key is valid.', 'promptor' ) ) );
		} elseif ( 401 === $status_code ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Error: Invalid API Key. Please check your key and try again.', 'promptor' ) ) );
		} else {
			$body          = json_decode( wp_remote_retrieve_body( $response ), true );
			$error_message = isset( $body['error']['message'] ) ? $body['error']['message'] : "HTTP Status Code: {$status_code}";
			wp_send_json_error(
				array(
					'message' => esc_html__( 'An error occurred: ', 'promptor' ) . esc_html( $error_message ),
				)
			);
		}
	}

	/**
	 * Handle adding a new context via AJAX.
	 *
	 * @return void
	 */
	public function handle_add_context() {
		check_ajax_referer( 'promptor_manage_context_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission Denied.', 'promptor' ) ) );
		}

		$contexts        = get_option( 'promptor_contexts', array() );
		$new_context_key = isset( $_POST['context_name'] ) ? sanitize_key( $_POST['context_name'] ) : '';

		if ( empty( $new_context_key ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Knowledge base name cannot be empty.', 'promptor' ) ) );
		}

		if ( array_key_exists( $new_context_key, $contexts ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Knowledge base with this name already exists.', 'promptor' ) ) );
		}

		$context_display_name         = ucfirst( str_replace( array( '-', '_' ), ' ', $new_context_key ) );
		$contexts[ $new_context_key ] = array(
			'name'        => $context_display_name,
			'source_type' => 'manual',
			'settings'    => array(
				'selected_pages'    => array(),
				'selected_files'    => array(),
				'example_questions' => '',
			),
		);

		update_option( 'promptor_contexts', $contexts );

		wp_send_json_success( array( 'message' => esc_html__( 'New knowledge base created successfully.', 'promptor' ) ) );
	}

	/**
	 * Handle deleting a context via AJAX.
	 *
	 * @return void
	 */
	public function handle_delete_context() {
		check_ajax_referer( 'promptor_manage_context_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission Denied.', 'promptor' ) ) );
		}

		$context_to_delete = isset( $_POST['context'] ) ? sanitize_key( $_POST['context'] ) : '';

		$contexts = get_option( 'promptor_contexts', array() );

		if ( 'default' === $context_to_delete ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Cannot delete the default knowledge base.', 'promptor' ) ) );
		}

		if ( ! array_key_exists( $context_to_delete, $contexts ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Knowledge base does not exist.', 'promptor' ) ) );
		}

		unset( $contexts[ $context_to_delete ] );
		update_option( 'promptor_contexts', $contexts );

		// Delete embeddings associated with this context.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table modification with cache invalidation.
		$deleted = $wpdb->delete(
			$wpdb->prefix . 'promptor_embeddings',
			array( 'context_name' => $context_to_delete ),
			array( '%s' )
		);

		// Clear any related cache.
		wp_cache_delete( 'promptor_stats_' . $context_to_delete, 'promptor' );

		wp_send_json_success( array( 'message' => esc_html__( 'Knowledge base deleted successfully.', 'promptor' ) ) );
	}

	/**
	 * Handle saving content selection via AJAX.
	 *
	 * @return void
	 */
	public function handle_save_content_selection() {
		check_ajax_referer( 'promptor_manage_context_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission Denied.', 'promptor' ) ) );
		}

		$context_key = isset( $_POST['context_key'] ) ? sanitize_key( $_POST['context_key'] ) : '';
		if ( empty( $context_key ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Context key is missing.', 'promptor' ) ) );
		}

		$contexts = get_option( 'promptor_contexts', array() );
		if ( ! isset( $contexts[ $context_key ] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Context not found.', 'promptor' ) ) );
		}

		// Sanitize content_data input.
		$content_data_raw = isset( $_POST['content_data'] ) ? sanitize_textarea_field( wp_unslash( $_POST['content_data'] ) ) : '{}';
		if ( ! is_string( $content_data_raw ) ) {
			$content_data_raw = '{}';
		}

		$content_data = json_decode( $content_data_raw, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $content_data ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid content data format.', 'promptor' ) ) );
		}

		// Get visible IDs (items that are currently loaded in DOM)
		$visible_ids_raw = isset( $_POST['visible_ids'] ) ? sanitize_textarea_field( wp_unslash( $_POST['visible_ids'] ) ) : '[]';
		$visible_ids     = json_decode( $visible_ids_raw, true );
		if ( ! is_array( $visible_ids ) ) {
			$visible_ids = array();
		}

		$sanitized_content_data = $this->sanitize_content_data( $content_data );

		// Get existing content_roles to preserve items not in DOM
		$existing_content_roles = $contexts[ $context_key ]['settings']['content_roles'] ?? array();

		// Remove old format data
		unset( $contexts[ $context_key ]['settings']['selected_pages'] );
		unset( $contexts[ $context_key ]['settings']['selected_files'] );

		// Strategy: Keep existing items that are NOT visible in DOM, update visible items
		$merged_content_roles = array();

		// First, keep all existing items that are NOT in visible_ids
		foreach ( $existing_content_roles as $post_id => $role ) {
			$post_id_int = absint( $post_id );
			if ( ! in_array( (string) $post_id_int, $visible_ids, true ) && ! in_array( $post_id_int, $visible_ids, true ) ) {
				$merged_content_roles[ $post_id_int ] = $role;
			}
		}

		// Then, add/update items from the form (visible items)
		foreach ( $sanitized_content_data as $post_id => $role ) {
			$merged_content_roles[ absint( $post_id ) ] = $role;
		}

		$contexts[ $context_key ]['settings']['content_roles']      = $merged_content_roles;
		$contexts[ $context_key ]['settings']['example_questions'] = isset( $_POST['example_questions'] ) ? sanitize_textarea_field( wp_unslash( $_POST['example_questions'] ) ) : '';

		if ( function_exists( 'promptor_fs' ) && promptor_fs()->can_use_premium_code__premium_only() ) {
			$auto = isset( $_POST['auto_sync_enabled'] ) ? absint( wp_unslash( $_POST['auto_sync_enabled'] ) ) : 0;
			$contexts[ $context_key ]['settings']['auto_sync_enabled'] = $auto ? 1 : 0;
		}

		update_option( 'promptor_contexts', $contexts );

		// Clear related cache.
		wp_cache_delete( 'promptor_stats_' . $context_key, 'promptor' );

		// Track KB update in telemetry (v1.2.2 - with immediate send)
		if ( class_exists( 'Promptor_Telemetry' ) ) {
			Promptor_Telemetry::track_kb_update( true ); // Send immediately
		}

		// Track step 2 completion in telemetry (v1.2.1)
		if ( class_exists( 'Promptor_Onboarding' ) && Promptor_Onboarding::is_step_2_completed() ) {
			if ( class_exists( 'Promptor_Telemetry' ) ) {
				Promptor_Telemetry::track_step_completion( 2 );
			}
		}

		wp_send_json_success( array( 'message' => esc_html__( 'Content settings saved successfully!', 'promptor' ) ) );
	}

	/**
	 * Sanitize content data array.
	 *
	 * @param array $content_data Raw content data.
	 * @return array Sanitized content data.
	 */
	private function sanitize_content_data( $content_data ) {
		$sanitized = array();

		foreach ( $content_data as $post_id => $role ) {
			$sanitized_post_id = absint( $post_id );
			$sanitized_role    = sanitize_key( $role );

			if ( $sanitized_post_id > 0 && ! empty( $sanitized_role ) ) {
				$sanitized[ $sanitized_post_id ] = $sanitized_role;
			}
		}

		return $sanitized;
	}

	/**
	 * Handle getting query details via AJAX.
	 *
	 * @return void
	 */
	public function handle_get_query_details() {
		$query_id = isset( $_GET['query_id'] ) ? absint( $_GET['query_id'] ) : 0;

		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'promptor_view_query_details_' . $query_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'promptor' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'promptor' ) );
		}

		// Check cache first.
		$cache_key = 'promptor_query_' . $query_id;
		$query     = wp_cache_get( $cache_key, 'promptor' );

		if ( false === $query ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table with caching implemented.
			$query = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}promptor_queries WHERE id = %d",
					$query_id
				),
				ARRAY_A
			);

			if ( $query ) {
				// Cache for 1 hour.
				wp_cache_set( $cache_key, $query, 'promptor', HOUR_IN_SECONDS );
			}
		}

		if ( ! $query ) {
			wp_die( esc_html__( 'Query not found.', 'promptor' ) );
		}

		$this->render_query_details( $query );
		wp_die();
	}

	/**
	 * Render query details.
	 *
	 * @param array $query Query data.
	 * @return void
	 */
	private function render_query_details( $query ) {
		$ai_response_formatted = wp_json_encode(
			json_decode( $query['ai_response_raw'] ),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);

		echo '<div style="padding: 20px;">';
		echo '<h2>' . esc_html__( 'Query Details', 'promptor' ) . '</h2>';
		echo '<hr>';
		echo '<p><strong>' . esc_html__( "User's Query:", 'promptor' ) . '</strong><br>';
		echo '<em>' . esc_html( $query['user_query'] ) . '</em></p>';
		echo '<p><strong>' . esc_html__( 'Full AI Response:', 'promptor' ) . '</strong>';
		echo '<pre style="white-space: pre-wrap; background: #23282d; color: #f1f1f1; padding: 15px; border-radius: 4px; max-height: 300px; overflow-y: auto;">';
		echo esc_html( $ai_response_formatted );
		echo '</pre></p>';
		echo '</div>';
	}

	/**
	 * Handle getting indexing statistics via AJAX.
	 *
	 * @return void
	 */
	public function handle_get_indexing_stats() {
		check_ajax_referer( 'promptor_indexing_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission Denied.', 'promptor' ) ) );
		}

		$context = isset( $_POST['context'] ) ? sanitize_key( $_POST['context'] ) : 'default';

		// Check cache first.
		$cache_key    = 'promptor_stats_' . $context;
		$cached_stats = wp_cache_get( $cache_key, 'promptor' );

		if ( false !== $cached_stats ) {
			wp_send_json_success( $cached_stats );
			return;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table with caching implemented.
		$total_chunks = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}promptor_embeddings WHERE context_name = %s",
				$context
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table with caching implemented.
		$total_documents = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->prefix}promptor_embeddings WHERE context_name = %s",
				$context
			)
		);

		$stats = array(
			'documents' => number_format_i18n( $total_documents ),
			'chunks'    => number_format_i18n( $total_chunks ),
		);

		// Cache for 5 minutes.
		wp_cache_set( $cache_key, $stats, 'promptor', 300 );

		wp_send_json_success( $stats );
	}

	/**
	 * Handle linking an order to a submission via AJAX.
	 *
	 * @return void
	 */
	public function handle_link_order_to_submission() {
		check_ajax_referer( 'promptor_link_order_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission Denied.', 'promptor' ) ) );
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce is not active.', 'promptor' ) ) );
		}

		$submission_id = isset( $_POST['submission_id'] ) ? absint( $_POST['submission_id'] ) : 0;
		$order_id      = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

		if ( empty( $submission_id ) || empty( $order_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'promptor' ) ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'promptor' ) ) );
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table with cache invalidation.
		$updated = $wpdb->update(
			$wpdb->prefix . 'promptor_submissions',
			array(
				'linked_order_id' => $order_id,
				'order_value'     => $order->get_total(),
			),
			array( 'id' => $submission_id ),
			array( '%d', '%f' ),
			array( '%d' )
		);

		// Clear submission cache.
		wp_cache_delete( 'promptor_submission_' . $submission_id, 'promptor' );

		$html = $this->render_linked_order_html( $order );

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Render linked order HTML.
	 *
	 * @param WC_Order $order The WooCommerce order object.
	 * @return string The rendered HTML.
	 */
	private function render_linked_order_html( $order ) {
		$order_link = sprintf(
			'<a href="%s" target="_blank">#%s</a>',
			esc_url( $order->get_edit_order_url() ),
			esc_html( $order->get_id() )
		);

		return sprintf(
			'<div class="linked-order-info">%s (%s) <br><button class="button-link-delete promptor-unlink-order-btn">%s</button></div>',
			/* translators: %s: Order number with link */
			sprintf( esc_html__( 'Linked to Order %s', 'promptor' ), $order_link ),
			wp_kses_post( $order->get_formatted_order_total() ),
			esc_html__( 'Unlink', 'promptor' )
		);
	}

	/**
	 * Handle unlinking an order from a submission via AJAX.
	 *
	 * @return void
	 */
	public function handle_unlink_order_from_submission() {
		check_ajax_referer( 'promptor_link_order_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission Denied.', 'promptor' ) ) );
		}

		$submission_id = isset( $_POST['submission_id'] ) ? absint( $_POST['submission_id'] ) : 0;
		if ( empty( $submission_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'promptor' ) ) );
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table with cache invalidation.
		$updated = $wpdb->update(
			$wpdb->prefix . 'promptor_submissions',
			array(
				'linked_order_id' => 0,
				'order_value'     => null,
			),
			array( 'id' => $submission_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		// Clear submission cache.
		wp_cache_delete( 'promptor_submission_' . $submission_id, 'promptor' );

		$html = sprintf(
			'<div class="order-link-form"><input type="number" class="small-text" placeholder="%s"><button class="button button-secondary promptor-link-order-btn">%s</button></div>',
			esc_attr__( 'Order ID', 'promptor' ),
			esc_html__( 'Link', 'promptor' )
		);

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Handle bulk updating content roles via AJAX.
	 *
	 * @return void
	 */
	public function handle_bulk_update_content_roles() {
		check_ajax_referer( 'promptor_manage_context_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission Denied.', 'promptor' ) ) );
		}

		$context_key = isset( $_POST['context_key'] ) ? sanitize_key( $_POST['context_key'] ) : '';
		$post_ids    = isset( $_POST['post_ids'] ) && is_array( $_POST['post_ids'] ) ? array_map( 'absint', $_POST['post_ids'] ) : array();
		$new_role    = isset( $_POST['new_role'] ) ? sanitize_key( $_POST['new_role'] ) : '';

		if ( empty( $context_key ) || empty( $post_ids ) || empty( $new_role ) ) {
			wp_send_json_error( array( 'message' => __( 'Incomplete data sent.', 'promptor' ) ) );
		}

		$contexts = get_option( 'promptor_contexts', array() );
		if ( ! isset( $contexts[ $context_key ] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Context not found.', 'promptor' ) ) );
		}

		if ( ! isset( $contexts[ $context_key ]['settings']['content_roles'] ) ) {
			$contexts[ $context_key ]['settings']['content_roles'] = array();
		}

		foreach ( $post_ids as $post_id ) {
			$contexts[ $context_key ]['settings']['content_roles'][ $post_id ] = $new_role;
		}

		update_option( 'promptor_contexts', $contexts );

		// Clear related cache.
		wp_cache_delete( 'promptor_stats_' . $context_key, 'promptor' );

		wp_send_json_success(
			array(
				'message' => sprintf(/* translators: %d: Number of items updated */
					_n(
						'%d content role successfully updated.',
						'%d content roles successfully updated.',
						count( $post_ids ),
						'promptor'
					),
					count( $post_ids )
				),
			)
		);
	}

	/**
	 * Handle updating a single content role via AJAX.
	 *
	 * @return void
	 */
	public function handle_update_single_content_role() {
		check_ajax_referer( 'promptor_manage_context_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission Denied.', 'promptor' ) ) );
		}

		$context_key = isset( $_POST['context_key'] ) ? sanitize_key( $_POST['context_key'] ) : '';
		$post_id     = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$new_role    = isset( $_POST['new_role'] ) ? sanitize_key( $_POST['new_role'] ) : '';

		if ( empty( $context_key ) || empty( $post_id ) || empty( $new_role ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing data.', 'promptor' ) ) );
		}

		$contexts = get_option( 'promptor_contexts', array() );
		if ( ! isset( $contexts[ $context_key ] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Context not found.', 'promptor' ) ) );
		}

		if ( ! isset( $contexts[ $context_key ]['settings']['content_roles'] ) ) {
			$contexts[ $context_key ]['settings']['content_roles'] = array();
		}

		$contexts[ $context_key ]['settings']['content_roles'][ $post_id ] = $new_role;

		update_option( 'promptor_contexts', $contexts );

		// Clear related cache.
		wp_cache_delete( 'promptor_stats_' . $context_key, 'promptor' );

		wp_send_json_success( array( 'message' => __( 'Role updated successfully.', 'promptor' ) ) );
	}

	/**
	 * Send a dynamic email based on template key.
	 *
	 * @param string $template_key The email template key.
	 * @param array  $placeholders The placeholders to replace in the template.
	 * @return bool True on success, false on failure.
	 */
	private function send_dynamic_email( $template_key, $placeholders ) {
		$settings       = get_option( 'promptor_notification_settings', array() );
		$email_settings = isset( $settings['email'] ) ? $settings['email'] : array();

		if ( ! class_exists( 'Promptor_Settings_Tab_Notifications' ) ) {
			require_once PROMPTOR_PATH . 'admin/settings/class-promptor-settings-notifications.php';
		}

		$templates_config_class = new Promptor_Settings_Tab_Notifications();
		$templates_config       = $templates_config_class->get_email_templates_config();

		$template = isset( $email_settings['templates'][ $template_key ] ) ? $email_settings['templates'][ $template_key ] : $templates_config[ $template_key ];

		$to_list = $this->prepare_email_recipients( $email_settings );
		$subject = wp_strip_all_tags( strtr( $template['subject'], $placeholders ) );
		$body    = strtr( $template['body'], $placeholders );
		$body    = wpautop( wp_kses_post( $body ) );

		return $this->send_email( $to_list, $subject, $body, $email_settings, true );
	}

	/**
	 * Prepare email recipients list.
	 *
	 * @param array $email_settings Email settings array.
	 * @return array Array of sanitized email addresses.
	 */
	private function prepare_email_recipients( $email_settings ) {
		$raw_to  = ! empty( $email_settings['recipients'] ) ? $email_settings['recipients'] : get_option( 'admin_email' );
		$to_list = is_array( $raw_to ) ? $raw_to : explode( ',', (string) $raw_to );
		$to_list = array_values( array_filter( array_map( 'sanitize_email', array_map( 'trim', $to_list ) ) ) );

		if ( empty( $to_list ) ) {
			$to_list = array( get_option( 'admin_email' ) );
		}

		return $to_list;
	}

	/**
	 * Send a Slack notification for lead conversion.
	 *
	 * @param array $placeholders The placeholders for the message.
	 * @return void
	 */
	private function send_lead_converted_slack( $placeholders ) {
		$settings       = get_option( 'promptor_notification_settings', array() );
		$slack_settings = isset( $settings['slack'] ) ? $settings['slack'] : array();

		if ( empty( $slack_settings['enabled'] ) || empty( $slack_settings['webhook_url'] ) ) {
			return;
		}

		$message = sprintf(
			"✅ *Lead Converted on %s!*\n> *Submitter:* %s (%s)\n> *Details:* %s",
			$placeholders['{site_name}'],
			$placeholders['{user_name}'],
			$placeholders['{user_email}'],
			$placeholders['{recommendations}']
		);

		$this->send_slack( $message, $slack_settings );
	}

	/**
	 * Send an email.
	 *
	 * @param array  $to             The recipient(s).
	 * @param string $subject        The email subject.
	 * @param string $body           The email body.
	 * @param array  $email_settings The email settings.
	 * @param bool   $is_html        Whether the email is HTML.
	 * @return bool True on success, false on failure.
	 */
	private function send_email( $to, $subject, $body, $email_settings, $is_html = false ) {
		$server_name = isset( $_SERVER['SERVER_NAME'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) ) : wp_parse_url( home_url(), PHP_URL_HOST );
		$domain_part = preg_replace( '#^www\.#', '', strtolower( (string) $server_name ) );

		$from_name  = ! empty( $email_settings['from_name'] ) ? wp_strip_all_tags( $email_settings['from_name'] ) : wp_strip_all_tags( get_bloginfo( 'name' ) );
		$from_email = ! empty( $email_settings['from_email'] ) ? sanitize_email( $email_settings['from_email'] ) : 'wordpress@' . $domain_part;

		if ( ! is_email( $from_email ) ) {
			$from_email = 'wordpress@' . $domain_part;
		}

		$headers = array( "From: {$from_name} <{$from_email}>" );

		// Wrap body in branded HTML template.
		if ( class_exists( 'Promptor_Email_Template' ) ) {
			$body    = Promptor_Email_Template::wrap( $subject, $body, 'info' );
			$is_html = true;
		}

		if ( $is_html ) {
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
		}

		// Ensure recipients array (already sanitized upstream, but double-safety here).
		$to_list = is_array( $to ) ? array_values( array_filter( array_map( 'sanitize_email', $to ) ) ) : array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', (string) $to ) ) ) );

		if ( empty( $to_list ) ) {
			$to_list = array( get_option( 'admin_email' ) );
		}

		return wp_mail( $to_list, $subject, $body, $headers );
	}

	/**
	 * Send a Slack notification.
	 *
	 * @param string $message        The message to send.
	 * @param array  $slack_settings The Slack settings.
	 * @return bool|string True on success, error message on failure.
	 */
	private function send_slack( $message, $slack_settings ) {
		$payload = array( 'text' => $message );

		if ( ! empty( $slack_settings['channel'] ) ) {
			$payload['channel'] = $slack_settings['channel'];
		}

		if ( ! empty( $slack_settings['bot_name'] ) ) {
			$payload['username'] = $slack_settings['bot_name'];
		}

		$webhook = isset( $slack_settings['webhook_url'] ) ? esc_url_raw( $slack_settings['webhook_url'] ) : '';

		if ( empty( $webhook ) || 'https' !== wp_parse_url( $webhook, PHP_URL_SCHEME ) ) {
			return esc_html__( 'Invalid Slack Webhook URL.', 'promptor' );
		}

		$response = wp_remote_post(
			$webhook,
			array(
				'body'    => wp_json_encode( $payload ),
				'headers' => array( 'Content-Type' => 'application/json' ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return esc_html__( 'Connection Error: ', 'promptor' ) . esc_html( $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 === $response_code ) {
			return true;
		}

		return esc_html__( 'Slack API returned an error: ', 'promptor' ) . esc_html( $response_body );
	}

	/**
	 * Generate example questions using AI based on indexed content.
	 *
	 * @return void
	 */
	public function handle_generate_example_questions() {
		check_ajax_referer( 'promptor_generate_questions', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'promptor' ) ) );
		}

		$context_key = isset( $_POST['context_key'] ) ? sanitize_key( $_POST['context_key'] ) : '';

		if ( empty( $context_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Context key is missing.', 'promptor' ) ) );
		}

		$contexts = get_option( 'promptor_contexts', array() );
		if ( ! isset( $contexts[ $context_key ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Context not found.', 'promptor' ) ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'promptor_embeddings';

		// Build SQL query with safe table name (from $wpdb->prefix) and prepared parameters
		$sql = sprintf(
			'SELECT content_chunk FROM %s WHERE context_name = %%s ORDER BY RAND() LIMIT 15',
			esc_sql( $table_name )
		);

		// Get sample content from indexed items for this context (random selection for diversity)
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$sample_content = $wpdb->get_results(
			$wpdb->prepare( $sql, $context_key ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( empty( $sample_content ) ) {
			wp_send_json_error( array( 'message' => __( 'No indexed content found. Please sync some content first.', 'promptor' ) ) );
		}

		// Combine sample text chunks from diverse sources
		$combined_text = '';
		foreach ( $sample_content as $row ) {
			$combined_text .= $row['content_chunk'] . ' ';
			if ( strlen( $combined_text ) > 3000 ) {
				break; // Limit to ~3000 chars for better context
			}
		}

		// Get API settings
		$api_settings = get_option( 'promptor_api_settings', array() );
		$api_key      = $api_settings['api_key'] ?? '';

		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => __( 'OpenAI API key not configured.', 'promptor' ) ) );
		}

		// Generate conversion-oriented example questions using OpenAI
		$prompt = sprintf(
			"CRITICAL: You MUST generate questions in the EXACT SAME LANGUAGE as the content below.\n\n" .
			"Your task: Create 3 CONVERSION-ORIENTED questions that potential customers would ask when trying to MAKE A DECISION or SOLVE THEIR PROBLEM.\n\n" .
			"Requirements:\n" .
			"1. LANGUAGE: Match the content language EXACTLY (English→English, Turkish→Turkish, etc.)\n" .
			"2. CONVERSION FOCUS: Questions should help users find THE RIGHT SOLUTION for their specific needs\n" .
			"   - GOOD: 'Which service would best fit my small business needs?'\n" .
			"   - BAD: 'What services do you offer?' (too generic)\n" .
			"   - GOOD: 'How can I improve my website's conversion rate?'\n" .
			"   - BAD: 'What is conversion rate optimization?' (just definition)\n" .
			"3. DECISION-MAKING: Frame questions as if the user is evaluating options or seeking guidance\n" .
			"4. PERSONALIZED: Use words like 'my', 'I', 'help me', 'which...for me' to make it personal\n" .
			"5. TONE: Natural and conversational, showing intent to take action\n" .
			"6. FORMAT: One question per line, WITHOUT numbers, bullets, or prefixes\n" .
			"7. DIVERSITY: Cover different use cases or customer scenarios from the content\n\n" .
			"Content:\n%s",
			substr( $combined_text, 0, 3000 )
		);

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'       => 'gpt-4o-mini',
						'messages'    => array(
							array(
								'role'    => 'system',
								'content' => 'You are a multilingual conversion optimization expert specializing in creating customer-centric, decision-oriented questions. You MUST always respond in the EXACT same language as the user content. Never translate or switch languages. Focus on questions that drive engagement and help customers make informed decisions.',
							),
							array(
								'role'    => 'user',
								'content' => $prompt,
							),
						),
						'temperature' => 0.8,
						'max_tokens'  => 300,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => __( 'Connection Error: ', 'promptor' ) . $response->get_error_message() ) );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$data          = json_decode( $response_body, true );

		if ( 200 !== $response_code || ! isset( $data['choices'][0]['message']['content'] ) ) {
			$error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Failed to generate questions.', 'promptor' );
			wp_send_json_error( array( 'message' => $error_message ) );
		}

		$generated_text = $data['choices'][0]['message']['content'];
		$questions      = array_filter( array_map( 'trim', explode( "\n", $generated_text ) ) );

		// Remove leading numbers, bullets, or dashes (e.g., "1. ", "- ", "• ")
		$questions = array_map(
			function( $question ) {
				return preg_replace( '/^[\d]+[\.\)]\s*|-\s*|•\s*/', '', $question );
			},
			$questions
		);

		$questions = array_slice( $questions, 0, 3 ); // Ensure max 3 questions

		wp_send_json_success(
			array(
				'questions' => $questions,
				'message'   => __( 'Questions generated successfully!', 'promptor' ),
			)
		);
	}

	/**
	 * Handle AJAX request for loading more content in KB management.
	 *
	 * @since 1.0.1
	 */
	public function handle_load_more_content() {
		check_ajax_referer( 'promptor_kb_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'promptor' ) ) );
		}

		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
		$paged     = isset( $_POST['paged'] ) ? absint( wp_unslash( $_POST['paged'] ) ) : 1;
		$context   = isset( $_POST['context'] ) ? sanitize_key( wp_unslash( $_POST['context'] ) ) : '';

		if ( empty( $post_type ) || empty( $context ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'promptor' ) ) );
		}

		$contexts      = get_option( 'promptor_contexts', array() );

		// Try to get content_roles with multiple fallbacks
		$content_roles = array();

		if ( isset( $contexts[ $context ]['settings']['content_roles'] ) && is_array( $contexts[ $context ]['settings']['content_roles'] ) ) {
			$content_roles = $contexts[ $context ]['settings']['content_roles'];
		} elseif ( isset( $contexts[ $context ]['content_roles'] ) && is_array( $contexts[ $context ]['content_roles'] ) ) {
			// Fallback: check if content_roles is at root level
			$content_roles = $contexts[ $context ]['content_roles'];
		} elseif ( isset( $contexts[ $context ]['settings']['selected_pages'] ) && is_array( $contexts[ $context ]['settings']['selected_pages'] ) ) {
			// Fallback: check for old 'selected_pages' format
			foreach ( $contexts[ $context ]['settings']['selected_pages'] as $page_id ) {
				$content_roles[ $page_id ] = 'blog';  // default role
			}
		}

		// Ensure all array keys are integers for consistent comparison
		$content_roles_normalized = array();
		foreach ( $content_roles as $id => $role ) {
			$content_roles_normalized[ absint( $id ) ] = $role;
		}
		$content_roles = $content_roles_normalized;

		$args = array(
			'post_type'      => $post_type,
			'posts_per_page' => 50,
			'paged'          => $paged,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		// Handle PDFs differently
		if ( 'pdf' === $post_type ) {
			$args['post_type']      = 'attachment';
			$args['post_status']    = 'inherit';
			$args['post_mime_type'] = 'application/pdf';
		}

		$query = new WP_Query( $args );

		if ( ! $query->have_posts() ) {
			wp_send_json_success( array( 'html' => '', 'has_more' => false ) );
		}

		ob_start();
		while ( $query->have_posts() ) {
			$query->the_post();
			$post              = get_post();
			$post_id           = absint( $post->ID );  // Ensure integer
			$is_selected       = isset( $content_roles[ $post_id ] );
			$current_role      = $content_roles[ $post_id ] ?? 'blog';
			$roles             = array(
				'service' => __( 'Service', 'promptor' ),
				'product' => __( 'Product', 'promptor' ),
				'blog'    => __( 'Blog Post', 'promptor' ),
				'faq'     => __( 'FAQ', 'promptor' ),
			);
			$word_count        = str_word_count( wp_strip_all_tags( $post->post_content ) );
			?>
			<tr>
				<th scope="row" class="check-column">
					<input type="checkbox" name="selected_pages[]" value="<?php echo esc_attr( $post_id ); ?>" <?php checked( $is_selected ); ?>>
				</th>
				<td>
					<strong><a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>" target="_blank"><?php echo esc_html( $post->post_title ); ?></a></strong>
				</td>
				<td>
					<select name="content_roles[<?php echo esc_attr( $post_id ); ?>]" class="promptor-role-selector">
						<?php foreach ( $roles as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current_role, $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
				<td><?php echo esc_html( $word_count ); ?></td>
				<td><?php echo esc_html( get_the_date() ); ?></td>
			</tr>
			<?php
		}
		wp_reset_postdata();

		$html = ob_get_clean();

		wp_send_json_success(
			array(
				'html'         => $html,
				'has_more'     => ( $query->max_num_pages > $paged ),
				'total_found'  => $query->found_posts,
				'current_page' => $paged,
			)
		);
	}

	/**
	 * Handle wizard content loading (v1.2.2)
	 * Returns simplified content list for wizard Step 2
	 *
	 * @return void
	 */
	public function handle_wizard_load_content() {
		check_ajax_referer( 'promptor_wizard_load_content_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'promptor' ) ) );
		}

		// Get existing context
		$contexts = get_option( 'promptor_contexts', array() );
		if ( empty( $contexts['default'] ) ) {
			$contexts['default'] = array(
				'name'        => 'Default',
				'source_type' => 'manual',
				'settings'    => array(
					'content_roles'      => array(),
					'example_questions'  => '',
				),
			);
		}

		$content_roles = $contexts['default']['settings']['content_roles'] ?? array();
		$selected_ids  = array_keys( $content_roles );

		// Check if Pro
		$is_premium = ( function_exists( 'promptor_fs' ) && method_exists( promptor_fs(), 'can_use_premium_code__premium_only' ) )
			? (bool) promptor_fs()->can_use_premium_code__premium_only()
			: false;

		// Query pages and posts
		$query = new WP_Query(
			array(
				'post_type'      => array( 'page', 'post' ),
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		ob_start();
		?>
		<ul class="wizard-content-list">
			<?php
			if ( $query->have_posts() ) :
				while ( $query->have_posts() ) :
					$query->the_post();
					$post_id     = get_the_ID();
					$is_selected = in_array( $post_id, $selected_ids, true );
					$word_count  = str_word_count( wp_strip_all_tags( get_the_content() ) );
					$post_type   = get_post_type();
					$type_label  = $post_type === 'page' ? __( 'Page', 'promptor' ) : __( 'Post', 'promptor' );

					// Disable checkbox if free and limit reached
					$disabled = ( ! $is_premium && count( $selected_ids ) >= 3 && ! $is_selected ) ? 'disabled' : '';
					?>
					<li class="wizard-content-item" onclick="this.querySelector('input').click(); event.stopPropagation();">
						<input type="checkbox" class="wizard-content-checkbox" value="<?php echo esc_attr( $post_id ); ?>" <?php checked( $is_selected ); ?> <?php echo esc_attr( $disabled ); ?>>
						<div class="wizard-content-item-info">
							<div class="wizard-content-item-title"><?php the_title(); ?></div>
							<div class="wizard-content-item-meta">
								<?php
								printf(
									/* translators: 1: post type label, 2: word count, 3: date */
									esc_html__( '%1$s • %2$d words • %3$s', 'promptor' ),
									esc_html( $type_label ),
									(int) $word_count,
									esc_html( get_the_date() )
								);
								?>
							</div>
						</div>
					</li>
					<?php
				endwhile;
			else :
				?>
				<li style="text-align: center; padding: 40px; color: #646970;">
					<?php esc_html_e( 'No published pages or posts found. Please create some content first.', 'promptor' ); ?>
				</li>
				<?php
			endif;
			wp_reset_postdata();
			?>
		</ul>
		<?php
		$html = ob_get_clean();

		wp_send_json_success(
			array(
				'html'     => $html,
				'selected' => $selected_ids,
			)
		);
	}

	/**
	 * Handle wizard API settings save (v1.2.2)
	 *
	 * @return void
	 */
	public function handle_wizard_save_api() {
		check_ajax_referer( 'promptor_wizard_save_api_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'promptor' ) ) );
		}

		$api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
		$model   = isset( $_POST['model'] ) ? sanitize_key( $_POST['model'] ) : 'gpt-4o';

		// Save API settings
		$api_settings = get_option( 'promptor_api_settings', array() );
		$api_settings['api_key'] = $api_key;
		$api_settings['model']   = $model;
		update_option( 'promptor_api_settings', $api_settings );

		wp_send_json_success( array( 'message' => __( 'API settings saved.', 'promptor' ) ) );
	}

	/**
	 * Handle wizard content save and sync (v1.2.2)
	 *
	 * @return void
	 */
	public function handle_wizard_save_content() {
		check_ajax_referer( 'promptor_wizard_save_content_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'promptor' ) ) );
		}

		$content_ids = isset( $_POST['content_ids'] ) && is_array( $_POST['content_ids'] )
			? array_map( 'absint', $_POST['content_ids'] )
			: array();

		// Check if Pro
		$is_premium = ( function_exists( 'promptor_fs' ) && method_exists( promptor_fs(), 'can_use_premium_code__premium_only' ) )
			? (bool) promptor_fs()->can_use_premium_code__premium_only()
			: false;

		// Free version limit
		if ( ! $is_premium && count( $content_ids ) > 3 ) {
			$content_ids = array_slice( $content_ids, 0, 3 );
		}

		// Get or create default context
		$contexts = get_option( 'promptor_contexts', array() );
		if ( empty( $contexts['default'] ) ) {
			$contexts['default'] = array(
				'name'        => 'Default',
				'source_type' => 'manual',
				'settings'    => array(
					'content_roles'      => array(),
					'example_questions'  => '',
				),
			);
		}

		// Update content roles
		$content_roles = array();
		foreach ( $content_ids as $post_id ) {
			$content_roles[ $post_id ] = 'blog'; // Default role
		}

		$contexts['default']['settings']['content_roles'] = $content_roles;
		update_option( 'promptor_contexts', $contexts );

		// Track in telemetry (v1.2.2)
		if ( class_exists( 'Promptor_Telemetry' ) ) {
			Promptor_Telemetry::track_kb_update( true ); // Immediate send
		}

		// Track step 2 completion
		if ( class_exists( 'Promptor_Telemetry' ) ) {
			Promptor_Telemetry::track_step_completion( 2 );
		}

		wp_send_json_success( array( 'message' => __( 'Content saved successfully.', 'promptor' ) ) );
	}

	/**
	 * Handle wizard completion (v1.2.2)
	 *
	 * @return void
	 */
	public function handle_wizard_complete() {
		check_ajax_referer( 'promptor_wizard_complete_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'promptor' ) ) );
		}

		// Mark step 3 as completed
		if ( class_exists( 'Promptor_Onboarding' ) ) {
			Promptor_Onboarding::mark_step_3_completed();
		}

		// Mark wizard as completed
		update_option( 'promptor_wizard_completed', true );

		wp_send_json_success( array( 'message' => __( 'Setup complete!', 'promptor' ) ) );
	}

	// ──────────────────────────────────────────────
	// Trial System AJAX Handlers (v1.4.0)
	// ──────────────────────────────────────────────

	/**
	 * Handle trial activation (v1.4.0)
	 *
	 * @return void
	 */
	public function handle_trial_activate() {
		check_ajax_referer( 'promptor_trial_activate_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'promptor' ) ) );
		}

		if ( ! class_exists( 'Promptor_Trial_Client' ) ) {
			wp_send_json_error( array( 'message' => __( 'Trial system not available.', 'promptor' ) ) );
		}

		$result = Promptor_Trial_Client::activate_trial();

		if ( $result['success'] ) {
			wp_send_json_success( array(
				'message' => $result['message'],
				'trial'   => $result['data'] ?? Promptor_Trial_Client::get_trial_data(),
			) );
		} else {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
	}

	/**
	 * Handle trial status check (v1.4.0)
	 *
	 * @return void
	 */
	public function handle_trial_status() {
		check_ajax_referer( 'promptor_trial_status_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'promptor' ) ) );
		}

		if ( ! class_exists( 'Promptor_Trial_Client' ) ) {
			wp_send_json_error( array( 'message' => __( 'Trial system not available.', 'promptor' ) ) );
		}

		$result = Promptor_Trial_Client::get_status( true );

		if ( $result['success'] ) {
			wp_send_json_success( array( 'trial' => $result['data'] ) );
		} else {
			wp_send_json_error( array( 'message' => $result['message'] ?? '' ) );
		}
	}

	/**
	 * Handle trial content indexing (v1.4.0)
	 *
	 * Extracts content from selected pages and stores as trial context.
	 *
	 * @return void
	 */
	public function handle_trial_index_content() {
		check_ajax_referer( 'promptor_trial_index_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'promptor' ) ) );
		}

		if ( ! class_exists( 'Promptor_Trial_Client' ) ) {
			wp_send_json_error( array( 'message' => __( 'Trial system not available.', 'promptor' ) ) );
		}

		if ( ! Promptor_Trial_Client::is_trial_active() ) {
			wp_send_json_error( array( 'message' => __( 'No active trial.', 'promptor' ) ) );
		}

		$content_ids = isset( $_POST['content_ids'] ) && is_array( $_POST['content_ids'] )
			? array_map( 'absint', $_POST['content_ids'] )
			: array();

		if ( empty( $content_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Please select at least one page.', 'promptor' ) ) );
		}

		// Enforce trial page limit
		$max = Promptor_Trial_Client::MAX_TRIAL_PAGES;
		if ( count( $content_ids ) > $max ) {
			$content_ids = array_slice( $content_ids, 0, $max );
		}

		// Extract content from each page
		$pages = array();
		$errors = array();

		foreach ( $content_ids as $post_id ) {
			$page_data = Promptor_Trial_Client::extract_page_content( $post_id );
			if ( ! empty( $page_data ) ) {
				$pages[] = $page_data;
			} else {
				$errors[] = sprintf(
					/* translators: %d: post ID */
					__( 'Could not extract content from post #%d.', 'promptor' ),
					$post_id
				);
			}
		}

		if ( empty( $pages ) ) {
			wp_send_json_error( array(
				'message' => __( 'Could not extract content from any selected page. Please select pages with published content.', 'promptor' ),
			) );
		}

		// Generate suggested questions
		$suggested_questions = Promptor_Trial_Client::generate_suggested_questions( $pages );

		// Store trial context
		Promptor_Trial_Client::set_trial_context( array(
			'pages'               => $pages,
			'suggested_questions' => $suggested_questions,
			'indexed_at'          => time(),
		) );

		// Mark onboarding step 1 as completed (trial with content = ready)
		set_transient( 'promptor_api_connection_success', true, WEEK_IN_SECONDS );

		wp_send_json_success( array(
			'message'             => sprintf(
				/* translators: %d: number of pages indexed */
				__( 'Successfully trained on %d page(s)!', 'promptor' ),
				count( $pages )
			),
			'pages_indexed'       => count( $pages ),
			'suggested_questions' => $suggested_questions,
			'errors'              => $errors,
		) );
	}

	/**
	 * Handle telemetry opt-in from wizard (v1.4.0)
	 *
	 * @return void
	 */
	public function handle_wizard_telemetry_optin() {
		check_ajax_referer( 'promptor_wizard_telemetry_optin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'promptor' ) ) );
		}

		$enabled = isset( $_POST['enabled'] ) && '1' === $_POST['enabled'];

		// Reuse the same production path as Settings > Telemetry
		if ( class_exists( 'Promptor_Telemetry' ) ) {
			if ( $enabled ) {
				// enable() generates UUID, syncs state, schedules cron, and sends immediately
				Promptor_Telemetry::enable();
			} else {
				Promptor_Telemetry::disable();
			}
		} else {
			// Fallback: class not loaded (shouldn't happen, but safe)
			update_option( 'promptor_telemetry_enabled', $enabled, false );
		}

		wp_send_json_success();
	}
}