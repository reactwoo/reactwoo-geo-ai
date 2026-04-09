=== ReactWoo Geo AI ===
Contributors: reactwoo
Requires at least: 6.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Stable tag: 0.4.2.0

AI-assisted geo variant drafts. Requires ReactWoo Geo Core.

== Description ==

This plugin extends the geo platform with AI workflows (draft variants via ReactWoo API). It does not replace Geo Core detection or licensing rules documented for Core.

== Installation ==

1. Install and activate **ReactWoo Geo Core**.
2. Upload and activate this plugin.

== Changelog ==

= 0.4.2.0 =
* **Competitor research + remote engine:** When workflow execution mode is remote or remote fallback, `competitor_research` calls the same ReactWoo API route as UX analysis (`POST` `/api/v5/geo-ai/workflow`) and persists the returned snapshot fields.

= 0.4.1.0 =
* **Automation:** Manual and WP-Cron runs now **execute the registered workflow** (impersonating the rule author or an administrator/shop manager/editor with `rwga_run_ai`). Supported inputs: **ux_analysis** (page ID and/or automation page URL), **competitor_research** (competitor URL in rule options). Other workflows: filter `rwga_automation_build_workflow_input`. Memory events include `workflow_dispatch` metadata; schedule timestamps still advance after each run.

= 0.4.0.0 =
* **Remote workflow engine:** Advanced setting `workflow_engine` — local stub, remote-only (`POST` `/api/v5/geo-ai/workflow` via Geo Core JWT), or remote with local fallback. UX analysis persists `remote_run_id` when the API returns it. Filters: `rwga_remote_workflow_path`, `rwga_remote_workflow_body`, `rwga_remote_workflow_response`.
* **WP-Cron:** `rwga_automation_cron_tick` every 15 minutes runs due **active** rules with `trigger_type` **schedule** (`next_run_at` null or past) via `RWGA_Automation_Runner`.

= 0.3.0.0 =
* **Competitor research:** `competitor_research` workflow and **Market Analyst** agent; rows in `rwga_competitor_research` (stub snapshot, no live fetch).
* **REST:** `POST /wp-json/geo-ai/v1/research/competitors`; `GET .../competitor-research` (optional `page_id`); `GET .../competitor-research/{id}`.
* **Admin:** **Competitors** screen — run form, list, detail.
* **Automation:** `RWGA_DB_Automation_Rules` CRUD; `RWGA_Automation_Runner` stub (updates `last_run_at` / `next_run_at`, memory event).
* **REST:** `GET`/`POST /automation/rules`; `GET`/`PATCH`/`DELETE /automation/rules/{id}`; `POST /automation/rules/{id}/run` (requires `rwga_run_ai` + license).
* **Admin:** **Automation** screen — create/edit rules (`rwga_manage_automations`), run/delete, workflow picker from registry.
* **Capabilities:** `RWGA_Capabilities::current_user_can_manage_automations()`.

= 0.2.4.0 =
* **SEO implementation:** `seo_implement` workflow and **SEO Strategist** agent; local stub writes meta, heading outline, and checklist rows to `rwga_implementation_drafts`.
* **REST:** `POST /wp-json/geo-ai/v1/implement/seo` (same JSON shape as copy); `GET .../implementation-drafts` accepts optional `workflow_key` (`copy_implement`, `seo_implement`).
* **Admin:** **Implementation** screen — SEO generate form, workflow column, list filter by workflow; recommendation detail includes **Generate SEO drafts**.

= 0.2.3.0 =
* **Copy implementation:** `copy_implement` workflow and **UX Writer** agent; local stub produces hero, CTA, and trust drafts in `rwga_implementation_drafts`.
* **REST:** `POST /wp-json/geo-ai/v1/implement/copy` (JSON `recommendation_id` and/or `page_id`, optional `geo_target`); `GET .../implementation-drafts` (optional `recommendation_id` filter); `GET .../implementation-drafts/{id}`.
* **Admin:** **Implementation** screen (list, detail, generate form); **Recommendations** titles link to detail with **Generate copy drafts**; recommendation detail view (`rec_id`).

= 0.2.2.0 =
* **UX recommendations:** `ux_recommend` workflow and **UX Strategist** agent; local stub maps analysis findings to structured cards in `rwga_recommendations` (max 12 per run).
* **REST:** `POST /wp-json/geo-ai/v1/recommend/ux` (JSON `analysis_run_id`, optional `business_goal`); `GET .../recommendations` (optional `analysis_run_id` filter); `GET .../recommendations/{id}`.
* **Admin:** **Recommendations** screen with optional analysis-run filter; **Analysis** detail includes **Generate recommendations** form.

= 0.2.1.0 =
* **Page context:** `RWGA_Page_Context` collects Gutenberg block names, plain text excerpt, word count, builder type, and featured-image flag for workflow payloads (filter `rwga_page_context`).
* **UX analysis:** Stub summary uses page context when available.
* **Admin:** **Analyses** submenu — paginated list and run detail with findings; Overview links runs to detail; sample UX redirects to the new detail screen.
* **REST:** `GET /wp-json/geo-ai/v1/analyses` and `GET /wp-json/geo-ai/v1/analyses/{id}` (view permission).

= 0.2.0.0 =
* **Foundation:** Custom tables for analyses, findings, recommendations, drafts, competitors, automations, memory events, and outcomes (`dbDelta`).
* **Workflows:** Bounded workflow registry, agent registry, UX analysis workflow with local deterministic stub (persists runs, findings, memory events).
* **REST:** `POST /wp-json/geo-ai/v1/analyse/ux`, `GET /wp-json/geo-ai/v1/agents` (license + `rwga_run_ai` / view caps).
* **Capabilities:** `rwga_manage_ai`, `rwga_run_ai`, `rwga_view_ai_reports`, `rwga_manage_automations`; admin menu uses view cap; License/Advanced remain `manage_options`.
* **Overview:** Sample UX analysis action and recent analyses table when licensed.

= 0.1.19.0 =
* **Updates:** Registers **`RWGC_Satellite_Updater`** (Geo Core 1.3.4+) — WordPress **Plugins →** update checks use `POST /api/v5/updates/check` with the license JWT; **`download_url`** is R2-signed from the API.
* **License login:** When this plugin supplies the ReactWoo license key, login requests include **`product_slug`** / **`catalog_slug`** (`reactwoo-geo-ai`) via Geo Core **`rwgc_auth_login_body`** (Geo Core 1.3.7+) so the API can attach multi-product claims to the JWT.

= 0.1.18.2 =
* **Suite handoff:** Overview shows context when opened from Geo Suite (`rwgc_handoff`); optional page title and **Open in editor** when `rwgc_variant_page_id` is present (uses `rwgc_get_suite_handoff_request_context()` from Geo Core 1.3.3+).

= 0.1.18.1 =
* **Release:** Patch bump for remote update pipeline (version-only).

= 0.1.18.0 =
* **Overview:** **Geo suite** card — quick links to **Geo Core**, **Geo Elementor** (when active; supports `geo-elementor/` and `GeoElementor/` plugin paths), **Geo Commerce**, **Geo Optimise** when those plugins are active (`RWGC_Admin_UI::render_quick_actions`).

= 0.1.17.0 =
* **IA:** **Overview**, **License**, **Drafts / Queue**, **Advanced**, **Help** — inner nav and submenus aligned with the Geo Suite product brief.
* **License:** Merchant-first screen (**license key**, **usage/plan** when cached, **Refresh usage**, **Disconnect**). **API base URL** removed from the default form; default host is internal unless **`RWGA_REACTWOO_API_BASE`**, filter **`rwga_reactwoo_api_base`**, or **`RWGA_SHOW_API_BASE_UI`** / filter **`rwga_show_api_base_field`** enables the Advanced field.
* **Overview:** Outcome-first dashboard (stat cards via Geo Core **`RWGC_Admin_UI`** when available), quick actions, setup checklist, queue preview; technical checks moved to **Advanced**.
* **Advanced:** REST URLs, capability links, **Check AI connection**, **Refresh usage**, **Validate variant route** (renamed actions), hooks note.
* **Drafts / Queue:** Empty-state shell with filter **`rwga_draft_queue_rows`**.
* **Styles:** Enqueue Geo Core **`rwgc-suite.css`** when present; **`rwga-admin.css`** complements for license/queue spacing.

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
