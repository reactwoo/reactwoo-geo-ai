# Agent workflow — ReactWoo Geo AI

Geo AI is a **Geo Core satellite** (AI-assisted variant drafts and product workflows). It does **not** replace Geo Core detection or free geo APIs.

## Defaults

- Prefer **one coherent thread** (read → change → verify). Match Geo Core `docs/AGENTS.md` for suite work.
- **Do not** put provider API keys or heavy model orchestration in WordPress beyond what this plugin already uses; follow Geo Core / ReactWoo API licensing patterns in `docs/geo-core-cursor-master-plan.md`.

## Build and release (parity with Geo Optimise / Geo Commerce)

- **`package.json`** defines `reactwooBuild.pluginFolder`, `reactwooBuild.zipFile`, and `reactwooBuild.geoCoreDependencySlug` (`reactwoo-geocore`).
- **Distribution zip:** `npm run package:zip` → `python scripts/package_zip.py` (includes `admin/`, `assets/`, `includes/`, main PHP, `readme.txt`).
- **CI:** `.github/workflows/publish-update.yml` runs the same packager on tag / dispatch.
- **Git:** do not commit `*.zip` (see `.gitignore`).
- **Cursor:** shared rules live under **`.cursor/rules/`** (committed).

## Product notes

- Fires **`rwga_loaded`** when ready. REST, usage, block editor bridges, and admin overview live here; Core exposes capabilities and shared admin patterns.

## References

- Geo Core: `docs/phases/phase-5.md`, `docs/geo-core-cursor-master-plan.md`, `docs/releases-and-git-tags.md`.
- **`RWGA_VERSION`** in `reactwoo-geo-ai.php` must match the shipped release and readme **Stable tag**.
