# Repository Guidelines

## Project overview

This repository is a Persian, RTL game shop built with Laravel, Inertia.js, React, and TypeScript. Public pages live under `resources/js/Pages`; admin pages use `resources/js/Layouts/AdminLayout.tsx`. Backend controllers are in `app/Http/Controllers`, domain models in `app/Models`, and HTTP routes in `routes/web.php`.

## Working conventions

- Preserve RTL layout, Persian copy, and UTF-8 encoding in all user-facing files.
- Keep controllers focused on HTTP orchestration. Put reusable pricing or domain logic in a service under `app/Services`.
- Use Form Request classes for validation, especially for admin mutations.
- Do not expose internal prices (`buy_price` or `partner_price`) to ordinary customers.
- Keep route names stable and use named routes in redirects and tests.
- Preserve unrelated local changes; inspect `git status` before and after editing.

## Setup and validation

Run these commands from the repository root:

```powershell
composer install
npm.cmd install
php artisan migrate --seed
npm.cmd run dev
php artisan serve
```

Before handing off a change, run the checks relevant to it:

```powershell
php artisan test
npx.cmd tsc --noEmit
npm.cmd run build
```

On Windows, prefer `npm.cmd` and `npx.cmd`; PowerShell may block the `.ps1` shims under restrictive execution policies.

## Testing expectations

- Add or update Feature tests for routes, authorization, validation, persistence, and Inertia props.
- Add Unit tests for isolated services and calculations.
- Use factories and `RefreshDatabase`; do not depend on records in the developer database.
- Test both guest/regular-user denial and admin success for protected admin features.
- For pricing changes, verify public, authenticated customer, and partner visibility separately.

## Database and admin safety

- Prefer additive migrations and never edit an already-deployed migration to change production data shape.
- Seeders must be idempotent where practical (`updateOrCreate`, `updateOrInsert`, or `insertOrIgnore`).
- Never commit real credentials. Admin seed credentials come from `ADMIN_EMAIL` and `ADMIN_PASSWORD`.
- Treat `.env` as local-only; document new variables in `.env.example`.

## Frontend conventions

- Keep page props typed in TypeScript and reuse shared types from `resources/js/types`.
- Format Persian numbers through existing utilities where applicable.
- Maintain keyboard access, visible focus states, useful labels, and responsive behavior.
- Avoid hard-coded backend assumptions when a value can be passed as an Inertia prop.
