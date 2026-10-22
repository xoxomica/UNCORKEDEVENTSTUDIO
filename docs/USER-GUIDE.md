# Uncorked: operator guide

## First launch

1. Start Apache in XAMPP, then visit `http://localhost/Uncorked%20Event%20Studio/public/`.
2. Open `http://localhost/Uncorked%20Event%20Studio/public/login.php` directly, choose first-time setup, and create your administrator account on this computer. The public landing page does not link to staff sign-in. No default password is supplied.
3. Sign in. Create the actual venue/table resources and their guest capacities under **Venues & tables**. Only resources marked Active can receive online requests.
4. Add the actual event packages and prices. Add verified address, contact details, opening hours, and Facebook URL under Business settings.
5. Add products, suppliers, and employees. Record an opening stock movement or receive a purchase before recording sales.
6. Create Manager or Staff accounts if needed. Use a separate account for each operator.

Alternative development server: run `php -S 127.0.0.1:8091 -t public` from the project folder and visit `http://127.0.0.1:8091/`.

## Reservation lifecycle

Customers choose an occasion, date, same-day time range, guest count, venue/table resource, and optional package. Availability checking reveals only whether an overlapping confirmed event exists; it does not expose customer records. Sending the form creates a Pending request, customer profile, reference number, and audit entry. A package price is copied into the event total at submission so later package changes do not rewrite old quotes.

The reservation desk lets authorized staff review the request, assign/change a venue or package, set the agreed event total, and adjust the schedule while Pending or Cancelled. Changing the selected package in the desk does not automatically change an agreed price; enter the correct total explicitly. Confirmation checks capacity and overlapping reservations in one database transaction. Events using different resource records may overlap; one resource cannot have overlapping Confirmed or Completed reservations. For exclusive whole-venue bookings, use a single venue record. Do not model tables and the whole venue as independent bookable resources until their blocking rules are defined.

Return a reservation to Pending before editing its schedule. Staff assignments should be removed and recreated when the event schedule changes. Complete events after service, cancel unused requests, and archive finished records for reference. Customer status lookup requires both the reference and the original booking email.

## Payments and sales

Managers and administrators can record verified event deposits and balances. The system rejects amounts exceeding the agreed event total. These are records of money received; the website does not initiate a bank/e-wallet transfer. Download/print an operational event statement from the reservation desk.

Bar orders are recorded as individual product sale lines. Each line snapshots the product price and deducts stock immediately, including unpaid orders. Mark unpaid sales Paid after collection; this records the collection timestamp without changing the order date. Repeating Mark paid does not change that timestamp. These sales are separate from event package totals: avoid entering the same charge in both ledgers. Received purchases add stock and retain supplier, product, cost, and reference history. Opening balances, consumption, wastage, and corrections use Stock movements with a reason. Negative inventory and overselling are rejected.

## Roles

| Role | Access |
|---|---|
| Administrator | All modules, accounts, settings, activity log, backup |
| Manager | Operational modules, payments, master data, reports |
| Staff | Overview, reservations, customers, inventory movements, sales, staff assignments, notifications |

Deactivating a user removes access on the next request. Employee records and login accounts are separate: an employee may be assigned to events without having system access.

## Reports and backup

Reports filter by a selected date range, display collections, received purchase costs, product sales and reservation statuses, and support printing and reservation CSV export. Reservation statistics use event dates; bar collections use collection dates, event payments and purchases use their recorded transaction dates, and product sales use original order dates. Older paid sales without a known collection date remain in all-time recorded collections but are excluded from period bar collections, with a notice showing their count and total. Collections are operational figures, not accounting profit. Notifications are live in-app lists of pending requests, upcoming events, low stocks and outstanding event balances.

Administrator-only database backup downloads a consistent snapshot. For restoration, stop the website and run `php database/restore.php path-to-backup.sqlite`. The restore utility validates database integrity and required tables, stages the replacement, and retains a timestamped safety copy. Backups include private business/customer information; keep them in protected storage.

## Current boundaries

The website has original branding typography and illustrative stock images pending actual business assets. This implementation uses SQLite through PDO for an immediately runnable local installation; it does not connect to MySQL. The full SQL schema is included in `database/schema.sql` for review.

There is no external email/SMS service, online payment gateway, salary/payroll calculation, tax filing, statutory accounting, hardware integration, or multi-branch routing. Sales/payment records are append-only in this version; refunds, payment reversals, item returns, and multi-item checkout receipts need additional business rules. Events cannot cross midnight. Stock quantities are whole units. Employee assignments record duties on event dates. Public customer registration/login is not included; lookup uses reference plus email. Real-world opening hours, cancellation policies, package terms, venue/table blocking relationships, and business assets must be validated with Uncorked before live use.
