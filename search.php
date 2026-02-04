<?php
/**
 * Plugin Name: Search
 * Plugin URI: https://github.com/nonatech-uk/wp-search
 * Description: Search parish documents and content using Meilisearch
 * Version: 1.2.2
 * Author: NonaTech Services Ltd
 * License: GPL v2 or later
 * Text Domain: search
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('PARISH_SEARCH_VERSION', '1.2.2');
define('PARISH_SEARCH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PARISH_SEARCH_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once PARISH_SEARCH_PLUGIN_DIR . 'includes/class-parish-search.php';
require_once PARISH_SEARCH_PLUGIN_DIR . 'includes/class-parish-search-api.php';
require_once PARISH_SEARCH_PLUGIN_DIR . 'includes/class-github-updater.php';

/**
 * Initialize the plugin
 */
function parish_search_init() {
    $plugin = new Parish_Search();
    $plugin->init();

    // Initialize GitHub updater
    if (is_admin()) {
        new Search_GitHub_Updater(
            __FILE__,
            'nonatech-uk/wp-search',
            PARISH_SEARCH_VERSION
        );
    }
}
add_action('plugins_loaded', 'parish_search_init');

/**
 * Activation hook
 */
function parish_search_activate() {
    // Set default options
    $defaults = array(
        'api_url' => '',
        'api_key' => '',
        'results_per_page' => 10,
        'enable_files' => true,
        'enable_posts' => true,
        'enable_pages' => true,
        'enable_faqs' => true,
        'enable_events' => true,
    );

    foreach ($defaults as $key => $value) {
        if (get_option('parish_search_' . $key) === false) {
            add_option('parish_search_' . $key, $value);
        }
    }
}
register_activation_hook(__FILE__, 'parish_search_activate');

/**
 * Deactivation hook
 */
function parish_search_deactivate() {
    // Nothing to clean up for now
}
register_deactivation_hook(__FILE__, 'parish_search_deactivate');
