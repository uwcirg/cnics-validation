"""Filesystem archive for bulk-import CSV submissions.

Every CSV submitted to ``POST /api/events/bulk`` is written here verbatim
before it is parsed, together with a JSON manifest recording who submitted
it, when, and which rows were skipped and why. See
``specs/009-save-import-csv/`` for the full design.

The archive is a directory holding two files per submission::

    <import_id>.csv     the submitted bytes, unmodified
    <import_id>.json    the import record (manifest) for that submission

where ``import_id`` is ``<YYYYMMDD>T<HHMMSS>Z-<8 hex>`` in UTC. A lexical
sort of those names is therefore a chronological sort, and the name carries
no patient identifier — the name as submitted lives inside the manifest,
which has the same access protection as the contents.

This module deliberately imports nothing from Flask: everything here is a
pure function over an archive directory path, so it is unit-testable without
standing up the app, and ``app.py`` keeps only the request handling.

Nothing in this module deletes, truncates, rotates, or overwrites an
archived file (FR-012). Writes use exclusive creation so that an id
collision raises rather than silently replacing an earlier submission.
"""

from __future__ import annotations

import json
import os
import re
import uuid
from datetime import datetime, timezone
from typing import Any, Dict, List, Optional, Tuple

# Grammar of an import id: UTC date, UTC time, and 8 hex digits of entropy.
# Machine-generated in full, so client-supplied ids can be held to a strict
# whitelist. The pattern admits no '/', '.', or '\', which is what makes a
# validated id safe to join onto a directory path.
IMPORT_ID_RE = re.compile(r'^\d{8}T\d{6}Z-[0-9a-f]{8}$')

# Recovered from an id when a manifest is missing; see `degraded_record`.
_ID_TIMESTAMP_FMT = '%Y%m%dT%H%M%SZ'

# The archive holds PHI, so it is not group- or world-writable and not
# world-readable at all.
ARCHIVE_DIR_MODE = 0o750
ARCHIVE_FILE_MODE = 0o640

# Bumped only if the manifest shape changes incompatibly. Readers must
# tolerate an unknown higher value by rendering what they understand.
RECORD_VERSION = 1

SUBMISSION_SUFFIX = '.csv'
RECORD_SUFFIX = '.json'

# The manifest carries the file name as submitted, which administrators tend
# to build out of site and patient names. It is stored (it is genuinely
# useful for cross-referencing) but truncated, and never logged.
MAX_ORIGINAL_NAME_CHARS = 255


class InvalidImportId(ValueError):
    """Raised for an id that fails the grammar or escapes the archive."""


def new_import_id() -> str:
    """Return a fresh, sortable, non-identifying import id."""
    stamp = datetime.now(timezone.utc).strftime(_ID_TIMESTAMP_FMT)
    return f'{stamp}-{uuid.uuid4().hex[:8]}'


def is_valid_import_id(value: Any) -> bool:
    """Whether `value` is a well-formed import id."""
    return isinstance(value, str) and IMPORT_ID_RE.match(value) is not None


def ensure_archive_dir(archive_dir: str) -> str:
    """Create the archive directory if absent and return it.

    An `OSError` is deliberately allowed to propagate: the caller fails the
    request closed rather than importing events it cannot archive.
    """
    os.makedirs(archive_dir, mode=ARCHIVE_DIR_MODE, exist_ok=True)
    return archive_dir


def _archived_path(archive_dir: str, import_id: str, suffix: str) -> str:
    """Resolve one archived file's path, refusing anything outside the archive.

    The id is matched against the grammar before it touches `os.path.join`,
    and the resolved path is then asserted to sit directly inside the
    resolved archive directory. Either failure raises `InvalidImportId`;
    callers turn that into a 404 so an invalid id and a missing record are
    indistinguishable to the client.
    """
    if not is_valid_import_id(import_id):
        raise InvalidImportId(f'malformed import id: {import_id!r}')
    name = import_id + suffix
    root = os.path.realpath(archive_dir)
    resolved = os.path.realpath(os.path.join(archive_dir, name))
    if os.path.dirname(resolved) != root or os.path.basename(resolved) != name:
        raise InvalidImportId(f'import id resolves outside the archive: {import_id!r}')
    return resolved


def submission_path(archive_dir: str, import_id: str) -> str:
    """Path of the archived submission for `import_id`."""
    return _archived_path(archive_dir, import_id, SUBMISSION_SUFFIX)


def record_path(archive_dir: str, import_id: str) -> str:
    """Path of the import record (manifest) for `import_id`."""
    return _archived_path(archive_dir, import_id, RECORD_SUFFIX)


def timestamp_from_import_id(import_id: str) -> Optional[str]:
    """Recover the submission time encoded in an id, as ISO 8601 UTC.

    Returns None for an id that does not match the grammar. This is what
    lets a submission whose manifest is missing still be shown with its
    correct timestamp.
    """
    if not is_valid_import_id(import_id):
        return None
    moment = datetime.strptime(import_id[:16], _ID_TIMESTAMP_FMT)
    return moment.replace(tzinfo=timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ')


# --- Writing ----------------------------------------------------------------


def write_submission(archive_dir: str, import_id: str, data: bytes) -> str:
    """Archive the submitted bytes verbatim and return the path written.

    Created exclusively (`O_EXCL`), so an id collision raises rather than
    overwriting an earlier submission (FR-002). `OSError` propagates: the
    caller refuses the import rather than proceeding unarchived.
    """
    ensure_archive_dir(archive_dir)
    path = submission_path(archive_dir, import_id)
    fd = os.open(path, os.O_CREAT | os.O_EXCL | os.O_WRONLY, ARCHIVE_FILE_MODE)
    with os.fdopen(fd, 'wb') as handle:
        handle.write(data)
    return path


def build_record(
    import_id: str,
    *,
    submitted_by_id: Optional[int],
    submitted_by: Optional[str],
    original_name: Optional[str],
    size_bytes: int,
    imported_count: int = 0,
    errors: Optional[List[str]] = None,
    refused: bool = False,
    submitted_at: Optional[str] = None,
) -> Dict[str, Any]:
    """Assemble the manifest for one submission.

    `outcome` is derived rather than passed in — it is a pure function of
    what happened, so there is no way for it to disagree with the counts:

    ======================================  ==========  ================
    Condition                               outcome     file_available
    ======================================  ==========  ================
    `refused` (over the size cap)           refused     False
    imported > 0, none skipped              imported    True
    imported > 0, some skipped              partial     True
    imported == 0                           rejected    True
    ======================================  ==========  ================

    `rejected` therefore covers every zero-event case where the file was
    archived — a decode failure, a file with no usable rows, an empty file,
    a database error — with the reason carried in `errors`.
    """
    errors = list(errors or [])
    if refused:
        outcome, file_available = 'refused', False
    elif imported_count > 0:
        outcome, file_available = ('partial' if errors else 'imported'), True
    else:
        outcome, file_available = 'rejected', True

    return {
        'record_version': RECORD_VERSION,
        'import_id': import_id,
        'submitted_at': submitted_at or timestamp_from_import_id(import_id),
        'submitted_by_id': submitted_by_id,
        'submitted_by': submitted_by,
        # Administrators name files after sites and patients, so this is
        # treated as PHI: stored, never logged.
        'original_name': (original_name or None) and original_name[:MAX_ORIGINAL_NAME_CHARS],
        'size_bytes': size_bytes,
        'outcome': outcome,
        'file_available': file_available,
        'imported_count': imported_count,
        'skipped_count': len(errors),
        'errors': errors,
    }


def write_record(archive_dir: str, import_id: str, record: Dict[str, Any]) -> str:
    """Write a submission's manifest and return the path written.

    Exclusive creation here too: a manifest is written once, after the import
    attempt, and never revised.
    """
    ensure_archive_dir(archive_dir)
    path = record_path(archive_dir, import_id)
    fd = os.open(path, os.O_CREAT | os.O_EXCL | os.O_WRONLY, ARCHIVE_FILE_MODE)
    with os.fdopen(fd, 'w', encoding='utf-8') as handle:
        json.dump(record, handle, ensure_ascii=False, indent=2)
        handle.write('\n')
    return path


# --- Reading ----------------------------------------------------------------


def _degraded_record(import_id: str, submission: str) -> Dict[str, Any]:
    """Reconstruct what can be known about a submission with no manifest.

    A `.csv` with no `.json` means the manifest write failed after the import
    (research D8). The submission time is recoverable from the id and the size
    from a `stat`, so the record is still useful and the file still
    downloadable — but the counts are genuinely unknown, and `incomplete` says
    so, to keep a lost manifest from reading as a real zero-event import.
    """
    try:
        size_bytes: Optional[int] = os.path.getsize(submission)
        file_available = True
    except OSError:
        size_bytes = None
        file_available = False
    return {
        'record_version': RECORD_VERSION,
        'import_id': import_id,
        'submitted_at': timestamp_from_import_id(import_id),
        'submitted_by_id': None,
        'submitted_by': None,
        'original_name': None,
        'size_bytes': size_bytes,
        'outcome': 'unknown',
        'file_available': file_available,
        'imported_count': None,
        'skipped_count': None,
        'errors': [],
        'incomplete': True,
    }


def read_record(archive_dir: str, import_id: str) -> Optional[Dict[str, Any]]:
    """Return the record for `import_id`, or None if there is no such import.

    A malformed id is simply not found: the caller turns that into a 404, so
    an invalid id and a missing record look the same from outside.
    """
    try:
        manifest = record_path(archive_dir, import_id)
        submission = submission_path(archive_dir, import_id)
    except InvalidImportId:
        return None

    try:
        with open(manifest, 'r', encoding='utf-8') as handle:
            record = json.load(handle)
        if isinstance(record, dict):
            return record
    except (OSError, ValueError):
        # Missing or unreadable manifest — fall through to the degraded form
        # rather than treating a recoverable gap as an error.
        pass

    if not os.path.exists(submission) and not os.path.exists(manifest):
        return None
    return _degraded_record(import_id, submission)


def list_records(
    archive_dir: str, limit: Optional[int] = None, offset: int = 0
) -> Tuple[List[Dict[str, Any]], int]:
    """Return `(records, total)`, newest first.

    The union of `.csv` and `.json` stems is taken deliberately: a refused
    submission has a manifest and no file, so globbing only `.csv` would
    write those records and then never show them (research D5).

    Ordering costs no manifest reads — the id grammar makes a descending
    lexical sort of the file names a descending chronological sort — so only
    the requested page is read from disk.
    """
    try:
        names = os.listdir(archive_dir)
    except OSError:
        # No archive directory yet simply means no imports have been made.
        return [], 0

    stems = set()
    for name in names:
        for suffix in (SUBMISSION_SUFFIX, RECORD_SUFFIX):
            if name.endswith(suffix):
                stems.add(name[: -len(suffix)])
                break

    ordered = sorted((s for s in stems if is_valid_import_id(s)), reverse=True)
    total = len(ordered)

    start = max(0, offset or 0)
    window = ordered[start:] if limit is None else ordered[start:start + max(0, limit)]

    records = [read_record(archive_dir, stem) for stem in window]
    return [r for r in records if r is not None], total
