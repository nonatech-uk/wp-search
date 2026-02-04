<?php
/**
 * Main Parish Search class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Parish_Search {

    /**
     * Initialize the plugin
     */
    public function init() {
        // Register shortcodes
        add_shortcode('parish_search', array($this, 'render_shortcode'));
        add_shortcode('parish_search_bar', array($this, 'render_search_bar_shortcode'));

        // Register admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Register settings
        add_action('admin_init', array($this, 'register_settings'));

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

        // Register AJAX handlers
        add_action('wp_ajax_parish_search', array($this, 'ajax_search'));
        add_action('wp_ajax_nopriv_parish_search', array($this, 'ajax_search'));

        // Admin-only handlers for exports (using admin_post for direct downloads)
        add_action('admin_post_parish_search_export_all', array($this, 'ajax_export_all'));
        add_action('admin_post_parish_search_export_null_year', array($this, 'ajax_export_null_year'));
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'parish-search',
            PARISH_SEARCH_PLUGIN_URL . 'assets/css/parish-search.css',
            array(),
            PARISH_SEARCH_VERSION
        );

        wp_enqueue_script(
            'parish-search',
            PARISH_SEARCH_PLUGIN_URL . 'assets/js/parish-search.js',
            array('jquery'),
            PARISH_SEARCH_VERSION,
            true
        );

        wp_localize_script('parish-search', 'parishSearchConfig', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('parish_search_nonce'),
        ));
    }

    /**
     * Render the search shortcode
     */
    public function render_shortcode($atts) {
        $atts = shortcode_atts(array(
            'placeholder' => 'Search documents...',
            'limit' => get_option('parish_search_results_per_page', 10),
        ), $atts);

        // Check for ?q= parameter to pre-fill search
        $atts['initial_query'] = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';

        ob_start();
        include PARISH_SEARCH_PLUGIN_DIR . 'templates/search-form.php';
        return ob_get_clean();
    }

    /**
     * Render the compact search bar shortcode for front page
     */
    public function render_search_bar_shortcode($atts) {
        $atts = shortcode_atts(array(
            'action' => '/search/',
            'placeholder' => 'Search documents...',
        ), $atts);

        $action = esc_url($atts['action']);
        $placeholder = esc_attr($atts['placeholder']);
        $unique_id = 'parish-search-bar-' . wp_unique_id();

        ob_start();
        ?>
        <form class="parish-search-bar" action="<?php echo $action; ?>" method="get" role="search">
            <label for="<?php echo $unique_id; ?>" class="screen-reader-text">Search</label>
            <input type="search" id="<?php echo $unique_id; ?>" name="q" placeholder="<?php echo $placeholder; ?>" class="parish-search-bar-input">
            <button type="submit" class="parish-search-bar-button">Search</button>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle AJAX search requests
     */
    public function ajax_search() {
        check_ajax_referer('parish_search_nonce', 'nonce');

        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
        $type_filter = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';

        // Extract new filter parameters
        $doctype = isset($_POST['doctype']) ? sanitize_text_field($_POST['doctype']) : '';
        $year = isset($_POST['year']) ? sanitize_text_field($_POST['year']) : '';
        $sort = isset($_POST['sort']) ? sanitize_text_field($_POST['sort']) : 'date_desc';
        $exact_match = isset($_POST['exact_match']) && $_POST['exact_match'] === '1';

        if (empty($query)) {
            wp_send_json_error(array('message' => 'No search query provided'));
        }

        // Build options array
        $options = array(
            'doctype' => $doctype,
            'year' => $year,
            'sort' => $sort,
            'exact_match' => $exact_match,
        );

        $api = new Parish_Search_API();
        $results = $api->search($query, $limit, $type_filter, $options);

        if (is_wp_error($results)) {
            wp_send_json_error(array('message' => $results->get_error_message()));
        }

        wp_send_json_success($results);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            'Parish Search Settings',
            'Parish Search',
            'manage_options',
            'parish-search',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('parish_search_settings', 'parish_search_api_url');
        register_setting('parish_search_settings', 'parish_search_api_key');
        register_setting('parish_search_settings', 'parish_search_admin_key');
        register_setting('parish_search_settings', 'parish_search_results_per_page', array(
            'type' => 'integer',
            'default' => 10,
            'sanitize_callback' => 'absint',
        ));
        register_setting('parish_search_settings', 'parish_search_enable_files', array(
            'type' => 'boolean',
            'default' => true,
        ));
        register_setting('parish_search_settings', 'parish_search_enable_posts', array(
            'type' => 'boolean',
            'default' => true,
        ));
        register_setting('parish_search_settings', 'parish_search_enable_pages', array(
            'type' => 'boolean',
            'default' => true,
        ));
        register_setting('parish_search_settings', 'parish_search_enable_faqs', array(
            'type' => 'boolean',
            'default' => true,
        ));
        register_setting('parish_search_settings', 'parish_search_enable_events', array(
            'type' => 'boolean',
            'default' => true,
        ));
    }

    /**
     * Export all documents as CSV
     */
    public function ajax_export_all() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        if (!wp_verify_nonce($_GET['_wpnonce'], 'parish_search_export_all')) {
            wp_die('Invalid nonce');
        }

        $admin_key = get_option('parish_search_admin_key', '');
        if (empty($admin_key)) {
            wp_die('Admin API key not configured. Please set it in Settings → Parish Search.');
        }

        $api = new Parish_Search_API();
        $documents = $api->fetch_all_documents($admin_key);

        if (is_wp_error($documents)) {
            wp_die('Error: ' . $documents->get_error_message());
        }

        $this->output_csv($documents, 'parish-search-all-documents.csv');
    }

    /**
     * Export documents with null year as CSV
     */
    public function ajax_export_null_year() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        if (!wp_verify_nonce($_GET['_wpnonce'], 'parish_search_export_null_year')) {
            wp_die('Invalid nonce');
        }

        $admin_key = get_option('parish_search_admin_key', '');
        if (empty($admin_key)) {
            wp_die('Admin API key not configured. Please set it in Settings → Parish Search.');
        }

        $api = new Parish_Search_API();
        $documents = $api->fetch_null_year_documents($admin_key);

        if (is_wp_error($documents)) {
            wp_die('Error: ' . $documents->get_error_message());
        }

        $this->output_csv($documents, 'parish-search-null-year-documents.csv');
    }

    /**
     * Output documents as CSV download
     */
    private function output_csv($documents, $filename) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // Write header row
        fputcsv($output, array('ID', 'Type', 'Title', 'Filename', 'Path', 'Year', 'Date', 'Document Type', 'URL'));

        // Write data rows
        foreach ($documents as $doc) {
            fputcsv($output, array(
                isset($doc['id']) ? $doc['id'] : '',
                isset($doc['type']) ? $doc['type'] : '',
                isset($doc['title']) ? $doc['title'] : '',
                isset($doc['filename']) ? $doc['filename'] : '',
                isset($doc['path']) ? $doc['path'] : '',
                isset($doc['year']) ? $doc['year'] : '',
                isset($doc['date_display']) ? $doc['date_display'] : '',
                isset($doc['document_type']) ? $doc['document_type'] : '',
                isset($doc['url']) ? $doc['url'] : '',
            ));
        }

        fclose($output);
        exit;
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <form action="options.php" method="post">
                <?php
                settings_fields('parish_search_settings');
                ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="parish_search_api_url">Search API URL</label>
                        </th>
                        <td>
                            <input type="url" id="parish_search_api_url" name="parish_search_api_url"
                                   value="<?php echo esc_attr(get_option('parish_search_api_url')); ?>"
                                   class="regular-text" placeholder="https://search.example.com">
                            <p class="description">The URL of your Meilisearch instance (e.g., https://search.example.com:7700)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="parish_search_api_key">Search API Key</label>
                        </th>
                        <td>
                            <input type="password" id="parish_search_api_key" name="parish_search_api_key"
                                   value="<?php echo esc_attr(get_option('parish_search_api_key')); ?>"
                                   class="regular-text">
                            <p class="description">The search-only API key (not the master key)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="parish_search_admin_key">Admin API Key</label>
                        </th>
                        <td>
                            <input type="password" id="parish_search_admin_key" name="parish_search_admin_key"
                                   value="<?php echo esc_attr(get_option('parish_search_admin_key')); ?>"
                                   class="regular-text">
                            <p class="description">The master/admin API key (required for CSV exports)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="parish_search_results_per_page">Results per page</label>
                        </th>
                        <td>
                            <input type="number" id="parish_search_results_per_page" name="parish_search_results_per_page"
                                   value="<?php echo esc_attr(get_option('parish_search_results_per_page', 10)); ?>"
                                   min="1" max="100" class="small-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Content Types</th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="parish_search_enable_files" value="1"
                                           <?php checked(get_option('parish_search_enable_files', true)); ?>>
                                    Search documents/files
                                </label><br>
                                <label>
                                    <input type="checkbox" name="parish_search_enable_posts" value="1"
                                           <?php checked(get_option('parish_search_enable_posts', true)); ?>>
                                    Search posts
                                </label><br>
                                <label>
                                    <input type="checkbox" name="parish_search_enable_pages" value="1"
                                           <?php checked(get_option('parish_search_enable_pages', true)); ?>>
                                    Search pages
                                </label><br>
                                <label>
                                    <input type="checkbox" name="parish_search_enable_faqs" value="1"
                                           <?php checked(get_option('parish_search_enable_faqs', true)); ?>>
                                    Search FAQs
                                </label><br>
                                <label>
                                    <input type="checkbox" name="parish_search_enable_events" value="1"
                                           <?php checked(get_option('parish_search_enable_events', true)); ?>>
                                    Search events
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <hr>
            <h2>Export Data</h2>
            <p>Download index data as CSV (requires Admin API Key to be configured).</p>
            <?php
            $export_all_url = wp_nonce_url(
                admin_url('admin-post.php?action=parish_search_export_all'),
                'parish_search_export_all'
            );
            $export_null_url = wp_nonce_url(
                admin_url('admin-post.php?action=parish_search_export_null_year'),
                'parish_search_export_null_year'
            );
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Download All Documents</th>
                    <td>
                        <a href="<?php echo esc_url($export_all_url); ?>" class="button">
                            Download All Documents (CSV)
                        </a>
                        <p class="description">Export the entire search index as a CSV file.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Download Missing Year</th>
                    <td>
                        <a href="<?php echo esc_url($export_null_url); ?>" class="button">
                            Download Documents Without Year (CSV)
                        </a>
                        <p class="description">Export documents where the year could not be determined from the filename.</p>
                    </td>
                </tr>
            </table>

            <hr>
            <h2>Shortcode Usage</h2>
            <h3>Full Search Form</h3>
            <p>Add the search form to any page or post using:</p>
            <code>[parish_search]</code>
            <p>Optional attributes:</p>
            <ul>
                <li><code>placeholder="Search..."</code> - Custom placeholder text</li>
                <li><code>limit="20"</code> - Override results per page</li>
            </ul>
            <p>Tip: Add <code>?q=search+term</code> to the page URL to pre-fill and auto-search.</p>

            <h3>Compact Search Bar</h3>
            <p>Add a compact search bar (e.g. for the front page) that redirects to a search page:</p>
            <code>[parish_search_bar]</code>
            <p>Optional attributes:</p>
            <ul>
                <li><code>action="/search/"</code> - URL to redirect to (default: /search/)</li>
                <li><code>placeholder="Search..."</code> - Custom placeholder text</li>
            </ul>
        </div>
        <?php
    }
}
