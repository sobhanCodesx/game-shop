#!/usr/bin/env python3
"""Send queued PlayNexus content jobs to the private MCP endpoint."""

from __future__ import annotations

import base64
import binascii
import hashlib
import ipaddress
import json
import mimetypes
import os
import socket
import subprocess
import sys
import tempfile
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path
from typing import Any

MCP_TOOLS = {
    "describe_playnexus_graph",
    "query_playnexus_graph",
    "list_game_events",
    "upsert_game_event",
    "set_game_event_state",
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
LOCAL_TOOLS = {"upload_asset", "upload_asset_by_query", "verify_feed_ready"}
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

    if tool == "query_playnexus_graph":
        structured = result.get("structuredContent")
        graph_result = structured.get("result") if isinstance(structured, dict) else None
        if not isinstance(graph_result, dict):
            fail("PlayNexus GraphQL response is missing structuredContent.result.")

        graph_errors = graph_result.get("errors")
        if graph_errors:
            fail(
                "PlayNexus GraphQL query failed: "
                f"{json.dumps(graph_errors, ensure_ascii=False)}"
            )

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



def local_structured_response(result: dict[str, Any]) -> dict[str, Any]:
    return {
        "result": {
            "structuredContent": {
                "result": result,
            },
        },
    }


def looks_like_supported_image(prefix: bytes) -> bool:
    return (
        prefix.startswith(b"\xff\xd8\xff")
        or prefix.startswith(b"\x89PNG\r\n\x1a\n")
        or prefix.startswith(b"GIF87a")
        or prefix.startswith(b"GIF89a")
        or (len(prefix) >= 12 and prefix.startswith(b"RIFF") and prefix[8:12] == b"WEBP")
    )


def verify_public_asset_url(asset_url: Any, expected_kind: str) -> dict[str, Any]:
    if not isinstance(asset_url, str) or not asset_url.strip():
        fail("Feed asset is missing a public URL.")

    asset_url = asset_url.strip()
    validate_public_https_url(asset_url)

    request = urllib.request.Request(
        asset_url,
        method="GET",
        headers={
            "Accept": "image/*,video/*;q=0.9,*/*;q=0.1",
            "Range": "bytes=0-65535",
            "User-Agent": "PlayNexus-GitHub-Publisher/2.3",
        },
    )
    opener = urllib.request.build_opener(SafeRedirectHandler())

    try:
        with opener.open(request, timeout=30) as response:
            final_url = response.geturl()
            validate_public_https_url(final_url)
            status = int(getattr(response, "status", 200) or 200)
            if status not in (200, 206):
                fail(f"Feed asset public URL returned unexpected HTTP {status}.")

            mime = str(response.headers.get_content_type() or "").casefold()
            prefix = response.read(64)
    except urllib.error.HTTPError as exc:
        fail(f"Feed asset public URL returned HTTP {exc.code}.")
    except urllib.error.URLError as exc:
        fail(f"Could not reach feed asset public URL: {exc.reason}")

    if not prefix:
        fail("Feed asset public URL returned an empty body.")

    if expected_kind == "image":
        if not mime.startswith("image/"):
            fail(f"Feed image public URL returned non-image Content-Type {mime!r}.")
        if not looks_like_supported_image(prefix):
            fail("Feed image public URL did not return a supported image signature.")
    elif expected_kind == "video":
        if not mime.startswith("video/"):
            fail(f"Feed video public URL returned non-video Content-Type {mime!r}.")
    else:
        fail(f"Unsupported feed media kind during verification: {expected_kind!r}.")

    return {
        "url": final_url,
        "http_status": status,
        "content_type": mime,
        "kind": expected_kind,
    }


def verify_feed_ready(
    url: str,
    token: str,
    *,
    feed_id: int,
    request_id: str,
) -> dict[str, Any]:
    if not isinstance(feed_id, int) or isinstance(feed_id, bool) or feed_id < 1:
        fail("verify_feed_ready requires a positive integer feed id.")

    content_response = rpc_request(
        url,
        token,
        tool="get_content",
        arguments={"resource": "feed", "id": feed_id},
        request_id=f"{request_id}:content",
    )
    feed = structured_result(content_response)

    if int(feed.get("id") or 0) != feed_id:
        fail("Feed verification returned the wrong feed id.")
    if not str(feed.get("title") or "").strip():
        fail("Feed cannot be published without a title.")
    if not str(feed.get("body") or "").strip():
        fail("Feed cannot be published without body content.")
    if not str(feed.get("feed_type") or "").strip():
        fail("Feed cannot be published without feed_type metadata.")

    if str(feed.get("feed_type") or "").casefold() == "news":
        if not str(feed.get("seo_title") or "").strip():
            fail("News feed cannot be published without seo_title.")
        if not str(feed.get("seo_description") or "").strip():
            fail("News feed cannot be published without seo_description.")

    status = str(feed.get("status") or "")
    if status not in {"draft", "published"}:
        fail(f"Feed has an invalid publish state: {status!r}.")

    assets_response = rpc_request(
        url,
        token,
        tool="list_content_assets",
        arguments={"resource": "feed", "id": feed_id},
        request_id=f"{request_id}:assets",
    )
    assets = structured_result(assets_response)
    slots = assets.get("slots")
    if not isinstance(slots, list) or not slots:
        fail("Feed cannot be published without internal media.")

    verified_assets: list[dict[str, Any]] = []
    for asset in slots:
        if not isinstance(asset, dict):
            fail("Feed asset verification returned an invalid asset record.")

        path_value = asset.get("path")
        if not isinstance(path_value, str) or not path_value.strip():
            fail("Feed asset is missing its internal storage path.")

        if asset.get("storage_exists") is False:
            fail(f"Feed asset is missing from storage: {path_value}")

        kind = str(asset.get("kind") or "").casefold()
        verified = verify_public_asset_url(asset.get("url"), kind)
        verified["path"] = path_value
        verified["storage_exists"] = asset.get("storage_exists")
        verified_assets.append(verified)

    return local_structured_response({
        "feed_id": feed_id,
        "status": status,
        "feed_type": feed.get("feed_type"),
        "seo_title": feed.get("seo_title"),
        "seo_description": feed.get("seo_description"),
        "verified_assets": verified_assets,
        "ready": True,
    })


def normalized_lookup_value(value: Any) -> str:
    return "".join(character for character in str(value or "").casefold() if character.isalnum())


def resolve_content_id_by_query(
    url: str,
    token: str,
    *,
    resource: str,
    query: str,
    request_id: str,
) -> int:
    if not isinstance(resource, str) or not resource.strip():
        fail("upload_asset_by_query resource must be a non-empty string.")
    if not isinstance(query, str) or not query.strip():
        fail("upload_asset_by_query query must be a non-empty string.")

    response = rpc_request(
        url,
        token,
        tool="select_content",
        arguments={
            "resource": resource.strip(),
            "query": query.strip(),
            "limit": 20,
        },
        request_id=f"{request_id}:resolve",
    )

    result = response.get("result")
    structured = result.get("structuredContent") if isinstance(result, dict) else None
    value = structured.get("result") if isinstance(structured, dict) else None
    items = value.get("items") if isinstance(value, dict) else None
    if not isinstance(items, list):
        fail("PlayNexus selector did not return an items list.")

    needle = normalized_lookup_value(query)
    exact_matches = []
    for item in items:
        if not isinstance(item, dict):
            continue
        labels = (item.get("name"), item.get("title"), item.get("slug"))
        if any(normalized_lookup_value(label) == needle for label in labels if label):
            exact_matches.append(item)

    if len(exact_matches) != 1:
        fail(
            "upload_asset_by_query requires exactly one exact PlayNexus match; "
            f"found {len(exact_matches)} for {resource!r} query {query!r}."
        )

    resolved_id = exact_matches[0].get("id")
    if not isinstance(resolved_id, int) or resolved_id < 1:
        fail("Resolved PlayNexus record is missing a valid integer id.")

    print(
        f"Resolved {resource} {query!r} to id={resolved_id} "
        f"({exact_matches[0].get('name') or exact_matches[0].get('title') or exact_matches[0].get('slug')})."
    )
    return resolved_id


def upload_asset_by_query(
    url: str,
    token: str,
    job: dict[str, Any],
    request_id: str,
) -> dict[str, Any]:
    arguments = job.get("arguments", {})
    if not isinstance(arguments, dict):
        fail("upload_asset_by_query arguments must be an object.")

    delegated_arguments = dict(arguments)
    query = delegated_arguments.pop("query", None)
    resource = delegated_arguments.get("resource")
    if "id" in delegated_arguments:
        fail("upload_asset_by_query must not include id; it resolves the id from query.")

    delegated_arguments["id"] = resolve_content_id_by_query(
        url,
        token,
        resource=resource,
        query=query,
        request_id=request_id,
    )

    delegated_job = {
        **job,
        "tool": "upload_asset",
        "arguments": delegated_arguments,
    }
    return upload_asset(url, token, delegated_job, request_id)



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


def require_media_tools() -> None:
    for command in ("ffmpeg", "ffprobe"):
        try:
            subprocess.run(
                [command, "-version"],
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL,
                check=True,
            )
        except (FileNotFoundError, subprocess.CalledProcessError):
            fail(f"{command} is required for video validation/transcoding.")


def probe_video(path: Path) -> dict[str, Any]:
    require_media_tools()
    try:
        completed = subprocess.run(
            [
                "ffprobe",
                "-v", "error",
                "-show_entries",
                "format=duration:stream=index,codec_type,width,height",
                "-of", "json",
                str(path),
            ],
            check=True,
            capture_output=True,
            text=True,
            timeout=60,
        )
        payload = json.loads(completed.stdout)
    except (subprocess.CalledProcessError, subprocess.TimeoutExpired, json.JSONDecodeError) as exc:
        fail(f"Could not inspect video asset with ffprobe: {exc}")

    streams = payload.get("streams") if isinstance(payload, dict) else None
    fmt = payload.get("format") if isinstance(payload, dict) else None
    if not isinstance(streams, list) or not isinstance(fmt, dict):
        fail("ffprobe returned invalid video metadata.")

    video_stream = next((item for item in streams if item.get("codec_type") == "video"), None)
    has_audio = any(item.get("codec_type") == "audio" for item in streams)
    if not isinstance(video_stream, dict):
        fail("Video asset has no video stream.")

    try:
        duration = float(fmt.get("duration"))
    except (TypeError, ValueError):
        fail("Video asset duration could not be determined.")

    if duration <= 0:
        fail("Video asset duration is invalid.")

    width = int(video_stream.get("width") or 0)
    height = int(video_stream.get("height") or 0)
    if width < 1 or height < 1:
        fail("Video asset dimensions could not be determined.")

    return {
        "duration_seconds": duration,
        "width": width,
        "height": height,
        "has_audio": has_audio,
    }


def materialize_hls(
    source_url: str,
    target: Path,
    *,
    max_height: int | None,
) -> None:
    require_media_tools()
    validate_public_https_url(source_url)

    command = [
        "ffmpeg",
        "-hide_banner",
        "-loglevel", "error",
        "-y",
        "-i", source_url,
        "-map", "0:v:0",
        "-map", "0:a:0?",
    ]

    if max_height is not None:
        if max_height < 144 or max_height > 2160:
            fail("transcode_max_height must be between 144 and 2160.")
        command += [
            "-vf", f"scale=-2:min(ih\\,{max_height})",
            "-c:v", "libx264",
            "-preset", "medium",
            "-crf", "23",
            "-c:a", "aac",
            "-b:a", "128k",
        ]
    else:
        command += ["-c", "copy"]

    command += ["-movflags", "+faststart", str(target)]

    try:
        subprocess.run(command, check=True, timeout=900)
    except (subprocess.CalledProcessError, subprocess.TimeoutExpired) as exc:
        fail(f"Could not materialize HLS video with ffmpeg: {exc}")

    if not target.is_file() or target.stat().st_size < 1:
        fail("HLS materialization produced an empty file.")


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

        if arguments.get("resource") == "video" and arguments.get("slot") == "video":
            if "/microtrailer." in source_url.casefold():
                fail("Steam microtrailer assets are not valid publishable videos.")

        parsed_source = urllib.parse.urlparse(source_url)
        is_hls = parsed_source.path.casefold().endswith(".m3u8")

        if is_hls:
            if parsed_source.hostname not in {
                "video.akamai.steamstatic.com",
                "video.fastly.steamstatic.com",
            }:
                fail("HLS source host is not approved for publisher materialization.")

            transcode_height = arguments.get("transcode_max_height")
            if transcode_height is not None:
                try:
                    transcode_height = int(transcode_height)
                except (TypeError, ValueError):
                    fail("transcode_max_height must be an integer.")

            target = directory / "source.mp4"
            materialize_hls(
                source_url,
                target,
                max_height=transcode_height,
            )
            detected_mime = "video/mp4"
            name = sanitized_name(str(requested_name or "video.mp4"))
        else:
            request = urllib.request.Request(
                source_url,
                headers={
                    "Accept": "*/*",
                    "User-Agent": "PlayNexus-GitHub-Publisher/2.2",
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

            url_name = urllib.parse.unquote(Path(parsed_source.path).name)
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
        except (ValueError, binascii.Error):
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


def upload_binary_chunk(
    mcp_url: str,
    token: str,
    *,
    upload_id: str,
    chunk_index: int,
    chunk: bytes,
) -> dict[str, Any]:
    endpoint = urllib.parse.urljoin(mcp_url, "content-agent/upload/chunk")
    validate_public_https_url(endpoint)

    boundary = f"----PlayNexusBinary{hashlib.sha256(f'{upload_id}:{chunk_index}'.encode()).hexdigest()[:24]}"
    boundary_bytes = boundary.encode("ascii")

    body = b"".join([
        b"--" + boundary_bytes + b"\r\n",
        b'Content-Disposition: form-data; name="upload_id"\r\n\r\n',
        upload_id.encode("utf-8") + b"\r\n",
        b"--" + boundary_bytes + b"\r\n",
        b'Content-Disposition: form-data; name="chunk_index"\r\n\r\n',
        str(chunk_index).encode("ascii") + b"\r\n",
        b"--" + boundary_bytes + b"\r\n",
        b'Content-Disposition: form-data; name="chunk"; filename="chunk.bin"\r\n',
        b"Content-Type: application/octet-stream\r\n\r\n",
        chunk + b"\r\n",
        b"--" + boundary_bytes + b"--\r\n",
    ])

    request = urllib.request.Request(
        url=endpoint,
        data=body,
        method="POST",
        headers={
            "Authorization": f"Bearer {token}",
            "Accept": "application/json",
            "Content-Type": f"multipart/form-data; boundary={boundary}",
            "User-Agent": "PlayNexus-GitHub-Publisher/2.2",
        },
    )

    try:
        with urllib.request.urlopen(request, timeout=90) as response:
            raw = response.read().decode("utf-8")
    except urllib.error.HTTPError as exc:
        detail = exc.read().decode("utf-8", errors="replace")
        fail(f"PlayNexus binary chunk endpoint returned HTTP {exc.code}: {detail[:1000]}")
    except urllib.error.URLError as exc:
        fail(f"Could not upload binary chunk to PlayNexus: {exc.reason}")

    try:
        payload = json.loads(raw)
    except json.JSONDecodeError:
        fail(f"PlayNexus binary chunk endpoint returned non-JSON data: {raw[:1000]}")

    if not isinstance(payload, dict):
        fail("PlayNexus binary chunk endpoint returned invalid JSON.")

    if int(payload.get("received_bytes") or 0) != len(chunk):
        fail("PlayNexus binary chunk endpoint acknowledged the wrong byte count.")

    return payload


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

        probe: dict[str, Any] | None = None
        if str(mime).casefold().startswith("video/"):
            probe = probe_video(source)

            require_audio = arguments.get("require_audio")
            if require_audio is None:
                require_audio = arguments.get("resource") == "video" and arguments.get("slot") == "video"
            if bool(require_audio) and not probe["has_audio"]:
                fail("Video asset has no audio stream.")

            min_duration = arguments.get("min_duration_seconds")
            if min_duration is not None and probe["duration_seconds"] < float(min_duration):
                fail(
                    "Video asset is shorter than min_duration_seconds "
                    f"({probe['duration_seconds']:.2f}s < {float(min_duration):.2f}s)."
                )

            max_duration = arguments.get("max_duration_seconds")
            if max_duration is not None and probe["duration_seconds"] > float(max_duration):
                fail(
                    "Video asset exceeds max_duration_seconds "
                    f"({probe['duration_seconds']:.2f}s > {float(max_duration):.2f}s)."
                )

            max_height = arguments.get("max_height")
            if max_height is not None and probe["height"] > int(max_height):
                fail(
                    "Video asset exceeds max_height "
                    f"({probe['height']}p > {int(max_height)}p)."
                )

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

        if probe is not None and arguments.get("slot") == "video":
            start_arguments["duration"] = max(1, int(round(probe["duration_seconds"])))

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

                    upload_binary_chunk(
                        url,
                        token,
                        upload_id=upload_id,
                        chunk_index=index,
                        chunk=chunk,
                    )

            response = rpc_request(
                url,
                token,
                tool="complete_asset_upload",
                arguments={"upload_id": upload_id},
                request_id=f"{request_id}:complete",
                timeout=120,
            )
            if probe is not None:
                result = structured_result(response)
                result["publisher_probe"] = {
                    **probe,
                    "size_bytes": size,
                }
            return response
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
    elif job["tool"] == "upload_asset_by_query":
        response = upload_asset_by_query(url, token, job, request_id)
    elif job["tool"] == "verify_feed_ready":
        arguments = job.get("arguments", {})
        feed_id = arguments.get("id") if isinstance(arguments, dict) else None
        response = verify_feed_ready(
            url,
            token,
            feed_id=feed_id,
            request_id=request_id,
        )
    elif job["tool"] == "publish_feed":
        arguments = job.get("arguments", {})
        feed_id = arguments.get("id") if isinstance(arguments, dict) else None
        verify_feed_ready(
            url,
            token,
            feed_id=feed_id,
            request_id=f"{request_id}:preflight",
        )
        response = rpc_request(
            url,
            token,
            tool="publish_feed",
            arguments=arguments,
            request_id=request_id,
        )
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
