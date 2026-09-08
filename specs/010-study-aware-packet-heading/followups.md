# Follow-up issues from 010-study-aware-packet-heading

Two issues to file. Neither is a regression from this feature; both were found
while working on it and are recorded so they are revisited deliberately rather
than forgotten. `gh` is not installed in the working environment, so these are
drafted here rather than filed.

---

## Issue 1 (T043) — Should the home page still fall back to `mci` guidance for an unrecognized study?

**Labels**: `tech-debt`, `study-config`

Spec 010 introduced `resolveStudyDocuments()` in
`frontend/src/components/reviewGuidance.js`, which deliberately does **not**
fall back to `mci` content for an unrecognized or unset `STUDY_TYPE`: the
scrubbing and review pages now show no document links rather than offering
another study's protocol (spec 010, FR-017).

Its sibling `resolveReviewGuidance()` — used by the home page — still falls
back to the `mci` entry, which is what spec 007 FR-008 asked for.

So on a deployment whose `STUDY_TYPE` is unrecognized or unset, the home page
shows MI guidance prose while the workflow pages show no documents at all.

**This was a deliberate, bounded decision, not an oversight** (research R4):
reversing spec 007's FR-008 is a decision about the home page and was outside
010's scope, and the asymmetry is defensible — guidance prose is read, whereas
a protocol document is acted on, so a wrong fallback costs more for documents.
Both functions carry comments saying exactly this.

**The question to settle**: now that the workflow pages do not fall back,
should the home page stop too? Options:

1. Leave as-is; keep the divergence and the comments explaining it.
2. Make `resolveReviewGuidance()` stop falling back — the home page then shows
   empty guidance boxes on a misconfigured deployment.
3. Keep the fallback but make it visible (e.g. a "showing default guidance"
   note) so a misconfiguration is noticeable rather than silent.

Only affects misconfigured deployments; no correctly configured study sees any
difference today.

---

## Issue 2 (T044) — Three stale request paths on the home page

**Labels**: `bug`, `frontend`

`frontend/src/pages/Home.jsx` makes requests the backend does not serve.
`flask_backend/app.py` registers no route matching `/api/mci/…` at all.

| Line | Code | Problem |
|---|---|---|
| 104 | ``fetch(`${API_BASE}/api/mci/tables/events`)`` | No such route; the `.catch(() => {})` swallows the failure silently |
| 199 | `endpoint="/api/mci/events"` | No such route; the search-results table cannot populate |
| 116 | ``fetch(`${API_BASE}/api/events/status_summary?study=mci`)`` | The route exists but takes no `study` argument, and the hard-coded `mci` is a leftover study name in shared code |

Line numbers are as of the 010 branch after that feature's changes.

Worth noting that the line-104 failure is invisible: the `.catch(() => {})`
means a broken main table on the home page produces no error anywhere. The
`statusSummary` state it feeds is also flagged by ESLint as assigned but never
used, which suggests this path has been dead for some time.

Recorded as out of scope in the 010 spec — these are pre-existing and
unrelated to study-aware naming, but the `?study=mci` argument in particular
is the same class of hard-coded study name that feature removed elsewhere.
