#!/usr/bin/env python3
"""Send queued PlayNexus content jobs to the private MCP endpoint."""

from __future__ import annotations

import base64
import hashlib
import ipaddress
import json
import mimetypes
import os
import socket
import sys
import tempfile
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path
from typing import Any

MCP_TOOLS = {
    "search_games",
    "search_studios",
    "search_platforms",
    "search_collections",
    "select_content",
    "get_content",
    "create_game",
    "create_studio",
    "create_collection",
    "create_story",
    "create_video",
    "get_feed",
    "create_feed",
    "update_content",
    "update_feed",
    "sync_collection_videos",
    "set_content_state",
    "publish_feed",
    "unpublish_feed",
    "delete_content",
    "restore_content",
    "start_asset_upload",
    "upload_asset_chunk",
    "complete_asset_upload",
    "abort_asset_upload",
    "list_content_assets",
    "remove_content_asset",
}

# Synthetic GitHub-runner operation. source_url/file_path/source_base64 are
# consumed here and are never forwarded to the PlayNexus MCP endpoint.
LOCAL_TOOLS = {"upload_asset"}
ALLOWED_TOOLS = MCP_TOOLS | LOCAL_TOOLS

DEFAULT_CHUNK_SIZE = 2 * 1024 * 1024
DEFAULT_MAX_SOURCE_SIZE = 100 * 1024 * 1024


def fail(message: str, code: int = 1) -> None:
    print(f"::error::{message}", file=sys.stderr)
    raise SystemExit(code)


def load_job(path: Path) -> dict[str, Any]:
    try:
        payload = json.loads(path.read_text(encoding="utf-8"))
    except FileNotFoundError:
        fail(f"Job file does not exist: {path}")
    except json.JSONDecodeError as exc:
        fail(f"Invalid JSON in {path}: {exc}")

    if not isinstance(payload, dict):
        fail("Job payload must be a JSON object.")

    tool = payload.get("tool")
    arguments = payload.get("arguments", {})

    if tool not in ALLOWED_TOOLS:
        fail(f"Unsupported PlayNexus tool: {tool!r}")
    if not isinstance(arguments, dict):
        fail("Job 'arguments' must be a JSON object.")

    return payload


def rpc_request(
    url: str,
    token: str,
    *,
    tool: str,
    arguments: dict[str, Any],
    request_id: str,
    timeout: int = 60,
) -> dict[str, Any]:
    if tool not in MCP_TOOLS:
        fail(f"Attempted to send a non-MCP tool to PlayNexus: {tool!r}")

    body = {
        "jsonrpc": "2.0",
        "id": request_id,
        "method": "tools/call",
        "params": {
            "name": tool,
            "arguments": arguments,
        },
    }

    request = urllib.request.Request(
        url=url,
        data=json.dumps(body, ensure_ascii=False).encode("utf-8"),
        method="POST",
        headers={
            "Authorization": f"Bearer {token}",
            "Accept": "application/json",
            "Content-Type": "application/json; charset=utf-8",
            "User-Agent": "PlayNexus-GitHub-Publisher/2.1",
        },
    )

    try:
        with urllib.request.urlopen(request, timeout=timeout) as response:
            raw = response.read().decode("utf-8")
    except urllib.error.HTTPError as exc:
        detail = exc.read().decode("utf-8", errors="replace")
        fail(f"PlayNexus returned HTTP {exc.code}: {detail[:1000]}")
    except urllib.error.URLError as exc:
        fail(f"Could not reach PlayNexus: {exc.reason}")

    try:
        payload = json.loads(raw)
    except json.JSONDecodeError:
        fail(f"PlayNexus returned non-JSON data: {raw[:1000]}")

    if not isinstance(payload, dict):
        fail("PlayNexus returned an invalid JSON-RPC response.")

    if payload.get("error"):
        fail(f"JSON-RPC error: {json.dumps(payload['error'], ensure_ascii=False)}")

    result = payload.get("result")
    if not isinstance(result, dict):
        fail("PlayNexus response is missing a valid result object.")

    if result.get("isError") is True:
        details = result.get("structuredContent") or result.get("content") or result
        fail(f"PlayNexus tool failed: {json.dumps(details, ensure_ascii=False)}")

    return payload


def structured_result(response: dict[str, Any]) -> dict[str, Any]:
    result = response.get("result")
    if not isinstance(result, dict):
        fail("PlayNexus response is missing a result object.")

    structured = result.get("structuredContent")
    if not isinstance(structured, dict):
        fail("PlayNexus response is missing structuredContent.")

    value = structured.get("result")
    if not isinstance(value, dict):
        fail("PlayNexus response is missing structuredContent.result.")

    return value


def positive_env_int(name: str, default: int) -> int:
    raw = os.getenv(name, "").strip()
    if not raw:
        return default

    try:
        value = int(raw)
    except ValueError:
        fail(f"{name} must be an integer.")

    if value < 1:
        fail(f"{name} must be greater than zero.")

    return value


def validate_public_https_url(url: str) -> None:
    if len(url) > 4096:
        fail("Asset source URL is too long.")

    parsed = urllib.parse.urlparse(url)
    if parsed.scheme.lower() != "https":
        fail("Asset source_url must use HTTPS.")
    if not parsed.hostname:
        fail("Asset source_url is missing a hostname.")
    if parsed.username or parsed.password:
        fail("Asset source_url must not contain credentials.")

    host = parsed.hostname.strip().lower()
    if host in {"localhost", "localhost.localdomain"}:
        fail("Localhost asset sources are not allowed.")

    try:
        records = socket.getaddrinfo(host, parsed.port or 443, type=socket.SOCK_STREAM)
    except socket.gaierror as exc:
        fail(f"Could not resolve asset source host: {exc}")

    addresses = {record[4][0] for record in records}
    if not addresses:
        fail("Asset source host did not resolve to an address.")

    for address in addresses:
        ip = ipaddress.ip_address(address)
        if not ip.is_global:
            fail("Asset source must resolve only to public internet addresses.")


class SafeRedirectHandler(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        validate_public_https_url(newurl)
        return super().redirect_request(req, fp, code, msg, headers, newurl)


def sanitized_name(value: str, fallback: str = "asset.bin") -> str:
    name = Path(value).name.strip()
    if not name or name in {".", ".."}:
        return fallback
    return name[:255]


def materialize_source(arguments: dict[str, Any], directory: Path) -> tuple[Path, str, str]:
    source_url = arguments.get("source_url")
    file_path = arguments.get("file_path")
    source_base64 = arguments.get("source_base64")
    selected = sum(value is not None for value in (source_url, file_path, source_base64))

    if selected != 1:
        fail("upload_asset requires exactly one of source_url, file_path or source_base64.")

    max_size = positive_env_int("PLAYNEXUS_PUBLISHER_MAX_SOURCE_BYTES", DEFAULT_MAX_SOURCE_SIZE)
    requested_name = arguments.get("name")
    requested_mime = arguments.get("mime")
    detected_mime = ""
    target = directory / "source"

    if source_url is not None:
        if not isinstance(source_url, str) or not source_url.strip():
            fail("source_url must be a non-empty string.")

        source_url = source_url.strip()
        validate_public_https_url(source_url)
        request = urllib.request.Request(
            source_url,
            headers={
                "Accept": "*/*",
                "User-Agent": "PlayNexus-GitHub-Publisher/2.1",
            },
        )
        opener = urllib.request.build_opener(SafeRedirectHandler())

        try:
            with opener.open(request, timeout=60) as response, target.open("wb") as handle:
                validate_public_https_url(response.geturl())
                content_length = response.headers.get("Content-Length")
                if content_length:
                    try:
                        if int(content_length) > max_size:
                            fail("Remote asset is larger than the publisher source limit.")
                    except ValueError:
                        pass

                total = 0
                while True:
                    chunk = response.read(1024 * 1024)
                    if not chunk:
                        break
                    total += len(chunk)
                    if total > max_size:
                        fail("Remote asset exceeded the publisher source limit.")
                    handle.write(chunk)

                detected_mime = response.headers.get_content_type() or ""
        except urllib.error.HTTPError as exc:
            fail(f"Asset source returned HTTP {exc.code}.")
        except urllib.error.URLError as exc:
            fail(f"Could not download asset source: {exc.reason}")

        url_name = urllib.parse.unquote(Path(urllib.parse.urlparse(source_url).path).name)
        name = sanitized_name(str(requested_name or url_name or "asset.bin"))

    elif file_path is not None:
        if not isinstance(file_path, str) or not file_path.strip():
            fail("file_path must be a non-empty string.")

        root = Path.cwd().resolve()
        allowed_root = (root / ".playnexus" / "uploads").resolve()
        candidate = (root / file_path).resolve()

        try:
            candidate.relative_to(allowed_root)
        except ValueError:
            fail("file_path must stay inside .playnexus/uploads/.")

        if not candidate.is_file():
            fail(f"Asset file does not exist: {file_path}")
        if candidate.stat().st_size > max_size:
            fail("Local asset is larger than the publisher source limit.")

        target = candidate
        name = sanitized_name(str(requested_name or candidate.name))

    else:
        if not isinstance(source_base64, str) or not source_base64:
            fail("source_base64 must be a non-empty Base64 string.")

        try:
            decoded = base64.b64decode(source_base64, validate=True)
        except (ValueError, base64.binascii.Error):
            fail("source_base64 is not valid Base64.")

        if not decoded:
            fail("source_base64 decoded to an empty file.")
        if len(decoded) > max_size:
            fail("Base64 asset is larger than the publisher source limit.")

        target.write_bytes(decoded)
        name = sanitized_name(str(requested_name or "asset.bin"))

    size = target.stat().st_size
    if size < 1:
        fail("Asset source is empty.")
    if size > max_size:
        fail("Asset source is larger than the publisher source limit.")

    mime = str(requested_mime or detected_mime or mimetypes.guess_type(name)[0] or "application/octet-stream")
    return target, name, mime[:120]


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        while True:
            chunk = handle.read(1024 * 1024)
            if not chunk:
                break
            digest.update(chunk)
    return digest.hexdigest()


def upload_asset(
    url: str,
    token: str,
    job: dict[str, Any],
    request_id: str,
) -> dict[str, Any]:
    arguments = job.get("arguments", {})
    if not isinstance(arguments, dict):
        fail("upload_asset arguments must be an object.")

    required = ("resource", "id", "slot")
    for key in required:
        if key not in arguments:
            fail(f"upload_asset is missing required argument: {key}")

    with tempfile.TemporaryDirectory(prefix="playnexus-asset-") as temporary:
        source, name, mime = materialize_source(arguments, Path(temporary))
        size = source.stat().st_size
        digest = sha256_file(source)
        chunk_size = positive_env_int("PLAYNEXUS_PUBLISHER_CHUNK_SIZE", DEFAULT_CHUNK_SIZE)
        chunk_size = min(chunk_size, size)
        total_chunks = (size + chunk_size - 1) // chunk_size

        start_arguments: dict[str, Any] = {
            "resource": arguments["resource"],
            "id": arguments["id"],
            "slot": arguments["slot"],
            "name": name,
            "mime": mime,
            "size": size,
            "chunk_size": chunk_size,
            "total_chunks": total_chunks,
            "sha256": digest,
        }
        for key in ("alt", "sort_order", "duration"):
            if key in arguments and arguments[key] is not None:
                start_arguments[key] = arguments[key]

        start = rpc_request(
            url,
            token,
            tool="start_asset_upload",
            arguments=start_arguments,
            request_id=f"{request_id}:start",
        )
        upload_id = structured_result(start).get("upload_id")
        if not isinstance(upload_id, str) or not upload_id:
            fail("PlayNexus did not return an upload_id.")

        try:
            with source.open("rb") as handle:
                for index in range(total_chunks):
                    chunk = handle.read(chunk_size)
                    if not chunk:
                        fail("Asset source ended before all chunks were read.")

                    rpc_request(
                        url,
                        token,
                        tool="upload_asset_chunk",
                        arguments={
                            "upload_id": upload_id,
                            "chunk_index": index,
                            "data_base64": base64.b64encode(chunk).decode("ascii"),
                        },
                        request_id=f"{request_id}:chunk:{index}",
                        timeout=90,
                    )

            return rpc_request(
                url,
                token,
                tool="complete_asset_upload",
                arguments={"upload_id": upload_id},
                request_id=f"{request_id}:complete",
                timeout=120,
            )
        except BaseException:
            # Best-effort cleanup. If the network is unavailable the server-side
            # upload session expires automatically.
            try:
                rpc_request(
                    url,
                    token,
                    tool="abort_asset_upload",
                    arguments={"upload_id": upload_id},
                    request_id=f"{request_id}:abort",
                    timeout=20,
                )
            except BaseException:
                pass
            raise


def append_summary(job_path: Path, job: dict[str, Any], response: dict[str, Any]) -> None:
    summary_path = os.getenv("GITHUB_STEP_SUMMARY")
    if not summary_path:
        return

    result = response.get("result", {})
    structured = result.get("structuredContent", {}) if isinstance(result, dict) else {}

    with open(summary_path, "a", encoding="utf-8") as handle:
        handle.write(f"### {job_path.name}\n\n")
        handle.write(f"Tool: {job['tool']}\n\n")
        handle.write(json.dumps(structured, ensure_ascii=False, indent=2)[:12000])
        handle.write("\n\n")


def main() -> None:
    if len(sys.argv) != 2:
        fail("Usage: playnexus_publish.py <job.json>", 2)

    job_path = Path(sys.argv[1])
    job = load_job(job_path)

    url = os.getenv("PLAYNEXUS_MCP_URL", "https://playnexus.ir/api/mcp").strip()
    token = os.getenv("PLAYNEXUS_CONTENT_AGENT_TOKEN", "").strip()

    if not url.startswith("https://"):
        fail("PLAYNEXUS_MCP_URL must use HTTPS.")
    if not token:
        fail("PLAYNEXUS_CONTENT_AGENT_TOKEN secret is missing.")

    request_id = str(job.get("job_id") or job_path.stem)
    if job["tool"] == "upload_asset":
        response = upload_asset(url, token, job, request_id)
    else:
        response = rpc_request(
            url,
            token,
            tool=job["tool"],
            arguments=job.get("arguments", {}),
            request_id=request_id,
        )

    append_summary(job_path, job, response)

    result = response.get("result", {})
    structured = result.get("structuredContent", {}) if isinstance(result, dict) else {}
    print(json.dumps(structured, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
