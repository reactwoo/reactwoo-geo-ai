=== ReactWoo Geo AI ===
Contributors: reactwoo
Requires at least: 6.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Stable tag: 0.4.132

AI-assisted geo variant drafts. Requires ReactWoo Geo Core.

== Description ==

This plugin extends the geo platform with AI workflows (draft variants via ReactWoo API). It does not replace Geo Core detection or licensing rules documented for Core.

== Installation ==

1. Install and activate **ReactWoo Geo Core**.
2. Upload and activate this plugin.

== Changelog ==

= 0.4.130 =
* **Geo Assistant — create-rule journey:** Execute applies client card resolutions before validation so resolved popup targets and Google Ads mappings can create draft rules. Action cards expose `can_execute`; created rules store assistant source metadata; journey regression tests added.

= 0.4.129 =
* **Geo Assistant — resolution applier:** `RWGA_Card_Resolution_Applier` now applies Google Ads / traffic-source UTM mapping from Action Review, updates OR-group child status, and rebuilds cards for execute gating. Regression tests for Free Delivery popup mapping and target resolution.

= 0.4.124 =
* **Geo Assistant — create-rule include/exclude parsing:** Compound instructions such as “create a rule for the Free Delivery popup… show only in IE/UK but do not show FR/DE… also trigger from Google Ads or URL contains /winter-sale” now stay a single `create_rule` action with separated include/exclude countries, page type and device conditions, Google Ads + URL OR trigger groups, and confirmation-only metadata. Prevents fallback to a flat “Hide from all countries” country rule.

= 0.4.121 =
* **Geo Assistant — per-condition resolution model:** Action cards now expose a normalised `condition_rows` list (location, weather, audience, device, URL, UTM, visitor) plus a `logic` operator, so every detected condition can be reviewed and resolved on its own. Ambiguous nation names (England/Scotland/Wales/Northern Ireland) are no longer silently converted: when the command explicitly asks to clarify (e.g. "if England is unclear, ask me whether I mean United Kingdom country targeting or England region targeting"), the location becomes a resolvable decision with *country*, *region*, and *remove* options. "audience matches any" is surfaced as an explicit any-audience vs. selected-groups decision instead of a synced-list-not-found error, and weather (e.g. "sunny") is kept as detected rather than mapped to a different condition. The resolution applier applies country/region picks, any-audience, and per-action match logic before execution; the interpret response also returns an `actions` alias and a coarse `source` badge (local_parser/local_memory/remote_memory/ai_fallback/clarification).

= 0.4.119 =
* **Geo Assistant — inherited & shared targets:** Variant clauses such as "create two new versions of the same category page: one for mobile users in Finland and the other for desktop users in Denmark" now inherit the previous named target ("ski jackets category page") instead of collapsing to a generic "category" page. Inherited targets carry `inheritedFrom` and are blocked (not "ready") while the source target is unresolved. Every unresolved target also carries a stable `dependencyId`, and the plan exposes a `shared_targets` list grouping the same raw target across actions (with `linkedActions`) so it can be resolved once and applied everywhere it is used.

= 0.4.118 =
* **Geo Assistant — server-side plan executor:** Confirming a reviewed multi-action plan now builds real Geo Core entities instead of only redirecting. Each action becomes a **draft** visibility rule via the Geo Core rule library; variant / original-targeting actions create a draft rule plus a listed manual step; preview/test actions create nothing. Field-level card resolutions (choose synced entity, ignore, remove action) are applied server-side, the plan is re-validated, and execution is hard-gated (HTTP 409) while any required mapping is still unresolved. Include/exclude condition groups convert to portable conditions with the correct visibility mode (`show_if`/`hide_if`); regions, raw URLs, and unsupported visitor states are reported as manual follow-ups rather than dropped silently. Proposals that carry an action plan are now persisted even while unresolved so resolutions can be submitted to the execute endpoint.

= 0.4.117 =
* **Geo Assistant — action review model:** The planner now emits a structured per-action card model (`action_cards`) so each detected action is reviewable on its own. Targets are validated against known site registries (WordPress pages + WooCommerce product categories, plus any pages/categories/popups/banners/products supplied via context) with fuzzy suggestions; campaigns and audiences keep their synced resolution status. Each action exposes `target`/`campaign`/`audiences` as `{raw, resolved, status, suggestions}` plus a `requiredResolutions` list and a `needs_resolution`/`ready` status, and the plan reports `fields_needing_attention` and `requires_resolution` and refuses to be executable while any required mapping is unresolved.
* **Parser fixes:** A leading campaign/scope preamble such as "For the Spring Promo campaign, …" is re-attached to the following action instead of becoming its own action; "same category page" (and "same page"/"it") now inherit the previous valid target and are flagged when that inherited target is itself unresolved; exclusion clauses like "but exclude visitors from utm_source=email" stay as exclusion conditions on the relevant action. "VIP buyers" is now recognised as an audience phrase. The example multi-action command now produces 4 actions, not 5.

= 0.4.116 =
* **Geo Assistant:** Each unresolved synced audience/campaign now carries the action it belongs to. Clarification rows include `action_index` and `target_label`, and the legacy interpreter result surfaces these as action-scoped `ambiguities`, so the "Choose location/audience" panel and chat clearly state which action or rule each selection is for.

= 0.4.115 =
* **Geo Assistant:** Audiences and ad campaigns are no longer invented from natural language. Phrases like "VIP customers", "returning visitors", or "new year campaign" resolve against the site's synced audience/campaign registry (GA4, Google Ads, Meta Ads, CRM). Exact matches resolve to the synced entry; fuzzy matches are offered as suggestions only (never auto-applied); anything unmatched is returned as an unresolved candidate and the plan becomes `needs_clarification` with "Choose audience/campaign" options. Native visitor states (logged-in / guest) map to Geo Core's real `logged_in` condition instead of a made-up audience. UTM conditions remain literal. The setup panel now shows matched audiences, visitor states, campaigns, and unresolved items with suggested matches.

= 0.4.114 =
* **Geo Assistant:** Detect "update the existing [name] rule" as `update_rule` targeting a named rule (not a generic page/hide); recognise "logged-in customers" audiences and "exclude anyone arriving from utm_source=…" UTM exclusions; classify "X category page" as `category_page`. Side panel now shows every detected condition type (target, visibility, devices, audiences, weather, URL/UTM, exclusions) and fails safe when an existing-rule update is misclassified.

= 0.4.106 =
* **Geo Assistant:** Fix variant plan clause splitting — attach countries per “one / the other” clause instead of merging globally; support `one variant should` and `the other in`; detect shop vs homepage page mismatch; resolve England on source clauses.

= 0.4.105 =
* **Geo Assistant:** Ambiguity detection and confirmation flow — no silent England→UK (or similar) mappings; memory/AI fallback with likely interpretation and alternatives; `needs_confirmation` status blocks execution until user confirms; confirm-interpretation REST endpoint; learning stores ambiguity resolutions.

= 0.4.104 =
* **Geo Assistant:** Visible inferred split for partial interpretations (`inferred_plan` API); confirm-split REST endpoint; status-aware action buttons; learning events for accepted/corrected splits.

= 0.4.103 =
* **Geo Assistant:** Mixed country+weather variant plans with per-clause parsing; interpretation status (`complete`/`needs_clarification`) and `can_execute` gating; memory/AI escalation before weak local proposals; parser hints and learning promotion from accepted interpretations.

= 0.4.102 =
* **Geo Assistant:** Clause-boundary variant plan splitting (`and one should`, `one for`, etc.) before country extraction; distinguish create-N-variants vs variations; optional interpreter debug logs (`RWGA_DEBUG_INTERPRETER` or `rwga_debug_interpreter` option).

= 0.4.101 =
* **Geo Assistant:** Parse “create N new variant” plans with “one/the other should display” segments; add Ireland to bundled countries; defer intelligence sync to init (WP 6.7 textdomain notices).

= 0.4.100 =
* **Fix:** Restore missing closing brace in assistant service (fatal parse error on load).

= 0.4.99 =
* **Geo Assistant:** Structured variant plan parser for natural-language multi-variant commands; interpretation memory layer (phrase shapes, local cache, API memory-match) before AI fallback; learning feedback wiring and layer trace debug.

= 0.4.94 =
* **Command intelligence:** Local phrase-to-action interpreter using shared ReactWoo API intelligence bundles; sync service, context resolver, learning events, admin test screen, REST endpoints, and offline fallback bundle.

= 0.4.93 =
* **Site intelligence quota:** Show monthly upload used/limit on the wizard; skip cron when quota is exhausted; promote audit-only when a prior sync exists.
* **Snapshot errors:** Quota failures display used/limit from the API.

= 0.4.91 =
* **Geo Commerce:** Move weather facet suggest button into the GeoCore WooCommerce product data tab.

= 0.4.78 =

* **Builder-aware analysis:** Elementor and Gutenberg page structure parsing, UX section classification, structure scoring, and compact `builder_context` for AI workflows. Admin page context inspector for debugging.

= 0.4.77 =

* **Experience builder:** `copy_implement` merges duplicated page content with Geo Core visibility rule context (country, device, campaign, audience). Auto-runs on `ai_adapt` save; remote engine receives `targeting_context` when enabled.

= 0.4.76 =

* **Experience builder handoff:** `ai_adapt` launcher banner, copy-drafts deep link, and prefill page/country on Implementation drafts from suite handoff context.

= 0.4.75 =

* **Site intelligence (Phase 14):** Admin notice on Geo Core / Geo AI screens when pending suggestions exist; Geo Core dashboard card links to the wizard and pending count.
* **Targeted audits:** Wizard section for variant relationship, tracking gap, and optimisation recommendation workflows (Optimise required for the latter).
* **Automation:** Site-wide intelligence workflow keys (`site_audit`, `variant_relationship_audit`, `tracking_gap_audit`, `optimisation_recommendation`) run on schedule with empty input; audit runs recorded in journey state.

= 0.4.74 =
* **Site intelligence wizard:** New guided hub under **Insights → Site intelligence** with progress bar, step-by-step checklist, **Run automated setup** (sync + site audit), and optional **auto-audit after sync**.
* **Navigation:** Cloud intelligence and Intelligence actions remain as detail screens linked from the wizard.

= 0.4.73 =
* **Navigation:** Cloud intelligence and Intelligence actions appear under **Insights** in the Geo platform shell (were registered but hidden with `is_section_nav = false`).
* **License:** After a successful intelligence sync, quick links to Cloud intelligence and Intelligence actions.
* **Cloud intelligence:** **Run site audit** button runs the remote `site_audit` workflow and routes pending suggestions to Intelligence actions for approve/dismiss.
* **Advanced:** Clarify that execution mode applies to page UX analyses; site intelligence audits always use the remote API when Remote or Remote with fallback is selected.

= 0.4.72 =
* **License / site intelligence:** Show live sync readiness and block reasons (not only stale stored status). Refresh usage and license save now auto-retry intelligence sync when pre-flight checks pass.

= 0.4.71 =
* **Cloud intelligence:** Split relationship graph into Pro targeting links and core/satellite edges; show experiment and commerce rule counts.

= 0.4.70 =
* **Cloud intelligence:** Relationship graph summary includes GeoCore Pro campaign, audience, and profile counts when present in synced snapshots.

= 0.4.69 =
* **Geo Optimise handoff:** Cloud intelligence and Intelligence actions link to Create Test with prefilled fields from optimisation intelligence runs (`RWGA_Intelligence_Optimise_Handoff`).

= 0.4.64 =
* **i18n:** Queue textdomain via Geo Core `RWGC_I18n` on `plugins_loaded` priority 6 (WP 6.7 JIT fix with Geo Core 1.8.29).

= 0.4.63 =
* **Suite release:** Aligned with Geo Core 1.7.9 contextual admin shell (integration categories, scoped Insights nav).

= 0.4.62 =
* **Admin IA:** Insights labels and section registration aligned with Geo Core intent-based nav; internal/detail routes hidden from section tabs.

= 0.4.60 =
* **Admin hub:** Geo AI uses Geo Core shell helpers (`rw_geo_register_admin_submenu`, `rw_geo_render_inner_nav`, hub breadcrumb). Detail screens hidden from wp-admin sidebar under Geo Core; navigate via in-page tabs. Filter: `rwga_inner_nav_items`.

= 0.4.59 =
* **Admin:** Geo AI screens register as **submenus under Geo Core** (`rwgc-dashboard`) instead of a separate top-level menu. `load-*` hooks for license/advanced snapshots updated to `rwgc-dashboard_page_*`. `?page=rwga-*` URLs unchanged.

= 0.4.58 =
* **Repo:** Add `AGENTS.md` and `.cursor/rules` for satellite build/release alignment with Geo Commerce; ignore `.phpunit.result.cache` in `.gitignore`.

= 0.4.34 =
* **Disconnect state guard:** while Geo AI bridge-block (`rwga_block_core_license_bridge`) is active, UI/config checks now force disconnected and ignore any stale in-request license key memo. This prevents the Settings badge from staying Connected after a Disconnect click due to fallback/cache timing.

= 0.4.33 =
* **Login diagnostics:** records `token_source_detail` from `/api/v5/auth/login` (for `api_stub` responses) so WP logs show why upstream token mint failed, e.g. `missing_secret`, `missing_license_domain`, `upstream_http_401`, `upstream_http_503`, or `upstream_network_error`.

= 0.4.32 =
* **Disconnect UI:** On `rwga_disconnected` / import redirects, reset DB option memos and JWT cache on `admin_init` (priority 0) so “Connected” updates in one load without a second click.

= 0.4.31 =
* **License API trace:** Successful login logs `token_source` and `login_message` from `/api/v5/auth/login` when present. **`token_source: api_stub`** means api.reactwoo.com minted a local HS256 token (tiers default to **free**) because `POST …/v1/plugin/access-token` did not return a token — fix `RW_API_TO_LICENSE_SECRET`, `LICENSE_DOMAIN`, and license server. **`license_server`** means the JWT came from react-license (tier follows package / inferAssistantTier there).

= 0.4.30 =
* **Plugin updates diagnostics:** `HTTP 0` from the last `/api/v5/updates/check` row means WordPress **did not send** a request (commercial updater skipped because there was no `Authorization` bearer). The UI now labels that case explicitly. Stale “No license JWT / rwga_no_license” rows are dropped when a cached JWT exists or a bearer can be obtained (e.g. after a key was added). Successful **Refresh usage** now busts the `update_plugins` transient and clears that stale row when applicable.
* **JWT before update transient:** `pre_set_site_transient_update_plugins` (priority 5) primes `get_access_token()` so Geo Core’s satellite updater is less likely to see an empty bearer when earlier warm hooks did not run (e.g. some cron timings).
* **License screen:** Short note when the JWT tier is `free` and package lines are empty pointing admins at ReactWoo License / API JWT claims for paid Geo AI plans.

= 0.4.29 =
* **License / disconnect (UX):** Clarified on License and Advanced that saving with an **empty** license field **keeps** the current key; only **Disconnect** (admin-post) clears it. Documented this in {@see RWGA_Settings::sanitize_settings()} so it is obvious the behavior is intentional, not a bug. Advanced → API section now includes **Disconnect** when a key is configured, with redirect back to Advanced; handler accepts optional `rwga_disconnect_redirect` (`license` default, `advanced`).

= 0.4.28 =
* **License / refresh:** `is_license_configured_for_geo_ai_ui()` now uses {@see RWGA_Platform_Client::is_configured()} (DB-backed key) instead of only the Settings memo. A stale memo previously made “Refresh usage” think there was no key and run `RWGA_License_State::clear_all( 'ai_usage_no_license' )`, which wiped the snapshot and felt like a failed disconnect or “back to free” after refresh.
* **Diagnostics:** Optional API trace lines to `debug.log` when `WP_DEBUG` + `WP_DEBUG_LOG` are on, or when `RWGA_LICENSE_API_TRACE` is true / filter `rwga_license_api_trace`. Logs login URL/domain/product slug, HTTP result, JWT claim summary (tier, packageType, product_slug, monthly_ai_tokens — no secrets), and usage `licenseTier` + token limit from the API response.

= 0.4.27 =
* **License key for API / updates:** `RWGA_Platform_Client::get_license_key()` now reads `rwga_settings` with a **direct database query first**, then falls back to `RWGA_Settings` / `get_option`. This avoids a stale static memo or object-cache miss yielding an empty key while the row still holds the license — which produced **`rwga_no_license`** on `/api/v5/updates/check` even though usage refresh worked. Per-request memo is cleared with the JWT cache.

= 0.4.26 =
* **Plugin updates (JWT):** Warm the license login cache on `load-plugins.php` / `load-update*.php` / `admin_init` / `wp_update_plugins` **before** WordPress builds `update_plugins`, so `/api/v5/updates/check` usually has a bearer (fixes “HTTP 0 / No license JWT” when the transient was still cold on the Plugins screen).
* **License key lookup:** Fall back to reading `rwga_settings` from the database when the memo path returns empty (edge cases around update checks).
* **Diagnostics:** When the bearer is missing, append the last login error code and message from the platform client.

= 0.4.25 =
* **License / disconnect:** Saving settings with an empty key no longer restores a previous key when the stored key is already empty (disconnect + object-cache edge cases). License/Advanced admin screens reset the DB snapshot memo on load so “Connected” clears in one step.
* **Plans:** JWT tier (and `monthly_ai_tokens` when tier claims are empty) overrides a stale usage API `free` label; large token caps override `free` from the API. Plan line no longer uses the “Free — …” marketing prefix when the only source is the usage API `free` string — shows allowance + confirm copy instead.

= 0.4.24 =
* **License state:** Single canonical option `rwga_license_state` (`RWGA_License_State`) for usage/tier/package snapshot; disconnect/import clear it atomically with legacy usage cache and JWT cache.
* **Plans:** Removed incorrect “Free” fallbacks when the API omits tier — unknown/unlabeled tiers show token allowance or “package unavailable” copy instead of inventing a free plan.
* **Admin:** Debug logging for license transitions when `WP_DEBUG` + `WP_DEBUG_LOG` are on; stacked `.rwgc-card` sections get grid gap + adjacent margin in `rwga-admin.css`.

= 0.4.23 =
* **Updates:** Register `RWGC_Satellite_Updater` before the workflow engine loads so a DB/workflow failure cannot block `/api/v5/updates/check`.
* **Settings:** “Plugin updates” section shows the last `/updates/check` HTTP status and a short explanation (401 = JWT/domain/product mismatch; 200 + no update = already current, rollout, or catalog). Hooks require Geo Core **1.3.21+**.

= 0.4.22 =
* **Updates:** Clear WordPress’s `update_plugins` site transient when the Geo AI license key or ReactWoo API base URL changes (and on disconnect) so `RWGC_Satellite_Updater` can see new releases without waiting hours for the default cache TTL.

= 0.4.21 =
* **CI:** Publish workflow POSTs `/api/v5/updates/publish` with Python `urllib` and `Content-Type: application/json` (no `charset=`) so OpenResty accepts the request; curl + `charset=utf-8` was returning HTML **415 Unsupported Media Type** at the edge.

= 0.4.18 =
* **License diagnostics:** Decode the cached plugin JWT (read-only) and show domain, product/catalog slugs, package/plan fields, token tier vs usage API tier; compare site host to token domain and Geo AI slug (`reactwoo-geo-ai`) so you can verify the license server response matches this product and domain.
* **Usage cache:** Store raw usage `licenseTier` before limit-based inference plus JWT snapshot fields when refreshing usage.

= 0.4.17 =
* **License / disconnect:** Read the saved key and bridge flag from the database for connection state, JWT login metadata, and the final license filter so a persistent object cache cannot keep showing **Connected** and stale **Free** usage after **Disconnect** (wp_options cache bust + optional `wp_cache_flush_group( 'options' )` on clear).

= 0.4.16 =
* **License / layout:** Save + Disconnect use a higher-specificity flex row so Geo Core’s `.rwgc-actions` wrap rule cannot stack Disconnect under Save.
* **Disconnect:** Also clear `notoptions` and the bridge option from the options cache so a single POST reliably shows “Not configured” with persistent object cache.
* **Plan label:** Infer **Pro** for token limits between the free cap (100k) and 1M — previously an empty/stale tier in that range defaulted to **Free** after refresh.

= 0.4.15 =
* **License:** Disconnect clears WordPress `alloptions` cache so the empty key is visible immediately (fixes “two clicks” when object cache served stale `rwga_settings`). No-cache headers on disconnect redirect; Save + Disconnect stay on one row (flex nowrap); disconnect button disables after submit.
* **Usage / plan label:** Unwrap double-nested `data.data` usage JSON; plan line upgrades stale **free** tier when token **limit** indicates Pro/Enterprise.

= 0.4.14 =
* **License:** Disconnect is a POST action next to **Save license** (not under Import & usage); clears the saved key in one step with reliable option/cache updates.
* **Settings:** When a key is saved but usage has not been fetched yet, Plan/Usage show a short “not loaded yet” hint instead of looking empty.

= 0.4.13 =
* **Settings → Refresh usage:** Link and handler use **Settings** (`rwga-license`) instead of Advanced; after refresh, redirects back with a short inline notice (no huge JSON in the admin bar).
* **Usage cache:** Broader parsing of `licenseTier` from API payloads; if the tier field is missing, infer **free / pro / enterprise** from returned token **limit** so the Plan line matches paid caps.

= 0.4.12 =
* **License:** Saving the license form redirects back to **Settings** (not Advanced) using `rwga_form_scope`; plan/tier cache refreshes from the API when the saved key changes.
* **Admin UI:** Stacked suite cards use adjacent margin so sections do not touch; license screen split into two cards; competitor filter explains “your page”; Implement SEO drafts use primary suite button; Help workflow actions use consistent spacing.
* **Automation:** Rule field partial loaded on boot so “Add rule” no longer fatals (PHP 8 `foreach` on null).

= 0.4.11 =
* **Admin UI:** Clearer vertical rhythm on the dashboard and other suite screens (card stack spacing via Geo Core `rwgc-suite.css`, section `h2` styling, roomier tables, hero/dev-details spacing in `rwga-admin.css`).

= 0.4.10 =
* **CI:** Publish workflow builds `/api/v5/updates/publish` JSON with Python (proper escaping; `Content-Type: application/json; charset=utf-8`) so OpenResty no longer returns 415.

= 0.4.9 =
* **Admin UI:** Competitor research detail uses suite section headers for metadata and each insight block. List filters and queue fallback use `rwgc-btn`. Automation rules use suite buttons for create/update, run, delete, and cancel.

= 0.4.8 =
* **Admin UI:** Advanced page and competitor research detail aligned with Geo Core suite shell (`rwgc-btn`, `render_section_header`). REST “Open” links and connection checks use suite buttons.

= 0.4.6.2 =
* **License login / usage:** `RWGA_Platform_Client` now runs the same `rwgc_auth_login_body` filter as Geo Core before POST `/api/v5/auth/login`, and `RWGA_Settings::filter_auth_login_body` is registered so the license server receives the same product metadata as the shared client. Assistant usage JSON is normalized with additional shapes (`license_tier`, unwrapped `data`, snake_case usage fields). Admin-only shows “Usage refreshed” when the response was parsed and cached; parse failures surface a clear error instead of a false success.

= 0.4.6.1 =
* **License / usage UI:** After **Disconnect**, cached assistant usage is ignored and removed so the Plan line no longer shows a misleading **Free** tier from stale data. Saving the license form while disconnected no longer restores the previous key when the password field is empty or omitted from POST.

= 0.4.6.0 =
* **Independent licensing:** Geo AI now uses its own platform client, JWT cache, update-auth callback, and license status checks. Automatic migration and shared runtime credential filters are removed; importing a key from another ReactWoo plugin is now an explicit one-time admin action.

= 0.4.5.8 =
* **Disconnect persistence:** Geo AI no longer re-migrates the Geo Core license on normal boot after the site has already saved or disconnected Geo AI settings. The legacy migration now runs only for a brand-new install with no existing Geo AI settings row.

= 0.4.5.7 =
* **License / usage refresh:** Geo AI now treats its own saved key as the source of truth for the License screen. After Disconnect, the badge shows **Not configured** and **Refresh usage** clears cached usage and stops with an error until a new Geo AI key is saved.

= 0.4.5.6 =
* **License → Disconnect:** Final `rwgc_reactwoo_license_key` filter now clears the effective key whenever Geo AI has disconnected (empty own key), even if Optimise/Commerce still hold a migrated copy of the license. Connection summary / “Key on file” use Geo AI–specific `is_license_configured_for_geo_ai_ui()` so the badge matches Disconnect.

= 0.4.5.5 =
* **License → Disconnect:** Persist disconnect with a dedicated option (`rwga_block_core_license_bridge`) so Core fallback is not re-applied after saves/merges; final license filter strips only Geo Core’s key when Optimise/Commerce have no explicit key.

= 0.4.4.0 =
* **Remote UX payload:** For API calls only, `page_context` is replaced by compact **`reading_context`** (`rwga_ai_reading_bundle_from_page_context`) — title, permalink, excerpt, `content_plain`, word count, builder, extraction source, block name list. No duplicate full context blob; lower token use and the model is not asked to “read between” markup. Filter: `rwga_ai_reading_bundle`.

= 0.4.3.0 =
* **AI / builder text:** `content_plain` in page context now uses `rwga_extract_text_for_ai()` — Gutenberg blocks parsed to inner text, shortcodes stripped via `strip_shortcodes`, HTML/`<!-- wp:` comments removed. New `content_plain_source` meta (`gutenberg_blocks`, `gutenberg_fallback_classic`, `classic`). Filter `rwga_extract_text_for_ai` receives the extraction path as a third argument. Reduces models seeing or “fixing” Elementor/Gutenberg/shortcode syntax.

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
