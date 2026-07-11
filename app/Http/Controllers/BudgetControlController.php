<?php

namespace App\Http\Controllers;

use App\Support\CsvExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BudgetControlController extends Controller
{
    public function index(Request $request): View
    {
        return view('budget_control.index', $this->data($request));
    }

    public function print(Request $request): View
    {
        return view('budget_control.print', $this->data($request));
    }

    public function export(Request $request): StreamedResponse
    {
        $data = $this->data($request);

        return CsvExporter::download('budget-control-'.now()->format('Ymd-His').'.csv', [
            'Department Code',
            'Department Name',
            'Budget',
            'Period Start',
            'Period End',
            'Account Code',
            'Description',
            'Allocated',
            'Committed',
            'Actual',
            'Used',
            'Remaining',
            'Used Percent',
            'Status',
        ], $data['lines']->map(fn (object $line) => [
            $line->department_code,
            $line->department_name,
            $line->budget_name,
            $line->period_start,
            $line->period_end,
            $line->account_code,
            $line->description,
            (float) $line->allocated_amount,
            (float) $line->committed_amount,
            (float) $line->actual_amount,
            (float) $line->used_amount,
            (float) $line->remaining_amount,
            round((float) $line->used_percent, 2),
            $line->control_status,
        ]));
    }

    private function data(Request $request): array
    {
        $company = DB::table('companies')->where('is_active', true)->orderBy('id')->firstOrFail();
        $branch = DB::table('branches')->where('is_active', true)->orderBy('id')->first();
        $departmentId = $request->integer('department_id') ?: null;
        $budgetId = $request->integer('budget_id') ?: null;

        $departments = DB::table('departments')
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        $budgets = DB::table('budgets')
            ->join('departments', 'departments.id', '=', 'budgets.department_id')
            ->where('budgets.company_id', $company->id)
            ->when($departmentId, fn ($query) => $query->where('budgets.department_id', $departmentId))
            ->select('budgets.*', 'departments.code as department_code', 'departments.name as department_name')
            ->orderBy('departments.code')
            ->orderByDesc('budgets.period_start')
            ->get();

        $lines = DB::table('budget_lines')
            ->join('budgets', 'budgets.id', '=', 'budget_lines.budget_id')
            ->join('departments', 'departments.id', '=', 'budgets.department_id')
            ->where('budgets.company_id', $company->id)
            ->when($departmentId, fn ($query) => $query->where('budgets.department_id', $departmentId))
            ->when($budgetId, fn ($query) => $query->where('budgets.id', $budgetId))
            ->select(
                'budget_lines.*',
                'budgets.name as budget_name',
                'budgets.period_start',
                'budgets.period_end',
                'budgets.status as budget_status',
                'departments.code as department_code',
                'departments.name as department_name',
            )
            ->orderBy('departments.code')
            ->orderBy('budget_lines.account_code')
            ->get()
            ->map(function (object $line) {
                $allocated = (float) $line->allocated_amount;
                $committed = (float) $line->committed_amount;
                $actual = (float) $line->actual_amount;
                $used = $committed + $actual;
                $remaining = $allocated - $used;
                $usedPercent = $allocated > 0 ? min(999, ($used / $allocated) * 100) : 0;
                $actualPercent = $allocated > 0 ? min(999, ($actual / $allocated) * 100) : 0;

                $line->used_amount = $used;
                $line->remaining_amount = $remaining;
                $line->used_percent = $usedPercent;
                $line->actual_percent = $actualPercent;
                $line->control_status = match (true) {
                    $remaining < 0 => 'over',
                    $usedPercent >= 90 => 'critical',
                    $usedPercent >= 75 => 'watch',
                    default => 'healthy',
                };

                return $line;
            });

        $summary = [
            'allocated' => $lines->sum(fn (object $line) => (float) $line->allocated_amount),
            'committed' => $lines->sum(fn (object $line) => (float) $line->committed_amount),
            'actual' => $lines->sum(fn (object $line) => (float) $line->actual_amount),
            'remaining' => $lines->sum(fn (object $line) => (float) $line->remaining_amount),
            'line_count' => $lines->count(),
            'watch_count' => $lines->whereIn('control_status', ['watch', 'critical', 'over'])->count(),
        ];
        $summary['used'] = $summary['committed'] + $summary['actual'];
        $summary['used_percent'] = $summary['allocated'] > 0 ? min(999, ($summary['used'] / $summary['allocated']) * 100) : 0;

        return [
            'company' => $company,
            'branch' => $branch,
            'departments' => $departments,
            'budgets' => $budgets,
            'lines' => $lines,
            'summary' => $summary,
            'filters' => [
                'department_id' => $departmentId,
                'budget_id' => $budgetId,
            ],
        ];
    }
}
