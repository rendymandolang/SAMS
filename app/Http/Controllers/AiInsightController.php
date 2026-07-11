<?php

namespace App\Http\Controllers;

use App\Support\AiInsightService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class AiInsightController extends Controller
{
    public function index(CompanyContext $context, AiInsightService $service): View
    {
        $company = $context->current();
        $runs = DB::table('ai_insight_runs')->where('company_id', $company->id)->orderByDesc('id')->limit(10)->get()
            ->map(function (object $run): object { $run->output = json_decode($run->output, true) ?: []; return $run; });

        return view('ai-insights.index', ['company' => $company, 'snapshot' => $service->snapshot((int) $company->id), 'runs' => $runs]);
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
}
