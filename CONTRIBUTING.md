# SuperSoft Engineering Standards

## Development principles

1. Business rules belong in explicit services, requests, policies, or domain-support classes.
2. Controllers coordinate requests and responses; they must not become unreviewable transaction scripts.
3. Every company-owned record must enforce company scope on reads and writes.
4. Financial and inventory history is reversed with contra entries, never silently deleted.
5. Every posting operation must be atomic, idempotent, authorized, auditable, and protected by period locks.
6. External API failures must not make core operational modules unavailable.
7. Secrets and customer data never enter source control, logs, fixtures, or error pages.

## Coding style

- Follow PSR-12 and Laravel conventions.
- Use descriptive class, method, and variable names.
- Use strict validation for every user-controlled input.
- Bind query values; never concatenate user input into SQL fragments.
- Prefer small methods with one clear responsibility.
- Comments explain business reasons and constraints, not obvious syntax.
- Use Indonesian or English consistently within one user-facing workflow.

## Required review

Changes affecting authentication, authorization, licensing, financial posting, payroll, guest data, payment, file upload, or external integrations require a dedicated security review and automated regression tests.

## Definition of done

- Formatting check passes.
- Automated tests pass.
- Dependency audit has no unresolved critical vulnerability.
- Database migration has a safe forward path.
- Authorization and company isolation are verified.
- User-facing text and print output are reviewed.
- Documentation is updated when behavior or configuration changes.
