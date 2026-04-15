#!/usr/bin/env python3
"""Generate the repository's `openapi.json` from the Flask backend.

This module walks `flask_backend.app.app.url_map` and, for each route,
parses the YAML block following the `---` marker in the view function's
docstring and splats it under `paths.<url>.<method>`. Routes without a
YAML block are emitted as empty `{}` placeholders so new endpoints show
up in the diff immediately.

Run it as a module from the repo root:

    python -m flask_backend.generate_openapi

Invoked by the `update-openapi.yml` GitHub Action on every push that
touches `flask_backend/**.py`. See the root `README.md` "OpenAPI
Documentation" section and the constitution's Development Workflow &
Quality Gates "API contracts" bullet.

NOTE: the file is named `openapi.json` for historical reasons but its
on-disk format is YAML, matching what the previous generator emitted
and what downstream tooling expects. Do not silently switch it to JSON
without updating the workflow and the downstream consumers. The
response schemas in the Flask docstrings are written in Swagger 2.0
style (schema directly under a response code), not OpenAPI 3.0
(content/<media-type>/schema), and this generator preserves them
verbatim to keep the diff minimal; a full 2.0→3.0 conversion is a
separate concern.
"""

from __future__ import annotations

import os
import re
import sys
from pathlib import Path
from typing import Any, Dict

# Repo root = the directory containing the `flask_backend` package.
# Used only to locate `openapi.json` on disk; not prepended to sys.path
# because running via `python -m flask_backend.generate_openapi` already
# puts the repo root on the path for us.
REPO_ROOT = Path(__file__).resolve().parent.parent

# Provide harmless defaults for environment variables the Flask app reads
# at import time, so the generator can run in CI without a real database
# or secrets configured.
os.environ.setdefault("LOG_LEVEL", "WARNING")
os.environ.setdefault("LOG_FORMAT", "TEXT")
os.environ.setdefault("FRONTEND_ORIGIN", "http://localhost:3000")

import yaml  # noqa: E402

from flask_backend.app import app  # noqa: E402


# Flask <converter:name> rule syntax → OpenAPI {converter:name} syntax.
# Preserving the converter prefix (`int:`, `string:`, etc.) matches the
# legacy generator's output so the diff on each regeneration stays
# minimal; downstream tooling that cares about the converter prefix is
# expected to strip it itself.
_CONVERTER_RE = re.compile(r"<([^>]+)>")


def _flask_rule_to_openapi_path(rule: str) -> str:
    """Turn Flask rule syntax like `/api/events/<int:event_id>` into
    legacy-compatible `/api/events/{int:event_id}`."""
    return _CONVERTER_RE.sub(lambda m: "{" + m.group(1) + "}", rule)


def _stringify_response_codes(node: Any) -> Any:
    """Recursively convert integer response-code keys (e.g. 200) into
    quoted strings (`'200'`) so the emitted YAML matches the legacy
    generator's output. Only `responses:` mappings are touched — other
    integer-keyed mappings are left alone."""
    if isinstance(node, dict):
        new_dict: Dict[Any, Any] = {}
        for k, v in node.items():
            if k == "responses" and isinstance(v, dict):
                new_dict[k] = {
                    str(code): _stringify_response_codes(body)
                    for code, body in v.items()
                }
            else:
                new_dict[k] = _stringify_response_codes(v)
        return new_dict
    if isinstance(node, list):
        return [_stringify_response_codes(item) for item in node]
    return node


def _parse_docstring_yaml(doc: str | None) -> Dict[str, Any]:
    """Extract the YAML block after a `---` marker in a view's docstring.

    Returns an empty dict if the docstring is missing, lacks a `---`
    marker, or fails to parse as YAML. A parse failure prints a warning
    and returns `{}` so one bad docstring doesn't block the whole spec.
    """
    if not doc:
        return {}
    if "---" not in doc:
        return {}
    _, _, yaml_block = doc.partition("---")
    try:
        parsed = yaml.safe_load(yaml_block)
    except yaml.YAMLError as exc:
        print(f"warning: YAML parse error in view docstring: {exc}", file=sys.stderr)
        return {}
    return parsed or {}


def build_spec() -> Dict[str, Any]:
    paths: Dict[str, Any] = {}

    for rule in app.url_map.iter_rules():
        # Skip Flask's auto-registered static endpoint — it is not part
        # of the API surface. Keep everything else.
        if rule.endpoint == "static":
            continue

        openapi_path = _flask_rule_to_openapi_path(rule.rule)
        view_func = app.view_functions.get(rule.endpoint)
        parsed = _parse_docstring_yaml(view_func.__doc__ if view_func else None)

        # Methods for this rule, excluding HEAD and OPTIONS which Flask
        # adds automatically and which the legacy openapi.json did not
        # emit.
        methods = [m for m in (rule.methods or set()) if m not in {"HEAD", "OPTIONS"}]

        # Preserve insertion order so stable paths stay in a stable
        # spot in the emitted file and the diff is minimal on reruns.
        entry = paths.setdefault(openapi_path, {})

        if not parsed:
            # No YAML block in the docstring → leave as empty placeholder
            # (matches the legacy generator's behavior for unannotated
            # routes).
            continue

        for method in methods:
            method_key = method.lower()
            # If the docstring YAML looks like an operation object
            # (has `responses:` or `parameters:` at its top level),
            # attach it to every method this rule handles. If it looks
            # like a paths-keyed object (already method-scoped), splat
            # it directly.
            if any(k in parsed for k in ("responses", "parameters", "requestBody", "summary", "description", "tags")):
                entry[method_key] = parsed
            elif method_key in parsed:
                entry[method_key] = parsed[method_key]

    spec: Dict[str, Any] = {
        "paths": _stringify_response_codes(paths),
        "info": {
            "title": "CNICS Validation API",
            "version": "1.0.0",
        },
        "openapi": "3.0.3",
    }
    return spec


def main() -> int:
    spec = build_spec()
    out_path = REPO_ROOT / "openapi.json"
    with out_path.open("w") as f:
        yaml.safe_dump(spec, f, sort_keys=False)
    print(f"wrote {out_path}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
