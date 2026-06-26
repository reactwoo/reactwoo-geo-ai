# Agent workflow — ReactWoo Geo AI

Geo AI is a **Geo Core satellite** (AI-assisted variant drafts and product workflows). It does **not** replace Geo Core detection or free geo APIs.

## Defaults

- Prefer **one coherent thread** (read → change → verify). Match Geo Core `docs/AGENTS.md` for suite work.
- **Do not** put provider API keys or heavy model orchestration in WordPress beyond what this plugin already uses; follow Geo Core / ReactWoo API licensing patterns in `docs/geo-core-cursor-master-plan.md`.

## Build and release (parity with Geo Optimise / Geo Commerce)

- **`package.json`** defines `reactwooBuild.pluginFolder`, `reactwooBuild.zipFile`, and `reactwooBuild.geoCoreDependencySlug` (`reactwoo-geocore`).
- **Distribution zip:** `npm run package:zip` → `python scripts/package_zip.py` (includes `admin/`, `assets/`, `includes/`, main PHP, `readme.txt`). **Always build the local zip** after implementation (version-suffixed filename) for Local/staging install — even when CI publishes on tag.
- **CI:** `.github/workflows/publish-update.yml` runs the same packager on tag / dispatch.
- **Git:** do not commit `*.zip` (see `.gitignore`).
- **Cursor:** shared rules live under **`.cursor/rules/`** (committed).

## Product notes

- Fires **`rwga_loaded`** when ready. REST, usage, block editor bridges, and admin overview live here; Core exposes capabilities and shared admin patterns.
- **Site intelligence:** sync via `RWGA_Site_Intelligence_Sync`, remote audit workflows, approval-gated actions — see **`docs/GEO-AI-INTELLIGENCE.md`**. Master plan: **`reactwoo-api/docs/PLAN-GEO-AI-INTELLIGENCE.md`**.

## References

- Geo Core: `docs/phases/phase-5.md`, `docs/GEO-AI-SNAPSHOT.md`, `docs/geo-core-cursor-master-plan.md`, `docs/releases-and-git-tags.md`.
- API env: `reactwoo-api/docs/GEO-AI-ENV-VARS.md`.
- **AI handoff:** `ai-handoff/`, `reactwoo-geocore/docs/ai-handoff-workflow.md`, `.cursor/rules/ai-handoff.mdc`
- **`RWGA_VERSION`** in `reactwoo-geo-ai.php` must match the shipped release and readme **Stable tag**.

## AI handoff (ChatGPT ↔ Cursor)

Planner → **`ai-handoff/current-task.md`**; Cursor → **`cursor-output.md`**. Read **`known-issues.md`** before editing. Suite doc: **`reactwoo-geocore/docs/ai-handoff-workflow.md`**.

**Geo AI-specific:** no provider keys or heavy orchestration in WordPress beyond existing patterns; API calls via ReactWoo license stack.
