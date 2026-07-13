# SuperSoft Security Policy

Security issues must be reported privately to PT Supersoft Global Investama. Do not publish credentials, customer data, exploit details, or unpatched vulnerabilities in a public issue.

## Supported environments

Only actively maintained SuperSoft Enterprise releases running on supported PHP, Laravel, database, and operating-system versions receive security updates.

## Production requirements

- HTTPS is mandatory.
- `APP_ENV=production` and `APP_DEBUG=false` are mandatory.
- Application, database, object-storage, email, and integration credentials must be unique per environment.
- Database users receive only the privileges required by the application.
- Web server document root must point to `public/`.
- Queue workers and scheduled jobs run as restricted operating-system users.
- Backups must be encrypted and restore-tested.
- Logs must not contain passwords, API keys, payment details, or identity documents.
- Administrator accounts must use strong unique passwords and multi-factor authentication when available.

## Release security checks

- Automated application tests
- Composer dependency audit
- npm dependency audit
- Authorization and company-isolation review
- File-upload and download authorization review
- SQL query-binding review
- Backup and recovery validation
- Web server and database hardening review

No software can be guaranteed free from all defects or vulnerabilities. Security is treated as a continuous release requirement, not a one-time checklist.
