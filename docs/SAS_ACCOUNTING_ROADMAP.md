# SaS Accounting Roadmap

## Available now

- Company-managed Chart of Accounts without forced templates.
- Duplicate and similar-account controls.
- Draft and posted Journal Vouchers.
- Controlled reversal with immutable ledger history.
- General Ledger, Trial Balance, Profit & Loss, and Balance Sheet.
- Monthly close and controlled reopen.
- Company, module, permission, and audit isolation.
- Supplier invoices and Accounts Payable aging.
- Partial and final supplier payments with allocation history.
- Automatic balanced journals for AP invoices and payments.
- Professional Supplier Invoice print layout.
- Company customer master, customer invoices, and Accounts Receivable aging.
- Partial and final customer receipts with allocation history.
- Automatic balanced journals and professional Customer Invoice print layout.
- Bank account to GL mapping and duplicate-safe CSV statement import.
- Conservative automatic matching, controlled manual matching, exclusions with audit reasons, and locked completion.
- Bank reconciliation difference control between statement balance and General Ledger book balance.
- Company-owned purchase, sales, and withholding tax codes with automatic calculation and linked GL accounts.
- Configurable posting defaults for AP, AR, cash/bank, revenue, expense, and retained earnings roles.
- Procurement-to-AP three-way matching with company price and quantity tolerances.
- AP and AR credit notes that reduce outstanding balances through immutable posted journals.
- Controlled supplier-payment and customer-receipt reversal with allocation restoration and bank-match protection.
- Fiscal-year closing to retained earnings, full-year locking, and controlled reopen through reversal journal.
- Company-configured cash/bank and operating, investing, or financing classifications without forcing a COA template.
- Direct cash-flow statement with opening balance, activity detail, net movement, and closing cash control.
- Detailed Journal Register and department-filtered Profit & Loss.
- Monthly, quarterly, and yearly recurring journal templates that generate reviewable drafts with duplicate-safe run history.
- Date-effective company exchange-rate register with strict missing-rate rejection.
- Foreign-currency AP and AR invoices retaining transaction-currency and base-currency values in the subledger and General Ledger.
- Supplier payments and customer receipts at settlement-date rates with automatic realized FX gain or loss posting.
- Period-end revaluation of open foreign AP/AR balances with immutable, duplicate-safe unrealized FX journals.
- Multi-entity consolidation groups restricted to companies accessible by the user.
- Automatic entity COA mapping with type-conflict protection and presentation-currency translation.
- Balanced intercompany elimination entries, immutable finalization, and full audit history.
- Consolidated Trial Balance, period Profit & Loss, Balance Sheet, and entity/elimination drill-down.

## Accounting core status

The planned SaS enterprise accounting scope is complete: ledger, AP, AR, bank reconciliation, taxation controls, closing, reporting, automation, multi-currency, FX revaluation, and entity consolidation. Localization packs, electronic tax filing connectors, and jurisdiction-specific statutory forms are optional integrations because their rules and credentials depend on the deployment country and customer.

Every subledger document must post through balanced Journal Vouchers, remain company-scoped, respect period locks, and use reversal rather than destructive changes after posting.
