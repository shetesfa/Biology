<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Promptor_Settings_Tab_Notifications {

    private $settings;
    private $email_templates = null;

    public function __construct() {
        $opts = get_option( 'promptor_notification_settings', array() );
        $this->settings = is_array( $opts ) ? $opts : array();
    }

    private function get_email_templates() {
        if ( null === $this->email_templates ) {
            $this->email_templates = $this->get_email_templates_config();
        }
        return $this->email_templates;
    }

    public function get_email_templates_config() {
        return array(
            'new_lead' => array(
                'label' => __( 'New Lead/Submission Received', 'promptor' ),
                'subject' => '[{site_name}] New Inquiry from {user_name}',
                'body' => "A new inquiry has been submitted.\n\nName: {user_name}\nEmail: {user_email}\nPhone: {user_phone}\n\nSelected Services:\n{selected_services}\n\nSelected Products:\n{selected_products}\n\nAdditional Notes:\n{user_notes}",
                'placeholders' => '{site_name}, {user_name}, {user_email}, {user_phone}, {selected_services}, {selected_products}, {user_notes}',
            ),
            'lead_converted' => array(
                'label' => __( 'Lead Status is Changed to "Converted"', 'promptor' ),
                'subject' => '[{site_name}] Lead Converted: {user_name}',
                'body' => "A lead has been successfully marked as 'Converted'.\n\nSubmitter Name: {user_name}\nSubmitter Email: {user_email}\nDetails:\n{recommendations}",
                'placeholders' => '{site_name}, {user_name}, {user_email}, {recommendations}',
            ),
            'product_added_to_cart' => array(
                'label' => __( 'Product Added to Cart via AI', 'promptor' ),
                'subject' => '[{site_name}] Product Added to Cart: {product_name}',
                'body' => "A user added a product to their cart following an AI recommendation.\n\nProduct: {product_name} ({product_price})\nUser's Original Query ID: {query_id}",
                'placeholders' => '{site_name}, {product_name}, {product_price}, {query_id}',
            ),
            'good_feedback' => array(
                'label' => __( 'Good AI Feedback Received', 'promptor' ),
                'subject' => '[{site_name}] Good AI Feedback Received',
                'body' => "A user submitted positive feedback for an AI interaction.\n\nUser's Query: {user_query}",
                'placeholders' => '{site_name}, {user_query}',
            ),
            'bad_feedback' => array(
                'label' => __( 'Bad AI Feedback Received', 'promptor' ),
                'subject' => '[{site_name}] Bad AI Feedback Received',
                'body' => "A user submitted negative feedback for an AI interaction.\n\nUser's Query: {user_query}",
                'placeholders' => '{site_name}, {user_query}',
            ),
            'query_error' => array(
                'label' => __( 'AI Query Fails (API Error)', 'promptor' ),
                'subject' => '[{site_name}] Critical: AI Query Failed',
                'body' => "An AI query failed, which may indicate a problem with the OpenAI API settings or service status.\n\nUser's Query: {user_query}\n\nError Details: {error_message}",
                'placeholders' => '{site_name}, {user_query}, {error_message}',
            ),
        );
    }

    public function register_settings() {
        register_setting(
            'promptor_notification_options_group',
            'promptor_notification_settings',
            array( $this, 'sanitize_settings' )
        );
    }

    public function render() {
        // 1) AUTH CONTROL
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'promptor' ) );
        }

        // 2) FREEMIUS SAFE CHECK
        $is_premium = ( function_exists( 'promptor_fs' ) && method_exists( promptor_fs(), 'can_use_premium_code__premium_only' ) )
            ? (bool) promptor_fs()->can_use_premium_code__premium_only()
            : false;

        if ( ! $is_premium ) {
            echo '<div class="promptor-inline-notice notice-info"><span class="dashicons dashicons-info"></span><p>';
            $upgrade_url = ( function_exists( 'promptor_fs' ) && method_exists( promptor_fs(), 'get_upgrade_url' ) )
                ? promptor_fs()->get_upgrade_url()
                : 'https://promptorai.com/pricing/';
            printf(/* translators: %s: Upgrade to Pro URL */
                wp_kses_post( __( 'Custom Email and Slack notifications are a Pro feature. <a href="%s" target="_blank"><strong>Upgrade to Pro</strong></a> to automate your lead workflow!', 'promptor' ) ),
                esc_url( $upgrade_url )
            );
            echo '</p></div>';
            return;
        }

        $allowed_sub_tabs = array( 'triggers', 'email', 'slack' );
        $active_tab       = 'triggers';

        // Since nonce verification is not performed, sub_tab should only be used for visual purposes
        // The actual form submission is already done via options.php
        if ( isset( $_GET['sub_tab'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $maybe_tab = sanitize_key( wp_unslash( $_GET['sub_tab'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ( in_array( $maybe_tab, $allowed_sub_tabs, true ) ) {
                // Distinct nonce
                $nonce = isset( $_GET['promptor_subtab_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['promptor_subtab_nonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                if ( wp_verify_nonce( $nonce, 'promptor_notifications_subtab' ) ) {
                    $active_tab = $maybe_tab;
                }
            }
        }

        $base_url = add_query_arg(
            array(
                'page'           => 'promptor-settings',
                'tab'            => 'notifications',
                'promptor_nonce' => wp_create_nonce( 'promptor_settings_tabs_action' ), // görsel amaçlı, doğrulaması yapılmıyor
            ),
            admin_url( 'admin.php' )
        );

        $triggers_url = wp_nonce_url(
            add_query_arg( 'sub_tab', 'triggers', $base_url ),
            'promptor_notifications_subtab',
            'promptor_subtab_nonce'
        );
        $email_url = wp_nonce_url(
            add_query_arg( 'sub_tab', 'email', $base_url ),
            'promptor_notifications_subtab',
            'promptor_subtab_nonce'
        );
        $slack_url = wp_nonce_url(
            add_query_arg( 'sub_tab', 'slack', $base_url ),
            'promptor_notifications_subtab',
            'promptor_subtab_nonce'
        );
        ?>
        <div class="nav-tab-wrapper promptor-sub-tabs">
            <a href="<?php echo esc_url( $triggers_url ); ?>" class="nav-tab <?php echo esc_attr( 'triggers' === $active_tab ? 'nav-tab-active' : '' ); ?>"><?php esc_html_e( 'Triggers & General', 'promptor' ); ?></a>
            <a href="<?php echo esc_url( $email_url ); ?>" class="nav-tab <?php echo esc_attr( 'email' === $active_tab ? 'nav-tab-active' : '' ); ?>"><?php esc_html_e( 'Email', 'promptor' ); ?></a>
            <a href="<?php echo esc_url( $slack_url ); ?>" class="nav-tab <?php echo esc_attr( 'slack' === $active_tab ? 'nav-tab-active' : '' ); ?>"><?php esc_html_e( 'Slack', 'promptor' ); ?></a>
        </div>

        <form method="post" action="options.php">
            <?php
            settings_fields( 'promptor_notification_options_group' );

            $referer_args = array(
                'page'           => 'promptor-settings',
                'tab'            => 'notifications',
                'sub_tab'        => $active_tab,
                'promptor_nonce' => wp_create_nonce( 'promptor_settings_tabs_action' ),
            );
            $referer_url = wp_nonce_url(
                add_query_arg( $referer_args, admin_url( 'admin.php' ) ),
                'promptor_notifications_subtab',
                'promptor_subtab_nonce'
            );
            ?>
            <input type="hidden" name="_wp_http_referer" value="<?php echo esc_url( $referer_url ); ?>" />

            <div id="triggers-content" class="promptor-tab-pane" style="<?php echo esc_attr( 'triggers' === $active_tab ? 'display:block;' : 'display:none;' ); ?>">
                <?php $this->render_triggers_tab(); ?>
            </div>
            <div id="email-content" class="promptor-tab-pane" style="<?php echo esc_attr( 'email' === $active_tab ? 'display:block;' : 'display:none;' ); ?>">
                <?php $this->render_email_tab(); ?>
            </div>
            <div id="slack-content" class="promptor-tab-pane" style="<?php echo esc_attr( 'slack' === $active_tab ? 'display:block;' : 'display:none;' ); ?>">
                <?php $this->render_slack_tab(); ?>
            </div>

            <?php submit_button(); ?>
        </form>
        <?php
    }

    private function render_triggers_tab() {
        ?>
        <div class="promptor-grid-2-col">
            <div class="postbox">
                <h2 class="hndle"><span class="dashicons dashicons-bell promptor-hndle-icon"></span><span><?php esc_html_e( 'Notification Triggers', 'promptor' ); ?></span></h2>
                <div class="inside">
                    <p><?php esc_html_e( 'Choose which events will trigger notifications on which channels.', 'promptor' ); ?></p>
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Event', 'promptor' ); ?></th>
                                <th class="promptor-col-action"><?php esc_html_e( 'Email', 'promptor' ); ?></th>
                                <th class="promptor-col-action"><?php esc_html_e( 'Slack', 'promptor' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        foreach ( $this->get_email_templates() as $event_key => $event_details ) {
                            echo '<tr><td>' . esc_html( $event_details['label'] ) . '</td>';
                            foreach ( array( 'email', 'slack' ) as $channel ) {
                                $checked = ! empty( $this->settings['triggers'][ $event_key ][ $channel ] );
                                echo '<td style="text-align:center;">';
                                echo '<input type="checkbox" name="promptor_notification_settings[triggers][' . esc_attr( $event_key ) . '][' . esc_attr( $channel ) . ']" value="1" ' . checked( $checked, true, false ) . ' />';
                                echo '</td>';
                            }
                            echo '</tr>';
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="postbox">
                <h2 class="hndle"><span class="dashicons dashicons-megaphone promptor-hndle-icon"></span><span><?php esc_html_e( 'Admin Area Notifications', 'promptor' ); ?></span></h2>
                <div class="inside">
                    <p><?php esc_html_e( 'Configure notifications that appear within the WordPress admin area', 'promptor' ); ?></p>
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th style="width:80%;"><?php esc_html_e( 'Setting', 'promptor' ); ?></th>
                                <th class="promptor-table-center"><?php esc_html_e( 'Enabled', 'promptor' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php esc_html_e( 'Show new lead counter in Admin Bar', 'promptor' ); ?></td>
                                <td class="promptor-table-center">
                                    <input type="checkbox"
                                           name="promptor_notification_settings[general][admin_bar_counter]"
                                           value="1"
                                           <?php checked( ! empty( $this->settings['general']['admin_bar_counter'] ) ); ?> />
                                </td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e( 'Play sound for new submissions', 'promptor' ); ?></td>
                                <td class="promptor-table-center">
                                    <input type="checkbox"
                                           name="promptor_notification_settings[general][notification_sound]"
                                           value="1"
                                           <?php checked( ! empty( $this->settings['general']['notification_sound'] ) ); ?> />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_email_tab() {
        ?>
        <div class="postbox">
            <h2 class="hndle"><span class="dashicons dashicons-email promptor-hndle-icon"></span><span><?php esc_html_e( 'Email Sender Settings', 'promptor' ); ?></span></h2>
            <div class="inside">
                <p><?php esc_html_e( 'Configure the "From" name and address for all notification emails sent from Promptor.', 'promptor' ); ?></p>
                <table class="form-table">
                    <?php
                    $host       = wp_parse_url( home_url(), PHP_URL_HOST );
                    $host_clean = preg_replace( '#^www\.#', '', strtolower( (string) $host ) );

                    $this->render_field(
                        'email',
                        'recipients',
                        __( 'Recipient(s)', 'promptor' ),
                        'text',
                        array(
                            'default' => get_option( 'admin_email' ),
                            'desc'    => __( 'Send notifications to these email addresses. Separate multiple emails with a comma.', 'promptor' ),
                        )
                    );
                    $this->render_field(
                        'email',
                        'from_name',
                        __( '"From" Name', 'promptor' ),
                        'text',
                        array(
                            'default' => get_bloginfo( 'name' ),
                            'desc'    => __( 'The name that appears as the sender.', 'promptor' ),
                        )
                    );
                    $this->render_field(
                        'email',
                        'from_email',
                        __( '"From" Email', 'promptor' ),
                        'text',
                        array(
                            'default' => 'wordpress@' . $host_clean,
                            'desc'    => __( 'The email address that appears as the sender.', 'promptor' ),
                        )
                    );
                    ?>
                </table>
                <p>
                    <button type="button" class="button button-secondary" id="promptor-send-test-email"><?php esc_html_e( 'Send Test Email', 'promptor' ); ?></button>
                    <span class="spinner"></span>
                    <span id="test-email-status" class="promptor-test-status" style="margin-left: 10px;"></span>
                </p>
            </div>
        </div>

        <div class="postbox">
            <h2 class="hndle"><span class="dashicons dashicons-media-document promptor-hndle-icon"></span><span><?php esc_html_e( 'Email Templates', 'promptor' ); ?></span></h2>
            <div class="inside">
                <p><?php esc_html_e( 'Customize the subject and content for each notification email. Click an event from the list to begin editing.', 'promptor' ); ?></p>
                <div class="promptor-email-templates-layout">
                    <ul class="promptor-email-templates-list">
                        <?php
                        $first = true;
                        foreach ( $this->get_email_templates() as $key => $template ) :
                            ?>
                            <li>
                                <a href="#template-<?php echo esc_attr( $key ); ?>" class="<?php echo esc_attr( $first ? 'active' : '' ); ?>">
                                    <?php echo esc_html( $template['label'] ); ?>
                                </a>
                            </li>
                            <?php
                            $first = false;
                        endforeach;
                        ?>
                    </ul>
                    <div class="promptor-email-template-editor">
                        <?php
                        $first = true;
                        foreach ( $this->get_email_templates() as $key => $template ) :
                            $subject_value = $this->settings['email']['templates'][ $key ]['subject'] ?? $template['subject'];
                            $body_value    = $this->settings['email']['templates'][ $key ]['body'] ?? $template['body'];
                            ?>
                            <div id="template-<?php echo esc_attr( $key ); ?>" class="template-editor-pane" style="<?php echo esc_attr( $first ? '' : 'display:none;' ); ?>">
                                <?php $first = false; ?>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">
                                            <label for="email_subject_<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Subject', 'promptor' ); ?></label>
                                        </th>
                                        <td>
                                            <input type="text"
                                                   id="email_subject_<?php echo esc_attr( $key ); ?>"
                                                   name="promptor_notification_settings[email][templates][<?php echo esc_attr( $key ); ?>][subject]"
                                                   value="<?php echo esc_attr( $subject_value ); ?>"
                                                   class="large-text" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row" style="vertical-align: top;">
                                            <label for="email_body_<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Message Body', 'promptor' ); ?></label>
                                        </th>
                                        <td>
                                            <?php
                                            wp_editor(
                                                $body_value,
                                                'email_body_' . esc_attr( $key ),
                                                array(
                                                    'textarea_name' => 'promptor_notification_settings[email][templates][' . esc_attr( $key ) . '][body]',
                                                    'media_buttons' => false,
                                                    'textarea_rows' => 18,
                                                    'teeny'         => true,
                                                )
                                            );
                                            ?>
                                            <p class="description">
                                                <strong><?php esc_html_e( 'Available shortcodes:', 'promptor' ); ?></strong>
                                                <code class="promptor-shortcode-list"><?php echo esc_html( $template['placeholders'] ); ?></code>
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_slack_tab() {
        ?>
        <div class="postbox">
            <h2 class="hndle"><span class="dashicons dashicons-share promptor-hndle-icon"></span><span><?php esc_html_e( 'Slack Configuration', 'promptor' ); ?></span></h2>
            <div class="inside">
                <p>
                    <?php
                    printf(/* translators: %s: Slack help URL */
                        wp_kses_post( __( 'To get your Webhook URL, follow the instructions <a href="%s" target="_blank">here</a>.', 'promptor' ) ),
                        esc_url( 'https://slack.com/help/articles/115005265063-Incoming-webhooks-for-Slack' )
                    );
                    ?>
                </p>
                <table class="form-table">
                    <?php $this->render_field( 'slack', 'enabled', __( 'Enable Slack Notifications', 'promptor' ), 'checkbox' ); ?>
                    <?php $this->render_field( 'slack', 'webhook_url', __( 'Webhook URL', 'promptor' ), 'text', array( 'desc' => __( 'Enter your Slack Incoming Webhook URL.', 'promptor' ), 'placeholder' => 'https://hooks.slack.com/services/...' ) ); ?>
                    <?php $this->render_field( 'slack', 'channel', __( 'Channel (Optional)', 'promptor' ), 'text', array( 'placeholder' => '#general', 'desc' => __( 'The channel to post to. If empty, the webhook\'s default channel will be used.', 'promptor' ) ) ); ?>
                    <?php $this->render_field( 'slack', 'bot_name', __( 'Bot Name (Optional)', 'promptor' ), 'text', array( 'placeholder' => 'Promptor Bot', 'desc' => __( 'The name your bot will post as.', 'promptor' ) ) ); ?>
                    <?php $this->render_field( 'slack', 'new_lead_template', __( 'New Lead Message Template', 'promptor' ), 'textarea', array( 'default' => "🔔 *New Lead on {site_name}*\n> *Name:* {user_name}\n> *Email:* {user_email}\n> *Phone:* {user_phone}\n> *Details:* See submission in WordPress.", 'desc' => __( 'You can use Slack\'s `mrkdwn` formatting.', 'promptor' ) ) ); ?>
                </table>
                <p>
                    <button type="button" class="button button-secondary" id="promptor-send-test-slack"><?php esc_html_e( 'Send Test Slack Notification', 'promptor' ); ?></button>
                    <span class="spinner"></span>
                    <span id="test-slack-status" class="promptor-test-status" style="margin-left: 10px;"></span>
                </p>
            </div>
        </div>
        <?php
    }

    private function render_field( $group, $id, $title, $type, $args = array() ) {
        $option_name = "promptor_notification_settings[{$group}][{$id}]";
        $value       = $this->settings[ $group ][ $id ] ?? ( $args['default'] ?? '' );
        $placeholder = $args['placeholder'] ?? '';

        echo '<tr>';
        echo '<th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $title ) . '</label></th>';
        echo '<td>';

        switch ( $type ) {
            case 'text':
                echo '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $option_name ) . '" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="' . esc_attr( $placeholder ) . '" />';
                break;

            case 'textarea':
                echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $option_name ) . '" rows="7" class="large-text">' . esc_textarea( $value ) . '</textarea>';
                break;

            case 'checkbox':
                $checked_val = isset( $this->settings[ $group ][ $id ] ) ? $this->settings[ $group ][ $id ] : ( $args['default'] ?? 0 );
                echo '<label><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $option_name ) . '" value="1" ' . checked( (int) $checked_val, 1, false ) . ' /> ' . '</label>';
                break;
        }

        if ( ! empty( $args['desc'] ) ) {
            echo '<p class="description">' . wp_kses_post( $args['desc'] ) . '</p>';
        }

        echo '</td></tr>';
    }

    public function sanitize_settings( $input ) {
        $existing  = get_option( 'promptor_notification_settings', array() );
        $new_input = is_array( $existing ) ? $existing : array();

        // Triggers
        if ( isset( $input['triggers'] ) ) {
            $all_triggers = array_keys( $this->get_email_templates() );
            foreach ( $all_triggers as $trigger ) {
                $new_input['triggers'][ $trigger ]['email'] = ! empty( $input['triggers'][ $trigger ]['email'] ) ? 1 : 0;
                $new_input['triggers'][ $trigger ]['slack'] = ! empty( $input['triggers'][ $trigger ]['slack'] ) ? 1 : 0;
            }
        }

        // Admin general
        if ( isset( $input['general'] ) ) {
            $new_input['general']['admin_bar_counter']  = ! empty( $input['general']['admin_bar_counter'] ) ? 1 : 0;
            $new_input['general']['notification_sound'] = ! empty( $input['general']['notification_sound'] ) ? 1 : 0;
        }

        // Email
        if ( isset( $input['email'] ) ) {
            $raw_recipients = isset( $input['email']['recipients'] ) ? wp_unslash( $input['email']['recipients'] ) : '';

            if ( is_array( $raw_recipients ) ) {
                $to_list = array_values( array_filter( array_map( 'sanitize_email', $raw_recipients ) ) );
                $new_input['email']['recipients'] = implode( ',', $to_list );
            } else {
                $parts   = array_map( 'trim', explode( ',', (string) $raw_recipients ) );
                $to_list = array_values( array_filter( array_map( 'sanitize_email', $parts ) ) );
                $new_input['email']['recipients'] = implode( ',', $to_list );
            }

            $new_input['email']['from_name']  = sanitize_text_field( wp_unslash( $input['email']['from_name'] ?? '' ) );
            $new_input['email']['from_email'] = sanitize_email( wp_unslash( $input['email']['from_email'] ?? '' ) );

            // Email templates
            $new_input['email']['templates'] = $new_input['email']['templates'] ?? array();
            if ( isset( $input['email']['templates'] ) && is_array( $input['email']['templates'] ) ) {
                foreach ( $input['email']['templates'] as $key => $template ) {
                    if ( array_key_exists( $key, $this->get_email_templates() ) ) {
                        $new_input['email']['templates'][ $key ]['subject'] = sanitize_text_field( wp_unslash( $template['subject'] ?? '' ) );
                        $new_input['email']['templates'][ $key ]['body']    = wp_kses_post( wp_unslash( $template['body'] ?? '' ) );
                    }
                }
            }
        }

        // Slack
        if ( isset( $input['slack'] ) ) {
            $new_input['slack']['enabled'] = ! empty( $input['slack']['enabled'] ) ? 1 : 0;

            $raw_webhook = wp_unslash( $input['slack']['webhook_url'] ?? '' );
            $webhook     = esc_url_raw( $raw_webhook );
            if ( ! empty( $webhook ) && 0 === strpos( $webhook, 'https://hooks.slack.com/services/' ) ) {
                $new_input['slack']['webhook_url'] = $webhook;
            } else {
                $new_input['slack']['webhook_url'] = '';
            }

            $new_input['slack']['channel']           = sanitize_text_field( wp_unslash( $input['slack']['channel'] ?? '' ) );
            $new_input['slack']['bot_name']          = sanitize_text_field( wp_unslash( $input['slack']['bot_name'] ?? '' ) );
            $new_input['slack']['new_lead_template'] = wp_kses_post( wp_unslash( $input['slack']['new_lead_template'] ?? '' ) );
        }

        return $new_input;
    }
}