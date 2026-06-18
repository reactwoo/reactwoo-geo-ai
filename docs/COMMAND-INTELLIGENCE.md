# Geo AI Command Intelligence

Local phrase → Geo Core action interpretation without external AI for normal Smart Actions.

## Services

| Class | Role |
|-------|------|
| `RWGA_Intelligence_Sync_Service` | Fetch/cache bundle from `GET /api/v1/intelligence/geocore/bundle` |
| `RWGA_Local_Intent_Interpreter` | Match phrases to intents/actions locally |
| `RWGA_Context_Resolver` | Resolve admin screen + "this popup/rule/page" references |
| `RWGA_Learning_Event_Service` | Send anonymised feedback to API |

## Admin

**Geo AI → Command intelligence** — bundle status, interpreter test, pattern browser, learning log.

## Example flow

```text
"Only show this to Canada"
  → intent: country_include
  → action: geocore_create_country_rule
  → params: { mode: "include_only", countries: ["CA"] }
  → Geo Core validates + confirms + executes
  → learning event: outcome=executed
```

## REST

- `GET /wp-json/geo-ai/v1/intelligence/command/bundle`
- `POST /wp-json/geo-ai/v1/intelligence/command/sync`
- `POST /wp-json/geo-ai/v1/intelligence/command/interpret`
- `POST /wp-json/geo-ai/v1/intelligence/command/learning-event`

## Offline fallback

`data/geocore-intelligence-fallback.json` — regenerate from reactwoo-api:

```bash
npx ts-node scripts/export-fallback-bundle.ts > data/geocore-intelligence-fallback.json
```

## API docs (reactwoo-api repo)

- `docs/intelligence-layer.md`
- `docs/geocore-intelligence-schema.md`
- `docs/geocore-satellite-sync.md`
- `docs/geocore-learning-events.md`

External AI remains reserved for copy generation, SEO/CRO research, and advanced UX recommendations.
