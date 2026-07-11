<?php

namespace App\Http\Controllers;

use App\Support\AuditLogger;
use App\Support\CompanyContext;
use App\Support\DocumentStateMachine;
use App\Support\TransactionPeriodLock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaseRequestController extends Controller
{
    public function index(): View
    {
        $company = $this->company();

        $purchaseRequests = DB::table('purchase_requests')
            ->join('departments', 'departments.id', '=', 'purchase_requests.department_id')
            ->join('users', 'users.id', '=', 'purchase_requests.requested_by')
            ->where('purchase_requests.company_id', $company->id)
            ->whereNull('purchase_requests.deleted_at')
            ->select(
                'purchase_requests.*',
                'departments.name as department_name',
                'users.name as requester_name',
            )
            ->orderByDesc('purchase_requests.request_date')
            ->orderByDesc('purchase_requests.id')
            ->paginate(10);

        return view('purchase_requests.index', compact('purchaseRequests'));
    }

    public function create(): View
    {
        return view('purchase_requests.create', [
            'departments' => $this->departments(),
            'items' => $this->items(),
            'budgetLines' => $this->budgetLines(),
            'defaultDepartmentId' => $this->defaultDepartment()?->id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->company();
        $branch = $this->branch();
        [$validated, $lines] = $this->validatedPurchaseRequestPayload($request, $company);

        $purchaseRequestId = DB::transaction(function () use ($company, $branch, $validated, $lines) {
            $documentNumber = $this->nextDocumentNumber($company->id, $branch->id);
            $estimatedTotal = $lines->sum('estimated_total');
            $now = now();

            $purchaseRequestId = DB::table('purchase_requests')->insertGetId([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'department_id' => $validated['department_id'],
                'requested_by' => auth()->id(),
                'document_number' => $documentNumber,
                'request_date' => $validated['request_date'],
                'required_date' => $validated['required_date'] ?? null,
                'priority' => $validated['priority'],
                'status' => 'draft',
                'purpose' => $validated['purpose'] ?? null,
                'estimated_total' => $estimatedTotal,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->insertLines($purchaseRequestId, $lines, $now);

            return $purchaseRequestId;
        });

        return redirect()
            ->route('purchase-requests.show', $purchaseRequestId)
            ->with('status', 'Purchase Request berhasil dibuat sebagai draft.');
    }

    public function edit(int $purchaseRequest): View|RedirectResponse
    {
        $header = $this->findPurchaseRequest($purchaseRequest);

        if ($header->status !== 'draft') {
            return redirect()
                ->route('purchase-requests.show', $header->id)
                ->with('status', 'Hanya Purchase Request draft yang bisa diedit.');
        }

        $lines = DB::table('purchase_request_items')
            ->where('purchase_request_id', $header->id)
            ->orderBy('id')
            ->get();

        return view('purchase_requests.edit', [
            'header' => $header,
            'lines' => $lines,
            'departments' => $this->departments(),
            'items' => $this->items(),
            'budgetLines' => $this->budgetLines(),
        ]);
    }

    public function update(Request $request, int $purchaseRequest): RedirectResponse
    {
        $company = $this->company();
        $header = $this->findPurchaseRequest($purchaseRequest);

        if ($header->status !== 'draft') {
            return redirect()
                ->route('purchase-requests.show', $header->id)
                ->with('status', 'Hanya Purchase Request draft yang bisa diedit.');
        }

        [$validated, $lines] = $this->validatedPurchaseRequestPayload($request, $company);

        DB::transaction(function () use ($header, $validated, $lines) {
            $now = now();

            DB::table('purchase_requests')->where('id', $header->id)->update([
                'department_id' => $validated['department_id'],
                'request_date' => $validated['request_date'],
                'required_date' => $validated['required_date'] ?? null,
                'priority' => $validated['priority'],
                'purpose' => $validated['purpose'] ?? null,
                'estimated_total' => $lines->sum('estimated_total'),
                'updated_at' => $now,
            ]);

            DB::table('purchase_request_items')->where('purchase_request_id', $header->id)->delete();
            $this->insertLines($header->id, $lines, $now);
        });

        return redirect()
            ->route('purchase-requests.show', $header->id)
            ->with('status', 'Purchase Request draft berhasil diperbarui.');
    }

    private function validatedPurchaseRequestPayload(Request $request, object $company): array
    {
        $validated = $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'request_date' => ['required', 'date'],
            'required_date' => ['nullable', 'date', 'after_or_equal:request_date'],
            'priority' => ['required', 'string', 'in:low,normal,high,urgent'],
            'purpose' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array'],
            'lines.*.item_id' => ['nullable', 'integer', 'exists:items,id'],
            'lines.*.budget_line_id' => ['nullable', 'integer', 'exists:budget_lines,id'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0.0001'],
            'lines.*.estimated_unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $department = DB::table('departments')
            ->where('company_id', $company->id)
            ->where('id', $validated['department_id'])
            ->whereNull('deleted_at')
            ->first();

        abort_unless($department, 422);

        $lines = collect($validated['lines'])
            ->filter(fn (array $line) => filled($line['item_id'] ?? null))
            ->map(function (array $line) use ($company, $validated) {
                $item = DB::table('items')
                    ->where('company_id', $company->id)
                    ->where('id', $line['item_id'])
                    ->whereNull('deleted_at')
                    ->first();

                if (! $item) {
                    throw ValidationException::withMessages([
                        'lines' => 'Item yang dipilih tidak valid.',
                    ]);
                }

                if (! filled($line['quantity'] ?? null)) {
                    throw ValidationException::withMessages([
                        'lines' => 'Quantity wajib diisi untuk setiap item.',
                    ]);
                }

                $quantity = (float) $line['quantity'];
                $estimatedUnitPrice = (float) ($line['estimated_unit_price'] ?? $item->standard_cost ?? 0);
                $budgetLine = $this->findBudgetLine($line['budget_line_id'] ?? null, $company, (int) $validated['department_id']);

                return [
                    'item' => $item,
                    'budget_line' => $budgetLine,
                    'quantity' => $quantity,
                    'estimated_unit_price' => $estimatedUnitPrice,
                    'estimated_total' => $quantity * $estimatedUnitPrice,
                    'notes' => $line['notes'] ?? null,
                ];
            })
            ->values();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'lines' => 'Minimal satu item harus diisi.',
            ]);
        }

        $this->validateBudgetAvailability($lines);

        return [$validated, $lines];
    }

    private function insertLines(int $purchaseRequestId, $lines, $now): void
    {
        foreach ($lines as $line) {
            DB::table('purchase_request_items')->insert([
                'purchase_request_id' => $purchaseRequestId,
                'item_id' => $line['item']->id,
                'unit_id' => $line['item']->base_unit_id,
                'budget_line_id' => $line['budget_line']?->id,
                'quantity' => $line['quantity'],
                'estimated_unit_price' => $line['estimated_unit_price'],
                'estimated_total' => $line['estimated_total'],
                'notes' => $line['notes'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function show(int $purchaseRequest): View
    {
        $header = $this->findPurchaseRequest($purchaseRequest);
        $items = $this->purchaseRequestItems((int) $header->id);
        $attachments = AttachmentController::listFor('purchase_request', (int) $header->id);

        return view('purchase_requests.show', compact('header', 'items', 'attachments'));
    }

    public function print(int $purchaseRequest): View
    {
        $header = $this->findPurchaseRequest($purchaseRequest);
        $items = $this->purchaseRequestItems((int) $header->id);
        $company = $this->company();
        $branch = DB::table('branches')->where('id', $header->branch_id)->first();

        return view('purchase_requests.print', compact('header', 'items', 'company', 'branch'));
    }

    private function purchaseRequestItems(int $purchaseRequestId)
    {
        return DB::table('purchase_request_items')
            ->join('items', 'items.id', '=', 'purchase_request_items.item_id')
            ->join('units', 'units.id', '=', 'purchase_request_items.unit_id')
            ->leftJoin('budget_lines', 'budget_lines.id', '=', 'purchase_request_items.budget_line_id')
            ->where('purchase_request_items.purchase_request_id', $purchaseRequestId)
            ->select(
                'purchase_request_items.*',
                'items.sku',
                'items.name as item_name',
                'units.code as unit_code',
                'budget_lines.account_code as budget_account_code',
                'budget_lines.description as budget_description',
            )
            ->orderBy('purchase_request_items.id')
            ->get();
    }

    public function submit(int $purchaseRequest): RedirectResponse
    {
        $header = $this->findPurchaseRequest($purchaseRequest);

        $submitted = DB::transaction(function () use ($header): bool {
            $lockedHeader = DB::table('purchase_requests')->where('id', $header->id)->lockForUpdate()->first();
            if (! $lockedHeader || ! DocumentStateMachine::allows('purchase_request', $lockedHeader->status, 'submitted')) {
                return false;
            }

            TransactionPeriodLock::ensureOpen((int) $lockedHeader->company_id, 'procurement', $lockedHeader->request_date);

            $this->commitBudget($lockedHeader->id);
            $this->createApprovalRequest($lockedHeader->id, (int) $lockedHeader->company_id);

            DB::table('purchase_requests')->where('id', $lockedHeader->id)->update([
                'status' => 'submitted',
                'updated_at' => now(),
            ]);

            AuditLogger::log('purchase_request_submitted', 'purchase_request', (int) $lockedHeader->id, ['status' => $lockedHeader->status], ['status' => 'submitted'], (int) $lockedHeader->company_id);

            return true;
        });

        if (! $submitted) {
            return redirect()
                ->route('purchase-requests.show', $header->id)
                ->with('status', 'Hanya Purchase Request draft yang bisa disubmit.');
        }

        return redirect()
            ->route('purchase-requests.show', $header->id)
            ->with('status', 'Purchase Request berhasil disubmit.');
    }

    public function approve(Request $request, int $purchaseRequest): RedirectResponse
    {
        $header = $this->findPurchaseRequest($purchaseRequest);

        $validated = $request->validate([
            'comments' => ['nullable', 'string', 'max:1000'],
        ]);

        $approved = DB::transaction(function () use ($header, $validated): bool {
            $lockedHeader = DB::table('purchase_requests')->where('id', $header->id)->lockForUpdate()->first();
            if (! $lockedHeader || ! DocumentStateMachine::allows('purchase_request', $lockedHeader->status, 'approved')) {
                return false;
            }

            $approvalRequest = $this->approvalRequestFor($lockedHeader->id, (int) $lockedHeader->company_id);

            DB::table('approval_actions')->insert([
                'approval_request_id' => $approvalRequest->id,
                'step_order' => $approvalRequest->current_step,
                'acted_by' => auth()->id(),
                'action' => 'approved',
                'comments' => $validated['comments'] ?? null,
                'acted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('approval_requests')->where('id', $approvalRequest->id)->update([
                'status' => 'approved',
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('purchase_requests')->where('id', $lockedHeader->id)->update([
                'status' => 'approved',
                'updated_at' => now(),
            ]);

            AuditLogger::log('purchase_request_approved', 'purchase_request', (int) $lockedHeader->id, ['status' => $lockedHeader->status], ['status' => 'approved', 'comments' => $validated['comments'] ?? null], (int) $lockedHeader->company_id);

            return true;
        });

        if (! $approved) {
            return redirect()->route('purchase-requests.show', $header->id)->with('status', 'Hanya Purchase Request submitted yang bisa di-approve.');
        }

        return redirect()
            ->route('purchase-requests.show', $header->id)
            ->with('status', 'Purchase Request berhasil di-approve.');
    }

    public function reject(Request $request, int $purchaseRequest): RedirectResponse
    {
        $header = $this->findPurchaseRequest($purchaseRequest);

        $validated = $request->validate([
            'comments' => ['nullable', 'string', 'max:1000'],
        ]);

        $rejected = DB::transaction(function () use ($header, $validated): bool {
            $lockedHeader = DB::table('purchase_requests')->where('id', $header->id)->lockForUpdate()->first();
            if (! $lockedHeader || ! DocumentStateMachine::allows('purchase_request', $lockedHeader->status, 'rejected')) {
                return false;
            }

            $approvalRequest = $this->approvalRequestFor($lockedHeader->id, (int) $lockedHeader->company_id);

            DB::table('approval_actions')->insert([
                'approval_request_id' => $approvalRequest->id,
                'step_order' => $approvalRequest->current_step,
                'acted_by' => auth()->id(),
                'action' => 'rejected',
                'comments' => $validated['comments'] ?? null,
                'acted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('approval_requests')->where('id', $approvalRequest->id)->update([
                'status' => 'rejected',
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

            $this->releaseBudget($lockedHeader->id);

            DB::table('purchase_requests')->where('id', $lockedHeader->id)->update([
                'status' => 'rejected',
                'updated_at' => now(),
            ]);

            AuditLogger::log('purchase_request_rejected', 'purchase_request', (int) $lockedHeader->id, ['status' => $lockedHeader->status], ['status' => 'rejected', 'comments' => $validated['comments'] ?? null], (int) $lockedHeader->company_id);

            return true;
        });

        if (! $rejected) {
            return redirect()->route('purchase-requests.show', $header->id)->with('status', 'Hanya Purchase Request submitted yang bisa di-reject.');
        }

        return redirect()
            ->route('purchase-requests.show', $header->id)
            ->with('status', 'Purchase Request berhasil di-reject.');
    }

    public function destroy(int $purchaseRequest): RedirectResponse
    {
        $header = $this->findPurchaseRequest($purchaseRequest);

        if ($header->status !== 'draft') {
            return redirect()
                ->route('purchase-requests.show', $header->id)
                ->with('status', 'Hanya Purchase Request draft yang bisa dihapus.');
        }

        DB::table('purchase_requests')->where('id', $header->id)->update([
            'deleted_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('purchase-requests.index')
            ->with('status', 'Purchase Request draft berhasil dihapus.');
    }

    private function findPurchaseRequest(int $id): object
    {
        $company = $this->company();

        $header = DB::table('purchase_requests')
            ->join('departments', 'departments.id', '=', 'purchase_requests.department_id')
            ->join('branches', 'branches.id', '=', 'purchase_requests.branch_id')
            ->join('users', 'users.id', '=', 'purchase_requests.requested_by')
            ->where('purchase_requests.company_id', $company->id)
            ->where('purchase_requests.id', $id)
            ->whereNull('purchase_requests.deleted_at')
            ->select(
                'purchase_requests.*',
                'departments.name as department_name',
                'branches.name as branch_name',
                'users.name as requester_name',
            )
            ->first();

        abort_unless($header, 404);

        return $header;
    }

    private function nextDocumentNumber(int $companyId, int $branchId): string
    {
        $period = now()->format('Ym');
        $sequence = DB::table('document_sequences')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('document_type', 'purchase_request')
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            DB::table('document_sequences')->insert([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'document_type' => 'purchase_request',
                'prefix' => 'PR',
                'next_number' => 1,
                'padding' => 5,
                'period_format' => 'Ym',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('document_sequences')
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('document_type', 'purchase_request')
                ->lockForUpdate()
                ->first();
        }

        $number = str_pad((string) $sequence->next_number, (int) $sequence->padding, '0', STR_PAD_LEFT);

        DB::table('document_sequences')->where('id', $sequence->id)->update([
            'next_number' => $sequence->next_number + 1,
            'updated_at' => now(),
        ]);

        return "{$sequence->prefix}-{$period}-{$number}";
    }

    private function company(): object
    {
        return app(CompanyContext::class)->current();
    }

    private function branch(): object
    {
        return app(CompanyContext::class)->branch()
            ?? abort(404, 'Cabang aktif tidak tersedia.');
    }

    private function defaultDepartment(): ?object
    {
        $company = $this->company();

        return DB::table('company_user')
            ->join('departments', 'departments.id', '=', 'company_user.department_id')
            ->where('company_user.company_id', $company->id)
            ->where('company_user.user_id', auth()->id())
            ->where('company_user.is_default', true)
            ->select('departments.*')
            ->first();
    }

    private function departments()
    {
        $company = $this->company();

        return DB::table('departments')
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();
    }

    private function items()
    {
        $company = $this->company();

        return DB::table('items')
            ->join('units', 'units.id', '=', 'items.base_unit_id')
            ->where('items.company_id', $company->id)
            ->where('items.is_active', true)
            ->whereNull('items.deleted_at')
            ->select('items.*', 'units.code as unit_code')
            ->orderBy('items.name')
            ->get();
    }

    private function budgetLines()
    {
        $company = $this->company();

        return DB::table('budget_lines')
            ->join('budgets', 'budgets.id', '=', 'budget_lines.budget_id')
            ->join('departments', 'departments.id', '=', 'budgets.department_id')
            ->where('budgets.company_id', $company->id)
            ->where('budgets.status', 'active')
            ->select(
                'budget_lines.*',
                'departments.code as department_code',
                'departments.name as department_name',
                'budgets.name as budget_name',
            )
            ->orderBy('departments.code')
            ->orderBy('budget_lines.account_code')
            ->get();
    }

    private function findBudgetLine(?int $budgetLineId, object $company, int $departmentId): ?object
    {
        if (! $budgetLineId) {
            return null;
        }

        $budgetLine = DB::table('budget_lines')
            ->join('budgets', 'budgets.id', '=', 'budget_lines.budget_id')
            ->where('budgets.company_id', $company->id)
            ->where('budgets.department_id', $departmentId)
            ->where('budgets.status', 'active')
            ->where('budget_lines.id', $budgetLineId)
            ->select('budget_lines.*')
            ->first();

        if (! $budgetLine) {
            throw ValidationException::withMessages([
                'lines' => 'Budget line yang dipilih tidak valid.',
            ]);
        }

        return $budgetLine;
    }

    private function commitBudget(int $purchaseRequestId): void
    {
        DB::table('purchase_request_items')
            ->where('purchase_request_id', $purchaseRequestId)
            ->whereNotNull('budget_line_id')
            ->select('budget_line_id', DB::raw('SUM(estimated_total) as total'))
            ->groupBy('budget_line_id')
            ->get()
            ->each(function (object $line) {
                $budgetLine = DB::table('budget_lines')
                    ->where('id', $line->budget_line_id)
                    ->lockForUpdate()
                    ->first();

                if (! $budgetLine) {
                    throw ValidationException::withMessages([
                        'lines' => 'Budget line tidak ditemukan.',
                    ]);
                }

                $available = (float) $budgetLine->allocated_amount
                    - (float) $budgetLine->committed_amount
                    - (float) $budgetLine->actual_amount;

                if ((float) $line->total > $available) {
                    throw ValidationException::withMessages([
                        'lines' => "Budget {$budgetLine->account_code} tidak cukup untuk submit. Sisa budget Rp ".number_format($available, 0, ',', '.').'.',
                    ]);
                }

                DB::table('budget_lines')->where('id', $budgetLine->id)->update([
                    'committed_amount' => (float) $budgetLine->committed_amount + (float) $line->total,
                    'updated_at' => now(),
                ]);
            });
    }

    private function releaseBudget(int $purchaseRequestId): void
    {
        DB::table('purchase_request_items')
            ->where('purchase_request_id', $purchaseRequestId)
            ->whereNotNull('budget_line_id')
            ->select('budget_line_id', DB::raw('SUM(estimated_total) as total'))
            ->groupBy('budget_line_id')
            ->get()
            ->each(function (object $line) {
                $budgetLine = DB::table('budget_lines')
                    ->where('id', $line->budget_line_id)
                    ->lockForUpdate()
                    ->first();

                if (! $budgetLine) {
                    return;
                }

                DB::table('budget_lines')->where('id', $budgetLine->id)->update([
                    'committed_amount' => max(0, (float) $budgetLine->committed_amount - (float) $line->total),
                    'updated_at' => now(),
                ]);
            });
    }

    private function createApprovalRequest(int $purchaseRequestId, int $companyId): void
    {
        if (DB::table('approval_requests')
            ->where('approvable_type', 'purchase_request')
            ->where('approvable_id', $purchaseRequestId)
            ->exists()) {
            return;
        }

        DB::table('approval_requests')->insert([
            'approval_flow_id' => $this->approvalFlowId($companyId),
            'approvable_type' => 'purchase_request',
            'approvable_id' => $purchaseRequestId,
            'current_step' => 1,
            'status' => 'pending',
            'submitted_by' => auth()->id(),
            'submitted_at' => now(),
            'completed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function approvalRequestFor(int $purchaseRequestId, int $companyId): object
    {
        $approvalRequest = DB::table('approval_requests')
            ->where('approvable_type', 'purchase_request')
            ->where('approvable_id', $purchaseRequestId)
            ->first();

        if (! $approvalRequest) {
            $this->createApprovalRequest($purchaseRequestId, $companyId);

            $approvalRequest = DB::table('approval_requests')
                ->where('approvable_type', 'purchase_request')
                ->where('approvable_id', $purchaseRequestId)
                ->first();
        }

        return $approvalRequest;
    }

    private function approvalFlowId(int $companyId): int
    {
        DB::table('approval_flows')->updateOrInsert(
            [
                'company_id' => $companyId,
                'document_type' => 'purchase_request',
                'name' => 'Default Purchase Request Approval',
            ],
            [
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $approvalFlow = DB::table('approval_flows')
            ->where('company_id', $companyId)
            ->where('document_type', 'purchase_request')
            ->where('name', 'Default Purchase Request Approval')
            ->first();

        DB::table('approval_flow_steps')->updateOrInsert(
            [
                'approval_flow_id' => $approvalFlow->id,
                'step_order' => 1,
            ],
            [
                'approver_type' => 'role',
                'approver_value' => 'super_admin',
                'minimum_amount' => null,
                'maximum_amount' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        return $approvalFlow->id;
    }

    private function validateBudgetAvailability($lines): void
    {
        $lines
            ->filter(fn (array $line) => $line['budget_line'] !== null)
            ->groupBy(fn (array $line) => $line['budget_line']->id)
            ->each(function ($groupedLines) {
                $budgetLine = $groupedLines->first()['budget_line'];
                $requestedTotal = $groupedLines->sum('estimated_total');
                $available = (float) $budgetLine->allocated_amount
                    - (float) $budgetLine->committed_amount
                    - (float) $budgetLine->actual_amount;

                if ($requestedTotal > $available) {
                    throw ValidationException::withMessages([
                        'lines' => "Budget {$budgetLine->account_code} tidak cukup. Sisa budget Rp ".number_format($available, 0, ',', '.').'.',
                    ]);
                }
            });
    }
}
