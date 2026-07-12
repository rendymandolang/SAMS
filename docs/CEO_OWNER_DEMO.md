# SAMS CEO & Owner Demo

Release: SAMS v1.0.1  
Developer: Rendy Mandolang, SE., MM., CPA., CHCGM.

## Recommended 8-Minute Flow

1. **Professional login** — explain secure, role-based entry and company isolation.
2. **Executive Dashboard** — show purchasing flow, budget usage, asset health, and maintenance closure.
3. **Procurement control** — open a Purchase Request and explain PR → approval → PO → Goods Receipt.
4. **Supplier Budget AI** — upload/publish a catalog, compare a requirement, show recommended supplier and potential savings, then record a decision.
5. **Inventory & assets** — show Stock on Hand, Stock Opname, asset register, and maintenance prediction.
6. **AI Insight Center** — show read-only operational insight and live BRI reference rates.
7. **Audit & governance** — show Audit Logs, period locks, permissions, and Data Connections.
8. **Closing** — explain that the local MVP is ready for controlled pilot preparation before VPS deployment.

## Key Messages

- SAMS connects purchasing, inventory, budgeting, assets, maintenance, approvals, reporting, and auditable AI assistance.
- AI recommendations do not post transactions or approve spending automatically.
- Every company is isolated, actions follow role permissions, and important decisions create audit records.
- Supplier intelligence accepts CSV, Excel, and PDF catalogs across food, ATK, furniture, and other categories.

## Demo Safety Checklist

- Use only sample data; do not display real API keys, passwords, personal data, or confidential supplier agreements.
- Verify Laragon, MySQL, and the SAMS application are running before the meeting.
- Open login, dashboard, supplier catalogs, AI Insight Center, and Data Connections once before presenting.
- Keep the administrator credential outside the presentation screen.
- If live API data is unavailable, explain the soft fallback and continue with local modules.

## Current External Dependencies

- API.co.id bank-rate connection is active locally and cached for 30 minutes.
- Official BRIAPI requires partner sandbox credentials before activation.
- Google Sheets synchronization requires Google authorization before activation.
- API keys must be rotated if exposed and must remain only in the server `.env`.
