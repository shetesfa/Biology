<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class Promptor_Admin {

	private $plugin_name;
	private $version;

	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles_and_scripts' ) );
		add_action( 'admin_init', array( $this, 'handle_activation_redirect' ) );
		add_action( 'admin_init', array( $this, 'handle_wizard_redirect' ) );
		add_action( 'admin_init', array( $this, 'check_for_upgrade' ) );
		add_action( 'admin_init', array( $this, 'track_admin_visit' ) );
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_notifications' ), 999 );
		add_action( 'admin_notices', array( $this, 'show_review_prompt' ) );
		add_action( 'wp_ajax_promptor_dismiss_review_prompt', array( $this, 'ajax_dismiss_review_prompt' ) );
		add_action( 'wp_ajax_promptor_dismiss_onboarding', array( $this, 'ajax_dismiss_onboarding' ) );
		// CRITICAL: Fix globals BEFORE admin-header.php renders (prevents ALL PHP 8.1 warnings)
		add_action( 'in_admin_header', array( $this, 'fix_admin_globals_before_header' ), 1 );
		// Dark mode body class
		add_filter( 'admin_body_class', array( $this, 'add_dark_mode_body_class' ) );
	}

	/**
	 * CRITICAL FIX: Fix all WordPress global variables BEFORE admin-header.php renders.
	 * Prevents PHP 8.1 deprecated warnings: strip_tags/strpos/str_replace passing null.
	 *
	 * Root cause: add_submenu_page(null, ...) for hidden pages leaves $title, $parent_file,
	 * $submenu_file as null. WordPress core calls strpos/str_replace on these null values.
	 *
	 * Hook: in_admin_header (priority 1)
	 *
	 * @since 1.2.2
	 * @return void
	 */
	public function fix_admin_globals_before_header() {
		global $title, $parent_file, $submenu_file, $plugin_page, $typenow, $taxnow, $pagenow;

		// Get current page from query string.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just reading page slug for global fix
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		// For wizard page (hidden submenu), WordPress leaves globals as null.
		if ( 'promptor-setup-wizard' === $current_page ) {
			$title        = __( 'Setup Wizard', 'promptor' );
			$parent_file  = 'promptor';
			$submenu_file = 'promptor-setup-wizard';
			$plugin_page  = 'promptor-setup-wizard';
			$pagenow      = 'admin.php';
			$typenow      = '';
			$taxnow       = '';
		}

		// For all admin pages: Ensure globals are NEVER null (PHP 8.1 requirement).
		if ( ! isset( $title ) || null === $title ) {
			$title = '';
		}
		if ( ! isset( $parent_file ) || null === $parent_file ) {
			$parent_file = '';
		}
		if ( ! isset( $submenu_file ) || null === $submenu_file ) {
			$submenu_file = '';
		}
		if ( ! isset( $plugin_page ) || null === $plugin_page ) {
			$plugin_page = '';
		}
		if ( ! isset( $typenow ) || null === $typenow ) {
			$typenow = '';
		}
		if ( ! isset( $taxnow ) || null === $taxnow ) {
			$taxnow = '';
		}
	}

	/**
	 * Set wizard page globals BEFORE admin-header.php runs.
	 *
	 * For hidden submenu pages with empty parent, get_admin_page_title() returns null.
	 * This null propagates to strpos(null) and str_replace(null) in WordPress core,
	 * triggering PHP 8.1 deprecated warnings before headers are sent.
	 *
	 * Hook: load-{wizard_hook} (fires BEFORE admin-header.php)
	 *
	 * @since 1.2.2
	 * @return void
	 */
	public function setup_wizard_globals() {
		global $title, $parent_file, $submenu_file, $plugin_page, $typenow, $taxnow, $pagenow;

		$title        = __( 'Setup Wizard', 'promptor' );
		$parent_file  = 'promptor';
		$submenu_file = 'promptor-setup-wizard';
		$plugin_page  = 'promptor-setup-wizard';
		$pagenow      = 'admin.php';
		$typenow      = '';
		$taxnow       = '';
	}

	/**
	 * Add dark mode CSS class to admin body.
	 *
	 * @since 1.3.0
	 * @param string $classes Space-separated list of body classes.
	 * @return string
	 */
	public function add_dark_mode_body_class( $classes ) {
		$screen = get_current_screen();
		if ( ! is_object( $screen ) || false === strpos( $screen->id, 'promptor' ) ) {
			return $classes;
		}

		$mode = get_option( 'promptor_admin_dark_mode', 'auto' );

		if ( 'dark' === $mode ) {
			$classes .= ' promptor-dark';
		} elseif ( 'light' === $mode ) {
			$classes .= ' promptor-light';
		}
		// 'auto' mode uses no extra class — CSS media query handles it.

		return $classes;
	}

	public function handle_activation_redirect() {
		if ( get_transient( 'promptor_activation_redirect' ) ) {
			delete_transient( 'promptor_activation_redirect' );
			if ( ! is_multisite() ) {
				require_once PROMPTOR_PATH . 'admin/class-promptor-onboarding.php';
				if ( Promptor_Onboarding::is_setup_completed() ) {
					wp_safe_redirect( admin_url( 'admin.php?page=promptor' ) );
				} else {
					wp_safe_redirect( admin_url( 'admin.php?page=promptor-setup-wizard' ) );
				}
				exit;
			}
		}

		// Handle wizard skip action.
		if (
			isset( $_GET['promptor_wizard_action'] ) && // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'skip' === sanitize_key( $_GET['promptor_wizard_action'] ) && // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			check_admin_referer( 'promptor_skip_wizard' )
		) {
			update_option( 'promptor_wizard_completed', true );
			wp_safe_redirect( admin_url( 'admin.php?page=promptor' ) );
			exit;
		}

		// Handle wizard reset (re-run) action.
		if (
			isset( $_GET['promptor_wizard_action'] ) && // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'reset' === sanitize_key( $_GET['promptor_wizard_action'] ) && // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			check_admin_referer( 'promptor_reset_wizard' )
		) {
			delete_option( 'promptor_wizard_completed' );
			wp_safe_redirect( admin_url( 'admin.php?page=promptor-setup-wizard' ) );
			exit;
		}
	}

	/**
	 * Redirect to setup wizard if not yet completed or dismissed.
	 * Triggers when user visits the Welcome (main Promptor) page.
	 *
	 * @since 1.2.3
	 * @return void
	 */
	public function handle_wizard_redirect() {
		// Only applies to the Welcome page.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading admin page slug for routing only; no state is modified.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'promptor' !== $page ) {
			return;
		}

		// Don't redirect if already handling a wizard action.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking for a URL action flag; actual wizard actions verify nonces separately.
		if ( isset( $_GET['promptor_wizard_action'] ) ) {
			return;
		}

		$wizard_completed = get_option( 'promptor_wizard_completed', false );
		$wizard_dismissed = get_option( 'promptor_wizard_dismissed', false );

		if ( ! $wizard_completed && ! $wizard_dismissed ) {
			wp_safe_redirect( admin_url( 'admin.php?page=promptor-setup-wizard' ) );
			exit;
		}
	}

	public function check_for_upgrade() {
		$current_version = get_option( 'promptor_db_version', '1.0.0' );
		if ( version_compare( $current_version, PROMPTOR_VERSION, '<' ) ) {
			$this->upgrade_routine( $current_version );
			update_option( 'promptor_db_version', PROMPTOR_VERSION );
		}
	}

	private function upgrade_routine( $from_version ) {
		// Clear changelog caches on upgrade to ensure fresh read
		delete_transient( 'promptor_full_changelog' );
		delete_transient( 'promptor_latest_changelog' );

		if ( version_compare( $from_version, '1.1.0', '<' ) ) {
			// Placeholder for future upgrades
		}
	}

	public function add_admin_bar_notifications( $wp_admin_bar ) {
		// Skip during activation - prevents database errors and null warnings
		if ( get_transient( 'promptor_activation_redirect' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// Avoid unnecessary DB work if the admin bar is not showing.
		if ( ! is_admin_bar_showing() ) {
			return;
		}

		global $wpdb;

		// Check if table exists before querying (prevents errors during/after activation)
		$table_name = $wpdb->prefix . 'promptor_submissions';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );
		if ( ! $table_exists ) {
			return;
		}

		// Direct query on an internal table with proper prepare; safe and intentional.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$pending_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}promptor_submissions WHERE status = %s",
				'pending'
			)
		);

		if ( $pending_count > 0 ) {
			$args = array(
				'id'    => 'promptor-pending-leads',
				'title' => '<span class="ab-icon dashicons-format-aside"></span><span class="ab-label">' . esc_html( $pending_count ) . '</span>',
				'href'  => esc_url( admin_url( 'admin.php?page=promptor-submissions&status=pending' ) ),
				'meta'  => array(
					/* translators: %d: number of pending submissions. */
					'title' => sprintf( esc_attr__( '%d pending submissions', 'promptor' ), $pending_count ),
				),
			);
			$wp_admin_bar->add_node( $args );
		}
	}

	public function add_admin_menu() {
		$icon_svg_base64 = 'data:image/svg+xml;base64,' . base64_encode(
			'
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 569.96 569.96" fill="#a0a5aa">
          <path d="M486.49 486.49c51.57-51.57 83.47-122.82 83.47-201.51V0H284.98C206.29 0 135.04 31.9 83.47 83.47 31.9 135.04 0 206.28 0 284.98v284.98h284.98c78.69 0 149.94-31.9 201.51-83.47m-200.94-384.6h182.52l-53.63 53.63c33.13 33.13 53.63 78.9 53.63 129.47s-20.49 96.33-53.63 129.46-78.91 53.63-129.47 53.63H101.88l53.63-53.63c-33.13-33.13-53.63-78.9-53.63-129.46s20.49-96.33 53.63-129.47c33.13-33.13 78.91-53.63 129.47-53.63h.57Z"/>
          <path d="m167 569.96 101.89-101.89h-167L0 569.96h167zM402.95 0 301.06 101.89h167.01L569.96 0H402.95zM284.98 267.36c9.73 0 17.62 7.89 17.62 17.62s-7.89 17.62-17.62 17.62-17.62-7.89-17.62-17.62 7.89-17.62 17.62-17.62m-70.48 0c9.73 0 17.62 7.89 17.62 17.62s-7.89 17.62-17.62 17.62-17.62-7.89-17.62-17.62 7.89-17.62 17.62-17.62Zm140.95 0c9.73 0 17.62 7.89 17.62 17.62s-7.89 17.62-17.62 17.62-17.62-7.89-17.62-17.62 7.89-17.62 17.62-17.62Z"/>
        </svg>'
		);

		add_menu_page(
			__( 'Promptor', 'promptor' ),
			__( 'Promptor', 'promptor' ),
			'manage_options',
			'promptor',
			array( $this, 'render_welcome_page' ),
			$icon_svg_base64,
			30
		);

		add_submenu_page( 'promptor', __( 'Welcome', 'promptor' ), __( 'Welcome', 'promptor' ), 'manage_options', 'promptor', array( $this, 'render_welcome_page' ) );

		// Setup Wizard - Hidden from menu, accessed via direct link.
		// IMPORTANT: Pass '' (not null) as parent_slug to avoid plugin_basename(null)
		// which triggers PHP 8.1 deprecated warnings on EVERY admin page load.
		// Empty string gives the same screen ID (admin_page_promptor-setup-wizard) as null.
		$wizard_hook = add_submenu_page( '', __( 'Setup Wizard', 'promptor' ), '', 'manage_options', 'promptor-setup-wizard', array( $this, 'render_wizard_page' ) );
		if ( $wizard_hook ) {
			// load-{hook} fires BEFORE admin-header.php runs, preventing PHP 8.1 warnings
			// from get_admin_page_title() returning null for hidden pages.
			add_action( 'load-' . $wizard_hook, array( $this, 'setup_wizard_globals' ) );
		}

		// Dashboard is Pro-only feature
		if ( promptor_is_pro() ) {
			add_submenu_page( 'promptor', __( 'Dashboard', 'promptor' ), __( 'Dashboard', 'promptor' ), 'manage_options', 'promptor-dashboard', array( $this, 'render_dashboard_page' ) );
		}

		add_submenu_page( 'promptor', __( 'Submissions', 'promptor' ), __( 'Submissions', 'promptor' ), 'manage_options', 'promptor-submissions', array( $this, 'render_submissions_page' ) );
		add_submenu_page( 'promptor', __( 'AI Conversations', 'promptor' ), __( 'AI Conversations', 'promptor' ), 'manage_options', 'promptor-queries', array( $this, 'render_queries_page' ) );
		add_submenu_page( 'promptor', __( 'Settings', 'promptor' ), __( 'Settings', 'promptor' ), 'manage_options', 'promptor-settings', array( $this, 'render_settings_page' ) );

		// Full Changelog page
		add_submenu_page( 'promptor', __( 'Changelog', 'promptor' ), __( 'Changelog', 'promptor' ), 'manage_options', 'promptor-changelog', array( $this, 'render_changelog_page' ) );
	}

	public function render_welcome_page() {
		require_once PROMPTOR_PATH . 'admin/class-promptor-welcome-page.php';
		$welcome_page = new Promptor_Welcome_Page();
		$welcome_page->render_page();
	}

	public function render_wizard_page() {
		$wizard = new Promptor_Setup_Wizard();
		$wizard->render_page();
	}

	public function render_dashboard_page() {
		require_once PROMPTOR_PATH . 'admin/class-promptor-dashboard-page.php';
		$dashboard_page = new Promptor_Dashboard_Page();
		$dashboard_page->render_page();
	}

	public function render_submissions_page() {
		require_once PROMPTOR_PATH . 'admin/class-promptor-submissions-table.php';
		require_once PROMPTOR_PATH . 'admin/class-promptor-list-table-pages.php';
		$list_table_page = new Promptor_List_Table_Pages();
		$list_table_page->render_submissions_page();
	}

	public function render_queries_page() {
		require_once PROMPTOR_PATH . 'admin/class-promptor-list-table-pages.php';
		$list_table_page = new Promptor_List_Table_Pages();
		$list_table_page->render_queries_page();
	}

	public function render_settings_page() {
		require_once PROMPTOR_PATH . 'admin/class-promptor-settings-page.php';
		$settings_page = new Promptor_Settings_Page();
		$settings_page->render_page();
	}

	public function render_changelog_page() {
		require_once PROMPTOR_PATH . 'admin/class-promptor-changelog-page.php';
		$page = new Promptor_Changelog_Page();
		$page->render_page();
	}

	public function enqueue_styles_and_scripts( $hook_suffix ) {
		$pages = array(
			'toplevel_page_promptor',
			'admin_page_promptor-setup-wizard',
			'promptor_page_promptor-dashboard',
			'promptor_page_promptor-submissions',
			'promptor_page_promptor-queries',
			'promptor_page_promptor-settings',
			'promptor_page_promptor-changelog',
			'promptor_page_promptor-webhooks',
		);

		if ( in_array( $hook_suffix, $pages, true ) ) {
			wp_enqueue_style( $this->plugin_name, PROMPTOR_URL . 'admin/assets/css/promptor-admin.css', array(), $this->version, 'all' );

			wp_enqueue_media();
			$script_dependencies = array( 'jquery', 'wp-color-picker', 'wp-i18n', 'media-editor' );

			if ( 'promptor_page_promptor-dashboard' === $hook_suffix ) {
				wp_enqueue_script( 'chart-js', PROMPTOR_URL . 'admin/assets/js/chart.js', array(), '4.4.1', true );
				$script_dependencies[] = 'chart-js';
			}

			wp_enqueue_script( 'promptor-toast', PROMPTOR_URL . 'admin/assets/js/promptor-toast.js', array( 'jquery' ), $this->version, true );
			$script_dependencies[] = 'promptor-toast';

			wp_enqueue_script( $this->plugin_name, PROMPTOR_URL . 'admin/assets/js/promptor-admin.js', $script_dependencies, $this->version, true );
			wp_set_script_translations( $this->plugin_name, 'promptor', PROMPTOR_PATH . 'languages' );

			wp_localize_script(
				$this->plugin_name,
				'promptor_admin_ajax',
				array(
					'ajax_url'                 => admin_url( 'admin-ajax.php' ),
					'nonce'                    => wp_create_nonce( 'promptor_indexing_nonce' ),
					'verify_api_key_nonce'     => wp_create_nonce( 'promptor_verify_api_key_nonce' ),
					'manage_context_nonce'     => wp_create_nonce( 'promptor_manage_context_nonce' ),
					'submissions_nonce'        => wp_create_nonce( 'promptor_update_status_nonce' ),
					'link_order_nonce'         => wp_create_nonce( 'promptor_link_order_nonce' ),
					'test_email_nonce'         => wp_create_nonce( 'promptor_test_email_nonce' ),
					'test_slack_nonce'         => wp_create_nonce( 'promptor_test_slack_nonce' ),
					'save_notifications_nonce' => wp_create_nonce( 'promptor_save_notifications_nonce' ),
					'test_license_nonce'       => wp_create_nonce( 'promptor_ajax_test_license' ),
					'kb_nonce'                 => wp_create_nonce( 'promptor_kb_nonce' ),
					'promptor_icon_url'        => PROMPTOR_URL . 'admin/assets/images/promptor-logo-icon.png',
					'is_premium'               => promptor_is_pro(),
					'max_free_items'           => 3,
					'i18n'                     => array(
						'confirm_clear'          => esc_js( __( 'Are you sure you want to permanently delete all indexed data for this knowledge base?', 'promptor' ) ),
						'confirm_delete_context' => esc_js( __( 'Are you sure you want to delete this knowledge base? This action cannot be undone.', 'promptor' ) ),
					),
				)
			);
		}
	}

	/**
	 * Track admin visit for onboarding (v1.2.1).
	 *
	 * @since 1.2.1
	 * @return void
	 */
	public function track_admin_visit() {
		// Skip during activation to prevent header issues
		if ( get_transient( 'promptor_activation_redirect' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! is_object( $screen ) || ! isset( $screen->id ) || false === strpos( $screen->id, 'promptor' ) ) {
			return;
		}

		require_once PROMPTOR_PATH . 'admin/class-promptor-onboarding.php';
		Promptor_Onboarding::track_admin_visit();
	}

	/**
	 * Show review prompt on Promptor admin pages (v1.2.1).
	 *
	 * @since 1.2.1
	 * @return void
	 */
	public function show_review_prompt() {
		// Skip during activation to prevent header issues
		if ( get_transient( 'promptor_activation_redirect' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! is_object( $screen ) || ! isset( $screen->id ) || false === strpos( $screen->id, 'promptor' ) ) {
			return;
		}

		require_once PROMPTOR_PATH . 'admin/class-promptor-onboarding.php';

		if ( ! Promptor_Onboarding::should_show_review_prompt() ) {
			return;
		}

		// Mark as shown if triggered by first lead
		$first_lead_captured = get_option( 'promptor_first_lead_captured', false );
		if ( $first_lead_captured ) {
			Promptor_Onboarding::mark_review_shown_for_lead();
		}

		$logo_url = PROMPTOR_URL . 'admin/assets/images/promptor-logo-welcome.png';
		$review_url = 'https://wordpress.org/support/plugin/promptor/reviews/#new-post';
		$feedback_url = 'https://wordpress.org/support/plugin/promptor/';
		$nonce = wp_create_nonce( 'promptor_dismiss_review_nonce' );
		?>
		<div class="notice notice-info is-dismissible promptor-review-notice" style="padding: 15px; display: flex; align-items: center; gap: 15px;">
			<div style="flex-shrink: 0;">
				<img src="<?php echo esc_url( $logo_url ); ?>" alt="Promptor" style="width: 60px; height: auto;">
			</div>
			<div style="flex-grow: 1;">
				<h3 style="margin: 0 0 8px 0; font-size: 15px;">
					<?php esc_html_e( 'Enjoying Promptor so far?', 'promptor' ); ?>
				</h3>
				<p style="margin: 0 0 10px 0;">
					<?php esc_html_e( 'We would love to hear your feedback! Your review helps us improve and helps other users discover Promptor.', 'promptor' ); ?>
				</p>
				<p style="margin: 0;">
					<a href="<?php echo esc_url( $review_url ); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Leave a Review', 'promptor' ); ?>
					</a>
					<a href="<?php echo esc_url( $feedback_url ); ?>" class="button button-secondary" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Give Feedback', 'promptor' ); ?>
					</a>
				</p>
			</div>
			<script type="text/javascript">
			jQuery(document).ready(function($) {
				$('.promptor-review-notice').on('click', '.notice-dismiss', function() {
					$.post(ajaxurl, {
						action: 'promptor_dismiss_review_prompt',
						nonce: '<?php echo esc_js( $nonce ); ?>'
					});
				});
			});
			</script>
		</div>
		<?php
	}

	/**
	 * AJAX handler to dismiss review prompt (v1.2.1).
	 *
	 * @since 1.2.1
	 * @return void
	 */
	public function ajax_dismiss_review_prompt() {
		check_ajax_referer( 'promptor_dismiss_review_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'promptor' ), 403 );
		}

		require_once PROMPTOR_PATH . 'admin/class-promptor-onboarding.php';
		Promptor_Onboarding::dismiss_review_prompt();

		wp_send_json_success();
	}

	/**
	 * AJAX handler to dismiss onboarding section.
	 *
	 * @since 1.2.1
	 */
	public function ajax_dismiss_onboarding() {
		check_ajax_referer( 'promptor_dismiss_onboarding_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'promptor' ), 403 );
		}

		$user_id = get_current_user_id();
		update_user_meta( $user_id, 'promptor_onboarding_dismissed', '1' );

		wp_send_json_success();
	}
}
