# Contract: `POST /api/events/assign_many`

**Feature**: 004-reviewer-assignment
**Status**: Existing endpoint — **additive, backward-compatible extension**

This endpoint assigns a reviewer to a batch of events. It is the only backend
contract this feature touches. The single-slot forms below are **unchanged**;
the only addition is the optional `reviewer2_id` field (research Decision 4).

After this change lands, regenerate the API contract:
`python -m flask_backend.generate_openapi` (run from the repository root) and
commit the updated `openapi.json` in the same change (Constitution quality
gate).

---

## Authorization

- `@requires_auth` + `@requires_roles('admin')`.
- Unauthenticated → `401`. Authenticated non-admin → `403`.

---

## Request

`Content-Type: application/json`

### Form A — single slot (existing, unchanged)

Used by this page's **single-reviewer** path (`slot:"first"`) and by the
third-reviewer page (`slot:"third"`).

```json
{
  "event_ids": [12, 13, 14],
  "reviewer_id": 7,
  "slot": "first"
}
```

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `event_ids` | array of int | yes | Must be a non-empty list |
| `reviewer_id` | int | yes | The reviewer for `slot` |
| `slot` | string | yes | `"first"` \| `"second"` \| `"third"` |

### Form B — first + second reviewer, atomic (new)

Used by this page's **two-reviewer** path (`reviewer_count == 2`).

```json
{
  "event_ids": [12, 13, 14],
  "reviewer_id": 7,
  "slot": "first",
  "reviewer2_id": 9
}
```

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `event_ids` | array of int | yes | Non-empty list |
| `reviewer_id` | int | yes | The **first** reviewer |
| `slot` | string | yes | Must be `"first"` |
| `reviewer2_id` | int | yes (Form B) | The **second** reviewer; absent in Form A |

---

## Behavior

| Case | Effect |
|------|--------|
| `slot == "first"`, no `reviewer2_id` | Sets `reviewer1_id`, `assigner_id`, `assign_date`; advances `status` → `assigned`. |
| `slot == "first"`, with `reviewer2_id` | Sets `reviewer1_id`, `reviewer2_id`, `assigner_id`, `assign_date`; advances `status` → `assigned`. **All events updated in one transaction (atomic).** |
| `slot == "second"` | Sets `reviewer2_id`, `assigner_id`, `assign_date`. (Not used by this page.) |
| `slot == "third"` | Sets `reviewer3_id`, `assigner3rd_id`, `assign3rd_date`; sends third-reviewer emails. (Not used by this page.) |

`assigner_id` is the authenticated admin (audit trail).

---

## Responses

### `200 OK`

```json
{ "data": { "updated": 3 } }
```

`updated` is the count of events written.

### `400 Bad Request`

`{ "error": "<message>" }` — surfaced verbatim to the admin (FR-017). Triggers:

| Condition | Message (representative) |
|-----------|--------------------------|
| `event_ids`, `reviewer_id`, or `slot` missing | `event_ids, reviewer_id, and slot are required` |
| `slot` not in {first, second, third} | `slot must be one of: first, second, third` |
| `slot` ∈ {second, third} **and** `reviewer_count == 1` | `Second- and third-reviewer assignment is unavailable when reviewer count is 1` |
| `reviewer2_id` present **and** `reviewer_count == 1` | `A second reviewer cannot be assigned when reviewer count is 1` |
| `reviewer2_id` present **and** `slot != "first"` | `reviewer2_id is only valid with the first slot` |
| `reviewer2_id == reviewer_id` | `The first and second reviewer must be different people` |

### `401 Unauthorized` / `403 Forbidden`

Not authenticated / not an admin. The page surfaces an authorization message
and claims no success (FR-004, FR-017).

### `500 Internal Server Error`

`{ "error": "Failed to assign events" }` — surfaced as a generic failure;
the admin can retry (FR-017).

---

## Backward compatibility

- Form A bodies behave exactly as before this feature.
- `pages/EventAssignThird.jsx` (`slot:"third"`) is unaffected.
- `table_service.assign_events(event_ids, reviewer_id, slot, assigner_id)`
  gains a trailing optional `reviewer2_id=None`; existing positional callers
  (including backend tests) are unaffected.
