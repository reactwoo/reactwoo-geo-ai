# Changelog

All notable changes to **reactwoo-geo-ai** are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
