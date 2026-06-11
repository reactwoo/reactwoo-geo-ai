# Geo AI — Payload Strategy

**Phase 1 audit** — what is sent to `api.reactwoo.com` today, what must never be sent, and the v3 target pipeline.

---

## Core principle

```text
Local Intelligence
  ↓
Cloud Intelligence (when site registered)
  ↓
Relationship Graph
  ↓
Task Context Builder (RWGA_Context_Builder — planned)
  ↓
AI
```

The model receives **summaries and findings**, not the website.

---

## Payload types today

### 1. Site intelligence snapshot (sync)

**Source:** `rwgc_build_ai_snapshot()` (Geo Core)  
**Transport:** `POST /api/v5/geo-ai/sites/{site_id}/snapshot`  
**Storage:** Redis `geo_ai_snapshot:{siteId}:{hash}`

| Included | Excluded |
|----------|----------|
| Site URL, name, language, timezone | Emails, API keys, license keys, tokens |
| Plugin versions (Geo suite, Woo, Elementor) | Post content, HTML, Elementor JSON |
| Rules (ids, types, countries) | Order/customer data |
| Variants (master_id, slug, status) | Raw page bodies |
| Popups, forms, tracking slugs | |
| Relationships (edges) | |
| Satellite blocks: `geocore_pro`, `geo_optimise`, `geo_commerce` | |

**Size limit:** `GEO_AI_SNAPSHOT_MAX_BYTES` (default 512 KiB).

**Filters:** `rwgc_ai_snapshot_payload`, `rwgcp_ai_snapshot_block`, `rwgo_ai_snapshot_block`, `rwgcm_ai_snapshot_block`.

### 2. Intelligence workflow payload

**Transport:** `POST /api/v5/geo-ai/workflow`  
**Workflow keys:** `site_audit`, `rule_*`, `popup_fire_debug`, `variant_relationship_audit`, `tracking_gap_audit`, `optimisation_recommendation`

```json
{
  "workflow_key": "site_audit",
  "payload": {
    "site_intelligence": { "...": "snapshot or subset" },
    "rule_id": "optional",
    "popup_id": "optional",
    "variant_page_id": "optional"
  },
  "site": { "uuid": "...", "url": "...", "site_id": "cloud id" }
}
```

**API compaction:** `geoAiIntelligenceLlm.ts` truncates arrays (max 48 rows), strings (512 chars), strips nested objects to `[object]`.

**Cache key:** `{workflowKey}:{snapshot_hash}[:rule_id|popup_id|variant_page_id]`

### 3. UX workflow payload (page-scoped)

**Workflow keys:** `ux_analysis`, `ux_recommend`, `competitor_research`

Built in WordPress by `RWGA_Workflow_UX_*` → `RWGA_Page_Context` / `RWGA_Page_Context_Builder`.

| Field | Content | Sent to API? |
|-------|---------|--------------|
| `page_id`, `page_url`, `page_type` | Metadata | Yes |
| `geo_target`, `analysis_focus` | Targeting / focus | Yes |
| `page_context` | Legacy bundle (title, plain text excerpt) | Yes (sanitized) |
| `reading_context` | Trimmed text + headings | Yes |
| `builder_context` | Compact sections/widgets/CTAs/scores | Yes |
| `builder_recommendations` | Deterministic parser hints | Yes (UX recommend) |
| `_elementor_data` | Raw JSON | **Never** |
| `post_content` (full) | HTML/blocks raw | **Never** (excerpt only) |

**Compact `builder_context` shape** (from `RWGA_Page_Context_Builder::compact_for_api()`):

```json
{
  "builder": "elementor",
  "page_type": "homepage",
  "sections": [{ "id": "...", "type": "hero", "confidence": 0.9, "heading": "...", "has_cta": true }],
  "widgets": [{ "id": "...", "type": "heading", "section_id": "...", "content": "trimmed", "is_cta": false }],
  "ctas": [{ "widget_id": "...", "label": "Get Started" }],
  "ux_scores": { "overall_score": 71, "cta_score": 62, "trust_score": 58 },
  "detected_issues": [{ "code": "missing_trust", "severity": "medium", "message": "..." }]
}
```

**API formatting:** `builderContextPrompt.ts` → plain-text block for system/user prompts.  
**Sanitization:** `aiPlaintext.ts` → `sanitizePageContextForModel()`.

### 4. Variant draft (separate product path)

**Route:** `POST /api/v5/ai/geo-variant-draft`  
**Scope:** Geo Core experience workflow — country list generation, not page intelligence.

---

## Never send by default

| Category | Reason |
|----------|--------|
| Elementor JSON (`_elementor_data`) | Size, privacy, re-analysis cost |
| Gutenberg raw block trees | Same |
| Full HTML / rendered output | PII risk, token cost |
| WooCommerce orders / customers | PII |
| Email addresses | PII |
| License keys / API tokens | Security |
| Google OAuth tokens | Belong in react-cloud only |

Enforcement today is **convention + compact builders**, not a central payload gate. v3 adds `RWGA_Context_Builder` + admin Payload Inspector.

---

## v3 target payload (per workflow)

Example: user asks *"Improve homepage conversion"*

**Send:**

```json
{
  "page_type": "homepage",
  "uvp": "Warehouse optimisation",
  "trust_score": 58,
  "cta_strength": 71,
  "friction": "medium",
  "objections": ["too_expensive", "switching_risk"],
  "conversion_findings": [{ "finding": "Primary CTA lacks emphasis", "severity": "medium" }],
  "relationships": { "variants": 2, "experiments": 1 },
  "user_request": "Improve homepage conversion"
}
```

**Do not send:** page HTML, widget inventory counts as the primary signal, or raw builder trees.

---

## Cloud intelligence keys (v3 target)

Proposed Redis namespace extension (not all implemented):

```text
geo_ai:site:{id}:snapshot      # exists (as geo_ai_snapshot:*)
geo_ai:site:{id}:graph         # derived from snapshot today
geo_ai:site:{id}:ux            # planned — intelligence snapshot
geo_ai:site:{id}:messaging     # planned
geo_ai:site:{id}:runs          # exists (geo_ai_intel_runs:*)
geo_ai:site:{id}:cache         # planned — insight memory
```

---

## Filters and extension points

| Filter | Purpose |
|--------|---------|
| `rwga_ai_page_context` | Trim local page intelligence before workflows |
| `rwga_remote_workflow_body` | Last chance to adjust API body |
| `rwgc_ai_snapshot_payload` | Append satellite snapshot blocks |
| `rwga_intelligence_allowed_action_types` | Action allowlist |

---

## Gaps vs v3

| Gap | Priority |
|-----|----------|
| No `RWGA_Context_Builder` — workflows assemble payloads ad hoc | High |
| UX workflows still send widget snippets (content strings) — should send messaging summaries | High |
| No payload inspector in admin | Medium |
| No automated exclusion audit (what was stripped and why) | Medium |
| Intelligence workflows do not yet consume page-level local intelligence | High |
| No sync of compact page intelligence graph to cloud | Medium |

See `docs/AI-INTELLIGENCE-ARCHITECTURE.md` for full gap analysis.
