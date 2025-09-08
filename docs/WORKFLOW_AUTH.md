## Auth-Level Workflow Guide

This guide summarizes what each role can see and do across the CNICS Validation app, aligned with current backend authorization and frontend routing.

### Roles
- Admin
- Reviewer
- Third Reviewer
- Uploader

### Capability matrix (high level)

| Capability | Admin | Reviewer | Third Reviewer | Uploader |
|---|---:|---:|---:|---:|
| View events lists/pages | Yes | Yes | Yes | Yes |
| Upload new packets | Yes | – | – | Yes |
| Re-upload existing packets | Yes | Yes | – | Yes |
| Upload scrubbed charts | Yes | Yes | – | Yes |
| Screen packets | Yes | Yes | – | – |
| Assign reviewers (1/2/3) | Yes | Yes (UI: assign third) | – | – |
| Send to reviewers | Yes | – | – | – |
| Review events (record decisions) | Yes | Yes | Yes | – |
| Manage users | Yes | – | – | – |
| Export events | Yes | – | – | – |
| Status summary | Yes | – | – | – |

Notes:
- “Re-upload existing packets” shows events at the uploader’s site with status `uploaded` (mutually exclusive from “review events”).
- “Review events” for a reviewer shows events assigned to that reviewer and still pending per slot rules.

### Endpoints by role (backend)

- Admin only
  - `POST /api/events` (create event)
  - `GET /api/events/status_summary`
  - `POST /api/users`
  - `POST /api/events/assign_many`
  - `POST /api/events/send_many`
  - `GET /api/events/export` (CSV)
  - `POST /api/events/bulk`

- Reviewer or Admin
  - `GET /api/events/for_review` (events ready for review – status `sent`)
  - `POST /api/events/<id>/screen` (screen decisions)
  - `GET /api/reviewer/awaiting` (events awaiting this reviewer)

- Reviewer, Uploader, or Admin
  - `GET /api/events/need_packets` (events needing packet upload; filtered to uploader site in UI flows)
  - `GET /api/events/need_reupload` (events eligible for re-upload; filtered to uploader site)
  - `POST /api/events/<id>/upload_scrubbed` (upload scrubbed charts)
  - `GET /api/events/by_status/<status>` (phase lists)
  - `GET /api/events` (generic listing with patient site info)
  - `GET /api/events/<id>` (event details)

- Any authenticated user
  - `GET /api/tables/<name>` (raw table access; used in admin/utility screens)

Public
- `GET /files/<path>` (serves static files; PDFs generated on demand from docs when missing)

### UI pages by role (frontend)

- Admin
  - Admin tools: Users (add/edit/delete), Events (add, bulk import, assign/send many, export)
  - All multi-role pages (upload, re-upload, scrub, screen, review, viewAll, edit, download, solicitations, criteria)

- Reviewer
  - Review Events `/events/review` (driven by `/api/reviewer/awaiting`)
  - Screen `/events/screen`
  - Assign third `/events/assignThird` (UI action)
  - Multi-role pages: scrub, edit, viewAll, download, solicitations, criteria

- Third Reviewer
  - Review Events `/events/review` (shows 3rd-review assignments via `/api/reviewer/awaiting`)
  - Multi-role pages: scrub, edit, viewAll, download, solicitations, criteria

- Uploader
  - Upload New Packets `/events/upload` (uses `/api/events/need_packets`)
  - Re-upload Existing Packets `/events/reupload` (uses `/api/events/need_reupload`)
  - Upload scrubbed charts (multi-role)
  - Multi-role pages: scrub, edit, viewAll, download, solicitations, criteria

### Data selection rules (key lists)

- Re-upload list (homepage “To Be Re-uploaded”)
  - Events at the uploader’s site with status `uploaded`, ordered by `Event.id` ascending.

- Events to review (homepage “Events to Review” / reviewer’s awaiting list)
  - Reviewer 1: `reviewer1_id = <id>` AND status in (`sent`, `reviewer2_done`) AND `review1_date IS NULL`
  - Reviewer 2: `reviewer2_id = <id>` AND status in (`sent`, `reviewer1_done`) AND `review2_date IS NULL`
  - Reviewer 3: `reviewer3_id = <id>` AND status = `third_review_assigned` AND `review3_date IS NULL`
  - Ordered by `Event.id` ascending.

These rules ensure no event appears in both “Re-upload” and “Review” simultaneously.


