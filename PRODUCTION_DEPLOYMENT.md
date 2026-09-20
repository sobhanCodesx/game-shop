# PlayNexus production deployment

PlayNexus production deploys over HTTPS because the production cPanel plan does
not expose external SSH.

## Security model

The deployment system reuses the existing root
`PLAYNEXUS_CONTENT_AGENT_TOKEN`. No second deployment secret and no
production enable/disable environment flag are required.

MCP and GraphQL continue to authenticate with the existing bearer token. The
deployment endpoint deliberately does **not** accept that raw bearer. GitHub
derives a deployment-only credential with the HMAC context
`playnexus/deployment-auth/v1`, while package signing uses the separate context
`playnexus/deployment-package/v1`. This domain separation means disclosure of
a deployment bearer does not grant access to MCP or GraphQL.

Deployment packages never contain the root token. A package signing key is
derived from it with the package-specific HMAC context. The package also has a
fixed application id (`playnexus-production-v1`) so a package built for
another application cannot be accepted.

Automated deployment additionally requires:

- source ref exactly `refs/heads/main`;
- a full 40-character source commit SHA;
- the manifest Git commit to exactly match the requested source SHA;
- a valid signed manifest;
- a checksum for every file in the package;
- all paths to stay inside the deployment allowlist;
- no ZIP path traversal or symlinks;
- package, extracted-size, file-count, and compression-ratio limits.

## GitHub flow

A push or merge to `main` runs:

1. Checkout the exact commit.
2. Verify the existing content-agent secret exists.
3. Install production-only Composer dependencies.
4. Run `npm ci` and build both browser and standalone Inertia SSR bundles.
5. Build a signed deployment ZIP.
6. Keep the package as a short-lived private GitHub artifact for recovery or
   the one-time bootstrap.
7. Upload the ZIP to PlayNexus in 1 MiB chunks over HTTPS.
8. Verify the signed manifest, source commit, checksums, file allowlist, server
   PHP requirements, disk space, DB connection, vendor, Vite output and SSR
   bundle.
9. Compute the exact changed, deleted and pending-migration sets.
10. Back up every affected server file and the database.
11. Enter Laravel maintenance mode.
12. Replace only changed files and remove only manifest-proven deleted files.
13. Run migrations and production caches.
14. Copy/restart the Passenger SSR bundle when configured.
15. Leave maintenance mode.
16. Run internal and public health checks.

The workflow is serialized with GitHub concurrency so two production deploys
cannot overlap.

## Retry behavior

Chunk uploads are idempotent: re-sending an already received chunk is safe.
Package completion is also idempotent. The GitHub client retries transient
network, rate-limit and 5xx errors without printing the bearer token.

Laravel's maintenance bypass secret is never printed. The deploy client uses it
only to establish the temporary maintenance cookie required to continue the
multi-request deployment sequence.

## Backups and rollback

Before switching files, PlayNexus backs up affected files and the database.
The existing admin Deployment screen keeps manual rollback available.

GitHub does **not** automatically roll back the database after a failed public
health check. Automatic database rollback can be more destructive than the
original failure when a migration is not perfectly reversible. A failed
workflow therefore stops, preserves deployment state and backups, and leaves a
clear failure for manual inspection.

## Persistent server data

The deployment package does not contain the production `.env`.
`public/apk` is explicitly preserved on the server. Runtime data is not
blindly replaced by a Git checkout.

## One-time bootstrap on the current shared host

The production server must receive the deployment-agent code once before GitHub
can call it. This is the final manual deployment.

The recommended bootstrap is:

1. Merge the completed deployment-agent work into `main`.
2. Let the first `main` workflow build the signed ZIP. Its HTTPS deploy step
   may fail with 404 because the old production server does not know the agent
   route yet; this is expected only for bootstrap.
3. Download the short-lived `playnexus-deployment-<sha>` artifact from that
   workflow.
4. Back up the current `public_html`.
5. Extract the deployment ZIP into the current PlayNexus project root. It does
   not contain `.env` and does not replace the root cPanel `.htaccess`.
6. From cPanel Terminal run the PHP 8.2 CLI explicitly:
   `/opt/cpanel/ea-php82/root/usr/bin/php artisan optimize:clear`
7. Confirm `https://playnexus.ir/up` returns successfully.
8. Re-run the same GitHub workflow from `main` with the manual `deploy`
   option enabled, or make the next merge to `main`.

After the bootstrap, normal production deployment is completely automatic and
does not require cPanel ZIP upload/extract.

## Emergency manual path

The existing `/admin/deployments` interface remains available as a recovery
path. It uses the same signed package format and server-side verification,
backup, migration, cache and health-check pipeline.
