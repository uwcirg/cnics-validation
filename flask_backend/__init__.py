import os

# Select which SQLAlchemy models module to expose as `flask_backend.models`
#
# Set env var `SQLA_MODELS` to one of:
#   - "models" (default): uses `flask_backend/models.py` with back_populates
#   - "models2": uses `flask_backend/models2.py` without back_populates
variant = os.getenv("SQLA_MODELS", "models").strip().lower()
if variant in {"models2", "no_back_populates", "alt", "m2"}:
    from . import models2 as models  # type: ignore
else:
    from . import models  # type: ignore

from .app import app

__all__ = ["app", "models"]
