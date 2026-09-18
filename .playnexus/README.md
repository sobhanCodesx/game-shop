# PlayNexus content queue

The GitHub publisher reads JSON jobs from `.playnexus/queue/*.json`.

## Binary media/file upload

Use the local runner operation `upload_asset`. This operation is consumed by
`scripts/playnexus_publish.py`; it is **not** sent as an MCP tool.

Example:

```json
{
  "job_id": "studio-ubisoft-logo-001",
  "tool": "upload_asset",
  "arguments": {
    "resource": "studio",
    "id": 123,
    "slot": "logo",
    "source_url": "https://example-cdn.invalid/ubisoft-logo.png",
    "name": "ubisoft-logo.png",
    "alt": "لوگوی یوبی‌سافت"
  }
}
```

Exactly one source is accepted:

- `source_url`: HTTPS public URL downloaded by the GitHub runner.
- `file_path`: repository-relative path under `.playnexus/uploads/`.
- `source_base64`: Base64 bytes embedded in the queue job.

The source URL/path/Base64 value is consumed on the GitHub runner and is never
forwarded to the PlayNexus MCP endpoint. The runner sends only upload metadata
plus Base64 chunks, and the server re-detects MIME and verifies SHA-256.

Supported resource/slot combinations:

- `game`: `cover`, `background`, `attachment`
- `studio`: `logo`, `background`, `attachment`
- `platform`: `icon`, `attachment`
- `collection`: `logo`, `attachment`
- `feed`: `media`, `attachment`
- `story`: `media`, `thumbnail`, `attachment`
- `video`: `video`, `thumbnail`, `attachment`
- `product`: `media`, `attachment`

Server uploads require `PLAYNEXUS_CONTENT_AGENT_ALLOW_UPLOADS=true`. They use
the existing `PLAYNEXUS_CONTENT_AGENT_TOKEN`; no extra authentication token is
introduced.
