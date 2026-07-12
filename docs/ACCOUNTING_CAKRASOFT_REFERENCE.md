# Accounting Reference Map

Reference source (read-only): `F:\BACKUP WD\BUNAKEN OASIS\Cakrasoft Hotel Suite\CASHTML`

## Confirmed Cakrasoft Patterns

- Journal header: date, company, memo, and adjustment flag.
- Journal detail: sub department, GL account, amount, remark, multiple debit/credit rows.
- Save requires equal debit and credit.
- Journal register includes reference number, date, department, account, debit, credit, memo, type, and last update.
- Reports include Journal, General Ledger, Trial Balance, Balance Sheet, Profit & Loss, and department variants.
- Account Payable supports invoice/company, expense account, AP account, due date, partial payment, outstanding balance, and payment journal.
- Close Month prevents normal posting into a closed accounting period and requires controlled reopening.

## SAMS Improvements

- Web-first responsive interface with company isolation and role permissions.
- Immutable posted journal direction; changes will use controlled reversal instead of deleting ledger history.
- Explicit audit events for create and post.
- Server-side double-entry validation and accounting period lock.
- A4 Journal Voucher print with company identity, clear totals, and Prepared/Checked/Approved blocks.
- Future AP entries will connect approved procurement and Goods Receipt without duplicate posting.

The Cakrasoft source is used as workflow and report reference. SAMS does not copy its branding, proprietary assets, or desktop interface.
