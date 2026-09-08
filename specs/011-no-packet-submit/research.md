# Phase 0 Research: No-Packet Submission Records the Event Outcome

**Feature**: `011-no-packet-submit` | **Date**: 2026-09-08

All unknowns in Technical Context are resolved below. Every decision is
grounded in code already in the tree or in real legacy rows from
`cnics-mci_prod.NoPHI.trim2.20250711.sql`, per Constitution Principle VI
("record the current observed behavior before modifying it").

---

## D1. Observed current behavior (Principle VI obligation)

**Finding**: The no-packet form is inert, and the inertness is structural, not
a runtime failure.

- `frontend/src/pages/EventUpload.jsx:325` opens `<form>` with **no `onSubmit`
  prop**. The Submit button at line 436 is `type="submit"`, so pressing it
  performs a default browser form submission that React never intercepts. No
  network request is made; no handler exists to make one.
- Of the six follow-up inputs, only two are bound to React state
  (`noPacketReason`, `priorEventDateKnown`). The other four —
  `twoAttemptsFlag` (line 353/358), the prior-event month and year (line 404),
  `priorEventOnsite` (line 415), and `otherCause` (line 429) — are
  **uncontrolled with no `value`/`onChange` and no ref**. Even with a handler
  attached, those four answers are currently unreadable.
- There is **no backend route** for this action. `grep` over
  `flask_backend/app.py` finds `no_packet_available` only in the by-status
  allow-list (line 607) and the export column list (lines 1072-1075) — both
  read paths. `upload_raw` (line 1828) is the only write path this page has.

**Conclusion**: this is unimplemented, not broken. Nothing is being replaced,
so Principle VI's "verify what it did before changing it" obligation is
satisfied by recording that it did nothing.

---

## D2. Endpoint shape

**Decision**: One new route, `POST /api/events/<int:event_id>/mark_no_packet`,
accepting a JSON body.

**Rationale**: This is a lifecycle transition with a side effect on
status/actor/date, which is exactly the shape of the two transition endpoints
already in the file — `POST /api/events/<id>/screen` (line 1249) and
`POST /api/events/<id>/review` (line 2034). Both take a JSON body, validate an
enumerated decision, stamp actor + date, set `status`, and return
`{'data': {...}}`. Following that precedent keeps the file internally
consistent and makes the new handler reviewable against a known-good template.

**Alternatives considered**:

- *Extend `PUT /api/events/<id>` (line 1196)*. Rejected: that route is a
  general field-editing surface for admins; folding a role-gated lifecycle
  transition into it would hide the transition from route-level review and
  conflict with the constitution's "declare role requirements at definition
  time" rule.
- *Extend `upload_raw` with a "no file" mode*. Rejected: it is
  `multipart/form-data` and its whole body is file handling. Overloading it
  would produce a handler with two unrelated halves.

---

## D3. Authorization

**Decision**: `@requires_auth` + `@requires_any_role('uploader', 'admin')`,
plus the same-site check that `upload_raw` performs — non-admins must have
`auth_user['site']` equal to the event's patient site; admins bypass.

**Rationale**: Spec FR-021. Declaring no packet exists and uploading a packet
resolve the same queue item, so they carry the same right. `upload_raw`
(lines 1863-1872) already implements exactly this rule, and
`get_events_need_packets` (`table_service.py:200`) already scopes the queue
the same way — non-admins see only their own site's events. Any weaker rule
would let a user resolve an event they cannot even see.

**Implementation note**: the site check is currently inline in `upload_raw`.
Lift it to a small shared helper (e.g. `_uploader_may_act_on_event`) used by
both, rather than copy-pasting the four-line check. This is a refactor of
existing code within the feature's own blast radius, not scope creep — a
second divergent copy of an authorization rule is exactly the failure mode
Principle IV warns about.

---

## D4. Which events may be marked

**Decision**: Only events with `status = 'created'`. Anything else is refused
with HTTP 409 and a message naming the event's current status.

**Rationale**: Spec FR-023. `get_events_need_packets` (`table_service.py:200`)
defines the "needs a packet" queue as exactly `status = 'created'`, so that
status *is* the definition of "still awaiting a packet". An event that has
moved on has either received a packet (`uploaded` and beyond) or been resolved
already (`rejected`, `no_packet_available`), and in every one of those cases a
no-packet declaration would destroy information.

**Consequence for FR-024 — free**: because the queue is defined as
`status = 'created'`, setting `status = 'no_packet_available'` removes the
event from the needs-packets list with no change to the query. Likewise
FR-025: `EventViewAll.jsx:394` already requests
`/api/events/by_status/no_packet_available`, and `no_packet_available` is
already in the by-status allow-list at `app.py:607`. Both list requirements
are satisfied by the write alone.

---

## D5. Field encoding, from real legacy rows

**Decision**: Match the legacy encoding exactly, as observed in production
data.

Sampled from `cnics-mci_prod.NoPHI.trim2.20250711.sql` (column order after
`status`: `rescrub_message`, `reject_message`, `no_packet_reason`,
`two_attempts_flag`, `prior_event_date`, `prior_event_onsite_flag`,
`other_cause`, `add_date`, `upload_date`, `markNoPacket_date`):

```text
'no_packet_available',NULL,NULL,'Outside hospital',0,NULL,NULL,NULL,'2010-07-08',NULL,'2011-01-27',…
'no_packet_available',NULL,NULL,'Ascertainment diagnosis error',NULL,NULL,NULL,NULL,'2010-01-07',NULL,'2010-09-16',…
'no_packet_available',NULL,NULL,'Ascertainment diagnosis referred to a prior event',NULL,'11-2011',0,NULL,'2013-03-20',NULL,'2013-05-09',…
'no_packet_available',NULL,NULL,'Other',NULL,NULL,NULL,'No NAACCORD number','2010-01-07',NULL,'2010-10-05',…
```

Four findings, each load-bearing:

1. **`prior_event_date` is zero-padded `MM-YYYY`** — 7 characters, matching
   `varchar(7)`. Not ISO, not `YYYY-MM`. Observed values include `'11-2011'`,
   `'10-2008'`, `'02-2004'`.
2. **Blank month/year are encoded as zeros, not NULL.** The distinct values in
   the dump include `'00-2008'` (year known, month unknown), `'01-0000'`
   (month known, year unknown), and `'00-0000'`. This is what the form's
   instruction "Leave a field blank if it is unknown" produces. A *NULL*
   `prior_event_date` means something different: the coordinator answered "No"
   to whether the approximate date is known at all. The three-way distinction
   must be preserved.
3. **`two_attempts_flag = 0` occurs in production** for "Outside hospital".
   This settles the question the spec defaulted on: "No, 2 attempts were not
   made" is a legitimate recorded answer, not a blocked submission. The
   protocol text *requests* two attempts; it does not gate the declaration.
4. **Non-applicable fields are NULL**, and `upload_date` stays NULL while
   `markNoPacket_date` carries the resolution date. Confirms FR-006's
   "write NULL, do not carry over stale answers".

**Refinement to spec FR-014**: the spec says reject a month outside 1-12.
Precisely: a *supplied* month must be 1-12 and a *supplied* year must be a
plausible four-digit year; a *blank* field is legitimate and encodes as `00` /
`0000`. At least one of the two must be supplied when the coordinator has said
the approximate date is known — `'00-0000'` under a "date is known" answer is
self-contradictory. (Legacy tolerated it; we do not need to reproduce that.)

**`other_cause` length**: real values run long — "Information obtain from
death certificate database. No other information specified." is 78 characters
against a 100-character column. FR-015's explicit limit message is therefore a
real user-facing concern, not a theoretical one.

---

## D6. Where validation lives

**Decision**: Both sides, with the backend authoritative.

**Rationale**: The frontend validates to give the coordinator an immediate,
field-specific message (FR-011 to FR-015) without a round trip. The backend
re-validates everything because it is the only guard that cannot be bypassed,
and because FR-016 ("event unchanged on rejection") is a data-integrity
promise. Duplication of a handful of predicates is the accepted cost; the
alternative — trusting the client — is not available for a PHI-adjacent
lifecycle write.

---

## D7. The event-detail gap (spec assumption corrected)

**Finding**: The spec's Assumptions say the detail page renders these fields
"using the fields already present there". That is **only true of
`markNoPacket_date`**. Verified:

- `table_service.get_event_details` (query at `table_service.py:884-926`)
  selects `markNoPacket_date` but **not** `no_packet_reason`,
  `two_attempts_flag`, `prior_event_date`, `prior_event_onsite_flag`,
  `other_cause`, and joins no `users` row for `marker_id`.
- `EventEdit.jsx:209-211` renders only the marking date; `grep` for
  `no_packet_reason|two_attempts|other_cause|prior_event|marker` in that file
  returns nothing.

**Decision**: FR-026 requires extending both — six columns plus a
`LEFT JOIN users mk ON mk.id = e.marker_id` in the detail query, and a
conditional block in `EventEdit.jsx`. The export path needs **no** change: the
export query (`table_service.py:602-608`, join at line 690) already selects
all six fields and the marker username, so FR-027 is already satisfied.

This is the one place the spec understated the work. It does not change scope
— FR-026 already required the display — only the estimate.

---

## D8. Study-awareness

**Decision**: No workflow-flag gating. The endpoint and the form are
unconditional.

**Rationale**: Constitution Principle V governs *bypassable* stages —
`ENABLE_SCRUBBING`, `ENABLE_SCREENING`, `ENABLE_SENDING`, `REVIEWER_COUNT`.
Packet upload is not among them: every study's lifecycle begins
`created → uploaded`, including the `scans` profile
(`created → uploaded → assigned → reviewer1_done → done`). An event that needs
a packet can fail to have one under every study, so the no-packet outcome is
universal. Adding a flag for it would invent a bypass the constitution does
not define.

The page's study-aware heading (spec 010) is untouched.

---

## D9. VTE fork

**Decision**: `frontend/src/studies/vte/EventUpload.jsx` is **not** modified.

**Rationale**: It carries a byte-identical dead form (same `<form>` with no
`onSubmit` at its line 265, same unbound inputs). Fixing it here would double
the divergent copy that Principle I exists to eliminate, and the fork is
already slated for retirement. Per Principle VI's unused-subsystem hygiene
rule, the plan instead **documents** the VTE page's form as known-inert in
`quickstart.md`, so it is a recorded gap rather than silent dead code.

---

## D10. Testing approach

**Decision**: Backend integration tests via pytest; frontend verified by lint
plus scripted manual checks.

**Rationale**: `flask_backend/tests/` has an established harness —
`conftest.py` provides `admin_client`, which patches
`_load_user_from_remote_header` to inject a full-admin `g.auth_user` and
satisfies the role decorators without a database.
`test_review_endpoint.py` shows the companion pattern for a transition
endpoint: `FakeEvent` / `FakeSession` stand-ins so lifecycle advancement is
asserted without a DB. The new endpoint's tests follow both directly.

The frontend has **no test runner** — `frontend/package.json` declares only
`dev`, `build`, `lint`, `preview`, with no vitest/jest and zero `*.test.*`
files. Introducing a test framework is out of scope for a bug fix, so
frontend verification is `npm run lint` plus the manual script in
`quickstart.md`. This matches the constitution's "SHOULD have at least one
integration test" for *backend endpoints*, which is met.

**Non-admin coverage note**: `conftest.py` offers only a full-admin fixture.
The site-restriction tests (FR-021) need a non-admin uploader identity, so the
tests define a local fixture in the same style rather than modifying the
shared one.

---

## D11. Contract regeneration

**Decision**: Regenerate `openapi.json` in the same PR via
`python -m flask_backend.generate_openapi` from the repository root.

**Rationale**: Mandated by the constitution's API-contracts gate whenever
routes or request bodies change. The file exists at the repo root and is
CI-refreshed; the regenerated delta must land with the change so reviewers see
the contract diff.

---

## Resolved unknowns summary

| Unknown | Resolution | Source |
|---------|-----------|--------|
| Is the form broken or unimplemented? | Unimplemented — no handler, no route, 4 unbound inputs | `EventUpload.jsx:325,353,404,415,429`; `app.py` grep |
| Endpoint shape | `POST /api/events/<id>/mark_no_packet`, JSON body | Precedent: `/screen` (1249), `/review` (2034) |
| Authorization rule | uploader-at-site or admin | `upload_raw` 1863-1872; `table_service.py:200` |
| Which statuses may be marked | `created` only | `get_events_need_packets` definition |
| `prior_event_date` format | zero-padded `MM-YYYY`; `00`/`0000` for blank | Legacy dump distinct values |
| Is "No, 2 attempts" allowed? | Yes — `two_attempts_flag = 0` in production | Legacy dump |
| Detail page readiness | Needs 6 columns + marker join + render block | `table_service.py:884-926`; `EventEdit.jsx` grep |
| Export readiness | Already complete, no change | `table_service.py:602-608,690` |
| Study gating | None — packet upload is a universal stage | Constitution Principle V |
| Frontend tests | No runner exists; lint + manual script | `frontend/package.json` |
