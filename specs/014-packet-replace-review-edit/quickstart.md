# Quickstart: Packet Replacement Before Review, and Editable Reviews

**Feature**: 014-packet-replace-review-edit

This feature affects all active studies (mci, scans, dischargesummaries). It needs **one database migration per existing deployment**. New databases get the column automatically.

## 1. Upgrade an existing deployment

Run the migration **before** pulling and restarting the new code. The old code ignores the extra nullable column, so this order is safe.

```bash
cd /srv/<deployment>
git fetch && git checkout <release containing 014>   # only the init/ script is needed at this point
docker compose exec -T mariadb bash -s < init/migrations/014-reviews-modified.sh
```

The script is idempotent and study-agnostic. It adds `reviews.modified` if missing. A second run reports "already present".

Then deploy as usual:

```bash
docker compose up -d --build backend web
```

If you deploy the code first, the review page and the review endpoints answer 503 with a message naming `init/migrations/014-reviews-modified.sh` until the migration is run **and the backend is restarted**. The schema check result is cached per process. The rest of the app keeps working.

## 2. Verify packet replacement (any study)

1. Sign in as an uploader at site X. Open **Re-upload Existing Packets**. Events from site X that are uploaded, scrubbed, screened, assigned or rejected, and not yet sent, are listed.
2. Open one that has been scrubbed. The button reads **Replace packet**. Upload a file.
   - mci and dischargesummaries: the event is back in the admin's **To Be Scrubbed** list, and its reviewer assignment is unchanged.
   - scans: the status is unchanged and the new file downloads.
3. As admin, upload the scrubbed copy again (and screen it, for mci).
   - If the event was assigned before the replacement, it returns to `assigned`. In mci it shows up again in **To Be Sent**.
4. Send an event (mci), or submit its review (scans or dischargesummaries). Then try to replace its packet as the uploader **and** as an admin. Both are refused with "…Contact the systems operator."
5. In `DOWNLOADS_DIR`, the old scrubbed copy now has the name `superseded_<id>_<timestamp>.<ext>`.
6. **Stale scrub (mci or dischargesummaries).** As admin, open an event's scrub page and download the packet. As the uploader, replace the packet. Back on the still-open scrub page, upload a file. It is refused with "…was replaced on…". Reload the page, and the upload is accepted.
7. **Screen before scrub (mci).** Replace a screened event's packet. On a screen page opened before the replacement, click accept. It is refused with "…has not been scrubbed yet."
8. **Reviewer notice (scans or dischargesummaries).** Assign an event, then replace its packet. The reviewer's review page shows "This event's packet was replaced on …". The `logs` table has a row with `action = 'replace_packet'` and `params = 'event_id=<id>;assigned=1'`.

## 3. Verify review editing

**Single-reviewer study (scans, or dischargesummaries after Phase B):**

1. As the assigned reviewer, submit a review. Reopen the event. The form shows your answers and a **Save changes** button.
2. Change an answer and save. As admin, **/events/edit** shows the new answer and "Last edited: …". The export's `Last Edited` column is filled.
3. Repeat after the event is `done`. Editing is still allowed.

**Two-reviewer study (mci):**

1. Reviewer A submits a review, reopens the event, edits it and saves. The save succeeds.
2. Reviewer B submits. Reviewer A reopens the event and sees the answers read-only, with "Both reviews for this event are complete…". Reviewer B sees the same.
3. In the mci export, `Review 1 Last Edited` and `Review 2 Last Edited` are the last two columns.

## 4. Operator procedure: replacing a packet after the window has closed

The application refuses this. Do it only at the study team's request, and agree in advance whether existing reviews stand.

1. Copy the new raw packet into the deployment's downloads volume as `orig_<event_id>_<N>.<ext>`, where `<N>` is a new random integer.
2. If the study scrubs, copy the new scrubbed copy in as `<event_id>.<ext>`. Rename any existing `<event_id>.*` file to `superseded_<event_id>_<YYYYmmddHHMMSS>.<ext>` first.
3. In the database:

   ```sql
   UPDATE events SET file_number = <N>, original_name = '<name>' WHERE id = <event_id>;
   INSERT INTO logs (user_id, controller, action, params, time)
     VALUES (<operator user id>, 'events', 'replace_packet', 'event_id=<event_id>;assigned=1', NOW());
   ```

4. Leave `status`, the stage dates and any reviews unchanged unless the study team decides otherwise.

## 5. Tests

```bash
python3 -m pytest flask_backend/tests
cd frontend && npm run lint && npm run build
python -m flask_backend.generate_openapi    # commit the regenerated openapi.json
```
