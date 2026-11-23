"""Tests for the per-study model modules and their resolver (spec 012, research D4)."""

import importlib
import logging

import pytest

from flask_backend import models
from flask_backend.models import studies


def test_models_is_a_package():
    # models.py used to shadow models/, making models/studies unimportable.
    assert hasattr(models, "__path__")


@pytest.mark.parametrize("value", [None, "", "mci", "  MCI  "])
def test_resolver_mci(monkeypatch, value):
    if value is None:
        monkeypatch.delenv("STUDY_TYPE", raising=False)
    else:
        monkeypatch.setenv("STUDY_TYPE", value)
    assert studies.get_study_module().__name__ == "flask_backend.models.studies.mci"


@pytest.mark.parametrize("study", ["vte", "nonexistent"])
def test_resolver_falls_back_to_mci_with_warning(monkeypatch, caplog, study):
    monkeypatch.setenv("STUDY_TYPE", study)
    monkeypatch.setattr(studies, "_warned", set())
    with caplog.at_level(logging.WARNING, logger=studies.__name__):
        module = studies.get_study_module()
    assert module.REVIEW_FORM == "mci"
    assert any(study in r.getMessage() for r in caplog.records)


def test_resolver_rejects_path_like_names():
    assert studies.get_study_module("../app").REVIEW_FORM == "mci"


def test_study_modules_import_together():
    mci = importlib.import_module("flask_backend.models.studies.mci")
    scans = importlib.import_module("flask_backend.models.studies.scans")
    assert mci.Reviews.__table__ is not scans.Reviews.__table__
    assert scans.REVIEW_FORM == "scans"


_INTERFACE = (
    "Reviews", "REVIEW_FORM", "REVIEW_TABLES", "parse_review", "add_review",
    "get_reviews", "export_csv", "check_schema",
)


def test_resolver_dischargesummaries_has_its_own_module(monkeypatch, caplog):
    """dischargesummaries resolves to its own module, with no mci fallback (spec 013 D5)."""
    monkeypatch.setenv("STUDY_TYPE", "dischargesummaries")
    monkeypatch.setattr(studies, "_warned", set())
    with caplog.at_level(logging.WARNING, logger=studies.__name__):
        module = studies.get_study_module()
    assert module.__name__ == "flask_backend.models.studies.dischargesummaries"
    assert module.REVIEW_FORM == "dischargesummaries"
    assert not caplog.records
    for name in _INTERFACE:
        assert hasattr(module, name), name


def test_dischargesummaries_phase_a_refuses_reviews():
    """Phase A: every submission is refused, and there is nothing to read or check."""
    ds = importlib.import_module("flask_backend.models.studies.dischargesummaries")
    with pytest.raises(studies.ReviewValidationError) as exc:
        ds.parse_review({"anything": 1})
    assert exc.value.message == "The discharge-summary review form is not available yet."
    assert ds.get_reviews(None, 1) == []
    assert ds.check_schema(None) is None
    assert ds.REVIEW_TABLES == ("reviews",)


def test_dischargesummaries_imports_beside_other_studies():
    mci = importlib.import_module("flask_backend.models.studies.mci")
    scans = importlib.import_module("flask_backend.models.studies.scans")
    ds = importlib.import_module("flask_backend.models.studies.dischargesummaries")
    tables = {mci.Reviews.__table__, scans.Reviews.__table__, ds.Reviews.__table__}
    assert len(tables) == 3
