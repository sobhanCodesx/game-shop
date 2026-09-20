# PlayNexus production deployment

This repository contains an atomic, release-based production deployment for the
Laravel + Inertia + React application.

The goal is to make production deployments repeatable and safe: every push to
`main` can deploy automatically, while `.env`, runtime storage, APK files,
and deployment exports stay outside Git history.

## Architecture

The production host uses this layout:

```text
<DEPLOY_BASE_PATH>/
├── current -> releases/<commit>-<run-id>
├── releases/
│   ├── <old-release>/
│   └── <new-release>/
├── shared/
│   ├── .env
│   ├── storage/
│   ├── public-apk/
│   └── deployment-exports/
├── .incoming/
└── .deploy/
```

The web server document root must point to:

```text
<DEPLOY_BASE_PATH>/current/public
```

This is important. The workflow never deploys by overwriting the active
application tree. It builds a new immutable release, validates it, runs the
migration during a short Laravel maintenance window, and atomically switches
the `current` symlink.

If the health check fails after the switch, the script switches the application
symlink back to the previous release and restarts SSR. Database migrations are
never rolled back automatically because destructive automatic DB rollbacks are
less safe than leaving a forward migration in place. Production migrations
should therefore remain backwards-compatible with the previous release.

## GitHub production secrets

Create a GitHub Environment named `production` and store these secrets in it:

- `DEPLOY_HOST` — SSH hostname.
- `DEPLOY_PORT` — SSH port.
- `DEPLOY_USER` — SSH username.
- `DEPLOY_BASE_PATH` — absolute deployment base path.
- `DEPLOY_SSH_PRIVATE_KEY` — private key used only by GitHub Actions.
- `DEPLOY_KNOWN_HOSTS` — pinned SSH host-key line for the host. Do not use
  `StrictHostKeyChecking=no`.
- `DEPLOY_HEALTHCHECK_URL` — normally `https://playnexus.ir/`.

Optional cPanel/Passenger SSR secrets:

- `DEPLOY_SSR_BUNDLE_PATH` — for example the existing Passenger
  `inertia-ssr/ssr.js` target.
- `DEPLOY_SSR_RESTART_FILE` — Passenger restart marker, typically
  `.../tmp/restart.txt`.

The SSH private key must never be committed to this repository.

## Safety switch

Automatic push-to-production is disabled until the repository variable below
is explicitly enabled:

```text
DEPLOY_ENABLED=true
```

Before enabling it, run the workflow manually with `dry_run=true`. A dry run
still builds the exact production bundles, uploads the signed-by-checksum
archive, links the production `.env`/shared storage into a temporary release,
and boots Laravel. It does **not** run migrations or change the `current`
symlink.

After one successful dry run, set `DEPLOY_ENABLED=true`. From then on every
successful push/merge to `main` deploys automatically.

## One-time host bootstrap

Do this once, before enabling automatic deployment:

1. Create `<DEPLOY_BASE_PATH>/shared`, `releases`, and `.incoming`.
2. Move/copy the production `.env` to
   `<DEPLOY_BASE_PATH>/shared/.env`.
3. Preserve the current production `storage` directory as
   `<DEPLOY_BASE_PATH>/shared/storage`.
4. Preserve any existing `public/apk` files as
   `<DEPLOY_BASE_PATH>/shared/public-apk`.
5. Preserve `deployment-exports`, if used, as
   `<DEPLOY_BASE_PATH>/shared/deployment-exports`.
6. Place the currently working application in
   `<DEPLOY_BASE_PATH>/releases/initial`, link its `.env`, `storage`,
   `public/storage`, `public/apk`, and `deployment-exports` to the shared
   paths, then create:
   `current -> releases/initial`.
7. Change the cPanel domain document root to
   `<DEPLOY_BASE_PATH>/current/public`.
8. If Passenger hosts Inertia SSR separately, keep its existing application
   path and set the two optional SSR secrets above.
9. Run the GitHub workflow manually with `dry_run=true`.
10. Only after the dry run passes, enable `DEPLOY_ENABLED=true`.

## What the workflow checks

Before production is touched, GitHub Actions verifies Composer metadata,
installs production PHP dependencies, builds the Vite client and standalone SSR
bundle, and verifies both manifests exist.

On the server, the deployment script verifies the archive SHA-256 checksum,
checks the PHP Composer platform guard by loading `vendor/autoload.php`, boots
Laravel, runs package discovery, and verifies the release before entering
maintenance mode.

Deployments are serialized, so two merges cannot deploy concurrently. SSH host
verification is pinned with `DEPLOY_KNOWN_HOSTS`, and the GitHub token has
read-only repository permissions.

## Rollback behavior

A failed preflight never changes production.

A failure during activation restores the previous `current` symlink when
possible, restarts SSR from that release, exits maintenance mode, and makes the
GitHub Action fail. Database migrations are intentionally not reversed
automatically.

Successful releases are retained so a previous release remains available for a
manual code rollback if needed.
