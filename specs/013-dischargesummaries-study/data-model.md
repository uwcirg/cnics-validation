# Data Model: `dischargesummaries` Study Type

**Feature**: 013-dischargesummaries-study | **Date**: 2026-10-23

There are **no changes to shared tables.** `events`, `users`, `patients_view`
and `logs` are used as they are. The one new study-specific table is
`reviews`, in `init/studies/dischargesummaries.sql`. It is applied only when
`STUDY_TYPE=dischargesummaries` (`init/04-study-schema.sh`, no changes to the
dispatcher).

## Event lifecycle for this study

Shared `events.status` enum, unchanged. The states entered under the
`dischargesummaries` profile (scrub on, screen off, send off, one reviewer):

```text
created ──(site uploader or admin uploads)──> uploaded
   │                                             │  (same-site uploader may replace
   │                                             │   while still `uploaded`; D3)
   │                                             ▼
   │                         (admin stores scrubbed copy) scrubbed
   │                                             │
   │                         (admin assigns 1 reviewer) assigned      ← refused unless scrubbed (D2)
   │                                             │
   │                       (assigned reviewer submits review) → done  (via reviewer1_done; existing
   │                                                                   single-reviewer auto-complete)
   └──(site uploader or admin: no document)──> no_packet_available    (existing path)
```

These states are never entered here, but they stay in the schema
(Principle V): `screened`, `sent`, `reviewer2_done`, `third_review_needed`,
`third_review_assigned`, and `rejected`, which is reached only from
screening.

### Fields written per transition (existing columns)

| Transition | Fields set |
|------------|-----------|
| → uploaded | `uploader_id`, `upload_date`, `file_number`, `original_name` |
| → scrubbed | `scrubber_id`, `scrub_date` |
| → assigned | `reviewer1_id`, `assigner_id`, `assign_date` |
| → done | `review1_date` (+ a `reviews` row) |

## Study-specific table: `reviews`

### Phase A (this plan; D5)

| Column | Type | Notes |
|--------|------|-------|
| `id` | int(11) PK auto-increment | shared invariant |
| `event_id` | int(11) NOT NULL, indexed | shared invariant; one row per reviewer per event |
| `reviewer_id` | int(11) NOT NULL | shared invariant |
| `created` | datetime DEFAULT CURRENT_TIMESTAMP | matches `scans` |

InnoDB, utf8mb4, `CREATE TABLE IF NOT EXISTS` (idempotent, like `scans.sql`).
Unique key `(event_id, reviewer_id)`. There is no `event_derived_datas`,
because there is one reviewer (constitution v1.5.1 allows omitting it).

In Phase A no rows are written: `parse_review` refuses every submission.

### Phase B (blocked on FR-011)

The answer columns, or child tables if an answer repeats (as
`review_regions` does for `scans`), are defined once the review questions
are known. Each question becomes a column with a type and nullability that
match its allowed answers. Validation rules live in
`flask_backend/models/studies/dischargesummaries.py` and are mirrored in
the React form. Existing Phase A databases get
`init/migrations/013-dischargesummaries-reviews.sh`. Its precondition:
`reviews` is empty, which Phase A guarantees, and the script checks it
before altering anything.

## Configuration entities (not persisted)

| Setting | New? | Default | Used by |
|---------|------|---------|---------|
| `STUDY_TYPE=dischargesummaries` | new value | — | profile (D1), study module, schema dispatcher, guidance map |
| `STUDY_LABEL` | **new**, optional | unset → upper-cased `STUDY_TYPE` | page headings, assignment emails (D6) |
| `STUDY_TITLE` | existing | unset → derived | banner, tab title |
| `INTERNAL_SUBNET` | **new**, optional | `172.31.222.0/24` | `docker-compose.yaml` network (D8) |
| `INTERNAL_GATEWAY` | **new**, optional | `172.31.222.1` | `docker-compose.yaml` network (D8) |
| `CNICS_DATA_DB_HOST` | existing | `172.31.222.1` | prod: left at the scans gateway to share its tunnel (D9) |

## Access rules (derived, not stored)

| Action | admin | uploader, same site | uploader, other site | reviewer, assigned | reviewer, not assigned |
|--------|:-----:|:-------------------:|:--------------------:|:------------------:|:----------------------:|
| Create event (single/bulk) | ✓ | ✗ | ✗ | ✗ | ✗ |
| Upload / replace (status `created`/`uploaded`) | ✓ | ✓ | ✗ | ✗ | ✗ |
| Upload when status is later | ✓ | ✗ (409) | ✗ | ✗ | ✗ |
| Mark no document (status `created`) | ✓ | ✓ | ✗ | ✗ | ✗ |
| Scrub | ✓ | ✗ | ✗ | ✗ | ✗ |
| Assign | ✓ | ✗ | ✗ | ✗ | ✗ |
| Download scrubbed copy | ✓ | ✓ | ✗ | ✓ | ✗ |
| Download original upload | ✓ | ✓ | ✗ | ✗ | ✗ |
| View / submit review | — | ✗ | ✗ | ✓ | ✗ |
