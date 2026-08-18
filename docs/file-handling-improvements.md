# File Upload/Download Improvements

## 🎯 Problem Solved

**Issue**: Files uploaded to the system were being downloaded and opened as ZIP files instead of their original format (PDF, DOC, DOCX).

**Root Cause**: The backend was not setting proper MIME types when serving files, causing browsers to default to treating all files as ZIP archives.

## 🔧 Backend Fixes

### 1. MIME Type Detection (`flask_backend/app.py`)

Added proper MIME type mapping for supported file extensions:

```python
# MIME type mapping for file extensions
MIME_TYPE_MAP = {
    '.zip': 'application/zip',
    '.pdf': 'application/pdf',
    '.doc': 'application/msword',
    '.docx': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
}
```

### 2. Enhanced Download Endpoint

Updated `/api/events/download/<event_id>` to:

- **Detect MIME type** based on file extension
- **Set proper Content-Type headers** for each file type
- **Add cache control headers** to prevent browser caching issues
- **Preserve original filenames** with proper Content-Disposition headers

```python
# Determine MIME type based on file extension
_, ext = os.path.splitext(name)
mime_type = MIME_TYPE_MAP.get(ext.lower(), 'application/octet-stream')

# Set appropriate headers for file download
response = send_file(
    path, 
    as_attachment=True, 
    download_name=name,
    mimetype=mime_type
)

# Add additional headers for better browser handling
response.headers['Content-Disposition'] = f'attachment; filename="{name}"'
response.headers['Cache-Control'] = 'no-cache, no-store, must-revalidate'
response.headers['Pragma'] = 'no-cache'
response.headers['Expires'] = '0'
```

## 🎨 Frontend Improvements

### 1. Enhanced Download Page (`EventDownload.jsx`)

- **Better user experience** with loading states and error handling
- **Toast notifications** for download success/failure
- **Retry functionality** if download fails
- **Manual download link** as fallback
- **Clear instructions** about file types

### 2. Improved Download Links Throughout App

Updated all download links to use proper download attributes:

- **EventViewAll.jsx**: Better download buttons with proper file handling
- **EventUpload.jsx**: Styled download links after successful upload
- **EventScreen.jsx**: Enhanced chart download functionality
- **EventReview.jsx**: Improved chart download for reviewers
- **EventEdit.jsx**: Better download experience for event editing

## 📋 Supported File Types

| Extension | MIME Type | Description |
|-----------|-----------|-------------|
| `.zip` | `application/zip` | Compressed archives |
| `.pdf` | `application/pdf` | PDF documents |
| `.doc` | `application/msword` | Microsoft Word 97-2003 |
| `.docx` | `application/vnd.openxmlformats-officedocument.wordprocessingml.document` | Microsoft Word 2007+ |

## 🗄️ Bulk-Import Archive

Separate from event packets: every CSV submitted at `/events/addMany`
(`POST /api/events/bulk`) is written to disk **before it is parsed**, together
with a JSON manifest of what the import did. Administrators review the history
at `/events/imports`.

### Storage layout

Archived submissions live in an `imports/` subdirectory of the uploads volume,
deliberately **not** its root, so they can never be matched by the candidate
scan in `events_download` — the two namespaces stay disjoint in both
directions.

```text
/opt/backend/uploads/                    # DOWNLOADS_DIR
├── 4711.zip                             # event packet
└── imports/                             # mode 0750, created on demand
    ├── 20260813T144512Z-7f3a1c2b.csv    # the submitted bytes, verbatim
    └── 20260813T144512Z-7f3a1c2b.json   # the import record for it
```

The directory holds three shapes, all of which the read endpoints handle:

| On disk | Meaning | Shown as |
|---|---|---|
| `.csv` + `.json` | Normal — a processed submission and its outcome | The record as written |
| `.csv` only | The manifest write failed after the import | `outcome: unknown`, `incomplete: true` |
| `.json` only | Refused over the size cap before its contents were kept | `outcome: refused`, no download |

### Id grammar

```text
<YYYYMMDD>T<HHMMSS>Z-<8 hex>       regex: ^\d{8}T\d{6}Z-[0-9a-f]{8}$
```

The UTC timestamp prefix makes a lexical sort chronological, so "newest first"
costs no manifest reads. The 8 hex digits of entropy keep two submissions in
the same second from colliding, and writes use exclusive creation so a
collision would fail loudly rather than overwrite. The name deliberately
carries **no** original file name: administrators name files after sites and
patients, and directory listings are not the place for that. The name as
submitted is kept inside the manifest, which has the same access protection as
the contents.

Every client-supplied id is matched against that regex before it touches
`os.path.join`, and the resolved path is asserted to sit inside the archive.
Either failure is a 404 — an invalid id and a missing record look identical
from outside.

### Configuration

| Variable | Default | Purpose |
|---|---|---|
| `IMPORT_ARCHIVE_DIR` | `<UPLOAD_DIR>/imports` | Move the archive, e.g. onto a separately backed-up mount |
| `MAX_IMPORT_CSV_BYTES` | `10485760` (10 MB) | Refuse larger submissions before their contents are stored |

Both are optional, and both must be listed under the `backend` service in
`docker-compose.yaml`: Compose reads `.env` for `${...}` substitution but does
not auto-inject variables into containers.

`MAX_IMPORT_CSV_BYTES` is checked inside the CSV endpoint on purpose. Flask's
global `MAX_CONTENT_LENGTH` would also cap event-packet uploads, which are
legitimately large ZIPs — a 10 MB global cap would break them.

### Two behaviors worth knowing before you deploy

- **Archiving is fail-closed.** The write happens before the parse, and if it
  fails the request returns 500 and creates **no events**. A full or read-only
  uploads volume therefore blocks bulk import; single-event creation at
  `/events/add` still works. An unarchived import is invisible after the fact,
  which is the exact problem the archive exists to prevent, so it refuses
  loudly instead.
- **A failed manifest write does not fail the request.** By then the bytes are
  safe and the events exist — and the shared tables are MyISAM, so the
  handler's `session.rollback()` cannot undo them. The reader renders the
  submission as an incomplete record instead.

Nothing in the application deletes, rotates, truncates, or overwrites an
archived file, and nothing in it ever will — see *Purging the archive* below
for the only removal path. Archived CSVs contain site patient identifiers and
event dates: treat them as PHI, exactly like event packets. No file name, file
content, or skipped-row text is ever logged — failures log the import id and an
error class only.

### Purging the archive

The application never removes an archived submission, on any schedule or
trigger. Purging is a manual action on the uploads volume, and its audience is
the **operator** — whoever has shell access to the deployment host and can run
`docker compose`. That is not the same as the application's administrator role:
signing in as an admin and reaching `/events/imports` confers no ability to
delete anything, by design.

Run every command below from the deployment directory. The archive lives on the
`cnics-downloads` named volume, which is scoped to `COMPOSE_PROJECT_NAME`, so on
a host running two studies side by side the wrong working directory purges the
wrong study's archive.

**1. Look before you delete.**

```bash
docker compose exec backend ls -la /opt/backend/uploads/imports
```

If `IMPORT_ARCHIVE_DIR` is set for this deployment, substitute that path here
and in every command that follows.

**2. Take a copy first — optional, and it is PHI.**

A purge is irreversible: the archive is the only copy of what was submitted.

```bash
docker compose cp backend:/opt/backend/uploads/imports ./imports-backup-20260818
```

Skip this step when the intent is to destroy the data — the whole point of a
purge is usually that these files should no longer exist. If you do take a
copy, it carries site patient identifiers off the protected volume and onto
whatever filesystem you are standing in: put it somewhere access-controlled,
and delete it when you are done with it.

**3a. Purge the whole archive.**

```bash
docker compose exec backend sh -c \
  'rm -f /opt/backend/uploads/imports/*.csv /opt/backend/uploads/imports/*.json'
```

**3b. Or remove a single import**, by its id. The id is not a column in the
history table — select the import at `/events/imports` and read it from the
URL (`?import_id=20260813T144512Z-7f3a1c2b`), or match on submission time in
the listing from step 1:

```bash
docker compose exec backend sh -c \
  'rm -f /opt/backend/uploads/imports/20260813T144512Z-7f3a1c2b.*'
```

> **Delete the pair, never one half.** The `.*` glob above is deliberate. A
> submission is a `.csv` and a `.json` with the same stem, and the read
> endpoints infer meaning from which of the two is present (see the three
> shapes table above). Deleting only the `.json` leaves a record that reports
> itself as `refused` — as though the submission had been rejected over the
> size cap — and deleting only the `.csv` leaves one that reports `incomplete`,
> as though the import had crashed mid-write. Either way the history now
> asserts something that did not happen, which is worse than the entry being
> gone.

**4. Confirm the result.** Reload `/events/imports`: it lists what survived, and
an empty archive renders as an empty list rather than an error. Then run a small
bulk import — it writes a fresh pair and appears in the history as usual.

Two things worth knowing:

- **Keeping the directory is optional.** The list endpoint treats a missing
  archive directory as "no imports yet", and the next submission recreates it at
  mode `0750` before writing. Removing the directory itself with `rm -rf` is
  therefore safe; recreating it by hand is not necessary, and if you do, match
  that mode — these files are PHI and must not be world-readable.
- **Purging removes the audit trail, not the data.** The events and criteria
  those imports created remain exactly as they are; nothing in the application
  links an event back to the submission that produced it. After a purge you can
  still see what exists in the study, but no longer what was uploaded to
  produce it, who uploaded it, or which rows were skipped.

## 🧪 Testing Guide

### Test File Upload/Download

1. **Upload Different File Types**:
   - Upload a PDF file → Should download as PDF
   - Upload a DOC file → Should download as DOC
   - Upload a DOCX file → Should download as DOCX
   - Upload a ZIP file → Should download as ZIP

2. **Test Download Behavior**:
   - Files should open in appropriate applications
   - Filenames should preserve original extensions
   - No more "ZIP file" errors in browsers

3. **Test Error Handling**:
   - Try downloading non-existent files → Should get 404
   - Test with unsupported file types → Should be rejected on upload

### Manual Testing Steps

1. **Upload Test**:
   ```bash
   # Test with different file types
   curl -X POST -F "scrubbed_file=@test.pdf" http://localhost:5000/api/events/123/upload_scrubbed
   curl -X POST -F "scrubbed_file=@test.doc" http://localhost:5000/api/events/123/upload_scrubbed
   ```

2. **Download Test**:
   ```bash
   # Check headers
   curl -I http://localhost:5000/api/events/download/123
   
   # Should see:
   # Content-Type: application/pdf (for PDF files)
   # Content-Disposition: attachment; filename="123.pdf"
   ```

3. **Browser Testing**:
   - Upload files through the web interface
   - Download files and verify they open correctly
   - Check browser developer tools for proper headers

## 🔍 Troubleshooting

### Common Issues

1. **File Still Opens as ZIP**:
   - Check browser cache (clear cache and try again)
   - Verify file was uploaded with correct extension
   - Check backend logs for MIME type detection

2. **Download Fails**:
   - Check file exists in DOWNLOADS_DIR
   - Verify user has proper permissions
   - Check backend error logs

3. **Wrong MIME Type**:
   - Verify file extension is in ALLOWED_PACKET_EXTENSIONS
   - Check MIME_TYPE_MAP includes the extension
   - Test with curl to see actual headers

### Debug Commands

```bash
# Check what files exist for an event
ls -la /path/to/downloads/123.*

# Test download endpoint directly
curl -v http://localhost:5000/api/events/download/123

# Check file type
file /path/to/downloads/123.pdf
```

## 🚀 Benefits

### For Users
- **Correct file opening**: Files open in appropriate applications
- **Better UX**: Clear download status and error messages
- **Reliable downloads**: Proper retry mechanisms and fallbacks

### For Administrators
- **Proper file handling**: No more confusion about file types
- **Better debugging**: Clear error messages and logging
- **Consistent behavior**: All download links work the same way

### For Developers
- **Maintainable code**: Clear MIME type mapping
- **Extensible**: Easy to add new file types
- **Robust error handling**: Graceful failure modes

## 📝 Future Enhancements

### Potential Improvements
1. **File type validation**: Check file content, not just extension
2. **Virus scanning**: Integrate antivirus scanning for uploads
3. **File preview**: Show file previews before download
4. **Bulk downloads**: Allow downloading multiple files as ZIP
5. **File versioning**: Track file upload history

### Adding New File Types
To add support for new file types:

1. **Add to ALLOWED_PACKET_EXTENSIONS**:
   ```python
   ALLOWED_PACKET_EXTENSIONS = {
       '.zip', '.pdf', '.doc', '.docx', '.xlsx'  # Add new extension
   }
   ```

2. **Add to MIME_TYPE_MAP**:
   ```python
   MIME_TYPE_MAP = {
       '.zip': 'application/zip',
       '.pdf': 'application/pdf',
       '.doc': 'application/msword',
       '.docx': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
       '.xlsx': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'  # Add new MIME type
   }
   ```

3. **Test thoroughly** with the new file type

---

**Result**: Files now download and open in their correct format, providing a much better user experience for the CNICS Validation system! 🎉
