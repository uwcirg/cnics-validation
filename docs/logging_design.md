# Logging Design (Draft)

## Goals
- Capture operational errors and key user actions.
- Forward logs to ELK with minimal friction; consider dedicated log server later.

## Events to log
- Auth: login resolved user, missing user 403s
- API errors: 4xx validation, 5xx exceptions (with request IDs)
- File operations: uploads, downloads (event_id, user_id)
- Data mutations: create/update/bulk operations (counts, ids)

## Transport
- Interim: structured JSON to stdout, harvested by container runtime → ELK
- Option: dedicated log server (to be discussed with Ivan)

## Structure
```
{
  "ts": "2025-01-01T12:34:56Z",
  "level": "INFO|WARN|ERROR",
  "event": "upload_scrubbed",
  "req_id": "...",
  "user": {"id": 123, "username": "alice"},
  "ctx": {"event_id": 42}
}
```

## Alerting
- Route ERROR logs for backend to LastAlert email for on-call.
- Rate-limit to avoid storms; include deduplication key.


