<?php

namespace App\Http\Controllers;

use App\Support\AiInsightService;
use App\Support\AiNarrativeService;
use App\Support\AiQueryService;
use App\Support\AiUsageManager;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class AiInsightController extends Controller
{
    public function index(CompanyContext $context, AiInsightService $service, AiUsageManager $usageManager): View
    {
        $company = $context->current();
        $runs = DB::table('ai_insight_runs')->where('company_id', $company->id)->orderByDesc('id')->limit(10)->get()
            ->map(function (object $run): object { $run->output = json_decode($run->output, true) ?: []; return $run; });
        $interactions = DB::table('ai_interactions')->where('company_id', $company->id)->orderByDesc('id')->limit(10)->get();

        return view('ai-insights.index', ['company' => $company, 'snapshot' => $service->snapshot((int) $company->id), 'runs' => $runs, 'interactions' => $interactions, 'aiSettings' => $usageManager->settings((int) $company->id), 'aiUsage' => $usageManager->usage((int) $company->id)]);
    }

    public function generate(CompanyContext $context, AiInsightService $service): RedirectResponse
    {
        try {
            $service->generate((int) $context->id());
        } catch (Throwable $exception) {
            report($exception);
            return back()->withErrors(['ai' => 'Insight gagal dibuat: '.$exception->getMessage()]);
        }

        return back()->with('status', 'Insight operasional berhasil dibuat.');
    }

    public function query(\Illuminate\Http\Request $request, CompanyContext $context, AiQueryService $service): RedirectResponse
    {
        $validated = $request->validate(['question' => ['required', 'string', 'max:500']]);
        $service->ask((int) $context->id(), $validated['question']);
        return back()->with('status', 'Pertanyaan berhasil dijawab dari data perusahaan aktif.');
    }

    public function narrative(CompanyContext $context, AiNarrativeService $service): RedirectResponse
    {
        $service->generate((int) $context->id());
        return back()->with('status', 'Narrative report berhasil dibuat.');
    }

    public function updateSettings(\Illuminate\Http\Request $request, CompanyContext $context, AiUsageManager $usageManager): RedirectResponse
    {
        $validated = $request->validate([
            'monthly_request_limit' => ['required', 'integer', 'min:1', 'max:100000'],
            'monthly_token_limit' => ['required', 'integer', 'min:1000', 'max:1000000000'],
        ]);
        $settings = $usageManager->settings((int) $context->id());
        DB::table('ai_company_settings')->where('id', $settings->id)->update([
            'is_enabled' => $request->boolean('is_enabled'),
            'allow_external_provider' => $request->boolean('allow_external_provider'),
            ...$validated, 'updated_at' => now(),
        ]);
        \App\Support\AuditLogger::log('ai_settings_updated', 'ai_company_setting', (int) $settings->id, null, $validated + ['is_enabled' => $request->boolean('is_enabled'), 'allow_external_provider' => $request->boolean('allow_external_provider')], (int) $context->id());
        return back()->with('status', 'Pengaturan AI berhasil diperbarui.');
    }
}
