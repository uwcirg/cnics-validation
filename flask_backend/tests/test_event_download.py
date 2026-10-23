"""Integration tests for GET /api/events/download/<event_id>.

The endpoint used to look only for the scrubbed packet (`<event_id><ext>`)
and the legacy `event_<id>.zip`, so every packet written by
`/api/events/<id>/upload_raw` — which names files
`orig_<event_id>_<file_number><ext>` — was unreachable and the button
404'd. On a deployment with scrubbing switched off (the `scans` profile)
no scrubbed file is ever produced, so that was every event.

`DOWNLOADS_DIR` and `FILES_DIR` are module-level constants resolved at
import time, so they are patched per test onto a tmp_path; the event row
supplying `file_number` is mocked with the `FakeSession` pattern used by
the other endpoint tests.
"""

import importlib
from unittest.mock import patch

import pytest
from flask import g


DOWNLOAD_URL = "/api/events/download/{event_id}"

EVENT_ID = 4821
FILE_NUMBER = 837261234


REVIEWER_ID = 9


class FakeEvent:
    def __init__(self, file_number=FILE_NUMBER, reviewer1_id=REVIEWER_ID):
        self.id = EVENT_ID
        self.patient_id = 7
        self.file_number = file_number
        self.reviewer1_id = reviewer1_id
        self.reviewer2_id = None
        self.reviewer3_id = None


class FakePatient:
    def __init__(self, site="TEST"):
        self.id = 7
        self.site = site


class FakeSession:
    """Answers `query(PatientsView).get` with the patient and any other
    `query(...).get` with the event row."""

    def __init__(self, event, patient=None):
        self._event = event
        self._patient = patient if patient is not None else FakePatient()
        self._target = None

    def query(self, model, *args, **kwargs):
        self._target = model
        return self

    def get(self, _row_id):
        models = importlib.import_module("flask_backend.models")
        if self._target is models.PatientsView:
            return self._patient
        return self._event

    def close(self):
        pass


@pytest.fixture
def app_mod():
    return importlib.import_module("flask_backend.app")


@pytest.fixture
def downloads(app_mod, tmp_path):
    """Point both packet directories at an empty tmp dir for one test."""
    d = tmp_path / "uploads"
    d.mkdir()
    with patch.object(app_mod, "DOWNLOADS_DIR", str(d)), patch.object(
        app_mod, "FILES_DIR", str(d)
    ):
        yield d


@pytest.fixture
def event_row(app_mod):
    """Install a mock `events` row, returning a setter for its file_number."""
    event = FakeEvent()

    def _session():
        return FakeSession(event)

    models = importlib.import_module("flask_backend.models")
    with patch.object(models, "get_session", _session):
        yield event


def _full_workflow(app_mod):
    """Patch the workflow config to the scrubbing-enabled profile."""
    cfg = app_mod.get_workflow_config()
    return patch.object(
        app_mod,
        "get_workflow_config",
        lambda: type(cfg)(
            study_type="mci",
            study_label="MCI",
            study_title="",
            scrubbing=True,
            screening=True,
            sending=True,
            reviewer_count=2,
        ),
    )


def _scans_workflow(app_mod):
    """Patch the workflow config to the scrubbing-disabled profile."""
    cfg = app_mod.get_workflow_config()
    return patch.object(
        app_mod,
        "get_workflow_config",
        lambda: type(cfg)(
            study_type="scans",
            study_label="SCANS",
            study_title="",
            scrubbing=False,
            screening=False,
            sending=False,
            reviewer_count=1,
        ),
    )


def _reviewer_client(app_mod):
    """Client authenticated as a plain reviewer — no uploader, no admin.

    The `event_row` fixture assigns this reviewer (id 9) to the event: since
    spec 013 D4, an unassigned reviewer gets nothing.
    """
    app_mod.keycloak_openid = None

    def _load():
        g.auth_user = {
            "id": 9,
            "username": "test-reviewer",
            "admin": False,
            "uploader": False,
            "reviewer": True,
            "third_reviewer": False,
            "site": "TEST",
        }
        return g.auth_user

    ctx = patch.object(app_mod, "_load_user_from_remote_header", _load)
    ctx.start()
    client = app_mod.app.test_client()
    client.environ_base["HTTP_X_REMOTE_USER"] = "test-reviewer"
    return ctx, client


def test_nothing_on_disk_is_still_a_404(admin_client, downloads, event_row):
    res = admin_client.get(DOWNLOAD_URL.format(event_id=EVENT_ID))
    assert res.status_code == 404


def test_scrubbed_packet_is_served(admin_client, downloads, event_row):
    (downloads / f"{EVENT_ID}.pdf").write_bytes(b"scrubbed")

    res = admin_client.get(DOWNLOAD_URL.format(event_id=EVENT_ID))

    assert res.status_code == 200
    assert res.data == b"scrubbed"
    assert res.headers["Content-Type"].startswith("application/pdf")
    assert f'filename="{EVENT_ID}.pdf"' in res.headers["Content-Disposition"]


def test_legacy_name_is_still_served(admin_client, downloads, event_row):
    (downloads / f"event_{EVENT_ID}.zip").write_bytes(b"legacy")

    res = admin_client.get(DOWNLOAD_URL.format(event_id=EVENT_ID))

    assert res.status_code == 200
    assert res.data == b"legacy"


def test_raw_packet_is_found_by_file_number(admin_client, downloads, event_row):
    """The regression: a raw upload was previously unreachable."""
    (downloads / f"orig_{EVENT_ID}_{FILE_NUMBER}.zip").write_bytes(b"raw")

    res = admin_client.get(DOWNLOAD_URL.format(event_id=EVENT_ID))

    assert res.status_code == 200
    assert res.data == b"raw"
    assert res.headers["Content-Type"].startswith("application/zip")


def test_raw_packet_is_found_without_a_file_number(admin_client, downloads, event_row):
    """Legacy rows carry no `file_number`; the glob has to find the file."""
    event_row.file_number = None
    (downloads / f"orig_{EVENT_ID}_1.pdf").write_bytes(b"raw-legacy")

    res = admin_client.get(DOWNLOAD_URL.format(event_id=EVENT_ID))

    assert res.status_code == 200
    assert res.data == b"raw-legacy"


def test_another_events_raw_packet_is_not_served(admin_client, downloads, event_row):
    """`orig_4821_*` must not match event 482's packets, or vice versa."""
    event_row.file_number = None
    (downloads / f"orig_{EVENT_ID}9_5.zip").write_bytes(b"different event")

    res = admin_client.get(DOWNLOAD_URL.format(event_id=EVENT_ID))

    assert res.status_code == 404


def test_scrubbed_packet_wins_over_the_raw_one(admin_client, downloads, event_row):
    (downloads / f"orig_{EVENT_ID}_{FILE_NUMBER}.zip").write_bytes(b"raw")
    (downloads / f"{EVENT_ID}.zip").write_bytes(b"scrubbed")

    res = admin_client.get(DOWNLOAD_URL.format(event_id=EVENT_ID))

    assert res.status_code == 200
    assert res.data == b"scrubbed"


def test_reviewer_is_not_handed_the_unscrubbed_packet(app_mod, downloads, event_row):
    """With scrubbing in the workflow, the raw packet still carries PHI.

    Was 404 before spec 013; a present file the caller may not have is now
    403 (research D4), so the page can say why.
    """
    (downloads / f"orig_{EVENT_ID}_{FILE_NUMBER}.zip").write_bytes(b"raw")
    ctx, client = _reviewer_client(app_mod)
    try:
        with _full_workflow(app_mod):
            res = client.get(DOWNLOAD_URL.format(event_id=EVENT_ID))
    finally:
        ctx.stop()

    assert res.status_code == 403


def test_reviewer_gets_the_raw_packet_when_scrubbing_is_off(app_mod, downloads, event_row):
    """No scrub stage means the packet as submitted is the only packet."""
    (downloads / f"orig_{EVENT_ID}_{FILE_NUMBER}.zip").write_bytes(b"raw")
    ctx, client = _reviewer_client(app_mod)
    try:
        with _scans_workflow(app_mod):
            res = client.get(DOWNLOAD_URL.format(event_id=EVENT_ID))
    finally:
        ctx.stop()

    assert res.status_code == 200
    assert res.data == b"raw"


def test_reviewer_still_gets_the_scrubbed_packet(app_mod, downloads, event_row):
    (downloads / f"{EVENT_ID}.zip").write_bytes(b"scrubbed")
    ctx, client = _reviewer_client(app_mod)
    try:
        with _full_workflow(app_mod):
            res = client.get(DOWNLOAD_URL.format(event_id=EVENT_ID))
    finally:
        ctx.stop()

    assert res.status_code == 200
    assert res.data == b"scrubbed"


def test_a_database_fault_does_not_hide_a_present_file(app_mod, admin_client, downloads):
    """A failed `file_number` lookup must fall through to the glob, not 404."""
    (downloads / f"orig_{EVENT_ID}_{FILE_NUMBER}.zip").write_bytes(b"raw")
    models = importlib.import_module("flask_backend.models")

    def _boom():
        raise RuntimeError("no database")

    with patch.object(models, "get_session", _boom):
        res = admin_client.get(DOWNLOAD_URL.format(event_id=EVENT_ID))

    assert res.status_code == 200
    assert res.data == b"raw"


# --- Per-event access (spec 013, research D4) --------------------------------
#
# Observed before: any authenticated user got the scrubbed packet, and with
# scrubbing on any admin or uploader got the raw one, with no site or
# assignment check. Now access follows the event.

DENIED = {"error": "You do not have access to this event's packet"}

_CALLERS = {
    "admin": dict(id=1, admin=True),
    "same_site_uploader": dict(id=42, uploader=True, site="TEST"),
    "other_site_uploader": dict(id=43, uploader=True, site="OTHER"),
    "assigned_reviewer": dict(id=REVIEWER_ID, reviewer=True, site="OTHER"),
    "unassigned_reviewer": dict(id=10, reviewer=True, site="OTHER"),
    "no_role": dict(id=11, site="OTHER"),
}

# (caller, scrubbing, files on disk) -> expected status and body
_ACCESS = [
    # Scrubbing on, scrubbed copy present: everyone entitled gets the scrubbed copy.
    ("admin", True, "both", 200, b"scrubbed"),
    ("same_site_uploader", True, "both", 200, b"scrubbed"),
    ("other_site_uploader", True, "both", 403, None),
    ("assigned_reviewer", True, "both", 200, b"scrubbed"),
    ("unassigned_reviewer", True, "both", 403, None),
    ("no_role", True, "both", 403, None),
    # Scrubbing on, raw only: only those who may see the original.
    ("admin", True, "raw", 200, b"raw"),
    ("same_site_uploader", True, "raw", 200, b"raw"),
    ("other_site_uploader", True, "raw", 403, None),
    ("assigned_reviewer", True, "raw", 403, None),
    ("unassigned_reviewer", True, "raw", 403, None),
    ("no_role", True, "raw", 403, None),
    # Scrubbing off (scans): the raw packet is the packet.
    ("admin", False, "raw", 200, b"raw"),
    ("same_site_uploader", False, "raw", 200, b"raw"),
    ("other_site_uploader", False, "raw", 403, None),
    ("assigned_reviewer", False, "raw", 200, b"raw"),
    ("unassigned_reviewer", False, "raw", 403, None),
    ("no_role", False, "raw", 403, None),
]


@pytest.mark.parametrize("caller,scrubbing,files,status,body", _ACCESS)
def test_download_access(client_as, downloads, event_row, monkeypatch,
                         caller, scrubbing, files, status, body):
    monkeypatch.setenv("ENABLE_SCRUBBING", "true" if scrubbing else "false")
    (downloads / f"orig_{EVENT_ID}_{FILE_NUMBER}.pdf").write_bytes(b"raw")
    if files == "both":
        (downloads / f"{EVENT_ID}.pdf").write_bytes(b"scrubbed")

    res = client_as(**_CALLERS[caller]).get(DOWNLOAD_URL.format(event_id=EVENT_ID))

    assert res.status_code == status
    if body is None:
        assert res.get_json() == DENIED
    else:
        assert res.data == body


@pytest.mark.parametrize("slot", ["reviewer2_id", "reviewer3_id"])
def test_reviewer_in_another_slot_gets_the_scrubbed_copy(
    client_as, downloads, event_row, slot
):
    event_row.reviewer1_id = 99
    setattr(event_row, slot, REVIEWER_ID)
    (downloads / f"{EVENT_ID}.pdf").write_bytes(b"scrubbed")

    res = client_as(**_CALLERS["assigned_reviewer"]).get(DOWNLOAD_URL.format(event_id=EVENT_ID))

    assert res.status_code == 200
    assert res.data == b"scrubbed"


def test_missing_event_is_404(app_mod, client_as, downloads):
    (downloads / f"{EVENT_ID}.pdf").write_bytes(b"scrubbed")
    models = importlib.import_module("flask_backend.models")
    with patch.object(models, "get_session", lambda: FakeSession(None)):
        res = client_as(**_CALLERS["admin"]).get(DOWNLOAD_URL.format(event_id=EVENT_ID))

    assert res.status_code == 404


def test_no_file_is_404_even_for_a_refused_caller(client_as, downloads, event_row):
    res = client_as(**_CALLERS["no_role"]).get(DOWNLOAD_URL.format(event_id=EVENT_ID))

    assert res.status_code == 404


def test_a_database_fault_refuses_a_non_admin(client_as, downloads):
    """Access is per event, so without the event row a non-admin gets nothing."""
    (downloads / f"{EVENT_ID}.pdf").write_bytes(b"scrubbed")
    models = importlib.import_module("flask_backend.models")

    def _boom():
        raise RuntimeError("no database")

    with patch.object(models, "get_session", _boom):
        res = client_as(**_CALLERS["assigned_reviewer"]).get(DOWNLOAD_URL.format(event_id=EVENT_ID))

    assert res.status_code == 500


@pytest.mark.parametrize("caller,status", [("assigned_reviewer", 200), ("unassigned_reviewer", 403)])
def test_head_request_matches_get(client_as, downloads, event_row, caller, status):
    """The frontend checks access with HEAD before downloading."""
    (downloads / f"{EVENT_ID}.pdf").write_bytes(b"scrubbed")

    res = client_as(**_CALLERS[caller]).head(DOWNLOAD_URL.format(event_id=EVENT_ID))

    assert res.status_code == status


@pytest.mark.parametrize("study,caller,files,status", [
    # mci (scrubbing on): an assigned reviewer gets only the scrubbed copy.
    ("mci", "assigned_reviewer", "both", 200),
    ("mci", "assigned_reviewer", "raw", 403),
    ("mci", "same_site_uploader", "raw", 200),
    # scans (scrubbing off): an assigned reviewer still gets the raw packet.
    ("scans", "assigned_reviewer", "raw", 200),
    ("scans", "unassigned_reviewer", "raw", 403),
    # dischargesummaries (scrubbing on).
    ("dischargesummaries", "assigned_reviewer", "both", 200),
    ("dischargesummaries", "assigned_reviewer", "raw", 403),
    ("dischargesummaries", "other_site_uploader", "both", 403),
])
def test_download_access_by_study_profile(client_as, downloads, event_row, monkeypatch,
                                          study, caller, files, status):
    monkeypatch.delenv("ENABLE_SCRUBBING", raising=False)
    monkeypatch.setenv("STUDY_TYPE", study)
    (downloads / f"orig_{EVENT_ID}_{FILE_NUMBER}.pdf").write_bytes(b"raw")
    if files == "both":
        (downloads / f"{EVENT_ID}.pdf").write_bytes(b"scrubbed")

    res = client_as(**_CALLERS[caller]).get(DOWNLOAD_URL.format(event_id=EVENT_ID))

    assert res.status_code == status
