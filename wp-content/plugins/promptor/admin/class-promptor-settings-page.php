<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main class to handle the settings page and route to the correct tab.
 */
class Promptor_Settings_Page {

    private $tabs;

    public function __construct() {
        $this->tabs = array(
            'knowledge_bases' => new Promptor_Settings_Tab_Knowledge_Base(),
            'api_settings'    => new Promptor_Settings_Tab_Api(),
            'ui_settings'     => new Promptor_Settings_Tab_Ui(),
            'ai_usage'        => new Promptor_Settings_Tab_Ai_Usage(),
            'telemetry'       => new Promptor_Settings_Tab_Telemetry(),
        );

        // Notifications tab is Pro-only
        if ( promptor_is_pro() ) {
            $this->tabs['notifications'] = new Promptor_Settings_Tab_Notifications();
        }
    }

    public function register_settings_for_all_tabs() {
        foreach ($this->tabs as $tab) {
            if (method_exists($tab, 'register_settings')) {
                $tab->register_settings();
            }
        }
    }

    public function render_page() {
        // Capability check — block direct access via URL.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'promptor' ) );
        }

        $allowed_tabs = array_keys( $this->tabs );
        $active_tab   = 'knowledge_bases';

        // Read the requested tab (from URL).
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab switching is a read-only display action; no state is modified.
        $requested_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : $active_tab;

        // Back-compat alias.
        if ( 'content_selection' === $requested_tab ) {
            $requested_tab = 'knowledge_bases';
        }

        // Tab navigation is read-only display — no nonce required for switching tabs.
        // Nonces are enforced at the form submission level (settings_fields/options.php).
        if ( in_array( $requested_tab, $allowed_tabs, true ) ) {
            $active_tab = $requested_tab;
        }

        ?>
        <div class="wrap promptor-settings-wrap">
            <div class="promptor-page-header">
                <div class="header-content">
                    <h1>
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php esc_html_e('Settings', 'promptor'); ?>
                    </h1>
                    <p class="page-subtitle"><?php esc_html_e( 'Configure your AI assistant, knowledge bases, and customize the chat experience.', 'promptor' ); ?></p>
                </div>
                <div class="header-actions">
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?promptor_wizard_action=reset' ), 'promptor_reset_wizard' ) ); ?>" class="button button-secondary">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e( 'Re-run Setup Wizard', 'promptor' ); ?>
                    </a>
                </div>
            </div>
            <?php settings_errors(); ?>
                <h2 class="nav-tab-wrapper">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=promptor-settings&tab=knowledge_bases' ) ); ?>"
                    class="nav-tab <?php echo esc_attr( $active_tab === 'knowledge_bases' ? 'nav-tab-active' : '' ); ?>">
                        <?php esc_html_e( 'Knowledge Bases', 'promptor' ); ?>
                    </a>

                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=promptor-settings&tab=api_settings' ) ); ?>"
                    class="nav-tab <?php echo esc_attr( $active_tab === 'api_settings' ? 'nav-tab-active' : '' ); ?>">
                        <?php esc_html_e( 'AI Behavior', 'promptor' ); ?>
                    </a>

                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=promptor-settings&tab=ui_settings' ) ); ?>"
                    class="nav-tab <?php echo esc_attr( $active_tab === 'ui_settings' ? 'nav-tab-active' : '' ); ?>">
                        <?php esc_html_e( 'UI Settings', 'promptor' ); ?>
                    </a>

                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=promptor-settings&tab=ai_usage' ) ); ?>"
                    class="nav-tab <?php echo esc_attr( $active_tab === 'ai_usage' ? 'nav-tab-active' : '' ); ?>">
                        <?php esc_html_e( 'AI Usage', 'promptor' ); ?>
                    </a>

                    <?php if ( promptor_is_pro() ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=promptor-settings&tab=notifications' ) ); ?>"
                    class="nav-tab <?php echo esc_attr( $active_tab === 'notifications' ? 'nav-tab-active' : '' ); ?>">
                        <?php esc_html_e( 'Notifications', 'promptor' ); ?>
                    </a>
                    <?php endif; ?>

                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=promptor-settings&tab=telemetry' ) ); ?>"
                    class="nav-tab <?php echo esc_attr( $active_tab === 'telemetry' ? 'nav-tab-active' : '' ); ?>">
                        <?php esc_html_e( 'Telemetry', 'promptor' ); ?>
                    </a>
                </h2>
            <?php
                if ( isset( $this->tabs[ $active_tab ] ) && is_object( $this->tabs[ $active_tab ] ) ) {
                    $this->tabs[ $active_tab ]->render();
                } else {
                    $this->tabs['knowledge_bases']->render();
                }
            ?>
        </div>
        <?php
    }
}