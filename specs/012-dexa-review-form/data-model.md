# Data Model: DEXA scan review form and on-site reviewer instructions

**Feature**: 012-dexa-review-form | **Date**: 2026-10-14 (rev. 2)

## Shared invariant: every study's `reviews` table

Each deployment's database has its **own** `reviews` table, defined by its
study (research D1). Shared code relies only on:

| Column | Meaning |
|---|---|
| `id` | primary key |
| `event_id` | the reviewed event (`events.id`) |
| `reviewer_id` | the reviewer (`users.id`) |

There is **one row per reviewer per event**. Every other column belongs to
the study.

## Study-specific tables per study (constitution v1.5.0, Principle IV)

| Study | Study-specific tables (in `init/studies/<study>.sql`) |
|---|---|
| `mci` | `reviews`, `event_derived_datas` (both moved verbatim from `02-schema.sql`) |
| `scans` | `reviews`, `review_regions` (new) |
| `vte`, `afib`, `cva`, `hf` | moved from `init/02-schema-<study>.sql`, still commented out, first line `-- DORMANT` → these studies fall back to `mci` |

## `mci`: `reviews` and `event_derived_datas` (unchanged)

The DDL moves verbatim from `init/02-schema.sql` to `init/studies/mci.sql`.
The `Reviews` and `EventDerivedDatas` models move verbatim from
`flask_backend/models.py` to `flask_backend/models/studies/mci.py`, with their
relationships removed (research D4.5). The unused `Events.reviews` and
`Events.derived_data` relationships are dropped. Columns, enums, engine, and
data are all unchanged (Principle III).

## `scans`: `reviews` + `review_regions` (new; `init/studies/scans.sql`)

```sql
CREATE TABLE IF NOT EXISTS `reviews` (
  `id`          int(11)  NOT NULL AUTO_INCREMENT,
  `event_id`    int(11)  NOT NULL,
  `reviewer_id` int(11)  NOT NULL,
  `created`     datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_reviewer` (`event_id`, `reviewer_id`),
  KEY `reviewer_id` (`reviewer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='DEXA scans: one row per completed review';

CREATE TABLE IF NOT EXISTS `review_regions` (
  `id`           int(11)      NOT NULL AUTO_INCREMENT,
  `review_id`    int(11)      NOT NULL,
  `position`     tinyint(3) unsigned NOT NULL COMMENT '1-based entry order within the review',
  `region`       varchar(40)  NOT NULL COMMENT 'dropdown label, e.g. "Lumbar spine"; validated in code',
  `region_other` varchar(100) DEFAULT NULL COMMENT 'free text; set only when region = "Other"',
  `laterality`   enum('Left','Right','Both','Unspecified') DEFAULT NULL COMMENT 'NULL = not provided',
  `t_score`      decimal(4,2) DEFAULT NULL COMMENT 'NULL = not provided',
  `z_score`      decimal(4,2) DEFAULT NULL COMMENT 'NULL = not provided',
  PRIMARY KEY (`id`),
  UNIQUE KEY `review_position` (`review_id`, `position`),
  CONSTRAINT `review_regions_review` FOREIGN KEY (`review_id`)
    REFERENCES `reviews` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='DEXA scans: one row per body-region entry';
```

- **Review date**: `events.review1_date` (set by the shared endpoint) is the
  date shown and exported. `reviews.created` keeps the exact time.
- **"Other"**: the stored value is `region = 'Other'`, with the reviewer's
  text in `region_other`.
- **No foreign keys to `events`/`users`**: those tables are MyISAM. The link
  between `review_regions` and `reviews` *is* a foreign key, because both
  are InnoDB.

### ORM (`flask_backend/models/studies/scans.py`)

```text
class ScansBase(DeclarativeBase)                      # own metadata (research D4.5)
class Reviews(ScansBase)        id, event_id, reviewer_id, created
                                regions = relationship("ReviewRegions", order_by=position,
                                                       cascade="all, delete-orphan")
class ReviewRegions(ScansBase)  id, review_id(FK), position, region, region_other,
                                laterality, t_score/z_score: Numeric(4,2, asdecimal=True)
REVIEW_FORM = "scans"
```

## Study module interface (`flask_backend/models/studies/<study>.py`)

| Member | `mci` | `scans` |
|---|---|---|
| `Reviews` | MI model (moved) | scans header model |
| `REVIEW_FORM` | `"mci"` | `"scans"` |
| `parse_review(data)` | existing MI body rules (moved from `app.py`): `mci` required, flags coerced, text trimmed | scans rules below |
| `add_review(session, event_id, reviewer_id, parsed)` | one `Reviews` row | one `Reviews` row + N `ReviewRegions` |
| `get_reviews(session, event_id)` | list of MI review dicts | list of `{reviewer_id, review_date, regions:[…]}` |
| `export_csv(session, writer)` | existing MI export (moved; byte-identical output; SQL in `_EXPORT_SQL`, executed by `_export_rows`) | one row per region (research D9) |
| `REVIEW_TABLES` (rev. 4) | `("reviews", "event_derived_datas")` | `("reviews", "review_regions")`. These are the tables `GET /api/tables/<name>` refuses to non-admins (research D16.5) |
| `check_schema(session)` | `None` | error string if `reviews` is still MI-shaped (has `mci`, or lacks `created`); the endpoints then return 503 naming the migration script |

Resolver: `flask_backend/models/studies/__init__.py::get_study_module()` reads
the study type at call time and falls back to `mci` when there is no live
module (research D4.2).

## Scans validation rules (enforced in both frontend and backend; FR-003 – FR-007, FR-012, FR-014)

| Field | Rule | Error when violated |
|---|---|---|
| `regions` | list of 1–20 entries | "At least one body region is required" / "Too many body regions (max 20)" |
| `region` | one of: `Spine`, `Lumbar spine`, `Thoracic spine`, `Cervical spine`, `Forearm/radius`, `Hip`, `Proximal femur`, `Femoral neck`, `Wrist`, `Other` | "Unknown body region" |
| `region_other` | when `region == 'Other'`: required, not blank after trimming, at most 100 characters. Otherwise ignored and stored as NULL | "Please specify the body region" |
| `laterality` | blank or absent → NULL. Otherwise one of `Left`, `Right`, `Both`, `Unspecified` | "Unknown laterality" |
| `t_score`, `z_score` | blank or absent → NULL. Otherwise a trimmed string matching `^[+-]?(\d{1,2}(\.\d{0,2})?\|\.\d{1,2})$` and −10 ≤ value ≤ 10 | "T score must be a number from -10 to 10, with at most 2 decimal places" (same wording for Z) |

- Twenty entries is a defensive cap. The spec requires at least 8 (FR-009).
- `position` = array index + 1. Regions may repeat (FR-010).
- Field-level errors come back as
  `{"error": "Invalid review", "fields": [{"index": i, "field": "t_score", "message": "…"}]}`.
- The list constants exist twice, once in `models/studies/scans.py` and once
  in `frontend/src/studies/scans/ScansReviewForm.jsx`. Each copy carries a
  "keep in sync with …" comment. The display label for `Other` is "Other:
  please specify".

## Lifecycle

No state is added, removed, or renamed (Principle V). `scans` still runs
`assigned → reviewer1_done → done` in one request.

New shared guard (research D5): if the submitter's slot already has a
`reviewN_date`, the request returns **409** and writes nothing.

## Frontend state (ScansReviewForm)

```text
entries: Array<{ key, region, regionOther, laterality, tScore, zScore }>   // strings; '' = not provided
errors:  { "<index>.<field>": message }
submitting: boolean;  submitted: boolean (locks the form)
```

- With 0 entries, the form shows Step 2 with a single dropdown and no Submit.
  Picking a region appends an entry.
- With 1 or more entries, each entry row shows: region (changeable), Other
  text, laterality, T, Z, and Remove. Below the rows: the Step 3 dropdown,
  which appends an entry, and Submit.
- Removing the last entry returns the form to the 0-entry state.

## Config: `GET /api/config` gains `review_form`

The value is `get_study_module().REVIEW_FORM` (`"mci"` | `"scans"`). The
frontend's `reviewForms.js` keys on it, so the backend and frontend always
agree on which form is shown (research D6).

## Instructions content

| Study | File | `reviewGuidance.js` `instructions` |
|---|---|---|
| `scans` | `app/webroot/files/instructions/scans.html` (new) | `items: []`, `links: [{label: 'View review instructions', href: '/files/instructions/scans.html', download: false}]` |
| `mci` | (none yet) | `items: []`, `links: []` (reviewer `.doc`/`.pdf` removed) |

## Rev. 4 (2026-10-16): no schema change

FR-025 – FR-032 add **no tables, columns, or migrations**. The derived values
they rely on:

- **Reviewer's slot** on an event: `N` where `events.reviewer{N}_id =
  caller`, with N in 1–3. It is computed in the shared endpoint and never
  stored.
- **Already reviewed by caller**: the caller's slot N is 1 or 2, and
  `events.review{N}_date IS NOT NULL`. This is the rule both the POST 409
  guard (FR-024) and `mine` (FR-029) use. Slot 3 (third review) is not
  submittable or reported through these endpoints in this feature.
- **Awaiting the caller's review** (FR-025): the event is in the
  review-eligible status (`sent`, or `assigned` when sending is disabled),
  and the caller holds slot 1 or 2 with that slot's `review{N}_date IS NULL`.
- **Review N content row** (FR-027): the `get_reviews` item whose `slot = N`,
  formatted by the study's frontend summary component. For `scans`, a
  region's parts that are `null` are omitted, never rendered as 0.
