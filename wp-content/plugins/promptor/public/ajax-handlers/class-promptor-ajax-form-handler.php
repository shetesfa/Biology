<?php
/**
 * AJAX Form Handler for Promptor
 *
 * Handles contact form submissions and add to cart functionality
 *
 * @package Promptor
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Promptor_Ajax_Form_Handler
 */
class Promptor_Ajax_Form_Handler {

	/**
	 * Cache group for wp_cache_* calls.
	 */
	const CACHE_GROUP = 'promptor';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_ajax_promptor_submit_contact_form', array( $this, 'handle_contact_form_submission' ) );
		add_action( 'wp_ajax_nopriv_promptor_submit_contact_form', array( $this, 'handle_contact_form_submission' ) );
		add_action( 'wp_ajax_promptor_add_to_cart', array( $this, 'handle_add_to_cart' ) );
		add_action( 'wp_ajax_nopriv_promptor_add_to_cart', array( $this, 'handle_add_to_cart' ) );
	}

	/**
	 * Handle contact form submission
	 *
	 * @return void
	 */
	public function handle_contact_form_submission() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'promptor_form_submit_nonce', 'nonce', false ) ) {
			Promptor_Error::send( 'nonce_failed', __( 'Security verification failed. Please refresh the page and try again.', 'promptor' ) );
		}

		// Basic IP-based rate limiting (10 minutes window, max 20 requests).
		$ip = '0.0.0.0';
		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$raw_ip = filter_input( INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP );
			$ip     = ( false !== $raw_ip && null !== $raw_ip ) ? $raw_ip : '0.0.0.0';
		}
		$rate_limit_key    = 'promptor_form_rl_' . md5( $ip );
		$request_count     = (int) get_transient( $rate_limit_key );
		$rate_limit_max    = 20;
		$rate_limit_window = 10 * MINUTE_IN_SECONDS;

		if ( $request_count >= $rate_limit_max ) {
			Promptor_Error::send( 'rate_limited', __( 'Too many attempts. Please try again later.', 'promptor' ) );
		}
		set_transient( $rate_limit_key, $request_count + 1, $rate_limit_window );

		// Sanitize and validate inputs.
		$name     = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$phone    = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$notes    = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';
		$query_id = isset( $_POST['query_id'] ) ? absint( $_POST['query_id'] ) : 0;

		// Normalize and validate phone number.
		if ( ! empty( $phone ) ) {
			$phone_digits = preg_replace( '/\D+/', '', $phone );
			$phone = preg_replace( '/[^\d+\s().-]/', '', $phone );
		if ( ! preg_match( '/^\+?[0-9\s().-]{7,30}$/', $phone ) ) {
			Promptor_Error::send( 'validation_error', __( 'Please provide a valid phone number (7-30 chars; digits, spaces, (), . and - allowed).', 'promptor' ) );
		}

			$digit_count  = strlen( $phone_digits );
			$total_length = function_exists( 'mb_strlen' ) ? mb_strlen( $phone, 'UTF-8' ) : strlen( $phone );

			if ( $digit_count < 7 || $total_length > 30 ) {
				Promptor_Error::send( 'validation_error', __( 'Please provide a valid phone number (7-30 characters).', 'promptor' ) );
			}

		}

		// Sanitize arrays.
		$services = array();
		if ( isset( $_POST['services'] ) && is_array( $_POST['services'] ) ) {
			$services = array_map( 'sanitize_text_field', wp_unslash( $_POST['services'] ) );
			$services = array_filter( $services );
		}

		$products = array();
		if ( isset( $_POST['products'] ) && is_array( $_POST['products'] ) ) {
			$products = array_map( 'sanitize_text_field', wp_unslash( $_POST['products'] ) );
			$products = array_filter( $products );
		}

		// Validate required fields.
		if ( empty( $name ) || empty( $email ) ) {
			Promptor_Error::send( 'validation_error', __( 'Name and email are required fields.', 'promptor' ) );
		}


		// Validate email format.
		if ( ! is_email( $email ) ) {
			Promptor_Error::send( 'validation_error', __( 'Please provide a valid email address.', 'promptor' ) );
		}


		// UTF-8 safe length checks.
		$name_length = function_exists( 'mb_strlen' ) ? mb_strlen( $name, 'UTF-8' ) : strlen( $name );
		$note_length = function_exists( 'mb_strlen' ) ? mb_strlen( $notes, 'UTF-8' ) : strlen( $notes );

		if ( $name_length > 100 ) {
			Promptor_Error::send( 'validation_error', __( 'Name is too long. Maximum 100 characters allowed.', 'promptor' ) );
		}

		if ( $note_length > 5000 ) {
			Promptor_Error::send( 'validation_error', __( 'Notes are too long. Maximum 5000 characters allowed.', 'promptor' ) );
		}

		// Store recommendations as readable string.
		$recommendations_text = $this->format_recommendations( $services, $products );

		// Prepare data for database.
		$submission_data = array(
			'name'            => $name,
			'email'           => $email,
			'phone'           => $phone,
			'recommendations' => $recommendations_text,
			'notes'           => $notes,
			'query_id'        => $query_id,
			'submitted_at'    => current_time( 'mysql' ),
		);

		// Insert submission using helper method.
		$submission_id = $this->insert_submission( $submission_data );

		if ( ! $submission_id ) {
			Promptor_Error::send( 'save_failed', __( 'Could not save your submission. Please try again later.', 'promptor' ) );
		}

		// Clear submission status counts cache.
		wp_cache_delete( 'promptor_submission_status_counts', self::CACHE_GROUP );

		// Track first lead captured for review prompt (v1.2.1).
		if ( ! get_option( 'promptor_first_lead_captured', false ) ) {
			require_once PROMPTOR_PATH . 'admin/class-promptor-onboarding.php';
			Promptor_Onboarding::mark_first_lead_captured();
		}

		// Track lead in telemetry (v1.2.1).
		if ( class_exists( 'Promptor_Telemetry' ) ) {
			Promptor_Telemetry::track_lead();
		}

		// Prepare placeholders for notifications.
		$notification_settings = get_option( 'promptor_notification_settings', array() );
		$placeholders          = array(
			'{site_name}'         => get_bloginfo( 'name' ),
			'{user_name}'         => $name,
			'{user_email}'        => $email,
			'{user_phone}'        => ! empty( $phone ) ? $phone : 'N/A',
			'{selected_services}' => ! empty( $services ) ? implode( ', ', $services ) : 'N/A',
			'{selected_products}' => ! empty( $products ) ? implode( ', ', $products ) : 'N/A',
			'{user_notes}'        => ! empty( $notes ) ? $notes : 'N/A',
		);

		// Send email notification with Reply-To set to user's email.
		if ( ! empty( $notification_settings['triggers']['new_lead']['email'] ) ) {
			$this->send_dynamic_email(
				'new_lead',
				$placeholders,
				array(
					'reply_to_email' => $email,
					'reply_to_name'  => $name,
				)
			);
		}

		// Send Slack notification.
		if ( ! empty( $notification_settings['triggers']['new_lead']['slack'] ) ) {
			$this->send_new_lead_slack( $placeholders );
		}

		wp_send_json_success(
			array(
				'message'       => __( 'Your request has been sent successfully! We will contact you soon.', 'promptor' ),
				'submission_id' => $submission_id,
			)
		);
	}

	/**
	 * Handle add to cart action
	 *
	 * @return void
	 */
	public function handle_add_to_cart() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'promptor_add_to_cart_nonce', 'nonce', false ) ) {
			Promptor_Error::send( 'nonce_failed', __( 'Security verification failed. Please refresh the page and try again.', 'promptor' ) );
		}

		// Check if WooCommerce is active.
		if ( ! class_exists( 'WooCommerce' ) ) {
			Promptor_Error::send( 'configuration_error', __( 'WooCommerce is not active.', 'promptor' ) );
		}

		// Ensure WooCommerce cart is initialized in AJAX context.
		if ( function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		} elseif ( null === WC()->cart && method_exists( WC(), 'initialize_cart' ) ) {
			WC()->initialize_cart();
		}

		// Sanitize inputs.
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$query_id   = isset( $_POST['query_id'] ) ? absint( $_POST['query_id'] ) : 0;

		// Validate product ID.
		if ( empty( $product_id ) ) {
			Promptor_Error::send( 'validation_error', __( 'Invalid product.', 'promptor' ) );
		}

		// Verify product exists and is purchasable.
		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() || 'publish' !== $product->get_status() ) {
			Promptor_Error::send( 'validation_error', __( 'This product cannot be purchased.', 'promptor' ) );
		}

		// Add product to cart with custom meta.
		$cart_item_key = WC()->cart->add_to_cart(
			$product_id,
			1,
			0,
			array(),
			array( 'promptor_query_id' => $query_id )
		);

		if ( ! $cart_item_key ) {
			Promptor_Error::send( 'save_failed', __( 'Failed to add product to cart. Please try again.', 'promptor' ) );
		}

		// Update query record if query_id is provided.
		if ( $query_id > 0 ) {
			// Get existing products with caching.
			$existing_products = $this->get_query_products( $query_id );

			// Build updated products list.
			$products_array = array();
			if ( ! empty( $existing_products ) ) {
				$products_array = array_map( 'absint', explode( ',', $existing_products ) );
				$products_array = array_filter( $products_array );
			}

			if ( ! in_array( $product_id, $products_array, true ) ) {
				$products_array[] = $product_id;
			}

			$new_products_csv = implode( ',', array_map( 'absint', $products_array ) );

			// Update database using helper method.
			$this->update_query_products( $query_id, $new_products_csv );

			// Send notifications.
			$notification_settings = get_option( 'promptor_notification_settings', array() );

			$placeholders = array(
				'{site_name}'     => get_bloginfo( 'name' ),
				'{product_name}'  => $product->get_name(),
				'{product_price}' => wp_strip_all_tags( $product->get_price_html() ),
				'{query_id}'      => $query_id,
			);

			// Email notification.
			if ( ! empty( $notification_settings['triggers']['product_added_to_cart']['email'] ) ) {
				$this->send_dynamic_email( 'product_added_to_cart', $placeholders );
			}

			// Slack notification.
			if ( ! empty( $notification_settings['triggers']['product_added_to_cart']['slack'] ) ) {
				$this->send_add_to_cart_slack( $placeholders, $product_id );
			}
		}

		// Get cart URL with fallback for older WooCommerce versions.
		$cart_url = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : wc_get_page_permalink( 'cart' );

		wp_send_json_success(
			array(
				'cart_url' => $cart_url,
				'message'  => __( 'Product added to cart!', 'promptor' ),
			)
		);
	}

	/**
	 * Format recommendations text
	 *
	 * @param array $services Services array.
	 * @param array $products Products array.
	 * @return string
	 */
	private function format_recommendations( $services, $products ) {
		$recommendations = array();

		if ( ! empty( $services ) && is_array( $services ) ) {
			$recommendations[] = 'Services: ' . implode( ', ', $services );
		}

		if ( ! empty( $products ) && is_array( $products ) ) {
			$recommendations[] = 'Products: ' . implode( ', ', $products );
		}

		return implode( '; ', $recommendations );
	}

	/**
	 * Send dynamic email notification
	 *
	 * @param string $template_key Template identifier.
	 * @param array  $placeholders Placeholder values.
	 * @param array  $opts         Optional options: reply_to_email, reply_to_name.
	 * @return bool
	 */
	private function send_dynamic_email( $template_key, $placeholders, $opts = array() ) {
		$settings       = get_option( 'promptor_notification_settings', array() );
		$email_settings = isset( $settings['email'] ) ? $settings['email'] : array();

		// Get template configuration.
		if ( class_exists( 'Promptor_Settings_Tab_Notifications' ) ) {
			$templates_config = ( new Promptor_Settings_Tab_Notifications() )->get_email_templates_config();
		} else {
			$templates_config = array();
		}

		// Get template or use default.
		$template = isset( $email_settings['templates'][ $template_key ] )
			? $email_settings['templates'][ $template_key ]
			: ( isset( $templates_config[ $template_key ] ) ? $templates_config[ $template_key ] : array() );

		if ( empty( $template ) ) {
			return false;
		}

		// Get recipients.
		$raw_to  = ! empty( $email_settings['recipients'] ) ? $email_settings['recipients'] : get_option( 'admin_email' );
		$to_list = is_array( $raw_to ) ? $raw_to : explode( ',', (string) $raw_to );
		$to_list = array_values(
			array_filter(
				array_map( 'sanitize_email', array_map( 'trim', $to_list ) )
			)
		);

		if ( empty( $to_list ) ) {
			$to_list = array( get_option( 'admin_email' ) );
		}

		// Prepare subject and body.
		$subject = isset( $template['subject'] ) ? wp_strip_all_tags( strtr( $template['subject'], $placeholders ) ) : '';
		$body    = isset( $template['body'] ) ? strtr( $template['body'], $placeholders ) : '';
		$body    = wpautop( wp_kses_post( $body ) );

		// Extract Reply-To from options.
		$reply_to_email = isset( $opts['reply_to_email'] ) ? sanitize_email( $opts['reply_to_email'] ) : '';
		$reply_to_name  = isset( $opts['reply_to_name'] ) ? wp_strip_all_tags( $opts['reply_to_name'] ) : '';

		return $this->send_email( $to_list, $subject, $body, $email_settings, true, $reply_to_email, $reply_to_name );
	}

	/**
	 * Send Slack notification for new lead
	 *
	 * @param array $placeholders Placeholder values.
	 * @return void
	 */
	private function send_new_lead_slack( $placeholders ) {
		$settings       = get_option( 'promptor_notification_settings', array() );
		$slack_settings = isset( $settings['slack'] ) ? $settings['slack'] : array();

		if ( empty( $slack_settings['enabled'] ) || empty( $slack_settings['webhook_url'] ) ) {
			return;
		}

		$message_template = isset( $slack_settings['new_lead_template'] )
			? $slack_settings['new_lead_template']
			: "🔔 *New Lead on {site_name}*\n> *Name:* {user_name}\n> *Email:* {user_email}\n> *Phone:* {user_phone}";

		$message = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $message_template );

		$this->send_slack( $message, $slack_settings );
	}

	/**
	 * Send Slack notification for add to cart
	 *
	 * @param array $placeholders Placeholder values.
	 * @param int   $product_id   Product ID.
	 * @return void
	 */
	private function send_add_to_cart_slack( $placeholders, $product_id ) {
		$settings       = get_option( 'promptor_notification_settings', array() );
		$slack_settings = isset( $settings['slack'] ) ? $settings['slack'] : array();

		if ( empty( $slack_settings['enabled'] ) || empty( $slack_settings['webhook_url'] ) ) {
			return;
		}

		$product_link = get_permalink( $product_id );
		$product_link = $product_link ? esc_url_raw( $product_link ) : '';

		$message = sprintf(
			"🛒 *Product Added to Cart on %s*\n> *Product:* <%s|%s> - %s\n> From Query ID: `%d`",
			wp_strip_all_tags( $placeholders['{site_name}'] ),
			$product_link,
			wp_strip_all_tags( $placeholders['{product_name}'] ),
			wp_strip_all_tags( $placeholders['{product_price}'] ),
			absint( $placeholders['{query_id}'] )
		);

		$this->send_slack( $message, $slack_settings );
	}

	/**
	 * Send email
	 *
	 * @param array|string $to              Recipient email(s).
	 * @param string       $subject         Email subject.
	 * @param string       $body            Email body.
	 * @param array        $email_settings  Email settings.
	 * @param bool         $is_html         Whether email is HTML.
	 * @param string       $reply_to_email  Optional Reply-To email.
	 * @param string       $reply_to_name   Optional Reply-To name.
	 * @return bool
	 */
	private function send_email( $to, $subject, $body, $email_settings, $is_html = false, $reply_to_email = '', $reply_to_name = '' ) {
		// Get server name safely.
		$server_name = isset( $_SERVER['SERVER_NAME'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) ) : wp_parse_url( home_url(), PHP_URL_HOST );
		$domain_part = preg_replace( '#^www\.#', '', strtolower( (string) $server_name ) );

		// Prepare from name and email.
		$from_name  = ! empty( $email_settings['from_name'] ) ? wp_strip_all_tags( $email_settings['from_name'] ) : wp_strip_all_tags( get_bloginfo( 'name' ) );
		$from_email = ! empty( $email_settings['from_email'] ) ? sanitize_email( $email_settings['from_email'] ) : 'wordpress@' . $domain_part;

		if ( ! is_email( $from_email ) ) {
			$from_email = 'wordpress@' . $domain_part;
		}

		// Prepare headers.
		$headers = array( "From: {$from_name} <{$from_email}>" );

		// Wrap body in branded HTML template.
		if ( class_exists( 'Promptor_Email_Template' ) ) {
			$body    = Promptor_Email_Template::wrap( $subject, $body, 'info' );
			$is_html = true;
		}

		if ( $is_html ) {
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
		}

		// Add Reply-To header if provided.
		if ( ! empty( $reply_to_email ) && is_email( $reply_to_email ) ) {
			$reply_to_display = ! empty( $reply_to_name ) ? $reply_to_name . ' <' . $reply_to_email . '>' : $reply_to_email;
			$headers[]        = 'Reply-To: ' . $reply_to_display;
		}

		// Ensure recipients array.
		$to_list = is_array( $to )
			? array_values( array_filter( array_map( 'sanitize_email', $to ) ) )
			: array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', (string) $to ) ) ) );

		if ( empty( $to_list ) ) {
			$to_list = array( get_option( 'admin_email' ) );
		}

		return wp_mail( $to_list, $subject, $body, $headers );
	}

	/**
	 * Send Slack notification
	 *
	 * @param string $message        Message to send.
	 * @param array  $slack_settings Slack settings.
	 * @return void
	 */
	private function send_slack( $message, $slack_settings ) {
		$payload = array( 'text' => $message );

		if ( ! empty( $slack_settings['channel'] ) ) {
			$payload['channel'] = sanitize_text_field( $slack_settings['channel'] );
		}

		if ( ! empty( $slack_settings['bot_name'] ) ) {
			$payload['username'] = sanitize_text_field( $slack_settings['bot_name'] );
		}

		$webhook = isset( $slack_settings['webhook_url'] ) ? esc_url_raw( $slack_settings['webhook_url'] ) : '';

		// Validate webhook URL.
		if ( empty( $webhook ) || 'https' !== wp_parse_url( $webhook, PHP_URL_SCHEME ) ) {
			return;
		}

		$webhook_host = wp_parse_url( $webhook, PHP_URL_HOST );
		if ( 'hooks.slack.com' !== $webhook_host ) {
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
	 * Insert submission into database
	 *
	 * @param array $data Submission data.
	 * @return int|false Submission ID on success, false on failure.
	 */
	private function insert_submission( $data ) {
		// Allow filtering submission data before insert.
		$data = apply_filters( 'promptor_before_insert_submission', $data );

		global $wpdb;
		
		// Use wpdb insert with cache invalidation.
		$cache_key = 'promptor_submissions_count';
		wp_cache_delete( $cache_key, self::CACHE_GROUP );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$wpdb->prefix . 'promptor_submissions',
			$data,
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		$submission_id = $wpdb->insert_id;

		// Update cache with new submission ID.
		wp_cache_set( 'submission_' . $submission_id, $data, self::CACHE_GROUP, HOUR_IN_SECONDS );

		// Trigger action after successful insert.
		do_action( 'promptor_after_insert_submission', $submission_id, $data );

		return $submission_id;
	}

	/**
	 * Get query products from database with caching
	 *
	 * @param int $query_id Query ID.
	 * @return string|null Comma-separated product IDs or null.
	 */
	private function get_query_products( $query_id ) {
		$cache_key = 'query_products_' . $query_id;
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$products = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT products_added_to_cart FROM {$wpdb->prefix}promptor_queries WHERE id = %d",
				$query_id
			)
		);

		// Always cache the result, even if null.
		wp_cache_set( $cache_key, $products, self::CACHE_GROUP, 10 * MINUTE_IN_SECONDS );

		return $products;
	}

	/**
	 * Update query products in database
	 *
	 * @param int    $query_id      Query ID.
	 * @param string $products_csv  Comma-separated product IDs.
	 * @return bool True on success, false on failure.
	 */
	private function update_query_products( $query_id, $products_csv ) {
		// Allow filtering products before update.
		$products_csv = apply_filters( 'promptor_before_update_query_products', $products_csv, $query_id );

		global $wpdb;

		// Invalidate old cache before update.
		$cache_key = 'query_products_' . $query_id;
		wp_cache_delete( $cache_key, self::CACHE_GROUP );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$wpdb->prefix . 'promptor_queries',
			array( 'products_added_to_cart' => $products_csv ),
			array( 'id' => $query_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false !== $result ) {
			// Set new cache after successful update.
			wp_cache_set( $cache_key, $products_csv, self::CACHE_GROUP, 10 * MINUTE_IN_SECONDS );

			// Trigger action after successful update.
			do_action( 'promptor_after_update_query_products', $query_id, $products_csv );

			return true;
		}

		return false;
	}
}