# UI contract: bulk-import feedback

**Status**: Phase 1 output for the 2026-08-17 spec amendment.
**Backend impact**: none. No route, request body, or response shape changes.
`openapi.json` does **not** need regeneration for this amendment; the
`imports-api.yaml` contract beside this file is unchanged and remains accurate.

This documents the contract between the *existing* responses of
`POST /api/events/bulk` and what the administrator is shown. It is a UI
contract because the defect being fixed is entirely on the consuming side: the
server was already reporting correctly.

## Inputs the client may receive

| Case | Status | `Content-Type` | Body |
|---|---|---|---|
| Some or all rows imported | 201 | `application/json` | `{data: {imported, errors?, import_id}}` |
| Nothing importable | 400 | `application/json` | `{error, data: {imported: 0, errors?, import_id}}` |
| Oversize | 413 | `application/json` | `{error, data: {import_id}}` |
| Not archivable / server fault | 500 | `application/json` | `{error}` |
| **Request answered by the wrong server** | 200 | `text/html` | The SPA shell (research D10) |
| **No response** | — | — | `fetch` rejects |

The last two rows are not part of the API. They are transport realities the
client must handle, and handling them wrongly is the defect this amendment
fixes.

## Required classification

The client MUST evaluate in this order and stop at the first match:

```text
1. fetch threw                          -> network
2. status === 413                       -> refused
3. Content-Type is not JSON             -> undetermined
4. res.json() throws                    -> undetermined
5. data.imported > 0                    -> imported | partial (errors present)
6. otherwise                            -> nothing
```

Ordering is normative:

- `413` is tested **before** the JSON check so the oversize path (009 FR-006),
  whose body *is* JSON, keeps its specific message.
- `Content-Type` is tested **before** parsing so a non-JSON body is classified
  on evidence rather than on a parse exception.
- `data.imported` is read only after the body is known to be JSON. Defaulting a
  missing `imported` to `0` and treating that as failure is precisely the
  observed bug and is prohibited.

## Required output per outcome

| Outcome | MUST show | MUST NOT show |
|---|---|---|
| `imported` | Count of events created | — |
| `partial` | Both counts, every skipped row on its own line, copy control, record link | The rows joined into one line |
| `nothing` | The server's `error` verbatim, any skipped rows, record link | — |
| `refused` | The server's `error` verbatim, the size limit, record link | Any claim that rows were or were not created |
| `undetermined` | That the outcome is unknown, that the import may have completed, and a link to the import history | The words "failed", "error", or any count |
| `network` | That the request did not complete, and a link to the import history | Any claim about what the server did |

`undetermined` and `network` asserting failure is a contract violation, not a
wording preference — it is the specific behavior that caused administrators to
re-upload files and create duplicate events.

## Lifecycle guarantees

- From submit until the outcome renders, a continuous activity indication is
  present, including elapsed time (FR-013).
- No second submission can be initiated while one is in flight (FR-014).
- The rendered result persists until the administrator dismisses it (FR-017).

## Consumers

One page: `frontend/src/pages/EventAddMany.jsx`, via the shared modules.

`frontend/src/studies/vte/EventAddMany.jsx` posts to the same endpoint and has
the same false-failure bug, but is **out of scope** — it is legacy the project
does not intend to use and is not linked from the shared tree (research D18).
This contract does not describe its behavior.
