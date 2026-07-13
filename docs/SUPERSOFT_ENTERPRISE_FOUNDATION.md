# SuperSoft Enterprise Foundation

## Official identity

- Product: **SuperSoft Enterprise**
- Domain: **supersoft.id**
- Company: **PT Supersoft Global Investama**

## Product suite

- SaMS — Super Asset Management System
- SaS — Super Accounting System
- SPoS — Super Point of Sale
- SHMS — Super Hotel Management System
- SHRiS — Super Human Resource Information System

Laravel remains the secure transactional core. Interactive Vue components may be introduced for real-time operational screens. The platform will not mix Vue and React without a specific architectural need.

## Security baseline

- Laravel ORM/query binding for user-supplied values.
- CSRF protection on state-changing web requests.
- Per-company module entitlements and role permissions.
- Login throttling by normalized email and IP address.
- Browser security headers, private authenticated responses, and production HSTS.
- Encrypted session storage is the production default.
- Private attachments with authorized application downloads.
- Audit trail for sensitive business operations.

Security is an ongoing release gate. Dependency audits, automated tests, authorization review, backup/restore testing, and infrastructure hardening remain mandatory before production deployment.

## Clean local installation

The regular `DatabaseSeeder` remains a deliberate test/demo fixture. A clean installation uses `FreshInstallationSeeder`, which creates only PT Supersoft Global Investama, one Head Office branch, and the initial administrator. It does not create suppliers, items, budgets, transactions, or COA.

Before running a clean installation, set `INITIAL_ADMIN_PASSWORD` in `.env`. Production installation refuses an empty initial password.
