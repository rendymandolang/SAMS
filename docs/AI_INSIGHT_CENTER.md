# SAMS AI Insight Center

## Local-first strategy

AI Insight Center is built and validated locally. VPS deployment does not change its architecture; it only supplies production environment variables, queue capacity, network access, monitoring, and usage limits.

The default `local` driver requires no API key. It produces deterministic, auditable operational insights for budget utilization, pending approvals, negative stock, and overdue maintenance.

Predictive analytics included locally:

- 90-day stock consumption, days-of-cover, and recommended reorder quantity;
- purchase price deviation against item history;
- supplier risk scoring from lateness, rejection rate, and incomplete orders;
- maintenance prediction from asset condition, event frequency, overdue work orders, and historical intervals.

Predictions expose their confidence and return an explicit insufficient-data state when history is not adequate. They are decision support, not automatic transactions.

## OpenAI provider

Set these only in the local or server `.env` file:

```dotenv
AI_DRIVER=openai
OPENAI_API_KEY=
OPENAI_MODEL=gpt-5-mini
OPENAI_TIMEOUT=45
```

Never commit the API key. The model name remains configurable so it can be upgraded without code changes.

## Guardrails

- AI is read-only and cannot approve, post, reverse, delete, or modify a transaction.
- Every snapshot is filtered by the active company.
- Module entitlement and `intelligence.view` / `intelligence.generate` permissions apply before access.
- Each successful or failed run stores provider, model, input snapshot, output, token usage when available, user, company, timestamp, and audit event.
- Only aggregated operational data is sent to an external provider; passwords, tokens, attachments, and personal documents are excluded.
- Recommendations must still be reviewed by an authorized person.

## Migration to VPS

1. Deploy the same release commit.
2. Run migrations and access-control provisioning.
3. Configure provider secrets directly in the VPS `.env`.
4. Run `php artisan optimize`.
5. Test AI with a non-production company first.
6. Monitor failed runs, latency, tokens, and provider cost before broad enablement.
