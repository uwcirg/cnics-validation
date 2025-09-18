import logging
import os
import sys
import datetime
import json
from typing import Any, Optional

try:
    # Prefer python-json-logger if available
    from pythonjsonlogger import jsonlogger
except Exception:  # pragma: no cover - dependency controlled by requirements
    jsonlogger = None  # type: ignore


class FlaskRequestContextFilter(logging.Filter):
    """Inject Flask request context fields into each log record when present.

    Adds: request_id, method, path, remote_ip, user_login, site
    """

    def filter(self, record: logging.LogRecord) -> bool:  # noqa: D401
        try:
            # Lazy import to avoid hard dependency outside request context
            from flask import has_request_context, request, g

            if has_request_context():
                # Request ID may be set by app before_request
                request_id = getattr(g, "request_id", None) or request.headers.get("X-Request-ID")
                setattr(record, "request_id", request_id)

                # Basic request metadata
                setattr(record, "method", getattr(request, "method", None))
                # Use full_path to include query string (ends with '?' if no query)
                path_value = getattr(request, "full_path", None) or getattr(request, "path", None)
                if isinstance(path_value, str) and path_value.endswith("?"):
                    path_value = path_value[:-1]
                setattr(record, "path", path_value)

                # Remote IP with X-Forwarded-For support
                xff = request.headers.get("X-Forwarded-For")
                if xff:
                    remote_ip = xff.split(",")[0].strip()
                else:
                    remote_ip = getattr(request, "remote_addr", None)
                setattr(record, "remote_ip", remote_ip)

                # User details if populated on g by the app
                auth_user = getattr(g, "auth_user", None) or {}
                setattr(record, "user_login", auth_user.get("username") or auth_user.get("login"))
                setattr(record, "site", auth_user.get("site"))
        except Exception:
            # Never break logging on filter errors
            pass
        return True


class IsoTimeJsonFormatter(jsonlogger.JsonFormatter if jsonlogger else logging.Formatter):
    """JSON formatter producing ISO8601 timestamps and consistent field names.

    Falls back to a plain Formatter if python-json-logger is unavailable.
    """

    def __init__(self, app_name: str):
        if jsonlogger:
            super().__init__(
                fmt=(
                    "%(asctime)s %(levelname)s %(name)s %(module)s %(funcName)s %(lineno)d "
                    "%(message)s %(request_id)s %(method)s %(path)s %(status)s %(duration_ms)s "
                    "%(remote_ip)s %(user_login)s %(site)s %(app)s"
                ),
                rename_fields={
                    "asctime": "ts",
                    "levelname": "level",
                    "name": "logger",
                    "module": "module",
                    "funcName": "func",
                    "lineno": "line",
                    "message": "msg",
                },
            )
        else:
            super().__init__("%(asctime)s %(levelname)s %(name)s - %(message)s")
        self.app_name = app_name

    def add_fields(self, log_record: dict, record: logging.LogRecord, message_dict: dict) -> None:  # type: ignore[override]
        # Only available when using python-json-logger
        if not jsonlogger:
            return
        super().add_fields(log_record, record, message_dict)  # type: ignore[misc]
        # Ensure timestamp is ISO8601 with timezone Z
        if not log_record.get("ts"):
            now = datetime.datetime.now(datetime.timezone.utc)
            log_record["ts"] = now.isoformat()
        # Ensure app name present (do not leave null)
        if log_record.get("app") in (None, ""):
            log_record["app"] = self.app_name
        # Backfill defaults for structured keys when absent
        for key, default in (
            ("request_id", None),
            ("method", None),
            ("path", None),
            ("status", None),
            ("duration_ms", None),
            ("remote_ip", None),
            ("user_login", None),
            ("site", None),
        ):
            if key not in log_record:
                log_record.setdefault(key, default)

    def formatTime(self, record: logging.LogRecord, datefmt: Optional[str] = None) -> str:  # noqa: N802
        # For python-json-logger, asctime will be renamed to ts
        now = datetime.datetime.fromtimestamp(record.created, tz=datetime.timezone.utc)
        return now.isoformat()


class FallbackJsonFormatter(logging.Formatter):
    """JSON formatter used when python-json-logger is unavailable.

    Produces the same schema as IsoTimeJsonFormatter:
    ts, level, logger, module, func, line, msg, request fields, app, plus extras.
    """

    def __init__(self, app_name: str):
        super().__init__()
        self.app_name = app_name
        # Standard logging attributes to exclude from extras
        self._standard_attrs = {
            "name",
            "msg",
            "args",
            "levelname",
            "levelno",
            "pathname",
            "filename",
            "module",
            "exc_info",
            "exc_text",
            "stack_info",
            "lineno",
            "funcName",
            "created",
            "msecs",
            "relativeCreated",
            "thread",
            "threadName",
            "processName",
            "process",
            "message",
        }

    def format(self, record: logging.LogRecord) -> str:  # noqa: D401
        ts = datetime.datetime.fromtimestamp(record.created, tz=datetime.timezone.utc).isoformat()
        log_record: dict[str, Any] = {
            "ts": ts,
            "level": record.levelname,
            "logger": record.name,
            "module": record.module,
            "func": record.funcName,
            "line": record.lineno,
            "msg": record.getMessage(),
            "request_id": getattr(record, "request_id", None),
            "method": getattr(record, "method", None),
            "path": getattr(record, "path", None),
            "status": getattr(record, "status", None),
            "duration_ms": getattr(record, "duration_ms", None),
            "remote_ip": getattr(record, "remote_ip", None),
            "user_login": getattr(record, "user_login", None),
            "site": getattr(record, "site", None),
            "app": self.app_name,
        }

        # Include any extra fields passed via logger(..., extra={...})
        for key, value in record.__dict__.items():
            if key.startswith("_"):
                continue
            if key in log_record or key in self._standard_attrs:
                continue
            try:
                json.dumps(value)
                log_record[key] = value
            except Exception:
                # Fallback to string representation for non-serializable extras
                log_record[key] = str(value)

        if record.exc_info:
            log_record["exc_info"] = self.formatException(record.exc_info)
        if record.stack_info:
            # record.stack_info is already a formatted string
            log_record["stack_info"] = record.stack_info

        return json.dumps(log_record, ensure_ascii=False)

def _determine_level(default: str = "INFO") -> int:
    level_str = os.getenv("LOG_LEVEL", default).upper()
    return getattr(logging, level_str, logging.INFO)


def configure_logging(app_name: Optional[str] = None) -> None:
    """Configure root logging to stdout in JSON or plain text.

    Environment variables:
    - LOG_FORMAT: 'JSON' (default) or 'TEXT'
    - LOG_LEVEL: python level name, default INFO
    - APP_NAME: application name to include in logs
    """
    app_val = app_name or os.getenv("APP_NAME", "cnics-validation-backend")
    fmt = (os.getenv("LOG_FORMAT", "JSON") or "JSON").upper()
    level = _determine_level()

    root = logging.getLogger()
    # Reset existing handlers to avoid duplicate logs on reloads
    for h in list(root.handlers):
        root.removeHandler(h)

    root.setLevel(level)

    handler = logging.StreamHandler(sys.stdout)
    handler.setLevel(level)
    handler.addFilter(FlaskRequestContextFilter())

    if fmt == "JSON":
        if jsonlogger is not None:
            handler.setFormatter(IsoTimeJsonFormatter(app_val))
        else:
            handler.setFormatter(FallbackJsonFormatter(app_val))
    else:
        # Plain text fallback
        handler.setFormatter(logging.Formatter("%(asctime)s %(levelname)s %(name)s - %(message)s"))

    root.addHandler(handler)

    # Ensure framework loggers propagate to root and do not keep their own handlers
    for lname, lvl in (
        ("flask", level),
        ("flask.app", level),
        ("werkzeug", logging.WARNING),
    ):
        lg = logging.getLogger(lname)
        for h in list(lg.handlers):
            lg.removeHandler(h)
        lg.propagate = True
        lg.setLevel(lvl)

    # Reduce noisy SQL logs
    sqla = logging.getLogger("sqlalchemy.engine")
    for h in list(sqla.handlers):
        sqla.removeHandler(h)
    sqla.propagate = True
    sqla.setLevel(logging.WARNING)


