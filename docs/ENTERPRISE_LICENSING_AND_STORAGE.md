# Enterprise Licensing and Storage

## Commercial control

SuperSoft separates commercial entitlement from company activation:

1. `is_licensed` records whether a module is included in the company's contract.
2. `is_enabled` records whether the licensed module is active for the company.
3. `licensed_until` can limit an individual module.
4. The company subscription controls suite-level status, expiry, and grace period.
5. Enterprise Core remains available when a subscription expires so authorized users can review settings and recover or export data.

Company administrators cannot activate an unlicensed or expired module. License changes are intentionally not exposed in the company administration UI; they must be issued by the SuperSoft commercial or installation process.

## License models

The data model supports:

- trial;
- annual or monthly subscription;
- perpetual license;
- internal SuperSoft installation;
- grace period after expiry;
- user, branch, and storage limits;
- per-module expiry.

## Storage modes

- **Local / On-Premise** stores private documents on the application server.
- **BYOC** stores connection details for an S3-compatible cloud owned by the customer.
- **SuperSoft Managed Cloud** reserves configuration for storage operated by SuperSoft.

BYOC credentials are encrypted using the Laravel application encryption key. Plain credentials are not stored in audit logs and are never rendered back to the browser.

The S3-compatible connector supports AWS S3 and providers that expose the standard S3 protocol. Path-style addressing can be enabled for compatible providers. A cloud configuration remains pending until SuperSoft verifies upload, read, and delete operations against the configured bucket. Endpoints that resolve to local, private, or reserved networks are rejected to reduce server-side request forgery risk.

SuperSoft does not silently fall back to local storage when a selected external provider is unavailable, because that could violate the customer's chosen data-location policy.

## Storage accounting

Private attachments and supplier catalog uploads use the company storage profile. Capacity is reserved under a database lock before an upload begins, so concurrent uploads cannot exceed the configured quota. Failed uploads release their reservation. Deleting an attachment also returns its capacity to the company.

Each company receives an isolated root prefix in the form `companies/{company_id}`. Supplier catalogs stored in cloud storage are streamed to a temporary private file for scanning and removed immediately afterward. Existing document rows retain their disk and path; a controlled provider-migration workflow is still required before replacing credentials for a cloud profile that already contains files.

## Remaining implementation stages

- Add encrypted backup destinations and restore testing.
- Add quota warnings at 70%, 85%, and 95%.
- Add managed-cloud metering and invoice calculation.
- Add a controlled migration workflow between storage providers.
