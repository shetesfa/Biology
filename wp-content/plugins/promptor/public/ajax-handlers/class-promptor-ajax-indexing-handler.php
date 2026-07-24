<?php
/**
 * AJAX Indexing / Crawler Handler for Promptor
 *
 * @package Promptor
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Handles: manual sync, sitemap crawl, single item processing, index clearing, and auto-sync on save_post.
 */
class Promptor_Ajax_Indexing_Handler {

	/**
	 * Constructor: hook registrations.
	 */
	public function __construct() {
		add_action( 'wp_ajax_promptor_start_sync',   array( $this, 'handle_start_sync' ) );
		add_action( 'wp_ajax_promptor_start_crawl',  array( $this, 'handle_start_crawl' ) );
		add_action( 'wp_ajax_promptor_process_item', array( $this, 'handle_process_item' ) );
		add_action( 'wp_ajax_promptor_clear_index',  array( $this, 'handle_clear_index' ) );

		// Auto-sync edited content that belongs to any active knowledge base with auto_sync_enabled.
		add_action( 'save_post', array( $this, 'handle_auto_sync_on_save' ), 10, 2 );
	}

	/**
	 * Polyfill for mb_strlen() when mbstring extension is not available.
	 *
	 * @param string $string The string to measure.
	 * @param string $encoding Character encoding (default UTF-8).
	 * @return int String length.
	 */
	private function safe_mb_strlen( $string, $encoding = 'UTF-8' ) {
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $string, $encoding );
		}
		// Fallback: count UTF-8 characters properly
		return strlen( utf8_decode( $string ) );
	}

	/**
	 * Polyfill for mb_substr() when mbstring extension is not available.
	 *
	 * @param string   $string   The string to extract from.
	 * @param int      $start    Start position.
	 * @param int|null $length   Length to extract.
	 * @param string   $encoding Character encoding (default UTF-8).
	 * @return string Substring.
	 */
	private function safe_mb_substr( $string, $start, $length = null, $encoding = 'UTF-8' ) {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $string, $start, $length, $encoding );
		}
		// Fallback: use substr but be aware it's not perfect for multibyte
		if ( null === $length ) {
			return substr( $string, $start );
		}
		return substr( $string, $start, $length );
	}

	/**
	 * Polyfill for mb_strrpos() when mbstring extension is not available.
	 *
	 * @param string $haystack String to search in.
	 * @param string $needle   String to search for.
	 * @param int    $offset   Offset position.
	 * @param string $encoding Character encoding (default UTF-8).
	 * @return int|false Position of last occurrence or false if not found.
	 */
	private function safe_mb_strrpos( $haystack, $needle, $offset = 0, $encoding = 'UTF-8' ) {
		if ( function_exists( 'mb_strrpos' ) ) {
			return mb_strrpos( $haystack, $needle, $offset, $encoding );
		}
		// Fallback: use strrpos
		return strrpos( $haystack, $needle, $offset );
	}

	/**
	 * Polyfill for mb_convert_encoding() when mbstring extension is not available.
	 *
	 * @param string $string   String to convert.
	 * @param string $to       Target encoding.
	 * @param string $from     Source encoding.
	 * @return string Converted string.
	 */
	private function safe_mb_convert_encoding( $string, $to, $from ) {
		if ( function_exists( 'mb_convert_encoding' ) ) {
			return mb_convert_encoding( $string, $to, $from );
		}
		// Fallback: return original string (not ideal but prevents fatal errors)
		return $string;
	}

	/**
	 * Update source_type of a context.
	 *
	 * @param string $context_key
	 * @param string $source_type 'manual' | 'crawler'
	 */
	private function update_context_source_type( $context_key, $source_type ) : void {
		$contexts = get_option( 'promptor_contexts', array() );
		if ( isset( $contexts[ $context_key ] ) ) {
			$contexts[ $context_key ]['source_type'] = $source_type;
			update_option( 'promptor_contexts', $contexts );
		}
	}

	/**
	 * Very conservative selector sanitization for class or id ('.cls' or '#id' or bare 'cls/id').
	 * Allows only letters, digits, underscore, dash, dot, hash. Everything else is stripped.
	 *
	 * @param string $selector
	 * @return string
	 */
	private function sanitize_simple_selector( $selector ) : string {
		$selector = (string) $selector;
		$selector = trim( $selector );
		$selector = preg_replace( '/[^a-zA-Z0-9_\-\.\#]/u', '', $selector );
		return $selector;
	}

	/**
	 * Split long text into UTF-8 safe chunks breaking on spaces when possible.
	 *
	 * @param string $text
	 * @param int    $max_chunk_size
	 * @return array
	 */
	private function chunk_text( $text, $max_chunk_size = 1000 ) : array {
		$text = trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
		if ( $text === '' ) { return array(); }

		$chunks      = array();
		$current_pos = 0;
		$text_length = $this->safe_mb_strlen( $text, 'UTF-8' );

		while ( $current_pos < $text_length ) {
			$chunk_end = $current_pos + $max_chunk_size;

			if ( $chunk_end >= $text_length ) {
				$chunks[] = $this->safe_mb_substr( $text, $current_pos, null, 'UTF-8' );
				break;
			}

			$substr     = $this->safe_mb_substr( $text, 0, $chunk_end, 'UTF-8' );
			$last_space = $this->safe_mb_strrpos( $substr, ' ', 0, 'UTF-8' );

			if ( false !== $last_space && $last_space > $current_pos ) {
				$chunks[]    = $this->safe_mb_substr( $text, $current_pos, $last_space - $current_pos, 'UTF-8' );
				$current_pos = $last_space + 1; // skip the space
			} else {
				$chunks[]    = $this->safe_mb_substr( $text, $current_pos, $max_chunk_size, 'UTF-8' );
				$current_pos += $max_chunk_size;
			}
		}

		return array_values( array_filter( array_map( 'trim', $chunks ) ) );
	}

	/**
	 * Clear all embeddings rows for a given context.
	 *
	 * @param string $context_name
	 * @return int|false Number of rows deleted or false on error.
	 * @throws Exception If table does not exist or wpdb error occurs.
	 */
	private function clear_index_for_context( $context_name ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'promptor_embeddings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		if ( $table_exists !== $table_name ) {
			throw new Exception( esc_html__( 'Embeddings table does not exist.', 'promptor' ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$deleted_rows = $wpdb->delete(
			$table_name,
			array( 'context_name' => $context_name ),
			array( '%s' )
		);

		if ( false === $deleted_rows && $wpdb->last_error ) {
			/* translators: %s: Database error message. */
			throw new Exception( sprintf( esc_html__( 'Database error: %s', 'promptor' ), esc_html( $wpdb->last_error ) ) );
		}

		return $deleted_rows;
	}

	/**
	 * Check if the embeddings table has a specific column. Cached per request.
	 *
	 * @param string $column
	 * @return bool
	 */
	private function embeddings_has_column( $column ) : bool {
		static $cache = array();
		if ( isset( $cache[ $column ] ) ) {
			return (bool) $cache[ $column ];
		}
		global $wpdb;
		$table = $wpdb->prefix . 'promptor_embeddings';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SHOW COLUMNS FROM `%1s` LIKE %s', // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder
				$table,
				$column
			)
		);
		$cache[ $column ] = $exists;
		return $exists;
	}

	/**
	 * Start manual sync based on context selections (posts/pages/files).
	 */
	public function handle_start_sync() : void {
		check_ajax_referer( 'promptor_indexing_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission Denied.', 'promptor' ) ) );
		}

		$context = isset( $_POST['context'] ) ? sanitize_key( wp_unslash( $_POST['context'] ) ) : 'default';
		$this->update_context_source_type( $context, 'manual' );

		$contexts     = get_option( 'promptor_contexts', array() );
		$context_data = $contexts[ $context ] ?? null;

		if ( ! $context_data ) {
			wp_send_json_error( array( 'message' => __( 'Knowledge base not found. Please refresh the page.', 'promptor' ) ) );
		}

		$posts_to_sync = array_keys( $context_data['settings']['content_roles'] ?? array() );

		$selected_pages = $context_data['settings']['selected_pages'] ?? array();
		if ( is_array( $selected_pages ) && ! empty( $selected_pages ) ) {
			$posts_to_sync = array_merge( $posts_to_sync, $selected_pages );
		}

		if ( function_exists( 'promptor_fs' ) && promptor_fs()->can_use_premium_code__premium_only() ) {
			$file_ids = $context_data['settings']['selected_files'] ?? array();
			if ( is_array( $file_ids ) && ! empty( $file_ids ) ) {
				$posts_to_sync = array_merge( $posts_to_sync, $file_ids );
			}
		}

		$posts_to_sync = array_unique( array_map( 'absint', $posts_to_sync ) );

		if ( empty( $posts_to_sync ) ) {
			$this->clear_index_for_context( $context );
			wp_send_json_success(
				array(
					'items'   => array(),
					'message' => __( 'No content selected. The index for this knowledge base has been cleared.', 'promptor' ),
				)
			);
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$indexed_posts_raw = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$wpdb->prefix}promptor_embeddings WHERE context_name = %s",
				$context
			)
		);
		$indexed_posts = array_map( 'intval', $indexed_posts_raw );

		$posts_to_delete = array_diff( $indexed_posts, $posts_to_sync );
		$deleted_count   = 0;

		if ( ! empty( $posts_to_delete ) ) {
			foreach ( array_map( 'intval', (array) $posts_to_delete ) as $pid ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$rows = $wpdb->delete(
					"{$wpdb->prefix}promptor_embeddings",
					array( 'context_name' => $context, 'post_id' => $pid ),
					array( '%s', '%d' )
				);
				if ( false !== $rows ) {
					$deleted_count += (int) $rows;
				}
			}
		}

		$posts_to_add = array_diff( $posts_to_sync, $indexed_posts );

		if ( empty( $posts_to_add ) && $deleted_count > 0 ) {
			/* translators: %d: Number of items removed. */
			$message = sprintf( __( '%d items removed from knowledge base. Sync completed.', 'promptor' ), (int) $deleted_count );
			wp_send_json_success( array( 'items' => array(), 'message' => $message ) );
		}

		if ( empty( $posts_to_add ) && 0 === (int) $deleted_count ) {
			wp_send_json_success( array( 'items' => array(), 'message' => __( 'Knowledge base is already up to date. Nothing to sync.', 'promptor' ) ) );
		}

		$message = '';
		if ( $deleted_count > 0 ) {
			/* translators: %d: Number of items removed. */
			$message = sprintf( __( '%d items removed from knowledge base. ', 'promptor' ), (int) $deleted_count );
		}

		wp_send_json_success(
			array(
				'items'     => array_values( $posts_to_add ),
				'item_type' => 'post',
				/* translators: %d: Number of items to be added. */
				'message'   => $message . sprintf( __( '%d items will be added.', 'promptor' ), count( $posts_to_add ) ),
			)
		);
	}

	/**
	 * Start sitemap crawl (Pro).
	 */
	public function handle_start_crawl() : void {
		check_ajax_referer( 'promptor_indexing_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission Denied.', 'promptor' ) ) );
		}

		if ( ! function_exists( 'promptor_fs' ) || ! promptor_fs()->can_use_premium_code__premium_only() ) {
			wp_send_json_error( array( 'message' => __( 'Website Crawler is a Pro feature. Please upgrade your license.', 'promptor' ) ) );
		}

		$context = isset( $_POST['context'] ) ? sanitize_key( wp_unslash( $_POST['context'] ) ) : 'default';
		$this->update_context_source_type( $context, 'crawler' );

		$sitemap_url = isset( $_POST['sitemap_url'] ) ? esc_url_raw( wp_unslash( $_POST['sitemap_url'] ) ) : '';
		if ( empty( $sitemap_url ) ) {
			wp_send_json_error( array( 'message' => __( 'Sitemap URL is required.', 'promptor' ) ) );
		}

		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$url_host  = wp_parse_url( $sitemap_url, PHP_URL_HOST );
		$scheme    = wp_parse_url( $sitemap_url, PHP_URL_SCHEME );

		if ( ! $home_host || ! $url_host || 0 !== strcasecmp( $home_host, $url_host ) || ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Only the current site domain is allowed for crawling.', 'promptor' ) ) );
		}

		$response = wp_remote_get(
			$sitemap_url,
			array(
				'timeout'      => 20,
				'redirection'  => 3,
				'user-agent'   => 'PromptorBot/1.0; ' . home_url( '/' ),
				'headers'      => array( 'Accept' => 'application/xml,text/xml;q=0.9,*/*;q=0.8' ),
				'decompress'   => true,
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter
				'sslverify'    => apply_filters( 'https_local_ssl_verify', true ),
			)
		);
		if ( is_wp_error( $response ) ) {
			/* translators: %s: Error message. */
			wp_send_json_error( array( 'message' => sprintf( __( 'Could not fetch sitemap. Error: %s', 'promptor' ), $response->get_error_message() ) ) );
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			/* translators: %d: HTTP status code. */
			wp_send_json_error( array( 'message' => sprintf( __( 'Could not fetch sitemap. The server returned status code: %d', 'promptor' ), (int) wp_remote_retrieve_response_code( $response ) ) ) );
		}

		$xml_content = (string) wp_remote_retrieve_body( $response );

		libxml_use_internal_errors( true );
		// LIBXML_NONET prevents external entity/network fetches.
		$xml = @simplexml_load_string( $xml_content, 'SimpleXMLElement', LIBXML_NONET );
		if ( false === $xml ) {
			libxml_clear_errors();
			libxml_use_internal_errors( false );
			wp_send_json_error( array( 'message' => __( 'The provided URL does not contain valid XML. Could not parse sitemap.', 'promptor' ) ) );
		}
		libxml_clear_errors();
		libxml_use_internal_errors( false );

		$urls = array();

		// urlset
		if ( isset( $xml->url ) ) {
			foreach ( $xml->url as $url_node ) {
				$loc = (string) $url_node->loc;
				if ( $loc ) { $urls[] = $loc; }
			}
		}

		// sitemapindex
		if ( empty( $urls ) && isset( $xml->sitemap ) ) {
			foreach ( $xml->sitemap as $sm_node ) {
				$loc = (string) $sm_node->loc;
				if ( ! $loc ) { continue; }

				$sm_host   = wp_parse_url( $loc, PHP_URL_HOST );
				$sm_scheme = wp_parse_url( $loc, PHP_URL_SCHEME );
				if ( ! $sm_host || 0 !== strcasecmp( $home_host, $sm_host ) || ! in_array( $sm_scheme, array( 'http', 'https' ), true ) ) {
					continue;
				}

				$sub = wp_remote_get( $loc, array( 'timeout' => 20, 'redirection' => 3 ) );
				if ( is_wp_error( $sub ) || 200 !== (int) wp_remote_retrieve_response_code( $sub ) ) { continue; }

				$sub_body = (string) wp_remote_retrieve_body( $sub );
				$sub_xml  = @simplexml_load_string( $sub_body, 'SimpleXMLElement', LIBXML_NONET );
				if ( $sub_xml && isset( $sub_xml->url ) ) {
					foreach ( $sub_xml->url as $u ) {
						$u_loc = (string) $u->loc;
						if ( $u_loc ) { $urls[] = $u_loc; }
					}
				}
			}
		}

		$crawler_settings         = get_option( 'promptor_crawler_settings', array() );
		$exclusion_keywords_raw   = (string) ( $crawler_settings['disallowed_patterns'] ?? '' );
		$crawl_limit              = (int) ( $crawler_settings['max_pages'] ?? 100 );

		if ( $exclusion_keywords_raw !== '' ) {
			$exclusion_keywords = array_filter( array_map( 'trim', preg_split( "/\r\n|\n|\r/", $exclusion_keywords_raw ) ) );
			if ( ! empty( $exclusion_keywords ) ) {
				$urls = array_filter(
					$urls,
					static function ( $url ) use ( $exclusion_keywords ) {
						foreach ( $exclusion_keywords as $keyword ) {
							if ( $keyword !== '' && strpos( $url, $keyword ) !== false ) {
								return false;
							}
						}
						return true;
					}
				);
			}
		}

		$urls = array_values( array_unique( array_map( 'esc_url_raw', $urls ) ) );

		if ( $crawl_limit > 0 && count( $urls ) > $crawl_limit ) {
			$urls = array_slice( $urls, 0, $crawl_limit );
		}

		if ( empty( $urls ) ) {
			wp_send_json_error( array( 'message' => __( 'No URLs found in the sitemap after applying filters. Check your sitemap and filter settings.', 'promptor' ) ) );
		}

		wp_send_json_success( array( 'items' => $urls, 'item_type' => 'url' ) );
	}

	/**
	 * Extract meaningful text content from HTML, optionally limited by a simple selector.
	 *
	 * @param string $html
	 * @param string $custom_selector
	 * @return string
	 */
	private function extract_meaningful_content( $html, $custom_selector = '' ) : string {
		if ( empty( $html ) ) { return ''; }

		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		// Silence warnings for broken HTML; ensure UTF-8.
		@$dom->loadHTML( $this->safe_mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ), LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();

		$xpath        = new DOMXPath( $dom );
		$content_node = null;

		$selector = $this->sanitize_simple_selector( $custom_selector );
		if ( $selector !== '' ) {
			// Support .class or #id or bare token.
			$needle = ltrim( $selector, '.#' );
			// Safe XPath contains() against class token.
			$nodes = $xpath->query(
				'//*[contains(concat(" ", normalize-space(@class), " "), " ' . $needle . ' ")] | //*[@id="' . $needle . '"]'
			);
			if ( $nodes && $nodes->length > 0 ) {
				$content_node = $nodes->item( 0 );
			}
		}

		if ( null === $content_node ) {
			$main_content_queries = array(
				'//main',
				'//article',
				'//div[contains(@class, "content")]',
				'//div[contains(@id, "content")]',
				'//div[contains(@class, "post")]',
				'//div[contains(@id, "post")]',
				'//div[contains(@role, "main")]',
			);
			foreach ( $main_content_queries as $query ) {
				$nodes = $xpath->query( $query );
				if ( $nodes && $nodes->length > 0 ) {
					$content_node = $nodes->item( 0 );
					break;
				}
			}
		}

		if ( null === $content_node ) {
			$body = $xpath->query( '//body' );
			if ( $body && $body->length ) {
				$content_node = $body->item( 0 );
			}
		}
		if ( null === $content_node ) {
			return '';
		}

		$to_remove = array( 'script', 'style', 'nav', 'footer', 'header', 'aside', 'form', 'iframe', 'button' );
		foreach ( $to_remove as $tag ) {
			$list = $content_node->getElementsByTagName( $tag );
			for ( $i = $list->length - 1; $i >= 0; $i-- ) {
				$node = $list->item( $i );
				if ( $node && $node->parentNode ) {
					$node->parentNode->removeChild( $node );
				}
			}
		}

		$text = trim( preg_replace( '/\s+/u', ' ', (string) $content_node->textContent ) );
		return $text;
	}

	/**
	 * Process a single item (post|attachment|product|url) into embeddings.
	 */
	public function handle_process_item() : void {
		check_ajax_referer( 'promptor_indexing_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission Denied.', 'promptor' ) ) );
		}

		$item_type    = isset( $_POST['item_type'] ) ? sanitize_key( wp_unslash( $_POST['item_type'] ) ) : 'post';
		$context_name = isset( $_POST['context'] )   ? sanitize_key( wp_unslash( $_POST['context'] ) )   : 'default';

		if ( 'url' === $item_type ) {
			$item_id = isset( $_POST['item_id'] ) ? esc_url_raw( wp_unslash( $_POST['item_id'] ) ) : '';
			if ( empty( $item_id ) || ! filter_var( $item_id, FILTER_VALIDATE_URL ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid URL provided.', 'promptor' ) ) );
			}
		} else {
			$item_id = isset( $_POST['item_id'] ) ? absint( wp_unslash( $_POST['item_id'] ) ) : 0;
			if ( $item_id <= 0 ) {
				wp_send_json_error( array( 'message' => __( 'Invalid Item ID provided.', 'promptor' ) ) );
			}
		}

		$api_settings = get_option( 'promptor_api_settings' );
		$api_key      = $api_settings['api_key']      ?? '';
		$embed_model  = $api_settings['embed_model']  ?? 'text-embedding-3-small';

		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => __( 'OpenAI API Key is not set in Settings > API Settings.', 'promptor' ) ) );
		}

		$content                 = '';
		$post_title              = '';
		$post_type_for_db        = '';
		$post_id_for_db          = 0;
		$item_text_for_embedding = '';

		if ( 'url' === $item_type ) {
			if ( ! function_exists( 'promptor_fs' ) || ! promptor_fs()->can_use_premium_code__premium_only() ) {
				wp_send_json_error( array( 'message' => __( 'Crawler is a Pro feature.', 'promptor' ) ) );
			}

			$url       = $item_id; // already validated above.
			$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
			$url_host  = wp_parse_url( $url, PHP_URL_HOST );
			$scheme    = wp_parse_url( $url, PHP_URL_SCHEME );

			if ( ! $home_host || ! $url_host || 0 !== strcasecmp( $home_host, $url_host ) || ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
				wp_send_json_error( array( 'message' => __( 'Only the current site domain is allowed for crawling.', 'promptor' ) ) );
			}

			$crawler_settings = get_option( 'promptor_crawler_settings', array() );
			$custom_selector  = $this->sanitize_simple_selector( $crawler_settings['content_selector'] ?? '' );

			// Keep a stable int key for url rows (CRC32). Note: collisions extremely rare but possible.
			$post_id_for_db   = (int) sprintf( '%u', crc32( $url ) );
			$post_title       = $url;
			$post_type_for_db = 'url';

			$response = wp_remote_get( $url, array( 'timeout' => 20, 'redirection' => 3 ) );
			if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
				$html_content            = (string) wp_remote_retrieve_body( $response );
				$content                 = $this->extract_meaningful_content( $html_content, $custom_selector );
				$item_text_for_embedding = $post_title . '. ' . wp_trim_words( $content, 50, '...' );
			} else {
				$error_message = is_wp_error( $response ) ? $response->get_error_message() : 'HTTP Status ' . (int) wp_remote_retrieve_response_code( $response );
				/* translators: 1: URL, 2: error message. */
				wp_send_json_error( array( 'message' => sprintf( __( 'Failed to fetch content for URL: %1$s. Reason: %2$s', 'promptor' ), esc_url_raw( $url ), esc_html( $error_message ) ) ) );
			}
		} else {
			$post_id_for_db = (int) $item_id;
			$post           = get_post( $post_id_for_db );

			if ( ! $post ) {
				wp_send_json_error( array( 'message' => sprintf(/* translators: %d: Post ID. */ __( 'Post with ID %d not found.', 'promptor' ), (int) $post_id_for_db ) ) );
			}

			$post_title       = (string) $post->post_title;
			$post_type_for_db = (string) $post->post_type;

			if ( class_exists( 'WooCommerce' ) && 'product' === $post->post_type ) {
				if ( ! function_exists( 'promptor_fs' ) || ! promptor_fs()->can_use_premium_code__premium_only() ) {
					wp_send_json_error( array( 'message' => __( 'WooCommerce product indexing is a Pro feature.', 'promptor' ) ) );
				}

				$product = wc_get_product( $post_id_for_db );
				if ( $product ) {
					$natural_content   = array();
					$natural_content[] = $product->get_name();
					$natural_content[] = wp_strip_all_tags( $product->get_short_description() );
					$natural_content[] = wp_strip_all_tags( $product->get_description() );
					$content           = implode( "\n\n", array_filter( $natural_content ) );
					$item_text_for_embedding = $product->get_name() . '. ' . wp_strip_all_tags( $product->get_short_description() );
				}
			} elseif ( 'attachment' === $post->post_type && false !== stripos( (string) $post->post_mime_type, 'pdf' ) ) {
				if ( ! function_exists( 'promptor_fs' ) || ! promptor_fs()->can_use_premium_code__premium_only() ) {
					wp_send_json_error( array( 'message' => __( 'PDF indexing is a Pro feature.', 'promptor' ) ) );
				}

				// Try to parse PDF content if Parser library is available
				if ( class_exists( '\Smalot\PdfParser\Parser' ) ) {
					try {
						$filepath = get_attached_file( $post_id_for_db );
						if ( ! $filepath || ! file_exists( $filepath ) ) {
							wp_send_json_error( array( 'message' => __( 'PDF file path is invalid or file does not exist.', 'promptor' ) ) );
						}
						$parser  = new \Smalot\PdfParser\Parser();
						$pdf     = $parser->parseFile( $filepath );
						$content = (string) $pdf->getText();
						$item_text_for_embedding = $post_title . '. ' . wp_trim_words( $content, 50, '...' );
					} catch ( Exception $e ) {
						// If parsing fails, use title, caption, and description as content
						$caption = get_the_excerpt( $post_id_for_db );
						$description = wp_strip_all_tags( $post->post_content );
						$metadata_parts = array_filter( array( $post_title, $caption, $description ) );
						$content = implode( '. ', $metadata_parts );
						$item_text_for_embedding = $content;
					}
				} else {
					// PDF Parser not available - use title, caption, and description as content for chunking
					$caption = get_the_excerpt( $post_id_for_db ); // Gets attachment caption
					$description = wp_strip_all_tags( $post->post_content ); // Gets attachment description

					// Combine all available text metadata
					$metadata_parts = array_filter( array( $post_title, $caption, $description ) );
					$content = implode( '. ', $metadata_parts );
					$item_text_for_embedding = $content;
				}
			} else {
				$content                 = wp_strip_all_tags( do_shortcode( $post->post_content ) );
				$item_text_for_embedding = $post_title . '. ' . ( has_excerpt( $post_id_for_db ) ? get_the_excerpt( $post_id_for_db ) : wp_trim_words( $content, 30, '...' ) );
			}
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'promptor_embeddings';

		// Clear old rows for this item+context.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $table_name, array( 'post_id' => $post_id_for_db, 'context_name' => $context_name ), array( '%d', '%s' ) );

		// Embedding for the whole-item short text (title + summary) — used as item_vector on each chunk row.
		$item_vector_response = wp_remote_post(
			'https://api.openai.com/v1/embeddings',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array( 'input' => $item_text_for_embedding, 'model' => $embed_model ) ),
				'timeout' => 60,
			)
		);

		$item_vector_json = null;
		if ( ! is_wp_error( $item_vector_response ) && 200 === (int) wp_remote_retrieve_response_code( $item_vector_response ) ) {
			$response_body = json_decode( wp_remote_retrieve_body( $item_vector_response ), true );
			if ( isset( $response_body['data'][0]['embedding'] ) ) {
				$item_vector_json = wp_json_encode( $response_body['data'][0]['embedding'] );
			}
		}

		$chunks = $this->chunk_text( $content );

		// If no chunks (empty content), at least store the item_vector row for the item.
		if ( empty( $chunks ) && $item_vector_json ) {
			$data    = array(
				'post_id'      => $post_id_for_db,
				'item_vector'  => $item_vector_json,
				'context_name' => $context_name,
				'created_at'   => current_time( 'mysql' ),
			);
			$formats = array( '%d', '%s', '%s', '%s' );
			if ( $this->embeddings_has_column( 'post_type' ) ) {
				$data['post_type'] = $post_type_for_db;
				$formats[]         = '%s';
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert( $table_name, $data, $formats );
		}

		$total_chunks_created = 0;

		if ( ! empty( $chunks ) ) {
			// Batch request for all chunks (faster/cheaper).
			$resp = wp_remote_post(
				'https://api.openai.com/v1/embeddings',
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $api_key,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( array( 'input' => array_values( $chunks ), 'model' => $embed_model ) ),
					'timeout' => 60,
				)
			);

			if ( ! is_wp_error( $resp ) && 200 === (int) wp_remote_retrieve_response_code( $resp ) ) {
				$rb = json_decode( wp_remote_retrieve_body( $resp ), true );
				if ( ! empty( $rb['data'] ) && is_array( $rb['data'] ) ) {
					foreach ( $rb['data'] as $i => $row ) {
						if ( ! isset( $row['embedding'] ) ) { continue; }
						$chunk_text = $chunks[ $i ];

						$data = array(
							'post_id'       => $post_id_for_db,
							'content_chunk' => $chunk_text,
							'vector_data'   => wp_json_encode( $row['embedding'] ),
							'item_vector'   => $item_vector_json,
							// Note: usage.total_tokens returns total for the request; still useful as hint.
							'token_count'   => (int) ( $rb['usage']['total_tokens'] ?? 0 ),
							'context_name'  => $context_name,
							'created_at'    => current_time( 'mysql' ),
						);
						$formats = array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' );
						if ( $this->embeddings_has_column( 'post_type' ) ) {
							$data['post_type'] = $post_type_for_db;
							$formats[]         = '%s';
						}
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery
						$wpdb->insert( $table_name, $data, $formats );

						$total_chunks_created++;
					}
				}
			} else {
				// Fallback: per-chunk requests.
				foreach ( $chunks as $chunk ) {
					$response = wp_remote_post(
						'https://api.openai.com/v1/embeddings',
						array(
							'headers' => array(
								'Authorization' => 'Bearer ' . $api_key,
								'Content-Type'  => 'application/json',
							),
							'body'    => wp_json_encode( array( 'input' => $chunk, 'model' => $embed_model ) ),
							'timeout' => 60,
						)
					);
					if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
						continue;
					}
					$response_body = json_decode( wp_remote_retrieve_body( $response ), true );
					if ( isset( $response_body['data'][0]['embedding'] ) ) {
						$data = array(
							'post_id'       => $post_id_for_db,
							'content_chunk' => $chunk,
							'vector_data'   => wp_json_encode( $response_body['data'][0]['embedding'] ),
							'item_vector'   => $item_vector_json,
							'token_count'   => (int) ( $response_body['usage']['total_tokens'] ?? 0 ),
							'context_name'  => $context_name,
							'created_at'    => current_time( 'mysql' ),
						);
						$formats = array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' );
						if ( $this->embeddings_has_column( 'post_type' ) ) {
							$data['post_type'] = $post_type_for_db;
							$formats[]         = '%s';
						}
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery
						$wpdb->insert( $table_name, $data, $formats );

						$total_chunks_created++;
					}
				}
			}
		}

		/* translators: 1: number of chunks, 2: post title */
		$message = sprintf( __( '%1$d chunks created for %2$s.', 'promptor' ), (int) $total_chunks_created, esc_html( $post_title ) );
		wp_send_json_success( array( 'message' => $message ) );
	}

	/**
	 * Clear entire index for a given context.
	 */
	public function handle_clear_index() : void {
		check_ajax_referer( 'promptor_indexing_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission Denied.', 'promptor' ) ) );
		}

		$context_to_clear = isset( $_POST['context'] ) ? sanitize_key( wp_unslash( $_POST['context'] ) ) : '';

		if ( '' === $context_to_clear ) {
			wp_send_json_error( array( 'message' => __( 'No knowledge base was specified to be cleared.', 'promptor' ) ) );
		}

		$contexts = get_option( 'promptor_contexts', array() );
		if ( ! isset( $contexts[ $context_to_clear ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Knowledge base not found.', 'promptor' ) ) );
		}

		try {
			$deleted_rows = $this->clear_index_for_context( $context_to_clear );

			if ( false === $deleted_rows ) {
				wp_send_json_error( array( 'message' => __( 'Database error occurred while clearing the index.', 'promptor' ) ) );
			}

			$message = sprintf(
				/* translators: 1: Knowledge base key, 2: number of removed records. */
				__( 'Knowledge base "%1$s" has been cleared successfully. %2$d records removed.', 'promptor' ),
				esc_html( $context_to_clear ),
				(int) $deleted_rows
			);

			wp_send_json_success( array( 'message' => $message ) );

		} catch ( Exception $e ) {
			/* translators: %s: Error message. */
			wp_send_json_error( array( 'message' => sprintf( esc_html__( 'Error clearing index: %s', 'promptor' ), esc_html( $e->getMessage() ) ) ) );
		}
	}

	/**
	 * Auto-sync a post to every context that has auto_sync_enabled and includes this post.
	 *
	 * @param int     $post_id
	 * @param WP_Post $post
	 */
	public function handle_auto_sync_on_save( $post_id, $post ) : void {
		if (
			( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ||
			wp_is_post_revision( $post_id ) ||
			! current_user_can( 'edit_post', $post_id )
		) {
			return;
		}

		if ( 'publish' !== (string) $post->post_status ) {
			return;
		}

		$contexts = get_option( 'promptor_contexts', array() );

		foreach ( $contexts as $context_key => $context_data ) {
			$is_auto_sync_enabled = ! empty( $context_data['settings']['auto_sync_enabled'] );
			$is_post_in_context   = isset( $context_data['settings']['content_roles'][ $post_id ] );

			if ( $is_auto_sync_enabled && $is_post_in_context ) {
				$this->process_single_item_for_sync( (int) $post_id, (string) $post->post_type, (string) $context_key );
			}
		}
	}

	/**
	 * Internal: Process a single item for auto-sync flow (no JSON output).
	 *
	 * @param int    $item_id
	 * @param string $item_type
	 * @param string $context_name
	 * @return void
	 */
	private function process_single_item_for_sync( $item_id, $item_type, $context_name ) : void {
		$api_settings = get_option( 'promptor_api_settings' );
		$api_key      = $api_settings['api_key']     ?? '';
		$embed_model  = $api_settings['embed_model'] ?? 'text-embedding-3-small';

		if ( '' === $api_key ) { return; }

		$post = get_post( $item_id );
		if ( ! $post ) { return; }

		$post_type_for_db        = (string) $post->post_type;
		$content                 = wp_strip_all_tags( do_shortcode( $post->post_content ) );
		$item_text_for_embedding = $post->post_title . '. ' . ( has_excerpt( $post->ID ) ? get_the_excerpt( $post->ID ) : wp_trim_words( $content, 30, '...' ) );

		global $wpdb;
		$table_name = $wpdb->prefix . 'promptor_embeddings';

		// Clear old rows for this item+context.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $table_name, array( 'post_id' => (int) $item_id, 'context_name' => $context_name ), array( '%d', '%s' ) );

		$item_vector_response = wp_remote_post(
			'https://api.openai.com/v1/embeddings',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array( 'input' => $item_text_for_embedding, 'model' => $embed_model ) ),
				'timeout' => 60,
			)
		);

		$item_vector_json = null;
		if ( ! is_wp_error( $item_vector_response ) && 200 === (int) wp_remote_retrieve_response_code( $item_vector_response ) ) {
			$response_body = json_decode( wp_remote_retrieve_body( $item_vector_response ), true );
			if ( isset( $response_body['data'][0]['embedding'] ) ) {
				$item_vector_json = wp_json_encode( $response_body['data'][0]['embedding'] );
			}
		}

		$chunks = $this->chunk_text( $content );

		if ( empty( $chunks ) && $item_vector_json ) {
			$data    = array(
				'post_id'      => (int) $item_id,
				'item_vector'  => $item_vector_json,
				'context_name' => $context_name,
				'created_at'   => current_time( 'mysql' ),
			);
			$formats = array( '%d', '%s', '%s', '%s' );
			if ( $this->embeddings_has_column( 'post_type' ) ) {
				$data['post_type'] = $post_type_for_db;
				$formats[]         = '%s';
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert( $table_name, $data, $formats );
		}

		if ( ! empty( $chunks ) ) {
			$resp = wp_remote_post(
				'https://api.openai.com/v1/embeddings',
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $api_key,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( array( 'input' => array_values( $chunks ), 'model' => $embed_model ) ),
					'timeout' => 60,
				)
			);

			if ( ! is_wp_error( $resp ) && 200 === (int) wp_remote_retrieve_response_code( $resp ) ) {
				$rb = json_decode( wp_remote_retrieve_body( $resp ), true );
				if ( ! empty( $rb['data'] ) && is_array( $rb['data'] ) ) {
					foreach ( $rb['data'] as $i => $row ) {
						if ( ! isset( $row['embedding'] ) ) { continue; }
						$chunk_text = $chunks[ $i ];

						$data = array(
							'post_id'       => (int) $item_id,
							'content_chunk' => $chunk_text,
							'vector_data'   => wp_json_encode( $row['embedding'] ),
							'item_vector'   => $item_vector_json,
							'token_count'   => (int) ( $rb['usage']['total_tokens'] ?? 0 ),
							'context_name'  => $context_name,
							'created_at'    => current_time( 'mysql' ),
						);
						$formats = array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' );
						if ( $this->embeddings_has_column( 'post_type' ) ) {
							$data['post_type'] = $post_type_for_db;
							$formats[]         = '%s';
						}
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery
						$wpdb->insert( $table_name, $data, $formats );
					}
				}
			} else {
				foreach ( $chunks as $chunk ) {
					$response = wp_remote_post(
						'https://api.openai.com/v1/embeddings',
						array(
							'headers' => array(
								'Authorization' => 'Bearer ' . $api_key,
								'Content-Type'  => 'application/json',
							),
							'body'    => wp_json_encode( array( 'input' => $chunk, 'model' => $embed_model ) ),
							'timeout' => 60,
						)
					);
					if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
						continue;
					}
					$response_body = json_decode( wp_remote_retrieve_body( $response ), true );
					if ( isset( $response_body['data'][0]['embedding'] ) ) {
						$data = array(
							'post_id'       => (int) $item_id,
							'content_chunk' => $chunk,
							'vector_data'   => wp_json_encode( $response_body['data'][0]['embedding'] ),
							'item_vector'   => $item_vector_json,
							'token_count'   => (int) ( $response_body['usage']['total_tokens'] ?? 0 ),
							'context_name'  => $context_name,
							'created_at'    => current_time( 'mysql' ),
						);
						$formats = array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' );
						if ( $this->embeddings_has_column( 'post_type' ) ) {
							$data['post_type'] = $post_type_for_db;
							$formats[]         = '%s';
						}
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery
						$wpdb->insert( $table_name, $data, $formats );
					}
				}
			}
		}
	}
}