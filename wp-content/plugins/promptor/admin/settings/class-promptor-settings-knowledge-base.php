<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Promptor_Settings_Tab_Knowledge_Base {
    
    private $contexts;

    public function __construct(){
        $this->contexts = get_option('promptor_contexts', array());
         if (empty($this->contexts)) {
             $this->contexts['default'] = array('name' => 'Default', 'source_type' => 'manual', 'settings' => array('content_roles' => array(), 'example_questions' => ''));
             update_option('promptor_contexts', $this->contexts);
        }
    }

    public function register_settings() {
        register_setting('promptor_crawler_settings_group', 'promptor_crawler_settings', array($this, 'sanitize_crawler_settings'));
        add_settings_section('promptor_crawler_section_main', '', null, 'promptor-crawler-settings-page');
        add_settings_field('crawl_depth', '', '__return_null', 'promptor-crawler-settings-page', 'promptor_crawler_section_main');
        add_settings_field('max_pages', '', '__return_null', 'promptor-crawler-settings-page', 'promptor_crawler_section_main');
        add_settings_field('content_selector', '', '__return_null', 'promptor-crawler-settings-page', 'promptor_crawler_section_main');
        add_settings_field('allowed_patterns', '', '__return_null', 'promptor-crawler-settings-page', 'promptor_crawler_section_main');
        add_settings_field('disallowed_patterns', '', '__return_null', 'promptor-crawler-settings-page', 'promptor_crawler_section_main');
        add_settings_field('crawl_schedule', '', '__return_null', 'promptor-crawler-settings-page', 'promptor_crawler_section_main');
    }
    
    public function render() {
        // YETKİ: sadece yöneticiler erişebilsin
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'promptor' ) );
        }

        $contexts         = get_option( 'promptor_contexts', array() );
        $allowed_contexts = is_array( $contexts ) ? array_keys( $contexts ) : array( 'default' );
        $active_context   = 'default';

        if ( isset( $_GET['context'] ) ) {
            $maybe_ctx = sanitize_key( wp_unslash( $_GET['context'] ) );
            $nonce     = isset( $_GET['promptor_kb_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['promptor_kb_nonce'] ) ) : '';

            if ( in_array( $maybe_ctx, $allowed_contexts, true ) && wp_verify_nonce( $nonce, 'promptor_kb_context_action' ) ) {
                $active_context = $maybe_ctx;
            } else {
                $active_context = 'default';
            }
        }

    $context = isset($_GET['context']) ? sanitize_key( wp_unslash( $_GET['context'] ) ) : '';
    $tab     = isset($_GET['tab']) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

    if (!empty($context) && $tab === 'content_selection') {
        $context_nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
        if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( $context_nonce, 'promptor_manage_content_' . $context ) ) {
            wp_die( esc_html__( 'Security check failed.', 'promptor' ) );
        }
        $this->render_content_selection_page($context);
    } else {
        $this->render_main_page();
    }
}

    public function sanitize_crawler_settings($input) {
        $in = is_array($input) ? wp_unslash($input) : array();

        $out = array();
        $out['crawl_depth']         = isset($in['crawl_depth'])        ? absint($in['crawl_depth'])        : 3;
        $out['max_pages']           = isset($in['max_pages'])          ? absint($in['max_pages'])          : 100;
        $out['content_selector']    = isset($in['content_selector'])   ? sanitize_text_field($in['content_selector']) : '';
        $allowed_raw = isset($in['allowed_patterns']) ? sanitize_textarea_field($in['allowed_patterns']) : '';
        $dis_raw     = isset($in['disallowed_patterns']) ? sanitize_textarea_field($in['disallowed_patterns']) : '';

        $normalize_lines = static function($txt) {
            $txt = str_replace(array("\r\n", "\r"), "\n", $txt);
            $lines = array_filter(array_map('trim', explode("\n", $txt)));
            return implode("\n", $lines);
        };

        $out['allowed_patterns']    = $normalize_lines($allowed_raw);
        $out['disallowed_patterns'] = $normalize_lines($dis_raw);
        $out['crawl_schedule']      = isset($in['crawl_schedule'])     ? sanitize_key($in['crawl_schedule']) : 'never';

        return $out;
    }

    
    private function render_main_page() {
        global $wpdb;
        $active_sub_tab = isset($_GET['sub_tab']) ? sanitize_key( wp_unslash( $_GET['sub_tab'] ) ) : 'manage'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        // Freemius güvenli premium tespiti
        $is_premium = ( function_exists( 'promptor_fs' ) && method_exists( promptor_fs(), 'can_use_premium_code__premium_only' ) )
            ? (bool) promptor_fs()->can_use_premium_code__premium_only()
            : false;

        // Load contexts for manage tab
        $contexts = get_option( 'promptor_contexts', array() );


        ?>
        <?php
        $base   = admin_url('admin.php');
        $m_url  = add_query_arg(array('page'=>'promptor-settings','tab'=>'knowledge_bases','sub_tab'=>'manage'),  $base);
        $c_url  = add_query_arg(array('page'=>'promptor-settings','tab'=>'knowledge_bases','sub_tab'=>'crawler'), $base);
        ?>
        <div class="nav-tab-wrapper promptor-sub-tabs">
            <a href="<?php echo esc_url( $m_url ); ?>" class="nav-tab <?php echo esc_attr( $active_sub_tab === 'manage' ? 'nav-tab-active' : '' ); ?>">
                <?php esc_html_e( 'Manage', 'promptor' ); ?>
            </a>
            <?php if ( $is_premium ) : ?>
                <a href="<?php echo esc_url( $c_url ); ?>" class="nav-tab <?php echo esc_attr( $active_sub_tab === 'crawler' ? 'nav-tab-active' : '' ); ?>">
                    <?php esc_html_e( 'Crawler', 'promptor' ); ?>
                </a>
            <?php endif; ?>
        </div>


        
        <?php $this->maybe_render_trial_kb_notice(); ?>

        <div id="manage-kb-content" class="promptor-tab-pane" style="<?php echo esc_attr( $active_sub_tab === 'manage' ? 'display:block;' : 'display:none;' ); ?>">
             <div class="promptor-knowledge-base-manager">
                <div class="postbox">
                    <h2 class="hndle">
                        <span class="dashicons dashicons-book-alt promptor-hndle-icon"></span>
                        <span><?php esc_html_e('Manage Existing Knowledge Bases', 'promptor'); ?></span>
                    </h2>
                    <div class="inside">
                    <p><?php esc_html_e('Manage all your created knowledge bases from here. You can edit their content, synchronize them, clear the index, and view their shortcodes.', 'promptor'); ?></p>
                        <table class="wp-list-table widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Name', 'promptor'); ?></th>
                                    <th><?php esc_html_e('Shortcode', 'promptor'); ?></th>
                                    <th><?php esc_html_e('Content Source', 'promptor'); ?></th>
                                    <th><?php esc_html_e('Indexed Docs / Chunks', 'promptor'); ?></th>
                                    <th style="min-width: 380px;"><?php esc_html_e('Actions', 'promptor'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="the-list">
                                <?php 
                                if (!empty($this->contexts) && is_array($this->contexts)): 
                                    foreach ($this->contexts as $key => $context):
                                        if (!is_array($context)) continue;
                                        $source_type = $context['source_type'] ?? 'manual';/* translators: %s: Key */
                                        $name = $context['name'] ?? sprintf( __( 'Corrupted (Key: %s)', 'promptor' ), $key );

                                        // FREE: Don't show context parameter for default KB (users should use simple shortcode)
                                        $is_pro = function_exists( 'promptor_fs' ) && promptor_fs()->can_use_premium_code();
                                        if ( ! $is_pro && 'default' === $key ) {
                                            $shortcode = "[promptor_search]";
                                        } else {
                                            $shortcode = "[promptor_search context=\"{$key}\"]";
                                        }

                                      $cache_key = 'kb_stats_' . md5( $wpdb->prefix . '|' . $key );
                                        $stats     = wp_cache_get( $cache_key, 'promptor' );

                                        if ( false === $stats ) {
                                            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                                           $doc_count = (int) $wpdb->get_var(
                                                 $wpdb->prepare(
                                                     "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->prefix}promptor_embeddings WHERE context_name = %s",
                                                     $key
                                                 )
                                             );
                                            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                                            $chunk_count = (int) $wpdb->get_var(
                                                 $wpdb->prepare(
                                                     "SELECT COUNT(*) FROM {$wpdb->prefix}promptor_embeddings WHERE context_name = %s",
                                                     $key
                                                 )
                                             );

                                            $stats = array( 'docs' => $doc_count, 'chunks' => $chunk_count );
                                            wp_cache_set( $cache_key, $stats, 'promptor', 5 * MINUTE_IN_SECONDS );
                                        } else {
                                            $doc_count   = (int) $stats['docs'];
                                            $chunk_count = (int) $stats['chunks'];
                                        }


                                ?>
                                <tr data-context-row="<?php echo esc_attr($key); ?>">
                                    <td>
                                        <label><input type="radio" name="crawl_context" value="<?php echo esc_attr($key); ?>" <?php checked($key, 'default'); ?>>
                                        <strong><?php echo esc_html($name); ?></strong><?php if($key === 'default') echo " (" . esc_html__('Default', 'promptor') . ")"; ?></label>
                                    </td>
                                    <td>
                                        <input type="text" value="<?php echo esc_attr($shortcode); ?>" readonly onfocus="this.select();" class="regular-text" style="width: 100%; font-family: monospace;">
                                    </td>
                                    <td class="content-source-cell">
                                        <?php if ($source_type === 'crawler'): ?>
                                            <span class="dashicons dashicons-admin-site-alt3"></span> <?php esc_html_e('Crawler', 'promptor'); ?>
                                        <?php else: ?>
                                            <span class="dashicons dashicons-edit"></span> <?php esc_html_e('Manual', 'promptor'); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td id="stats-<?php echo esc_attr($key); ?>">
                                        <?php echo esc_html(number_format_i18n($doc_count)); ?> / <?php echo esc_html(number_format_i18n($chunk_count)); ?>
                                    </td>
                                    <td class="promptor-kb-actions-cell">
                                        <?php if ($source_type === 'manual'): ?>
                                            <?php
											$manage_url_args = array(
												'page'    => 'promptor-settings',
												'tab'     => 'content_selection',
												'context' => $key
											);
											$manage_url = wp_nonce_url(add_query_arg($manage_url_args, admin_url('admin.php')), 'promptor_manage_content_' . $key);
											?>
											<a href="<?php echo esc_url( $manage_url ); ?>" class="button" title="<?php esc_attr_e('Select and manage content items for this knowledge base.', 'promptor'); ?>">
												<span class="dashicons dashicons-edit" style="vertical-align: middle; font-size: 16px; line-height: inherit;"></span>
												<?php esc_html_e('Manage', 'promptor'); ?>
											</a>

                                        <?php else: ?>
                                            <button class="button" disabled title="<?php esc_attr_e('Content for this knowledge base is managed by the crawler.', 'promptor'); ?>">
												<span class="dashicons dashicons-edit" style="vertical-align: middle; font-size: 16px; line-height: inherit;"></span>
												<?php esc_html_e('Manage', 'promptor'); ?>
											</button>
                                        <?php endif; ?>
                                        <button class="button button-secondary promptor-sync-btn"
                                                data-context="<?php echo esc_attr($key); ?>"
                                                data-nonce="<?php echo esc_attr( wp_create_nonce( 'promptor_sync_kb_' . $key ) ); ?>"
                                                title="<?php esc_attr_e('Re-index all selected content for this knowledge base.', 'promptor'); ?>">
                                    <span class="dashicons dashicons-update" style="vertical-align: middle; font-size: 16px; line-height: inherit;"></span>
                                    <?php esc_html_e('Sync', 'promptor'); ?>
                                </button>

                                <button class="button button-link-delete promptor-clear-btn"
                                        data-context="<?php echo esc_attr($key); ?>"
                                        data-nonce="<?php echo esc_attr( wp_create_nonce( 'promptor_clear_index' ) ); ?>"
                                        title="<?php esc_attr_e('Remove all indexed data for this knowledge base.', 'promptor'); ?>">
                                    <?php esc_html_e('Clear Index', 'promptor'); ?>
                                </button>

                                <?php if ($key !== 'default'): ?>
                                    <button type="button"
                                            class="button button-link-delete promptor-delete-context-btn"
                                            data-context="<?php echo esc_attr($key); ?>"
                                            data-nonce="<?php echo esc_attr( wp_create_nonce( 'promptor_delete_context' ) ); ?>"
                                            title="<?php esc_attr_e('Permanently delete this knowledge base and all its data.', 'promptor'); ?>">
                                        <?php esc_html_e('Delete', 'promptor'); ?>
                                    </button>
                                <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr class="no-items"><td class="colspanchange" colspan="5"><?php esc_html_e('No knowledge bases found. Create one to get started.', 'promptor'); ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php
                // Free version: limit to 1 knowledge base
                $kb_count = count( $contexts );
                $can_create_kb = $is_premium || $kb_count < 1;
                ?>

                <?php if ( $can_create_kb ) : ?>
                <div class="postbox"><h2 class="hndle"><span class="dashicons dashicons-plus-alt promptor-hndle-icon"></span><span><?php esc_html_e('Create New Knowledge Base', 'promptor'); ?></span></h2><div class="inside"><p><?php esc_html_e('Create a new, independent knowledge base for different purposes (e.g., sales, support). Use a short, lowercase, and space-free key.', 'promptor'); ?></p> <p><label for="new_context_name"><strong><?php esc_html_e('Short Name (Key):', 'promptor'); ?></strong></label><br><input type="text" id="new_context_name" name="new_context_name" placeholder="<?php esc_attr_e('e.g., support, sales', 'promptor'); ?>" required class="regular-text"><p class="description"><?php esc_html_e('Use a short, lowercase name with no spaces.', 'promptor'); ?></p></p><button type="button" id="add_new_context_btn" class="button button-primary"
        data-nonce="<?php echo esc_attr( wp_create_nonce( 'promptor_add_context' ) ); ?>">
    <?php esc_html_e('Create', 'promptor'); ?>
</button>
<span class="spinner" style="float: none; vertical-align: middle; visibility: hidden;"></span></div></div>
                <?php else : ?>
                <div class="postbox">
                    <h2 class="hndle"><span class="dashicons dashicons-plus-alt promptor-hndle-icon"></span><span><?php esc_html_e('Create New Knowledge Base', 'promptor'); ?></span></h2>
                    <div class="inside">
                        <div class="promptor-inline-notice notice-warning">
                            <p>
                                <?php
                                $upgrade_url = ( function_exists( 'promptor_fs' ) && method_exists( promptor_fs(), 'get_upgrade_url' ) )
                                    ? promptor_fs()->get_upgrade_url()
                                    : 'https://promptorai.com/pricing/';
                                echo wp_kses_post(
                                    sprintf(
                                        /* translators: %s: Upgrade to Pro URL */
                                        __( 'You are using Promptor Lite and have reached the limit of 1 knowledge base. <a href="%s" target="_blank" rel="noopener noreferrer"><strong>Upgrade to Pro</strong></a> to create unlimited knowledge bases!', 'promptor' ),
                                        esc_url( $upgrade_url )
                                    )
                                );
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ( $is_premium ) : ?>
        <div id="crawler-settings-content" class="promptor-tab-pane" style="<?php echo esc_attr( $active_sub_tab === 'crawler' ? 'display:block;' : 'display:none;' ); ?>">
            <div class="postbox">
                <h2 class="hndle"><span class="dashicons dashicons-admin-site-alt3 promptor-hndle-icon"></span><span><?php esc_html_e('Crawler Initiation', 'promptor'); ?></span></h2>
                <div class="inside">
                    <p><?php esc_html_e('Select a knowledge base from the "Manage" tab, then provide your sitemap URL and start crawling.', 'promptor'); ?></p>
                    <table class="form-table">
                         <tr>
                            <th scope="row"><label for="sitemap_url"><?php esc_html_e('Sitemap URL', 'promptor'); ?></label></th>
                            <td>
                                <input type="text" id="sitemap_url" value="<?php echo esc_url(home_url('/sitemap.xml')); ?>" class="large-text">
                                <p class="description"><?php esc_html_e('Enter the full URL of your sitemap. The crawler will find all pages from here.', 'promptor'); ?></p>
                            </td>
                        </tr>
                    </table>
                     <p class="submit">
                        <button id="promptor-start-crawl-btn" class="button button-primary"
        data-nonce="<?php echo esc_attr( wp_create_nonce( 'promptor_start_crawl' ) ); ?>"
        style="font-size: 16px; padding: 8px 20px;">
    <?php esc_html_e('Start Crawling and Indexing', 'promptor'); ?>
</button>

                        <span class="spinner" style="float: none; vertical-align: middle; visibility: hidden;"></span>
                    </p>
                </div>
            </div>
             <form method="post" action="options.php">
                <?php settings_fields('promptor_crawler_settings_group'); ?>
                <div class="postbox">
                    <h2 class="hndle"><span class="dashicons dashicons-admin-generic promptor-hndle-icon"></span><span><?php esc_html_e('Crawler Configuration', 'promptor'); ?></span></h2>
                    <div class="inside">
                    <p><?php esc_html_e('Configure how the website crawler will index your site. Set crawling limits, define content areas, and schedule automatic updates.', 'promptor'); ?></p> 
                        <div class="promptor-grid-2-col">
                            <div class="promptor-col-1">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><label for="crawl_depth"><?php esc_html_e('Crawl Depth', 'promptor'); ?></label></th>
                                        <td><?php $this->render_text_field(array('option_name' => 'promptor_crawler_settings', 'field_id' => 'crawl_depth', 'default' => '3', 'type' => 'number', 'desc' => __('How many levels deep the crawler should follow links from the start URL.', 'promptor'))); ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="max_pages"><?php esc_html_e('Max Pages to Crawl', 'promptor'); ?></label></th>
                                        <td><?php $this->render_text_field(array('option_name' => 'promptor_crawler_settings', 'field_id' => 'max_pages', 'default' => '100', 'type' => 'number', 'desc' => __('The maximum number of pages to crawl in a single operation.', 'promptor'))); ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="crawl_schedule"><?php esc_html_e('Crawl Schedule', 'promptor'); ?></label></th>
                                        <td><?php $this->render_select_field(array(
                                            'option_name' => 'promptor_crawler_settings', 'field_id' => 'crawl_schedule', 
                                            'options' => array('never' => __('Never (Manual Only)', 'promptor'), 'hourly' => __('Hourly', 'promptor'), 'twicedaily' => __('Twice Daily', 'promptor'), 'daily' => __('Daily', 'promptor'), 'weekly' => __('Weekly', 'promptor')),
                                            'desc' => __('How often the crawler should automatically run.', 'promptor')
                                        )); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="promptor-col-2">
                                <table class="form-table">
                                     <tr>
                                        <th scope="row"><label for="content_selector"><?php esc_html_e('Content CSS Selector', 'promptor'); ?></label></th>
                                        <td><?php $this->render_text_field(array('option_name' => 'promptor_crawler_settings', 'field_id' => 'content_selector', 'default' => '.post-content', 'desc' => __('e.g., #main-article or .entry-content. If empty, it will try to find the main content automatically.', 'promptor'))); ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="allowed_patterns"><?php esc_html_e('Allowed URL Patterns', 'promptor'); ?></label></th>
                                        <td><?php $this->render_textarea_field(array('option_name' => 'promptor_crawler_settings', 'field_id' => 'allowed_patterns', 'default' => "/blog/.*\n/products/.*", 'desc' => __('Only URLs matching these patterns will be crawled. One per line.', 'promptor'))); ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="disallowed_patterns"><?php esc_html_e('Disallowed URL Patterns', 'promptor'); ?></label></th>
                                        <td><?php $this->render_textarea_field(array('option_name' => 'promptor_crawler_settings', 'field_id' => 'disallowed_patterns', 'default' => "/cart/\n/my-account/\n/checkout/", 'desc' => __('URLs matching these patterns will be excluded. One per line.', 'promptor'))); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php submit_button(); ?>
             </form>
        </div>
        <?php endif; ?>
        
        <div class="postbox" style="margin-top: 20px;">
            <h2 class="hndle"><span class="dashicons dashicons-media-text promptor-hndle-icon"></span><span><?php esc_html_e('Log', 'promptor'); ?></span></h2>
            <div class="inside">
            <p><?php esc_html_e('Follow the real-time status and results of content synchronization or website crawling processes from this log area.', 'promptor'); ?></p> 
                <pre id="promptor-indexing-log"><?php esc_html_e('Ready. Click a "Sync" or "Crawl" button to start...', 'promptor'); ?></pre>
            </div>
        </div>
        <?php
    }

    public function render_content_selection_page( $context_key ) {
        // Access Control
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'promptor' ) );
        }

        $context_data = $this->contexts[ $context_key ] ?? null;
        if ( ! $context_data ) { echo '<div class="notice notice-error"><p>' . esc_html__( 'Knowledge base not found.', 'promptor' ) . '</p></div>'; return; }

        // Secure Premium
        $is_premium = ( function_exists( 'promptor_fs' ) && method_exists( promptor_fs(), 'can_use_premium_code__premium_only' ) )
            ? (bool) promptor_fs()->can_use_premium_code__premium_only()
            : false;
        $is_lite = ! $is_premium;
        $limit   = $is_lite ? 3 : -1;


        $content_roles = $context_data['settings']['content_roles'] ?? array();
        $selected_post_ids = array_keys($content_roles);
        $example_questions = $context_data['settings']['example_questions'] ?? '';
        ?>
        <h2><?php /* translators: %s: Knowledge base name */ printf(esc_html__('Managing Content for "%s" Knowledge Base', 'promptor'), esc_html($context_data['name'])); ?></h2>
        <p><a href="<?php echo esc_url(admin_url('admin.php?page=promptor-settings&tab=knowledge_bases')); ?>">&larr; <?php esc_html_e('Back to all Knowledge Bases', 'promptor'); ?></a></p>
        
    <div id="promptor-content-selection-form" 
        data-context="<?php echo esc_attr( $context_key ); ?>" 
        data-is-lite="<?php echo esc_attr( (int) $is_lite ); ?>" 
        data-limit="<?php echo esc_attr( $limit ); ?>"
        data-save-nonce="<?php echo esc_attr( wp_create_nonce( 'promptor_save_content_selection' ) ); ?>"
        data-bulk-nonce="<?php echo esc_attr( wp_create_nonce( 'promptor_bulk_role_change' ) ); ?>">

<?php if ( $is_lite ) : ?>
    <div class="notice notice-info">
        <p>
        <?php
            $upgrade_url = ( function_exists( 'promptor_fs' ) && method_exists( promptor_fs(), 'get_upgrade_url' ) )
                ? promptor_fs()->get_upgrade_url()
                : 'https://promptorai.com/pricing/';

            echo wp_kses_post(
                sprintf(
                    /* translators: 1: number of items, 2: Upgrade to Pro URL */
                    __( 'You are using Promptor Lite. You can select a maximum of %1$d content items (pages, posts, or files) in total. <a href="%2$s" target="_blank"><strong>Upgrade to Pro</strong></a> for unlimited content.', 'promptor' ),
                    (int) $limit,
                    esc_url( $upgrade_url )
                )
            );
        ?>
        </p>
    </div>
<?php endif; ?>


            <div class="postbox"><div class="inside">
                <div class="promptor-content-selection-wrapper">
                    <?php
                    $post_types = get_post_types(array('public' => true), 'objects');
                    unset($post_types['attachment']);

                    // Free version: hide WooCommerce products
                    if ( ! $is_premium ) {
                        unset($post_types['product']);
                    }

                    // PDF support (Pro only - listing works without PDF Parser, but indexing requires it)
                    $pdf_query = $is_premium
    ? new WP_Query(array('post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'application/pdf', 'posts_per_page' => 50))
    : null;
$total_found_pdf = $pdf_query ? $pdf_query->found_posts : 0;
$max_pages_pdf = $pdf_query ? $pdf_query->max_num_pages : 0;
$has_pdf_parser = class_exists('\Smalot\PdfParser\Parser');

                    $first_tab = true; 
                    ?>
                    <div class="nav-tab-wrapper promptor-content-tabs">
    <?php foreach ( $post_types as $post_type ) : ?>
        <a href="#tab-<?php echo esc_attr( $post_type->name ); ?>" class="nav-tab <?php echo esc_attr( $first_tab ? 'nav-tab-active' : '' ); ?>">
            <?php echo esc_html( $post_type->labels->name ); ?>
        </a>
        <?php $first_tab = false; ?>
    <?php endforeach; ?>
    <?php if ( $pdf_query && $pdf_query->have_posts() ) : ?>
        <a href="#tab-pdf" class="nav-tab <?php echo esc_attr( $first_tab ? 'nav-tab-active' : '' ); ?>">
            <?php esc_html_e( 'PDF Files', 'promptor' ); ?>
        </a>
    <?php endif; ?>
</div>

                    <?php $first_tab = true; foreach ( $post_types as $post_type_slug => $post_type_object ) :
// Run query first to get total count
$posts_query = new WP_Query(array(
    'post_type' => $post_type_slug,
    'posts_per_page' => 50,
    'post_status' => 'publish',
    'orderby' => 'title',
    'order' => 'ASC'
));
$total_found = $posts_query->found_posts;
$max_pages = $posts_query->max_num_pages;
?>
<div id="tab-<?php echo esc_attr( $post_type_slug ); ?>" class="promptor-tab-content <?php echo esc_attr( $first_tab ? 'active' : '' ); ?>">
<?php $first_tab = false; ?>
<div class="promptor-tab-toolbar promptor-kb-action-bar">
    <div class="alignleft actions bulkactions">
        <label for="bulk-action-selector-top-<?php echo esc_attr($post_type_slug); ?>" class="screen-reader-text"><?php esc_html_e('Select bulk action', 'promptor'); ?></label>
        <select class="promptor-bulk-action-selector">
            <option value="-1"><?php esc_html_e('Bulk actions', 'promptor'); ?></option>
            <option value="change-role-service"><?php esc_html_e('Change role to: Service', 'promptor'); ?></option>
            <option value="change-role-product"><?php esc_html_e('Change role to: Product', 'promptor'); ?></option>
            <option value="change-role-blog"><?php esc_html_e('Change role to: Blog Post', 'promptor'); ?></option>
            <option value="change-role-faq"><?php esc_html_e('Change role to: FAQ', 'promptor'); ?></option>
        </select>
        <input type="button" class="button action promptor-bulk-apply-btn" value="<?php esc_attr_e('Apply', 'promptor'); ?>">
    </div>
    <div class="promptor-kb-progress">
        <strong class="promptor-selected-count">0 <?php esc_html_e("items selected", "promptor"); ?></strong>
        <button type="button" class="button promptor-select-page-btn"><?php esc_html_e("Select This Page", "promptor"); ?></button>
        <button type="button" class="button promptor-select-all-btn" data-total="<?php echo esc_attr($total_found); ?>"><?php esc_html_e("Select All", "promptor"); ?></button>
    </div><p class="search-box" style="margin: 0;"><input type="search" class="promptor-content-search" data-target-table="table-<?php echo esc_attr($post_type_slug); ?>" placeholder="<?php esc_attr_e('Search content...', 'promptor'); ?>"></p>
</div><?php if ($posts_query->have_posts()): ?><table class="wp-list-table widefat striped fixed" id="table-<?php echo esc_attr($post_type_slug); ?>"><thead><tr><th scope="col" class="manage-column column-cb check-column"><input type="checkbox" /></th><th scope="col" style="width: 40%;"><?php esc_html_e('Title', 'promptor'); ?></th><th scope="col" style="width: 20%;"><?php esc_html_e('Content Role', 'promptor'); ?></th><th scope="col" style="width: 15%;"><?php esc_html_e('Word Count', 'promptor'); ?></th><th scope="col"><?php esc_html_e('Date', 'promptor'); ?></th></tr></thead><tbody><?php if (is_post_type_hierarchical($post_type_slug)) { $this->display_hierarchical_posts($post_type_slug, $content_roles, $context_key); } else { while($posts_query->have_posts()){ $posts_query->the_post(); $this->display_post_row(get_post(), $content_roles, $context_key); } } wp_reset_postdata(); ?></tbody></table><?php if ( $max_pages > 1 ): ?><div class="promptor-load-more-wrapper" style="text-align: center; padding: 20px;"><button type="button" class="button promptor-load-more-btn" data-post-type="<?php echo esc_attr($post_type_slug); ?>" data-context="<?php echo esc_attr($context_key); ?>" data-page="1" data-max-pages="<?php echo esc_attr($max_pages); ?>"><?php echo esc_html( sprintf( /* translators: %d: Number of remaining items to load */ __( "Load More (%d remaining)", "promptor" ), max( 0, $total_found - 50 ) ) ); ?></button><span class="spinner" style="float: none; margin: 0 10px; display: none;"></span></div><?php endif; ?><?php else: ?><p><?php esc_html_e('No content found for this post type.', 'promptor'); ?></p><?php endif; ?></div><?php endforeach; ?>
                    <?php if($pdf_query && $pdf_query->have_posts()): ?><div id="tab-pdf" class="promptor-tab-content <?php echo esc_attr( $first_tab ? 'active' : '' ); ?>"><?php if ( ! $has_pdf_parser ) : ?>
    <div class="promptor-inline-notice notice-warning">
        <p>
            <strong><?php esc_html_e( 'Note:', 'promptor' ); ?></strong>
            <?php esc_html_e( 'PDF Parser library is not installed. You can add PDFs to the knowledge base, but their content cannot be indexed. Only titles and metadata will be used for AI recommendations.', 'promptor' ); ?>
        </p>
    </div>
<?php endif; ?><div class="promptor-tab-toolbar promptor-kb-action-bar">
    <div class="alignleft actions bulkactions">
        <label for="bulk-action-selector-top-pdf" class="screen-reader-text"><?php esc_html_e('Select bulk action', 'promptor'); ?></label>
        <select class="promptor-bulk-action-selector">
            <option value="-1"><?php esc_html_e('Bulk actions', 'promptor'); ?></option>
            <option value="change-role-service"><?php esc_html_e('Change role to: Service', 'promptor'); ?></option>
            <option value="change-role-product"><?php esc_html_e('Change role to: Product', 'promptor'); ?></option>
            <option value="change-role-blog"><?php esc_html_e('Change role to: Blog Post', 'promptor'); ?></option>
            <option value="change-role-faq"><?php esc_html_e('Change role to: FAQ', 'promptor'); ?></option>
        </select>
        <input type="button" class="button action promptor-bulk-apply-btn" value="<?php esc_attr_e('Apply', 'promptor'); ?>">
    </div>
    <div class="promptor-kb-progress">
        <strong class="promptor-selected-count">0 <?php esc_html_e("items selected", "promptor"); ?></strong>
        <button type="button" class="button promptor-select-page-btn"><?php esc_html_e("Select This Page", "promptor"); ?></button>
        <button type="button" class="button promptor-select-all-btn" data-total="<?php echo esc_attr($total_found_pdf); ?>"><?php esc_html_e("Select All", "promptor"); ?></button>
    </div><p class="search-box" style="margin: 0;"><input type="search" class="promptor-content-search" data-target-table="table-pdf" placeholder="<?php esc_attr_e('Search PDFs...', 'promptor'); ?>"></p>
</div><table class="wp-list-table widefat striped fixed" id="table-pdf"><thead><tr><th scope="col" class="manage-column column-cb check-column"><input type="checkbox" /></th><th scope="col" style="width: 45%;"><?php esc_html_e('File Name', 'promptor'); ?></th><th scope="col" style="width: 25%;"><?php esc_html_e('Content Role', 'promptor'); ?></th><th scope="col"><?php esc_html_e('Date', 'promptor'); ?></th></tr></thead><tbody><?php while ($pdf_query->have_posts()) : $pdf_query->the_post(); global $post; ?><tr><th scope="row" class="check-column"><?php
$is_selected = in_array($post->ID, $selected_post_ids);
$current_role = $content_roles[$post->ID] ?? 'blog';
$roles = array('service' => __('Service', 'promptor'), 'product' => __('Product', 'promptor'), 'blog' => __('Blog Post', 'promptor'), 'faq' => __('FAQ', 'promptor'));
?><input type="checkbox" name="selected_files[]" value="<?php echo esc_attr($post->ID); ?>" <?php checked($is_selected); ?>></th><td><strong><a href="<?php echo esc_url(wp_get_attachment_url($post->ID)); ?>" target="_blank"><?php echo esc_html($post->post_title); ?></a></strong><br><small><?php echo esc_html(basename(get_attached_file($post->ID))); ?></small></td><td><select name="content_roles[<?php echo esc_attr($post->ID); ?>]" class="promptor-role-selector"><?php foreach ($roles as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($current_role, $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></td><td><?php echo esc_html(get_the_date()); ?></td></tr><?php endwhile; ?></tbody></table><?php $total_found_pdf = $pdf_query->found_posts; $max_pages_pdf = $pdf_query->max_num_pages; if ( $max_pages_pdf > 1 ): ?><div class="promptor-load-more-wrapper" style="text-align: center; padding: 20px;"><button type="button" class="button promptor-load-more-btn" data-post-type="pdf" data-context="<?php echo esc_attr($context_key); ?>" data-page="1" data-max-pages="<?php echo esc_attr($max_pages_pdf); ?>"><?php echo esc_html( sprintf( /* translators: %d: Number of remaining items to load */ __( "Load More (%d remaining)", "promptor" ), max( 0, $total_found_pdf - 50 ) ) ); ?></button><span class="spinner" style="float: none; margin: 0 10px; display: none;"></span></div><?php endif; ?></div><?php wp_reset_postdata(); endif; ?>
                </div>
            </div></div>
            <div class="postbox">
				<h2 class="hndle"><span class="dashicons dashicons-editor-help promptor-hndle-icon"></span><span><?php esc_html_e('Example Questions', 'promptor'); ?></span></h2>
				<div class="inside">
					<table class="form-table">
						<tr>
							<th scope="row"><label for="example_questions"><?php esc_html_e('Example Questions', 'promptor'); ?></label></th>
							<td>
								<textarea id="example_questions" name="example_questions" rows="5" class="large-text" placeholder="<?php esc_attr_e('e.g. I want to build a corporate website.', 'promptor'); ?>"><?php echo esc_textarea($example_questions); ?></textarea>
								<p class="description"><?php esc_html_e('Enter one example question per line. These will be shown to the user as suggestions.', 'promptor'); ?></p>
								<p>
									<button type="button" id="promptor-generate-questions" class="button" data-context="<?php echo esc_attr($context_key); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'promptor_generate_questions' ) ); ?>">
										<span class="dashicons dashicons-lightbulb" style="margin-top: 3px;"></span>
										<?php esc_html_e('Generate with AI', 'promptor'); ?>
									</button>
									<span class="spinner" style="float: none; margin: 0 10px;"></span>
									<span id="promptor-generate-status" class="promptor-status-ok"></span>
								</p>
							</td>
						</tr>
					</table>
				</div>
			</div>
            <div class="postbox">
    <h2 class="hndle"><span class="dashicons dashicons-update promptor-hndle-icon"></span><span><?php esc_html_e('Automation Settings (Pro)', 'promptor'); ?></span></h2>
    <div class="inside">
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Auto-Sync on Publish', 'promptor'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="auto_sync_enabled" value="1"
                            <?php checked( ! empty( $context_data['settings']['auto_sync_enabled'] ) ); ?>
                            <?php disabled( ! $is_premium ); ?>>
                        <?php esc_html_e( 'Automatically add and index newly published content to this knowledge base.', 'promptor' ); ?>
                    </label>
                    <?php if ( ! $is_premium ) : ?>
                        <p class="description" style="color: #d63638;"><?php esc_html_e( 'This is a Pro feature.', 'promptor' ); ?></p>
                    <?php endif; ?>

                </td>
            </tr>
        </table>
    </div>
</div>
            <p class="submit">
                <button type="button" id="promptor-save-content-selection" class="button button-primary"
        data-nonce="<?php echo esc_attr( wp_create_nonce( 'promptor_save_content_selection' ) ); ?>">
    <?php esc_html_e('Save Changes', 'promptor'); ?>
</button>

                <span class="spinner"></span>
            </p>
        </div>
        <?php
    }

    private function display_post_row($post, $content_roles, $context_key, $level = 0) {
    $is_selected = array_key_exists($post->ID, $content_roles);
    
    // YENİ AKILLI VARSAYILAN ATAMA MANTIĞI
    if ($is_selected) {
        $current_role = $content_roles[$post->ID];
    } else {
        switch ($post->post_type) {
            case 'page':
                $current_role = 'service';
                break;
            case 'product':
                $current_role = 'product';
                break;
            case 'post':
            default: // Diğer tüm Custom Post Type'lar dahil
                $current_role = 'blog';
                break;
        }
    }

    $roles = array(
        'service' => __('Service', 'promptor'),
        'product' => __('Product', 'promptor'),
        'blog'    => __('Blog Post', 'promptor'),
        'faq'     => __('FAQ', 'promptor')
    );
    $padding = str_repeat('&#8212; ', $level);
    $content_raw      = get_post_field('post_content', $post->ID);
    $content_stripped = wp_strip_all_tags( (string) $content_raw );
    $word_count       = $content_stripped !== '' ? str_word_count( preg_replace('/\s+/', ' ', $content_stripped) ) : 0;
    ?>
    <tr>
        <th scope="row" class="check-column">
            <input type="checkbox" name="selected_pages[]" value="<?php echo esc_attr($post->ID); ?>" <?php checked($is_selected); ?>>
        </th>
        <td><?php echo wp_kses_post($padding); ?><strong><a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>" target="_blank"><?php echo esc_html($post->post_title); ?></a></strong></td>
        <td>
            <select name="content_roles[<?php echo esc_attr($post->ID); ?>]" class="promptor-role-selector">
                <?php foreach ($roles as $key => $label) : ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($current_role, $key); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><?php echo esc_html(number_format_i18n($word_count)); ?></td>
        <td><?php echo esc_html(get_the_date('', $post)); ?></td>
    </tr>
    <?php
}
    
    private function display_hierarchical_posts($post_type, $content_roles, $context_key, $parent_id = 0, $level = 0) {
        $args = array('post_type' => $post_type, 'posts_per_page' => -1, 'post_status' => 'publish', 'post_parent' => $parent_id, 'orderby' => 'menu_order title', 'order' => 'ASC');
        $children = get_posts($args);
        foreach ($children as $post) {
            $this->display_post_row($post, $content_roles, $context_key, $level);
            $this->display_hierarchical_posts($post_type, $content_roles, $context_key, $post->ID, $level + 1);
        }
    }

    public function render_text_field($args) {
        $options = get_option($args['option_name']);
        $value = $options[$args['field_id']] ?? $args['default'] ?? '';
        $type = $args['type'] ?? 'text';
        $class = ($type === 'number') ? 'small-text' : 'regular-text';
        echo "<input type='" . esc_attr($type) . "' id='" . esc_attr($args['field_id']) . "' name='" . esc_attr($args['option_name'] . '[' . $args['field_id'] . ']') . "' value='" . esc_attr($value) . "' class='" . esc_attr($class) . "' />";
        if (isset($args['desc'])) { echo "<p class='description'>" . esc_html($args['desc']) . "</p>"; }
    }

    public function render_textarea_field($args) {
        $options = get_option($args['option_name']);
        $value = $options[$args['field_id']] ?? $args['default'] ?? '';
        echo "<textarea id='" . esc_attr($args['field_id']) . "' name='" . esc_attr($args['option_name'] . '[' . $args['field_id'] . ']') . "' rows='5' class='large-text'>" . esc_textarea($value) . "</textarea>";
        if (isset($args['desc'])) {
            echo "<p class='description'>" . esc_html($args['desc']) . "</p>";
        }
    }
    
    public function render_select_field($args) {
        $options = get_option($args['option_name']);
        $value = $options[$args['field_id']] ?? $args['default'] ?? 'never';
        echo "<select id='" . esc_attr($args['field_id']) . "' name='" . esc_attr($args['option_name'] . '[' . $args['field_id'] . ']') . "'>";
        foreach($args['options'] as $val => $label) {
            echo "<option value='" . esc_attr($val) . "' " . selected($value, $val, false) . ">" . esc_html($label) . "</option>";
        }
        echo "</select>";
        if (isset($args['desc'])) {
            echo "<p class='description'>" . esc_html($args['desc']) . "</p>";
        }
    }

    /**
     * Show trial context notice when trial is active with temporary content (v1.4.0).
     */
    private function maybe_render_trial_kb_notice() {
        if ( ! class_exists( 'Promptor_Trial_Client' ) ) {
            return;
        }

        $api_settings = get_option( 'promptor_api_settings', array() );
        if ( ! empty( $api_settings['api_key'] ) ) {
            return; // User has own API key — no trial notice needed
        }

        if ( ! Promptor_Trial_Client::is_trial_active() ) {
            return;
        }

        $trial_ctx = Promptor_Trial_Client::get_trial_context();
        $pages     = $trial_ctx['pages'] ?? array();

        if ( empty( $pages ) ) {
            return;
        }

        $page_titles = array_filter( array_column( $pages, 'title' ) );
        ?>
        <div class="notice notice-info" style="margin: 15px 0; padding: 12px 15px;">
            <p>
                <span class="dashicons dashicons-info" style="color: #2271b1; margin-right: 4px;"></span>
                <strong><?php esc_html_e( 'Trial Mode: Using temporary AI context from your selected pages.', 'promptor' ); ?></strong>
            </p>
            <?php if ( ! empty( $page_titles ) ) : ?>
                <p style="margin: 5px 0 0; color: #646970;">
                    <?php
                    printf(
                        /* translators: %s: comma-separated page titles */
                        esc_html__( 'Pages: %s', 'promptor' ),
                        esc_html( implode( ', ', $page_titles ) )
                    );
                    ?>
                </p>
            <?php endif; ?>
            <p style="margin: 8px 0 0; color: #646970; font-size: 12px;">
                <?php esc_html_e( 'Temporary trial content is separate from your synced Knowledge Base. Add your OpenAI API key to sync and index your content permanently.', 'promptor' ); ?>
            </p>
        </div>
        <?php
    }
}