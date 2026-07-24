<?php
/**
 * Promptor List Table and Pages
 *
 * Handles the display and management of queries and submissions in list tables.
 *
 * @package Promptor
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Promptor_Queries_List_Table
 *
 * Displays AI queries in a WP_List_Table format.
 */
class Promptor_Queries_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => __( 'Query', 'promptor' ),
				'plural'   => __( 'Queries', 'promptor' ),
				'ajax'     => false,
			)
		);
		
		// Hook to display admin notices.
		add_action( 'admin_notices', array( $this, 'display_bulk_action_notices' ) );
	}

	/**
	 * Display admin notices for bulk actions.
	 */
	public function display_bulk_action_notices() {
		// Only show on promptor-queries page.
		if ( ! isset( $_GET['page'] ) || 'promptor-queries' !== $_GET['page'] ) {
			return;
		}

		// Verify nonce for bulk result.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'promptor_bulk_result' ) ) {
			return;
		}

		// Delete notice.
		if ( isset( $_GET['bulk_deleted'] ) ) {
			$count = absint( $_GET['bulk_deleted'] );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %d: number of items deleted */
						_n( '%d query deleted successfully.', '%d queries deleted successfully.', $count, 'promptor' ),
						$count
					)
				)
			);
		}

		// Archive notice.
		if ( isset( $_GET['bulk_archived'] ) ) {
			$count = absint( $_GET['bulk_archived'] );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %d: number of items archived */
						_n( '%d query archived successfully.', '%d queries archived successfully.', $count, 'promptor' ),
						$count
					)
				)
			);
		}

		// Unarchive notice.
		if ( isset( $_GET['bulk_unarchived'] ) ) {
			$count = absint( $_GET['bulk_unarchived'] );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %d: number of items unarchived */
						_n( '%d query unarchived successfully.', '%d queries unarchived successfully.', $count, 'promptor' ),
						$count
					)
				)
			);
		}
	}

	/**
	 * Display extra tablenav (filters).
	 *
	 * @param string $which Top or bottom of table.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		// Verify nonce for filter processing.
		$nonce_verified = false;
		if ( isset( $_REQUEST['_wpnonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
			if ( wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ) {
				$nonce_verified = true;
			}
		}

		$feedback_filter   = '';
		$suggestion_filter = '';

		if ( $nonce_verified ) {
			$feedback_filter   = isset( $_REQUEST['feedback_filter'] ) ? sanitize_key( wp_unslash( $_REQUEST['feedback_filter'] ) ) : '';
			$suggestion_filter = isset( $_REQUEST['suggestion_filter'] ) ? sanitize_key( wp_unslash( $_REQUEST['suggestion_filter'] ) ) : '';
		}
		?>
		<div class="alignleft actions">
			<select name="feedback_filter" id="feedback_filter">
				<option value=""><?php esc_html_e( 'All Feedback', 'promptor' ); ?></option>
				<option value="1" <?php selected( $feedback_filter, '1' ); ?>><?php esc_html_e( 'Positive Feedback', 'promptor' ); ?></option>
				<option value="-1" <?php selected( $feedback_filter, '-1' ); ?>><?php esc_html_e( 'Negative Feedback', 'promptor' ); ?></option>
				<option value="0" <?php selected( $feedback_filter, '0' ); ?>><?php esc_html_e( 'No Feedback', 'promptor' ); ?></option>
			</select>

			<select name="suggestion_filter" id="suggestion_filter">
				<option value=""><?php esc_html_e( 'All Suggestions', 'promptor' ); ?></option>
				<option value="yes" <?php selected( $suggestion_filter, 'yes' ); ?>><?php esc_html_e( 'With Suggestions', 'promptor' ); ?></option>
				<option value="no"  <?php selected( $suggestion_filter, 'no' ); ?>><?php esc_html_e( 'Without Suggestions', 'promptor' ); ?></option>
			</select>

			<?php submit_button( __( 'Filter', 'promptor' ), 'action', '', false, array( 'id' => 'post-query-submit' ) ); ?>
		</div>
		<?php
	}

	/**
	 * Get views (Active/Archived tabs).
	 *
	 * @return array Views array.
	 */
	protected function get_views() {
		global $wpdb;

		// Try to get cached counts.
		$counts = wp_cache_get( 'promptor_queries_counts', 'promptor' );
		
		if ( false === $counts ) {
			$counts = array(
				'active'   => 0,
				'archived' => 0,
				'total'    => 0,
			);

			// Total count query - direkt prepare kullan.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$total_count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(id) FROM {$wpdb->prefix}promptor_queries WHERE 1=%d",
					1
				)
			);

			// Archived count query - direkt prepare kullan.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$archived_count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(id) FROM {$wpdb->prefix}promptor_queries WHERE is_archived = %d",
					1
				)
			);

			$counts['total']    = (int) $total_count;
			$counts['archived'] = (int) $archived_count;
			$counts['active']   = $counts['total'] - $counts['archived'];

			// Cache for 1 hour.
			wp_cache_set( 'promptor_queries_counts', $counts, 'promptor', HOUR_IN_SECONDS );
		}

		// Verify nonce for view parameter.
		$current_view   = 'active';
		$nonce_verified = false;

		if ( isset( $_REQUEST['_wpnonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
			if ( wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ) {
				$nonce_verified = true;
			}
		}

		if ( $nonce_verified && isset( $_REQUEST['view'] ) ) {
			$current_view = sanitize_key( wp_unslash( $_REQUEST['view'] ) );
		}

		$base_url = admin_url( 'admin.php?page=promptor-queries' );

		// Nonce oluştur - tab değiştirme için gerekli.
		$views_nonce = wp_create_nonce( 'bulk-' . $this->_args['plural'] );

		$views = array(
			'active'   => sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
				esc_url( add_query_arg( array( 'view' => 'active', '_wpnonce' => $views_nonce ), $base_url ) ),
				'active' === $current_view ? 'current' : '',
				__( 'Active', 'promptor' ),
				absint( $counts['active'] )
			),
			'archived' => sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
				esc_url( add_query_arg( array( 'view' => 'archived', '_wpnonce' => $views_nonce ), $base_url ) ),
				'archived' === $current_view ? 'current' : '',
				__( 'Archived', 'promptor' ),
				absint( $counts['archived'] )
			),
		);

		return $views;
	}

	/**
	 * Get table columns.
	 *
	 * @return array Columns array.
	 */
	public function get_columns() {
		return array(
			'cb'               => '<input type="checkbox" />',
			'user_query'       => __( 'User Query', 'promptor' ),
			'suggestions'      => __( 'Suggested Items', 'promptor' ),
			'linked_order'     => __( 'Linked Order', 'promptor' ),
			'feedback'         => __( 'Feedback', 'promptor' ),
			'response_time_ms' => __( 'Response Time', 'promptor' ),
			'tokens_used'      => __( 'Tokens Used', 'promptor' ),
			'query_cost'       => __( 'Cost', 'promptor' ),
			'query_timestamp'  => __( 'Date', 'promptor' ),
		);
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array Sortable columns array.
	 */
	public function get_sortable_columns() {
		return array(
			'linked_order'     => array( 'linked_order_id', false ),
			'response_time_ms' => array( 'response_time_ms', false ),
			'tokens_used'      => array( 'tokens_used', false ),
			'query_cost'       => array( 'query_cost', false ),
			'query_timestamp'  => array( 'query_timestamp', true ),
		);
	}

	/**
	 * Default column display.
	 *
	 * @param array  $item        Item data.
	 * @param string $column_name Column name.
	 * @return string Column content.
	 */
	protected function column_default( $item, $column_name ) {
		$api_settings = get_option( 'promptor_api_settings', array() );

		switch ( $column_name ) {
			case 'feedback':
				if ( 1 === (int) $item['feedback'] ) {
					return '<span class="dashicons dashicons-thumbs-up promptor-feedback-positive"></span>';
				}
				if ( -1 === (int) $item['feedback'] ) {
					return '<span class="dashicons dashicons-thumbs-down promptor-feedback-negative"></span>';
				}
				return '—';

			case 'response_time_ms':
				$threshold_red    = isset( $api_settings['highlight_response_red'] ) ? (int) $api_settings['highlight_response_red'] : 5000;
				$threshold_yellow = isset( $api_settings['highlight_response_yellow'] ) ? (int) $api_settings['highlight_response_yellow'] : 3000;

				$time_ms = ! empty( $item[ $column_name ] ) ? (int) $item[ $column_name ] : 0;
				if ( ! $time_ms ) {
					return 'N/A';
				}

				$time_s = $time_ms / 1000;
				
				// CSS class ile renklendirme - inline style yerine.
				$class = 'promptor-time-normal';
				if ( $time_ms > $threshold_red ) {
					$class = 'promptor-time-slow';
				} elseif ( $time_ms > $threshold_yellow ) {
					$class = 'promptor-time-warning';
				}

				$time_s_display = number_format_i18n( $time_s, 2 );
				return '<span class="' . esc_attr( $class ) . '">' . esc_html( $time_s_display ) . ' s</span>';

			case 'query_cost':
				$threshold_red    = isset( $api_settings['highlight_cost_red'] ) ? (float) $api_settings['highlight_cost_red'] : 0.005;
				$threshold_yellow = isset( $api_settings['highlight_cost_yellow'] ) ? (float) $api_settings['highlight_cost_yellow'] : 0.003;

				$cost = ! empty( $item[ $column_name ] ) ? (float) $item[ $column_name ] : 0.0;
				if ( $cost <= 0 ) {
					return 'N/A';
				}

				// CSS class ile renklendirme - inline style yerine.
				$class = 'promptor-cost-normal';
				if ( $cost > $threshold_red ) {
					$class = 'promptor-cost-high';
				} elseif ( $cost > $threshold_yellow ) {
					$class = 'promptor-cost-warning';
				}

				$cost_display = number_format_i18n( $cost, 6 );
				return '<span class="' . esc_attr( $class ) . '">$' . esc_html( $cost_display ) . '</span>';

			case 'tokens_used':
				return ! empty( $item[ $column_name ] ) ? esc_html( number_format_i18n( (int) $item[ $column_name ] ) ) : 'N/A';

			case 'query_timestamp':
				$ts = ! empty( $item[ $column_name ] ) ? strtotime( $item[ $column_name ] ) : false;
				return $ts ? esc_html( date_i18n( get_option( 'date_format' ) . ' H:i', $ts ) ) : 'N/A';

			case 'suggestions':
				return ! empty( $item['suggested_service_titles'] ) ? esc_html( $item['suggested_service_titles'] ) : '—';

			case 'user_query':
				return '<strong>' . esc_html( wp_trim_words( $item['user_query'], 15, '...' ) ) . '</strong>';

			case 'linked_order':
				if ( ! empty( $item['linked_order_id'] ) && $item['linked_order_id'] > 0 && class_exists( 'WooCommerce' ) ) {
					$order_url       = get_edit_post_link( $item['linked_order_id'] );
					$order_total_raw = $item['order_value'];
					$order_total     = is_numeric( $order_total_raw ) ? wc_price( $order_total_raw ) : 'N/A';
					return sprintf(
						'<a href="%s" target="_blank" rel="noopener noreferrer"><strong>#%d</strong></a><br>%s',
						esc_url( $order_url ),
						absint( $item['linked_order_id'] ),
						wp_kses_post( $order_total )
					);
				}
				return '—';

			default:
				return '';
		}
	}

	/**
	 * Handle row actions.
	 *
	 * @param array  $item        Item data.
	 * @param string $column_name Column name.
	 * @param string $primary     Primary column name.
	 * @return string Row actions HTML.
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) {
		if ( $primary !== $column_name ) {
			return '';
		}

		$page = 'promptor-queries';

		// Verify nonce for view parameter.
		$view           = 'active';
		$nonce_verified = false;

		if ( isset( $_REQUEST['_wpnonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
			if ( wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ) {
				$nonce_verified = true;
			}
		}

		if ( $nonce_verified && isset( $_REQUEST['view'] ) ) {
			$view = sanitize_key( wp_unslash( $_REQUEST['view'] ) );
		}

		$actions = array();
		$base    = admin_url( 'admin.php' );

		if ( 'active' === $view ) {
			$archive_url        = add_query_arg(
				array(
					'page'     => $page,
					'action'   => 'archive',
					'query_id' => absint( $item['id'] ),
				),
				$base
			);
			$actions['archive'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( wp_nonce_url( $archive_url, 'promptor_archive_query' ) ),
				__( 'Archive', 'promptor' )
			);
		} else {
			$unarchive_url        = add_query_arg(
				array(
					'page'     => $page,
					'action'   => 'unarchive',
					'query_id' => absint( $item['id'] ),
				),
				$base
			);
			$actions['unarchive'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( wp_nonce_url( $unarchive_url, 'promptor_unarchive_query' ) ),
				__( 'Unarchive', 'promptor' )
			);
		}

		$delete_url        = add_query_arg(
			array(
				'page'     => $page,
				'action'   => 'delete',
				'query_id' => absint( $item['id'] ),
			),
			$base
		);
		$actions['delete'] = sprintf(
			'<a href="%s" class="submitdelete">%s</a>',
			esc_url( wp_nonce_url( $delete_url, 'promptor_delete_query' ) ),
			__( 'Delete Permanently', 'promptor' )
		);

		return $this->row_actions( $actions );
	}

	/**
	 * Checkbox column.
	 *
	 * @param array $item Item data.
	 * @return string Checkbox HTML.
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="query_ids[]" value="%s" />', esc_attr( $item['id'] ) );
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array Bulk actions array.
	 */
	protected function get_bulk_actions() {
		// Verify nonce for view parameter.
		$view           = 'active';
		$nonce_verified = false;

		if ( isset( $_REQUEST['_wpnonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
			if ( wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ) {
				$nonce_verified = true;
			}
		}

		if ( $nonce_verified && isset( $_REQUEST['view'] ) ) {
			$view = sanitize_key( wp_unslash( $_REQUEST['view'] ) );
		}

		if ( 'archived' === $view ) {
			return array(
				'bulk-unarchive' => __( 'Unarchive', 'promptor' ),
				'bulk-delete'    => __( 'Delete Permanently', 'promptor' ),
			);
		}

		return array(
			'bulk-archive' => __( 'Archive', 'promptor' ),
			'bulk-delete'  => __( 'Delete Permanently', 'promptor' ),
		);
	}

	/**
	 * Process bulk actions.
	 */
	public function process_bulk_action() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'promptor_queries';
		$action     = $this->current_action();

		if ( ! $action ) {
			return;
		}

		// Capability check - EN ÖNCE.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'promptor' ) );
		}

		// Nonce verification.
		$nonce          = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
		$nonce_verified = false;

		if ( 0 === strpos( $action, 'bulk-' ) ) {
			if ( wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ) {
				$nonce_verified = true;
			}
		} elseif ( in_array( $action, array( 'archive', 'unarchive', 'delete' ), true ) ) {
			if ( wp_verify_nonce( $nonce, 'promptor_' . $action . '_query' ) ) {
				$nonce_verified = true;
			}
		}

		if ( ! $nonce_verified ) {
			wp_die( esc_html__( 'Security check failed.', 'promptor' ) );
		}

		// Collect IDs - properly sanitized.
		$ids = array();
		if ( isset( $_REQUEST['query_ids'] ) && is_array( $_REQUEST['query_ids'] ) ) {
			$ids_raw = array_map( 'sanitize_text_field', wp_unslash( $_REQUEST['query_ids'] ) );
			$ids     = array_unique( array_map( 'absint', $ids_raw ) );
		}

		// Single ID - properly sanitized.
		if ( isset( $_REQUEST['query_id'] ) ) {
			$single_id = absint( wp_unslash( $_REQUEST['query_id'] ) );
			if ( $single_id > 0 ) {
				$ids = array( $single_id );
			}
		}

		if ( empty( $ids ) ) {
			return;
		}

		// Build placeholders.
		$placeholders   = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$query_executed = false;

		// Execute query based on action - BUILD COMPLETE SQL THEN PREPARE.
		if ( 'delete' === $action || 'bulk-delete' === $action ) {
			$query = "DELETE FROM {$wpdb->prefix}promptor_queries WHERE id IN ({$placeholders})";
			
			/**
			 * Direct database query is required for bulk delete operations.
			 * Security measures in place:
			 * - Nonce verification completed above
			 * - Capability check (manage_options) enforced
			 * - All IDs sanitized with absint()
			 * - Using $wpdb->prepare() with placeholders
			 * - Cache invalidation included
			 */
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( $wpdb->prepare( $query, ...$ids ) );
			$query_executed = true;
		} elseif ( 'archive' === $action || 'bulk-archive' === $action ) {
			$query = "UPDATE {$wpdb->prefix}promptor_queries SET is_archived = 1 WHERE id IN ({$placeholders})";
			
			/**
			 * Direct database query is required for bulk archive operations.
			 * Security measures in place:
			 * - Nonce verification completed above
			 * - Capability check (manage_options) enforced
			 * - All IDs sanitized with absint()
			 * - Using $wpdb->prepare() with placeholders
			 * - Cache invalidation included
			 */
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( $wpdb->prepare( $query, ...$ids ) );
			$query_executed = true;
		} elseif ( 'unarchive' === $action || 'bulk-unarchive' === $action ) {
			$query = "UPDATE {$wpdb->prefix}promptor_queries SET is_archived = 0 WHERE id IN ({$placeholders})";
			
			/**
			 * Direct database query is required for bulk unarchive operations.
			 * Security measures in place:
			 * - Nonce verification completed above
			 * - Capability check (manage_options) enforced
			 * - All IDs sanitized with absint()
			 * - Using $wpdb->prepare() with placeholders
			 * - Cache invalidation included
			 */
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( $wpdb->prepare( $query, ...$ids ) );
			$query_executed = true;
		}

		if ( $query_executed ) {
			// Cache invalidation - tüm ilgili cache'leri temizle.
			wp_cache_delete( 'promptor_queries_counts', 'promptor' );
			
			// prepare_items() metodundaki count cache'lerini temizle.
			$this->clear_query_count_cache();

			// Redirect after successful action.
			$action_count = count( $ids );
			$redirect_url = add_query_arg(
				array(
					'page'     => 'promptor-queries',
					'_wpnonce' => wp_create_nonce( 'promptor_bulk_result' ),
				),
				admin_url( 'admin.php' )
			);

			// Add specific message parameter based on action.
			if ( in_array( $action, array( 'delete', 'bulk-delete' ), true ) ) {
				$redirect_url = add_query_arg( 'bulk_deleted', $action_count, $redirect_url );
			} elseif ( in_array( $action, array( 'archive', 'bulk-archive' ), true ) ) {
				$redirect_url = add_query_arg( 'bulk_archived', $action_count, $redirect_url );
			} elseif ( in_array( $action, array( 'unarchive', 'bulk-unarchive' ), true ) ) {
				$redirect_url = add_query_arg( 'bulk_unarchived', $action_count, $redirect_url );
			}

			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Clear query count cache.
	 * 
	 * Clears cached count queries from prepare_items().
	 */
	private function clear_query_count_cache() {
		// WordPress object cache ile group flush.
		global $wp_object_cache;
		
		if ( is_object( $wp_object_cache ) && method_exists( $wp_object_cache, 'flush_group' ) ) {
			$wp_object_cache->flush_group( 'promptor' );
		} else {
			// Fallback - bilinen cache pattern'lerini temizle.
			// Active ve archived view'lar için cache key'leri.
			$views = array( 'active', 'archived' );
			
			foreach ( $views as $view ) {
				// Her view için muhtemel cache key'leri temizle.
				for ( $i = 0; $i < 20; $i++ ) {
					wp_cache_delete( 'promptor_query_count_' . md5( $view . $i ), 'promptor' );
				}
			}
		}
	}

	/**
	 * Prepare items for display.
	 */
	public function prepare_items() {
		global $wpdb;

		$table_name            = $wpdb->prefix . 'promptor_queries';
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$this->process_bulk_action();

		$per_page     = 20;
		$current_page = $this->get_pagenum();

		// Verify nonce for all filters.
		$nonce_verified = false;
		if ( isset( $_REQUEST['_wpnonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
			if ( wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ) {
				$nonce_verified = true;
			}
		}

		// WHERE clause için array sistemi - GÜVENLİ.
		$where_parts  = array();
		$where_values = array();

		// View filter.
		$view = 'active';
		if ( $nonce_verified && isset( $_REQUEST['view'] ) ) {
			$view = sanitize_key( wp_unslash( $_REQUEST['view'] ) );
		}
		$where_parts[]  = 'is_archived = %d';
		$where_values[] = ( 'archived' === $view ? 1 : 0 );

		// Feedback filter.
		if ( $nonce_verified && isset( $_REQUEST['feedback_filter'] ) && '' !== $_REQUEST['feedback_filter'] ) {
			$feedback = sanitize_key( wp_unslash( $_REQUEST['feedback_filter'] ) );
			if ( '0' === $feedback ) {
				$where_parts[] = 'feedback IS NULL';
			} else {
				$where_parts[]  = 'feedback = %d';
				$where_values[] = (int) $feedback;
			}
		}

		// Suggestion filter.
		if ( $nonce_verified && isset( $_REQUEST['suggestion_filter'] ) && '' !== $_REQUEST['suggestion_filter'] ) {
			$suggestion = sanitize_key( wp_unslash( $_REQUEST['suggestion_filter'] ) );
			if ( 'yes' === $suggestion ) {
				$where_parts[] = "suggested_service_titles IS NOT NULL AND suggested_service_titles != ''";
			} elseif ( 'no' === $suggestion ) {
				$where_parts[] = "(suggested_service_titles IS NULL OR suggested_service_titles = '')";
			}
		}

		// Search filter.
		if ( $nonce_verified && isset( $_REQUEST['s'] ) ) {
			$search_term = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
			if ( '' !== $search_term ) {
				$like           = '%' . $wpdb->esc_like( $search_term ) . '%';
				$where_parts[]  = '(user_query LIKE %s OR suggested_service_titles LIKE %s)';
				$where_values[] = $like;
				$where_values[] = $like;
			}
		}

		// WHERE clause'u oluştur.
		$where_clause = implode( ' AND ', $where_parts );
		
		// PREPARE EDİLMİŞ WHERE CLAUSE - Bu artık tamamen güvenli.
		if ( ! empty( $where_values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$where_sql = $wpdb->prepare( $where_clause, $where_values );
		} else {
			$where_sql = $where_clause;
		}

		// COUNT query - CACHE İLE.
		$cache_key   = 'promptor_query_count_' . md5( $where_sql . $view );
		$total_items = wp_cache_get( $cache_key, 'promptor' );
		
		if ( false === $total_items ) {
			/**
			 * Direct database query is necessary for counting filtered results.
			 * Security measures in place:
			 * - $where_sql is already prepared using $wpdb->prepare()
			 * - All user inputs sanitized (sanitize_key, absint, esc_like)
			 * - Results cached for 5 minutes to reduce DB load
			 * - No user input directly in query string
			 */
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$total_items = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$wpdb->prefix}promptor_queries WHERE " . $where_sql );
			
			// Cache for 5 minutes.
			wp_cache_set( $cache_key, $total_items, 'promptor', 5 * MINUTE_IN_SECONDS );
		}

		// Sorting - WHITELIST kontrolü.
		$sortable_columns = $this->get_sortable_columns();
		$allowed_orderby  = array_keys( $sortable_columns );
		
		$orderby = 'query_timestamp';
		$order   = 'DESC';

		if ( $nonce_verified && isset( $_REQUEST['orderby'] ) ) {
			$orderby_input = sanitize_key( wp_unslash( $_REQUEST['orderby'] ) );
			if ( in_array( $orderby_input, $allowed_orderby, true ) ) {
				$orderby = $orderby_input;
			}
		}

		if ( $nonce_verified && isset( $_REQUEST['order'] ) ) {
			$order_input = strtoupper( sanitize_key( wp_unslash( $_REQUEST['order'] ) ) );
			if ( in_array( $order_input, array( 'ASC', 'DESC' ), true ) ) {
				$order = $order_input;
			}
		}

		// Güvenli ORDER BY (whitelist edilmiş, esc_sql ile güvenli).
		$order_by_sql = ' ORDER BY ' . esc_sql( $orderby ) . ' ' . esc_sql( $order );
		
		$limit  = (int) $per_page;
		$offset = (int) ( ( $current_page - 1 ) * $per_page );

		/**
		 * Direct database query is necessary for fetching paginated, filtered results.
		 * Security measures in place:
		 * - $where_sql is already prepared using $wpdb->prepare()
		 * - $order_by_sql uses whitelisted columns + esc_sql()
		 * - LIMIT and OFFSET use %d placeholders
		 * - All user inputs sanitized before query construction
		 * - No direct user input in concatenated SQL parts
		 */
		$limit  = (int) $per_page;
		$offset = (int) ( ( $current_page - 1 ) * $per_page );
		
		// Build final query - $where_sql and $order_by_sql already safe, only add LIMIT/OFFSET.
		$query = "SELECT * FROM {$wpdb->prefix}promptor_queries WHERE " . $where_sql . $order_by_sql . $wpdb->prepare( ' LIMIT %d OFFSET %d', $limit, $offset );
		
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$this->items = $wpdb->get_results( $query, ARRAY_A );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
			)
		);
	}
}

/**
 * Class Promptor_List_Table_Pages
 *
 * Handles the rendering of admin pages for queries and submissions.
 */
class Promptor_List_Table_Pages {

	/**
	 * Render the queries page.
	 */
	public function render_queries_page() {
		$queries_table = new Promptor_Queries_List_Table();

		// Handle cache refresh.
		if ( isset( $_GET['force_refresh'], $_GET['_wpnonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
			if ( wp_verify_nonce( $nonce, 'promptor_refresh_dashboard' ) ) {
				wp_cache_delete( 'promptor_dashboard_data_daily', 'promptor' );
				wp_cache_delete( 'promptor_dashboard_data_weekly', 'promptor' );
				wp_cache_delete( 'promptor_dashboard_data_monthly', 'promptor' );
				wp_cache_delete( 'promptor_dashboard_data_yearly', 'promptor' );
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Dashboard cache has been cleared.', 'promptor' ) . '</p></div>';
			}
		}
		?>
		<div class="wrap promptor-queries-wrap">
			<div class="promptor-page-header">
				<div class="header-content">
					<h1>
						<span class="dashicons dashicons-format-chat"></span>
						<?php esc_html_e( 'AI Conversations', 'promptor' ); ?>
					</h1>
					<p class="page-subtitle"><?php esc_html_e( 'View and analyze all AI-powered conversations with your website visitors.', 'promptor' ); ?></p>
				</div>
			</div>

			<?php
			// Display bulk delete notice.
			if ( isset( $_GET['bulk_deleted'], $_GET['_wpnonce'] ) ) {
				$nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
				if ( wp_verify_nonce( $nonce, 'promptor_bulk_result' ) ) {
					$count   = absint( wp_unslash( $_GET['bulk_deleted'] ) );
					$message = sprintf(
						/* translators: %s: number of deleted queries */
						_n(
							'%s query has been deleted.',
							'%s queries have been deleted.',
							$count,
							'promptor'
						),
						number_format_i18n( $count )
					);
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
				}
			}

			// Freemius integration - helper fonksiyon kullanımı önerilir.
			$has_fs     = function_exists( 'promptor_fs' );
			$is_premium = false;
			
			if ( $has_fs ) {
				$fs = promptor_fs();
				if ( is_object( $fs ) && method_exists( $fs, 'can_use_premium_code__premium_only' ) ) {
					$is_premium = (bool) $fs->can_use_premium_code__premium_only();
				}
			}

			if ( ! $is_premium ) {
				$query_count    = (int) get_option( 'promptor_query_count', 0 );
				$query_limit    = 100;
				$percentage     = ( $query_limit > 0 ) ? ( $query_count / $query_limit ) * 100 : 0;
				$progress_color = $percentage > 80 ? '#f44336' : ( $percentage > 50 ? '#ffc107' : '#4caf50' );

				echo '<div class="promptor-query-counter-bar">';
				echo '<span>' . sprintf(
					/* translators: 1: queries used, 2: query limit */
					esc_html__( 'Monthly Query Limit: %1$d / %2$d used', 'promptor' ),
					esc_html( number_format_i18n( $query_count ) ),
					esc_html( number_format_i18n( $query_limit ) )
				) . '</span>';
				echo '<div class="promptor-progress-track"><div class="promptor-progress-fill" style="width: ' . esc_attr( $percentage ) . '%; background-color: ' . esc_attr( $progress_color ) . ';"></div></div>';
				echo '</div>';

				if ( $percentage > 80 ) {
					echo '<div class="notice notice-warning"><p>';

					$upgrade_url = 'https://promptorai.com/pricing/';
					if ( $has_fs ) {
						$fs = promptor_fs();
						if ( is_object( $fs ) && method_exists( $fs, 'get_upgrade_url' ) ) {
							$upgrade_url = $fs->get_upgrade_url();
						}
					}

					printf(
						wp_kses_post(
							/* translators: 1: percentage, 2: upgrade URL */
							__( 'You have used over %1$d%% of your monthly query limit. <a href="%2$s" target="_blank" rel="noopener noreferrer"><strong>Upgrade to Pro</strong></a> for unlimited queries and more features!', 'promptor' )
						),
						80,
						esc_url( $upgrade_url )
					);

					echo '</p></div>';
				}
			}
			?>

			<form method="post" id="promptor-queries-form">
				<?php wp_nonce_field( 'bulk-' . $queries_table->_args['plural'] ); ?>
				<input type="hidden" name="page" value="promptor-queries" />
				<?php
				$queries_table->prepare_items();
				$queries_table->views();
				$queries_table->search_box( __( 'Search Queries', 'promptor' ), 'promptor_query_search' );
				$queries_table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the submissions page.
	 */
	public function render_submissions_page() {
		$has_fs   = function_exists( 'promptor_fs' );
		$can_premium = false;
		
		if ( $has_fs ) {
			$fs = promptor_fs();
			if ( is_object( $fs ) && method_exists( $fs, 'can_use_premium_code' ) ) {
				$can_premium = (bool) $fs->can_use_premium_code();
			}
		}

		if ( $can_premium ) {
			$submissions_table = new Promptor_Submissions_Table();
			echo '<div class="wrap promptor-submissions-wrap">';
			echo '<div class="promptor-page-header">';
			echo '<div class="header-content">';
			echo '<h1><span class="dashicons dashicons-email"></span> ' . esc_html__( 'Lead Submissions', 'promptor' ) . '</h1>';
			echo '<p class="page-subtitle">' . esc_html__( 'Manage and track all lead form submissions generated through AI conversations.', 'promptor' ) . '</p>';
			echo '</div>';
			echo '</div>';
			echo '<form method="post">';
			wp_nonce_field( 'promptor_submissions_action', 'promptor_submissions_nonce' );
			$submissions_table->prepare_items();
			$submissions_table->display();
			echo '</form></div>';
		} else {
			// Try to get cached submissions.
			$submissions = wp_cache_get( 'promptor_lite_recent_submissions', 'promptor' );

			if ( false === $submissions ) {
				global $wpdb;
				
				// Direkt prepare - sprintf gereksiz.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$submissions = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT name, email, recommendations, submitted_at FROM {$wpdb->prefix}promptor_submissions ORDER BY submitted_at DESC LIMIT %d",
						5
					),
					ARRAY_A
				);

				// Cache for 5 minutes.
				wp_cache_set( 'promptor_lite_recent_submissions', $submissions, 'promptor', 5 * MINUTE_IN_SECONDS );
			}

			$upgrade_url = 'https://promptorai.com/pricing/';
			if ( $has_fs ) {
				$fs = promptor_fs();
				if ( is_object( $fs ) && method_exists( $fs, 'get_upgrade_url' ) ) {
					$upgrade_url = $fs->get_upgrade_url();
				}
			}

			?>
			<div class="wrap promptor-submissions-wrap">
				<div class="promptor-page-header">
					<div class="header-content">
						<h1>
							<span class="dashicons dashicons-email"></span>
							<?php esc_html_e( 'Lead Submissions', 'promptor' ); ?>
						</h1>
						<p class="page-subtitle"><?php esc_html_e( 'Manage and track all lead form submissions generated through AI conversations.', 'promptor' ); ?></p>
					</div>
				</div>
				<div class="notice notice-info notice-large" style="margin-top: 20px;">
					<p style="font-size: 14px;">
						<?php
						printf(
							wp_kses_post(
								/* translators: %s: upgrade URL */
								__( 'You are using Promptor Lite. You can see the last 5 submissions here with limited data. To unlock full lead management features, including status tracking, detailed query information, and unlimited history, please <a href="%s" target="_blank" rel="noopener noreferrer"><strong>Upgrade to Next Level.</strong></a>', 'promptor' )
							),
							esc_url( $upgrade_url )
						);
						?>
					</p>
				</div>
				<table class="wp-list-table widefat striped" style="margin-top: 20px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Submitter', 'promptor' ); ?></th>
							<th><?php esc_html_e( 'Contact Details', 'promptor' ); ?></th>
							<th><?php esc_html_e( 'Selected Services', 'promptor' ); ?></th>
							<th><?php esc_html_e( 'Date', 'promptor' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $submissions ) ) : ?>
							<tr>
								<td colspan="4"><?php esc_html_e( 'No submissions yet.', 'promptor' ); ?></td>
							</tr>
						<?php else : ?>
							<?php foreach ( $submissions as $item ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $item['name'] ); ?></strong></td>
									<td>
										<?php
										// Free version: Show full email and phone (limited to 5 records)
										if ( ! empty( $item['email'] ) && is_email( $item['email'] ) ) {
											echo '<a href="mailto:' . esc_attr( $item['email'] ) . '">' . esc_html( $item['email'] ) . '</a>';
										} else {
											esc_html_e( 'Email hidden', 'promptor' );
										}
										?>
										<br>
										<?php
										if ( ! empty( $item['phone'] ) ) {
											echo esc_html( $item['phone'] );
										} else {
											echo '<span style="color:#999;">' . esc_html__( 'No phone', 'promptor' ) . '</span>';
										}
										?>
									</td>
									<td><?php echo esc_html( wp_trim_words( $item['recommendations'], 10, '...' ) ); ?></td>
									<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $item['submitted_at'] ) ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
			<?php
		}
	}
}