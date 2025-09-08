# Sorting, Filtering, Pagination API Contract (Draft)

This document defines the backend contract for server-driven sorting, filtering, and pagination used by the tables in the frontend.

## Query parameters

- `limit` (int, optional): page size. Default TBD.
- `offset` (int, optional): zero-based offset. Default 0.
- `q` (string, optional): free-text search across selected fields.
- `site` (string, optional): site code filter.
- `sort_by` (string, optional): canonical column key. Examples: `event_date`, `add_date`, `upload_date`, `scrub_date`, `site`, `id`.
- `sort_dir` (string, optional): `asc` or `desc`. Default `asc`.

## Response shape

```
{
  "data": [ { /* row */ }, ... ],
  "total": 1234
}
```

## Endpoints adopting contract

- `GET /api/events`
- `GET /api/events/by_status/<status>`

## Notes

- Backends must validate `sort_by` against whitelist to avoid SQL injection.
- `total` must reflect the count after applied filters.
- Default sorting should be stable and documented per view.





