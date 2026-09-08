# Phase 1 Data Model: Study-aware naming on the shared event pages and reviewer emails

**Feature**: `010-study-aware-packet-heading` | **Date**: 2026-09-08

**No database schema change.** No table, column, index, or migration is added,
altered, or removed. Every entity below is runtime configuration or derived
presentation state. `init/` is untouched.

---

## Study identity

The deployment's `STUDY_TYPE` environment variable. Already exists; read here,
not changed.

| Property | Value |
|---|---|
| Source | `STUDY_TYPE` environment variable, via `flask_backend/study_config.py` |
| Type | String, free-form; `mci`, `vte`, `cva`, `hf`, `afib`, `scans` in use |
| Default | `"mci"`, applied by `get_study_type()` — see the distinction below |
| Scope | One value per deployment; not a property of any record |

**Two accessors, deliberately different**, and the distinction is the crux of
this feature:

| Accessor | Unset `STUDY_TYPE` yields | Used by |
|---|---|---|
| `get_study_type()` *(existing, unchanged)* | `"mci"` | Workflow profile resolution, guidance content, everything that exists today |
| `get_study_label()` *(new)* | `""` | The four page headings and the assignment email |

`get_study_label()` is the only place the raw, undefaulted variable is read, and
it lives in the shared configuration layer as the constitution requires.

## Study label

Derived presentation value. Not stored, not persisted, not cached.

| Property | Value |
|---|---|
| Derivation | `STUDY_TYPE`, trimmed, upper-cased |
| Type | String; empty string means "no study word to show" |
| Examples | `mci` → `MCI`; ` Scans ` → `SCANS`; `cva` → `CVA`; unset → `""` |
| Validation | None. Any non-empty value is displayable — a study never served before needs no entry anywhere (FR-003) |
| Consumers | Four page headings (frontend), assignment email subject and body (backend) |

**Transport**: computed once on the server, added to `WorkflowConfig` as
`study_label`, and published on `GET /api/config` as `data.study_label`. The
frontend never re-derives it. See `contracts/config-api.md`.

**Invariants**

- Empty **only** when `STUDY_TYPE` is unset or blank. A non-empty identity
  always produces a non-empty label (FR-005).
- Never falls back to another study's value (FR-007, FR-017).
- Identical for every consumer in a deployment — four headings and the email
  cannot disagree, because there is one derivation (FR-010, FR-025).

## Study document set

Per-study content held in `frontend/src/components/reviewGuidance.js`. The
existing `STUDY_GUIDANCE` map gains one box per entry; no new file, no new
source of truth.

**Current shape** (spec 007):

```text
Link  = { label: string, href: string, download: boolean }
Box   = { items: string[], linkLabel?: string, links: Link[] }
Guide = { packets: Box, instructions: Box }
```

**New shape** — one added key, existing keys untouched:

```text
Guide = { packets: Box, instructions: Box, scrubbing: Box }
```

| Study | `packets` | `instructions` | `scrubbing` *(new)* |
|---|---|---|---|
| `mci` | unchanged | unchanged — now also feeds the review page | `CNICS MI event scrubbing protocol` `.doc` + `.pdf`, moved from `EventScrub.jsx:67,69` |
| `scans` | unchanged (`links: []`) | unchanged (`links: []`) | `links: []` — scans has no documents |
| absent / unrecognized | n/a | n/a | resolves to `{ links: [] }` |

**Why documents are per-study content and the label is not**: document names do
not track the identity. The files are named `CNICS MI …` while the identity is
`mci`, and most studies have no documents at all. A mechanical rule can express
neither fact. Recorded in spec Assumptions and research R4.

**Two resolvers over one map**

| Resolver | Unknown/unset study | Used by |
|---|---|---|
| `resolveReviewGuidance()` *(existing, unchanged)* | falls back to `mci` content (spec 007 FR-008) | Home page guidance boxes |
| `resolveStudyDocuments()` *(new)* | `{ scrubbing: {links: []}, instructions: {links: []} }` — no fallback | Scrubbing and review page document links |

For any configured, recognized study both resolvers read the same entry, so the
home page and the workflow pages agree (FR-014). They differ only on a
misconfigured deployment. This is a known bounded divergence, recorded in
research R4 and flagged for follow-up.

## Assignment email

Composed per reviewer assignment; nothing persisted.

| Property | Value |
|---|---|
| Built by | `_format_subject()` and `_build_body()` in `flask_backend/emailer.py` |
| Slots served | First, second (`emailer.py:222-223`) and third reviewer (`:353-354`) — two functions cover all three |
| Study naming | Study label, in the subject and in the body's opening sentence |
| Unchanged | `EMAIL_SUBJECT_PREFIX`, event identifier, download/review/index links, signature, attachment handling, delivery, logging, test mode |

| State | Subject | Body opening |
|---|---|---|
| `STUDY_TYPE=mci` | `CNICS / NA-ACCORD MCI Review Assignment – Event 4821` | `You have been assigned a review in the MCI study.` |
| `STUDY_TYPE=scans` | `CNICS / NA-ACCORD SCANS Review Assignment – Event 4821` | `You have been assigned a review in the SCANS study.` |
| unset | `CNICS / NA-ACCORD Review Assignment – Event 4821` | `You have been assigned a review.` |

No indefinite article precedes the label in any state (FR-022), and no state
leaves a double space or a dangling separator (FR-006 in spirit; FR-024).

## Event

Unchanged. Referenced only for its identifier, which continues to appear in
every heading and every email exactly as it does today. **An event carries no
study identity** — every event in a deployment belongs to that deployment's
study, so nothing here is ever derived from a record (FR-002).
