import os
import smtplib
import socket
import datetime
from email.message import EmailMessage
from email.utils import formatdate, make_msgid
from typing import Optional, List, Dict, Any

from . import models
import logging

logger = logging.getLogger(__name__)


def _get_downloads_dir() -> str:
    # Prefer UPLOAD_DIR (alias requested), then DOWNLOADS_DIR, else default under FILES_DIR
    downloads_dir = os.getenv("UPLOAD_DIR") or os.getenv("DOWNLOADS_DIR")
    if downloads_dir:
        return downloads_dir
    files_dir = os.getenv("FILES_DIR")
    if not files_dir:
        files_dir = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "app", "webroot", "files"))
    return os.path.join(files_dir, "downloads")


def _looks_like_email(value: Optional[str]) -> bool:
    if not value:
        return False
    s = value.strip()
    return "@" in s and "." in s.split("@")[-1]


def _resolve_recipient_address(user: models.Users) -> Optional[str]:
    # Prefer username if it looks like an email, else login if it looks like an email
    if _looks_like_email(getattr(user, "username", None)):
        return str(user.username).strip()
    if _looks_like_email(getattr(user, "login", None)):
        return str(user.login).strip()
    return None


def _get_origins() -> Dict[str, str]:
    # Prefer explicit BACKEND_ORIGIN for API links; fall back to FRONTEND_ORIGIN; else a sane default
    frontend_origin = (os.getenv("FRONTEND_ORIGIN") or "https://cnics-validation.pm.ssingh20.dev.cirg.uw.edu").rstrip("/")
    backend_origin = (os.getenv("BACKEND_ORIGIN") or frontend_origin).rstrip("/")
    return {"frontend": frontend_origin, "backend": backend_origin}


def _build_urls(event_id: int) -> Dict[str, str]:
    origins = _get_origins()
    return {
        "download": f"{origins['backend']}/api/events/download/{event_id}",
        "review": f"{origins['frontend']}/events/review?event_id={event_id}",
        "index": f"{origins['frontend']}/events/viewAll",
    }


def _format_subject(event_id: int) -> str:
    prefix = os.getenv("EMAIL_SUBJECT_PREFIX", "CNICS / NA-ACCORD")
    return f"{prefix} MI Review Assignment – Event {event_id}"


def _format_signature() -> str:
    help_addr = os.getenv("EMAIL_HELP_ADDRESS") or os.getenv("EMAIL_REPLY_TO") or os.getenv("EMAIL_FROM") or "cnics-help@uw.edu"
    sig_lines = [
        "Thank you,",
        "CNICS/NA-ACCORD Team",
        f"Help: {help_addr}",
    ]
    return "\r\n".join(sig_lines)


def _build_body(first_name: str, last_name: str, urls: Dict[str, str]) -> str:
    # Emulate legacy style text email (CRLF newlines)
    parts: List[str] = []
    parts.append(f"Dear {first_name} {last_name}, \r\n")
    parts.append(
        "You have been assigned a Myocardial Infarction (MI) review.\r\n"
        "Please download the charts and complete the review at the links below.\r\n"
    )
    parts.append("")
    parts.append(urls["download"])  # download link
    parts.append("")
    parts.append("After reviewing the packet, submit your decision here:")
    parts.append(urls["review"])  # review link
    parts.append("")
    parts.append("You can also view all events here:")
    parts.append(urls["index"])  # index link
    parts.append("")
    parts.append(_format_signature())
    return "\r\n".join(parts) + "\r\n"


def _find_attachment_path(event_id: int) -> Optional[str]:
    downloads_dir = _get_downloads_dir()
    candidates: List[str] = []
    for ext in (".zip", ".pdf", ".doc", ".docx"):
        candidates.append(os.path.join(downloads_dir, f"{event_id}{ext}"))
    candidates.append(os.path.join(downloads_dir, f"event_{event_id}.zip"))
    for path in candidates:
        if os.path.exists(path):
            return path
    return None


def _build_message(to_addr: str, subject: str, body: str, attachment_path: Optional[str]) -> EmailMessage:
    msg = EmailMessage()
    from_addr = os.getenv("EMAIL_FROM", f"no-reply@{socket.getfqdn()}")
    reply_to = os.getenv("EMAIL_REPLY_TO") or os.getenv("EMAIL_HELP_ADDRESS")
    msg["From"] = from_addr
    msg["To"] = to_addr
    msg["Date"] = formatdate(localtime=True)
    msg["Message-ID"] = make_msgid()
    if reply_to:
        msg["Reply-To"] = reply_to
    msg["Subject"] = subject

    attach_packet = os.getenv("EMAIL_ATTACH_PACKET", "0").strip() in {"1", "true", "True", "YES", "yes"}
    if attach_packet and attachment_path and os.path.exists(attachment_path):
        msg.set_content(body)
        with open(attachment_path, "rb") as f:
            data = f.read()
        import mimetypes
        mime_type, _ = mimetypes.guess_type(attachment_path)
        maintype, subtype = (mime_type.split("/", 1) if mime_type else ("application", "octet-stream"))
        msg.add_attachment(data, maintype=maintype, subtype=subtype, filename=os.path.basename(attachment_path))
    else:
        msg.set_content(body)
    return msg


def _send_via_smtp(msg: EmailMessage) -> Optional[str]:
    host = os.getenv("SMTP_HOST", "localhost")
    port_str = os.getenv("SMTP_PORT", "25")
    tls = (os.getenv("SMTP_TLS", "0").strip() in {"1", "true", "True", "YES", "yes"})
    ssl = (os.getenv("SMTP_SSL", "0").strip() in {"1", "true", "True", "YES", "yes"})
    # Fall back to EMAIL_FROM for SMTP auth username when not explicitly provided
    user = os.getenv("SMTP_USER") or os.getenv("EMAIL_FROM")
    password = os.getenv("SMTP_PASSWORD")

    try:
        port = int(port_str)
    except Exception:
        port = 25

    try:
        if ssl:
            server = smtplib.SMTP_SSL(host, port, timeout=15)
        else:
            server = smtplib.SMTP(host, port, timeout=15)
        try:
            server.ehlo()
            if tls and not ssl:
                server.starttls()
                server.ehlo()
            if user and password:
                server.login(user, password)
            server.send_message(msg)
        finally:
            try:
                server.quit()
            except Exception:
                pass
        return None
    except Exception as exc:
        return str(exc)


def send_assignment_emails_for_event_ids(event_ids: List[int], sender_id: Optional[int] = None) -> Dict[str, Any]:
    """Send assignment emails to reviewer 1 and 2 for each event ID.

    Returns a summary dict: { attempted, sent, skipped, errors: [...], details: [...] }.
    """
    if not event_ids:
        return {"attempted": 0, "sent": 0, "skipped": 0, "errors": [], "details": []}

    # Test mode: don't actually send; return what would be sent
    test_mode = os.getenv("EMAIL_TEST_MODE", "0").strip() in {"1", "true", "True", "YES", "yes"}

    session = models.get_session()
    try:
        results: Dict[str, Any] = {"attempted": 0, "sent": 0, "skipped": 0, "errors": [], "details": []}
        for eid in event_ids:
            e = session.query(models.Events).get(int(eid))
            if e is None:
                results["errors"].append(f"Event {eid} not found")
                continue

            urls = _build_urls(int(e.id))
            reviewer_pairs = []  # list of tuples (slot, user)
            if getattr(e, "reviewer1_id", None):
                u1 = session.query(models.Users).get(int(e.reviewer1_id))
                if u1 is not None:
                    reviewer_pairs.append((1, u1))
            if getattr(e, "reviewer2_id", None):
                u2 = session.query(models.Users).get(int(e.reviewer2_id))
                if u2 is not None:
                    reviewer_pairs.append((2, u2))

            if not reviewer_pairs:
                results["skipped"] += 1
                results["details"].append({"event_id": int(e.id), "skipped": True, "reason": "no reviewers assigned"})
                try:
                    logger.info(
                        "email_skipped",
                        extra={
                            "event_id": int(e.id),
                            "reason": "no_reviewers_assigned",
                        },
                    )
                except Exception:
                    pass
                continue

            for slot, user in reviewer_pairs:
                to_addr = _resolve_recipient_address(user)
                if not to_addr:
                    results["errors"].append(f"Event {e.id}: reviewer {slot} has no email address")
                    results["details"].append({"event_id": int(e.id), "slot": slot, "recipient": None, "error": "no email"})
                    continue

                subject = _format_subject(int(e.id))
                body = _build_body(user.first_name or "", user.last_name or "", urls)
                attach_path = _find_attachment_path(int(e.id))
                msg = _build_message(to_addr, subject, body, attach_path)

                results["attempted"] += 1
                if test_mode:
                    results["sent"] += 1
                    results["details"].append({
                        "event_id": int(e.id),
                        "slot": slot,
                        "recipient": to_addr,
                        "subject": subject,
                        "body_preview": body[:200],
                        "attached": bool(attach_path),
                        "testing": True,
                    })
                    try:
                        logger.info(
                            "email_sent_test",
                            extra={
                                "event_id": int(e.id),
                                "recipient": to_addr,
                                "slot": slot,
                                "attached": bool(attach_path),
                                "test_mode": True,
                            },
                        )
                    except Exception:
                        pass
                else:
                    err = _send_via_smtp(msg)
                    if err is None:
                        results["sent"] += 1
                        results["details"].append({
                            "event_id": int(e.id),
                            "slot": slot,
                            "recipient": to_addr,
                            "subject": subject,
                            "attached": bool(attach_path),
                        })
                        try:
                            logger.info(
                                "email_sent",
                                extra={
                                    "event_id": int(e.id),
                                    "recipient": to_addr,
                                    "slot": slot,
                                    "attached": bool(attach_path),
                                    "test_mode": False,
                                },
                            )
                        except Exception:
                            pass
                    else:
                        results["errors"].append(f"Event {e.id}: email to {to_addr} failed: {err}")
                        results["details"].append({
                            "event_id": int(e.id),
                            "slot": slot,
                            "recipient": to_addr,
                            "subject": subject,
                            "attached": bool(attach_path),
                            "error": err,
                        })
                        try:
                            logger.warning(
                                "email_failed",
                                extra={
                                    "event_id": int(e.id),
                                    "recipient": to_addr,
                                    "slot": slot,
                                    "attached": bool(attach_path),
                                    "error": str(err),
                                    "test_mode": False,
                                },
                            )
                        except Exception:
                            pass
        return results
    finally:
        session.close()



def send_third_reviewer_emails_for_event_ids(event_ids: List[int]) -> Dict[str, Any]:
    """Send assignment emails to the third reviewer for each event ID.

    Returns a summary dict: { attempted, sent, skipped, errors: [...], details: [...] }.
    """
    if not event_ids:
        return {"attempted": 0, "sent": 0, "skipped": 0, "errors": [], "details": []}

    test_mode = os.getenv("EMAIL_TEST_MODE", "0").strip() in {"1", "true", "True", "YES", "yes"}

    session = models.get_session()
    try:
        results: Dict[str, Any] = {"attempted": 0, "sent": 0, "skipped": 0, "errors": [], "details": []}
        for eid in event_ids:
            e = session.query(models.Events).get(int(eid))
            if e is None:
                results["errors"].append(f"Event {eid} not found")
                continue

            if not getattr(e, "reviewer3_id", None):
                results["skipped"] += 1
                results["details"].append({"event_id": int(eid), "skipped": True, "reason": "no third reviewer assigned"})
                try:
                    logger.info(
                        "email_skipped",
                        extra={
                            "event_id": int(eid),
                            "reason": "no_third_reviewer_assigned",
                        },
                    )
                except Exception:
                    pass
                continue

            user = session.query(models.Users).get(int(e.reviewer3_id))
            if user is None:
                results["errors"].append(f"Event {eid}: third reviewer user not found")
                results["details"].append({"event_id": int(eid), "slot": 3, "recipient": None, "error": "user not found"})
                continue

            to_addr = _resolve_recipient_address(user)
            if not to_addr:
                results["errors"].append(f"Event {eid}: third reviewer has no email address")
                results["details"].append({"event_id": int(eid), "slot": 3, "recipient": None, "error": "no email"})
                continue

            urls = _build_urls(int(e.id))
            subject = _format_subject(int(e.id))
            body = _build_body(user.first_name or "", user.last_name or "", urls)
            attach_path = _find_attachment_path(int(e.id))
            msg = _build_message(to_addr, subject, body, attach_path)

            results["attempted"] += 1
            if test_mode:
                results["sent"] += 1
                results["details"].append({
                    "event_id": int(e.id),
                    "slot": 3,
                    "recipient": to_addr,
                    "subject": subject,
                    "body_preview": body[:200],
                    "attached": bool(attach_path),
                    "testing": True,
                })
                try:
                    logger.info(
                        "email_sent_test",
                        extra={
                            "event_id": int(e.id),
                            "recipient": to_addr,
                            "slot": 3,
                            "attached": bool(attach_path),
                            "test_mode": True,
                        },
                    )
                except Exception:
                    pass
            else:
                err = _send_via_smtp(msg)
                if err is None:
                    results["sent"] += 1
                    results["details"].append({
                        "event_id": int(e.id),
                        "slot": 3,
                        "recipient": to_addr,
                        "subject": subject,
                        "attached": bool(attach_path),
                    })
                    try:
                        logger.info(
                            "email_sent",
                            extra={
                                "event_id": int(e.id),
                                "recipient": to_addr,
                                "slot": 3,
                                "attached": bool(attach_path),
                                "test_mode": False,
                            },
                        )
                    except Exception:
                        pass
                else:
                    results["errors"].append(f"Event {e.id}: email to {to_addr} failed: {err}")
                    results["details"].append({
                        "event_id": int(e.id),
                        "slot": 3,
                        "recipient": to_addr,
                        "subject": subject,
                        "attached": bool(attach_path),
                        "error": err,
                    })
                    try:
                        logger.warning(
                            "email_failed",
                            extra={
                                "event_id": int(e.id),
                                "recipient": to_addr,
                                "slot": 3,
                                "attached": bool(attach_path),
                                "error": str(err),
                                "test_mode": False,
                            },
                        )
                    except Exception:
                        pass
        return results
    finally:
        session.close()

