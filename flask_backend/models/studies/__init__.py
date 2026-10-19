"""Per-study model modules and the resolver that picks one (spec 012).

Constitution Principle IV: some tables take their shape from one study's data
needs, as in the legacy CakePHP system, where every study had a table of the
same name with its own schema. Each study models them in its own module here:

    flask_backend/models/studies/<study>.py

The matching DDL lives in `init/studies/<study>.sql`. Only `reviews` is
mandatory. Every study's `reviews` table has `id`, `event_id` and
`reviewer_id`, with one row per reviewer per event, and shared code may rely
on those three columns only. Any other read or write of review data goes
through the study module.

Study-module interface (duck-typed)
-----------------------------------

``Reviews``
    ORM class for this study's ``reviews`` table.
``REVIEW_FORM``
    Short id of the review form the frontend renders. It is reported on
    ``GET /api/config`` as ``review_form``, and keys
    ``frontend/src/components/review/reviewForms.js``.
``REVIEW_TABLES``
    Tuple of this study's tables that hold review answers. Generic table
    reads (``GET /api/tables/<name>``) refuse them to non-admins (spec 012
    FR-028).
``parse_review(data) -> dict``
    Validates a ``POST /api/events/<id>/review`` body. Raises
    :class:`ReviewValidationError` when the body is invalid.
``add_review(session, event_id, reviewer_id, parsed) -> None``
    Adds this study's rows for one review to ``session``. Does not commit.
``get_reviews(session, event_id) -> list[dict]``
    Serializes the stored reviews of one event.
``export_csv(session, writer) -> None``
    Writes this study's ``GET /api/events/export`` headings and rows to a
    ``csv.writer``.
``check_schema(session) -> str | None``
    Returns an operator-facing message if the database's study tables do not
    match this module (e.g. an un-migrated database); otherwise ``None``.

Each study module declares its **own** ``DeclarativeBase``. Several studies
define a table named ``reviews``, and the test suite imports more than one
study module in a single process, so they cannot share one metadata. As a
consequence, study models carry no ORM relationships to the shared ``Events``
and ``Users`` models; joins are written explicitly.

Dormancy
--------

A study module that exists but is not yet usable ("dormant") does not export
``REVIEW_FORM``. The vte, afib, cva and hf files are still wrapped in a
triple-quoted string, so they export nothing. For a dormant study, as for a
study with no module at all, :func:`get_study_module` falls back to ``mci``
and logs a warning. ``init/04-study-schema.sh`` applies the same rule to
schema files marked ``-- DORMANT``, so the database and the backend agree.
"""

import importlib
import logging
from types import ModuleType
from typing import Optional

from ... import study_config

logger = logging.getLogger(__name__)

# The study whose module serves any study that has none of its own, matching
# the system-wide default STUDY_TYPE and init/04-study-schema.sh's fallback.
FALLBACK_STUDY = "mci"

# Study names already warned about, so each fallback is logged once per process.
_warned: set = set()


class ReviewValidationError(Exception):
    """A review-submission body failed a study module's validation.

    ``fields`` optionally lists per-input problems as
    ``{"index": int, "field": str, "message": str}`` dicts, so the form can
    flag the specific input. ``index`` is 0-based and only meaningful for
    studies whose body is a list of entries.
    """

    def __init__(self, message: str, fields: Optional[list] = None):
        super().__init__(message)
        self.message = message
        self.fields = fields or []


def get_study_module(study_type: Optional[str] = None) -> ModuleType:
    """Return the model module for ``study_type`` (default: this deployment's).

    The study type is read at call time, like ``get_workflow_config()``, so
    tests can switch ``STUDY_TYPE`` with ``monkeypatch``. A study with no
    module, or whose module is dormant (no ``REVIEW_FORM``), resolves to the
    ``mci`` module, with a one-time warning.
    """
    name = (study_type if study_type is not None else study_config.get_study_type())
    name = (name or "").strip().lower() or FALLBACK_STUDY

    module = None
    if name.replace("_", "").isalnum():
        try:
            module = importlib.import_module(f"{__name__}.{name}")
        except ModuleNotFoundError:
            module = None

    if module is None or not hasattr(module, "REVIEW_FORM"):
        if name != FALLBACK_STUDY and name not in _warned:
            _warned.add(name)
            logger.warning(
                "No usable study module for STUDY_TYPE=%r (missing or dormant); "
                "using the %r study module instead.", name, FALLBACK_STUDY,
            )
        module = importlib.import_module(f"{__name__}.{FALLBACK_STUDY}")
    return module
