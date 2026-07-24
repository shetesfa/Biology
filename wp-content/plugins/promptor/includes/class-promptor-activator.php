<?php
/**
 * Promptor Plugin Activator
 *
 * Handles plugin activation tasks including database table creation,
 * option initialization, and schema updates.
 *
 * @package Promptor
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Promptor_Activator
 *
 * Manages plugin activation procedures.
 */
class Promptor_Activator {

	/**
	 * Activate the plugin.
	 *
	 * Creates/updates database tables, initializes options,
	 * and sets activation redirect transient.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_or_update_tables();
		self::setup_options();
		set_transient( 'promptor_activation_redirect', true, 30 );
	}

	/**
	 * Setup initial plugin options.
	 *
	 * Initializes default options including contexts, limits,
	 * and various settings arrays.
	 *
	 * @return void
	 */
	private static function setup_options() {
		$end_of_month = wp_date( 'Y-m-t', current_time( 'timestamp' ) );

		add_option( 'promptor_db_version', defined( 'PROMPTOR_VERSION' ) ? PROMPTOR_VERSION : '1.0.0' );
		add_option( 'promptor_query_count', 0 );
		add_option( 'promptor_limit_reset_date', $end_of_month );
		add_option( 'promptor_activation_time', time() ); // Track activation time (v1.2.3)
		add_option(
			'promptor_contexts',
			array(
				'default' => array(
					'name'        => 'Default',
					'source_type' => 'manual',
					'settings'    => array(
						'selected_pages'    => array(),
						'selected_files'    => array(),
						'example_questions' => '',
					),
				),
			),
			'',
			'no'
		);

		self::ensure_default_context_exists();

		self::maybe_add_option_noautoload( 'promptor_api_settings', array(
			'system_prompt'         => "You are a professional, helpful, and friendly sales assistant for this website. Your main goal is to understand the user's needs and, based ONLY on the context provided to you, recommend the most relevant services or products from the context.\n\nKey instructions:\n- Always be polite and professional.\n- Your answers must be based strictly on the provided context. Do not use any external knowledge.\n- If the answer is not in the context, state that you don't have information on that topic.\n- Keep your explanations concise and to the point.",
			'summarization_prompt'  => 'Briefly summarize the key points of the preceding conversation.',
		) );
		self::maybe_add_option_noautoload( 'promptor_ui_settings', array() );
		self::maybe_add_option_noautoload( 'promptor_crawler_settings', array() );
		self::maybe_add_option_noautoload( 'promptor_notification_settings', array() );
	}

	/**
	 * Ensure the default context exists with proper structure.
	 *
	 * Validates and repairs the default context configuration
	 * if it's missing or malformed.
	 *
	 * @return void
	 */
	private static function ensure_default_context_exists() {
		$contexts = get_option( 'promptor_contexts', array() );
		
		if ( ! is_array( $contexts ) ) {
			$contexts = array();
		}
		
		if ( ! isset( $contexts['default'] ) || ! is_array( $contexts['default'] ) ) {
			$contexts['default'] = array();
		}
		
		if ( ! isset( $contexts['default']['name'] ) ) {
			$contexts['default']['name'] = 'Default';
		}
		
		if ( ! isset( $contexts['default']['source_type'] ) ) {
			$contexts['default']['source_type'] = 'manual';
		}
		
		if ( ! isset( $contexts['default']['settings'] ) || ! is_array( $contexts['default']['settings'] ) ) {
			$contexts['default']['settings'] = array();
		}
		
		if ( ! array_key_exists( 'selected_pages', $contexts['default']['settings'] ) ) {
			$contexts['default']['settings']['selected_pages'] = array();
		}
		
		if ( ! array_key_exists( 'selected_files', $contexts['default']['settings'] ) ) {
			$contexts['default']['settings']['selected_files'] = array();
		}
		
		if ( ! array_key_exists( 'example_questions', $contexts['default']['settings'] ) ) {
			$contexts['default']['settings']['example_questions'] = '';
		}
		
		$old = get_option( 'promptor_contexts', array() );
		
		if ( $old !== $contexts ) {
			update_option( 'promptor_contexts', $contexts );
		}
	}

	/**
	 * Add an option with autoload disabled if it doesn't exist.
	 *
	 * @param string $option_name   The option name to add.
	 * @param mixed  $default_value The default value for the option.
	 * @return void
	 */
	private static function maybe_add_option_noautoload( $option_name, $default_value ) {
		$sentinel = new stdClass();
		$existing = get_option( $option_name, $sentinel );
		
		if ( $existing === $sentinel ) {
			add_option( $option_name, $default_value, '', 'no' );
		}
	}

	/**
	 * Create or update database tables.
	 *
	 * Creates the plugin's custom tables and performs schema updates
	 * for existing installations.
	 *
	 * @return void
	 */
	private static function create_or_update_tables() {
		global $wpdb;
		
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Create submissions table.
		$submissions_table = $wpdb->prefix . 'promptor_submissions';
		$sql_submissions   = "CREATE TABLE {$submissions_table} (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			name varchar(100) NOT NULL,
			email varchar(100) NOT NULL,
			phone varchar(50) DEFAULT '' NOT NULL,
			recommendations text NOT NULL,
			notes text,
			query_id bigint(20) DEFAULT 0 NOT NULL,
			submitted_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			status varchar(20) DEFAULT 'pending' NOT NULL,
			is_demo tinyint(1) DEFAULT 0 NOT NULL,
			linked_order_id bigint(20) DEFAULT 0,
			order_value decimal(10,2) DEFAULT NULL,
			source varchar(50) DEFAULT '' NOT NULL,
			meta longtext,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY query_id (query_id),
			KEY is_demo (is_demo),
			KEY linked_order_id (linked_order_id),
			KEY idx_source (source)
		) {$charset_collate};";
		
		dbDelta( $sql_submissions );

		// Add missing columns to submissions table.
		self::maybe_add_column( $submissions_table, 'source', "ALTER TABLE {$submissions_table} ADD COLUMN source varchar(50) DEFAULT '' NOT NULL" );
		self::maybe_add_column( $submissions_table, 'meta', "ALTER TABLE {$submissions_table} ADD COLUMN meta longtext" );
		self::maybe_add_index( $submissions_table, 'idx_source', "ALTER TABLE {$submissions_table} ADD KEY idx_source (source)" );

		// Create queries table.
		$queries_table = $wpdb->prefix . 'promptor_queries';
		$sql_queries   = "CREATE TABLE {$queries_table} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_query text NOT NULL,
			ai_response_raw longtext,
			suggested_service_titles text,
			products_added_to_cart text,
			is_demo tinyint(1) DEFAULT 0 NOT NULL,
			is_archived tinyint(1) DEFAULT 0 NOT NULL,
			linked_order_id bigint(20) DEFAULT 0,
			order_value decimal(10,2) DEFAULT NULL,
			query_timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			status_code smallint(5) unsigned DEFAULT 0,
			feedback tinyint(1) DEFAULT NULL,
			response_time_ms int(11) DEFAULT NULL,
			similarity_score double DEFAULT NULL,
			tokens_used int(11) DEFAULT NULL,
			query_cost decimal(10,6) DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY query_timestamp (query_timestamp),
			KEY is_demo (is_demo),
			KEY is_archived (is_archived),
			KEY linked_order_id (linked_order_id)
		) {$charset_collate};";
		
		dbDelta( $sql_queries );

		// Create embeddings table.
		$emb_table      = $wpdb->prefix . 'promptor_embeddings';
		$sql_embeddings = "CREATE TABLE {$emb_table} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			post_type varchar(20) NOT NULL DEFAULT '',
			content_chunk longtext,
			vector_data mediumtext,
			item_vector mediumtext,
			token_count smallint(5) unsigned NOT NULL DEFAULT 0,
			context_name varchar(55) DEFAULT 'default' NOT NULL,
			is_demo tinyint(1) DEFAULT 0 NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_post_id (post_id),
			KEY idx_context_name (context_name),
			KEY is_demo (is_demo)
		) {$charset_collate};";
		
		dbDelta( $sql_embeddings );

		// Update embeddings table structure.
		self::maybe_add_column( $emb_table, 'post_type', "ALTER TABLE {$emb_table} ADD COLUMN post_type varchar(20) NOT NULL DEFAULT '' AFTER post_id" );
		self::maybe_modify_column( $emb_table, 'token_count', "ALTER TABLE {$emb_table} MODIFY COLUMN token_count smallint(5) unsigned NOT NULL DEFAULT 0" );
		self::maybe_modify_created_at_to_current_timestamp( $emb_table );
		
		// Update datetime columns to use CURRENT_TIMESTAMP default.
		self::maybe_modify_datetime_default_current( $submissions_table, 'submitted_at' );
		self::maybe_modify_datetime_default_current( $queries_table, 'query_timestamp' );
	}

	/**
	 * Modify datetime column to use CURRENT_TIMESTAMP as default.
	 *
	 * @param string $table  The table name.
	 * @param string $column The column name.
	 * @return void
	 */
	private static function maybe_modify_datetime_default_current( $table, $column ) {
    if ( ! self::is_managed_table( $table ) ) {
        return;
    }
    
    if ( ! self::column_exists( $table, $column ) ) {
        return;
    }

    global $wpdb;
    
    // Validate column name contains only safe characters (alphanumeric and underscore).
    if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $column ) ) {
        return;
    }
    
    // Additional validation: remove any potential dangerous characters.
    $safe_table  = preg_replace( '/[^A-Za-z0-9_]/', '', $table );
    $safe_column = preg_replace( '/[^A-Za-z0-9_]/', '', $column );
    
    // Build query using validated identifiers.
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $alter_sql = sprintf(
        'ALTER TABLE `%s` MODIFY COLUMN `%s` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP',
        esc_sql( $safe_table ),
        esc_sql( $safe_column )
    );
    
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
    $wpdb->query( $alter_sql );
    }

	/**
	 * Check if the table is managed by this plugin.
	 *
	 * Internal security: ALTER operations are only applied to the plugin's own tables.
	 *
	 * @param string $table The table name to check.
	 * @return bool True if the table is managed by this plugin, false otherwise.
	 */
	private static function is_managed_table( $table ) {
		global $wpdb;
		
		$allowed = array(
			$wpdb->prefix . 'promptor_submissions',
			$wpdb->prefix . 'promptor_queries',
			$wpdb->prefix . 'promptor_embeddings',
		);
		
		return in_array( $table, $allowed, true );
	}

	/**
	 * Check if a column exists in a table.
	 *
	 * @param string $table  The table name.
	 * @param string $column The column name.
	 * @return bool True if column exists, false otherwise.
	 */
	private static function column_exists( $table, $column ) {
    if ( ! self::is_managed_table( $table ) ) {
        return false;
    }
    
    global $wpdb;
    
    // Validate and sanitize table name (already whitelisted, but extra safety).
    $safe_table = preg_replace( '/[^A-Za-z0-9_]/', '', $table );
    
    // Use sprintf with esc_sql for table identifier
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $query = sprintf(
        'SHOW COLUMNS FROM `%s` LIKE %%s',
        esc_sql( $safe_table )
    );
    
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
    $result = $wpdb->get_var(
        $wpdb->prepare(
            $query, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $column
        )
    );
    
    return (bool) $result;
    }

	/**
	 * Check if an index exists in a table.
	 *
	 * @param string $table The table name.
	 * @param string $index The index name.
	 * @return bool True if index exists, false otherwise.
	 */
	private static function index_exists( $table, $index ) {
    if ( ! self::is_managed_table( $table ) ) {
        return false;
    }
    
    global $wpdb;
    
    // Validate and sanitize table name (already whitelisted, but extra safety).
    $safe_table = preg_replace( '/[^A-Za-z0-9_]/', '', $table );
    
    // Use sprintf with esc_sql for table identifier
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $query = sprintf(
        'SHOW INDEX FROM `%s`',
        esc_sql( $safe_table )
    );
    
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
    $results = $wpdb->get_results( $query );
    
    if ( ! $results ) {
        return false;
    }
    
    foreach ( $results as $row ) {
        if ( isset( $row->Key_name ) && $row->Key_name === $index ) {
            return true;
        }
    }
    
    return false;
    }

	/**
	 * Add a column to a table if it doesn't exist.
	 *
	 * @param string $table     The table name.
	 * @param string $column    The column name to add.
	 * @param string $alter_sql The ALTER TABLE SQL statement.
	 * @return void
	 */
	private static function maybe_add_column( $table, $column, $alter_sql ) {
		if ( ! self::is_managed_table( $table ) ) {
			return;
		}
		
		if ( self::column_exists( $table, $column ) ) {
			return;
		}

		// Validate the ALTER SQL statement.
		if ( ! self::validate_alter_sql( $alter_sql, $table ) ) {
			return;
		}

		// Identifier security: only allow alphanumeric and underscore characters.
		if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $column ) ) {
			return;
		}

		global $wpdb;
		
		// Escape the SQL statement for safe execution.
		$safe_sql = esc_sql( $alter_sql );
		
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $safe_sql );
	}

	/**
	 * Add an index to a table if it doesn't exist.
	 *
	 * @param string $table     The table name.
	 * @param string $index     The index name to add.
	 * @param string $alter_sql The ALTER TABLE SQL statement.
	 * @return void
	 */
	private static function maybe_add_index( $table, $index, $alter_sql ) {
		if ( ! self::is_managed_table( $table ) ) {
			return;
		}
		
		if ( self::index_exists( $table, $index ) ) {
			return;
		}

		// Validate the ALTER SQL statement.
		if ( ! self::validate_alter_sql( $alter_sql, $table ) ) {
			return;
		}

		// Identifier security: only allow alphanumeric and underscore characters.
		if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $index ) ) {
			return;
		}

		global $wpdb;
		
		// Escape the SQL statement for safe execution.
		$safe_sql = esc_sql( $alter_sql );
		
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $safe_sql );
	}

	/**
	 * Modify a column in a table if it exists.
	 *
	 * @param string $table     The table name.
	 * @param string $column    The column name to modify.
	 * @param string $alter_sql The ALTER TABLE SQL statement.
	 * @return void
	 */
	private static function maybe_modify_column( $table, $column, $alter_sql ) {
		if ( ! self::is_managed_table( $table ) ) {
			return;
		}
		
		if ( ! self::column_exists( $table, $column ) ) {
			return;
		}

		// Validate the ALTER SQL statement.
		if ( ! self::validate_alter_sql( $alter_sql, $table ) ) {
			return;
		}

		// Identifier security: only allow alphanumeric and underscore characters.
		if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $column ) ) {
			return;
		}

		global $wpdb;
		
		// Escape the SQL statement for safe execution.
		$safe_sql = esc_sql( $alter_sql );
		
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $safe_sql );
	}

	/**
	 * Modify created_at column to use CURRENT_TIMESTAMP as default.
	 *
	 * @param string $table The table name.
	 * @return void
	 */
	private static function maybe_modify_created_at_to_current_timestamp( $table ) {
    if ( ! self::is_managed_table( $table ) ) {
        return;
    }
    
    if ( ! self::column_exists( $table, 'created_at' ) ) {
        return;
    }

    global $wpdb;
    
    // Validate and sanitize table name (already whitelisted, but extra safety).
    $safe_table = preg_replace( '/[^A-Za-z0-9_]/', '', $table );
    
    // Build query using validated identifier with sprintf and esc_sql.
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $alter_sql = sprintf(
        'ALTER TABLE `%s` MODIFY COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP',
        esc_sql( $safe_table )
    );
    
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
    $wpdb->query( $alter_sql );
    }

	/**
	 * Validate ALTER SQL statement.
	 *
	 * Ensures the SQL statement is safe and targets the correct table.
	 *
	 * @param string $alter_sql The ALTER SQL statement to validate.
	 * @param string $table     The expected table name.
	 * @return bool True if valid, false otherwise.
	 */
	private static function validate_alter_sql( $alter_sql, $table ) {
		// Must be a string.
		if ( ! is_string( $alter_sql ) ) {
			return false;
		}
		
		// Must contain the target table name.
		if ( false === strpos( $alter_sql, $table ) ) {
			return false;
		}
		
		// Must not contain semicolons or SQL comments.
		if ( preg_match( '/[;#]/', $alter_sql ) ) {
			return false;
		}
		
		// Must start with ALTER TABLE.
		if ( ! preg_match( '/^\s*ALTER\s+TABLE\b/i', $alter_sql ) ) {
			return false;
		}
		
		return true;
	}
}