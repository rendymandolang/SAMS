<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class AuditLogger
{
    public static function log(string $event, string $auditableType, int $auditableId, ?array $oldValues = null, ?array $newValues = null, ?int $companyId = null): void
    {
        DB::table('audit_logs')->insert([
            'company_id' => $companyId ?? self::companyId(),
            'user_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'old_values' => $oldValues === null ? null : json_encode($oldValues),
            'new_values' => $newValues === null ? null : json_encode($newValues),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'correlation_id' => null,
            'created_at' => now(),
        ]);
    }

    private static function companyId(): ?int
    {
        if (! auth()->check()) {
            return null;
        }

        return app(CompanyContext::class)->id();
    }
}
