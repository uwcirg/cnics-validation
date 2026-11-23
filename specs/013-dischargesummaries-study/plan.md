# Implementation Plan: Add the `dischargesummaries` Study Type

**Branch**: `013-dischargesummaries-study` | **Date**: 2026-10-23 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/013-dischargesummaries-study/spec.md`

## Summary

Add a third usable study type, `dischargesummaries`: site uploaders upload a
redacted discharge summary, an admin scrubs it, an admin assigns one
reviewer, and the review completes the event. The lifecycle is
`created → uploaded → scrubbed → assigned → reviewer1_done → done`.

Almost all of the workflow already exists and is driven by flags. The work
is:

- a new profile entry;
- a study module and schema that own this database's `reviews` table;
- three server-side guards in shared code that the spec's privacy and
  lifecycle rules need, which currently hold only because the UI hides the
  options (assign-before-scrub, site re-upload after scrub, per-event
  download access);
- an optional `STUDY_LABEL` for readable headings and emails;
- making the Docker network range settable, so a second stack can run on the
  `scans` VMs;
- documentation.

**The review form's content is blocked on FR-011.** The questions are not
known yet. The plan ships in two phases (research D5):

- **Phase A**: everything except the review questions. The review step shows
  "not available yet" and refuses submissions.
- **Phase B**: the review form, its storage, the export columns and the
  instructions page, added when the questions arrive.

## Technical Context

**Language/Version**: Python 3.11 (Flask backend); JavaScript / JSX, React 19 (frontend)
**Primary Dependencies**: Flask, SQLAlchemy, mysql-connector-python; React 19, react-router-dom 6, Vite 7. **No new dependency.**
**Storage**: MariaDB 10.11. There is a new per-study schema file, `init/studies/dischargesummaries.sql`, holding `reviews` with the shared columns only in Phase A. **No change to shared tables. No migration for existing `mci`/`scans` databases.** Files go on the existing per-deployment downloads volume.
**Testing**: pytest via system `python3 -m pytest flask_backend/tests` (baseline 309 passed); frontend `npm run lint` + `npm run build`; `docker compose config` for the network interpolation.
**Target Platform**: Linux VMs (the existing `scans` dev and prod VMs) running Docker Compose behind Apache + LDAP.
**Project Type**: Web application (Flask API + React SPA).
**Performance Goals**: No new targets. Volumes match `scans`: tens to low hundreds of events per site.
**Constraints**: Must coexist on one host with a running `scans` stack, with no edit to `docker-compose.yaml` and no `scans` downtime. Must reuse the `scans` prod SSH tunnel (user decision). Must not change `mci`/`scans` legitimate behavior. The implementer has no local stack (diagnose from code; operators deploy).
**Scale/Scope**: Backend ~6 files, frontend ~4 files plus 2 new components, 1 new SQL file, compose and docs. Phase B adds about 1 module rewrite, 2 components, 1 HTML page and 1 migration script.

All Technical Context unknowns are resolved in [research.md](research.md).
The single open product question (FR-011) is handled by the phase split,
not by guessing.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | How this plan complies |
|-----------|--------|------------------------|
| I. Single codebase, many studies | ✅ | New study added as configuration plus study-scoped files; no fork, no branch per study. |
| II. Study data isolation | ✅ | Own `.env`, `COMPOSE_PROJECT_NAME`, DB container/volume/credentials, downloads volume and domain. The DB gets only `dischargesummaries` study tables from the first deploy (D5 avoids the `mci` fallback). The shared tunnel is read-only access to the shared upstream roster, which `scans` already reads; no cross-study app data is reachable. |
| III. Legacy compatibility | ✅ | No shared-schema change; lifecycle states, role flags and `patients_view` untouched. |
| IV. Configuration over code forks | ✅ | Profile table entry (D1), env settings (`STUDY_LABEL`, `INTERNAL_SUBNET`/`GATEWAY`), `init/studies/<study>.sql`, `models/studies/<study>.py`, `frontend/src/studies/<study>/`, keyed maps. The three guards are shared rules, not study checks. The compose change removes the need to edit `docker-compose.yaml` per deployment. No `if study == …` added. |
| V. Workflow and role parity | ✅ | Uses only the named flags (scrub on, screen off, send off, `REVIEWER_COUNT=1`), a combination the principle explicitly allows. No state or role added, renamed or removed. |
| VI. Pre-release iteration | ✅ | Observed behavior is recorded before each shared change (D2, D3, D4, D6, D8). The PR description must restate D2–D4 as behavior changes for `mci`/`scans`. |
| Security & data governance | ✅ | Tightens access: per-event download checks, no raw file to reviewers or other-site uploaders. Auth chain (Apache/LDAP → `X-Remote-User`) unchanged. |
| Every study defines `reviews` with `id`, `event_id`, `reviewer_id` | ✅ | Phase A table has exactly those plus `created`. |

**Gate result: PASS.** No violations, so Complexity Tracking is empty.

**Post-design re-check (after Phase 1)**: PASS. The data model adds no shared
columns. The contracts change three shared endpoints by narrowing access
only, which is documented, and the UI differences all come from config.

## Project Structure

### Documentation (this feature)

```text
specs/013-dischargesummaries-study/
├── spec.md
├── plan.md              # this file
├── research.md          # D1–D12
├── data-model.md        # lifecycle, reviews table, access matrix
├── quickstart.md        # side-by-side deployment on the scans VMs
├── contracts/
│   ├── api-and-config.md
│   └── ui.md
├── checklists/requirements.md
└── tasks.md             # /speckit.tasks (not created here)
```

### Source Code (repository root)

```text
docker-compose.yaml                         # D8: ${INTERNAL_SUBNET}/${INTERNAL_GATEWAY}
default.env                                 # new STUDY_TYPE value, STUDY_LABEL, INTERNAL_*; worked example
README.md                                   # study list + new settings
docs/template-setup-guide.md                # dischargesummaries worked example (beside scans)
docs/cnics_data-bridge.md                   # shared-tunnel section: extra INPUT rule, scans-down dependency

init/studies/dischargesummaries.sql         # NEW: reviews (id, event_id, reviewer_id, created)
init/migrations/013-dischargesummaries-reviews.sh   # Phase B only

flask_backend/study_config.py               # D1 profile; D6 STUDY_LABEL in get_study_label()
flask_backend/models/studies/dischargesummaries.py  # NEW: Phase A module (refuses reviews)
flask_backend/table_service.py              # D2 guard in assign_events (reuses predicate builder)
flask_backend/app.py                        # D3 upload_raw status guard; D4 download access helper
flask_backend/tests/
├── test_study_config.py                    # profile + STUDY_LABEL
├── test_study_modules.py                   # resolves without fallback
├── test_dischargesummaries_workflow.py     # NEW: end-to-end lifecycle + refusals
├── test_assign_many.py                     # D2 for all profiles
├── test_event_download.py                  # D4 access table
└── test_review_endpoint.py                 # Phase A refusal

frontend/src/components/reviewGuidance.js   # D7 entry
frontend/src/components/review/reviewForms.js  # register dischargesummaries
frontend/src/studies/dischargesummaries/
├── DischargesummariesReviewForm.jsx        # NEW: Phase A notice → Phase B form
└── DischargesummariesReviewSummary.jsx     # NEW
frontend/src/pages/EventUpload.jsx, EventReupload.jsx, EventReview.jsx  # show 409/403 messages if not already surfaced

app/webroot/files/instructions/dischargesummaries.html  # Phase B only
```

**Structure Decision**: The existing web-application layout
(`flask_backend/`, `frontend/`, `init/`). Study-specific code goes only in
the per-study locations Principle IV names. Shared changes are limited to
the three guards, the label setting and the compose network interpolation.

## Phasing for `/speckit.tasks`

- **Phase A, ship now**: US1 except the review submission, US2, US3, US4. Ready for dev deployment and upload/scrub testing.
- **Phase B, blocked on FR-011**: the review form, storage, export, instructions page and migration script. `tasks.md` should list these tasks as blocked until the questions arrive, then refine them with `/speckit.clarify` → `/speckit.plan` updates.

## Complexity Tracking

No constitution violations; nothing to justify.
