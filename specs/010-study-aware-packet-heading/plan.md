# Implementation Plan: Study-aware naming on the shared event pages and reviewer emails

**Branch**: `010-study-aware-packet-heading` | **Date**: 2026-09-08 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/010-study-aware-packet-heading/spec.md`

## Summary

Four page headings, two document-link areas, and the reviewer-assignment email
all name a study that is written into the code as the literal `MI`. Replace
each with the deployment's configured study, using two different mechanisms
because the material is different: headings and the email take a **mechanical
upper-cased study identity** (`mci` → `MCI`), while the document links take
**per-study recorded content**, because the files on disk are named `MI` while
the identity is `mci` and most studies have no documents at all.

The technical crux is that `get_study_type()` already defaults an unset
`STUDY_TYPE` to `mci`, so "not configured" is currently indistinguishable from
"configured as MCI" by the time any consumer sees it. The spec requires both
the headings and the email to omit the study word when nothing is configured.
Resolution: one new accessor in the shared configuration layer,
`get_study_label()`, which reads the raw variable and returns `""` when unset,
published to the browser as a new `study_label` field on `GET /api/config` so
the frontend and the emailer resolve from one rule rather than two.

No schema change, no migration. Nine files, one additive API field.

## Technical Context

**Language/Version**: Python 3.11 (Flask backend); JavaScript / JSX, React 19 (frontend)
**Primary Dependencies**: Flask, SQLAlchemy (backend); React 19, react-router-dom 6, Vite 7 (frontend) — **no new dependency**
**Storage**: MariaDB 10.11, shared schema under `init/`. **No schema change** — every value here is runtime configuration or derived presentation state
**Testing**: pytest (`flask_backend/tests/`, 11 modules). **No frontend test framework exists** — `frontend/package.json` defines `dev`, `build`, `lint`, `preview` and no test runner; React changes are verified by inspection and by the constitution's pre-merge `STUDY_TYPE` check
**Target Platform**: Linux server, Docker Compose; browser frontend
**Project Type**: Web application — Flask backend + React frontend
**Performance Goals**: None applicable. One added string field on a config endpoint already fetched once per app load; no new request, no new query
**Constraints**: `/api/config` gains one additive field, so `openapi.json` MUST be regenerated in the same PR. `get_study_type()` MUST keep its `mci` default — only the new accessor sees the raw value
**Scale/Scope**: 9 files. 8 literal strings replaced, 1 new config accessor, 1 new response field, 2 new frontend helpers, 1 component extracted for reuse, 3 pages newly threaded with props

No NEEDS CLARIFICATION remains — all five were resolved during `/speckit.clarify`
and are recorded in the spec's Clarifications section.

## Constitution Check

*GATE: evaluated before Phase 0 and re-evaluated after Phase 1 design.*

| # | Principle | Verdict | Basis |
|---|---|---|---|
| I | Single Codebase, Many Studies | **PASS** | Removes eight hard-coded study names from shared code. Creates no fork and no study branch. The legacy `studies/vte/` copies are explicitly out of scope and are not extended |
| II | Study Data Isolation | **PASS** | No data access of any kind. No query, no cross-study read. Reads one environment variable |
| III | Backwards Compatibility With Legacy Data | **PASS** | No schema, column, or legacy consumer touched. The `mci` column and the CSV export's study-named headers are explicitly out of scope |
| IV | Configuration Over Code Forks | **PASS** | Mechanism 1 (environment variable through shared code) for the label; mechanism 3 (study-scoped content) for documents. `get_study_label()` lives in `study_config.py`, so no scattered `os.environ` read. **No new `if/elif` on study type is introduced** — the label is a pure transformation and the documents are a keyed lookup |
| V | Workflow and Role Parity | **PASS** | No state, role, transition, or flag added or altered. Display text and one email's wording only |
| VI | Pre-Release Iteration and Discovery | **PASS** | `/api/config` gains a field, which this principle permits pre-release, with the regenerated `openapi.json` required in the same PR (tasked). Every observed behaviour was recorded before being changed — see `research.md`, where each decision opens with what the code does today and cites line numbers |

**Development Workflow & Quality Gates**

| Gate | Status |
|---|---|
| PR states which studies are affected | **Shared code — all studies.** To be stated in the PR body |
| Schema changes ship a migration plan | **N/A** — no schema change |
| `openapi.json` regenerated when routes or response shapes change | **Required.** `python -m flask_backend.generate_openapi`, tasked for the same PR |
| Backend endpoints have an integration test exercising role decorators | `/api/config` is an existing endpoint with existing role coverage in `test_config_endpoint.py`; extended, not replaced |
| Frontend study-specific components verified under at least one `STUDY_TYPE` | **Required and manual.** No test framework exists. Steps in `quickstart.md` |
| Local development parity | No compose change needed — `STUDY_TYPE` is already a documented `.env` variable (`default.env:71-74`) |
| Feature-flag discipline: unknown studies default to the safer value | **Honoured.** The safer value for a display label is *no label* rather than a guessed one, which is what FR-005 and FR-007 require and what `get_study_label()` returns |
| Unused subsystem hygiene | No dead code introduced. `GuidanceLinks` moves from a private definition in `Home.jsx` to a shared component with three consumers |

**Result: PASS, no violations.** The Complexity Tracking table below is
therefore empty, as the template requires.

One item is carried forward as a **known bounded inconsistency rather than a
violation**: `resolveReviewGuidance()` falls back to `mci` content for an
unrecognized study (spec 007, FR-008), while this feature's FR-017/FR-018
forbid falling back for documents. Both behaviours are kept, scoped to
different surfaces, and the divergence is confined to misconfigured
deployments. Reversing spec 007's decision is out of scope here. See
`research.md` R4.

## Project Structure

### Documentation (this feature)

```text
specs/010-study-aware-packet-heading/
├── plan.md                  # This file
├── spec.md                  # Feature specification (5 clarifications resolved)
├── research.md              # Phase 0 — 6 decisions, each recording prior behaviour
├── data-model.md            # Phase 1 — configuration and derived entities; no schema
├── quickstart.md            # Phase 1 — what changes, and how to verify it
├── contracts/
│   └── config-api.md        # Phase 1 — the additive `study_label` field
├── checklists/
│   └── requirements.md      # Spec quality checklist (16/16)
└── tasks.md                 # Phase 2 — NOT created by /speckit.plan
```

### Source Code (repository root)

```text
flask_backend/
├── study_config.py                    # + get_study_label(); + study_label on WorkflowConfig
├── app.py                             # + study_label in the /api/config body (~line 780)
├── emailer.py                         # ~ _format_subject() (58-60), _build_body() (77-80)
└── tests/
    ├── test_study_config.py           # + label cases: configured, padded, unset
    ├── test_config_endpoint.py        # + study_label exposed and correct
    └── test_emailer_study_naming.py   # NEW — subject/body across mci, scans, unset, 3rd reviewer

frontend/src/
├── App.jsx                            # + studyLabel state; pass to 4 pages
├── components/
│   ├── studyHeading.js                # NEW — headingSuffix(label, eventId)
│   ├── GuidanceLinks.jsx              # NEW — extracted from Home.jsx:96-112
│   └── reviewGuidance.js              # + `scrubbing` box per study; + resolveStudyDocuments()
└── pages/
    ├── Home.jsx                       # ~ import GuidanceLinks rather than define it
    ├── EventUpload.jsx                # ~ heading (247)
    ├── EventScreen.jsx                # ~ heading (53)
    ├── EventScrub.jsx                 # ~ heading (73), document links (67, 69)
    └── EventReview.jsx                # ~ heading (187), document links (181, 183)

openapi.json                           # regenerated — same PR
```

**Structure Decision**: Web application, using the repository's existing
`flask_backend/` + `frontend/` split unchanged. No new directory and no new
module boundary. Both new frontend helpers go in `frontend/src/components/`
beside `studyTitle.js` and `reviewGuidance.js`, which are the established homes
for pure, study-aware presentation logic.

## Implementation Sequence

Ordered so each step is independently verifiable and the backend lands before
its consumers.

1. **`get_study_label()` + `study_label` on `WorkflowConfig`** — the shared rule
   both consumers depend on. Unit-tested against configured, padded, and unset
   values. *(FR-003, FR-005, FR-008)*
2. **Expose `study_label` on `/api/config`; regenerate `openapi.json`** — the
   transport, with `study_type` and its default explicitly asserted unchanged.
   *(contract)*
3. **Reword the email** — subject and body, both collapsing cleanly when the
   label is empty. Independently shippable; no frontend dependency.
   *(FR-020 – FR-026, User Story 3)*
4. **`studyHeading.js` + thread `studyLabel` through `App.jsx`** — including
   props for the three pages that currently receive none. *(FR-009, FR-010)*
5. **The four headings** — one line each, all through the same helper.
   *(FR-001 – FR-011, User Story 1)*
6. **Extract `GuidanceLinks`; add the `scrubbing` box and
   `resolveStudyDocuments()`; rewire the two document areas** — last, because
   it touches the most-established shared content. *(FR-012 – FR-019, User Story 2)*

Steps 1–3 are backend and fully covered by automated tests. Steps 4–6 are
frontend and are not, which is the honest limitation stated in `research.md` R6
and worked around in `quickstart.md` with manual verification steps.

## Complexity Tracking

No constitutional violations. Table intentionally empty.
