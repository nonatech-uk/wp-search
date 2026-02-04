<?php
/**
 * Parish Search - Search Form Template
 *
 * @var array $atts Shortcode attributes
 */

if (!defined('ABSPATH')) {
    exit;
}

$placeholder = isset($atts['placeholder']) ? esc_attr($atts['placeholder']) : 'Search documents...';
$limit = isset($atts['limit']) ? intval($atts['limit']) : 10;
$initial_query = isset($atts['initial_query']) ? esc_attr($atts['initial_query']) : '';

$show_files = get_option('parish_search_enable_files', true);
$show_posts = get_option('parish_search_enable_posts', true);
$show_pages = get_option('parish_search_enable_pages', true);
$show_faqs = get_option('parish_search_enable_faqs', true);
$show_events = get_option('parish_search_enable_events', true);

$show_filters = ($show_files + $show_posts + $show_pages + $show_faqs + $show_events) > 1;
?>

<div class="parish-search-container"<?php if ($initial_query): ?> data-auto-search="true"<?php endif; ?>>
    <form class="parish-search-form" data-limit="<?php echo $limit; ?>">
        <input type="text"
               class="parish-search-input"
               placeholder="<?php echo $placeholder; ?>"
               value="<?php echo $initial_query; ?>"
               autocomplete="off">
        <button type="submit" class="parish-search-button">Search</button>
    </form>

    <?php if ($show_filters): ?>
    <div class="parish-search-filters">
        <button type="button" class="parish-search-filter active" data-type="">All</button>
        <?php if ($show_files): ?>
        <button type="button" class="parish-search-filter" data-type="file">Documents</button>
        <?php endif; ?>
        <?php if ($show_posts): ?>
        <button type="button" class="parish-search-filter" data-type="post">News</button>
        <?php endif; ?>
        <?php if ($show_pages): ?>
        <button type="button" class="parish-search-filter" data-type="page">Pages</button>
        <?php endif; ?>
        <?php if ($show_faqs): ?>
        <button type="button" class="parish-search-filter" data-type="faq">FAQs</button>
        <?php endif; ?>
        <?php if ($show_events): ?>
        <button type="button" class="parish-search-filter" data-type="event">Events</button>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="parish-search-filter-bar">
        <div class="parish-search-filter-group parish-search-doctype-group">
            <label for="parish-search-doctype">Doc Type:</label>
            <select id="parish-search-doctype" class="parish-search-select">
                <option value="">All</option>
                <option value="minutes">Minutes</option>
                <option value="agenda">Agenda</option>
                <option value="policy">Policy</option>
                <option value="planning">Planning</option>
                <option value="finance">Finance</option>
                <option value="other">Other</option>
            </select>
        </div>

        <div class="parish-search-filter-group">
            <label for="parish-search-year">Year:</label>
            <select id="parish-search-year" class="parish-search-select">
                <option value="">All</option>
                <?php
                $current_year = intval(date('Y'));
                for ($y = $current_year; $y >= 2020; $y--) {
                    echo '<option value="' . $y . '">' . $y . '</option>';
                }
                ?>
            </select>
        </div>

        <div class="parish-search-filter-group">
            <label class="parish-search-checkbox-label">
                <input type="checkbox" id="parish-search-exact-match"> Exact match
            </label>
        </div>

        <div class="parish-search-filter-group parish-search-sort-group">
            <label for="parish-search-sort">Sort:</label>
            <select id="parish-search-sort" class="parish-search-select">
                <option value="relevance">Relevance</option>
                <option value="date_desc" selected>Date (newest)</option>
                <option value="date_asc">Date (oldest)</option>
            </select>
        </div>
    </div>

    <div class="parish-search-results"></div>
</div>
