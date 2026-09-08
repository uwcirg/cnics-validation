# Phase 1 Data Model: No-Packet Submission

**Feature**: `011-no-packet-submit` | **Date**: 2026-09-08

**No schema change. No migration.** Every field written by this feature
already exists in `init/02-schema.sql` and in `flask_backend/models.py`, is
already populated in legacy production data, and is already read by the export
path. This document describes how the feature writes them.

---

## Entity: `events` (existing table)

### Fields written by this feature

| Column | Type | Written to | Applies when |
|--------|------|-----------|--------------|
| `status` | enum | `'no_packet_available'` | always |
| `no_packet_reason` | enum(4) | the selected reason | always |
| `two_attempts_flag` | tinyint(1) | `1` or `0` | reason = `Outside hospital` |
| `prior_event_date` | varchar(7) | `MM-YYYY`, or NULL | reason = prior-event |
| `prior_event_onsite_flag` | tinyint(1) | `1` or `0` | reason = prior-event |
| `other_cause` | varchar(100) | the free text | reason = `Other` |
| `markNoPacket_date` | date | today | always |
| `marker_id` | int | acting user's id | always |

All eight are existing columns (`init/02-schema.sql:100-110`, `:91`).

### Fields explicitly *not* written

`upload_date`, `uploader_id`, `file_number`, and `original_name` stay NULL —
no packet exists. Confirmed against legacy rows, where `upload_date` is NULL
on every `no_packet_available` event while `markNoPacket_date` carries the
resolution date.

---

## Field applicability matrix

The core integrity rule (FR-006): for a given reason, fields outside its
column are written **NULL**, including when the coordinator answered them
before changing their selection.

| Reason | `two_attempts_flag` | `prior_event_date` | `prior_event_onsite_flag` | `other_cause` |
|--------|--------------------|--------------------|---------------------------|---------------|
| `Outside hospital` | **required** (1/0) | NULL | NULL | NULL |
| `Ascertainment diagnosis error` | NULL | NULL | NULL | NULL |
| `Ascertainment diagnosis referred to a prior event` | NULL | **conditional** | **required** (1/0) | NULL |
| `Other` | NULL | NULL | NULL | **required**, 1-100 chars |

Matches legacy production rows exactly (research D5).

---

## `prior_event_date` encoding

`varchar(7)`, zero-padded `MM-YYYY`. Three distinct states, all meaningful:

| Coordinator's input | Stored | Meaning |
|---------------------|--------|---------|
| "Is approximate month/year known?" = **No** | `NULL` | no approximate date at all |
| Known; month `11`, year `2011` | `'11-2011'` | fully known |
| Known; month blank, year `2008` | `'00-2008'` | year only |
| Known; month `01`, year blank | `'01-0000'` | month only |

`00` and `0000` are the legacy sentinels for "this half is unknown" and are
observed in production (`'00-2008'`, `'01-0000'`). They are **not**
interchangeable with a NULL `prior_event_date`, which means the coordinator
denied knowing the date at all. Preserve the distinction.

---

## Validation rules

Applied on the backend authoritatively, mirrored on the frontend for immediate
feedback (research D6).

| # | Rule | Failure | Spec |
|---|------|---------|------|
| V1 | `reason` is one of the four enum values | 400 | FR-002, FR-010 |
| V2 | `Outside hospital` ⇒ `two_attempts_flag` present and in {0,1} | 400 | FR-011 |
| V3 | `Other` ⇒ `other_cause` non-empty after trim | 400 | FR-012 |
| V4 | `Other` ⇒ `len(other_cause) <= 100` | 400, states the limit | FR-015 |
| V5 | prior-event ⇒ `prior_event_onsite_flag` present and in {0,1} | 400 | FR-013 |
| V6 | prior-event + date known ⇒ at least one of month/year supplied | 400 | FR-013 |
| V7 | supplied month is an integer 1-12 | 400, names the field | FR-014 |
| V8 | supplied year is a four-digit year in a plausible range | 400, names the field | FR-014 |
| V9 | event exists | 404 | FR-022 |
| V10 | actor is admin, or an uploader at the event's patient site | 403 | FR-021 |
| V11 | `status == 'created'` | 409, names current status | FR-023 |

Every failure leaves the row untouched (FR-016) — validation precedes any
mutation, and the handler rolls back on exception, as `/screen` does.

---

## State transition

```text
created ──mark_no_packet──▶ no_packet_available
```

- `no_packet_available` is an **existing** terminal state in the lifecycle
  enum (`init/02-schema.sql:100`, `models.py:49`). No state is added, renamed,
  or removed — Constitution Principle V is untouched.
- `created` is the only permitted source (research D4). It is precisely the
  membership rule of the needs-packet queue, so the transition removes the
  event from that queue as a side effect of the write.
- Terminal for this feature: no transition out of `no_packet_available` is
  added (spec Out of Scope).

### Effect on existing lists — no query changes needed

| Surface | Rule | Effect |
|---------|------|--------|
| Needs-packet queue | `status = 'created'` (`table_service.py:200`) | event drops out (FR-024) |
| "No packet available" list | `by_status/no_packet_available` (`EventViewAll.jsx:394`, allow-list `app.py:607`) | event appears (FR-025) |
| Export | already selects all 8 fields + marker join (`table_service.py:602-608,690`) | populated (FR-027) |

---

## Read-path change (the one gap)

`table_service.get_event_details` (`table_service.py:884-926`) currently
selects `markNoPacket_date` but none of the other no-packet fields, and does
not join the marker user. FR-026 requires adding to that query:

```text
e.no_packet_reason, e.two_attempts_flag, e.prior_event_date,
e.prior_event_onsite_flag, e.other_cause
LEFT JOIN users mk ON mk.id = e.marker_id   →  mk.username AS marker_username
```

The export query is the reference implementation of exactly this join
(`table_service.py:690`).

---

## Entities *not* introduced

- **No no-packet record table.** The declaration is a set of values on the
  event row, as in legacy. A side table would fragment data the export and
  detail page already read from `events`.
- **No new enum member, no new column, no new index.**
- **No import/archive sidecar.** That pattern (feature 009) exists to preserve
  irreplaceable submitted bytes; a four-field declaration has no such artifact.
