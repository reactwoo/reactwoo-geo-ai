# Changelog

All notable changes to **reactwoo-geo-ai** are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.4.89] - 2026-06-11

### Added
- **Intelligence Centre admin (v3 Phase 16):** `rwga-intelligence-centre` — site context, local graph counts, UX knowledge preview, workflow context inspector with payload audit, recent `rwga_ai_runs`.
- **Insight memory (v3 Phase 14):** `RWGA_Insight_Memory` — option-backed cache checked before `RWGA_Remote_Client::dispatch()`; telemetry `cache_hit` on `rwga_ai_runs`.
- **Model router (v3 Phase 13):** `RWGA_Model_Router` — task-class tier hints (`premium`, `light`, `deterministic`) attached as `model_routing` on remote payloads.
- **Cloud snapshot extension (v3 Phase 10):** `RWGA_AI_Snapshot` appends compact `geo_ai_intelligence` block (page summaries, scores, graph counts) via `rwgc_ai_snapshot_payload`.
- **UX knowledge graph (v3 Phase 9):** `RWGA_Knowledge_Graph` — seeded anonymous benchmarks, optional remote refresh, `benchmark_context` in `RWGA_Context_Builder`.
- **REST:** `GET /wp-json/geo-ai/v1/intelligence/local/knowledge`.

## [0.4.85] - 2026-06-11

### Added
- **Task context builder (v3 Phase 8):** `RWGA_Context_Builder` — workflow-specific intelligence bundles (messaging, UX, visual, semantics, insights, relationship counts) instead of ad-hoc payload assembly.
- **`RWGA_Payload_Guard`** — strips forbidden keys (`_elementor_data`, `post_content`, `ai_page_context`, etc.) before remote AI calls; includes exclusion audit metadata.
- UX analysis, UX recommend, and intelligence workflows now dispatch via context builder.
- **REST:** `GET /wp-json/geo-ai/v1/intelligence/local/context/{workflow}?page_id=` — preview bundle + `remote_ready` payload.
- Local intelligence version **1.6.0**.

## [0.4.84] - 2026-06-11

### Added
- **Local relationship graph (v3 Phase 7):** `RWGA_Relationship_Graph` — builds config graph from Geo Core snapshot (rules, variants, popups, experiments, Pro/commerce edges) and enriches with local page intelligence + UX insight nodes.
- Persisted in `rwga_site_context.context_json.relationship_graph`; rebuilt on site sync and page intelligence refresh.
- **REST:** `GET /wp-json/geo-ai/v1/intelligence/local/graph`; site context endpoint includes `relationship_graph`.
- Local intelligence version **1.5.0**.

## [0.4.83] - 2026-06-11

### Added
- **Builder context extractors (v3 Phase 6):** `RWGA_Elementor_Context_Extractor`, `RWGA_Gutenberg_Context_Extractor`, `RWGA_Classic_Context_Extractor` via `RWGA_Context_Extractor_Registry` — narrative beats, persuasion summary, trust signals, conversion path, and structure gaps (meaning, not widget inventory).
- `RWGA_Page_Context_Builder` now attaches `builder_semantics`; compact API payloads include `builder_semantics`.
- Persists to `context_json.builder_semantics` in local intelligence.
- **REST:** `GET /wp-json/geo-ai/v1/intelligence/local/semantics/{page_id}`; page context includes `builder_semantics`.
- Local intelligence version **1.4.0**.

## [0.4.82] - 2026-06-11

### Added
- **Visual emphasis intelligence (v3 Phase 5):** `RWGA_Visual_Analyzer` — attention flow, CTA emphasis, focus conflicts, and colour role meaning (deterministic).
- Elementor button `public_settings` now exposes normalized colours and interpreted roles.
- Persists to `context_json.visual_intelligence` and `rwga_ux_insights` (source `visual_analyzer`).
- **REST:** `GET /wp-json/geo-ai/v1/intelligence/local/visual/{page_id}`; page context includes `visual_intelligence`.
- Local intelligence version **1.3.0**.

## [0.4.81] - 2026-06-11

### Added
- **UX intelligence (v3 Phase 4):** `RWGA_UX_Insight_Builder` — messaging hierarchy, CTA effectiveness, trust gaps, friction, and mobile heuristics (deterministic).
- Persists to `context_json.ux_intelligence` and `rwga_ux_insights` (source `ux_insight_builder`).
- **REST:** `GET /wp-json/geo-ai/v1/intelligence/local/ux/{page_id}`; page context includes `ux_intelligence`.
- Local intelligence version **1.2.0**.

## [0.4.80] - 2026-06-11

### Added
- **Messaging intelligence (v3 Phase 3):** `RWGA_Messaging_Analyzer` — deterministic promise, UVP, audience, emotional drivers, objections, and clarity scores from builder context (no AI call).
- Persists messaging into `rwga_page_context.context_json.messaging` and `rwga_ux_insights` (source `messaging_analyzer`).
- **REST:** `GET /wp-json/geo-ai/v1/intelligence/local/messaging/{page_id}`; page context endpoint now includes `messaging` block.
- Local intelligence version **1.1.0**.

## [0.4.79] - 2026-06-11

### Added
- **Geo AI Intelligence Platform v3 — Phase 1 audit docs:**
  - `docs/AI-INTELLIGENCE-ARCHITECTURE.md` — repository inventory and gap analysis
  - `docs/AI-PAYLOAD-STRATEGY.md` — current vs target payload pipeline
  - `docs/AI-UX-INSIGHT-CONTRACT.md` — insight schemas (shipped + planned)
  - `docs/AI-KNOWLEDGE-GRAPH.md` — relationship graph (shipped) vs UX knowledge graph (planned)
  - `docs/AI-MODEL-ROUTING.md` — current model selection and v3 routing target
- **Local intelligence layer (v3 Phase 2):** `RWGA_Local_Intelligence` orchestrates site, page, and entity context persistence.
- **Tables (DB 1.4.0):** `rwga_site_context`, `rwga_page_context`, `rwga_entity_context`, `rwga_ux_insights`, `rwga_ai_runs`.
- **Hooks:** `save_post` and `rwga_site_intelligence_synced` refresh local intelligence; `rwga_workflow_persisted` records AI runs and ingests findings.
- **REST:** `GET/POST /wp-json/geo-ai/v1/intelligence/local/*` — site context, page context + insights, runs, manual refresh.

## [0.4.78] - 2026-06-10

### Added
- **Builder-aware page analysis:** `RWGA_Builder_Registry` with Elementor, Gutenberg, and classic adapters under `includes/builders/`.
- **Elementor parser:** Reads `_elementor_data` JSON (sections, containers, columns, nested widgets) without requiring Elementor active.
- **Section classification:** `RWGA_Section_Classifier` (hero, FAQ, pricing, testimonials, etc.) with confidence scores.
- **UX structure scoring:** `RWGA_UX_Structure_Scorer` for hero/CTA/trust/form/hierarchy scores and detected issues.
- **AI context:** `RWGA_Page_Context_Builder` — single trimmed payload for workflows/API (`builder_context` in remote UX payloads).
- **Builder recommendations:** `RWGA_Builder_Recommendations` with widget/section targets; `RWGA_Elementor_Action_Planner` dry-run plans.
- **Blueprints:** `RWGA_Page_Blueprint`, `RWGA_Section_Blueprint`, `RWGA_Widget_Blueprint` intent models.
- **Admin:** Page context inspector (`rwga-page-context-debug`) under Advanced settings.
- **Tests:** PHPUnit suite for Elementor/Gutenberg parsers, classifier, and UX scorer.
- **Docs:** `PLAN.md` architecture and phase tracker.

### Changed
- `RWGA_Page_Context` detects Elementor via builder registry; Elementor widget text feeds `content_plain`.
- UX analysis/recommend workflows send compact `builder_context` to the remote API.

## [0.4.64] - 2026-06-06

### Fixed
- **i18n:** Queue textdomain via Geo Core `RWGC_I18n` on `plugins_loaded` priority 6 (WP 6.7 JIT fix with Geo Core 1.8.29).

## [0.4.63] - 2026-06-06

### Changed
- **Suite release:** Aligned with Geo Core 1.7.9 contextual admin shell.

## [0.4.60] - 2026-06-06

### Changed
- **Admin hub:** Geo AI uses Geo Core shell helpers; detail screens hidden from wp-admin sidebar.

## [0.4.59] - 2026-06-06

### Changed
- **Admin:** Screens register as submenus under Geo Core (`rwgc-dashboard`).

## [0.4.34] - 2026-06-06

### Fixed
- **Disconnect state guard:** Force disconnected UI while bridge-block active; ignore stale license memo.

## [0.4.33] - 2026-06-06

### Added
- **Login diagnostics:** `token_source_detail` from `/api/v5/auth/login` logged for stub failures.

## [0.4.31] - 2026-06-06

### Added
- **License API trace:** Logs `token_source` and `login_message`; documents `api_stub` vs `license_server`.

## [0.4.30] - 2026-06-06

### Fixed
- **Plugin updates diagnostics:** Clarify HTTP 0 (no bearer sent); prime JWT before `update_plugins` transient.

## [0.4.27] - 2026-06-06

### Fixed
- **License key lookup:** Direct DB read first to avoid stale memo on `/updates/check`.

## [0.4.24] - 2026-06-06

### Added
- **License state:** Canonical `rwga_license_state` option; improved tier display without false “Free” labels.

## [0.4.23] - 2026-06-06

### Fixed
- **Updates:** Register `RWGC_Satellite_Updater` before workflow engine; Settings shows last `/updates/check` status.

## [0.4.6.0] - 2026-06-06

### Changed
- **Independent licensing:** Own platform client, JWT cache, update-auth; explicit import only.

## [0.4.4.0] - 2026-06-06

### Changed
- **Remote UX payload:** Compact `reading_context` bundle for lower token use on API calls.

## [0.4.0.0] - 2026-06-06

### Added
- **Remote workflow engine:** Advanced `workflow_engine` setting; `POST /api/v5/geo-ai/workflow`.
- **WP-Cron:** `rwga_automation_cron_tick` every 15 minutes for schedule rules.

## [0.3.0.0] - 2026-06-06

### Added
- **Competitor research** workflow and REST; **Automation** rules CRUD and runner stub.

## [0.2.4.0] - 2026-06-06

### Added
- **SEO implementation** workflow and REST `implement/seo`.

## [0.2.3.0] - 2026-06-06

### Added
- **Copy implementation** workflow and REST `implement/copy`.

## [0.2.2.0] - 2026-06-06

### Added
- **UX recommendations** workflow and REST `recommend/ux`.

## [0.2.0.0] - 2026-06-06

### Added
- **Foundation:** Custom tables, workflow/agent registry, UX analysis stub, REST `analyse/ux`, capabilities.

## [0.1.19.0] - 2026-06-06

### Added
- **Updates:** `RWGC_Satellite_Updater`; `product_slug` on login via `rwgc_auth_login_body`.

---

Full history: `readme.txt`.
