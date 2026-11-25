## Auth-Level Workflow Guide

This page lists what each role can see and do. It reflects the backend's
`@requires_roles` / `@requires_any_role` decorators in `flask_backend/app.py`
and the `ProtectedRoute` guards in `frontend/src/App.jsx`. For the lifecycle
these actions drive, see [WORKFLOW.md](WORKFLOW.md).

### Roles

A user may hold more than one role. The roles are stored as flags on `users`:

- **Admin** — coordinating center; runs every step and sees every site.
- **Uploader** — site coordinator; supplies packets for events at **their own site**.
- **Reviewer** — adjudicates events **assigned to them**.
- **Third Reviewer** — reserved for the tiebreaker review, which is not
  implemented (see [WORKFLOW.md](WORKFLOW.md#third-reviewer-not-implemented)).

### Authorization matrix

"Site" means the event's patient is at the user's own site. "Assigned" means
the user holds one of the event's reviewer slots. Rows marked *(flag)* exist
only when that workflow control is on (see
[WORKFLOW.md](WORKFLOW.md#workflow-controls)).

| Action                                         | Admin | Uploader | Reviewer | Third Reviewer |
|------------------------------------------------|:-----:|:--------:|:--------:|:--------------:|
| Add events (single, CSV bulk), view CSV imports | Yes  | –        | –        | –              |
| Edit an event's patient / date                 | Yes   | –        | –        | –              |
| Upload a new packet                            | Yes   | Site     | –        | –              |
| Mark "no packet available"                     | Yes   | Site     | –        | –              |
| Re-upload (replace) an existing packet         | Yes   | Site     | –        | –              |
| Upload a scrubbed copy *(scrubbing)*           | Yes   | –        | –        | –              |
| Screen packets *(screening)*                   | Yes   | –        | –        | –              |
| Assign reviewers                               | Yes   | –        | –        | –              |
| Send to reviewers *(sending)*                  | Yes   | –        | –        | –              |
| Submit / edit own review (slot 1 or 2)         | Yes¹  | –        | Assigned | –²             |
| Read other reviewers' answers                  | Yes   | –        | –        | –              |
| Download a packet                              | Yes   | Site     | Assigned³ | Assigned³     |
| Add criteria and solicitations⁴                | Yes   | Yes      | Yes      | –              |
| Manage users, status summary, export           | Yes   | –        | –        | –              |

1. An admin can submit or edit a review only in a slot they are assigned to.
2. A third reviewer can see slot-3 work in their queue, but no endpoint
   accepts a slot-3 review.
3. Reviewers get the scrubbed copy. They get the original only when scrubbing
   is off, because then there is no scrubbed copy.
4. The `/criteria/delete` and `/solicitations/delete` pages are placeholders;
   there is no delete endpoint.

The packet-replacement window is spec 014's. See
[WORKFLOW.md](WORKFLOW.md#replacing-a-packet-spec-014) and
[specs/014-packet-replace-review-edit](../specs/014-packet-replace-review-edit/spec.md).
Spec 012 made scrubbing, screening and the view-all page admin-only (FR-033);
before that, reviewers could do them too.

### Endpoints by role (backend)

- **Public**
  - `GET /files/<path>` (static files and instructions)

- **Any signed-in user**
  - `GET /api/auth/me`, `GET /api/sites`, `GET /api/events`
  - `GET /api/events/download/<id>` — access is checked per event; see [Packet downloads](#packet-downloads)

- **Admin only**
  - `POST /api/events`, `POST /api/events/bulk`, `GET /api/events/imports[/<id>[/file]]`
  - `PUT /api/events/<id>`
  - `POST /api/events/<id>/upload_scrubbed`, `POST /api/events/<id>/screen`
  - `POST /api/events/assign_many`, `POST /api/events/send_many`
  - `GET /api/events/status_summary`, `GET /api/events/export`
  - `POST /api/users`

- **Uploader or Admin**
  - `POST /api/events/<id>/upload_raw` (new and replacement packets; the site and replacement window are checked)
  - `POST /api/events/<id>/mark_no_packet` (only while the event is `created`)

- **Reviewer or Admin**
  - `GET /api/events/for_review` (non-admins see only events awaiting their own review)
  - `GET|POST|PUT /api/events/<id>/review`

- **Reviewer, Third Reviewer, or Admin**
  - `GET /api/reviewer/awaiting`

- **Reviewer, Uploader, or Admin**
  - `GET /api/events/need_packets`, `GET /api/events/need_reupload` (non-admins see their own site only)
  - `GET /api/events/by_status/<status>`
  - `POST /api/criteria`, `POST /api/solicitations`

- **Any role (admin, uploader, reviewer, third reviewer)**
  - `GET /api/tables/<name>`, `GET /api/config`
  - `GET /api/events/<id>` — users who are only reviewers get just the events they are assigned to

### Packet downloads

`GET /api/events/download/<id>` serves the scrubbed copy when one exists, and
otherwise the original. Admins can always download. Uploaders can download
packets for their own site. Assigned reviewers get the scrubbed copy, or the
original when scrubbing is off. Everyone else gets 403. These rules come from
spec 013 (`_may_download_event_packet`).

### UI pages by role (frontend)

The menu and the Home page queues follow the same rules. Pages marked
*(flag)* are not registered when that control is off.

- **Admin:** Admin tools, Users (view/add/edit/delete), Events add / addMany /
  imports / assignMany / export / viewAll, sendMany *(sending)*, screen
  *(screening)*, scrub *(scrubbing)*, and every page below.
- **Uploader:** Upload New Packets `/events/upload`, Re-upload Existing Packets
  `/events/reupload`, download, criteria, solicitations. An uploader can open
  `/events/edit`, but saving the patient or date calls `PUT /api/events/<id>`,
  which only an admin may use.
- **Reviewer:** Review Events `/events/review`, download, criteria,
  solicitations. `/events/assignThird` *(two reviewers)* is reachable, but
  `assign_many` is admin-only, so a reviewer who isn't an admin can't assign
  from it.
- **Third Reviewer:** sees only Home. Their queue is empty until
  third-review escalation is built.

The `/vte/*` routes are legacy; don't extend them.

### Data selection rules (key lists)

- **Need packets** (`/events/upload`): status `created`, at the user's site
  (admins see all sites), ordered by ID.
- **Re-upload** (`/events/reupload`): events whose packet may still be
  replaced. That is status `uploaded`, `scrubbed`, `screened`, `assigned` or
  `rejected`, not yet sent, and with no review submitted. Shown for the user's
  site (admins see all sites), ordered by ID. Before spec 014 this list was
  status `uploaded` only.
- **Awaiting this reviewer** (`/events/review`, Home "Events to Review"):
  - Slot 1: `reviewer1_id = me`, status `sent` or `reviewer2_done`, `review1_date IS NULL`
  - Slot 2: `reviewer2_id = me`, status `sent` or `reviewer1_done`, `review2_date IS NULL`
  - Slot 3: `reviewer3_id = me`, status `third_review_assigned`, `review3_date IS NULL` (never reached today)
  - With sending off, `assigned` also counts for slots 1 and 2.
  - Ordered by ID.

An event in the re-upload list has not been sent and has no reviews, so it
can't also be awaiting review. The one exception is a deployment with sending
off: there, an `assigned` event with no reviews yet is in both lists.
