# Inventory and Asset Request Management System (IARMS)

**Internship project at the National Bank of Ethiopia | June-August 2026**

IARMS is a locally demonstrated PHP/MySQL web application for asset requests and inventory management. The internship work covered analysis, design, and a local demonstration of the system.

## What the project demonstrates

- Role-based portals for employees, department heads, inventory, procurement, HR, and administrators
- An asset-request review workflow with status history
- Inventory tracking for stock receipt, issue, and asset returns
- Procurement reports with CSV export
- Administrative controls and an audit log

## Technology

PHP 8, MySQL/MariaDB, PDO, HTML5, CSS3, Bootstrap 5, and Bootstrap Icons. The project was demonstrated locally; it was not deployed to a production environment.

## Local setup

1. Use a local PHP 8 and MySQL/MariaDB environment such as XAMPP.
2. Copy `config/config.example.php` to `config/config.php` and set your local database connection values. Never commit the local config file.
3. Import `database/schema.sql` into a disposable development database.
4. Add your own development-only test data and users before logging in. Demo seed data and accounts are intentionally not included.
5. Open the project through your local web server, for example `http://localhost/iarms/`.

> **Important:** `database/schema.sql` drops and recreates the `iarms_db` database. Use it only in a disposable local development environment; it will destroy existing data in that database. This project is a local internship demonstration, not a production deployment.

## Repository notes

- `config/config.php`, seeded demo accounts, and demo passwords are excluded.
- `config/config.example.php` is a placeholder template; replace its values locally.
- Do not use real employee or Bank data in a local copy.
- Bootstrap and Bootstrap Icons retain their upstream license notices in the bundled CSS files.
