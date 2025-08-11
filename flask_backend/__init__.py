import os

# Select which SQLAlchemy models module to expose as `flask_backend.models`.
# Avoid importing the Flask app here to keep package import lightweight
# (prevents optional dependencies like Keycloak from being imported when not needed).
variant = os.getenv("SQLA_MODELS", "models").strip().lower()
if variant in {"models2", "no_back_populates", "alt", "m2"}:
    from . import models2 as models  # type: ignore
else:
    from . import models  # type: ignore

__all__ = ["models"]
