=== ReactWoo Geo AI ===
Contributors: reactwoo
Requires at least: 6.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Stable tag: 0.1.16.0

AI-assisted geo variant drafts. Requires ReactWoo Geo Core.

== Description ==

This plugin extends the geo platform with AI workflows (draft variants via ReactWoo API). It does not replace Geo Core detection or licensing rules documented for Core.

== Installation ==

1. Install and activate **ReactWoo Geo Core**.
2. Upload and activate this plugin.

== Changelog ==

= 0.1.16.0 =
* **Admin:** **Top-level Geo AI menu** (Overview, License & API, Help). No longer nested under Geo Core sidebar.
* **Geo Core dashboard:** Summary card when Geo AI is active.
* **UX:** Merchant-first overview; **Technical details** for REST URLs, pages, hooks; **rwga-admin.css** + Geo Core styles.

= 0.1.14.0 =
* **Admin UI:** Dashboard and **Geo AI License** screens use Geo Core shared styles (**`rwgc-wrap`**, **`rwgc-inner-nav`**, **`rwgc-card`**) for consistency with Geo Core and Geo Elementor-style section navigation.
* **Navigation:** Registers **Geo AI** and **Geo AI License** on **`rwgc_inner_nav_items`** so both pages appear in the Geo Core menu bar tabs.
* **License:** Dedicated **Geo AI → License** screen for ReactWoo API base and product key (credentials stay out of Geo Core settings).

= 0.1.12.0 =
* **Dashboard:** **REST API v1 base** URL (when Core REST is enabled); **Test variant-draft REST (validation only)** — local `rest_do_request` POST with no `page_id` (expects HTTP 400, no external AI call). Orchestrator not required for that check.

= 0.1.11.0 =
* **Dashboard:** **REST location (visitor)** link + URL when Geo Core exposes **`rwgc_get_rest_location_url()`**.

= 0.1.10.0 =
* **Dashboard:** **REST capabilities (discovery)** link + URL when Geo Core exposes **`rwgc_get_rest_capabilities_url()`**.

= 0.1.9.0 =
* **Block editor:** **Open in new tab** next to **Copy URL** (same variant-draft REST URL).

= 0.1.8.0 =
* **Block editor:** **`wp_set_script_translations`** for **`rwga-block-editor`**; **Copy URL** button (clipboard + fallback) with short “Copied!” feedback.

= 0.1.7.0 =
* **Block editor (pages):** document sidebar panel **Geo AI** shows the **`ai/variant-draft`** REST URL when Geo Core REST is enabled (`RWGA_Block_Editor`).

= 0.1.6.0 =
* Dashboard **Editor workflow (pages)** — links to all pages and add new page (variant drafts are page-scoped).

= 0.1.5.0 =
* **Assistant token usage** table on the dashboard: caches successful **Test authenticated assistant usage** responses (tier, period, used/limit/remaining). Filter **`rwga_usage_display_rows`**.

= 0.1.4.0 =
* Dashboard **Integration snapshot** table (`RWGA_Stats::get_snapshot()`).

= 0.1.3.0 =
* **`RWGA_Stats::get_snapshot()`** and filter **`rwga_stats_snapshot`** for integrations (version, site, UTC time).

= 0.1.2.0 =
* Dashboard: read-only **connection** summary (API base, license set/not set, REST on/off from Geo Core). Buttons **Test AI service reachability** and **Test authenticated assistant usage** (same behavior as Geo Core → Tools). `RWGA_Connection::get_summary()` for extensions.

= 0.1.1.0 =
* Admin: **Geo Core → Geo AI** dashboard (links to Core settings, Tools, Usage; documents `ai/variant-draft` and filters).
