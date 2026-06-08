# ReactWoo Geo AI

**Version:** 0.4.66  
**Plugin slug:** `reactwoo-geo-ai`

## Overview

Geo AI extends the ReactWoo Geo platform with AI-assisted workflows: UX analysis, recommendations, copy/SEO implementation drafts, competitor research, and automation rules. Heavy model orchestration runs on **reactwoo-api** when remote mode is enabled; local deterministic stubs operate without external calls for development and offline use. Requires **ReactWoo Geo Core**; licensing via **react-license**.

## Position in family

```
react-license  →  JWT (product_slug: reactwoo-geo-ai)
       ↓
Geo Core  →  Geo AI  →  reactwoo-api (/api/v5/ai/*, /geo-ai/workflow)
```

Geo AI does not replace Geo Core detection or licensing rules. WordPress exposes REST at `/wp-json/geo-ai/v1/` and admin workflows; API keys and token usage limits are enforced server-side.

## Key Features

### Available

- **Workflow registry** with agents (UX Strategist, UX Writer, SEO Strategist, Market Analyst, etc.)
- **Local stub engine** — deterministic UX analysis, recommendations, copy/SEO drafts, competitor snapshots
- **Remote workflow engine** — `POST /api/v5/geo-ai/workflow` via Geo Core JWT (local fallback optional)
- **REST API** (`geo-ai/v1`): analyse UX, recommend, implement copy/SEO, competitor research, automation CRUD
- Custom DB tables for analyses, findings, recommendations, drafts, competitors, automations, memory events
- **Automation rules** with schedule trigger (WP-Cron every 15 minutes) and manual run
- **Block editor** panel with variant-draft REST URL
- Independent license screen, usage refresh, disconnect, JWT diagnostics
- `RWGC_Satellite_Updater` for commercial plugin updates
- Insights section in Geo Core platform shell
- **Site intelligence sync** — uploads Geo Core compact snapshot to `api.reactwoo.com` (Pro+; cron + manual sync)
- **Intelligence workflows** — `site_audit`, `rule_explain`, `rule_debug`, and related suite audit keys via `/api/v5/geo-ai/workflow`
- **Approval-gated actions** — pending actions table, REST apply/dismiss, **Intelligence actions** admin screen

### In Progress

- Remote workflow coverage for all automation workflow keys (some workflows still stub-only locally)
- Usage tier display alignment when JWT and usage API disagree

### Planned

- Additional remote agents beyond current workflow registry
- Deeper Geo Optimise handoff (variant draft → experiment)

## Requirements

| Requirement | Minimum |
|-------------|---------|
| WordPress | 6.2+ |
| PHP | 7.4+ |
| ReactWoo Geo Core | 1.3.7+ for auth body filter; 1.8.x for shell |
| react-license | Valid Geo AI product key |
| reactwoo-api | Required for remote AI and updates |
| OpenAI / model keys | On **reactwoo-api** server only — not in WordPress |

## Installation

1. Install and activate **ReactWoo Geo Core**.
2. Activate **reactwoo-geo-ai** (runs `dbDelta` for AI tables).
3. Save license key under **Geo → Geo AI → License**.
4. Optional: set Advanced → **workflow_engine** to remote or remote-with-fallback.

```bash
npm run package:zip
```

## Configuration

| Setting | Location | Notes |
|---------|----------|-------|
| License key | Geo AI → License | Independent from other satellites |
| Workflow engine | Advanced | `local`, `remote`, `remote_fallback` |
| API base | Filter `rwga_reactwoo_api_base` or `RWGA_REACTWOO_API_BASE` | Default `https://api.reactwoo.com` |
| Capabilities | `rwga_manage_ai`, `rwga_run_ai`, `rwga_view_ai_reports`, `rwga_manage_automations` | |
| Debug trace | `RWGA_LICENSE_API_TRACE`, `WP_DEBUG_LOG` | Login and usage diagnostics |

## Feature Entitlements

| Feature | Free tier | Paid (via license JWT) |
|---------|-----------|-------------------------|
| Local stub workflows | Yes (limited) | Yes |
| Remote AI workflows | No | Yes (token limits per package) |
| Automation schedules | License + cap | Per plan |
| Plugin updates | License JWT required | Yes |

Token limits and `monthly_ai_tokens` come from **react-license** JWT claims and usage API.

## Integrations

| Integration | Purpose |
|-------------|---------|
| **Geo Core** | JWT client, REST discovery, suite shell, optional license bridge filter |
| **react-license** | Domain-bound key, tier, product_slug `reactwoo-geo-ai` |
| **reactwoo-api** | `/api/v5/auth/login`, `/api/v5/ai/*`, `/api/v5/geo-ai/workflow`, updates |
| **Geo Optimise** | Suite handoff `rwgc_variant_page_id` |
| **Elementor / Gutenberg** | Page context extraction for AI payloads |

## Developer Notes

- Constant: `RWGA_VERSION`; fires `rwga_loaded`.
- REST namespace: `geo-ai/v1`.
- Page context: `RWGA_Page_Context`, filter `rwga_page_context`; compact `reading_context` for remote calls.
- Remote filters: `rwga_remote_workflow_path`, `rwga_remote_workflow_body`, `rwga_remote_workflow_response`.
- Stats: `rwga_stats_snapshot` filter.
- Phase doc: Geo Core `docs/phases/phase-5.md`.
- Intelligence architecture: `docs/GEO-AI-INTELLIGENCE.md` (this repo); master plan `reactwoo-api/docs/PLAN-GEO-AI-INTELLIGENCE.md`.

## Known Limitations

- **Competitor research** local stub does not live-fetch competitor URLs.
- Remote mode requires reachable **reactwoo-api** and valid JWT; `api_stub` tokens indicate upstream license misconfiguration.
- Automation runs only supported workflows (`ux_analysis`, `competitor_research` by default; extend via `rwga_automation_build_workflow_input`).
- AI credentials never belong in WordPress — only on API server.

## Release Readiness

| Area | Status |
|------|--------|
| Local workflow stubs & REST | **Shipped** |
| Remote workflow engine | **Shipped** |
| Automation (schedule + manual) | **Shipped** |
| License diagnostics & updates | **Shipped** |
| Site intelligence sync & actions | **Shipped** (API v0.1.39+) |
| Full remote parity for all workflows | **In Progress** |
| LLM-backed intelligence workflows | **Planned** (deterministic runner shipped) |

## Compatibility

| Component | Version |
|-----------|---------|
| WordPress | 6.2+ |
| PHP | 7.4+ |
| Geo Core | 1.3.x – 1.8.x |
| react-license | 1.0.14+ |
| reactwoo-api | 0.1.39+ (geo-ai workflow, site sync, cost controls) |
| Node OpenAI client | On API server |

## Roadmap

- Expand remote workflow registry
- Tighter Optimise experiment creation from AI variant drafts
- Usage dashboard improvements for enterprise tiers

## Support

- Geo AI → Help and Advanced connection checks
- [ReactWoo support](https://reactwoo.com/support)

## License

GPLv2 or later.
