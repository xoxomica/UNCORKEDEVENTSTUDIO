# GitHub and live hosting

## Repository preparation

The repository contains application source, the database schema, public brand assets, documentation and isolated tests. `.gitignore` excludes the runtime database, sessions, local backups, downloaded research, environment files and Vercel's local project settings. `storage/.gitkeep` preserves the writable runtime directory on a fresh checkout.

The GitHub destination and visibility must be selected before connecting a remote or pushing. Keep business records and credentials outside Git. Configure deployment secrets in the hosting provider's environment settings.

## Current application requirements

- PHP 8.2 or newer with PDO SQLite, mbstring and sessions.
- Document root: `public/`. Its index.php loads the single-file landing page in the repository root.
- Writable, persistent runtime storage outside the public document root.
- Existing configurable paths: `UNCORKED_DB_PATH` for SQLite and `UNCORKED_SESSION_PATH` for PHP sessions. Their parent storage location must exist and be writable.
- HTTPS for the live site. Back up the actual database separately from the repository.

The public landing page has no staff sign-in link. Staff open `/login.php` directly when the document root is `public/`. Existing authentication and role checks still protect the operations screens.

## Vercel compatibility

The current full application is not ready to deploy unchanged to Vercel. PHP is available through a community runtime, and Vercel Functions do not provide the persistent local filesystem required by this application's writable SQLite database. Local session files also require a persistence plan.

Two possible deployment paths:

1. **Keep the existing PHP application:** deploy the full repository to a PHP host with persistent storage and set its document root to `public/`. This preserves the implemented booking, administration and SQLite workflows.
2. **Use Vercel:** prepare a separate public frontend backed by a persistent backend, or migrate the application's SQLite-specific database operations and file-based session/storage features before deploying the complete application. Booking, tracking and staff workflows need to be retested against that architecture.

Vercel's GitHub integration alone does not perform that migration. No Vercel deployment configuration is included yet, because the live architecture has not been selected.

Official sources checked 22 October 2026:

- https://vercel.com/docs/functions/runtimes
- https://vercel.com/kb/guide/is-sqlite-supported-in-vercel

## Before the first live release

Connect the selected GitHub repository, choose the hosting path, configure the environment, and run the isolated project checks described in `docs/ARCHITECTURE.md`. Confirm that bookings, staff authentication, customer tracking and data persistence work on the chosen host before accepting real online requests.
