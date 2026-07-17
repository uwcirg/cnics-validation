# Quickstart: Verifying the FHIR "not currently used" Change

**Feature**: 001-mark-fhir-unused
**Audience**: the engineer implementing this feature, and reviewers

This quickstart is the verification recipe for the change. It replaces a
traditional test suite because no runtime behavior is changing — the goal
is to prove that nothing broke *and* that the documentation is now
internally consistent.

## Prerequisites

- Local Docker Compose working copy of this branch.
- A `.env` file derived from the post-change `.env.example`.

## 1. Consistency check (documentation)

From the repo root:

```bash
grep -rni 'fhir' --include='*.md' --include='*.env*' --include='*.yml' \
  --include='*.sh' .
```

Expected outcome:

- Every hit falls into one of two categories:
  1. A retained reference that includes the canonical phrase
     **"not currently used"** (variants in `README.md`, `.env.example`,
     `default.env`, `.env.backup`, `docs/technical-architecture.md`).
  2. An intentional absence — `docker-entrypoint.sh` and
     `.github/workflows/tests.yml` should produce **zero** hits because
     their FHIR blocks were removed.
- No hit exists anywhere that implies FHIR is a live runtime dependency.

If a new file is introduced during development that mentions FHIR, it
MUST use the canonical wording from `research.md`.

## 2. Local startup check (unset `FHIR_SERVER`)

```bash
# Start from a fresh environment file with no FHIR entry
cp .env.example .env
grep -i fhir .env   # should print nothing (entry is commented out)

docker-compose build
docker-compose up -d
docker-compose ps
```

Expected outcome:

- All services become healthy.
- `docker-compose logs backend` shows no warnings or errors related to
  FHIR, environment variables, or missing configuration.
- The frontend loads in a browser at the configured origin.
- Log in as a test user and navigate to the events list. Confirm the
  primary validation workflow (list events, open an event, view a
  review) works identically to a pre-change run.

## 3. Local startup check (explicit `FHIR_SERVER` set)

This verifies the backward-compatibility promise from the contract.

```bash
# Temporarily add FHIR_SERVER to .env
echo 'FHIR_SERVER=http://legacy-value.example.com' >> .env

docker-compose down
docker-compose up -d
docker-compose logs backend | grep -i fhir   # should print nothing
```

Expected outcome:

- Stack starts successfully.
- No backend logs mention FHIR.
- The application behaves identically to the unset case.

Remove the temporary line after the check:

```bash
sed -i '/^FHIR_SERVER=/d' .env
```

## 4. CI check (GitHub Actions)

Push this branch to the remote. Open the Actions tab and watch the
`Python Tests` workflow run.

Expected outcome:

- Workflow passes without the `FHIR_SERVER` env entry in
  `.github/workflows/tests.yml`.
- `pytest flask_backend/tests/ -v` completes with the same pass count as
  on `main` (no new tests added, no existing tests regressed).

If any test fails with a stack trace referencing FHIR, the research
assumption (no runtime consumer) was wrong — stop and revisit
`research.md` before merging.

## 5. Architecture diagram spot-check

Render `docs/technical-architecture.md` (GitHub, VS Code preview, or
any Markdown viewer with Mermaid support).

Expected outcome:

- The "Detailed System Architecture" diagram still contains a FHIR node
  under "External Systems," but:
  - Its label reads `reserved — not currently used`.
  - No arrows connect any study backend (`MCI_BE`, `VTE_BE`, `CVA_BE`)
    to the FHIR node.
- A short paragraph below the diagram explains the FHIR node is
  reserved and that no runtime component currently calls it.

## Rollback

The change is a handful of documentation and config edits. If anything
goes wrong, revert the branch with `git revert`. No database migrations,
feature flags, or staged rollouts are involved.
