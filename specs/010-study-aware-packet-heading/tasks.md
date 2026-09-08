---
description: "Task list for 010-study-aware-packet-heading"
---

# Tasks: Study-aware naming on the shared event pages and reviewer emails

**Input**: Design documents from `/specs/010-study-aware-packet-heading/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/config-api.md, quickstart.md

**Tests**: Backend test tasks are included because `research.md` R6 committed to three specific test modules and the constitution's Development Workflow gate requires endpoint coverage. They are written alongside their implementation, not test-first — no TDD ordering was requested. **The frontend has no test framework** (`frontend/package.json` defines `dev`, `build`, `lint`, `preview` only), so User Stories 1, 2 and the frontend half of 4 are verified manually per `quickstart.md`.

**Organization**: Tasks are grouped by user story. Every task names an absolute-from-repo-root file path.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel — different file, no dependency on an incomplete task
- **[Story]**: US1–US5, mapping to the spec's user stories
- Line numbers cited are current as of 2026-09-08 and will shift as edits land

## Path Conventions

Web application, per `plan.md`: `flask_backend/` (Python 3.11 / Flask) and `frontend/src/` (React 19 / Vite 7). No new directory is created.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Establish a known-good baseline before anything changes. No dependencies are added and no scaffolding is needed — this feature touches nine existing files.

- [X] T001 Establish a green backend baseline by running `python -m pytest flask_backend/tests/ -q` from the repository root; record any pre-existing failures so they are not later mistaken for regressions from this feature
- [X] T002 [P] Establish a clean frontend baseline by running `npm run lint && npm run build` in `frontend/`

**Checkpoint**: Baseline recorded — changes can now be attributed correctly.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The one shared label rule, its transport to the browser, and the prop plumbing three pages currently lack.

**⚠️ CRITICAL**: No user story can be completed until this phase is done. Note that the blocking is not uniform — see Dependencies below. US3 (email) needs only T003; US2 (documents) needs only T010–T014.

### The shared label rule

- [X] T003 Add `get_study_label()` to `flask_backend/study_config.py`, returning `os.getenv("STUDY_TYPE", "").strip().upper()` — the empty string when unset. Leave `get_study_type()` and its `"mci"` default untouched; docstring must state why the two differ (research R1, FR-003, FR-005, FR-008)
- [X] T004 Add a `study_label: str` field to the `WorkflowConfig` frozen dataclass in `flask_backend/study_config.py` and populate it from `get_study_label()` wherever the config is assembled (depends on T003)
- [X] T005 [P] Add label cases to `flask_backend/tests/test_study_config.py`: a configured study yields its upper-cased value; `"  Scans  "` yields `"SCANS"`; an unset `STUDY_TYPE` yields `""` **while `get_study_type()` still yields `"mci"`** — assert both in the same test so the distinction cannot silently regress (depends on T003)

### Transport

- [X] T006 Add `'study_label': cfg.study_label` to the `/api/config` response body in `flask_backend/app.py` (~line 780), leaving `study_type`, `study_title`, and `workflow` unchanged (depends on T004)
- [X] T007 [P] Add `study_label` assertions to `flask_backend/tests/test_config_endpoint.py` per the four cases in `contracts/config-api.md`, including that `study_type` still returns `"mci"` when `STUDY_TYPE` is unset (depends on T006)
- [X] T008 Regenerate the API contract by running `python -m flask_backend.generate_openapi` from the repository root and commit the updated `openapi.json` — required in the same PR by the constitution's Development Workflow gate (depends on T006)

### Frontend plumbing

- [X] T009 [P] Create `frontend/src/components/studyHeading.js` exporting `headingSuffix(studyLabel, eventId)`, which returns `` `${eventId}` `` when the trimmed label is empty and `` `${label} ${eventId}` `` otherwise — one space, never two, never a dangling separator (research R3, FR-006)
- [X] T010 Add `studyLabel` state to `frontend/src/App.jsx`, initialised to `''` and set from `json.data.study_label` inside the existing `fetchConfig()` handler alongside `study_type` (~line 106). The `if (sl)` guard keeps it `''` for an unset deployment, which is the no-study-word form FR-009 requires while config resolves
- [X] T011 Pass `studyLabel` to all four route render sites in `frontend/src/App.jsx` — `EventUpload` (line 271), `EventReview` (293), `EventScreen` (305), `EventScrub` (329) — and additionally pass `studyType` and `configResolved` to the latter three, which currently receive no props at all (depends on T010)
- [X] T012 [P] Add `{ studyLabel, configResolved = true }` to the `EventScreen` signature in `frontend/src/pages/EventScreen.jsx` (line 4), replacing the empty parameter list (depends on T011)
- [X] T013 [P] Add `{ studyLabel, studyType, configResolved = true }` to the `EventScrub` signature in `frontend/src/pages/EventScrub.jsx` (line 5), replacing the empty parameter list (depends on T011)
- [X] T014 [P] Add `{ studyLabel, studyType, configResolved = true }` to the `EventReview` signature in `frontend/src/pages/EventReview.jsx` (line 4), replacing the empty parameter list (depends on T011)
- [X] T015 [P] Add `studyLabel` to the existing `EventUpload` signature in `frontend/src/pages/EventUpload.jsx` (line 116), keeping `studyType`, `configResolved` and `workflow` (depends on T011)

**Checkpoint**: The label resolves identically on both sides of the wire, and all four pages can see it. User story work can begin.

---

## Phase 3: User Story 1 - Every workflow page names the deployment's own study (Priority: P1) 🎯 MVP

**Goal**: All four workflow headings name the configured study instead of the literal `MI`.

**Independent Test**: Configure a deployment for a study other than the cardiology one, visit all four pages for a known event, and confirm each heading names that deployment's study.

**Implementation** — four one-line edits in four separate files, all routed through the same helper so they cannot drift (FR-010).

- [X] T016 [P] [US1] Replace `Packet for MI {eventId}` with `Packet for {headingSuffix(studyLabel, eventId)}` at `frontend/src/pages/EventUpload.jsx` line 247, importing `headingSuffix` from `../components/studyHeading`
- [X] T017 [P] [US1] Replace `Screen charts for MI {eventId}` with the `headingSuffix` form at `frontend/src/pages/EventScreen.jsx` line 53, importing from `../components/studyHeading`
- [X] T018 [P] [US1] Replace `Upload scrubbed charts for MI {eventId}` with the `headingSuffix` form at `frontend/src/pages/EventScrub.jsx` line 73, importing from `../components/studyHeading`
- [X] T019 [P] [US1] Replace `Review event: MI {eventId}` with `Review event: {headingSuffix(studyLabel, eventId)}` at `frontend/src/pages/EventReview.jsx` line 187, importing from `../components/studyHeading`
- [X] T020 [US1] Run `npm run lint && npm run build` in `frontend/` and confirm all four pages compile with no unused-import or unused-prop warnings (depends on T016–T019)

**Checkpoint**: All four headings are study-aware. On an `mci` deployment they now read `MCI` — the accepted change recorded in spec FR-011.

---

## Phase 4: User Story 2 - Linked documents match the study, and absent documents show nothing (Priority: P1)

**Goal**: The scrubbing protocol and reviewer instructions come from per-study content, and a study with no documents shows no link area at all.

**Independent Test**: On a deployment whose study has documents, follow each link and confirm it opens that study's document; on a `scans` deployment, confirm no link area appears on either page.

**Implementation** — extract the component that already handles the empty case, extend the map, then rewire two pages.

- [X] T021 [US2] Create `frontend/src/components/GuidanceLinks.jsx` by moving the `GuidanceLinks` function verbatim from `frontend/src/pages/Home.jsx` (lines 96–112) and exporting it; it already returns `null` for an empty link list, which is exactly FR-015 and FR-016
- [X] T022 [US2] Update `frontend/src/pages/Home.jsx` to import `GuidanceLinks` from `../components/GuidanceLinks` and delete the local definition, leaving both existing call sites unchanged (depends on T021)
- [X] T023 [US2] Add a `scrubbing` box to each study entry in `frontend/src/components/reviewGuidance.js`: for `mci`, the two `CNICS MI event scrubbing protocol` links currently hard-coded at `EventScrub.jsx` lines 67 and 69 (`.doc` with `download: true`, `.pdf` with `download: false`, `linkLabel: 'View as:'`); for `scans`, `{ items: [], links: [] }`
- [X] T024 [US2] Add `resolveStudyDocuments(studyType)` to `frontend/src/components/reviewGuidance.js` returning `{ scrubbing, instructions }` from the map with **no `|| STUDY_GUIDANCE.mci` fallback**, defaulting each to `{ links: [] }` when the study has no entry. Add a comment explaining why this deliberately differs from `resolveReviewGuidance()` above it, citing spec 007 FR-008 versus this feature's FR-017 (research R4) (depends on T023)
- [X] T025 [US2] Replace the hard-coded `<a>` pair at `frontend/src/pages/EventScrub.jsx` lines 67–69 with `<GuidanceLinks box={documents.scrubbing} />`, and render the enclosing `infobox` div and its `<h3>Scrubbing Instructions:</h3>` only when `documents.scrubbing.links.length > 0`, so no orphaned header remains (FR-015) (depends on T021, T024)
- [X] T026 [US2] Replace the hard-coded `<a>` pair at `frontend/src/pages/EventReview.jsx` lines 181–183 with `<GuidanceLinks box={documents.instructions} />`, and render the enclosing `boxright` div and its `<h3>Review Instructions:</h3>` only when `documents.instructions.links.length > 0` (FR-015) (depends on T021, T024)
- [X] T027 [US2] Confirm the review page and the home page now offer byte-identical instruction links for the same study, both sourced from the `instructions` box in `reviewGuidance.js` (FR-014) (depends on T022, T026)

**Checkpoint**: No shared page hard-codes a document name. A `scans` deployment shows no document boxes.

---

## Phase 5: User Story 3 - The assignment email names the study the reviewer is actually reviewing (Priority: P1)

**Goal**: The assignment email's subject and body name the configured study, on all three reviewer slots.

**Independent Test**: Trigger an assignment on a non-cardiology deployment and inspect the resulting email's subject and body.

**Note**: This story depends only on T003. It needs neither the `/api/config` change nor any frontend work, so it is the smallest independently shippable increment and the only one with full automated coverage.

- [X] T028 [US3] Rewrite `_format_subject()` in `flask_backend/emailer.py` (lines 58–60) to interpolate `get_study_label()` between the prefix and `Review Assignment`, collapsing cleanly to `"{prefix} Review Assignment – Event {id}"` with no double space when the label is empty; import from `.study_config`
- [X] T029 [US3] Rewrite the opening sentence in `_build_body()` in `flask_backend/emailer.py` (lines 77–80) from `"You have been assigned a Myocardial Infarction (MI) review."` to `"You have been assigned a review in the {label} study."`, falling back to `"You have been assigned a review."` when the label is empty. **No indefinite article may precede the label** (FR-022) and the full clinical name is deliberately dropped (FR-023) (depends on T028)
- [X] T030 [US3] Create `flask_backend/tests/test_emailer_study_naming.py` covering subject and body for `STUDY_TYPE=mci` and `STUDY_TYPE=scans`, asserting the study is named, that `"Myocardial"` no longer appears, and that no `" a MCI"`-style article precedes the label. Use `EMAIL_TEST_MODE=1`, which returns the composed `subject` and `body_preview` without SMTP (depends on T029)
- [X] T031 [US3] Extend `flask_backend/tests/test_emailer_study_naming.py` with a third-reviewer case asserting `send_third_reviewer_emails_for_event_ids` produces the same naming as the first two, since both paths share `_format_subject`/`_build_body` (FR-025) (depends on T030)

**Checkpoint**: Every reviewer on every study receives an email naming their own study.

---

## Phase 6: User Story 4 - Headings degrade gracefully when no study is configured (Priority: P2)

**Goal**: An unset or unresolved study identity produces a readable line with no study word — never a blank, a stray separator, or `undefined`.

**Independent Test**: Start with `STUDY_TYPE` unset and confirm all four headings read e.g. `Packet for 4821`, with no document boxes and no placeholder text.

**Note**: Most of this story is satisfied by construction — `headingSuffix` (T009) collapses on an empty label, `studyLabel` initialises to `''` (T010), and `resolveStudyDocuments` returns empty boxes (T024). These tasks prove it rather than build it.

- [X] T032 [P] [US4] Add unit cases to `frontend/src/components/studyHeading.js`'s usage expectations by asserting behaviour in a short comment block in the file: empty, whitespace-only, `null`, and `undefined` labels all yield the bare event identifier with no leading space (FR-006)
- [X] T033 [P] [US4] Add unset-study cases to `flask_backend/tests/test_emailer_study_naming.py`: with `STUDY_TYPE` unset, the subject contains no study token and no double space, and the body reads `"You have been assigned a review."` (FR-024) (depends on T030)
- [X] T034 [US4] Confirm `frontend/src/App.jsx` leaves `studyLabel` as `''` when `/api/config` fails, since the existing `catch` block keeps state defaults and the `finally` still sets `configResolved` — verify no code change is needed and note it in the PR body (FR-009) (depends on T010)
- [ ] T035 [US4] Verify manually against a deployment with `STUDY_TYPE` unset that all four headings render the bare-identifier form and neither document box appears, per `quickstart.md` step 3 (depends on T016–T019, T025, T026)

**Checkpoint**: The feature fails safe. An unconfigured deployment names no study rather than the wrong one.

---

## Phase 7: User Story 5 - One deployment reads consistently throughout (Priority: P3)

**Goal**: Banner, guidance, headings, documents and email all refer to one study.

**Independent Test**: On a single configured deployment, walk all five surfaces and confirm they agree.

- [ ] T036 [US5] Verify on a configured deployment that the banner title, the home-page guidance, all four headings, both document areas, and a generated assignment email refer to the same study, per `quickstart.md`; confirm the banner may read `MCI Project` where headings read `MCI` without that counting as disagreement (SC-008) (depends on Phases 3–5)
- [ ] T037 [US5] Verify that changing `STUDY_TYPE` in the deployment's `.env` and restarting updates all five surfaces together, with none retaining the previous study, following the two-configuration walkthrough in `specs/010-study-aware-packet-heading/quickstart.md` (depends on T036)

**Checkpoint**: All five user stories complete.

---

## Phase 8: Polish & Cross-Cutting Concerns

- [X] T038 [P] Add a comment above `resolveReviewGuidance()` in `frontend/src/components/reviewGuidance.js` recording the deliberate divergence between it and `resolveStudyDocuments()` — the home page falls back to `mci` content for an unrecognised study per spec 007 FR-008, the workflow pages do not per FR-017 — so a future reader does not "fix" one to match the other (research R4)
- [X] T039 [P] Record in the PR body that this change affects **shared code and therefore all studies**, as the constitution's change-review gate requires, and state the three accepted user-visible changes: `MI` → `MCI` in headings on the cardiology deployment, `SCANS` on a scans deployment, and the loss of the `Myocardial Infarction (MI)` expansion in the assignment email
- [X] T040 [P] Record in the PR body the current observed behaviour that was replaced — the eight literal strings and the previous email subject and body — satisfying Principle VI's requirement to document prior behaviour before changing it
- [X] T041 Run the full backend suite `python -m pytest flask_backend/tests/ -q` and confirm no regression against the T001 baseline (depends on all backend tasks)
- [X] T042 Run `npm run lint && npm run build` in `frontend/` and confirm no regression against the T002 baseline (depends on all frontend tasks)
- [X] T043 Open a follow-up issue for the bounded inconsistency in research R4 — whether the home page should keep falling back to `mci` guidance for an unrecognised study now that the workflow pages do not — so the decision is revisited deliberately rather than forgotten
- [X] T044 [P] Open a follow-up issue for the two stale request paths recorded in the spec's Out of scope section: `frontend/src/pages/Home.jsx` line 126 requests `/api/mci/tables/events` and line 221 sets `endpoint="/api/mci/events"`, neither of which the backend serves, and line 138 passes `?study=mci` to an endpoint that takes no arguments

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies
- **Foundational (Phase 2)**: Depends on Setup. Blocks all stories, but **not uniformly** — see below
- **User Stories (Phases 3–7)**: See the per-story prerequisites
- **Polish (Phase 8)**: Depends on the stories being delivered

### The foundational phase does not block every story equally

| Story | Actually needs | Can start once |
|---|---|---|
| US3 (email, P1) | T003 only | The label function exists — no transport, no frontend |
| US2 (documents, P1) | T011, T013, T014 | The two pages receive props |
| US1 (headings, P1) | T003–T015 | The whole foundational phase |
| US4 (degradation, P2) | Same as US1, plus T030 | After US1 and US2 |
| US5 (consistency, P3) | Phases 3–5 complete | Last |

This matters for sequencing: **US3 is the shortest path to something shippable and fully tested**, needing one function and two string edits.

### Within Phase 2

- T003 → T004 → T006 → T008; T005 parallel after T003; T007 after T006
- T009 is independent of everything
- T010 → T011 → T012, T013, T014, T015 (the last four in parallel)

### Within each story

- US1: T016–T019 all parallel, then T020
- US2: T021 → T022; T023 → T024; then T025 and T026 (both need T021 and T024); then T027
- US3: T028 → T029 → T030 → T031
- US4: T032 independent; T033 after T030; T034 after T010; T035 after US1 and US2
- US5: T036 → T037

---

## Parallel Opportunities

```bash
# Phase 2 — after T011 lands, four signature edits in four files:
Task: "Add studyLabel/configResolved params to EventScreen signature"    # T012
Task: "Add studyLabel/studyType/configResolved to EventScrub signature"  # T013
Task: "Add studyLabel/studyType/configResolved to EventReview signature" # T014
Task: "Add studyLabel to EventUpload signature"                          # T015

# Phase 3 (US1) — all four headings, four separate files:
Task: "EventUpload.jsx:247 heading"   # T016
Task: "EventScreen.jsx:53 heading"    # T017
Task: "EventScrub.jsx:73 heading"     # T018
Task: "EventReview.jsx:187 heading"   # T019

# Across stories — once Phase 2 is done, three developers can take:
Developer A: US1 (T016–T020)   frontend headings
Developer B: US2 (T021–T027)   frontend documents
Developer C: US3 (T028–T031)   backend email — no frontend dependency at all
```

Note that US1 and US2 both touch `EventScrub.jsx` and `EventReview.jsx`, in different regions of each file (heading versus document box). Two developers can work them concurrently but should expect to coordinate on those two files.

---

## Implementation Strategy

### MVP: User Story 1

US1 is the original request and the visible core of the feature. Complete Phases 1–3 and stop: all four headings name the configured study.

Be aware the MVP carries the full foundational cost — T003 through T015 — because a heading cannot name a study the page cannot see.

### Smallest shippable increment: User Story 3

If the goal is to land something verified quickly, US3 is the better first slice. It needs T003 plus T028–T031, is entirely backend, and is the only story with automated test coverage end to end. It is also the story fixing the defect that has already reached real users' inboxes.

### Incremental delivery

1. Phase 1 + T003 → US3 → reviewers on every study get correct emails
2. Rest of Phase 2 → US1 → all four headings correct (MVP)
3. US2 → no page offers another study's documents
4. US4 → unconfigured deployments fail safe
5. US5 → whole-surface consistency confirmed
6. Phase 8 → contract, PR record, follow-ups

### A caution carried from the plan

`get_study_type()` and `get_study_label()` differ **only** in how they treat an unset variable, and that difference is the entire point of T003. Calling `get_study_type()` when building a heading or an email would silently name MCI on an unconfigured deployment, and every test in this list would still pass except the unset assertions in T005, T007 and T033. Those three are load-bearing — do not relax them.

---

## Notes

- 44 tasks: 2 setup, 13 foundational, 5 US1, 7 US2, 4 US3, 4 US4, 2 US5, 7 polish
- Frontend tasks have no automated verification available in this repository; T035, T036 and T037 require a running deployment
- Commit after each task or logical group; the checkpoints are safe stopping points
- `openapi.json` (T008) must land in the same PR as T006 — a constitution gate, not a preference

---

## Verification status (recorded at implementation time, 2026-09-08)

**Done and verified automatically**

- Backend: **155 passed**, against a **134-passed** baseline recorded at T001.
  21 new tests; no pre-existing failures, so no regression is masked.
- Frontend: `npm run build` clean. `npm run lint` reports **31 problems
  (25 errors, 6 warnings)** — byte-identical to the baseline measured by
  stashing this feature's changes and re-running. Every remaining problem is
  pre-existing, in `EventUpload.jsx`, `Home.jsx` and `studies/vte/`. **This
  feature introduces none.**
  - Note: the T002 baseline first read *180* problems because `node_modules`
    was incomplete (115 packages, no `vite`, no `react`). `npm install` fixed
    two packages; the real baseline is 31 and both numbers were re-measured
    against the same toolchain before being compared.
- `headingSuffix()` exercised directly under Node for all six FR-006 cases —
  `'MCI'`, `'SCANS'`, `''`, whitespace-only, `null`, `undefined` — each
  yielding the bare identifier with no leading space where the label is empty.
- `resolveStudyDocuments()` exercised directly for `mci`, `scans`, `cva`
  (unrecognized), `''`, whitespace, `null`, `undefined`. Confirmed **no `mci`
  fallback**: `cva` yields 0 links where `resolveReviewGuidance('cva')` yields 2.
- FR-014 confirmed **by object identity**, not just equality: for `mci` and
  `scans`, `resolveReviewGuidance(s).instructions === resolveStudyDocuments(s).instructions`.
- Email subject and body composed under `mci`, `scans`, `'  Cva  '`, unset,
  and an empty `EMAIL_SUBJECT_PREFIX` — every result matches the
  `data-model.md` table, with no double space in any state.
- The T005/T007/T033 unset assertions were confirmed **load-bearing** by
  simulating the exact mistake the plan warns about (emailer resolving its
  label through `get_study_type()`): the subject becomes
  `... MCI Review Assignment ...` on an unconfigured deployment, which those
  assertions reject.

**T035, T036, T037 — NOT DONE. Blocked, not skipped.**

All three require a running deployment whose `STUDY_TYPE` can be changed and
the stack restarted. No deployment is reachable from the implementation
environment, and the repository has no frontend test framework, so the
rendered pages could not be walked. These remain **outstanding work for
whoever has deployment access**, and the constitution's Development Workflow
gate ("frontend study-specific components verified under at least one
`STUDY_TYPE` before merge") is **not yet satisfied**. Steps are in
`quickstart.md`.

What this leaves unverified is the *rendering* — that the composed strings
reach the page and that the boxes appear and disappear as expected. The label
*logic* the four headings and the email share is covered above.

**T043, T044** — issue text is drafted in `followups.md`; `gh` is not
installed in the implementation environment, so neither was filed.
