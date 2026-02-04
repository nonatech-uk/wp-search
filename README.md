# Parish Search - WordPress Plugin

A WordPress plugin for searching parish documents and content using Meilisearch.

## Features

- Full-text search across documents, posts, and pages
- Live search with debouncing
- Filter by content type (Documents, News, Pages)
- Search grammar for advanced queries
- Accessible search bar shortcode for embedding anywhere
- Auto-search from URL parameters

## Installation

1. Upload the `parish-search` folder to `/wp-content/plugins/`
2. Activate the plugin in WordPress admin
3. Go to Settings > Parish Search to configure

## Configuration

### Settings

| Setting | Description |
|---------|-------------|
| Search API URL | Meilisearch URL (e.g., `http://meilisearch:7700`) |
| Search API Key | Read-only search API key |
| Results per page | Default number of results |
| Content Types | Enable/disable files, posts, pages |

## Shortcodes

### Full Search Form

```
[parish_search]
[parish_search placeholder="Search documents..." limit="20"]
```

Displays a complete search form with filters and results area.

**Attributes:**
- `placeholder` - Input placeholder text (default: "Search documents...")
- `limit` - Results per page (default: from settings)

**URL Parameter:** Add `?q=search+term` to pre-fill and auto-search.

### Compact Search Bar

```
[parish_search_bar]
[parish_search_bar action="/search/" placeholder="Search..."]
```

Displays a compact search form that redirects to a search page.

**Attributes:**
- `action` - URL to redirect to (default: `/search/`)
- `placeholder` - Input placeholder text (default: "Search documents...")

## Search Grammar

Refine searches using these patterns:

| Pattern | Example | Description |
|---------|---------|-------------|
| `type:` | `type:file` | Filter by content type (`file`, `post`, `page`, `document`, `news`) |
| `year:` | `year:2024` | Filter by year |
| `doctype:` | `doctype:minutes` | Filter by document type (`minutes`, `agenda`, `policy`, `planning`, `finance`) |
| `before:` | `before:2024` | Documents before date |
| `after:` | `after:2023-06` | Documents after date |
| `in:` | `in:council` | Search within folder path |

### Examples

```
budget year:2024                    # "budget" from 2024
"annual report" type:file           # Files containing "annual report"
planning doctype:minutes            # Planning-related minutes
grants after:2023 before:2025       # Grants from 2023-2024
policy in:council                   # Policies in Council folder
```

## Template Customization

The plugin uses these template files:

- `templates/search-form.php` - Main search form template

To override, copy to your theme's `parish-search/` folder.

## CSS Classes

| Class | Description |
|-------|-------------|
| `.parish-search-container` | Main wrapper |
| `.parish-search-form` | Search form |
| `.parish-search-input` | Text input |
| `.parish-search-button` | Submit button |
| `.parish-search-filters` | Filter buttons container |
| `.parish-search-filter` | Individual filter button |
| `.parish-search-results` | Results container |
| `.parish-search-result` | Single result item |
| `.parish-search-bar` | Compact search bar form |

## Filter Bar

The search form includes a filter bar with these controls:

| Control | Description |
|---------|-------------|
| Doc Type | Filter documents by type: Minutes, Agenda, Policy, Planning, Finance, Other |
| Year | Filter by year (current year down to 2020) |
| Exact match | Disable AI/semantic search for precise keyword matching |
| Sort | Sort by Relevance, Date (newest), or Date (oldest) |

**Notes:**
- Doc Type filter only applies to Documents - selecting a doc type auto-selects the Documents filter
- Date sorting disables hybrid/semantic search to ensure proper date ordering
- Relevance sorting uses AI-powered semantic search (20% semantic, 80% keyword)

## AJAX Endpoint

The plugin registers an AJAX endpoint for search:

**Action:** `parish_search`

**Parameters:**
- `query` - Search query (may include grammar patterns)
- `limit` - Maximum results
- `type` - Content type filter from UI
- `doctype` - Document type filter (minutes, agenda, policy, planning, finance, other)
- `year` - Year filter
- `sort` - Sort order (relevance, date_desc, date_asc)
- `exact_match` - Disable semantic search (0 or 1)
- `nonce` - Security nonce

## Requirements

- WordPress 5.0+
- PHP 7.4+
- Meilisearch server with indexed documents

## Changelog

### 1.2.1
- Auto-select Documents filter when doc type is chosen

### 1.2.0
- Added filter bar with Doc Type, Year, Sort, and Exact Match controls
- Added date sorting (newest/oldest)
- Added exact match option to disable semantic search
- Hybrid search now only used with Relevance sort for proper date ordering

### 1.0.3
- Added search grammar (type:, year:, doctype:, before:, after:, in:)
- Added year and path filterable attributes

### 1.0.2
- Added `[parish_search_bar]` shortcode
- Added URL parameter (`?q=`) support for pre-filled searches
- Added auto-search on page load

### 1.0.1
- Fixed document links to use Caddy-served path
- Version bump for cache busting

### 1.0.0
- Initial release

## License

GPL v2 or later
