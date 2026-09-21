#!/usr/bin/env python3
"""Upload and apply a signed PlayNexus deployment package over HTTPS.

The script deliberately reuses PLAYNEXUS_CONTENT_AGENT_TOKEN. It never prints
that token or the temporary Laravel maintenance bypass secret.
"""

from __future__ import annotations

import hashlib
import hmac
import http.cookiejar
import json
import math
import mimetypes
import os
import pathlib
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid


API_BASE = os.getenv(
    "PLAYNEXUS_DEPLOY_API_URL",
    "https://playnexus.ir/api/deployment-agent",
).rstrip("/")
ROOT_TOKEN = os.getenv("PLAYNEXUS_CONTENT_AGENT_TOKEN", "").strip()
DEPLOY_TOKEN = (
    hmac.new(
        ROOT_TOKEN.encode("utf-8"),
        b"playnexus/deployment-auth/v1",
        hashlib.sha256,
    ).hexdigest()
    if ROOT_TOKEN
    else ""
)
SOURCE_SHA = os.getenv("GITHUB_SHA", "").strip().lower()
SOURCE_REF = os.getenv("GITHUB_REF", "").strip()
RUN_ID = os.getenv("GITHUB_RUN_ID", "").strip()
CHUNK_SIZE = 1024 * 1024
MAX_RETRIES = 4
TERMINAL = {"completed", "rolled_back"}


def die(message: str) -> "NoReturn":
    print(f"::error::{message}", file=sys.stderr)
    raise SystemExit(1)


if not ROOT_TOKEN:
    die("PLAYNEXUS_CONTENT_AGENT_TOKEN is missing.")
if SOURCE_REF != "refs/heads/main":
    die(f"Refusing deployment from non-main ref: {SOURCE_REF or '<empty>'}")
if len(SOURCE_SHA) != 40 or any(ch not in "0123456789abcdef" for ch in SOURCE_SHA):
    die("GITHUB_SHA must be a full 40-character hexadecimal commit SHA.")
if not RUN_ID.isdigit():
    die("GITHUB_RUN_ID is invalid.")

cookies = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookies))


def _decode_response(response: urllib.response.addinfourl) -> dict:
    raw = response.read()
    if not raw:
        return {}
    try:
        parsed = json.loads(raw.decode("utf-8"))
    except Exception as exc:
        status = getattr(response, "status", "unknown")
        content_type = response.headers.get("Content-Type", "unknown")
        raise RuntimeError(
            "PlayNexus returned a non-JSON response "
            f"(HTTP {status}, Content-Type: {content_type})."
        ) from exc
    if not isinstance(parsed, dict):
        raise RuntimeError("PlayNexus returned an unexpected JSON payload.")
    return parsed


def _error_message(exc: urllib.error.HTTPError) -> str:
    try:
        raw = exc.read().decode("utf-8", errors="replace")
        payload = json.loads(raw)
        if isinstance(payload, dict):
            for key in ("message", "error"):
                value = payload.get(key)
                if isinstance(value, str) and value.strip():
                    return value.strip()
    except Exception:
        pass
    return f"HTTP {exc.code} {exc.reason}"


def request_json(
    method: str,
    path: str,
    *,
    json_body: dict | None = None,
    multipart: tuple[dict[str, str], str, bytes] | None = None,
) -> dict:
    url = f"{API_BASE}/{path.lstrip('/')}"
    headers = {
        "Accept": "application/json",
        "Authorization": f"Bearer {DEPLOY_TOKEN}",
        "User-Agent": "PlayNexus-GitHub-Deploy/1",
        "X-PlayNexus-Deploy-SHA": SOURCE_SHA,
        "X-PlayNexus-Deploy-Run": RUN_ID,
    }

    if multipart is not None:
        fields, filename, file_bytes = multipart
        boundary = f"----PlayNexusDeploy{uuid.uuid4().hex}"
        body = bytearray()
        for key, value in fields.items():
            body.extend(f"--{boundary}\r\n".encode())
            body.extend(
                f'Content-Disposition: form-data; name="{key}"\r\n\r\n'.encode()
            )
            body.extend(str(value).encode("utf-8"))
            body.extend(b"\r\n")

        mime = mimetypes.guess_type(filename)[0] or "application/octet-stream"
        body.extend(f"--{boundary}\r\n".encode())
        body.extend(
            (
                'Content-Disposition: form-data; name="chunk"; '
                f'filename="{pathlib.Path(filename).name}"\r\n'
            ).encode()
        )
        body.extend(f"Content-Type: {mime}\r\n\r\n".encode())
        body.extend(file_bytes)
        body.extend(b"\r\n")
        body.extend(f"--{boundary}--\r\n".encode())
        data = bytes(body)
        headers["Content-Type"] = f"multipart/form-data; boundary={boundary}"
    elif json_body is not None:
        data = json.dumps(json_body, separators=(",", ":")).encode("utf-8")
        headers["Content-Type"] = "application/json"
    else:
        data = None

    last_error: Exception | None = None
    for attempt in range(MAX_RETRIES):
        request = urllib.request.Request(url, data=data, headers=headers, method=method)
        try:
            with opener.open(request, timeout=180) as response:
                return _decode_response(response)
        except urllib.error.HTTPError as exc:
            message = _error_message(exc)
            if exc.code == 404:
                raise RuntimeError(
                    "Deployment API is not available on production yet. "
                    "Complete the one-time bootstrap deployment first."
                ) from exc
            if exc.code not in {408, 425, 429, 500, 502, 503, 504}:
                raise RuntimeError(message) from exc
            last_error = RuntimeError(message)
        except (urllib.error.URLError, TimeoutError, OSError) as exc:
            last_error = exc

        if attempt + 1 < MAX_RETRIES:
            time.sleep(2 ** attempt)

    raise RuntimeError(f"Request failed after retries: {last_error}")


def establish_maintenance_bypass(secret: str) -> None:
    if not secret or "/" in secret or len(secret) > 128:
        raise RuntimeError("Invalid maintenance bypass value returned by server.")

    parsed = urllib.parse.urlparse(API_BASE)
    url = f"{parsed.scheme}://{parsed.netloc}/{secret}"
    request = urllib.request.Request(
        url,
        headers={
            "Accept": "text/html,*/*",
            "User-Agent": "PlayNexus-GitHub-Deploy/1",
        },
        method="GET",
    )
    with opener.open(request, timeout=60) as response:
        response.read(1024)


def public_health(path: str, expected_kind: str | None = None) -> None:
    parsed = urllib.parse.urlparse(API_BASE)
    url = f"{parsed.scheme}://{parsed.netloc}{path}"
    last_error: Exception | None = None

    for attempt in range(MAX_RETRIES):
        accept = "*/*"
        if expected_kind == "html":
            accept = "text/html,application/xhtml+xml"
        elif expected_kind == "json":
            accept = "application/json"

        request = urllib.request.Request(
            url,
            headers={
                "Accept": accept,
                "User-Agent": "PlayNexus-GitHub-Deploy/2",
            },
            method="GET",
        )
        try:
            with opener.open(request, timeout=60) as response:
                if response.status < 200 or response.status >= 300:
                    raise RuntimeError(
                        f"Health check failed for {path}: HTTP {response.status}"
                    )

                content_type = response.headers.get("Content-Type", "").lower()
                body = response.read(4 * 1024 * 1024)
                if not body:
                    raise RuntimeError(f"Health check returned an empty body for {path}")

                if expected_kind == "html":
                    lowered = body[:8192].lower()
                    if "text/html" not in content_type:
                        raise RuntimeError(
                            f"Expected HTML from {path}, got {content_type or 'unknown'}"
                        )
                    if b"<html" not in lowered and b"<!doctype html" not in lowered:
                        raise RuntimeError(f"HTML shell marker is missing for {path}")
                elif expected_kind == "json":
                    if "application/json" not in content_type:
                        raise RuntimeError(
                            f"Expected JSON from {path}, got {content_type or 'unknown'}"
                        )
                    payload = json.loads(body.decode("utf-8"))
                    if not isinstance(payload, dict):
                        raise RuntimeError(f"JSON payload is not an object for {path}")

                return
        except (urllib.error.HTTPError, urllib.error.URLError, TimeoutError, OSError, ValueError, RuntimeError) as exc:
            last_error = exc

        if attempt + 1 < MAX_RETRIES:
            time.sleep(2 ** attempt)

    raise RuntimeError(f"Public smoke check failed for {path}: {last_error}")


def external_asset_health(url: str) -> None:
    parsed = urllib.parse.urlparse(url)
    hostname = (parsed.hostname or "").lower()
    if parsed.scheme != "https" or not (
        hostname == "cdnpn.ir"
        or hostname.endswith(".cdnpn.ir")
        or hostname == "playnexus.ir"
        or hostname.endswith(".playnexus.ir")
    ):
        raise RuntimeError("Health endpoint returned an unexpected CDN probe URL.")

    request = urllib.request.Request(
        url,
        headers={
            "Range": "bytes=0-1023",
            "User-Agent": "PlayNexus-GitHub-Deploy/2",
        },
        method="GET",
    )
    with urllib.request.urlopen(request, timeout=60) as response:
        if response.status not in {200, 206}:
            raise RuntimeError(f"CDN probe failed: HTTP {response.status}")
        if not response.read(1024):
            raise RuntimeError("CDN probe returned an empty asset.")


def main() -> int:
    if len(sys.argv) != 2:
        die("Usage: scripts/playnexus_deploy.py <deployment.zip>")

    package = pathlib.Path(sys.argv[1]).resolve()
    if not package.is_file():
        die(f"Deployment package not found: {package}")

    size = package.stat().st_size
    if size <= 0:
        die("Deployment package is empty.")

    total_chunks = math.ceil(size / CHUNK_SIZE)
    operation_id = ""

    print(
        f"Uploading signed deployment package: {size / (1024 * 1024):.2f} MiB "
        f"in {total_chunks} chunks",
        flush=True,
    )

    with package.open("rb") as handle:
        for index in range(total_chunks):
            chunk = handle.read(CHUNK_SIZE)
            if not chunk:
                die("Unexpected end of deployment package.")

            fields = {
                "operation_id": operation_id,
                "chunk_index": str(index),
                "total_chunks": str(total_chunks),
                "size": str(size),
                "name": package.name,
                "source_sha": SOURCE_SHA,
                "source_ref": SOURCE_REF,
                "run_id": RUN_ID,
            }
            state = request_json(
                "POST",
                "upload/chunk",
                multipart=(fields, f"{package.name}.part", chunk),
            )
            received_id = str(state.get("id") or "")
            if not received_id:
                die("Server did not return a deployment operation id.")
            if operation_id and received_id != operation_id:
                die("Server changed deployment operation id during upload.")
            operation_id = received_id

            if index == total_chunks - 1 or (index + 1) % max(1, total_chunks // 10) == 0:
                print(f"Upload progress: {index + 1}/{total_chunks}", flush=True)

    state = request_json(
        "POST",
        "upload/complete",
        json_body={"operation_id": operation_id},
    )
    if state.get("status") not in {"uploaded", "verified"}:
        die(f"Unexpected state after upload: {state.get('status')}")

    state = request_json("POST", f"{operation_id}/verify", json_body={})
    preflight = state.get("preflight") or []
    blocking = [
        check
        for check in preflight
        if isinstance(check, dict) and check.get("status") == "error"
    ]
    if blocking:
        labels = ", ".join(str(item.get("label", "unknown")) for item in blocking)
        die(f"Deployment preflight has blocking errors: {labels}")

    diff = state.get("diff") or {}
    print(
        "Verified package: "
        f"{len(diff.get('changed') or [])} changed, "
        f"{len(diff.get('deleted') or [])} deleted, "
        f"{len(diff.get('pending_migrations') or [])} pending migrations"
    )

    for _ in range(12):
        if state.get("status") in TERMINAL:
            break
        if state.get("status") == "failed":
            die(str(state.get("error") or "Deployment failed on server."))

        state = request_json("POST", f"{operation_id}/apply", json_body={})
        bypass = state.get("maintenance_bypass")
        if isinstance(bypass, str) and bypass:
            # Never log the secret. Establish the Laravel maintenance cookie only.
            establish_maintenance_bypass(bypass)

        print(
            f"Server stage: {state.get('stage', 'unknown')} "
            f"({state.get('progress', 0)}%)",
            flush=True,
        )
    else:
        die("Deployment exceeded the expected number of server stages.")

    if state.get("status") != "completed":
        die(
            f"Deployment did not complete successfully: "
            f"{state.get('status')} / {state.get('stage')}"
        )

    final_state = request_json("GET", f"{operation_id}/status")
    if final_state.get("status") != "completed":
        die("Server did not persist the completed deployment state.")

    health = request_json(
        "GET",
        f"health?expected_sha={urllib.parse.quote(SOURCE_SHA)}",
    )
    if health.get("status") != "ok":
        failed = [
            name
            for name, result in (health.get("checks") or {}).items()
            if isinstance(result, dict) and result.get("ok") is not True
        ]
        die(
            "Authenticated post-deploy health check failed: "
            + (", ".join(failed) if failed else "unknown")
        )

    deployed_commit = str(health.get("commit") or "").lower()
    if deployed_commit != SOURCE_SHA:
        die(
            "Authenticated health check reported the wrong deployed commit: "
            f"{deployed_commit or '<missing>'}"
        )

    checks = health.get("checks") or {}
    print(
        "Authenticated health checks passed: "
        + ", ".join(
            name
            for name, result in checks.items()
            if isinstance(result, dict) and result.get("ok") is True
        ),
        flush=True,
    )
    warnings = [
        f"{name}: {result.get('detail', 'warning')}"
        for name, result in checks.items()
        if isinstance(result, dict)
        and result.get("blocking") is False
        and result.get("detail") not in {None, "", "production/debug-off/url-ok"}
    ]
    for warning in warnings:
        print(f"::warning::Post-deploy diagnostic: {warning}")

    public_health("/up")
    public_health("/", "html")
    public_health("/feed", "html")
    public_health("/videos", "html")
    public_health("/game-radar", "html")
    public_health("/api/v1/meta", "json")

    cdn_probe_url = health.get("cdn_probe_url")
    if isinstance(cdn_probe_url, str) and cdn_probe_url:
        external_asset_health(cdn_probe_url)
        print("CDN asset probe passed.", flush=True)

    print(f"PlayNexus deployment and post-deploy verification completed for commit {SOURCE_SHA[:12]}.")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except KeyboardInterrupt:
        die("Deployment interrupted.")
    except Exception as exc:
        die(str(exc))
