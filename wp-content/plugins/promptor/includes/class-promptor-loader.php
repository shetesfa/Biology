<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Promptor_Loader {

	protected $actions;
	protected $filters;
	private static $instance;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->actions = array();
		$this->filters = array();
		$this->load_dependencies();

		// Admin veya AJAX ise admin hook'ları bağla.
		if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			$this->define_admin_hooks();
		}

		// Public hook'lar her zaman.
		$this->define_public_hooks();
	}

	private function load_dependencies() {
		$require_if_exists = static function ( $path ) {
			if ( file_exists( $path ) ) {
				require_once $path; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
			}
			// Dosya yoksa sessizce geç; üretimde log/çıktı üretme.
		};

		// Core includes.
		$require_if_exists( PROMPTOR_PATH . 'includes/class-promptor-behavior.php' );
		$require_if_exists( PROMPTOR_PATH . 'includes/class-promptor-trial-client.php' );

		// Public taraf.
		$require_if_exists( PROMPTOR_PATH . 'public/class-promptor-public.php' );
		$require_if_exists( PROMPTOR_PATH . 'public/ajax-handlers/class-promptor-ajax-chat-handler.php' );
		$require_if_exists( PROMPTOR_PATH . 'public/ajax-handlers/class-promptor-ajax-form-handler.php' );
		$require_if_exists( PROMPTOR_PATH . 'public/ajax-handlers/class-promptor-ajax-indexing-handler.php' );

		// Email template (used by all notification senders).
		$require_if_exists( PROMPTOR_PATH . 'includes/class-promptor-email-template.php' );

		// Admin + AJAX tarafı (is_admin ya da DOING_AJAX).
		if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			$require_if_exists( PROMPTOR_PATH . 'admin/class-promptor-setup-wizard.php' );
			$require_if_exists( PROMPTOR_PATH . 'admin/class-promptor-admin.php' );
			$require_if_exists( PROMPTOR_PATH . 'admin/class-promptor-welcome-page.php' );
			$require_if_exists( PROMPTOR_PATH . 'admin/class-promptor-dashboard-page.php' );
			$require_if_exists( PROMPTOR_PATH . 'admin/class-promptor-list-table-pages.php' );
			$require_if_exists( PROMPTOR_PATH . 'admin/class-promptor-submissions-table.php' );
			$require_if_exists( PROMPTOR_PATH . 'admin/class-promptor-telemetry.php' );
			$require_if_exists( PROMPTOR_PATH . 'admin/class-promptor-telemetry-notice.php' );
			$require_if_exists( PROMPTOR_PATH . 'admin/class-promptor-onboarding.php' );
			$require_if_exists( PROMPTOR_PATH . 'admin/settings/class-promptor-settings-api.php' );
			$require_if_exists( PROMPTOR_PATH . 'admin/settings/class-promptor-settings-knowledge-base.php' );
			$require_if_exists( PROMPTOR_PATH . 'admin/settings/class-promptor-settings-notifications.php' );
			$require_if_exists( PROMPTOR_PATH . 'admin/settings/class-promptor-settings-ui.php' );
			$require_if_exists( PROMPTOR_PATH . 'admin/settings/class-promptor-settings-ai-usage.php' );
			$require_if_exists( PROMPTOR_PATH . 'admin/settings/class-promptor-settings-telemetry.php' );
			$require_if_exists( PROMPTOR_PATH . 'admin/class-promptor-settings-page.php' );
			$require_if_exists( PROMPTOR_PATH . 'public/ajax-handlers/class-promptor-ajax-admin-handler.php' );
		}
	}

	private function define_admin_hooks() {
		// Initialize telemetry system (v1.2.1)
		if ( class_exists( 'Promptor_Telemetry' ) ) {
			Promptor_Telemetry::init();
		}

		// Initialize telemetry opt-in notice (v1.2.2)
		// handle_notice_actions fires on admin_init (before current_screen) to process GET actions.
		// show_opt_in_notice is registered inside current_screen so get_current_screen() is available.
		if ( class_exists( 'Promptor_Telemetry_Notice' ) ) {
			add_action( 'admin_init', array( 'Promptor_Telemetry_Notice', 'handle_notice_actions' ) );
			add_action( 'current_screen', static function () {
				add_action( 'admin_notices', array( 'Promptor_Telemetry_Notice', 'show_opt_in_notice' ) );
				add_action( 'admin_notices', array( 'Promptor_Telemetry_Notice', 'show_coupon_received_notice' ) );
			} );
		}

		if ( class_exists( 'Promptor_Admin' ) ) {
			$plugin_admin = new Promptor_Admin( 'promptor', PROMPTOR_VERSION );
			$this->add_action( 'admin_init', $plugin_admin, 'handle_activation_redirect' );
			$this->add_action( 'admin_menu', $plugin_admin, 'add_admin_menu' );
			$this->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles_and_scripts' );
		}

		if ( class_exists( 'Promptor_Settings_Page' ) ) {
			$settings_page = new Promptor_Settings_Page();
			$this->add_action( 'admin_init', $settings_page, 'register_settings_for_all_tabs' );
		}

		if ( class_exists( 'Promptor_Ajax_Admin_Handler' ) ) {
			$ajax_admin_handler = new Promptor_Ajax_Admin_Handler();
			$this->add_action( 'wp_ajax_promptor_add_context',                  $ajax_admin_handler, 'handle_add_context' );
			$this->add_action( 'wp_ajax_promptor_delete_context',               $ajax_admin_handler, 'handle_delete_context' );
			$this->add_action( 'wp_ajax_promptor_update_submission_status',     $ajax_admin_handler, 'handle_update_submission_status' );
			$this->add_action( 'wp_ajax_promptor_get_query_details',            $ajax_admin_handler, 'handle_get_query_details' );
			$this->add_action( 'wp_ajax_promptor_get_indexing_stats',           $ajax_admin_handler, 'handle_get_indexing_stats' );
			$this->add_action( 'wp_ajax_promptor_save_content_selection',       $ajax_admin_handler, 'handle_save_content_selection' );
			$this->add_action( 'wp_ajax_promptor_verify_api_key',               $ajax_admin_handler, 'handle_verify_api_key' );
			$this->add_action( 'wp_ajax_promptor_send_test_email',              $ajax_admin_handler, 'handle_send_test_email' );
			$this->add_action( 'wp_ajax_promptor_send_test_slack',              $ajax_admin_handler, 'handle_send_test_slack' );
			$this->add_action( 'wp_ajax_promptor_save_notification_settings',   $ajax_admin_handler, 'handle_save_notification_settings' );
			$this->add_action( 'wp_ajax_promptor_bulk_update_roles',            $ajax_admin_handler, 'handle_bulk_update_content_roles' );
			$this->add_action( 'wp_ajax_promptor_update_single_role',           $ajax_admin_handler, 'handle_update_single_content_role' );
			$this->add_action( 'wp_ajax_promptor_generate_example_questions',   $ajax_admin_handler, 'handle_generate_example_questions' );
			$this->add_action( 'wp_ajax_promptor_load_more_content',            $ajax_admin_handler, 'handle_load_more_content' );
			// Setup Wizard AJAX handlers (v1.2.2)
			$this->add_action( 'wp_ajax_promptor_wizard_load_content',          $ajax_admin_handler, 'handle_wizard_load_content' );
			$this->add_action( 'wp_ajax_promptor_wizard_save_api',              $ajax_admin_handler, 'handle_wizard_save_api' );
			$this->add_action( 'wp_ajax_promptor_wizard_save_content',          $ajax_admin_handler, 'handle_wizard_save_content' );
			$this->add_action( 'wp_ajax_promptor_wizard_complete',              $ajax_admin_handler, 'handle_wizard_complete' );
			// Trial system AJAX handlers (v1.4.0)
			$this->add_action( 'wp_ajax_promptor_trial_activate',               $ajax_admin_handler, 'handle_trial_activate' );
			$this->add_action( 'wp_ajax_promptor_trial_status',                 $ajax_admin_handler, 'handle_trial_status' );
			$this->add_action( 'wp_ajax_promptor_trial_index_content',          $ajax_admin_handler, 'handle_trial_index_content' );
			$this->add_action( 'wp_ajax_promptor_wizard_telemetry_optin',     $ajax_admin_handler, 'handle_wizard_telemetry_optin' );
		}

		if ( class_exists( 'Promptor_Ajax_Indexing_Handler' ) ) {
			$ajax_indexing_handler = new Promptor_Ajax_Indexing_Handler();
			$this->add_action( 'wp_ajax_promptor_start_sync',       $ajax_indexing_handler, 'handle_start_sync' );
			$this->add_action( 'wp_ajax_promptor_start_crawl',      $ajax_indexing_handler, 'handle_start_crawl' );
			$this->add_action( 'wp_ajax_promptor_process_item',     $ajax_indexing_handler, 'handle_process_item' );
			$this->add_action( 'wp_ajax_promptor_clear_index',      $ajax_indexing_handler, 'handle_clear_index' );
		}
	}

	private function define_public_hooks() {
		if ( class_exists( 'Promptor_Public' ) ) {
			$plugin_public = new Promptor_Public( 'promptor', PROMPTOR_VERSION );
			// Eğer public tarafta ek hook varsa burada ekleyebilirsin.
		}

		if ( class_exists( 'Promptor_Ajax_Chat_Handler' ) ) {
			$ajax_chat_handler = new Promptor_Ajax_Chat_Handler();
			$this->add_action( 'wp_ajax_promptor_get_ai_suggestion',            $ajax_chat_handler, 'handle_ai_query' );
			$this->add_action( 'wp_ajax_nopriv_promptor_get_ai_suggestion',     $ajax_chat_handler, 'handle_ai_query' );
			$this->add_action( 'wp_ajax_promptor_save_feedback',                $ajax_chat_handler, 'handle_save_feedback' );
			$this->add_action( 'wp_ajax_nopriv_promptor_save_feedback',         $ajax_chat_handler, 'handle_save_feedback' );
		}

		if ( class_exists( 'Promptor_Ajax_Form_Handler' ) ) {
			$ajax_form_handler = new Promptor_Ajax_Form_Handler();
			$this->add_action( 'wp_ajax_promptor_submit_contact_form',          $ajax_form_handler, 'handle_contact_form_submission' );
			$this->add_action( 'wp_ajax_nopriv_promptor_submit_contact_form',   $ajax_form_handler, 'handle_contact_form_submission' );
			$this->add_action( 'wp_ajax_promptor_add_to_cart',                  $ajax_form_handler, 'handle_add_to_cart' );
			$this->add_action( 'wp_ajax_nopriv_promptor_add_to_cart',           $ajax_form_handler, 'handle_add_to_cart' );
		}
	}

	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
	}

	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
	}

	private function add( $hooks, $hook, $component, $callback, $priority, $accepted_args ) {
		// Geçerli obje/metod mu?
		if ( is_object( $component ) && is_string( $callback ) && method_exists( $component, $callback ) ) {
			$hooks[] = array(
				'hook'          => $hook,
				'component'     => $component,
				'callback'      => $callback,
				'priority'      => $priority,
				'accepted_args' => $accepted_args,
			);
		}
		// Geçersizse sessizce atla (prod’da çıktı/log yok).
		return $hooks;
	}

	public function run() {
		foreach ( $this->filters as $hook ) {
			add_filter( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}
		foreach ( $this->actions as $hook ) {
			add_action( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}
	}
}