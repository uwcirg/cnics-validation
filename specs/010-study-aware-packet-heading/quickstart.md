# Quickstart: Study-aware naming on the shared event pages and reviewer emails

**Feature**: `010-study-aware-packet-heading` | **Date**: 2026-09-08

What changes, where, and how to confirm it.

## The shape of the change

Nine files. One new config accessor, one new response field, two new frontend
helpers, one component moved, and eight literal strings replaced.

```text
flask_backend/
├── study_config.py        + get_study_label(); + study_label on WorkflowConfig
├── app.py                 + study_label in the /api/config body (~line 780)
└── emailer.py             ~ _format_subject() and _build_body() (2 strings)

frontend/src/
├── App.jsx                + studyLabel state; pass to 4 pages (3 currently get no props)
├── components/
│   ├── studyHeading.js    NEW — headingSuffix(label, eventId)
│   ├── GuidanceLinks.jsx  NEW — extracted verbatim from Home.jsx:96-112
│   └── reviewGuidance.js  + `scrubbing` box per study; + resolveStudyDocuments()
└── pages/
    ├── Home.jsx           ~ import GuidanceLinks instead of defining it
    ├── EventUpload.jsx    ~ heading (line 247)
    ├── EventScreen.jsx    ~ heading (line 53)
    ├── EventScrub.jsx     ~ heading (73) + doc links (67, 69)
    └── EventReview.jsx    ~ heading (187) + doc links (181, 183)

openapi.json               regenerated — required in the same PR
```

No schema change. No migration. `init/` untouched.

## Before and after

| Where | Today | After (`STUDY_TYPE=scans`) |
|---|---|---|
| Upload page | `Packet for MI 4821` | `Packet for SCANS 4821` |
| Screening page | `Screen charts for MI 4821` | `Screen charts for SCANS 4821` |
| Scrubbing page | `Upload scrubbed charts for MI 4821` | `Upload scrubbed charts for SCANS 4821` |
| Review page | `Review event: MI 4821` | `Review event: SCANS 4821` |
| Scrubbing box | links to `CNICS MI event scrubbing protocol` | **box not rendered** — scans has no documents |
| Review box | links to `CNICS MI reviewer instructions` | **box not rendered** |
| Email subject | `… MI Review Assignment – Event 4821` | `… SCANS Review Assignment – Event 4821` |
| Email body | `You have been assigned a Myocardial Infarction (MI) review.` | `You have been assigned a review in the SCANS study.` |

On the existing `mci` deployment the headings read `MCI` where they read `MI`,
the document links are unchanged, and the email loses its "Myocardial
Infarction" expansion. All three are accepted consequences recorded in the spec.

## Verifying the backend

```bash
cd /home/debadmin/cnics-validation
python -m pytest flask_backend/tests/test_study_config.py \
                 flask_backend/tests/test_config_endpoint.py \
                 flask_backend/tests/test_emailer_study_naming.py -v
```

Three behaviours to confirm:

```python
# 1. The label reports "unset" honestly, while study_type keeps its default.
monkeypatch.delenv("STUDY_TYPE", raising=False)
assert study_config.get_study_label() == ""
assert study_config.get_study_type() == "mci"      # unchanged

# 2. Whitespace and case are tolerated (FR-008).
monkeypatch.setenv("STUDY_TYPE", "  Scans  ")
assert study_config.get_study_label() == "SCANS"

# 3. The email names the study, with no article before the label (FR-022).
monkeypatch.setenv("STUDY_TYPE", "cva")
monkeypatch.setenv("EMAIL_TEST_MODE", "1")
result = emailer.send_assignment_emails_for_event_ids([event_id])
assert "CVA Review Assignment" in result["details"][0]["subject"]
assert "a review in the CVA study" in result["details"][0]["body_preview"]
assert "Myocardial" not in result["details"][0]["body_preview"]
```

`EMAIL_TEST_MODE=1` returns the composed subject and a body preview without
sending, so no SMTP server is needed.

Regenerate the contract before opening the PR:

```bash
python -m flask_backend.generate_openapi
```

## Verifying the frontend

**There is no frontend test framework in this repository** — `package.json`
defines `dev`, `build`, `lint`, and `preview`, and no test runner. The React
changes are verified by inspection and by the constitution's pre-merge check
against a running deployment, not by automated test.

What is verifiable locally:

```bash
cd frontend && npm run lint && npm run build
```

What needs a deployment, under at least one `STUDY_TYPE` per the Development
Workflow gate:

1. Set `STUDY_TYPE=scans` in `.env`, restart, and walk the four pages —
   each heading reads `SCANS`, and neither the scrubbing nor the review page
   renders its instructions box at all.
2. Set `STUDY_TYPE=mci`, restart, and confirm the four headings read `MCI` and
   both document boxes render with their existing links, working.
3. Unset `STUDY_TYPE`, restart, and confirm the headings read
   `Packet for 4821` — one space, no gap, no `undefined` — and that no
   document box appears.
4. Watch a page load on a slow connection and confirm no heading shows a study
   name that is then replaced by another (FR-009, gated on `configResolved`).

## The one thing most likely to go wrong

`get_study_type()` and `get_study_label()` differ **only** in how they treat an
unset variable, and that difference is the whole point of the feature. Calling
`get_study_type()` when building a heading or an email would silently name MCI
on an unconfigured deployment, and every test above would still pass except the
unset ones. The unset assertions are load-bearing — do not relax them.
