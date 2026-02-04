<?php
/**
 * Parish Search API client for Meilisearch
 */

if (!defined('ABSPATH')) {
    exit;
}

class Parish_Search_API {

    private $api_url;
    private $api_key;
    private $index_name = 'parish_search';

    // Valid values for grammar filters
    private $valid_types = array('file', 'post', 'page', 'document', 'news', 'faq', 'event');
    private $valid_doctypes = array('minutes', 'agenda', 'policy', 'planning', 'finance', 'other');

    public function __construct() {
        $this->api_url = rtrim(get_option('parish_search_api_url', ''), '/');
        $this->api_key = get_option('parish_search_api_key', '');
    }

    /**
     * Parse search query for grammar patterns and extract filters.
     *
     * Supported patterns:
     *   type:file, type:post, type:page, type:document, type:news
     *   year:2024
     *   doctype:minutes, doctype:agenda, doctype:policy, doctype:planning, doctype:finance
     *   before:2024-01 or before:2024
     *   after:2023-06 or after:2023
     *   in:council, in:planning (matches against file path)
     *
     * @param string $query Raw search query
     * @return array ['query' => cleaned query, 'filters' => array of filter expressions]
     */
    private function parse_query($query) {
        $filters = array();
        $cleaned_query = $query;

        // Pattern: type:value
        if (preg_match('/\btype:(\w+)/i', $cleaned_query, $matches)) {
            $type_value = strtolower($matches[1]);
            // Map aliases
            if ($type_value === 'document') $type_value = 'file';
            if ($type_value === 'news') $type_value = 'post';

            if (in_array($type_value, array('file', 'post', 'page', 'faq', 'event'))) {
                $filters[] = 'type = "' . $type_value . '"';
            }
            $cleaned_query = preg_replace('/\btype:\w+/i', '', $cleaned_query);
        }

        // Pattern: year:YYYY
        if (preg_match('/\byear:(\d{4})/i', $cleaned_query, $matches)) {
            $year = intval($matches[1]);
            if ($year >= 1990 && $year <= 2100) {
                $filters[] = 'year = ' . $year;
            }
            $cleaned_query = preg_replace('/\byear:\d{4}/i', '', $cleaned_query);
        }

        // Pattern: doctype:value
        if (preg_match('/\bdoctype:(\w+)/i', $cleaned_query, $matches)) {
            $doctype = strtolower($matches[1]);
            if (in_array($doctype, $this->valid_doctypes)) {
                $filters[] = 'document_type = "' . $doctype . '"';
            }
            $cleaned_query = preg_replace('/\bdoctype:\w+/i', '', $cleaned_query);
        }

        // Pattern: before:YYYY-MM or before:YYYY
        if (preg_match('/\bbefore:(\d{4})(?:-(\d{2}))?/i', $cleaned_query, $matches)) {
            $year = intval($matches[1]);
            $month = isset($matches[2]) ? intval($matches[2]) : 12;
            if ($year >= 1990 && $year <= 2100 && $month >= 1 && $month <= 12) {
                // Use date_sortable: YYYYMMDD format
                $before_date = $year * 10000 + $month * 100 + 31;
                $filters[] = 'date_sortable < ' . $before_date;
            }
            $cleaned_query = preg_replace('/\bbefore:\d{4}(?:-\d{2})?/i', '', $cleaned_query);
        }

        // Pattern: after:YYYY-MM or after:YYYY
        if (preg_match('/\bafter:(\d{4})(?:-(\d{2}))?/i', $cleaned_query, $matches)) {
            $year = intval($matches[1]);
            $month = isset($matches[2]) ? intval($matches[2]) : 1;
            if ($year >= 1990 && $year <= 2100 && $month >= 1 && $month <= 12) {
                // Use date_sortable: YYYYMMDD format
                $after_date = $year * 10000 + $month * 100 + 1;
                $filters[] = 'date_sortable > ' . $after_date;
            }
            $cleaned_query = preg_replace('/\bafter:\d{4}(?:-\d{2})?/i', '', $cleaned_query);
        }

        // Pattern: in:folder (matches against path)
        if (preg_match('/\bin:(\w+)/i', $cleaned_query, $matches)) {
            $folder = strtolower($matches[1]);
            // Escape for Meilisearch filter - path CONTAINS is not directly supported,
            // but we can use path CONTAINS with quotes for substring match
            $filters[] = 'path CONTAINS "' . addslashes($folder) . '"';
            $cleaned_query = preg_replace('/\bin:\w+/i', '', $cleaned_query);
        }

        // Clean up extra whitespace
        $cleaned_query = trim(preg_replace('/\s+/', ' ', $cleaned_query));

        return array(
            'query' => $cleaned_query,
            'filters' => $filters,
        );
    }

    /**
     * Perform a search query
     *
     * @param string $query The search query (may include grammar patterns like type:file year:2024)
     * @param int $limit Maximum results to return
     * @param string $type_filter Filter by content type from UI (file, post, page, or empty for all)
     * @param array $options Additional options: doctype, year, exact_match, sort
     * @return array|WP_Error Search results or error
     */
    public function search($query, $limit = 10, $type_filter = '', $options = array()) {
        if (empty($this->api_url) || empty($this->api_key)) {
            return new WP_Error('not_configured', 'Parish Search is not configured. Please set the API URL and key in Settings.');
        }

        // Parse query for grammar patterns (type:, year:, doctype:, before:, after:, in:)
        $parsed = $this->parse_query($query);
        $search_query = $parsed['query'];
        $grammar_filters = $parsed['filters'];

        $url = $this->api_url . '/indexes/' . $this->index_name . '/search';

        $body = array(
            'q' => $search_query,
            'limit' => min($limit, 100),
            'attributesToRetrieve' => array(
                'id', 'type', 'title', 'content', 'filename', 'path', 'url_prefix',
                'url', 'excerpt', 'date_display', 'document_type', 'page',
                'categories', 'priority', 'event_time', 'event_location'
            ),
            'attributesToHighlight' => array('content', 'title'),
            'highlightPreTag' => '<mark>',
            'highlightPostTag' => '</mark>',
            'attributesToCrop' => array('content'),
            'cropLength' => 150,
        );

        // Handle sort option (default to date_desc)
        $sort_option = isset($options['sort']) ? $options['sort'] : 'date_desc';
        if ($sort_option === 'date_desc') {
            $body['sort'] = array('date_sortable:desc');
        } elseif ($sort_option === 'date_asc') {
            $body['sort'] = array('date_sortable:asc');
        }
        // 'relevance' = no sort parameter, let Meilisearch use relevance scoring

        // Handle hybrid search (skip if exact_match is enabled OR date sort is selected)
        // Hybrid search interferes with date sorting, so only use it for relevance sort
        if (empty($options['exact_match']) && $sort_option === 'relevance') {
            $body['hybrid'] = array(
                'semanticRatio' => 0.2,  // 20% semantic, 80% keyword
                'embedder' => 'openai',
            );
        }

        // Disable typo tolerance for exact match searches
        if (!empty($options['exact_match'])) {
            $body['matchingStrategy'] = 'all';  // Require all words to match
            // Disable typo tolerance entirely for true exact matching
            $body['typoTolerance'] = array(
                'enabled' => false,
            );
        }

        // Build filters - combine grammar filters with UI/settings filters
        $all_filters = array();

        // Check if grammar already includes a type filter
        $has_type_in_grammar = false;
        foreach ($grammar_filters as $gf) {
            if (strpos($gf, 'type =') !== false) {
                $has_type_in_grammar = true;
                break;
            }
        }

        // Add type filter from UI button if specified and not in grammar
        if (!empty($type_filter) && !$has_type_in_grammar) {
            $all_filters[] = 'type = "' . $type_filter . '"';
        } elseif (!$has_type_in_grammar) {
            // Build filter based on enabled content types (from settings)
            $type_options = array();
            if (get_option('parish_search_enable_files', true)) {
                $type_options[] = 'type = "file"';
            }
            if (get_option('parish_search_enable_posts', true)) {
                $type_options[] = 'type = "post"';
            }
            if (get_option('parish_search_enable_pages', true)) {
                $type_options[] = 'type = "page"';
            }
            if (get_option('parish_search_enable_faqs', true)) {
                $type_options[] = 'type = "faq"';
            }
            if (get_option('parish_search_enable_events', true)) {
                $type_options[] = 'type = "event"';
            }

            if (!empty($type_options)) {
                $all_filters[] = '(' . implode(' OR ', $type_options) . ')';
            }
        }

        // Add all grammar filters
        $all_filters = array_merge($all_filters, $grammar_filters);

        // Add doctype filter from UI dropdown (if not already in grammar)
        if (!empty($options['doctype'])) {
            $doctype = strtolower($options['doctype']);
            if (in_array($doctype, $this->valid_doctypes)) {
                // Check if grammar already has a doctype filter
                $has_doctype_in_grammar = false;
                foreach ($grammar_filters as $gf) {
                    if (strpos($gf, 'document_type =') !== false) {
                        $has_doctype_in_grammar = true;
                        break;
                    }
                }
                if (!$has_doctype_in_grammar) {
                    $all_filters[] = 'document_type = "' . $doctype . '"';
                }
            }
        }

        // Add year filter from UI dropdown (if not already in grammar)
        if (!empty($options['year'])) {
            $year = intval($options['year']);
            if ($year >= 1990 && $year <= 2100) {
                // Check if grammar already has a year filter
                $has_year_in_grammar = false;
                foreach ($grammar_filters as $gf) {
                    if (strpos($gf, 'year =') !== false) {
                        $has_year_in_grammar = true;
                        break;
                    }
                }
                if (!$has_year_in_grammar) {
                    $all_filters[] = 'year = ' . $year;
                }
            }
        }

        // Combine all filters with AND
        if (!empty($all_filters)) {
            $body['filter'] = implode(' AND ', $all_filters);
        }

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
            ),
            'body' => json_encode($body),
            'timeout' => 10,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code !== 200) {
            $error_message = isset($data['message']) ? $data['message'] : 'Search request failed';
            return new WP_Error('search_error', $error_message);
        }

        // Format results for display
        $results = array(
            'query' => $query,
            'hits' => array(),
            'total' => isset($data['estimatedTotalHits']) ? $data['estimatedTotalHits'] : 0,
            'processingTimeMs' => isset($data['processingTimeMs']) ? $data['processingTimeMs'] : 0,
        );

        if (isset($data['hits']) && is_array($data['hits'])) {
            foreach ($data['hits'] as $hit) {
                $formatted = isset($hit['_formatted']) ? $hit['_formatted'] : array();

                $result = array(
                    'id' => isset($hit['id']) ? $hit['id'] : '',
                    'type' => isset($hit['type']) ? $hit['type'] : 'unknown',
                    'title' => isset($formatted['title']) ? $formatted['title'] : (isset($hit['title']) ? $hit['title'] : ''),
                    'content' => isset($formatted['content']) ? $formatted['content'] : '',
                    'date' => isset($hit['date_display']) ? $hit['date_display'] : '',
                );

                // Add type-specific fields
                if ($hit['type'] === 'file') {
                    $result['filename'] = isset($hit['filename']) ? $hit['filename'] : '';
                    $result['path'] = isset($hit['path']) ? $hit['path'] : '';
                    $result['url_prefix'] = isset($hit['url_prefix']) ? $hit['url_prefix'] : '/wp-content/uploads/';
                    $result['page'] = isset($hit['page']) ? $hit['page'] : 1;
                    $result['document_type'] = isset($hit['document_type']) ? $hit['document_type'] : '';
                } elseif ($hit['type'] === 'faq') {
                    $result['categories'] = isset($hit['categories']) ? $hit['categories'] : array();
                    $result['priority'] = isset($hit['priority']) ? $hit['priority'] : 10;
                } elseif ($hit['type'] === 'event') {
                    $result['url'] = isset($hit['url']) ? $hit['url'] : '';
                    $result['event_time'] = isset($hit['event_time']) ? $hit['event_time'] : '';
                    $result['event_location'] = isset($hit['event_location']) ? $hit['event_location'] : '';
                } else {
                    $result['url'] = isset($hit['url']) ? $hit['url'] : '';
                    $result['excerpt'] = isset($hit['excerpt']) ? $hit['excerpt'] : '';
                }

                $results['hits'][] = $result;
            }
        }

        return $results;
    }

    /**
     * Test the API connection
     *
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function test_connection() {
        if (empty($this->api_url) || empty($this->api_key)) {
            return new WP_Error('not_configured', 'API URL and key are required');
        }

        $url = $this->api_url . '/health';

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
            ),
            'timeout' => 5,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            return new WP_Error('connection_failed', 'Could not connect to search server');
        }

        return true;
    }

    /**
     * Fetch all documents from the index (requires admin key)
     *
     * @param string $admin_key Admin/master API key
     * @param string $filter Optional filter expression
     * @return array|WP_Error Array of documents or error
     */
    public function fetch_all_documents($admin_key, $filter = '') {
        if (empty($this->api_url) || empty($admin_key)) {
            return new WP_Error('not_configured', 'API URL and admin key are required');
        }

        $documents = array();
        $offset = 0;
        $limit = 1000;

        do {
            $url = $this->api_url . '/indexes/' . $this->index_name . '/documents';
            $url .= '?limit=' . $limit . '&offset=' . $offset;
            $url .= '&fields=id,type,title,filename,path,year,date_display,document_type,url';

            if (!empty($filter)) {
                $url .= '&filter=' . urlencode($filter);
            }

            $response = wp_remote_get($url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $admin_key,
                ),
                'timeout' => 30,
            ));

            if (is_wp_error($response)) {
                return $response;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if ($status_code !== 200) {
                $error_message = isset($data['message']) ? $data['message'] : 'Failed to fetch documents';
                return new WP_Error('fetch_error', $error_message);
            }

            $results = isset($data['results']) ? $data['results'] : array();
            $documents = array_merge($documents, $results);
            $offset += $limit;

        } while (count($results) === $limit);

        return $documents;
    }

    /**
     * Fetch documents with null year (requires admin key)
     *
     * @param string $admin_key Admin/master API key
     * @return array|WP_Error Array of documents or error
     */
    public function fetch_null_year_documents($admin_key) {
        if (empty($this->api_url) || empty($admin_key)) {
            return new WP_Error('not_configured', 'API URL and admin key are required');
        }

        $documents = array();
        $offset = 0;
        $limit = 1000;

        do {
            $url = $this->api_url . '/indexes/' . $this->index_name . '/search';

            $body = array(
                'q' => '',
                'filter' => 'year IS NULL OR year IS EMPTY',
                'limit' => $limit,
                'offset' => $offset,
                'attributesToRetrieve' => array('id', 'type', 'title', 'filename', 'path', 'year', 'date_display', 'document_type', 'url'),
            );

            $response = wp_remote_post($url, array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $admin_key,
                ),
                'body' => json_encode($body),
                'timeout' => 30,
            ));

            if (is_wp_error($response)) {
                return $response;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if ($status_code !== 200) {
                $error_message = isset($data['message']) ? $data['message'] : 'Failed to fetch documents';
                return new WP_Error('fetch_error', $error_message);
            }

            $results = isset($data['hits']) ? $data['hits'] : array();
            $documents = array_merge($documents, $results);
            $offset += $limit;

        } while (count($results) === $limit);

        return $documents;
    }
}
