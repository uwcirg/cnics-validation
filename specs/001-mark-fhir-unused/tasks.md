---

description: "Task list for feature 001-mark-fhir-unused"
---

# Tasks: Mark FHIR Server References as Not Currently Used

**Input**: Design documents from `/specs/001-mark-fhir-unused/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: The feature specification does not request automated tests, and
this is a documentation/configuration-only change with zero runtime code
impact. No test tasks are included. Verification is performed manually
using `quickstart.md`.

**Organization**: Tasks are grouped by user story so each story can be
landed and verified independently. Because this feature edits seven
existing files with no new code, the Setup and Foundational phases are
minimal.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1, US2, US3)
- Exact file paths are included in every implementation task

## Path Conventions

This feature has no new source paths. All edits target existing files at
their current locations in the repository root, `docs/`, `.github/`, and
the repo-level dotenv files.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Establish the canonical wording the rest of the feature will
reuse. This phase creates nothing new — it simply confirms the phrasing
from `research.md` is the single source of truth for all later tasks.

- [X] T001 Confirm the canonical wording from `specs/001-mark-fhir-unused/research.md` section "Wording standard (for FR-008 consistency)" is the phrase every later task must use verbatim: "not currently used — retained for backward compatibility; no runtime component reads this value." (short form: "reserved — not currently used"). No file edits in this task; this is the shared reference that all `[US1]`, `[US2]`, and `[US3]` tasks cite.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Verify, before any edits land, that the "no runtime consumer"
assumption this entire feature rests on is still true. If this phase
finds runtime code that reads `FHIR_SERVER`, STOP and revise the plan —
the research assumption has been invalidated.

**⚠️ CRITICAL**: No user story work may begin until this phase completes
without surprises.

- [X] T002 Re-run a case-insensitive repository search for `fhir` scoped to `flask_backend/` and `frontend/` (e.g., `grep -rni fhir flask_backend/ frontend/`) and confirm zero matches. **Result**: 0 matches in both directories. Assumption holds.
- [X] T003 Re-run a case-insensitive repository search for `env.json` (e.g., `grep -rn env.json .`) and confirm the only match is `docker-entrypoint.sh`. **Result**: only matches outside the `specs/` directory are `docker-entrypoint.sh`. Assumption holds.

**Checkpoint**: Assumptions verified. User story phases may begin in
parallel.

---

## Phase 3: User Story 1 - New Contributor Reading Configuration (Priority: P1) 🎯 MVP

**Goal**: Make it unambiguous to a new contributor — reading only
environment templates and the README — that `FHIR_SERVER` is not
currently used and is not required to run the stack.

**Independent Test**: A reviewer opens `.env.example`, `default.env`,
`.env.backup`, and the README's environment-variables section in a fresh
clone of this branch and can correctly classify `FHIR_SERVER` as "not
currently used" without reading any source code. Additionally, a local
`docker-compose up` with no `FHIR_SERVER` line in `.env` starts cleanly
and serves the primary workflow.

### Implementation for User Story 1

- [X] T004 [P] [US1] Edit `.env.example` (lines 9–10): comment out the `FHIR_SERVER=http://example-fhir-server.com` line and replace the existing `# URL to FHIR server used by the application` comment with the canonical phrase from T001. Do not delete the entry — it must remain visible as a commented reference so readers searching for "FHIR" land on the explanation.
- [X] T005 [P] [US1] Edit `default.env` (lines 25–26): comment out `FHIR_SERVER=http://example-fhir-server.com` and replace the preceding `# URL to FHIR server used by the application` comment with the canonical phrase from T001. Preserve the surrounding section ordering.
- [X] T006 [P] [US1] Edit `.env.backup` in two places — the already-commented historical block at lines 13–14 and the currently-active block at lines 51–52. In both places, annotate with the canonical phrase from T001 (the active-block entry must also be commented out so grep-based searches return the explanation). Do not delete either block; `.env.backup` is a historical snapshot and edits must preserve its structure.
- [X] T007 [P] [US1] Edit `README.md` line 50: replace the `FHIR_SERVER` bullet with a single bullet whose description begins "**not currently used**" and then continues with "retained for backward compatibility with deployments that still set it; no runtime component reads this value." Keep the bullet in the existing environment-variables list so readers searching for "FHIR" in the README find the explanation.
- [ ] T008 [US1] **Deferred to human operator** — requires Docker Compose. Local verification: from the repo root, run `cp .env.example .env` (after T004 lands), confirm `grep -i fhir .env` prints nothing, then run `docker-compose build && docker-compose up -d`. Wait for services to become healthy, open the frontend in a browser, log in, and walk the primary validation flow (list events → open an event → view a review). Confirm `docker-compose logs backend` contains zero mentions of FHIR and zero config-related errors or warnings. This task implements the Story 1 independent test and also satisfies SC-003 from `spec.md`.

**Checkpoint**: Story 1 done. The MVP slice is complete — a new contributor
can now correctly classify `FHIR_SERVER` as not currently used from
config/README alone, and the stack runs without it. Safe to stop here and
ship if time-boxed.

---

## Phase 4: User Story 2 - Architecture Doc Reader (Priority: P2)

**Goal**: Remove the phantom-dependency misread from
`docs/technical-architecture.md` so architects, auditors, and reviewers
no longer conclude the system talks to a live FHIR server.

**Independent Test**: A reader opens `docs/technical-architecture.md`,
views the "Detailed System Architecture" Mermaid diagram, and correctly
answers "no, this system does not currently talk to a FHIR server — the
node is reserved and not wired up." Verifiable by rendering the diagram
in any Mermaid preview and confirming no arrows connect `MCI_BE`, `VTE_BE`,
or `CVA_BE` to the FHIR node, and that the node label includes "reserved
— not currently used."

### Implementation for User Story 2

- [X] T009 [US2] Edit `docs/technical-architecture.md`: in the "Detailed System Architecture" Mermaid graph (lines 5–79), change the FHIR node declaration on line 39 from `FHIR[FHIR Server]` to `FHIR[FHIR Server<br/>reserved — not currently used]` and delete the three edges `MCI_BE --> FHIR`, `VTE_BE --> FHIR`, `CVA_BE --> FHIR` on lines 68–70. Leave the node in the `subgraph "External Systems"` block so revival context is preserved. Do not touch any other diagrams in the file; only Section 1 mentions FHIR.
- [X] T010 [US2] In `docs/technical-architecture.md`, immediately below the closing ``` ``` ``` of the Section 1 Mermaid block, insert a one-paragraph note stating: the FHIR node is retained as a reserved placeholder, no runtime component in any study backend currently calls a FHIR server, and any future FHIR integration will be tracked as its own feature. Use the canonical wording from T001 as the key phrase.
- [ ] T011 [US2] **Deferred to human operator** — requires a Mermaid-capable Markdown renderer. Render verification: open `docs/technical-architecture.md` in a Markdown viewer that supports Mermaid (VS Code preview or GitHub file view) and visually confirm that (a) the diagram still displays the FHIR node under "External Systems," (b) no arrows connect any study backend to the FHIR node, and (c) the new paragraph below the diagram is readable and uses the canonical phrase. Paste a screenshot or a copy of the rendered output into the PR description.

**Checkpoint**: Story 2 done. Stories 1 and 2 are both independently
verified; architecture docs no longer mislead stakeholders.

---

## Phase 5: User Story 3 - CI/Deployment Operator (Priority: P3)

**Goal**: Remove the stale `FHIR_SERVER` entries from CI and the container
entrypoint so operators maintaining those files do not treat the
variable as a working dependency.

**Independent Test**: An operator inspects `.github/workflows/tests.yml`
and `docker-entrypoint.sh` and finds zero references to `FHIR_SERVER` in
either file. A CI run on this branch passes `pytest flask_backend/tests/`
without the variable being injected, and the container entrypoint
starts cleanly on local Docker Compose.

### Implementation for User Story 3

- [X] T012 [P] [US3] Edit `.github/workflows/tests.yml`: delete line 25 (`FHIR_SERVER: http://test-server.com`) from the `env:` block under the `Run tests` step. Do not leave a YAML comment in its place; the whole point is that the env block now accurately reflects what the tests need. Confirm the remaining `LOG_LEVEL`, `LOG_FORMAT`, and `FRONTEND_ORIGIN` entries are untouched and the YAML indentation is preserved. **Verified**: `python3 -c "import yaml; yaml.safe_load(open('.github/workflows/tests.yml'))"` passes.
- [X] T013 [P] [US3] Edit `docker-entrypoint.sh`: delete lines 4–7 (the `# Write environment variables used by the app` comment and the `if [ -n "$FHIR_SERVER" ]; then ... fi` block that writes `/var/www/html/env.json`). The resulting file must still `set -e` at the top and end with `exec "$@"`. Verify with `sh -n docker-entrypoint.sh` that the script still parses. **Verified**: `sh -n docker-entrypoint.sh` passes.
- [ ] T014 [US3] **Deferred to human operator** — requires pushing the branch to the remote. CI verification: push the branch to the remote and confirm the `Python Tests` workflow run on this branch passes without the `FHIR_SERVER` env entry. If any test fails with a traceback mentioning FHIR, the Phase 2 research assumption was wrong — revert T012/T013 and revisit `research.md` before proceeding.
- [ ] T015 [US3] **Deferred to human operator** — requires Docker. Entrypoint verification: rebuild the affected container (`docker-compose build`) and restart it (`docker-compose up -d`). Confirm the container starts cleanly, then `docker-compose exec` into it and verify `/var/www/html/env.json` is absent (because nothing writes it anymore) and that the application's primary workflow still functions. This satisfies SC-005 (zero runtime behavior change) for the entrypoint edit specifically.

**Checkpoint**: All three stories are independently verified. The feature
is ready for review.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Final consistency sweep and PR hygiene. These tasks touch
no new files; they verify the whole feature hangs together and prepare
the PR for review.

- [X] T016 Run the full consistency sweep from `specs/001-mark-fhir-unused/quickstart.md` section 1: `grep -rni 'fhir' --include='*.md' --include='*.env*' --include='*.yml' --include='*.sh' .` from the repo root. Every hit must fall into one of two categories: (a) an annotated reference using the canonical phrase from T001, or (b) an intentional absence (zero hits in `docker-entrypoint.sh` and `.github/workflows/tests.yml`). **Result**: all remaining hits in `.env.example`, `default.env`, `.env.backup`, `README.md`, and `docs/technical-architecture.md` carry the canonical "not currently used" phrasing; `docker-entrypoint.sh` and `.github/workflows/tests.yml` have zero FHIR hits.
- [ ] T017 **Deferred to human operator** — requires Docker. Run the explicit-`FHIR_SERVER` backward-compatibility check from `quickstart.md` section 3: add `FHIR_SERVER=http://legacy-value.example.com` to a local `.env`, restart the stack, and confirm the application behaves identically and backend logs contain zero FHIR mentions. This directly verifies Constitution Principle III (backwards compatibility) and the contract matrix in `specs/001-mark-fhir-unused/contracts/fhir-env-var.md`.
- [ ] T018 **Deferred to human operator** — to be completed when the PR is opened. Update the PR description to state explicitly: (a) which studies the change affects ("all shared config — applies to every study deployment"), (b) that the change introduces zero runtime behavior changes, (c) that downstream deployments setting `FHIR_SERVER` in their real `.env` files remain unaffected, and (d) a link to `specs/001-mark-fhir-unused/spec.md` and `plan.md` for reviewers. This satisfies the "PRs MUST state which studies it affects" rule from the constitution's Development Workflow section.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies. T001 is a one-off confirmation.
- **Foundational (Phase 2)**: Depends on Setup. T002 and T003 MUST pass
  (zero unexpected matches) before any user story work begins. If
  either fails, the whole feature halts.
- **User Story 1 (Phase 3)**: Depends on Phase 2. Independent of US2 and
  US3 — can be merged alone as the MVP.
- **User Story 2 (Phase 4)**: Depends on Phase 2. Independent of US1 and
  US3. Touches only `docs/technical-architecture.md`.
- **User Story 3 (Phase 5)**: Depends on Phase 2. Independent of US1 and
  US2. Touches `.github/workflows/tests.yml` and `docker-entrypoint.sh`.
- **Polish (Phase 6)**: Depends on whichever user stories are being
  shipped in this PR. T016–T018 should be the last things done before
  opening the PR.

### User Story Dependencies

- **US1 (P1)**: Independent. No cross-story dependencies.
- **US2 (P2)**: Independent. No cross-story dependencies.
- **US3 (P3)**: Independent. No cross-story dependencies.

All three stories can be implemented in parallel by different engineers
once Phase 2 completes. They touch disjoint files.

### Within Each User Story

- US1: T004, T005, T006, T007 all edit different files and are fully
  parallel. T008 is the verification task and must run after all four
  edits land in the working tree.
- US2: T009 and T010 both edit `docs/technical-architecture.md` and are
  therefore sequential (same file). T011 is verification and runs after
  T010.
- US3: T012 and T013 edit different files and are parallel. T014
  (CI verification) depends on T012 being pushed. T015 (entrypoint
  verification) depends on T013.

### Parallel Opportunities

- Phase 2: T002 and T003 can run in parallel (both read-only searches).
- Phase 3 (US1): T004, T005, T006, T007 run in parallel (four different
  files).
- Phase 5 (US3): T012 and T013 run in parallel (two different files).
- Across phases: Once Phase 2 completes, all three user story phases
  can run concurrently.

---

## Parallel Example: User Story 1

```text
# After Phase 2 completes, launch all four Story 1 edits together:
Task: "Edit .env.example lines 9–10 to comment out FHIR_SERVER and annotate"
Task: "Edit default.env lines 25–26 to comment out FHIR_SERVER and annotate"
Task: "Edit .env.backup lines 13–14 and 51–52 to annotate both FHIR blocks"
Task: "Edit README.md line 50 to rewrite the FHIR_SERVER bullet"

# Then sequentially run the verification:
Task: "Run docker-compose smoke test with .env lacking FHIR_SERVER"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1 (T001) — confirm canonical wording.
2. Complete Phase 2 (T002, T003) — verify research assumptions.
3. Complete Phase 3 (T004–T008) — the four config/README edits plus
   local smoke test.
4. **STOP and VALIDATE**: Story 1 is independently shippable. This
   alone resolves the highest-friction onboarding trap.
5. Run T016–T018 and open a PR scoped to US1 only.

### Incremental Delivery

1. Setup + Foundational → research assumptions confirmed.
2. Add US1 → open PR → merge (MVP).
3. Add US2 → open follow-up PR → merge.
4. Add US3 → open follow-up PR → merge.

Each PR is independently reviewable and ships a complete, testable
increment.

### Single-Shot Delivery (Recommended for this feature)

Because the total task count is small (18 tasks) and the file set is
disjoint, a single PR covering all three stories is also reasonable.
Do this if an engineer is picking up the whole feature at once:

1. Phase 1 → Phase 2 → all three user story phases in parallel → Phase 6.
2. Open one PR that lists the three stories in its description.

The constitution's "one bundled PR for a small, coherent refactor" is
consistent with this approach.

---

## Notes

- Every edit task names its exact file and line range so an implementer
  can apply the change without re-reading `research.md`.
- The canonical phrase from T001 ("not currently used — retained for
  backward compatibility; no runtime component reads this value.") is
  the authoritative wording. Deviations MUST be justified in the PR.
- This feature has no automated test tasks because the spec does not
  request tests and there is zero runtime behavior to test. The existing
  `pytest flask_backend/tests/` suite acts as the regression net.
- If Phase 2 finds that `FHIR_SERVER` *is* actually consumed somewhere,
  stop the feature immediately and revisit both `research.md` and
  `plan.md` — the entire design rests on the "no runtime consumer"
  claim.
