### Assignment Email Template

This is the plain‑text template used by the backend emailer when sending reviewer assignment emails (reviewer 1, reviewer 2, and reviewer 3).

#### Subject

```
{{EMAIL_SUBJECT_PREFIX}} MI Review Assignment – Event {{EVENT_ID}}
```

Defaults: `EMAIL_SUBJECT_PREFIX` → "CNICS / NA-ACCORD".

#### Body (text)

```
Dear {{FIRST_NAME}} {{LAST_NAME}},

You have been assigned a Myocardial Infarction (MI) review.
Please download the charts and complete the review at the links below.

{{DOWNLOAD_URL}}

After reviewing the packet, submit your decision here:
{{REVIEW_URL}}

You can also view all events here:
{{INDEX_URL}}

Thank you,
CNICS/NA-ACCORD Team
Help: {{EMAIL_HELP_ADDRESS}}
```

#### Placeholder definitions

- `{{EVENT_ID}}`: Numeric ID of the event being sent
- `{{FIRST_NAME}}`, `{{LAST_NAME}}`: Reviewer’s name (from `users` table)
- `{{EMAIL_HELP_ADDRESS}}`: From env `EMAIL_HELP_ADDRESS` (falls back to `EMAIL_REPLY_TO` or `EMAIL_FROM`)
- `{{DOWNLOAD_URL}}`: `{{BACKEND_ORIGIN}}/api/events/download/{{EVENT_ID}}`
- `{{REVIEW_URL}}`: `{{FRONTEND_ORIGIN}}/events/review?event_id={{EVENT_ID}}`
- `{{INDEX_URL}}`: `{{FRONTEND_ORIGIN}}/events/viewAll`

Origin resolution:
- `FRONTEND_ORIGIN` is required for link generation
- `BACKEND_ORIGIN` (optional) defaults to `FRONTEND_ORIGIN` when not set

#### Attachments

If `EMAIL_ATTACH_PACKET=1`, the emailer will attach the first existing file it finds for the event in the downloads directory:

- `UPLOAD_DIR` → else `DOWNLOADS_DIR` → else `FILES_DIR/downloads`
- Filenames tried (in order): `{{EVENT_ID}}.zip`, `.pdf`, `.doc`, `.docx`, then `event_{{EVENT_ID}}.zip`

#### Notes

- The message is sent as plain text. Line endings are normalized by the mail library.
- Emails are sent to reviewer 1 and reviewer 2 when events are "sent" via the API/UI, and to reviewer 3 when the third reviewer is assigned.


