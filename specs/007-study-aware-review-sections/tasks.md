---

description: "Task list for Study-aware home review sections"
---

# Tasks: Study-aware home review sections

**Input**: Design documents from `/specs/007-study-aware-review-sections/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/review-guidance.md, quickstart.md

**Tests**: No automated test tasks are generated. The spec did not request TDD and the repo has no frontend unit-test runner configured (the sibling precedent `frontend/src/components/studyTitle.js` ships without a test). Verification is `npm run build` + manual check under a `STUDY_TYPE`, per the constitution's testing-discipline rule. Verification tasks are included in each story.

**Organization**: Tasks are grouped by user story. This feature is frontend-only (`frontend/src/`); no backend, schema, dependency, or `openapi.json` change.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1, US2, US3)

## Path Conventions

Web application. All paths below are under the repository root `frontend/src/`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Establish a clean baseline and confirm the exact edit points before changing anything.

- [X] T001 Confirm baseline builds clean: run `cd frontend && npm run build` and record success. Note the exact code to be replaced — the two hard-coded `.infobox` blocks in `frontend/src/pages/Home.jsx` (lines ~225–262) and the `<Home auth={auth} />` render in `frontend/src/App.jsx` (line ~129) — so later diffs are scoped.

**Checkpoint**: Baseline green; edit points identified.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Shared rendering machinery used by BOTH P1 stories — the per-study content module, the `App → Home` wiring, and the generic renderer that keeps headers constant, omits empty link areas (FR-004), and prevents the content flash (FR-010).

**⚠️ CRITICAL**: No user story content (mci/scans entries) renders until this phase is complete. The renderer must degrade gracefully (render nothing) when an entry is absent, so each story remains independently shippable on its own deployment.

- [X] T002 [P] Create `frontend/src/components/reviewGuidance.js` (sibling of `studyTitle.js`) as a pure, side-effect-free module: define the `GuidanceContent { packets, instructions }`, `Box { items, linkLabel?, links }`, and `Link { label, href, download }` shapes (per data-model.md); declare a `STUDY_GUIDANCE` map (entries added by the story phases); and export `resolveReviewGuidance(studyType)` that trims+lowercases the input, returns the matching entry, else falls back to the `mci` entry, else returns a safe empty `{ packets: { items: [], links: [] }, instructions: { items: [], links: [] } }`. Add JSDoc mirroring `studyTitle.js`, citing FR-002/FR-008.
- [X] T003 [P] Wire `frontend/src/App.jsx`: pass the already-fetched study type into the home route (`<Home auth={auth} studyType={studyType} />`, line ~129) and add a config-resolved signal so the bottom boxes are not painted with fallback content before `/api/config` resolves (FR-010) — e.g. track a `configResolved` boolean set in `fetchConfig` and pass it to `Home`.
- [X] T004 [US-shared] Replace the two hard-coded `.infobox` blocks in `frontend/src/pages/Home.jsx` (lines ~225–262) with a generic renderer driven by `resolveReviewGuidance(studyType)`: keep the two `<h3>` texts literal and constant ("Review packets should contain:" / "Review Instructions:", FR-001); render each box's `items` (packets as an ordered list, matching current style); render the link area only when `links` is non-empty — `linkLabel` plus each `<a>` with its `href`, `download` for `.doc`-style and `target="_blank"` for `.pdf`-style — and render no label/links otherwise (FR-004); gate the whole pair on the `configResolved` prop from T003 (FR-010). Depends on T002, T003.

**Checkpoint**: Boxes render generically; with no entries yet they render empty/hidden, headers constant, no flash. Build is clean.

---

## Phase 3: User Story 1 - Scans reviewer sees DEXA-appropriate packet guidance (Priority: P1) 🎯 MVP

**Goal**: A `scans` deployment shows DEXA packet items and "No additional instructions", with no file links — not MI content.

**Independent Test**: Configure `STUDY_TYPE=scans`, open the home page; the "Review packets should contain:" box lists the two DEXA lines, the "Review Instructions:" box reads "No additional instructions", and neither box shows any `.doc`/`.pdf` link.

### Implementation for User Story 1

- [X] T005 [US1] Add the `scans` entry to `STUDY_GUIDANCE` in `frontend/src/components/reviewGuidance.js`: `packets.items = ["DEXA scan reports", "Please redact their PHI (names, birthdate, etc)"]` with `links: []`; `instructions.items = ["No additional instructions"]` with `links: []` (FR-006, FR-007). Depends on T002.
- [X] T006 [US1] Verify scans: `cd frontend && npm run build` (clean), then with the app running under `STUDY_TYPE=scans` confirm both DEXA items, the "No additional instructions" line, no link area in either box, and unchanged headers — mapping to acceptance scenarios US1 #1–#4 and the `scans` row of contracts/review-guidance.md.

**Checkpoint**: User Story 1 fully functional and independently testable on a scans deployment.

---

## Phase 4: User Story 2 - MCI reviewer keeps existing guidance (Priority: P1)

**Goal**: An `mci` (or unset/default) deployment shows the exact existing eight-item checklist and the existing `.doc`/`.pdf` instruction links — no visible change. This entry is also the fallback for unrecognized studies (FR-008).

**Independent Test**: Configure `STUDY_TYPE=mci` (or leave unset), open the home page; both boxes match the pre-feature page item-for-item and link-for-link.

### Implementation for User Story 2

- [X] T007 [US2] Add the `mci` entry to `STUDY_GUIDANCE` in `frontend/src/components/reviewGuidance.js` and confirm it is the fallback default: `packets.items` = the existing eight lines (physician's notes closest to potential Event date; outpatient cardiology consultations; in-patient cardiology notes or consults; baseline ECG; first 2 ECGs after admission or in-hospital event; related procedure and diagnostic test results; related laboratory evidence; please redact the personal identifiers including name, birthday, and hospital number); `packets.linkLabel = "Full instructions:"` with links `.doc`→`/files/CNICS MI Review packet assembly instructions.doc` (download) and `.pdf`→`/files/CNICS MI Review packet assembly instructions.pdf` (new tab); `instructions.items = []`, `instructions.linkLabel = "View as:"` with links `.doc`→`/files/CNICS MI reviewer instructions.doc` (download) and `.pdf`→`/files/CNICS MI reviewer instructions.pdf` (new tab). Match `Home.jsx:225-262` verbatim (FR-005). Depends on T002. Same file as T005 — sequence after T005, do not run in parallel with it.
- [X] T008 [US2] Verify mci unchanged: `cd frontend && npm run build` (clean), then under `STUDY_TYPE=mci` and unset, visually compare both boxes against the pre-feature page (items, link text, link targets, download-vs-new-tab behavior) — mapping to acceptance scenarios US2 #1–#3, SC-002, and the `mci`/fallback rows of contracts/review-guidance.md.

**Checkpoint**: User Stories 1 and 2 both work independently; mci fallback confirmed for unrecognized studies.

---

## Phase 5: User Story 3 - Operator adds guidance for a new study (Priority: P2)

**Goal**: Adding a new study's content requires editing only that study's entry — no header, layout, or other-study edits.

**Independent Test**: Add a temporary third-study entry, build, configure that study type, confirm the boxes render the new content under unchanged headers; remove the temporary entry.

### Implementation for User Story 3

- [X] T009 [US3] Verify extensibility against `quickstart.md`: temporarily add a throwaway study entry (e.g. `vte`) to `STUDY_GUIDANCE` in `frontend/src/components/reviewGuidance.js` with sample items and a sample `/files/...` link, `npm run build`, confirm under that `STUDY_TYPE` the boxes render the new content with the two headers unchanged and that no edits to `Home.jsx`/`App.jsx`/other entries were needed (FR-009, SC-004), then remove the throwaway entry. Leave only the `mci` and `scans` entries committed.

**Checkpoint**: Extensibility proven; only `mci` and `scans` entries remain in the tree.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Final cross-study validation.

- [X] T010 Run the full `quickstart.md` verification end-to-end: confirm under `STUDY_TYPE=mci`, unset, and `STUDY_TYPE=scans` that headers are byte-identical, no MI content flashes before scans content on a scans deployment (FR-010), empty-link boxes show no orphaned label (FR-004), and there are no browser console errors.
- [X] T011 [P] Final `cd frontend && npm run build` clean, and confirm no backend artifacts changed (no `openapi.json` regeneration, no schema/route change) — consistent with the plan's frontend-only scope.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately.
- **Foundational (Phase 2)**: Depends on Setup — BLOCKS both user stories. T004 depends on T002 and T003.
- **User Story 1 (Phase 3)**: Depends on Foundational. T005 depends on T002.
- **User Story 2 (Phase 4)**: Depends on Foundational. T007 depends on T002 and edits the same file as T005 — sequence T005 → T007.
- **User Story 3 (Phase 5)**: Depends on Foundational and on at least one entry existing.
- **Polish (Phase 6)**: Depends on US1 and US2 complete.

### User Story Dependencies

- **US1 (scans)** and **US2 (mci)** are both P1 and independently testable per deployment (a scans deployment needs only the scans entry; an mci deployment needs only the mci entry). They share `reviewGuidance.js`, so their two add-entry tasks (T005, T007) are sequenced, not parallel.
- **US3 (extensibility)** is P2 and verifies a property already enabled by the foundational module design.

### Parallel Opportunities

- T002 (new module) and T003 (App.jsx wiring) edit different files → can run in parallel.
- T005 and T007 edit the **same** file (`reviewGuidance.js`) → must be sequential.
- Verification tasks (T006, T008) can run after their respective entry tasks.

---

## Parallel Example: Foundational

```bash
# T002 and T003 touch different files and can proceed together:
Task: "Create frontend/src/components/reviewGuidance.js (pure module + resolveReviewGuidance)"
Task: "Wire frontend/src/App.jsx to pass studyType + configResolved into <Home>"
# Then T004 (Home.jsx renderer) once T002 and T003 land.
```

---

## Implementation Strategy

### MVP (the actual change)

The feature's reason to exist is correct `scans` content (US1), but `mci` (US2) is the fallback and the no-regression baseline. Ship both P1 stories together:

1. Phase 1 Setup → Phase 2 Foundational (module + wiring + generic renderer).
2. Phase 4 task T007 (mci entry) — establishes the fallback and preserves current behavior.
3. Phase 3 task T005 (scans entry) — delivers the new value.
4. Verify both (T006, T008) → deploy.

### Incremental Delivery

- Foundational → renderer in place (boxes empty/hidden, no regression risk).
- Add `mci` entry → mci/default deployments unchanged.
- Add `scans` entry → scans deployments correct.
- US3 verification → confirms future studies need only an entry.

---

## Notes

- [P] = different files, no dependencies. T005/T007 are intentionally NOT [P] (same file).
- No automated tests generated (no runner configured; mirrors `studyTitle.js`). Verification is build + manual `STUDY_TYPE` checks per the constitution.
- Keep the two `<h3>` header strings literal in `Home.jsx` — they are constant, not data-driven (FR-001).
- Commit after each entry + its verification so each study's behavior is independently reviewable.
