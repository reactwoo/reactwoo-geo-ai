# Geo AI — Builder-aware page analysis

## Goal

Geo AI must read, score, and eventually recommend changes against **Elementor** and **Gutenberg** page structures using a shared abstraction—not rendered HTML or raw `_elementor_data` JSON sent to the API.

## Current state (pre-implementation)

| Area | Location | Status |
|------|----------|--------|
| Page metadata + plain text | `RWGA_Page_Context`, `rwga-builder-text.php` | Gutenberg `parse_blocks()` for text; builder detection is `gutenberg` vs `classic` only |
| Elementor pages | — | Treated as classic; `_elementor_data` ignored |
| Site intelligence snapshot | Geo Core `RWGC_AI_Snapshot_Builder` | Config metadata only; form detection via meta LIKE on `_elementor_data` |
| AI prompts | `reactwoo-api` (remote) | WordPress sends `reading_context` bundle |
| UX workflows | `RWGA_Workflow_UX_Analysis`, `RWGA_Workflow_UX_Recommend` | Use `page_context` / `reading_context`; no widget IDs |
| Elementor geo controls | Geo Core `integrations/elementor/` | Targeting only—not content extraction |
| Tests | Geo AI | None |

## Architecture (added in Geo AI)

All builder-specific logic lives under **`includes/builders/`** in **reactwoo-geo-ai**. Geo Core stays builder-agnostic.

```
RWGA_Builder_Registry
  ├── RWGA_Elementor_Adapter      (_elementor_data JSON)
  ├── RWGA_Gutenberg_Adapter      (parse_blocks)
  └── RWGA_Default_Post_Content_Adapter (classic fallback)

RWGA_Section_Classifier           (deterministic UX section types)
RWGA_UX_Structure_Scorer          (hero/CTA/trust/form scores)
RWGA_Page_Context_Builder         (single AI payload entry point)
RWGA_Builder_Recommendations      (builder-aware recommendation shape)
RWGA_Elementor_Action_Planner     (dry-run mutation plans only)
RWGA_Page_Blueprint / Section / Widget (intent models, no generation yet)
```

### Data flow

1. `RWGA_Builder_Registry::resolve( $post_id )` picks adapter (Elementor meta → Gutenberg blocks → default).
2. Adapter returns normalized `sections`, `widgets`, `content_blocks`, `ctas`, `forms`, `media`.
3. `RWGA_Section_Classifier` labels sections (`hero`, `faq`, `pricing`, …).
4. `RWGA_UX_Structure_Scorer` scores structure and emits `detected_issues`.
5. `RWGA_Page_Context_Builder::build( $post_id )` trims/summarises for AI; **no raw Elementor JSON** in API payload.
6. `RWGA_Page_Context::collect()` enriches legacy context via builder layer (backward compatible).
7. `rwga_ai_reading_bundle_from_page_context()` includes compact builder summary for remote workflows.

### Admin debug

**Geo AI → Advanced → Page context inspector** (`rwga-page-context-debug`): post picker, full normalized parse, classifications, UX scores, AI payload preview.

## Phases

| Phase | Deliverable | Status |
|-------|-------------|--------|
| 1 | Interface + registry + three adapters | Done |
| 2 | Elementor parser (nested sections/containers/widgets) | Done |
| 3 | Gutenberg adapter (normalized parity) | Done |
| 4 | `RWGA_Section_Classifier` | Done |
| 5 | `RWGA_UX_Structure_Scorer` | Done |
| 6 | `RWGA_Page_Context_Builder` + reading bundle integration | Done |
| 7 | Builder-aware recommendations helper + workflow hook | Done |
| 8 | `RWGA_Elementor_Action_Planner` (dry-run only) | Done |
| 9 | Blueprint model classes | Done |
| 10 | PHPUnit tests (Elementor, Gutenberg, classifier, scorer) | Done |
| 11 | Admin debug UI | Done |

## Out of scope (future)

- Auto-writing `_elementor_data` (requires backup + user confirmation UI).
- Full page generation from blueprints.
- Moving builder adapters into Geo Core (keeps Core free of Elementor dependency).
- Remote API prompt updates in `reactwoo-api` (WordPress now sends richer `builder_context`).

## Risks

- Elementor widget settings vary by version; parser uses known keys with safe fallbacks.
- Very large pages: payload trimming caps sections/widgets in AI bundle.
- Reusable block refs may not expand without `WP_Block` runtime.

## Next recommended step

1. Update `reactwoo-api` UX workflow handlers to consume `builder_context.sections[].type` and `recommendations[].target.widget_id`.
2. Add approval UI that runs `RWGA_Elementor_Action_Planner::plan()` and shows dry-run diff before any write.
