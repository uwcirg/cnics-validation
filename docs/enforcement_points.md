# Enforcement Points (Draft)

Define protected pages and actions and the roles required.

## Roles
- admin
- uploader
- reviewer
- third_reviewer

## Pages (read access)
- Home dashboard: reviewer OR uploader OR admin
- Need packets: reviewer OR uploader OR admin
- To be scrubbed: reviewer OR admin
- To be screened: reviewer OR admin
- Assign/Send: admin

## Actions (write)
- Create event: admin
- Update event: admin
- Upload packet: uploader OR reviewer OR admin
- Upload scrubbed: uploader OR reviewer OR admin
- Screen event: reviewer OR admin
- Assign events: admin
- Send events: admin
- Bulk import CSV: admin

Implementation: Use backend decorators and frontend route guards keyed to these roles.


