# Decisions — ReactWoo Geo AI

| Date | Decision | Rationale |
|------|----------|-----------|
| — | Satellite of Geo Core | Requires `reactwoo-geocore`; fires `rwga_loaded` |
| — | AI/API via ReactWoo license stack | No direct OpenAI keys in plugin for product AI |
| — | File handoff for cross-tool debug | `ai-handoff/` + `reactwoo-geocore/docs/ai-handoff-workflow.md` |

## AI handoff defaults

- Site intelligence sync: see `docs/GEO-AI-INTELLIGENCE.md` — do not bypass approval gates in fixes.
