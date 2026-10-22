# Uncorked Event Studio Bar

Online Reservation with Integrated Business Operations Management.
PHP 8.2+, HTML, CSS, vanilla JavaScript, and PDO SQLite.

## Open with XAMPP

Start Apache and visit:
http://localhost/Uncorked%20Event%20Studio/public/

First administrator setup:
http://localhost/Uncorked%20Event%20Studio/public/setup.php

No default passwords or invented business records are included. Create your administrator locally, then configure actual venues, packages, products, and business settings.

## Modules

Advertisement website, live venue/package selection, availability checks, reservation requests and customer tracking; protected reservation/event desk; customers; venues/tables; packages; inventory and stock history; suppliers and received purchases; product sales and collection status; event deposits/balances; employee profiles and duty assignments; dashboard, in-app alerts, reports, print statements and CSV export; user roles, audit trail, business settings, backup download and offline restoration.

## Organization

- `public/`: website, admin entry, HTTP endpoints, CSS/JS
- `app/`: shared code, services, permissions, module definitions and views
- `database/`: schema and restoration utility
- `storage/`: private database and sessions
- `docs/`: user guide and architecture
- `tests/`: isolated integration and HTTP checks

Read docs/USER-GUIDE.md for setup, workflows, roles and current boundaries.
Read docs/ARCHITECTURE.md for relationships, checks and deployment.
Read docs/HOSTING.md for GitHub preparation and the PHP/SQLite constraints to resolve before a Vercel deployment.

## Local development

```powershell
php -S 127.0.0.1:8091 -t public
php tests/integration.php
python tests/http_flow.py
```

Keep private folders protected; preferred document root is public/. The database is created automatically on first use. SQLite is the implemented database, not MySQL. Payments are recorded, not transferred. External messaging, gateway integration and verified business assets remain configuration/integration work.
