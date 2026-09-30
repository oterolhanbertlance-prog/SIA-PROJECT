# Finance — Offline Localhost System

Finance is a self-contained PHP/MySQL accounting layer designed to run beside the supplied HRMS and Milk Tea POS projects on a Windows XAMPP installation.

## Install in XAMPP

1. Copy this folder to `C:\xampp\htdocs\finance\`.
2. Start **Apache** and **MySQL** in XAMPP.
3. Open `http://localhost/phpmyadmin/`.
4. From the package root, import `database/combined_database_install.sql` once. It creates all three databases and seeds the Finance chart of accounts and roles:
   - `finance_system`
   - `hrms`
   - `milk_tea_pos`
5. Copy `config/local.php` to the target Finance folder if needed and set the three local database names, users, and passwords:
   - Finance: `finance_system`
   - HRMS: `hrms`
   - Milk Tea POS: `milk_tea_pos`
6. Open `http://localhost/finance/`.
7. Complete the one-time administrator setup, then sign in.
8. Open **Integration**, test both connections, and run the initial sync.

For a complete copy-paste installation guide, see `../README_LOCALHOST.txt`.

The supplied schemas were inspected and the integration reads these source tables without updating them:

- HRMS: `employees`, `departments`, `positions`, `payroll`
- Milk Tea POS: `transactions`, `transaction_details`, `products`, `categories`, `ingredients`, `product_ingredients`, `cash_fund`

## Accounting behavior

- Completed POS sales create balanced journals: Cash, Bank, or E-Wallet debit and Sales Revenue credit.
- HRMS payroll creates balanced journals: Payroll Expense debit against net payroll and the supplied payroll deduction fields.
- Every imported record uses `source_system` + `source_id` as an idempotency key. Re-running sync skips records already imported.
- Source databases are connected with separate PDO objects and are treated as read-only.
- Manual journals cannot post unless the debit and credit amount are equal.
- The optional `sync.php` script can be scheduled through Windows Task Scheduler:

```text
C:\xampp\php\php.exe C:\xampp\htdocs\finance\sync.php
```

## Offline and privacy guarantees

- Bootstrap and the Finance styles/scripts are local files.
- No CDN, Google Fonts, external API, cloud database, or online authentication is used.
- Access uses a short-lived PHP session only. No remember-me, tracking, or custom persistent cookies are created.
- Credentials are kept in `config/local.php` or environment variables and are never printed to the browser.
- Use phpMyAdmin Export to create a local backup of `finance_system` before restore operations.

## Project map

`config/` contains local PDO connection settings. `database/finance.sql` is the importable schema. `services/IntegrationService.php` contains the read-only HRMS/POS bridge. Root PHP pages provide the public landing page, dashboard, integration controls, imported registers, journals, reports, chart of accounts, settings, and the administrator-only `users.php` access area.

## Landing page, themes, and access

- Opening `http://localhost/finance/` shows the public Finance landing page. Signed-in users go directly to the dashboard.
- The moon/sun button switches between light and dark mode. The preference is stored locally in the browser only; no persistent cookie or external service is used.
- **Admin & Employees** is available to users with the `settings.manage` permission. Super administrators can create Finance accounts, assign roles, activate/deactivate accounts, and reset passwords.
- The same page includes a read-only employee directory loaded from the HRMS `employees`, `departments`, and `positions` tables. Finance never writes to HRMS employee records.

## Added operations modules

- **Mailer** provides an auditable, local-first outbox for payroll notices, collection reminders, and finance updates. Messages are queued in Finance and do not leave the computer until a local mail transport is configured.
- **GPS & Branches** provides a privacy-friendly location register for cash collections, branch audits, deliveries, and bank deposits. Coordinates are optional and stored only in Finance.
- **Attendance** is a local timekeeping register for NCST Main Campus · Dasmariñas, Cavite. It supports time-in, time-out, late/leave/half-day status, and an absent/unmarked employee list based on the synced HRMS employee directory.
- Import the updated `database/finance.sql` when upgrading an existing installation so the `mail_queue`, `location_logs`, and new permissions are created.
- The Mailer, GPS, and Attendance pages also run a safe first-access migration for these optional tables, so an older Finance database can open the modules without a fatal “table does not exist” error.

## If Apache says “Not Found”

The ZIP contains a folder named `finance`. Extract that exact folder into `C:\xampp\htdocs\` so these files exist:

```text
C:\xampp\htdocs\finance\index.php
C:\xampp\htdocs\finance\dashboard.php
C:\xampp\htdocs\finance\attendance.php
```

Then open `http://localhost/finance/` (not `http://localhost/finance-improved/`). If you changed the folder name, use that folder name in the URL. Start Apache and confirm that the Finance folder is inside `htdocs`, not beside it.

## v2 connected HRMS/POS integration

- Finance now maintains a branch mapping between the existing HRMS and POS branch tables.
- Branch code, address, city, GPS coordinates, status and source IDs are copied into Finance; HRMS/POS remain source systems.
- Active HRMS and POS staff identities can be synchronized to Finance. The same source username/password can sign in; Finance creates only a local role/permission shadow.
- HRMS role mapping: admin → Finance super administrator, HR → Finance manager, employee → viewer.
- POS role mapping: super admin → Finance manager; cashier/inventory staff → Finance staff. Customer accounts are never granted Finance access.
- `branches.php` shows the HRMS ↔ Finance ↔ POS mapping.
- Existing Finance-only administrator accounts continue to work.
- Existing databases are upgraded safely on access; `database/migration_v2_connected_hrms_pos.sql` is also supplied for manual/phpMyAdmin upgrades.
