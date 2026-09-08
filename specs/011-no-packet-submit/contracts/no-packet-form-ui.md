# Contract: No-Packet Form UI (`/events/upload?event_id=<id>`)

**Feature**: `011-no-packet-submit`
**File**: `frontend/src/pages/EventUpload.jsx` (the "If no packet is
available:" section, lines 322-441)

---

## Unchanged by this feature

The four reasons, the follow-up questions each raises, all question wording,
the show/hide logic (`showTwoAttempts`, `showPriorEvent`, `showOtherCause`),
and the rule that Submit appears only once a reason is selected. The section
heading and the study-aware page heading (spec 010) are untouched.

This feature makes the existing form work. It does not redesign it.

---

## Change 1 — bind the four unbound inputs

Currently only `noPacketReason` and `priorEventDateKnown` are stateful. These
four are uncontrolled with no `value`/`onChange`, so their answers are
unreadable:

| Input | Line | New state |
|-------|------|-----------|
| `twoAttemptsFlag` radios | 353, 358 | `twoAttempts` (`''` \| `'1'` \| `'0'`) |
| prior-event month / year | 404 | `priorEventMonth`, `priorEventYear` (strings) |
| `priorEventOnsite` radios | 415, 420 | `priorEventOnsite` (`''` \| `'1'` \| `'0'`) |
| `otherCause` text | 429 | `otherCause` (string) |

Follow the existing `priorEventDateKnown` pattern (lines 369-386):
`checked={x === '1'} onChange={(e) => setX(e.target.value)}`.

---

## Change 2 — attach the submit handler

`<form>` at line 325 gains `onSubmit={handleNoPacketSubmit}`. The handler:

1. `e.preventDefault()` — the current default submission is the whole bug.
2. Guards on `eventId` and on an in-flight submission (FR-019).
3. Validates client-side; on failure sets an error and returns without a
   request (FR-011 to FR-015).
4. `POST`s to `/api/events/{eventId}/mark_no_packet` with
   `credentials: 'include'` and a JSON body carrying **only the fields
   applicable to the selected reason** (FR-006).
5. On success sets confirmation state; on failure surfaces the server's
   `error` message verbatim.

Mirrors `handleUploadSubmit` (lines 190-229), including its error-extraction
shape (`res.ok` → parse `j.error` → fall back to a generic message).

---

## Change 3 — feedback the form has none of today

New state `noPacketStatus` (`idle | submitting | success | error`) and
`noPacketError`, parallel to the existing `uploadStatus` / `uploadError`.

| State | Requirement | Presentation |
|-------|-------------|--------------|
| `submitting` | FR-019 | Submit disabled, label indicates progress |
| `success` | FR-017, FR-020 | Green confirmation naming the event; reason select and follow-ups disabled so the resolved event cannot be re-submitted |
| `error` | FR-018 | Red message with the specific reason |

Reuse the inline colour convention already on this page (`color: 'green'` at
line 315 for upload success).

**Reason change clears prior answers** (FR-006): changing the reason select
resets `twoAttempts`, `otherCause`, `priorEventDateKnown`,
`priorEventMonth`, `priorEventYear`, and `priorEventOnsite`, and clears any
error. This makes the "answered, then switched" edge case impossible to
submit rather than merely filtered server-side.

---

## Client-side validation

Same predicates as the backend's V1-V8 (data-model.md), with messages naming
the field:

| Reason | Blocks submission when |
|--------|------------------------|
| Outside hospital | no attempts answer selected |
| Other | `other_cause` empty/whitespace, or over 100 chars |
| prior-event | no on-site answer; or date known with both month and year blank; or month outside 1-12; or year not four digits |

Client validation is convenience only — the backend re-validates everything
(research D6).

---

## Change 4 — event detail display (FR-026)

**`frontend/src/pages/EventEdit.jsx`**, beside the existing
`markNoPacket_date` row (lines 209-211): when `details.no_packet_reason` is
present, render the reason, the applicable follow-up answers (flags as
Yes/No, `prior_event_date` as stored `MM-YYYY`), and `marker_username`.

Requires the backend read-path change in data-model.md — those fields are not
currently returned by `GET /api/events/{id}`.

---

## Out of scope

- `frontend/src/studies/vte/EventUpload.jsx` — a byte-identical inert form
  (its `<form>` at line 265 also has no `onSubmit`). Deliberately not fixed;
  see research D9. Recorded as a known gap in `quickstart.md`.
- Any change to the packet-upload form above this section.
- Undo of a submitted declaration.
