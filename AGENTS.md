# AGENTS.md

## Project
Laravel + Inertia + React + TypeScript + Vite + Tailwind + HeroUI.
Laravel is the server. React is the Inertia client. This is not Next.js.

## Work efficiently
- Do not scan the entire repository unless genuinely necessary.
- Search for the relevant symbol/file first, then inspect only related code.
- Do not repeatedly reread unchanged files.
- For large files, locate the relevant section instead of reading the whole file.
- Stop exploring once enough context exists to implement the task correctly.

## Ignore by default
- vendor/**
- node_modules/**
- public/build/**
- storage/**
- composer.lock
- package-lock.json
- binary/media/font files
- .env

## Changes
- Stay focused on the requested feature.
- Do not perform unrelated refactors or cleanup.
- Reuse existing services, components, helpers and patterns when appropriate.
- Create new files/components when they genuinely improve the implementation.
- Do not add packages or upgrade dependencies unless required.
- Preserve existing architecture and conventions.

## Architecture
- Business logic belongs in existing Laravel Services where applicable.
- Keep Controllers focused on request/response orchestration.
- Use Form Requests for meaningful backend validation.
- Use existing Eloquent relations/scopes and avoid N+1 queries.
- Use Inertia patterns (`Link`, `router`, `useForm`) for existing Inertia flows.
- Keep TypeScript strict and avoid unnecessary `any`.
- Preserve RTL/Persian-first and responsive behavior.

## Creativity
Be highly creative for UI/UX and feature design.
Efficiency rules must not reduce solution quality or originality.
Necessary complexity is allowed; unnecessary work is not.

## Validation
Run the smallest relevant validation/test first.
Do not run the entire test suite or production build after every small change unless necessary.

## Response
Keep completion summaries concise:
- what changed
- files changed
- validation performed
- relevant caveats