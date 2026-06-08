# Geo AI — site intelligence architecture

WordPress-side implementation for cloud **site intelligence sync**, **remote audit workflows**, and **approval-gated actions**.

**Requires:** Geo Core (snapshot builder), react-license JWT, reactwoo-api **v0.1.38+** (sync), **v0.1.39+** (cost controls), **v0.1.41+** (run history + graph).

Master plan: `reactwoo-api/docs/PLAN-GEO-AI-INTELLIGENCE.md`

## Components

| Class / file | Role |
|--------------|------|
| `RWGA_Site_Intelligence_Sync` | Cron (15 min), status, orchestrates register + upload |
| `RWGA_Site_Snapshot_Client` | HTTP client for `/geo-ai/sites/*` |
| `RWGA_Workflow_Intelligence` | Remote intelligence workflows (8 keys) |
| `RWGA_Intelligence_Response` | Parses API JSON; persists findings and pending actions |
| `RWGA_DB_Intelligence_Actions` | Custom table `rwga_intelligence_actions` |
| `RWGA_Intelligence_Action_Applier` | Allowlisted apply handlers after admin approval |

## Cloud intelligence admin (WordPress)

**Geo → Geo AI → Cloud intelligence** (`?page=rwga-intelligence-cloud`)

- Lists recent cloud runs (workflow, provider, findings/actions counts)
- Drill-down run summary (findings + recommendations)
- Relationship graph table from latest synced snapshot
- Links to sync, pending actions, and Geo Optimise when installed

Requires `RWGA_Intelligence_Cloud_Client` and API **v0.1.41+**.

## Cloud run history and graph (API)

When a site is registered via sync, intelligence workflow runs are stored in Redis and exposed at:

| Method | Route |
|--------|-------|
| GET | `/api/v5/geo-ai/sites/{site_id}/intelligence/runs` |
| GET | `/api/v5/geo-ai/sites/{site_id}/intelligence/runs/{run_id}` |
| GET | `/api/v5/geo-ai/sites/{site_id}/intelligence/graph` |

`RWGA_Remote_Client` includes `site.site_id` (cloud id from sync status) so runs link to the correct site. Workflow responses may include `cloud_run_id`.

## Sync flow

1. `rwgc_build_ai_snapshot()` produces compact JSON + `snapshot_hash`.
2. If hash unchanged since last successful sync, upload may be skipped (client optimization).
3. `POST /api/v5/geo-ai/sites/register` — resolves `site_id` (stable site UUID in options).
4. `POST /api/v5/geo-ai/sites/{site_id}/snapshot` — uploads payload.

**Admin:** Geo → Geo AI → **License** — intelligence sync status, manual **Sync now** / **Force sync**.

**Cron:** `rwga_site_intelligence_sync` every 15 minutes when license is valid.

**Helper:** `rwga_sync_site_intelligence( $force = false )` in `includes/helpers/rwga-site.php`.

### Sync error handling

`RWGA_Site_Snapshot_Client` maps API error codes to admin-readable messages:

- `TIER_REQUIRED` — Pro+ license required
- `SNAPSHOT_QUOTA_EXCEEDED` — monthly upload cap
- `RATE_LIMIT_EXCEEDED` — hourly rate limit
- `license_not_entitled` — JWT product/plan mismatch (check API `GEO_AI_ALLOWED_*` if set)

## Intelligence workflows

Registered workflow keys (remote-only product path):

- `site_audit`
- `rule_explain`, `rule_debug`, `rule_create`
- `popup_fire_debug`
- `variant_relationship_audit`
- `tracking_gap_audit`
- `optimisation_recommendation`

Each call sends:

```json
{
  "workflow_key": "site_audit",
  "payload": {
    "site_intelligence": { "...": "compact snapshot subset or full snapshot" },
    "rule_id": "optional",
    "popup_id": "optional",
    "variant_page_id": "optional"
  },
  "site": { "uuid": "...", "url": "..." }
}
```

to `POST /api/v5/geo-ai/workflow`.

When OpenAI is configured on the API (`GEO_AI_INTEL_USE_LLM=1`, default when a key exists), responses use **gpt-4o-mini** (override with `GEO_AI_INTEL_MODEL`). Parse/validation errors fall back to the deterministic runner (`usage.provider: internal`). Cached and rate-limited as documented in `reactwoo-api/docs/GEO-AI-ENV-VARS.md`.

API returns:

```json
{
  "remote_run_id": "uuid",
  "result": {
    "summary": "...",
    "findings": [],
    "recommendations": [],
    "actions": [
      {
        "action_type": "open_admin_route",
        "label": "...",
        "action_json": { "admin_page": "rwgc-visibility-rules" },
        "requires_approval": true,
        "status": "pending"
      }
    ],
    "usage": { "charged_units": 1, "cache_hit": false, "provider": "internal" }
  }
}
```

`RWGA_Intelligence_Response` stores findings and creates **pending** action rows.

## Approval-gated actions

### Allowlisted action types

| `action_type` | Apply behaviour |
|---------------|-----------------|
| `mark_orphaned_variant` | Audit metadata on variant; no live routing change |
| `open_admin_route` | Returns admin URL (e.g. `rwgc-suite-variants`, `rwgc-visibility-rules`) |
| `create_implementation_draft` | Creates Geo AI implementation draft row |

Extend via filters:

- `rwga_intelligence_allowed_action_types`
- `rwga_intelligence_apply_action`

### REST API

Namespace: `geo-ai/v1`

| Method | Route | Capability |
|--------|-------|------------|
| GET | `/intelligence/actions` | `rwga_view_ai_reports` |
| GET | `/intelligence/actions/{id}` | `rwga_view_ai_reports` |
| POST | `/intelligence/actions/{id}/apply` | `rwga_manage_ai` |
| POST | `/intelligence/actions/{id}/dismiss` | `rwga_manage_ai` |

### Admin UI

**Geo → Geo AI → Intelligence actions** (`?page=rwga-intelligence-actions`)

Lists pending/applied/dismissed rows with Apply and Dismiss buttons.

## Database

Table: `{prefix}rwga_intelligence_actions` (DB schema **1.3.0**)

Key columns: `workflow_key`, `action_type`, `action_json`, `status` (`pending`|`applied`|`dismissed`), `approved_by`, `apply_result_json`.

## Hooks

| Hook | When |
|------|------|
| `rwga_intelligence_action_applied` | After successful apply |
| `rwga_intelligence_allowed_action_types` | Extend allowlist |
| `rwga_intelligence_apply_action` | Custom action handler |

## Configuration

| Setting | Location |
|---------|----------|
| License / sync | Geo AI → License |
| Workflow engine | Advanced (`local`, `remote`, `remote_fallback`) |
| API base | Filter `rwga_reactwoo_api_base` |

Intelligence workflows expect **remote** mode for cloud analysis. Local stub engine covers UX workflows only.

## Version alignment

| Component | Minimum |
|-----------|---------|
| Geo AI | **0.4.66** |
| Geo Core | Snapshot builder shipped (1.8.x line) |
| reactwoo-api | **0.1.41** for run history, graph, LLM workflows |

## Related

- Env vars: `reactwoo-api/docs/GEO-AI-ENV-VARS.md`
- Snapshot schema: `reactwoo-geocore/docs/GEO-AI-SNAPSHOT.md`
