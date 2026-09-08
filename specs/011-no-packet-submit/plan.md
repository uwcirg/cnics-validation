# Implementation Plan: No-Packet Submission Records the Event Outcome

**Branch**: `011-no-packet-submit` | **Date**: 2026-09-08 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/011-no-packet-submit/spec.md`

## Summary

The "If no packet is available" form on `/events/upload` is fully rendered but
completely inert: its `<form>` has no `onSubmit` handler, four of its six
follow-up inputs are unbound, and no backend route exists to receive the
submission. A coordinator answers every question, presses Submit, sees no
error, and the event is unchanged — silent data loss.

The fix is narrow because everything downstream already exists. The `events`
table already carries all eight fields this feature writes, the
`no_packet_available` lifecycle state already exists, the needs-packet queue
already excludes anything that is not `created`, the "no packet available"
list already queries for it, and the export already selects every field. The
work is: **one backend endpoint, one read-path extension, and the frontend
wiring the form has always been missing.**

Approach: a new `POST /api/events/<id>/mark_no_packet` shaped after the
existing `/screen` and `/review` transition endpoints, authorized identically
to `upload_raw`, gated to `status = 'created'`, writing the reason plus exactly
the follow-ups that reason calls for and NULL elsewhere. Field encodings were
recovered from real legacy production rows rather than guessed (research D5) —
notably `prior_event_date` is zero-padded `MM-YYYY` with `00`/`0000` sentinels
for blank halves, and `two_attempts_flag = 0` is a legitimate recorded answer.

## Technical Context

**Language/Version**: Python 3.11 (Flask backend); JavaScript / JSX, React 19 (frontend)
**Primary Dependencies**: Flask, SQLAlchemy, mysql-connector-python; React 19, react-router-dom 6, Vite 7 — **no new dependency**
**Storage**: MariaDB 10.11, shared schema under `init/`. **No schema change, no migration** — all eight fields exist and are populated in legacy data
**Testing**: pytest (`flask_backend/tests/`, `admin_client` fixture in `conftest.py`). Frontend has **no test runner** — lint plus the manual script in `quickstart.md` (research D10)
**Target Platform**: Linux server, Docker Compose; Apache basic+ldap edge forwarding `X-Remote-User`
**Project Type**: Web application — Flask API + React SPA
**Performance Goals**: Single indexed row read plus one row update; no measurable performance dimension
**Constraints**: `other_cause` is coordinator-authored free text about patient records — PHI-adjacent, never logged. Submission must be all-or-nothing (FR-009); any rejection leaves the row untouched (FR-016)
**Scale/Scope**: 1 new endpoint, 1 modified query, 2 modified frontend pages, 1 new test module. Roughly 300 lines of change

No NEEDS CLARIFICATION remain — all resolved in [research.md](./research.md).

## Constitution Check

*GATE: evaluated before Phase 0 and re-evaluated after Phase 1 design.*

| Principle | Assessment | Verdict |
|-----------|-----------|---------|
| **I. Single Codebase, Many Studies** | Shared code only. The VTE fork's identical inert form is deliberately **not** duplicated into — fixing it there would deepen the fork this principle exists to eliminate (research D9). | ✅ Pass |
| **II. Study Data Isolation** | No cross-study access. One deployment, one study, one database; the endpoint reads and writes a single event row in the deployment's own schema. | ✅ Pass |
| **III. Backwards Compatibility With Legacy Data** | Strengthened, not threatened. No schema change. Field encodings were **recovered from legacy production rows** (`MM-YYYY`, `00`/`0000` sentinels, `two_attempts_flag = 0`) so new rows are indistinguishable from CakePHP-era ones. Legacy consumers of these columns keep working. | ✅ Pass |
| **IV. Configuration Over Code Forks** | No `STUDY_TYPE` branching, no new flag, no `if/elif` on study. The site-authorization check is lifted into one shared helper instead of a second copy — the divergence this principle warns about. | ✅ Pass |
| **V. Workflow and Role Parity** | Uses the **existing** `no_packet_available` state and the existing `uploader`/`admin` roles. Nothing redefined, removed, or renamed. Not flag-gated: packet upload is not a bypassable stage — every study, `scans` included, begins `created → uploaded` (research D8). | ✅ Pass |
| **VI. Pre-Release Iteration and Discovery** | Current behavior recorded before changing it: research D1 documents the missing handler, the four unbound inputs, and the absent route with file:line evidence. The VTE fork's inert twin is **documented as a known gap** in `quickstart.md` rather than left silent, per the unused-subsystem rule. | ✅ Pass |
| **Security — PHI handling** | `other_cause` is never logged at any level; logs carry the event id and error class only, following `_write_import_record`'s precedent. | ✅ Pass |
| **Security — Authorization** | `@requires_auth` + `@requires_any_role('uploader', 'admin')` declared at definition time, plus the same-site check. Matches `upload_raw`, which gates the same queue item. No "open by default". | ✅ Pass |
| **Security — File storage** | No file I/O. `FILES_DIR`/`DOWNLOADS_DIR` untouched. | ✅ Pass |
| **Quality — API contracts** | `openapi.json` regenerated via `python -m flask_backend.generate_openapi` in the same PR. | ✅ Pass |
| **Quality — Testing discipline** | New endpoint gets an integration test module exercising the role decorators with representative fixtures, as required. | ✅ Pass |
| **Quality — Schema changes** | None, so the migration-plan gate does not apply. | ✅ N/A |

**Initial gate: PASS.** No violations, no entries in Complexity Tracking.

**Post-Phase-1 re-evaluation: PASS.** The design added no study branching, no
new state, no new dependency, and no schema change. The single design decision
with constitutional weight — lifting `upload_raw`'s inline site check into a
shared helper rather than copying it — moves *toward* Principle IV rather than
away. The one correction Phase 1 forced (research D7: the event-detail query
does not currently return these fields, so FR-026 needs a read-path change)
adds work inside the existing feature scope and touches no principle.

## Project Structure

### Documentation (this feature)

```text
specs/011-no-packet-submit/
├── plan.md                          # This file
├── spec.md                          # Feature specification
├── research.md                      # Phase 0 — 11 decisions, legacy-data grounded
├── data-model.md                    # Phase 1 — field matrix, encodings, validation
├── quickstart.md                    # Phase 1 — verification script
├── checklists/
│   └── requirements.md              # Spec quality checklist
└── contracts/
    ├── mark-no-packet-api.md        # Phase 1 — the new endpoint
    └── no-packet-form-ui.md         # Phase 1 — the frontend contract
```

### Source Code (repository root)

```text
flask_backend/
├── app.py                      # + POST /api/events/<id>/mark_no_packet
│                               # + shared site-authorization helper
│                               #   (lifted out of upload_raw, line 1863-1872)
├── table_service.py            # get_event_details (884-926): + 5 no-packet
│                               #   columns, + LEFT JOIN users mk on marker_id
├── models.py                   # unchanged — all 8 fields already mapped
└── tests/
    ├── conftest.py             # unchanged — admin_client reused as-is
    └── test_mark_no_packet.py  # NEW — persistence, validation, authz, conflict

frontend/src/pages/
├── EventUpload.jsx             # bind 4 unbound inputs; add handleNoPacketSubmit;
│                               #   add noPacketStatus/noPacketError feedback;
│                               #   clear follow-ups on reason change
└── EventEdit.jsx               # render reason + follow-ups + marker beside
                                #   the existing markNoPacket_date row (209-211)

openapi.json                    # regenerated

init/                           # UNCHANGED — no schema change, no migration
frontend/src/studies/vte/       # UNCHANGED — see research D9
```

**Structure Decision**: Web application — the existing `flask_backend/` +
`frontend/` split. This feature adds no directory and no module; it extends
four existing files and adds one test module. The frontend change is confined
to the two page components that own the affected surfaces, matching how
features 003-010 were structured.

## Phase Summary

**Phase 0 — [research.md](./research.md)**: 11 decisions. D1 records the
current (non-)behavior per Principle VI. D2-D4 fix the endpoint shape,
authorization, and the `created`-only guard. **D5 recovers the field encodings
from real production rows** — the highest-value finding, since `MM-YYYY` with
`00`/`0000` sentinels and a legitimate `two_attempts_flag = 0` would all have
been guessed wrong otherwise. D7 corrects a spec assumption: the detail page
does *not* already return these fields. D8-D11 settle study-awareness, the VTE
fork, testing, and contract regeneration.

**Phase 1 — [data-model.md](./data-model.md), [contracts/](./contracts/),
[quickstart.md](./quickstart.md)**: the field-applicability matrix and its
NULL rule, the three-state `prior_event_date` encoding, validation rules
V1-V11 with their HTTP codes, the request/response contract with per-field
error messages, the frontend wiring contract, and a verification script that
reproduces the user's two original attempts.

**Phase 2 — not performed by this command.** `/speckit.tasks` generates
`tasks.md`.

## Notable Design Points

1. **Two spec requirements are already satisfied by the write alone.** FR-024
   (leaves the needs-packet queue) and FR-025 (appears in the no-packet list)
   need no query change: the queue is defined as `status = 'created'` and the
   list already requests `by_status/no_packet_available`. FR-027 (export) is
   likewise already complete.

2. **One spec assumption was wrong and is corrected here.** The spec said the
   detail page renders these fields "using the fields already present there";
   only `markNoPacket_date` is present. FR-026 therefore requires five columns
   and a marker join added to `get_event_details`. Scope is unchanged — the
   display was always required — but the estimate grows.

3. **Legacy data settled the question the spec defaulted on.** The spec assumed
   "No, 2 attempts were not made" should be recorded rather than blocked;
   production rows carry `two_attempts_flag = 0`, confirming it.

4. **The silence is treated as a first-class defect.** FR-017 to FR-020 exist
   because an unreported failure is what made the user re-enter data twice. The
   form has no feedback affordance today; adding one is part of the fix, not
   polish.

## Complexity Tracking

No constitutional violations. Table intentionally empty.
