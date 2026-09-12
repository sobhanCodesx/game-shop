# Inertia SSR infrastructure

SSR is intentionally opt-in. The production bundle and server can be ready
without rendering any route until its URL pattern is added to
`config/inertia.php` under `ssr.paths`. Admin routes are always excluded.

## Requirements

- Node.js 22 or newer on the server
- The normal Laravel/PHP runtime

## Build

```bash
npm ci
npm run build
```

This creates the regular browser assets in `public/build` and exactly one,
self-contained SSR server file at `bootstrap/ssr/ssr.js`. The SSR file has its
npm dependencies bundled, so production does not need `node_modules` to run it.
Admin page modules are not included in this server bundle.

## Run

### cPanel / CloudLinux / Passenger

Create a production Node.js application with application URL
`https://playnexus.ir/__inertia_ssr` and startup file
`bootstrap/ssr/ssr.js`. Passenger binds the server to its own Unix socket, so
no production TCP port is configured in Vite and `node_modules` is not needed.
Set this in Laravel's production `.env`:

```dotenv
INERTIA_SSR_ENABLED=true
INERTIA_SSR_URL=https://playnexus.ir/__inertia_ssr
```

Restart the Node.js application from cPanel after each deployment. The SSR
bundle accepts Passenger requests both with and without the application URL
prefix.

### Direct Node / process monitor

Run the SSR process behind a process monitor (Supervisor, systemd, or the
hosting platform's process manager):

```bash
php artisan inertia:start-ssr
```

After each deployment restart it so the new bundle is loaded:

```bash
php artisan inertia:stop-ssr
php artisan inertia:start-ssr
php artisan inertia:check-ssr
```

Outside Passenger, the service listens only on `127.0.0.1` and reads
`INERTIA_SSR_PORT` at runtime (`13714` by default). Rebuilding is not required
when that local/direct-run port changes. The service should not be exposed
publicly unless it is mounted behind Passenger. Laravel falls back to client
rendering if the SSR service is temporarily unavailable. Set
`INERTIA_SSR_THROW_ON_ERROR=true` in test or staging when SSR failures must
fail loudly.

## Enable selected pages later

Add only indexable public URL patterns to `config/inertia.php`:

```php
'paths' => [
    // 'products/*',
],
```

Do not add authenticated, account, checkout, or admin routes unless they have
an explicit indexing requirement.
