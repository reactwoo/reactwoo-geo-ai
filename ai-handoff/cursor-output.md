# Cursor output — Geo AI execute rule link fix

## Status
done

## Files changed
- `includes/services/executor/class-rwga-plan-executor.php` — verify created `rwgc_visibility_rule`, Geo Core editor URL, delete failed partial rules
- `includes/services/class-rwga-assistant-service.php` — `rule_create_failed` WP_Error, top-level `rule_id` / `edit_url` on success
- `tests/Services/RWGAPlanExecutorTest.php` — repository verification stubs + edit URL / cleanup tests
- `reactwoo-geo-ai.php`, `readme.txt` — v0.4.133

## Not changed
- Parser/planner, popup REST, Google Ads mapping, condition converter types

## Commands run
- `composer test -- --filter RWGAPlanExecutor` — OK (10 tests)

## Remaining
- Staging: upgrade Geo Core **1.8.100** + Geo AI **0.4.133**; retest Free Delivery create flow
- Post **13532** on staging: inspect with WP-CLI before manual delete (pre-fix orphan)
