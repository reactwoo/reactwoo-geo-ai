# Geo AI — Model Routing

**Phase 1 audit** — how models are selected today and the v3 `RWGA_Model_Router` target.

---

## Current routing (shipped)

### Intelligence workflows (site-wide)

**File:** `reactwoo-api/src/utils/geoAiIntelligenceLlm.ts`

| Setting | Default | Effect |
|---------|---------|--------|
| `GEO_AI_INTEL_USE_LLM` | `1` when provider key exists | `0` forces deterministic runner |
| `GEO_AI_INTEL_PROVIDER` | `auto` | Claude if Anthropic key, else OpenAI |
| `GEO_AI_INTEL_ANTHROPIC_MODEL` | `claude-3-5-haiku-latest` | Intelligence workflows |
| `GEO_AI_INTEL_OPENAI_MODEL` | `gpt-4o-mini` | Fallback intelligence |
| `GEO_AI_INTEL_MODEL` | — | Legacy override for both providers |

**Fallback chain:**

```text
LLM (Claude or OpenAI)
  ↓ on parse/validation error
runIntelligenceWorkflowDeterministic()  → provider: internal
  ↓
Flat unit charge (GEO_AI_INTEL_UNIT_TOKEN_CHARGE = 500)
```

**Workflow keys:** `site_audit`, `rule_explain`, `rule_debug`, `rule_create`, `popup_fire_debug`, `variant_relationship_audit`, `tracking_gap_audit`, `optimisation_recommendation`

### UX workflows (page-scoped)

**File:** `reactwoo-api/src/routes/geoAiWorkflow.ts`

| Workflow | Model | Configurable? |
|----------|-------|---------------|
| `ux_analysis` | `gpt-4o-mini` | **No** (hardcoded) |
| `ux_recommend` | `gpt-4o-mini` | **No** (hardcoded) |
| `competitor_research` | `gpt-4o-mini` | **No** (hardcoded) |

**Provider:** OpenAI only (no Anthropic path for UX).

**Prompt construction:**

- System prompt: inline in `buildUxSystemPrompt()` / recommend variant
- User context: `payloadToUxContext()` + `formatReadingBundleForPrompt()` + `formatBuilderContextForPrompt()`
- `analysis_focus`: `messaging` (1600 max tokens), `layout` (2000), `combined` (2400)

### Variant draft (Geo Core bridge)

**File:** `reactwoo-api/src/routes/ai/geoVariantDraft.ts`  
**Model:** `gpt-4o-mini` (hardcoded)

### WordPress engine selection

**Setting:** Geo AI → Advanced → Workflow engine

| Mode | Behaviour |
|------|-----------|
| `local` | Stub workflows only (UX bounded fake output) |
| `remote` | `RWGA_Remote_Client` → API |
| `remote_fallback` | Remote with local stub on failure |

Intelligence workflows **require remote**.

---

## Token metering

### Monthly budget

**Tracker:** `tokenTracker` + `GEO_AI_WORKFLOWS` registry (`usesTokenBudget: true`, `minTier: pro`)

All remote workflow keys count toward monthly AI token allowance when `usesTokenBudget` is true.

### Per-run logging

**File:** `aiTokenUsageLog.ts`

Logs `prompt_tokens`, `completion_tokens`, `total_tokens` per route with `workflow_key` context.

### Intelligence-specific metering

| Case | Charge |
|------|--------|
| Cache hit (`usage.cache_hit: true`) | 0 tokens |
| LLM intelligence run | Actual provider tokens |
| Deterministic fallback | `GEO_AI_INTEL_UNIT_TOKEN_CHARGE` (500) |

### Rate limits

| Bucket | Default/hour |
|--------|--------------|
| `intelligence_workflow` | 60 |
| `snapshot_upload` | 30 |
| `sites_register` | 120 |

---

## Caching (model-adjacent)

| Cache | Key | TTL |
|-------|-----|-----|
| Intelligence results | `{workflow}:{snapshot_hash}[:entity]` | 86400s |
| Snapshot re-upload | Same `snapshot_hash` | Quota-neutral |
| UX workflow results | **None** | Every run calls model |
| Local `input_hash` | Stored on `rwga_analysis_runs` | **Not used to skip API** |

---

## v3 target: `RWGA_Model_Router`

Central routing in WordPress (with API mirror for remote-only paths).

### Premium models

**Use for:** UX audits, CRO audits, copy recommendations, messaging recommendations, localisation recommendations, experiment recommendations, audience recommendations.

**Rationale:** Geo AI credibility depends on expert-level output. These tasks must not use budget models by default.

**Proposed env vars:**

```bash
GEO_AI_PREMIUM_PROVIDER=anthropic
GEO_AI_PREMIUM_ANTHROPIC_MODEL=claude-sonnet-4-20250514
GEO_AI_PREMIUM_OPENAI_MODEL=gpt-4o
```

### Deterministic engine

**Use for:** Rule debugging, popup debugging, relationship analysis, tracking audits, variant audits.

**Implementation:** Extend `runIntelligenceWorkflowDeterministic()`; prefer local when insight bundle is complete.

### Lightweight models

**Use for:** Formatting, classification, labelling, summaries.

**Proposed:**

```bash
GEO_AI_LIGHT_OPENAI_MODEL=gpt-4o-mini
GEO_AI_LIGHT_ANTHROPIC_MODEL=claude-3-5-haiku-latest
```

### Routing table (target)

| Task class | Engine | Model tier |
|------------|--------|------------|
| `site_audit` (deep) | Premium LLM | Sonnet / gpt-4o |
| `rule_debug`, `popup_fire_debug` | Deterministic + light LLM summary | Haiku / mini |
| `ux_analysis`, `ux_recommend` | Premium LLM | Sonnet / gpt-4o |
| Section classification | Deterministic | — (`RWGA_Section_Classifier`) |
| Messaging extraction | Light LLM on insight summary | Haiku / mini |
| Insight formatting | Light LLM | Haiku / mini |
| Copy implement draft | Premium or light (user setting) | Configurable |

### Insight memory interaction (Phase 14)

Before calling premium model:

```text
cache_key = workflow_key + snapshot_hash + entity_hash + prompt_version + model_version
```

If hit → return cached insight; **do not rerun premium AI**.

---

## Prompt versioning (gap)

| Item | Today | v3 |
|------|-------|-----|
| Prompt storage | Inline TypeScript strings | Versioned prompt registry |
| `prompt_version` in cache key | No | Yes |
| WordPress visibility | No | Intelligence Centre |
| A/B prompt testing | No | Future |

---

## Security

| Rule | Status |
|------|--------|
| API keys only on server | Enforced |
| No model selection in wp-admin | Enforced (engine mode only) |
| License JWT for all remote calls | Enforced |
| Tier gate (`minTier: pro`) | Enforced |

---

## Gaps vs v3

| Gap | Impact |
|-----|--------|
| UX workflows locked to `gpt-4o-mini` | High — quality ceiling |
| No `RWGA_Model_Router` | High |
| Intelligence uses Haiku/mini, not premium | High for audits |
| No task-class routing | Medium |
| No insight-aware cache before model call | Medium — cost |
| No prompt version registry | Medium — reproducibility |
| Anthropic unavailable for UX workflows | Medium |

---

## Recommended first changes (post Phase 2)

1. Add `GEO_AI_UX_MODEL` env var; wire `geoAiWorkflow.ts` UX handlers.
2. Split intelligence routing: deterministic-first for debug workflows; premium for `site_audit` when tier = enterprise.
3. Implement insight cache check in WordPress before `RWGA_Remote_Client::dispatch()`.
4. Log `model`, `prompt_version`, `cache_hit` on `rwga_ai_runs` (planned table).

See `docs/AI-INTELLIGENCE-ARCHITECTURE.md` and `reactwoo-api/docs/GEO-AI-ENV-VARS.md`.
