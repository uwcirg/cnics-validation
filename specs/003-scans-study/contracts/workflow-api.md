# Phase 1 Contracts: Workflow & Configuration API

**Feature**: 003-scans-study | **Date**: 2026-05-20

Backend API contracts introduced or changed by this feature. The canonical
machine-readable contract is `openapi.json` at the repository root, which MUST
be regenerated (`python -m flask_backend.generate_openapi`, from the repo root)
in the same change set (Constitution "API contracts" gate; Principle VI
pre-release latitude).

All endpoints require `@requires_auth` (the basic+ldap → `X-Remote-User`
chain). Role decorators are noted per endpoint.

---

## NEW — `GET /api/config`

Exposes the resolved workflow configuration so the frontend can hide
bypassed-stage UI by flag (FR-021).

- **Auth**: `@requires_auth`
- **Response 200**:
  ```json
  {
    "data": {
      "study_type": "scans",
      "workflow": {
        "scrubbing": false,
        "screening": false,
        "sending": false,
        "reviewer_count": 1
      }
    }
  }
  ```
- Values are the *resolved* config (profile defaults + `.env` overrides). No
  secrets are exposed — only the four non-sensitive workflow controls and the
  study type.

---

## NEW — `POST /api/events/{event_id}/review`

Submit a reviewer's adjudication for an event and advance the lifecycle.

- **Auth**: `@requires_auth`, `@requires_any_role('reviewer', 'admin')`
- **Path param**: `event_id` (int)
- **Request body**: the shared review fields (same shape the existing review
  form collects; recorded into the shared `reviews` table).
- **Behavior**:
  - Inserts one `reviews` row for the submitting reviewer.
  - Determines the submitter's slot from `events.reviewer1_id` / `reviewer2_id`.
  - Reviewer 1 → sets `review1_date`, `status = 'reviewer1_done'`.
    If `reviewer_count == 1` the same call advances `status = 'done'` (FR-010);
    no second-reviewer or third-review state is entered, and no
    reviewer-disagreement comparison is performed (FR-014).
  - Reviewer 2 (only when `reviewer_count == 2`) → sets `review2_date`,
    `status = 'reviewer2_done'`.
- **Response 200**: `{ "data": { "event_id": <int>, "status": "<new status>" } }`
- **Errors**: `404` event not found; `403` submitter is not an assigned
  reviewer for the event; `400` invalid body.

---

## CHANGED — `POST /api/events/assign_many`

- **Auth**: `@requires_auth`, `@requires_roles('admin')` (unchanged)
- **Change**: in addition to setting `reviewerN_id` / `assign_date` /
  `assigner_id`, first-reviewer assignment now sets `status = 'assigned'`.
- **Change**: when `reviewer_count == 1`, a `slot` of `second` or `third` is
  rejected with `400` (FR-013).
- **Recorded prior behavior** (Principle VI): previously set the id/date
  columns only and never advanced `status`.

---

## CHANGED — `POST /api/events/send_many`

- **Auth**: `@requires_auth`, `@requires_roles('admin')` (unchanged)
- **Change**: in addition to setting `send_date` / `sender_id` and sending
  assignment emails, now sets `status = 'sent'`.
- When `sending` is disabled the send step is not part of the lifecycle; the
  send queue/action is not surfaced (FR-009). The dispatch-time
  reviewer-notification email is bypassed along with the send step (spec
  Assumption).
- **Recorded prior behavior** (Principle VI): previously set `send_date` /
  `sender_id` and sent emails, but never advanced `status`.

---

## CHANGED — `GET /api/events/by_status/{status}` (phase queues)

- **Auth**: `@requires_auth`, `@requires_any_role('reviewer','uploader','admin')`
  (unchanged)
- **Change**: the date-driven phase predicates become flag-aware. "Ready to
  assign" = all *enabled* pre-assignment stages complete AND `assign_date IS
  NULL`; with scrubbing+screening disabled this is `upload_date IS NOT NULL AND
  assign_date IS NULL` (FR-007, FR-008). Bypassed phases surface no queue.

---

## CHANGED — `GET /api/reviewer/awaiting` and `GET /api/events/for_review`

- **Auth**: unchanged.
- **Change**: when `sending` is disabled, the reviewer queue includes events
  with `status = 'assigned'` assigned to the reviewer (in addition to / instead
  of `status = 'sent'`), so assigned events are directly pickable (FR-009,
  FR-011).
- **Recorded prior behavior** (Principle VI): keyed only on `status IN ('sent',
  'reviewer2_done')` / `status = 'sent'` — non-functional because nothing set
  `status = 'sent'`.

---

## Startup contract (not an HTTP endpoint) — FR-005

On application startup the shared configuration layer validates the resolved
config. If `REVIEWER_COUNT` is not `1` or `2`, or any boolean control holds an
unrecognized token, the Flask app fails to construct and serves **no** request
(SC-004). The error message names the offending variable and value.
