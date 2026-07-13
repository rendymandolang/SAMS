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

Cloud configurations remain in a pending state until a provider connector performs a real connection test. SuperSoft does not silently fall back to local storage when a selected external provider is unavailable, because that could violate the customer's chosen data-location policy.

## Storage accounting

Private attachments and supplier catalog uploads use the company storage profile and increment `used_bytes`. Each company receives an isolated root prefix in the form `companies/{company_id}`. Existing document rows retain their disk and path so they remain downloadable after storage configuration changes.

## Next implementation stage

- Install the S3-compatible filesystem adapter.
- Perform signed connection tests against the configured endpoint and bucket.
- Add encrypted backup destinations and restore testing.
- Add quota warnings at 70%, 85%, and 95%.
- Add managed-cloud metering and invoice calculation.
- Add a controlled migration workflow between storage providers.
