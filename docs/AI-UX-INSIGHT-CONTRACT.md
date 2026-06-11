# Geo AI — UX Insight Contract

**Phase 1 audit** — schemas for reusable UX findings: what exists, what v3 requires, and how layers connect.

---

## Purpose

UX insights are **persistent, reusable findings** — not one-off model outputs. They feed:

- Local intelligence tables (`rwga_ux_insights` — planned)
- Context builder (workflow-specific bundles)
- Premium AI reasoning (consumes insights, does not re-parse pages)
- Admin Intelligence Centre

---

## Current contracts (shipped)

### 1. Analysis run + findings (WordPress DB)

**Tables:** `rwga_analysis_runs`, `rwga_analysis_findings`

**Run row** (`rwga_analysis_runs`):

| Column | Type | Notes |
|--------|------|-------|
| `workflow_key` | string | e.g. `ux_analysis` |
| `page_id` | int | Target page |
| `analysis_focus` | string | `messaging`, `layout`, `combined` |
| `score`, `confidence` | decimal | Run-level scores |
| `summary` | longtext | Narrative summary |
| `input_hash` | varchar(64) | Input fingerprint — **stored, not used for cache gate** |
| `remote_run_id` | string | API run id when remote |

**Finding row** (`rwga_analysis_findings`):

| Column | Type | Notes |
|--------|------|-------|
| `finding_key` | string | Stable slug |
| `category` | string | e.g. `messaging`, `conversion`, `trust` |
| `severity` | string | `low`, `medium`, `high` |
| `title` | string | Short headline |
| `evidence` | longtext | Supporting detail |
| `recommendation_hint` | longtext | Next step |
| `impact_estimate` | string | `low`, `medium`, `high` |

**API mirror** (`geoAiWorkflow.ts` — `findingSchema`): same shape for `ux_analysis` JSON output.

### 2. UX structure scores (deterministic, not persisted)

**Source:** `RWGA_UX_Structure_Scorer::score()`

```json
{
  "overall_score": 71,
  "hero_score": 80,
  "cta_score": 62,
  "trust_score": 58,
  "form_score": 70,
  "content_clarity_score": 65,
  "structure_score": 72,
  "detected_issues": [
    {
      "code": "weak_primary_cta",
      "severity": "medium",
      "message": "Primary CTA label is generic",
      "widget_id": "abc123"
    }
  ],
  "recommendations": []
}
```

Included in `builder_context.ux_scores` and `detected_issues` when sent to API. **Not stored** in dedicated insight table today.

### 3. Intelligence workflow findings (cloud)

**Source:** `geoAiIntelligenceWorkflow.ts` → `IntelligenceResult`

```json
{
  "summary": "...",
  "findings": [{ "title": "...", "severity": "medium", "category": "...", "evidence": "..." }],
  "recommendations": [],
  "actions": [{ "action_type": "open_admin_route", "requires_approval": true, "status": "pending" }]
}
```

Persisted in Redis (`geo_ai_intel_run:{runId}`) and locally in `rwga_intelligence_actions` for actions only — **not** as reusable UX insights.

### 4. Builder recommendations (deterministic hints)

**Source:** `RWGA_Builder_Recommendations`

```json
{
  "builder": "elementor",
  "recommendation_type": "improve_cta",
  "target": { "section_id": "...", "widget_id": "...", "widget_type": "button" },
  "reason": "...",
  "suggested_change": "...",
  "implementation_possible": true,
  "risk_level": "low"
}
```

Sent as hints to `ux_recommend` prompt via `formatBuilderRecommendationsForPrompt()`.

---

## v3 target: `rwga_ux_insights` row

Planned reusable finding store:

```json
{
  "entity_type": "page",
  "entity_id": 123,
  "finding": "Primary CTA lacks emphasis",
  "severity": "medium",
  "category": "conversion",
  "insight_type": "cta_effectiveness",
  "scores": {
    "cta_strength": 71,
    "cta_visibility": 62,
    "commitment_level": "high"
  },
  "evidence_json": {},
  "source": "RWGA_UX_Insight_Builder",
  "source_version": "1.0.0",
  "snapshot_hash": "sha256...",
  "entity_hash": "sha256...",
  "expires_at": null
}
```

### v3 insight categories (engines)

| Engine | Insight types | Example |
|--------|---------------|---------|
| `RWGA_Messaging_Analyzer` | promise, uvp, audience, objections, clarity | `{ "uvp": "...", "clarity_score": 72 }` |
| `RWGA_UX_Insight_Builder` | hierarchy, cta, trust, friction, mobile | `{ "trust_gap": "proof_before_cta_missing" }` |
| `RWGA_Visual_Analyzer` | emphasis, competition, colour meaning | `{ "primary_cta_emphasis": 78 }` |
| Premium AI | synthesized recommendations | Linked to `rwga_ai_runs` |

---

## v3 messaging contract (planned)

### Primary promise

```json
{ "promise": "Reduce warehouse costs by 30%" }
```

### Unique value proposition

```json
{ "uvp": "African warehouse expertise combined with automation technology" }
```

### Audience

```json
{
  "persona": "warehouse_operations_manager",
  "awareness_stage": "problem_aware"
}
```

### Emotional drivers

```json
{ "drivers": ["certainty", "trust", "cost_reduction"] }
```

### Objections

```json
{ "objections": ["too_expensive", "switching_risk", "implementation_time"] }
```

### Clarity scores

```json
{
  "clarity": 68,
  "specificity": 72,
  "differentiation": 55,
  "credibility": 61
}
```

**Today:** none of these are extracted deterministically. UX analysis prompts may infer similar concepts from widget text.

---

## v3 UX intelligence contract (planned)

### Messaging hierarchy

```json
{ "message_order": ["problem", "solution", "proof", "cta"] }
```

### CTA effectiveness

```json
{
  "primary_cta": "Get Started",
  "cta_strength": 71,
  "cta_visibility": 62,
  "commitment_level": "high"
}
```

### Trust

```json
{
  "trust_score": 58,
  "signals": ["reviews", "logos"],
  "trust_gap": "proof_before_cta_missing"
}
```

### Friction

```json
{
  "friction": "medium",
  "choice_complexity": "high",
  "confidence_required": "high"
}
```

### Mobile experience

```json
{
  "cta_visibility_mobile": 45,
  "scroll_depth_estimate": "deep",
  "visual_density": "high",
  "content_overload": true,
  "form_usability": "fair"
}
```

**Today:** `RWGA_UX_Structure_Scorer` approximates hero/CTA/trust at structure level only.

---

## v3 visual emphasis contract (planned)

```json
{
  "attention_flow": ["hero", "benefits", "cta"],
  "primary_cta_emphasis": 78,
  "secondary_cta_competition": 34,
  "focus_conflicts": 2,
  "colour_roles": {
    "primary_action": "high-contrast warm",
    "trust": "cool neutral",
    "warning": "amber accent"
  }
}
```

**Today:** not implemented. Elementor colour values exist in parsed widgets but are not interpreted.

---

## Insight lifecycle (v3)

```text
Page change / cron / manual refresh
  ↓
Local analyzers (deterministic)
  ↓
rwga_ux_insights (+ page_context summaries)
  ↓
Optional: sync compact bundle to cloud
  ↓
Workflow requests insight bundle via RWGA_Context_Builder
  ↓
Premium AI (only if insight bundle stale or user requests deep audit)
  ↓
rwga_ai_runs + new/updated insights
  ↓
Approval-gated actions
```

---

## Versioning

| Artifact | Current version | Notes |
|----------|-----------------|-------|
| Snapshot schema | `1` | `RWGC_AI_Snapshot_Schema::VERSION` |
| DB schema | `1.3.0` | `RWGA_DB::SCHEMA_VERSION` |
| UX finding API schema | implicit | Zod in `geoAiWorkflow.ts` |
| UX insight contract | **draft** | This document — implement in Phase 2–5 |

---

## Gaps

| Item | Status |
|------|--------|
| `rwga_ux_insights` table | Not created |
| Insight deduplication by entity + type | Not implemented |
| Insight invalidation on page save | Not implemented |
| Cross-workflow insight reuse | Not implemented |
| Messaging / visual insight types | Not implemented |
| Contract tests for insight JSON | Not implemented |

See `docs/AI-INTELLIGENCE-ARCHITECTURE.md`.
