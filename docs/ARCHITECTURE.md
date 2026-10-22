# Project architecture

```text
Uncorked Event Studio/
  index.php                    Single-file marketing page and reservation form
  .htaccess                    Protect private folders and database files
  public/                      Customer pages and HTTP endpoints
    index.php                  Entry wrapper for the root marketing page
    track.php                  Customer reservation-status lookup
    availability.php           Public availability endpoint
    api.php                    Reservation submission endpoint
    setup.php, login.php       First-admin setup and staff authentication
    admin/index.php            Authorized operations workspace controller
    receipt.php, export.php    Statements and report download
    backup.php                 Administrator backup download
    assets/                    Public/admin CSS and JavaScript
  app/
    bootstrap.php              Session, PDO connection, migrations, helpers
    modules.php                Master-data module definitions
    services/Operations.php    Transactional stock/booking/payment rules
    services/AdminActions.php  Validated, authorized admin write actions
    views.php                  Admin module templates
    views-reports.php          Report calculations and rendering
  database/
    schema.sql                 SQLite tables and indexes
    restore.php                Offline restoration utility
  storage/                     Private runtime database and session files
  docs/                        Operator guide and technical overview
  tests/                       Isolated service and HTTP workflow checks
```

## Data relationships

Reservations reference a venue and an optional package. Their contact details and agreed totals are snapshots. Customers retain profiles keyed by email. Payments link to reservations. Sales link to products and optionally an event, and retain prices independently of later product edits. Products have stock movements; purchases record supplier receipts and also create a movement. Employees have event duty assignments. Users have roles and are recorded on transactions and audit activity.

Amounts are integer centavos, displayed as PHP. Dates/times use Asia/Manila. Booking confirmation, purchases, sales, stock adjustments, payment validation and staff assignments execute inside SQLite write transactions. This prevents simultaneous requests from overselling or confirming conflicting schedules.

## Running checks

```powershell
php tests/integration.php
php tests/sales_migration.php
python tests/http_flow.py
python tests/concurrency.py
Get-ChildItem -Recurse -Filter '*.php' | ForEach-Object { php -l $_.FullName }
```

Tests use temporary databases and do not seed business records. The HTTP suite runs its own temporary PHP server on port 8092. PHP needs PDO SQLite, mbstring, and sessions. No Node package installation or build step is needed. Optional Node syntax checks can inspect `public/assets/app.js` and `admin.js`.

The HTTP suite also restores a downloaded backup into an isolated target, reopens it through the application, checks safety copies and invalid input rejection, and checks module access for Manager and Staff accounts. The concurrency suite starts independent PHP processes behind a barrier to compete for a venue, the last stock unit, and the remaining event balance. These are targeted correctness checks, not a production load benchmark.

The CLI restore utility honors `UNCORKED_DB_PATH` for its destination, matching the application connection setting; without it the destination remains `storage/uncorked.sqlite`. Stop the site using the target database before restoration.

## Deployment

Preferred web root: `public/`. The supplied root `.htaccess` additionally blocks private project folders when running beneath XAMPP's htdocs, and disables directory indexes. Apache must allow these directives. Other web servers must configure equivalent access restrictions; never serve storage, app, database or tests publicly. Use HTTPS, protected server filesystem permissions and off-device backups before production. PHP's built-in web server is for local development.

The marketing page keeps its PHP, HTML, CSS and JavaScript in the root `index.php`, with brand images in `public/assets/brand/`. Google Fonts and embedded media depend on external services; typography falls back to local system fonts. Reduced-motion preferences and the motion toggle provide alternatives to continuous animation. See `BRAND-SOURCES.md` for media provenance and `CINEMATIC-MOTION.md` for the scroll effects and their fallbacks. See `HOSTING.md` before choosing a production platform.
