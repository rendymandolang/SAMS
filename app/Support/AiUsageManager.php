<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AiUsageManager
{
    public function settings(int $companyId): object
    {
        DB::table('ai_company_settings')->insertOrIgnore([
            'company_id' => $companyId, 'is_enabled' => true, 'allow_external_provider' => false,
            'monthly_request_limit' => 100, 'monthly_token_limit' => 100000, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('ai_company_settings')->where('company_id', $companyId)->first();
    }

    public function usage(int $companyId): array
    {
        $start = now()->startOfMonth();
        $runs = DB::table('ai_insight_runs')->where('company_id', $companyId)->where('created_at', '>=', $start);
        $interactions = DB::table('ai_interactions')->where('company_id', $companyId)->where('created_at', '>=', $start);

        return [
            'requests' => (clone $runs)->count() + (clone $interactions)->count(),
            'tokens' => (int) ((clone $runs)->selectRaw('COALESCE(SUM(COALESCE(input_tokens,0)+COALESCE(output_tokens,0)),0) total')->value('total') ?? 0)
                + (int) ((clone $interactions)->selectRaw('COALESCE(SUM(COALESCE(input_tokens,0)+COALESCE(output_tokens,0)),0) total')->value('total') ?? 0),
        ];
    }

    public function ensureAllowed(int $companyId, bool $external = false): void
    {
        $settings = $this->settings($companyId);
        $usage = $this->usage($companyId);
        if (! $settings->is_enabled) {
            throw ValidationException::withMessages(['ai' => 'AI dinonaktifkan untuk perusahaan ini.']);
        }
        if ($external && ! $settings->allow_external_provider) {
            throw ValidationException::withMessages(['ai' => 'Provider eksternal belum diizinkan untuk perusahaan ini.']);
        }
        if ($usage['requests'] >= $settings->monthly_request_limit) {
            throw ValidationException::withMessages(['ai' => 'Batas request AI bulanan sudah tercapai.']);
        }
        if ($usage['tokens'] >= $settings->monthly_token_limit) {
            throw ValidationException::withMessages(['ai' => 'Batas token AI bulanan sudah tercapai.']);
        }
    }
}
