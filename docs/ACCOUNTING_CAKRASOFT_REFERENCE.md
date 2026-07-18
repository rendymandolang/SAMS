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
- Immutable posted journal direction with controlled, single-use reversal instead of deleting ledger history.
- Explicit audit events for create and post.
- Server-side double-entry validation and accounting period lock.
- A4 Journal Voucher print with company identity, clear totals, and Prepared/Checked/Approved blocks.
- Supplier invoice and payment entries post through the SaS subledger. Direct PO/GR accrual matching remains a controlled next stage.

## Implemented SaS controls

- Company-owned COA with duplicate-code prevention and similar-name warning.
- Posting accounts must be active detail accounts; header accounts cannot receive journal lines.
- Each journal line must use exactly one side: debit or credit.
- Collision-resistant monthly Journal Voucher numbering under a database lock.
- Posted journals can be reversed once. SaS creates and posts an opposite Journal Voucher, links both records, keeps original lines unchanged, and records the reason in the audit trail.
- Reversal dates cannot precede the original journal and must fall in an open accounting period.
- Accounts Payable supports supplier invoice drafts, posting, aging, partial payment, final settlement, payment allocation, and linked General Ledger journals.
- Supplier invoice posting debits company-selected expense/asset and input-tax accounts, then credits the selected liability account.
- Supplier payment posting debits Accounts Payable and credits the selected cash/bank account.
- Subledger journals cannot be reversed directly from General Ledger because that would desynchronize AP balances.
- Accounts Receivable supports company-owned customer master data, customer invoice posting, aging, partial receipt, final settlement, and receipt allocation history.
- Bank reconciliation links each bank account to one GL account, imports duplicate-safe statement files, and locks completed reconciliations with a full audit trail.
- Tax codes and posting rules remain company-owned so each business can map its own COA without forced templates.
- PO, posted Goods Receipt, and supplier invoice lines are matched before AP posting using company-controlled tolerances.
- Credit notes, payment/receipt reversals, and fiscal-year reopen operations always create linked reversal journals instead of deleting ledger history.
- Customer invoices debit the selected receivable account and credit company-selected revenue plus output-tax accounts.
- Customer receipts debit cash/bank and credit Accounts Receivable through linked posted journals.
- Cash Flow uses company-controlled account classifications rather than assuming account codes.
- Journal Register and Department Profit & Loss preserve the report patterns used by hotel operations while adding web filters and print-ready output.
- Recurring journals create drafts for finance review and never bypass the normal posting permission or period lock.
- Foreign invoices preserve original currency evidence while every posted journal remains balanced in company base currency.
- Settlement-date differences post to configured realized FX accounts; period-end open balances post to configured unrealized FX accounts.

The Cakrasoft source is used as workflow and report reference. SAMS does not copy its branding, proprietary assets, or desktop interface.
