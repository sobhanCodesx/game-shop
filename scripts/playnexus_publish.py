#!/usr/bin/env python3
"""Send a queued PlayNexus content job to the private MCP endpoint."""

from __future__ import annotations

import json
import os
import sys
import urllib.error
import urllib.request
from pathlib import Path
from typing import Any

ALLOWED_TOOLS = {
    "search_games",
    "search_studios",
    "search_platforms",
    "search_collections",
    "create_game",
    "create_studio",
    "create_collection",
    "create_story",
    "get_feed",
    "create_feed",
    "update_feed",
    "publish_feed",
}


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


def rpc_request(url: str, token: str, job: dict[str, Any]) -> dict[str, Any]:
    request_id = str(job.get("job_id") or Path(sys.argv[1]).stem)
    body = {
        "jsonrpc": "2.0",
        "id": request_id,
        "method": "tools/call",
        "params": {
            "name": job["tool"],
            "arguments": job.get("arguments", {}),
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
            "User-Agent": "PlayNexus-GitHub-Publisher/1.1",
        },
    )

    try:
        with urllib.request.urlopen(request, timeout=30) as response:
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


def append_summary(job_path: Path, response: dict[str, Any]) -> None:
    summary_path = os.getenv("GITHUB_STEP_SUMMARY")
    if not summary_path:
        return

    result = response.get("result", {})
    structured = result.get("structuredContent", {}) if isinstance(result, dict) else {}

    with open(summary_path, "a", encoding="utf-8") as handle:
        handle.write(f"### ✅ {job_path.name}\n\n")
        handle.write(f"Tool: `{load_job(job_path)['tool']}`\n\n")
        handle.write("```json\n")
        handle.write(json.dumps(structured, ensure_ascii=False, indent=2)[:12000])
        handle.write("\n```\n\n")


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

    response = rpc_request(url, token, job)
    append_summary(job_path, response)

    result = response.get("result", {})
    structured = result.get("structuredContent", {}) if isinstance(result, dict) else {}
    print(json.dumps(structured, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
