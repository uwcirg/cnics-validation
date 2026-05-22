# UI Contract: Top Banner

**Feature**: 005-banner-restyle | **Date**: 2026-05-22

This feature exposes no new HTTP API. Its contract is the **rendering contract**
of the shared banner — what `BaseLayout` must render given its inputs. It is the
acceptance surface for `/speckit.tasks` and for manual verification.

## Consumed API (existing — unchanged)

`GET /api/config` (already called by `App.jsx`):

```json
{ "data": { "study_type": "scans", "workflow": { "...": "..." } } }
```

This feature adds **consumption of `data.study_type`**. The request, the route,
the response shape, and `data.workflow` are unchanged. No backend work.

## Banner inputs

`BaseLayout` receives:

| Prop | Type | Meaning |
|------|------|---------|
| `study_type` | string | The deployment's study code, or `""` until `/api/config` resolves. |
| `auth` | object | Existing auth object; `auth.username` drives the logged-in strip. |

## Rendering contract

The banner MUST render, on every page (it lives in the shared layout):

1. **Logo** — the CNICS logo image (`/cnics_logo.png`), displayed prominently
   at the top-left as the visual anchor of the header, with descriptive `alt`
   text. (FR-001, FR-012)
2. **Study title** — `formatStudyTitle(study_type)`, rendered immediately
   beside the logo as the most prominent text in the banner. When the helper
   returns `null` (study type not yet resolved / empty), **no title element**
   is rendered — never the literal `"undefined"` or `"undefined Project"`.
   (FR-002, FR-003, FR-006, FR-010, FR-011)
3. **Logged-in strip** — when `auth.username` is set, the user identity and a
   "Log Out" control, rendered as a subtle strip (not an unstyled box).
   When `auth.username` is absent, the strip renders with no identity or
   Log Out control. (FR-007, FR-009, unauthenticated edge case)

Styling MUST be visually faithful to the legacy reference screenshot
`CVA.Screenshot 2026-05-21 103208.jpg`: logo + title as a left-aligned header
block, logged-in line as a subtle strip beneath. (FR-007, SC-003)

## Behavioral assertions (verification checklist)

| # | Given | Then |
|---|-------|------|
| C1 | `study_type = "cva"` | Banner title reads exactly `CVA Project`. |
| C2 | `study_type = "mci"` | Banner title reads exactly `MCI Project`. |
| C3 | `study_type = "scans"` | Banner title reads exactly `Scans Project` (not `SCANS Project`). |
| C4 | `study_type = ""` (config pending/failed) | Banner renders the logo and is usable; **no** title text, no `undefined`. |
| C5 | `study_type` is an unrecognized code, e.g. `"afib"` | Banner title reads `AFIB Project`. |
| C6 | Any page is opened | Logo and title appear identically — same banner on every route. |
| C7 | Logo image fails to load | Title and Log Out control remain visible and usable; logo degrades to alt text. |
| C8 | No user is logged in | Logo and title still show; logged-in strip shows no identity / Log Out. |
| C9 | Logged-in user on any page | User identity + Log Out control reachable within the banner. |
| C10 | New banner vs. legacy reference screenshot, side by side | Judged a faithful match in logo prominence, title placement, header styling. |

## Non-goals (explicit)

- No change to `MenuBar` or to page-content styling beyond the banner region.
- No change to the wording of the logged-in line (only placement/styling).
- No new HTTP endpoint, request field, response field, or schema column.
</content>
