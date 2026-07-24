<?php
/**
 * Plugin Name:       Promptor
 * Plugin URI:        https://promptorai.com
 * Description:       Advanced AI-powered search, recommendation, and lead generation system for WordPress. Turn your website content into an intelligent sales assistant with Promptor. Engage visitors with AI-powered chat, recommend products & services, and generate leads effortlessly.
 * Version:           1.4.0
 * Author:            Corrplus
 * Author URI:        https://corrplus.net
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       promptor
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( defined( 'PROMPTOR_VERSION' ) ) { return; }

if ( ! function_exists( 'promptor_fs' ) ) {
	function promptor_fs() {
		static $fs = null;
		if ( null !== $fs ) {
			return $fs;
		}

		// CRITICAL: Skip Freemius initialization during activation to prevent null errors
		// Freemius menu system causes strpos/str_replace null warnings in WordPress admin
		if ( get_transient( 'promptor_activation_redirect' ) ) {
			// Return minimal mock object during activation
			$fs = new class {
				public function can_use_premium_code__premium_only() { return false; }
				public function can_use_premium_code() { return false; }
				public function is_registered() { return false; }
				public function is_anonymous() { return true; }
				public function get_upgrade_url( ...$args ) { return 'https://promptorai.com/pricing/'; }
				public function get_account_url( ...$args ) { return admin_url( 'admin.php?page=promptor' ); }
				public function set_basename( ...$args ) {}
				public function add_action( ...$args ) {}
				public function __call( $name, $args ) {
					if ( ! is_string( $name ) || '' === $name ) {
						return null;
					}
					if ( 0 === strpos( $name, 'is_' ) ) { return false; }
					if ( false !== strpos( $name, 'url' ) ) { return admin_url( 'admin.php?page=promptor' ); }
					return null;
				}
			};
			return $fs;
		}

		$sdk = __DIR__ . '/freemius/start.php';
		if ( file_exists( $sdk ) ) {
			if ( ! defined( 'WP_FS__PRODUCT_19673_MULTISITE' ) ) {
			// Vendor constant: Required for Freemius multisite licensing.
        	// The "wp" prefix warning is safely suppressed for this line.
        	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Required vendor constant (Freemius)
				define( 'WP_FS__PRODUCT_19673_MULTISITE', true );
			}
			require_once $sdk;
			$fs = fs_dynamic_init( array(
				'id'                  => '19673',
				'slug'                => 'promptor',
				'type'                => 'plugin',
				'public_key'          => 'pk_39ffe51c9b321e8d21b8c56d17745',
				'is_premium'          => true,
				'is_org_compliant'    => true,
				'premium_suffix'      => '(Pro)',
				'has_premium_version' => true,
				'has_addons'          => false,
				'has_paid_plans'      => true,
				'dev_mode'            => false,
				'menu'                => array(
					'slug'        => 'promptor',
					'first-path'  => 'admin.php?page=promptor',
					'parent_slug' => 'promptor',
					'pricing'     => 'promptor-pricing',
				),
			) );
		} else {
			$fs = new class {
				public function can_use_premium_code__premium_only() { return false; }
				public function can_use_premium_code() { return false; }
				public function is_registered() { return false; }
				public function is_anonymous() { return true; }
				public function get_upgrade_url( ...$args ) { return 'https://promptorai.com/pricing/'; }
				public function get_account_url( ...$args ) { return admin_url( 'admin.php?page=promptor' ); }
				public function set_basename( ...$args ) {}
				public function __call( $name, $args ) {
					// Null safety for PHP 8.1+
					if ( ! is_string( $name ) || '' === $name ) {
						return null;
					}
					if ( 0 === strpos( $name, 'is_' ) ) { return false; }
					if ( false !== strpos( $name, 'url' ) ) { return admin_url( 'admin.php?page=promptor' ); }
					return null;
				}
			};
		}

		return $fs;
	}

	// Only trigger Freemius actions after activation redirect completes
	if ( ! get_transient( 'promptor_activation_redirect' ) ) {
		do_action( 'promptor_fs_loaded' );

		// Register Freemius uninstall handler
		promptor_fs()->add_action( 'after_uninstall', 'promptor_fs_uninstall_cleanup' );
	}
}

/**
 * Freemius uninstall cleanup handler.
 * This replaces the static uninstall.php file to allow Freemius to track uninstall events.
 */
function promptor_fs_uninstall_cleanup() {
	global $wpdb;

	if ( is_multisite() ) {
		promptor_uninstall_cleanup_network();
		$site_ids = get_sites( array( 'fields' => 'ids' ) );
		if ( ! empty( $site_ids ) ) {
			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				promptor_uninstall_cleanup_single_site();
				restore_current_blog();
			}
		} else {
			promptor_uninstall_cleanup_single_site();
		}
	} else {
		promptor_uninstall_cleanup_single_site();
	}
}

/**
 * Clean up single site data.
 */
function promptor_uninstall_cleanup_single_site() {
	global $wpdb;

	// Drop custom tables
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}promptor_submissions" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}promptor_queries" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}promptor_embeddings" );
	// phpcs:enable

	// Delete options
	$site_options = array(
		'promptor_db_version',
		'promptor_query_count',
		'promptor_limit_reset_date',
		'promptor_contexts',
		'promptor_api_settings',
		'promptor_ui_settings',
		'promptor_notification_settings',
		'promptor_crawler_settings',
		'promptor_license_settings',
		'promptor_ai_usage',
		'promptor_cost_limits',
		'promptor_telemetry_enabled',
		'promptor_telemetry_id',
		'promptor_telemetry_queue',
		'promptor_telemetry_first_query_sent',
		'promptor_telemetry_queries_total',
		'promptor_telemetry_first_lead_captured',
		'promptor_telemetry_leads_total',
		'promptor_telemetry_step_1_completed',
		'promptor_telemetry_step_2_completed',
		'promptor_telemetry_step_3_completed',
		'promptor_telemetry_kb_count',
		'promptor_telemetry_kb_items_total',
		'promptor_wizard_completed',
		'promptor_wizard_dismissed',
		'promptor_activation_time',
		'promptor_telemetry_notice_dismissed',
		'promptor_trial_data',
		'promptor_trial_context',
	);
	foreach ( $site_options as $opt ) {
		delete_option( $opt );
		wp_cache_delete( $opt, 'options' );
	}

	delete_transient( 'promptor_activation_redirect' );
	wp_cache_delete( 'promptor_activation_redirect', 'transient' );

	// Delete all promptor transients
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
	$like1 = $wpdb->esc_like( '_transient_promptor_' ) . '%';
	$like2 = $wpdb->esc_like( '_transient_timeout_promptor_' ) . '%';
	$like3 = $wpdb->esc_like( '_transient_promptor_rl_' ) . '%';
	$like4 = $wpdb->esc_like( '_transient_timeout_promptor_rl_' ) . '%';
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE %s OR option_name LIKE %s
			    OR option_name LIKE %s OR option_name LIKE %s",
			$like1,
			$like2,
			$like3,
			$like4
		)
	);
	// phpcs:enable

	wp_clear_scheduled_hook( 'promptor_daily_license_check' );
	wp_clear_scheduled_hook( 'promptor_telemetry_send' );
}

/**
 * Clean up network-wide data.
 */
function promptor_uninstall_cleanup_network() {
	global $wpdb;

	$network_options = array(
		'promptor_db_version',
		'promptor_query_count',
		'promptor_limit_reset_date',
		'promptor_contexts',
		'promptor_api_settings',
		'promptor_ui_settings',
		'promptor_notification_settings',
		'promptor_crawler_settings',
		'promptor_license_settings',
		'promptor_ai_usage',
		'promptor_cost_limits',
		'promptor_telemetry_enabled',
		'promptor_telemetry_id',
		'promptor_telemetry_queue',
		'promptor_telemetry_first_query_sent',
		'promptor_telemetry_queries_total',
		'promptor_telemetry_first_lead_captured',
		'promptor_telemetry_leads_total',
		'promptor_telemetry_step_1_completed',
		'promptor_telemetry_step_2_completed',
		'promptor_telemetry_step_3_completed',
		'promptor_telemetry_kb_count',
		'promptor_telemetry_kb_items_total',
		'promptor_trial_data',
		'promptor_trial_context',
	);
	foreach ( $network_options as $opt ) {
		delete_site_option( $opt );
		wp_cache_delete( $opt, 'site-options' );
	}

	delete_site_transient( 'promptor_license_status_check' );
	wp_cache_delete( 'promptor_license_status_check', 'site-transient' );

	// Delete all promptor site transients
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
	$like1 = $wpdb->esc_like( '_site_transient_promptor_' ) . '%';
	$like2 = $wpdb->esc_like( '_site_transient_timeout_promptor_' ) . '%';
	$like3 = $wpdb->esc_like( '_site_transient_promptor_rl_' ) . '%';
	$like4 = $wpdb->esc_like( '_site_transient_timeout_promptor_rl_' ) . '%';
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->sitemeta}
			 WHERE meta_key LIKE %s OR meta_key LIKE %s
			    OR meta_key LIKE %s OR meta_key LIKE %s",
			$like1,
			$like2,
			$like3,
			$like4
		)
	);
	// phpcs:enable
}

define( 'PROMPTOR_VERSION', '1.4.0' );
define( 'PROMPTOR_PATH', plugin_dir_path( __FILE__ ) );
define( 'PROMPTOR_URL',  plugin_dir_url( __FILE__ ) );

if ( ! function_exists( 'promptor_is_pro' ) ) {
	function promptor_is_pro() {
		return function_exists( 'promptor_fs' ) && promptor_fs()->can_use_premium_code__premium_only();
	}
}


require_once __DIR__ . '/includes/class-promptor-activator.php';
require_once __DIR__ . '/includes/class-promptor-deactivator.php';
require_once __DIR__ . '/includes/class-promptor-error.php';
require_once __DIR__ . '/includes/class-promptor-cost-guard.php';

/**
 * Initialize the plugin.
 * Note: Translations are automatically loaded by WordPress for plugins hosted on WordPress.org.
 */
function promptor_boot() {
	require_once __DIR__ . '/includes/class-promptor-loader.php';
	if ( class_exists( 'Promptor_Loader' ) ) {
		$plugin = Promptor_Loader::get_instance();
		$plugin->run();
	}
}
add_action( 'plugins_loaded', 'promptor_boot' );
/**
 * Load WooCommerce-specific integrations (only if WooCommerce is active).
 */
function promptor_load_woocommerce_integrations() {
	// Ensure WooCommerce functions are available.
	if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_order' ) ) {
		return;
	}

	// Hook order item meta writer.
	add_action(
		'woocommerce_checkout_create_order_line_item',
		'promptor_save_query_id_to_order_item_meta',
		10,
		4
	);

	// Hook order-to-query linker.
	add_action(
		'woocommerce_thankyou',
		'promptor_link_order_to_query',
		10,
		1
	);
}
// Run after core boot, but still on plugins_loaded.
add_action( 'plugins_loaded', 'promptor_load_woocommerce_integrations', 20 );

function promptor_activate( $network_wide = false ) {
	if ( is_multisite() && $network_wide ) {
		$site_ids = get_sites( array( 'fields' => 'ids' ) );
		foreach ( $site_ids as $blog_id ) {
			switch_to_blog( $blog_id );
			Promptor_Activator::activate();
			restore_current_blog();
		}
	} else {
		Promptor_Activator::activate();
	}
}
register_activation_hook( __FILE__, 'promptor_activate' );

function promptor_deactivate() {
	Promptor_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'promptor_deactivate' );

function promptor_save_query_id_to_order_item_meta( $item, $cart_item_key, $values, $order ) {
	if ( isset( $values['promptor_query_id'] ) ) {
		$item->add_meta_data( '_promptor_query_id', absint( $values['promptor_query_id'] ), true );
	}
}

function promptor_link_order_to_query( $order_id ) {
	$order_id = absint( $order_id );
	if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) { return; }

	$order = wc_get_order( $order_id );
	if ( ! $order ) { return; }

	$query_id_found = 0;

	foreach ( $order->get_items() as $item ) {
		$query_id = $item->get_meta( '_promptor_query_id', true );
		if ( ! empty( $query_id ) ) {
			$query_id_found = (int) $query_id;
			break;
		}
	}

	if ( $query_id_found > 0 ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'promptor_queries';

		// Clear internal cache for this query.
		wp_cache_delete( 'promptor_q_' . $query_id_found, 'promptor' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Internal table, safe usage.
		$wpdb->update(
			$table_name,
			array(
				'linked_order_id' => $order_id,
				'order_value'     => (float) $order->get_total(),
			),
			array( 'id' => $query_id_found ),
			array( '%d', '%f' ),
			array( '%d' )
		);
	}
}

// Localize frontend AJAX variables (ajax_url + nonces)
function promptor_localize_public_ajax_vars() {
	// It's not needed on the admin screen, only on the public side.
if ( is_admin() ) {
		return;
	}

	static $did = false;
	if ( $did ) {
		return;
	}

	$data = array(
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'nonce'    => array(
			'ai_query'    => wp_create_nonce( 'promptor_ai_query_nonce' ),
			'feedback'    => wp_create_nonce( 'promptor_feedback_nonce' ),
			'form'        => wp_create_nonce( 'promptor_form_submit_nonce' ),
			'add_to_cart' => wp_create_nonce( 'promptor_add_to_cart_nonce' ),
		),
	);

	$handles = array( 'promptor-public', 'promptor' );

	foreach ( $handles as $handle ) {
		if ( wp_script_is( $handle, 'enqueued' ) || wp_script_is( $handle, 'registered' ) ) {
			wp_localize_script( $handle, 'promptor_public', $data );
			wp_set_script_translations( $handle, 'promptor', PROMPTOR_PATH . 'languages' );
			$did = true;
			break;
		}
	}
}
add_action( 'wp_enqueue_scripts', 'promptor_localize_public_ajax_vars', 100 );

// Only set Freemius basename after activation redirect completes
if ( function_exists( 'promptor_fs' ) && ! get_transient( 'promptor_activation_redirect' ) ) {
	promptor_fs()->set_basename( true, __FILE__ );
}