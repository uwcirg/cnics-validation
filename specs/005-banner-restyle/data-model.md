# Phase 1 Data Model: Legacy-style Top Banner

**Feature**: 005-banner-restyle | **Date**: 2026-05-22

This feature persists nothing and changes no database schema. The "model" here
is the **runtime derivation** of the banner's display values from existing
configuration. It is documented so `/speckit.tasks` and reviewers share one
definition of the title rule.

## Entities

### Study Type (input — existing)

The identifier of the study a deployment serves.

| Attribute | Description |
|-----------|-------------|
| Value | A short study code, e.g. `cva`, `mci`, `scans`. Originates from the `STUDY_TYPE` environment variable, resolved by the backend `study_config` layer. |
| Source | `GET /api/config` → `data.study_type`. Already fetched by `App.jsx`. |
| Lifetime | Fixed per deployment; resolved once per page load. |
| Absent state | Before `/api/config` resolves (or if it fails), the frontend holds an empty value. |

This entity is **not introduced** by this feature — it already exists in config
and in the API response. The feature only begins *consuming* it for display.

### Study Title (derived — new)

The user-facing label shown in the banner, computed from Study Type.

| Attribute | Description |
|-----------|-------------|
| Value | A display string, e.g. `"CVA Project"`, `"MCI Project"`, `"Scans Project"`, or `null` when no title should be shown. |
| Derivation | `formatStudyTitle(studyType)` — see rule below. |
| Lifetime | Recomputed on each render; not stored. |

## Derivation rule — `formatStudyTitle(studyType)`

Pure function. No side effects. Input is the raw `study_type` string (possibly
empty/null); output is the display title or `null`.

```text
input  := trim(studyType)

if input is empty / null / undefined:
    return null                      # banner shows logo only, no title (FR-010)

if lowercase(input) == "scans":
    base := "Scans"                  # title case, not all-caps (FR-005)
else:
    base := uppercase(input)         # acronym studies + unknown fallback (FR-004, FR-011)

return base + " Project"             # always suffixed (FR-006)
```

### Worked examples

| `study_type` input | `formatStudyTitle` output | Requirement |
|--------------------|---------------------------|-------------|
| `"cva"`            | `"CVA Project"`           | FR-004, FR-006 |
| `"mci"`            | `"MCI Project"`           | FR-004, FR-006 |
| `"scans"`          | `"Scans Project"`         | FR-005, FR-006 |
| `"CVA"`            | `"CVA Project"`           | FR-004 (case-insensitive input) |
| `"  mci  "`        | `"MCI Project"`           | input trimmed |
| `"afib"` (unknown to spec) | `"AFIB Project"`  | FR-011 (uppercase fallback) |
| `""` / `null` / `undefined` | `null`           | FR-010 (no broken placeholder) |

## State / transitions

There is one observable transition per page load:

```text
[config unresolved]  study_type = ""     → title = null   → banner: logo only
        │  GET /api/config resolves
        ▼
[config resolved]    study_type = "<x>"  → title = "<X> Project" → banner: logo + title
```

No transition back. A failed `/api/config` leaves the banner in the first
state (logo only) — still valid and usable (FR-010).

## Relationships

```text
STUDY_TYPE (env)  ──resolved by──▶  /api/config.data.study_type
                                            │ consumed by App.jsx
                                            ▼
                                    BaseLayout (study_type prop)
                                            │ formatStudyTitle()
                                            ▼
                                    Study Title (rendered in banner)
```
</content>
