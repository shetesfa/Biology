<?php
/**
 * Promptor Submissions Table Class
 *
 * This class extends WP_List_Table to display and manage form submissions
 * in the WordPress admin area.
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
 * Promptor Submissions Table Class
 *
 * Manages the display and manipulation of form submissions in the admin area.
 *
 * @since 1.0.0
 */
class Promptor_Submissions_Table extends WP_List_Table {

	/**
	 * Cached status counts for submissions.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $status_counts;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => __( 'Submission', 'promptor' ),
				'plural'   => __( 'Submissions', 'promptor' ),
				'ajax'     => false,
			)
		);
		$this->count_statuses();
	}

	/**
	 * Count submissions by status and cache the results.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function count_statuses() {
		$cache_key     = 'promptor_submission_status_counts';
		$cached_counts = wp_cache_get( $cache_key, 'promptor' );

		if ( false !== $cached_counts ) {
			$this->status_counts = $cached_counts;
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'promptor_submissions';
		// Plugin Check: Ensure table name is escaped before interpolation.
		$safe_table = esc_sql( $table_name );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$counts = $wpdb->get_results( "SELECT status, COUNT(*) AS count FROM {$safe_table} GROUP BY status", ARRAY_A );

		if ( ! is_array( $counts ) ) {
			$counts = array();
		}

		$this->status_counts = array_fill_keys( array( 'pending', 'contacted', 'converted', 'rejected' ), 0 );

		foreach ( $counts as $row ) {
			$status = isset( $row['status'] ) ? (string) $row['status'] : '';
			if ( isset( $this->status_counts[ $status ] ) ) {
				$this->status_counts[ $status ] = (int) ( $row['count'] ?? 0 );
			}
		}

		// Cache the results for 5 minutes.
		wp_cache_set( $cache_key, $this->status_counts, 'promptor', 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Get status filter views for the list table.
	 *
	 * @since 1.0.0
	 * @return array Array of view links.
	 */
	protected function get_views() {
		$total_items = array_sum( $this->status_counts );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification not needed for view filtering.
		$current_status = isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : 'all';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_temperature = isset( $_REQUEST['temperature'] ) ? sanitize_key( wp_unslash( $_REQUEST['temperature'] ) ) : '';
		$base_url            = admin_url( 'admin.php?page=promptor-submissions' );
		$views               = array(
			'all' => sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
				esc_url( $base_url ),
				esc_attr( 'all' === $current_status && empty( $current_temperature ) ? 'current' : '' ),
				esc_html__( 'All', 'promptor' ),
				esc_html( number_format_i18n( (int) $total_items ) )
			),
		);

		foreach ( $this->status_counts as $status => $count ) {
			if ( $count > 0 || $current_status === $status ) {
				$status_label = '';
				switch ( $status ) {
					case 'pending':
						$status_label = __( 'Pending', 'promptor' );
						break;
					case 'contacted':
						$status_label = __( 'Contacted', 'promptor' );
						break;
					case 'converted':
						$status_label = __( 'Converted', 'promptor' );
						break;
					case 'rejected':
						$status_label = __( 'Rejected', 'promptor' );
						break;
				}
				$views[ $status ] = sprintf(
					'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
					esc_url( add_query_arg( 'status', $status, $base_url ) ),
					esc_attr( $current_status === $status && empty( $current_temperature ) ? 'current' : '' ),
					esc_html( $status_label ),
					esc_html( number_format_i18n( (int) $count ) )
				);
			}
		}

		// Add temperature filters (Pro only).
		if ( function_exists( 'promptor_is_pro' ) && promptor_is_pro() ) {
			$temperatures = array(
				'hot'  => array(
					'label' => __( 'Hot Leads', 'promptor' ),
					'icon'  => '🔥',
				),
				'warm' => array(
					'label' => __( 'Warm Leads', 'promptor' ),
					'icon'  => '⚡',
				),
				'cold' => array(
					'label' => __( 'Cold Leads', 'promptor' ),
					'icon'  => '❄️',
				),
			);

			foreach ( $temperatures as $temp_key => $temp_data ) {
				$views[ 'temp_' . $temp_key ] = sprintf(
					'<a href="%s" class="%s">%s %s</a>',
					esc_url( add_query_arg( 'temperature', $temp_key, $base_url ) ),
					esc_attr( $current_temperature === $temp_key ? 'current' : '' ),
					$temp_data['icon'],
					esc_html( $temp_data['label'] )
				);
			}
		}

		return $views;
	}

	/**
	 * Prepare items for display.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function prepare_items() {
		$this->process_bulk_action();
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$per_page              = $this->get_items_per_page( 'submissions_per_page', 20 );

		// Free version: limit to 5 most recent submissions.
		$is_premium = ( function_exists( 'promptor_fs' ) && method_exists( promptor_fs(), 'can_use_premium_code__premium_only' ) )
			? (bool) promptor_fs()->can_use_premium_code__premium_only()
			: false;
		if ( ! $is_premium ) {
			$per_page = 5;
		}

		$current_page = $this->get_pagenum();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search_term = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : null;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : null;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$temperature = isset( $_REQUEST['temperature'] ) ? sanitize_key( wp_unslash( $_REQUEST['temperature'] ) ) : null;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'submitted_at';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order = isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'DESC';

		$total_items = self::record_count( $search_term, $status, $temperature );
		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
			)
		);
		$this->items = self::get_submissions( $per_page, $current_page, $search_term, $status, $temperature, $orderby, $order );
	}

	/**
	 * Retrieve submissions from the database.
	 *
	 * @since 1.0.0
	 * @param int         $per_page    Number of submissions per page.
	 * @param int         $page_number Current page number.
	 * @param string|null $search_term Search term for filtering.
	 * @param string|null $status      Status filter.
	 * @param string|null $temperature Temperature filter (hot/warm/cold).
	 * @param string      $orderby     Column to order by.
	 * @param string      $order       Order direction (ASC/DESC).
	 * @return array Array of submission records.
	 */
	public static function get_submissions( $per_page = 20, $page_number = 1, $search_term = null, $status = null, $temperature = null, $orderby = 'submitted_at', $order = 'DESC' ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'promptor_submissions';
		$safe_table = esc_sql( $table_name );

		// Normalize pagination inputs.
		$per_page    = max( 1, absint( $per_page ) );
		$page_number = max( 1, absint( $page_number ) );
		$offset      = ( $page_number - 1 ) * $per_page;

		$params = array();

		// Construct query parts.
		$sql = "SELECT * FROM {$safe_table} WHERE 1=1";

		if ( $status && 'all' !== $status ) {
			$sql     .= ' AND status = %s';
			$params[] = $status;
		}

		if ( $search_term ) {
			$sql     .= ' AND (name LIKE %s OR email LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $search_term ) . '%';
			$params[] = $like;
			$params[] = $like;
		}

		// Temperature filter (Pro only - filter by JSON field in meta).
		if ( $temperature && in_array( $temperature, array( 'hot', 'warm', 'cold' ), true ) ) {
			$sql .= ' AND meta LIKE %s';
			// Match "lead_temperature":"hot" pattern in JSON.
			$params[] = '%"lead_temperature":"' . $temperature . '"%';
		}

		// Ordering.
		$allowed_orderby = array( 'name', 'status', 'submitted_at', 'score' );
		$orderby         = in_array( $orderby, $allowed_orderby, true ) ? $orderby : 'submitted_at';
		$order           = ( 'ASC' === strtoupper( $order ) ) ? 'ASC' : 'DESC';

		// Score ordering requires extracting from JSON (Pro only).
		if ( 'score' === $orderby ) {
			// Use JSON extraction for MySQL 5.7+.
			// For compatibility, we'll fetch all and sort in PHP for score.
			$sql .= ' ORDER BY submitted_at DESC';
		} else {
			$sql .= " ORDER BY {$orderby} {$order}";
		}

		$sql .= ' LIMIT %d OFFSET %d';

		$params[] = $per_page;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$results = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

		if ( ! is_array( $results ) ) {
			return array();
		}

		// Sort by score in PHP if needed (extract from meta JSON).
		if ( 'score' === $orderby ) {
			usort(
				$results,
				function ( $a, $b ) use ( $order ) {
					$score_a = self::get_lead_score_from_meta( $a['meta'] ?? '' );
					$score_b = self::get_lead_score_from_meta( $b['meta'] ?? '' );

					if ( 'ASC' === $order ) {
						return $score_a <=> $score_b;
					} else {
						return $score_b <=> $score_a;
					}
				}
			);
		}

		return $results;
	}

	/**
	 * Get the total number of submissions matching the current filters.
	 *
	 * @since 1.0.0
	 * @param string|null $search_term Search term for filtering.
	 * @param string|null $status      Status filter.
	 * @param string|null $temperature Temperature filter (hot/warm/cold).
	 * @return int Total number of records.
	 */
	public static function record_count( $search_term = null, $status = null, $temperature = null ) {
		global $wpdb;

		// Cache key based on parameters.
		$cache_key = 'promptor_submissions_count_' . md5( serialize( array( $search_term, $status, $temperature ) ) );
		$count     = wp_cache_get( $cache_key, 'promptor' );

		if ( false !== $count ) {
			return (int) $count;
		}

		$table_name = $wpdb->prefix . 'promptor_submissions';
		$safe_table = esc_sql( $table_name );

		$params = array();
		$sql    = "SELECT COUNT(*) FROM {$safe_table} WHERE 1=1";

		if ( $status && 'all' !== $status ) {
			$sql     .= ' AND status = %s';
			$params[] = $status;
		}

		if ( $search_term ) {
			$sql     .= ' AND (name LIKE %s OR email LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $search_term ) . '%';
			$params[] = $like;
			$params[] = $like;
		}

		// Temperature filter.
		if ( $temperature && in_array( $temperature, array( 'hot', 'warm', 'cold' ), true ) ) {
			$sql     .= ' AND meta LIKE %s';
			$params[] = '%"lead_temperature":"' . $temperature . '"%';
		}

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$count = $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$count = $wpdb->get_var( $sql );
		}

		$count_int = (int) $count;

		// Cache for 5 minutes.
		wp_cache_set( $cache_key, $count_int, 'promptor', 5 * MINUTE_IN_SECONDS );

		return $count_int;
	}

	/**
	 * Define the columns for the table.
	 *
	 * @since 1.0.0
	 * @return array Columns array.
	 */
	public function get_columns() {
		$columns = array(
			'cb' => '<input type="checkbox" />',
		);

		// Add score column (Pro only).
		if ( function_exists( 'promptor_is_pro' ) && promptor_is_pro() ) {
			$columns['score'] = __( 'Score', 'promptor' );
		}

		$columns['name']            = __( 'Submitter', 'promptor' );
		$columns['contact']         = __( 'Contact Details', 'promptor' );
		$columns['original_query']  = __( 'Original Query', 'promptor' );
		$columns['recommendations'] = __( 'Selected Services', 'promptor' );
		$columns['status']          = __( 'Status', 'promptor' );
		$columns['submitted_at']    = __( 'Date', 'promptor' );

		return $columns;
	}

	/**
	 * Define sortable columns.
	 *
	 * @since 1.0.0
	 * @return array Sortable columns.
	 */
	public function get_sortable_columns() {
		$sortable = array(
			'name'         => array( 'name', false ),
			'submitted_at' => array( 'submitted_at', true ),
			'status'       => array( 'status', false ),
		);

		// Add score sorting (Pro only).
		if ( function_exists( 'promptor_is_pro' ) && promptor_is_pro() ) {
			$sortable['score'] = array( 'score', true ); // Default descending (highest first).
		}

		return $sortable;
	}

	/**
	 * Define bulk actions.
	 *
	 * @since 1.0.0
	 * @return array Bulk actions.
	 */
	protected function get_bulk_actions() {
		return array(
			'bulk-contacted' => __( 'Mark as Contacted', 'promptor' ),
			'bulk-converted' => __( 'Mark as Converted', 'promptor' ),
			'bulk-rejected'  => __( 'Mark as Rejected', 'promptor' ),
			'bulk-pending'   => __( 'Mark as Pending', 'promptor' ),
			'bulk-delete'    => __( 'Delete', 'promptor' ),
		);
	}

	/**
	 * Process bulk actions.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function process_bulk_action() {
		$action = $this->current_action();
		if ( ! $action ) {
			return;
		}

		// Authorization check.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'promptor' ) );
		}

		// Nonce verification.
		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ) {
			wp_die( esc_html__( 'Security check failed.', 'promptor' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array is sanitized with array_map below.
		$ids = ( isset( $_REQUEST['submission'] ) && is_array( $_REQUEST['submission'] ) )
			? array_map( 'absint', wp_unslash( $_REQUEST['submission'] ) )
			: array();
		if ( empty( $ids ) ) {
			return;
		}

		$status_map = array(
			'bulk-contacted' => 'contacted',
			'bulk-converted' => 'converted',
			'bulk-rejected'  => 'rejected',
			'bulk-pending'   => 'pending',
		);
		if ( isset( $status_map[ $action ] ) ) {
			self::update_status( $ids, $status_map[ $action ] );
			wp_cache_delete( 'promptor_submission_status_counts', 'promptor' );
		}
		if ( 'bulk-delete' === $action ) {
			self::delete_submission( $ids );
			wp_cache_delete( 'promptor_submission_status_counts', 'promptor' );
		}
	}

	/**
	 * Delete submission(s) from the database.
	 *
	 * @since 1.0.0
	 * @param int|array $ids Submission ID(s) to delete.
	 * @return void
	 */
	public static function delete_submission( $ids ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'promptor_submissions';

		// Extra safety: table name should be a safe identifier.
		if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table_name ) ) {
			return;
		}

		// Normalize IDs to a clean, non-zero int array.
		$ids = array_filter( array_map( 'absint', (array) $ids ) );
		if ( empty( $ids ) ) {
			return;
		}

		foreach ( $ids as $id ) {
			// $wpdb->delete() is a WP wrapper; caching is not applicable for DELETE.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete(
				$table_name,
				array( 'id' => $id ),
				array( '%d' )
			);

			// Future: submission-specific cache can be cleared here.
		}
	}


	/**
	 * Update the status of submission(s).
	 *
	 * @since 1.0.0
	 * @param int|array $ids    Submission ID(s) to update.
	 * @param string    $status New status value.
	 * @return void
	 */
	public static function update_status( $ids, $status ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'promptor_submissions';
		$safe_table = esc_sql( $table_name );

		$ids = is_array( $ids ) ? array_map( 'absint', $ids ) : array( absint( $ids ) );
		if ( ! in_array( $status, array( 'pending', 'contacted', 'converted', 'rejected' ), true ) || empty( $ids ) ) {
			return;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$prepare_args = array_merge( array( $status ), $ids );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "UPDATE {$safe_table} SET status = %s WHERE id IN ({$placeholders})", $prepare_args ) );
	}

	/**
	 * Render the checkbox column.
	 *
	 * @since 1.0.0
	 * @param array $item Current item data.
	 * @return string Checkbox HTML.
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="submission[]" value="%s" />', esc_attr( $item['id'] ) );
	}

	/**
	 * Render the name column.
	 *
	 * @since 1.0.0
	 * @param array $item Current item data.
	 * @return string Name HTML.
	 */
	public function column_name( $item ) {
		return '<strong>' . esc_html( $item['name'] ) . '</strong>';
	}

	/**
	 * Render the contact column.
	 *
	 * @since 1.0.0
	 * @param array $item Current item data.
	 * @return string Contact details HTML.
	 */
	public function column_contact( $item ) {
		$email_attr = ! empty( $item['email'] ) && is_email( $item['email'] ) ? sanitize_email( $item['email'] ) : '';
		$email_text = $email_attr ? $email_attr : '';
		$email_link = $email_attr
			? sprintf( '<a href="mailto:%1$s">%2$s</a>', esc_attr( $email_attr ), esc_html( $email_text ) )
			: esc_html__( 'Email hidden', 'promptor' );

		$phone_text = ! empty( $item['phone'] ) ? esc_html( $item['phone'] ) : '';

		$notes_html = ! empty( $item['notes'] )
			? '<br><small><strong>' . esc_html__( 'Notes', 'promptor' ) . ':</strong> ' . esc_html( $item['notes'] ) . '</small>'
			: '';

		return sprintf( '%s<br>%s%s', $email_link, $phone_text, wp_kses_post( $notes_html ) );
	}

	/**
	 * Render the original query column with view details button.
	 *
	 * @since 1.0.0
	 * @param array $item Current item data.
	 * @return string Query details button HTML.
	 */
	public function column_original_query( $item ) {
		if ( empty( $item['query_id'] ) || (int) $item['query_id'] <= 0 ) {
			return '—';
		}

		global $wpdb;

		$table_name = $wpdb->prefix . 'promptor_queries';
		$query_id   = absint( $item['query_id'] );

		// Validate table identifier (only letters, numbers, underscore).
		if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table_name ) ) {
			return '—';
		}

		// Short-term cache.
		$cache_key   = 'promptor_query_' . $query_id;
		$cache_group = 'promptor';
		$query_data  = wp_cache_get( $cache_key, $cache_group );

		if ( false === $query_data ) {

			$sql = $wpdb->prepare(
			// Table name is a validated identifier, safe to interpolate.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT user_query, ai_response_raw FROM {$table_name} WHERE id = %d",
				$query_id
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$query_data = $wpdb->get_row( $sql );

			// 10 min cache.
			wp_cache_set( $cache_key, $query_data, $cache_group, 10 * MINUTE_IN_SECONDS );
		}

		if ( ! $query_data ) {
			return '—';
		}

		return sprintf(
			'<button type="button" class="button button-small promptor-view-details" data-query="%1$s" data-full_ai_response="%2$s">%3$s</button>',
			esc_attr( (string) $query_data->user_query ),
			esc_attr( (string) $query_data->ai_response_raw ),
			esc_html__( 'View Details', 'promptor' )
		);
	}


	/**
	 * Display the list table with search box and modal.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function display() {
		$this->search_box( esc_html__( 'Search Submissions', 'promptor' ), 'promptor-submission-search' );
		parent::display();
		?>
		<div id="promptor-details-modal" style="display:none;">
			<div class="promptor-modal-content">
				<h2><?php esc_html_e( 'Submission Details', 'promptor' ); ?></h2>
				<table class="form-table promptor-details-table">
					<tr>
						<th><?php esc_html_e( 'User\'s Query', 'promptor' ); ?></th>
						<td id="modal-query"></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Full AI Response', 'promptor' ); ?></th>
						<td><pre id="modal-full_ai_response" class="promptor-log-viewer"></pre></td>
					</tr>
				</table>
				<button type="button" class="button modal-close"><?php esc_html_e( 'Close', 'promptor' ); ?></button>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the status column with interactive dropdown.
	 *
	 * @since 1.0.0
	 * @param array $item Current item data.
	 * @return string Status selector HTML.
	 */
	public function column_status( $item ) {
		$statuses       = array(
			'pending'   => array(
				'label' => __( 'Pending', 'promptor' ),
				'icon'  => 'dashicons-clock',
			),
			'contacted' => array(
				'label' => __( 'Contacted', 'promptor' ),
				'icon'  => 'dashicons-phone',
			),
			'converted' => array(
				'label' => __( 'Converted', 'promptor' ),
				'icon'  => 'dashicons-yes-alt',
			),
			'rejected'  => array(
				'label' => __( 'Rejected', 'promptor' ),
				'icon'  => 'dashicons-dismiss',
			),
		);
		$current_status = isset( $statuses[ $item['status'] ] ) ? $item['status'] : 'pending';
		$output         = '<div class="promptor-status-selector status-is-' . esc_attr( $current_status ) . '" data-id="' . esc_attr( $item['id'] ) . '">';
		$output        .= '<span class="dashicons ' . esc_attr( $statuses[ $current_status ]['icon'] ) . '"></span>';
		$output        .= '<span class="current-status">' . esc_html( $statuses[ $current_status ]['label'] ) . '</span>';
		$output        .= '<div class="status-dropdown">';
		foreach ( $statuses as $key => $value ) {
			$output .= sprintf(
				'<a href="#" class="status-option" data-status="%s"><span class="dashicons %s"></span> %s</a>',
				esc_attr( $key ),
				esc_attr( $value['icon'] ),
				esc_html( $value['label'] )
			);
		}
		$output .= '</div><span class="spinner"></span></div>';
		return $output;
	}

	/**
	 * Default column rendering.
	 *
	 * @since 1.0.0
	 * @param array  $item        Current item data.
	 * @param string $column_name Current column name.
	 * @return string Column content.
	 */
	public function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
	}

	/**
	 * Render the recommendations column.
	 *
	 * @since 1.0.0
	 * @param array $item Current item data.
	 * @return string Trimmed recommendations text.
	 */
	public function column_recommendations( $item ) {
		return esc_html( wp_trim_words( $item['recommendations'], 15, '...' ) );
	}

	/**
	 * Render the score column (Pro only).
	 *
	 * @since 1.2.0
	 * @param array $item Current item data.
	 * @return string Score HTML with temperature icon.
	 */
	public function column_score( $item ) {
		$meta = isset( $item['meta'] ) ? $item['meta'] : '';

		if ( empty( $meta ) ) {
			return '<span class="promptor-text-muted">—</span>';
		}

		$meta_data = json_decode( $meta, true );

		if ( ! is_array( $meta_data ) || ! isset( $meta_data['scoring'] ) ) {
			return '<span class="promptor-text-muted">—</span>';
		}

		$scoring     = $meta_data['scoring'];
		$score       = isset( $scoring['lead_score'] ) ? absint( $scoring['lead_score'] ) : 0;
		$temperature = isset( $scoring['lead_temperature'] ) ? sanitize_text_field( $scoring['lead_temperature'] ) : 'cold';
		$confidence  = isset( $scoring['confidence_level'] ) ? sanitize_text_field( $scoring['confidence_level'] ) : 'low';

		// Temperature icons and colors.
		$temp_config = array(
			'hot'  => array(
				'icon'  => '🔥',
				'color' => '#d63638',
				'bg'    => '#ffebee',
			),
			'warm' => array(
				'icon'  => '⚡',
				'color' => '#dba617',
				'bg'    => '#fff8e1',
			),
			'cold' => array(
				'icon'  => '❄️',
				'color' => '#2271b1',
				'bg'    => '#e8f4f8',
			),
		);

		$config = isset( $temp_config[ $temperature ] ) ? $temp_config[ $temperature ] : $temp_config['cold'];

		// Confidence indicator.
		$confidence_icon = '';
		if ( 'high' === $confidence ) {
			$confidence_icon = ' <span title="' . esc_attr__( 'High Confidence', 'promptor' ) . '">✅</span>';
		} elseif ( 'medium' === $confidence ) {
			$confidence_icon = ' <span title="' . esc_attr__( 'Medium Confidence', 'promptor' ) . '">⚠️</span>';
		} else {
			$confidence_icon = ' <span title="' . esc_attr__( 'Low Confidence', 'promptor' ) . '">⚡</span>';
		}

		// Build score display with breakdown toggle.
		$output  = '<div class="promptor-score-wrapper">';
		$output .= sprintf(
			'<div class="promptor-lead-score" style="padding: 6px 10px; border-radius: 4px; background: %s; color: %s; font-weight: 600; text-align: center; display: inline-block; min-width: 60px;">%s %d%s</div>',
			esc_attr( $config['bg'] ),
			esc_attr( $config['color'] ),
			$config['icon'],
			$score,
			$confidence_icon
		);

		// Add breakdown toggle button.
		$row_id  = 'breakdown-row-' . absint( $item['id'] );
		$output .= sprintf(
			' <button type="button" class="button button-small promptor-score-toggle" data-row-id="%s" aria-expanded="false" aria-controls="%s" style="margin-left: 5px; vertical-align: middle;">ℹ️ %s <span class="dashicons dashicons-arrow-down" style="font-size: 14px; width: 14px; height: 14px;"></span><span class="dashicons dashicons-arrow-up" style="font-size: 14px; width: 14px; height: 14px;"></span></button>',
			esc_attr( $row_id ),
			esc_attr( $row_id ),
			esc_html__( 'Why?', 'promptor' )
		);

		$output .= '</div>';

		return $output;
	}

	/**
	 * Override single_row to add breakdown row after each item.
	 *
	 * @since 1.2.0
	 * @param array $item Current item.
	 * @return void
	 */
	public function single_row( $item ) {
		echo '<tr id="row-' . absint( $item['id'] ) . '">';
		$this->single_row_columns( $item );
		echo '</tr>';

		// Add breakdown row if scoring data exists (Pro only).
		if ( function_exists( 'promptor_is_pro' ) && promptor_is_pro() ) {
			$meta = isset( $item['meta'] ) ? $item['meta'] : '';
			if ( ! empty( $meta ) ) {
				$meta_data = json_decode( $meta, true );
				if ( is_array( $meta_data ) && isset( $meta_data['scoring'] ) ) {
					$this->render_breakdown_row( $item, $meta_data['scoring'] );
				}
			}
		}
	}

	/**
	 * Render score breakdown as a separate table row.
	 *
	 * @since 1.2.0
	 * @param array $item Current item.
	 * @param array $scoring Scoring data.
	 * @return void
	 */
	private function render_breakdown_row( $item, $scoring ) {
		$row_id    = 'breakdown-row-' . absint( $item['id'] );
		$col_count = count( $this->get_columns() );
		$breakdown = $this->generate_score_breakdown_html( $scoring );

		?>
		<tr id="<?php echo esc_attr( $row_id ); ?>" class="promptor-score-breakdown-row" aria-hidden="true">
			<td colspan="<?php echo absint( $col_count ); ?>" class="promptor-score-breakdown-cell">
				<div class="promptor-score-breakdown-content">
					<?php echo $breakdown; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in generate_score_breakdown_html. ?>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Generate score breakdown HTML
	 *
	 * @since 1.2.0
	 * @param array $scoring Scoring data.
	 * @return string Breakdown HTML.
	 */
	private function generate_score_breakdown_html( $scoring ) {
		// Extract scoring data.
		$scoring_factors = isset( $scoring['scoring_factors'] ) ? $scoring['scoring_factors'] : array();
		$scoring_details = isset( $scoring['scoring_details'] ) ? $scoring['scoring_details'] : array();
		$signals         = isset( $scoring_details['signals_detected'] ) ? $scoring_details['signals_detected'] : array();
		$lead_score      = isset( $scoring['lead_score'] ) ? absint( $scoring['lead_score'] ) : 0;
		$temperature     = isset( $scoring['lead_temperature'] ) ? sanitize_text_field( $scoring['lead_temperature'] ) : 'cold';

		$html = '<div style="font-size: 13px; line-height: 1.6;">';

		// Info line at top.
		$html .= '<div style="margin-bottom: 12px; padding: 8px 12px; background: #f0f6fc; border-left: 3px solid #2271b1; border-radius: 3px;">';
		$html .= '<span class="dashicons dashicons-info" style="color: #2271b1; font-size: 14px; vertical-align: middle; margin-right: 5px;"></span>';
		/* translators: %1$d: Lead score value, %2$s: Temperature classification (Hot/Warm/Cold) */
		$html .= sprintf( esc_html__( 'This lead scored %1$d/105 points (%2$s). The score is calculated from 4 factors and detected signals.', 'promptor' ), $lead_score, '<strong>' . esc_html( ucfirst( $temperature ) ) . '</strong>' );
		$html .= '</div>';

		// 2-column responsive layout.
		$html .= '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">';
		$html .= '@media (max-width: 768px) { .promptor-score-breakdown-content > div > div { grid-template-columns: 1fr !important; } }';

		// LEFT COLUMN: Factor Totals.
		$html .= '<div>';
		$html .= '<div style="margin-bottom: 8px;">';
		$html .= '<span class="dashicons dashicons-analytics" style="color: #2271b1; font-size: 14px; vertical-align: middle; margin-right: 3px;"></span>';
		$html .= '<strong style="font-size: 13px;">' . esc_html__( 'Factor Totals', 'promptor' ) . '</strong>';
		$html .= '</div>';

		$factor_labels = array(
			'intent_strength'   => array(
				'label' => __( 'Intent Strength', 'promptor' ),
				'icon'  => 'dashicons-heart',
			),
			'budget_indicators' => array(
				'label' => __( 'Budget Indicators', 'promptor' ),
				'icon'  => 'dashicons-money-alt',
			),
			'timeline_urgency'  => array(
				'label' => __( 'Timeline Urgency', 'promptor' ),
				'icon'  => 'dashicons-clock',
			),
			'service_match'     => array(
				'label' => __( 'Service Match', 'promptor' ),
				'icon'  => 'dashicons-yes-alt',
			),
		);

		$html .= '<div style="background: #f6f7f7; padding: 12px; border-radius: 3px; border-left: 3px solid #2271b1;">';
		foreach ( $factor_labels as $key => $factor_data ) {
			$points = isset( $scoring_factors[ $key ] ) ? absint( $scoring_factors[ $key ] ) : 0;
			if ( $points > 0 ) {
				$html .= sprintf(
					'<div style="padding: 4px 0;"><span class="dashicons %s" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle; color: #0a7d0a; margin-right: 3px;"></span>%s: <strong style="color: #0a7d0a;">+%d points</strong></div>',
					esc_attr( $factor_data['icon'] ),
					esc_html( $factor_data['label'] ),
					$points
				);
			}
		}
		$html .= '</div></div>';

		// RIGHT COLUMN: Signals Detected.
		$html .= '<div>';
		$html .= '<div style="margin-bottom: 8px;">';
		$html .= '<span class="dashicons dashicons-list-view" style="color: #2271b1; font-size: 14px; vertical-align: middle; margin-right: 3px;"></span>';
		$html .= '<strong style="font-size: 13px;">' . esc_html__( 'Signals Detected', 'promptor' ) . '</strong>';
		$html .= '</div>';

		if ( ! empty( $signals ) ) {
			$signal_labels = array(
				'clear_purchase_intent'       => __( 'Clear Purchase Intent (hire, buy, etc.)', 'promptor' ),
				'asked_about_pricing'         => __( 'Asked About Pricing', 'promptor' ),
				'detailed_project_discussion' => __( 'Detailed Project Discussion', 'promptor' ),
				'multi_service_selection'     => __( 'Multi-Service Selection (2-3 services)', 'promptor' ),
				'provided_phone'              => __( 'Provided Phone Number', 'promptor' ),
				'provided_email'              => __( 'Provided Email Address', 'promptor' ),
				'mentioned_budget_range'      => __( 'Mentioned Budget Range', 'promptor' ),
				'mentioned_budget_exists'     => __( 'Mentioned Budget Exists', 'promptor' ),
				'discussed_payment_terms'     => __( 'Discussed Payment Terms', 'promptor' ),
				'asked_premium_options'       => __( 'Asked About Premium Options', 'promptor' ),
				'budget_concerns'             => __( 'Budget Concerns (cheap, affordable)', 'promptor' ),
				'timeline_asap'               => __( 'Timeline: ASAP (<2 weeks)', 'promptor' ),
				'timeline_soon'               => __( 'Timeline: Soon (2-4 weeks)', 'promptor' ),
				'timeline_1_3_months'         => __( 'Timeline: 1-3 Months', 'promptor' ),
				'specific_deadline'           => __( 'Specific Deadline Mentioned', 'promptor' ),
				'1_service_selected'          => __( '1 Service Selected (Clear Need)', 'promptor' ),
				'2-3_services_selected'       => __( '2-3 Services Selected (Related Needs)', 'promptor' ),
				'4+_services_selected'        => __( '4+ Services Selected (Unclear Needs)', 'promptor' ),
			);

			$html .= '<div style="background: #fff; padding: 10px; border: 1px solid #ddd; border-radius: 3px;">';
			foreach ( $signals as $signal_data ) {
				$signal_name = isset( $signal_data['signal'] ) ? $signal_data['signal'] : '';
				$points      = isset( $signal_data['points'] ) ? intval( $signal_data['points'] ) : 0;
				$label       = isset( $signal_labels[ $signal_name ] ) ? $signal_labels[ $signal_name ] : ucwords( str_replace( '_', ' ', $signal_name ) );

				$icon_style = $points > 0 ? 'color: #0a7d0a;' : 'color: #d63638;';
				$icon       = $points > 0 ? 'dashicons-yes' : 'dashicons-minus';

				$html .= sprintf(
					'<div style="padding: 6px 0; border-bottom: 1px solid #f0f0f0;"><span class="dashicons %s" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle; %s margin-right: 5px;"></span><span style="%s">%s: <strong>%s%d</strong> points</span></div>',
					esc_attr( $icon ),
					esc_attr( $icon_style ),
					esc_attr( $icon_style ),
					esc_html( $label ),
					$points > 0 ? '+' : '',
					$points
				);

				// Show budget range if available.
				if ( 'mentioned_budget_range' === $signal_name && isset( $signal_data['budget_min'] ) ) {
					$budget_min = absint( $signal_data['budget_min'] );
					$budget_max = isset( $signal_data['budget_max'] ) ? absint( $signal_data['budget_max'] ) : $budget_min;

					$html .= sprintf(
						'<div style="padding: 4px 0 8px 25px; font-size: 12px; color: #666;"><span class="dashicons dashicons-money" style="font-size: 12px; width: 12px; height: 12px; vertical-align: middle; margin-right: 3px;"></span>%s: <strong>$%s - $%s</strong></div>',
						esc_html__( 'Budget Range', 'promptor' ),
						esc_html( number_format( $budget_min ) ),
						esc_html( number_format( $budget_max ) )
					);
				}
			}
			$html .= '</div>';
		} else {
			$html .= '<div style="background: #f6f7f7; padding: 12px; border-radius: 3px; color: #666; text-align: center;">';
			$html .= '<span class="dashicons dashicons-warning" style="font-size: 16px; vertical-align: middle; margin-right: 5px;"></span>';
			$html .= esc_html__( 'No signals detected (minimal information provided)', 'promptor' );
			$html .= '</div>';
		}

		$html .= '</div>'; // Close right column.
		$html .= '</div>'; // Close 2-column grid.
		$html .= '</div>'; // Close outer wrapper.

		return $html;
	}

	/**
	 * Extract lead score from meta JSON
	 *
	 * @since 1.2.0
	 * @param string $meta Meta JSON string.
	 * @return int Lead score (0 if not found).
	 */
	private static function get_lead_score_from_meta( $meta ) {
		if ( empty( $meta ) ) {
			return 0;
		}

		$meta_data = json_decode( $meta, true );

		if ( ! is_array( $meta_data ) || ! isset( $meta_data['scoring']['lead_score'] ) ) {
			return 0;
		}

		return absint( $meta_data['scoring']['lead_score'] );
	}
}