/**
 * Parish Search - Frontend JavaScript
 */
(function($) {
    'use strict';

    var ParishSearch = {
        currentFilter: '',
        currentDoctype: '',
        currentYear: '',
        currentSort: 'date_desc',
        exactMatch: false,
        debounceTimer: null,

        init: function() {
            this.bindEvents();
            this.updateFilterBarVisibility();
            this.autoSearch();
        },

        updateFilterBarVisibility: function() {
            // Show/hide doctype filter based on whether Documents filter is active
            var $container = $('.parish-search-container');
            var $doctypeGroup = $container.find('.parish-search-doctype-group');

            if (this.currentFilter === 'file' || this.currentFilter === '') {
                $doctypeGroup.show();
            } else {
                $doctypeGroup.hide();
                // Reset doctype when hidden
                this.currentDoctype = '';
                $container.find('#parish-search-doctype').val('');
            }
        },

        autoSearch: function() {
            var self = this;
            // Check for containers with auto-search data attribute
            $('.parish-search-container[data-auto-search="true"]').each(function() {
                var $container = $(this);
                var $form = $container.find('.parish-search-form');
                var query = $container.find('.parish-search-input').val();
                if (query && query.trim()) {
                    self.performSearch($form);
                }
            });
        },

        bindEvents: function() {
            var self = this;

            // Form submit
            $(document).on('submit', '.parish-search-form', function(e) {
                e.preventDefault();
                self.performSearch($(this));
            });

            // Filter clicks
            $(document).on('click', '.parish-search-filter', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var $container = $btn.closest('.parish-search-container');

                $container.find('.parish-search-filter').removeClass('active');
                $btn.addClass('active');

                self.currentFilter = $btn.data('type') || '';
                self.updateFilterBarVisibility();

                // Re-run search if there's a query
                var query = $container.find('.parish-search-input').val();
                if (query.trim()) {
                    self.performSearch($container.find('.parish-search-form'));
                }
            });

            // Document type dropdown change
            $(document).on('change', '#parish-search-doctype', function() {
                var $container = $(this).closest('.parish-search-container');
                self.currentDoctype = $(this).val();

                // Auto-select Documents filter when a doc type is chosen
                if (self.currentDoctype && self.currentFilter !== 'file') {
                    self.currentFilter = 'file';
                    $container.find('.parish-search-filter').removeClass('active');
                    $container.find('.parish-search-filter[data-type="file"]').addClass('active');
                }

                var query = $container.find('.parish-search-input').val();
                if (query.trim()) {
                    self.performSearch($container.find('.parish-search-form'));
                }
            });

            // Year dropdown change
            $(document).on('change', '#parish-search-year', function() {
                var $container = $(this).closest('.parish-search-container');
                self.currentYear = $(this).val();

                var query = $container.find('.parish-search-input').val();
                if (query.trim()) {
                    self.performSearch($container.find('.parish-search-form'));
                }
            });

            // Sort dropdown change
            $(document).on('change', '#parish-search-sort', function() {
                var $container = $(this).closest('.parish-search-container');
                self.currentSort = $(this).val();

                var query = $container.find('.parish-search-input').val();
                if (query.trim()) {
                    self.performSearch($container.find('.parish-search-form'));
                }
            });

            // Exact match checkbox change
            $(document).on('change', '#parish-search-exact-match', function() {
                var $container = $(this).closest('.parish-search-container');
                self.exactMatch = $(this).is(':checked');

                var query = $container.find('.parish-search-input').val();
                if (query.trim()) {
                    self.performSearch($container.find('.parish-search-form'));
                }
            });

            // Live search (debounced)
            $(document).on('input', '.parish-search-input', function() {
                var self = ParishSearch;
                var $input = $(this);
                var $form = $input.closest('.parish-search-form');

                clearTimeout(self.debounceTimer);
                self.debounceTimer = setTimeout(function() {
                    var query = $input.val().trim();
                    if (query.length >= 2) {
                        self.performSearch($form);
                    }
                }, 300);
            });
        },

        performSearch: function($form) {
            var self = this;
            var $container = $form.closest('.parish-search-container');
            var $results = $container.find('.parish-search-results');
            var $button = $form.find('.parish-search-button');

            var query = $form.find('.parish-search-input').val().trim();
            var limit = $form.data('limit') || 10;

            if (!query) {
                $results.html('');
                return;
            }

            // Show loading
            $button.prop('disabled', true);
            $results.html(
                '<div class="parish-search-loading">' +
                '<span class="parish-search-spinner"></span> Searching...' +
                '</div>'
            );

            $.ajax({
                url: parishSearchConfig.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'parish_search',
                    nonce: parishSearchConfig.nonce,
                    query: query,
                    limit: limit,
                    type: self.currentFilter,
                    doctype: self.currentDoctype,
                    year: self.currentYear,
                    sort: self.currentSort,
                    exact_match: self.exactMatch ? '1' : '0'
                },
                success: function(response) {
                    $button.prop('disabled', false);

                    if (response.success) {
                        self.renderResults($results, response.data);
                    } else {
                        self.renderError($results, response.data.message || 'Search failed');
                    }
                },
                error: function() {
                    $button.prop('disabled', false);
                    self.renderError($results, 'Connection error. Please try again.');
                }
            });
        },

        renderResults: function($container, data) {
            if (!data.hits || data.hits.length === 0) {
                $container.html(
                    '<div class="parish-search-no-results">' +
                    'No results found for "' + this.escapeHtml(data.query) + '"' +
                    '</div>'
                );
                return;
            }

            var html = '<div class="parish-search-meta">' +
                'Found ' + data.total + ' result' + (data.total !== 1 ? 's' : '') +
                ' (' + data.processingTimeMs + 'ms)' +
                '</div>';

            for (var i = 0; i < data.hits.length; i++) {
                html += this.renderResult(data.hits[i]);
            }

            $container.html(html);
        },

        renderResult: function(hit) {
            var typeClass = hit.type || 'unknown';
            var typeLabel = hit.type === 'file' ? 'Document' :
                           hit.type === 'post' ? 'News' :
                           hit.type === 'page' ? 'Page' :
                           hit.type === 'faq' ? 'FAQ' :
                           hit.type === 'event' ? 'Event' : hit.type;

            var title = hit.title || hit.filename || 'Untitled';
            var content = hit.content || '';

            // Build link URL
            var linkUrl = '#';
            var linkTarget = '';

            if (hit.type === 'file' && hit.path) {
                // For files, use url_prefix (defaults to /wp-content/uploads/)
                var prefix = hit.url_prefix || '/wp-content/uploads/';
                linkUrl = prefix + encodeURI(hit.path);
                linkTarget = ' target="_blank"';
            } else if (hit.url) {
                linkUrl = hit.url;
            }

            var html = '<div class="parish-search-result parish-search-result-' + typeClass + '">';

            // FAQ: Special Q&A display
            if (hit.type === 'faq') {
                html += '<div class="parish-search-result-header">' +
                    '<span class="parish-search-result-type ' + typeClass + '">' + typeLabel + '</span>' +
                    '<span class="parish-search-result-question">' + title + '</span>' +
                    '</div>';
                if (content) {
                    html += '<div class="parish-search-result-answer">' + content + '</div>';
                }
                // FAQ categories
                if (hit.categories && hit.categories.length > 0) {
                    html += '<div class="parish-search-result-meta"><span>Category: ' + hit.categories.join(', ') + '</span></div>';
                }
            }
            // Event: Show date, time, location prominently
            else if (hit.type === 'event') {
                html += '<div class="parish-search-result-header">' +
                    '<span class="parish-search-result-type ' + typeClass + '">' + typeLabel + '</span>' +
                    '<a href="' + linkUrl + '" class="parish-search-result-title">' + title + '</a>' +
                    '</div>';
                // Event details
                var eventMeta = [];
                if (hit.date) {
                    var dateStr = hit.date;
                    if (hit.event_time) {
                        dateStr += ' at ' + hit.event_time;
                    }
                    eventMeta.push('<span class="parish-search-event-date">' + dateStr + '</span>');
                }
                if (hit.event_location) {
                    eventMeta.push('<span class="parish-search-event-location">' + hit.event_location + '</span>');
                }
                if (eventMeta.length > 0) {
                    html += '<div class="parish-search-result-event-info">' + eventMeta.join('') + '</div>';
                }
                if (content) {
                    html += '<div class="parish-search-result-content">' + content + '</div>';
                }
            }
            // Default: file, post, page
            else {
                html += '<div class="parish-search-result-header">' +
                    '<span class="parish-search-result-type ' + typeClass + '">' + typeLabel + '</span>' +
                    '<a href="' + linkUrl + '" class="parish-search-result-title"' + linkTarget + '>' + title + '</a>' +
                    '</div>';

                if (content) {
                    html += '<div class="parish-search-result-content">' + content + '</div>';
                }

                // Meta info
                var meta = [];
                if (hit.date) {
                    meta.push('<span>Date: ' + hit.date + '</span>');
                }
                if (hit.document_type) {
                    meta.push('<span>Type: ' + hit.document_type + '</span>');
                }
                if (hit.page && hit.page > 1) {
                    meta.push('<span>Page ' + hit.page + '</span>');
                }

                if (meta.length > 0) {
                    html += '<div class="parish-search-result-meta">' + meta.join('') + '</div>';
                }
            }

            html += '</div>';

            return html;
        },

        renderError: function($container, message) {
            $container.html(
                '<div class="parish-search-error">' +
                this.escapeHtml(message) +
                '</div>'
            );
        },

        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    $(document).ready(function() {
        ParishSearch.init();
    });

})(jQuery);
