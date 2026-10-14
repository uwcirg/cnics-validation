# Quickstart: DEXA scan review form and on-site reviewer instructions

**Feature**: 012-dexa-review-form (rev. 3)

These steps are run by the deployment operator on the server. Development
laptops have no running stack.

## Deploying

1. Pull the feature and confirm `.env` has the right `STUDY_TYPE`. The
   mariadb service now reads it too: `init/04-study-schema.sh` uses it on a
   new volume, and the migration below checks it.
2. Rebuild and recreate everything: `docker compose up -d --build`. The
   mariadb container is recreated because its environment gained
   `STUDY_TYPE`. Its data volume is kept, and nothing in `init/` re-runs on
   an existing volume.
3. **Migration** (research D10). How much is needed depends on the study:
   - **`mci` deployments**: nothing. The `reviews` table is unchanged.
   - **`scans` deployments that already exist**: run once. The script is
     idempotent, and it refuses to run unless the container's `STUDY_TYPE`
     is `scans`.
     ```bash
     docker compose exec -T mariadb bash -s < init/migrations/012-scans-reviews.sh
     # verify
     docker compose exec mariadb bash -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mariadb -uroot "$MYSQL_DATABASE" \
       -e "SHOW TABLES LIKE \"%review%\"; DESCRIBE reviews; DESCRIBE review_regions"'
     docker compose restart backend   # it caches its schema check
     ```
     If the old MI-shaped `reviews` and `event_derived_datas` tables are
     present, the script renames them to `reviews_mi_pre012` and
     `event_derived_datas_mi_pre012`, so anything entered through the old MI
     form is kept. It then creates the scans `reviews` and `review_regions`
     tables. Once you've confirmed the `*_mi_pre012` tables hold nothing you
     need, you can drop them.
     If you skip this step, review submission and the export return **503**
     with a message naming this script.
   - **New volumes, any study**: nothing to do. `init/04-study-schema.sh`
     applies `init/studies/<STUDY_TYPE>.sql` automatically. Only that study's
     tables are created. For `vte`, `afib`, `cva` and `hf`, whose files are
     still dormant, it applies `mci.sql` and logs a warning.

## Instructions content

- DEXA: `app/webroot/files/instructions/scans.html`, or
  `$FILES_DIR/instructions/scans.html` if `FILES_DIR` is overridden.
- This is a plain, self-contained HTML file. To change the wording, edit the
  file on the server and reload the tab. No rebuild is needed (FR-023).

## Automated checks

```bash
pytest flask_backend/tests     # incl. new test_study_modules.py, test_scans_review.py; updated test_review_endpoint.py
(cd frontend && npm run lint && npm run build)
python -m flask_backend.generate_openapi   # commit regenerated openapi.json
```

## Manual verification script

Setup: a `scans` deployment with one event assigned to reviewer **R**, and an
`mci` deployment (or `STUDY_TYPE=mci`).

### A. Scans form (`scans`, as R)

1. Open `/events/review?event_id=<id>`. Expect Step 1 with "Download charts
   for SCANS <id>", which downloads the packet. Expect Step 2 with one region
   dropdown and **no** Submit button. No MI questions appear.
2. Pick **Lumbar spine**. Expect Laterality (blank), T score, and Z score
   inputs, followed by Step 3 with an empty dropdown and Submit.
3. Enter T = `-1.1` and Z = `-0.4`.
4. In Step 3 pick **Hip**. Set Laterality = Left and T = `-0.9`. Leave Z
   blank.
5. In Step 3 pick **Wrist** (Left), then **Wrist** again (Right). Repeating
   a region is allowed.
6. In Step 3 pick **Other: please specify**, leave its text blank, and click
   Submit. Expect "Please specify the body region" and no request sent. Then
   type `Total body`.
7. Change Region 2 from Hip to **Proximal femur**. Expect its values to stay.
   Remove Region 3 (the first Wrist). Expect the other entries unchanged.
8. Enter T = `abc` and leave the field. Expect an inline error. `12` and
   `1.234` are also rejected. Fix it to `1.2`.
9. Add entries until there are 8. Expect no horizontal scrolling. Then remove
   entries until 5 remain.
10. Submit. Expect "Review submitted. Event status: done." and the form
    becomes read-only.
11. As admin, call `GET /api/events/<id>/review`. Expect 5 regions in the
    order entered, `null` for blanks, and score strings such as `"-1.10"`.
    `/events/export` should give 5 CSV rows for this event.
12. POST the review again from another tab. Expect **409** and no new rows.

### B. Instructions (`scans`)

1. On the review page, click "View review instructions". Expect a new tab
   with the full DEXA instructions: steps 1–3, sub-items a–d, and notes 1–6,
   in that order. The review tab keeps its entries.
2. The home page "Review Instructions:" box shows the same link, and
   "No additional instructions" is gone.
3. Print preview the instructions tab. Expect only the instructions, on 2
   pages or fewer.

### C. MCI (`mci`)

1. The review page shows the existing MI form, behaving exactly as before.
   The only visible change is the Step 1 label "Download charts for …". There
   is no "Review Instructions:" box.
2. On the home page, the "Review Instructions:" box shows its header only,
   with no `.doc`/`.pdf` links. The "Review packets should contain:" box
   keeps its links.
3. Submitting an MI review still works. Submitting a second time returns
   409.
4. `/events/export` produces the same CSV as before this feature, with the
   same headings and columns.
