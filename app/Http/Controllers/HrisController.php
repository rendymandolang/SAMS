<?php

namespace App\Http\Controllers;

use App\Support\AuditLogger;
use App\Support\CompanyContext;
use App\Support\HrisDocumentService;
use App\Support\HrisLeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrisController extends Controller
{
    public function index(Request $request, CompanyContext $context): View
    {
        $companyId = $context->id();
        $employees = DB::table('hr_employees')->leftJoin('branches', 'branches.id', '=', 'hr_employees.branch_id')
            ->leftJoin('departments', 'departments.id', '=', 'hr_employees.department_id')->leftJoin('hr_positions', 'hr_positions.id', '=', 'hr_employees.position_id')
            ->where('hr_employees.company_id', $companyId)->when($request->filled('status'), fn ($query) => $query->where('hr_employees.status', $request->string('status')->toString()))
            ->when($request->filled('search'), fn ($query) => $query->where(function ($nested) use ($request): void {
                $search = '%'.$request->string('search')->toString().'%';
                $nested->where('hr_employees.employee_number', 'like', $search)->orWhere('hr_employees.first_name', 'like', $search)->orWhere('hr_employees.last_name', 'like', $search);
            }))->select('hr_employees.*', 'branches.name as branch_name', 'departments.name as department_name', 'hr_positions.title as position_title')->orderBy('first_name')->paginate(50)->withQueryString();
        $leaveRequests = DB::table('hr_leave_requests')->join('hr_employees', 'hr_employees.id', '=', 'hr_leave_requests.employee_id')->join('hr_leave_types', 'hr_leave_types.id', '=', 'hr_leave_requests.leave_type_id')
            ->where('hr_leave_requests.company_id', $companyId)->select('hr_leave_requests.*', 'hr_employees.employee_number', 'hr_employees.first_name', 'hr_employees.last_name', 'hr_leave_types.name as leave_type_name')->orderByDesc('hr_leave_requests.created_at')->limit(50)->get();

        return view('hris.index', [
            'company' => $context->current(), 'employees' => $employees, 'leaveRequests' => $leaveRequests,
            'positions' => DB::table('hr_positions')->where('company_id', $companyId)->orderBy('code')->get(),
            'leaveTypes' => DB::table('hr_leave_types')->where('company_id', $companyId)->orderBy('code')->get(),
            'branches' => DB::table('branches')->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(),
            'departments' => DB::table('departments')->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(),
            'users' => DB::table('company_user')->join('users', 'users.id', '=', 'company_user.user_id')->where('company_user.company_id', $companyId)->where('company_user.is_active', true)->select('users.id', 'users.name', 'users.email')->orderBy('users.name')->get(),
            'activeEmployees' => DB::table('hr_employees')->where('company_id', $companyId)->where('status', 'active')->orderBy('first_name')->get(),
            'myEmployee' => DB::table('hr_employees')->where('company_id', $companyId)->where('user_id', auth()->id())->first(),
            'metrics' => [
                'active' => DB::table('hr_employees')->where('company_id', $companyId)->where('status', 'active')->count(),
                'contracts_due' => DB::table('hr_employees')->where('company_id', $companyId)->where('status', 'active')->whereNotNull('contract_end_date')->whereBetween('contract_end_date', [today(), today()->addDays(60)])->count(),
                'leave_pending' => DB::table('hr_leave_requests')->where('company_id', $companyId)->where('status', 'submitted')->count(),
                'on_leave' => DB::table('hr_leave_requests')->where('company_id', $companyId)->where('status', 'approved')->where('starts_on', '<=', today())->where('ends_on', '>=', today())->count(),
            ],
        ]);
    }

    public function show(int $employee, CompanyContext $context): View
    {
        $row = DB::table('hr_employees')->leftJoin('branches', 'branches.id', '=', 'hr_employees.branch_id')->leftJoin('departments', 'departments.id', '=', 'hr_employees.department_id')
            ->leftJoin('hr_positions', 'hr_positions.id', '=', 'hr_employees.position_id')->leftJoin('hr_employees as manager', 'manager.id', '=', 'hr_employees.manager_id')
            ->where('hr_employees.company_id', $context->id())->where('hr_employees.id', $employee)
            ->select('hr_employees.*', 'branches.name as branch_name', 'departments.name as department_name', 'hr_positions.title as position_title', 'manager.first_name as manager_first_name', 'manager.last_name as manager_last_name')->firstOrFail();

        return view('hris.show', [
            'company' => $context->current(), 'employee' => $row, 'canSensitive' => auth()->user()->hasPermission('hris.sensitive.view'),
            'documents' => DB::table('hr_employee_documents')->where('company_id', $context->id())->where('employee_id', $row->id)->orderByDesc('created_at')->get(),
            'balances' => DB::table('hr_leave_balances')->join('hr_leave_types', 'hr_leave_types.id', '=', 'hr_leave_balances.leave_type_id')->where('employee_id', $row->id)->select('hr_leave_balances.*', 'hr_leave_types.name as leave_type_name')->orderByDesc('year')->get(),
            'leaveRequests' => DB::table('hr_leave_requests')->join('hr_leave_types', 'hr_leave_types.id', '=', 'hr_leave_requests.leave_type_id')->where('employee_id', $row->id)->select('hr_leave_requests.*', 'hr_leave_types.name as leave_type_name')->orderByDesc('starts_on')->get(),
        ]);
    }

    public function storePosition(Request $request, CompanyContext $context): RedirectResponse
    {
        $v = $request->validate(['code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9.\-]+$/'], 'title' => ['required', 'string', 'max:255'], 'department_id' => ['nullable', 'integer'], 'grade' => ['nullable', 'string', 'max:30'], 'description' => ['nullable', 'string', 'max:2000']]);
        $v['code'] = Str::upper(trim($v['code']));
        $this->companyForeignKey('departments', $v['department_id'] ?? null, $context->id());
        if (DB::table('hr_positions')->where('company_id', $context->id())->where('code', $v['code'])->exists()) {
            throw ValidationException::withMessages(['code' => 'Position code sudah digunakan.']);
        }
        $id = DB::table('hr_positions')->insertGetId($v + ['company_id' => $context->id(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        AuditLogger::log('hr_position_created', 'hr_position', $id, null, $v, $context->id());

        return back()->with('status', 'Position berhasil ditambahkan.');
    }

    public function storeEmployee(Request $request, CompanyContext $context): RedirectResponse
    {
        $v = $request->validate($this->employeeRules());
        $companyId = $context->id();
        $v['employee_number'] = Str::upper(trim($v['employee_number']));
        if (DB::table('hr_employees')->where('company_id', $companyId)->where('employee_number', $v['employee_number'])->exists()) {
            throw ValidationException::withMessages(['employee_number' => 'Employee number sudah digunakan.']);
        }
        $this->validateEmployeeRelations($companyId, $v);
        $id = DB::transaction(function () use ($companyId, $v): int {
            $employeeId = DB::table('hr_employees')->insertGetId($v + ['company_id' => $companyId, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            $leaveTypes = DB::table('hr_leave_types')->where('company_id', $companyId)->where('is_active', true)->get();
            foreach ($leaveTypes as $leaveType) {
                DB::table('hr_leave_balances')->insert(['employee_id' => $employeeId, 'leave_type_id' => $leaveType->id, 'year' => now()->year, 'entitled_days' => $leaveType->annual_entitlement_days, 'carried_days' => 0, 'used_days' => 0, 'created_at' => now(), 'updated_at' => now()]);
            }

            return $employeeId;
        });
        AuditLogger::log('hr_employee_created', 'hr_employee', $id, null, ['employee_number' => $v['employee_number'], 'status' => 'active'], $companyId);

        return redirect()->route('hris.employees.show', $id)->with('status', 'Employee record berhasil dibuat.');
    }

    public function updateEmployee(Request $request, int $employee, CompanyContext $context): RedirectResponse
    {
        $row = DB::table('hr_employees')->where('company_id', $context->id())->where('id', $employee)->firstOrFail();
        $v = $request->validate(['status' => ['required', Rule::in(['active', 'on_leave', 'suspended', 'terminated'])], 'position_id' => ['nullable', 'integer'], 'department_id' => ['nullable', 'integer'], 'manager_id' => ['nullable', 'integer'], 'contract_end_date' => ['nullable', 'date'], 'termination_date' => ['nullable', 'date'], 'termination_reason' => ['nullable', 'string', 'max:2000']]);
        $this->validateEmployeeRelations($context->id(), $v);
        if ($v['status'] === 'terminated' && empty($v['termination_date'])) {
            throw ValidationException::withMessages(['termination_date' => 'Termination date wajib untuk karyawan terminated.']);
        }
        abort_if((int) ($v['manager_id'] ?? 0) === (int) $row->id, 422, 'Karyawan tidak dapat menjadi manager dirinya sendiri.');
        DB::table('hr_employees')->where('id', $row->id)->update($v + ['updated_at' => now()]);
        AuditLogger::log('hr_employee_lifecycle_updated', 'hr_employee', (int) $row->id, ['status' => $row->status], $v, $context->id());

        return back()->with('status', 'Employee lifecycle berhasil diperbarui.');
    }

    public function storeLeaveType(Request $request, CompanyContext $context): RedirectResponse
    {
        $v = $request->validate(['code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9.\-]+$/'], 'name' => ['required', 'string', 'max:255'], 'annual_entitlement_days' => ['required', 'numeric', 'min:0', 'max:366'], 'is_paid' => ['nullable', 'boolean'], 'requires_attachment' => ['nullable', 'boolean']]);
        $v['code'] = Str::upper(trim($v['code']));
        if (DB::table('hr_leave_types')->where('company_id', $context->id())->where('code', $v['code'])->exists()) {
            throw ValidationException::withMessages(['code' => 'Leave type code sudah digunakan.']);
        }
        $id = DB::transaction(function () use ($context, $v): int {
            $leaveTypeId = DB::table('hr_leave_types')->insertGetId($v + ['company_id' => $context->id(), 'is_paid' => (bool) ($v['is_paid'] ?? false), 'requires_attachment' => (bool) ($v['requires_attachment'] ?? false), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            foreach (DB::table('hr_employees')->where('company_id', $context->id())->where('status', 'active')->pluck('id') as $employeeId) {
                DB::table('hr_leave_balances')->insert(['employee_id' => $employeeId, 'leave_type_id' => $leaveTypeId, 'year' => now()->year, 'entitled_days' => $v['annual_entitlement_days'], 'carried_days' => 0, 'used_days' => 0, 'created_at' => now(), 'updated_at' => now()]);
            }

            return $leaveTypeId;
        });
        AuditLogger::log('hr_leave_type_created', 'hr_leave_type', $id, null, $v, $context->id());

        return back()->with('status', 'Leave type dan saldo awal berhasil dibuat.');
    }

    public function requestLeave(Request $request, CompanyContext $context, HrisLeaveService $service): RedirectResponse
    {
        $v = $request->validate(['employee_id' => ['nullable', 'integer'], 'leave_type_id' => ['required', 'integer'], 'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after_or_equal:starts_on'], 'reason' => ['required', 'string', 'max:2000']]);
        $canManage = auth()->user()->hasPermission('hris.manage');
        $employeeId = $canManage && ! empty($v['employee_id']) ? (int) $v['employee_id'] : (int) DB::table('hr_employees')->where('company_id', $context->id())->where('user_id', auth()->id())->value('id');
        if ($employeeId <= 0) {
            throw ValidationException::withMessages(['employee_id' => 'User belum terhubung dengan employee record.']);
        }
        $id = $service->submit($context->id(), $employeeId, $v);
        AuditLogger::log('hr_leave_submitted', 'hr_leave_request', $id, null, ['employee_id' => $employeeId, 'starts_on' => $v['starts_on'], 'ends_on' => $v['ends_on']], $context->id());

        return back()->with('status', 'Pengajuan cuti berhasil dikirim.');
    }

    public function decideLeave(Request $request, int $leaveRequest, CompanyContext $context, HrisLeaveService $service): RedirectResponse
    {
        $v = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected'])], 'decision_notes' => ['nullable', 'string', 'max:1000']]);
        $service->decide($context->id(), $leaveRequest, (int) auth()->id(), $v['decision'], $v['decision_notes'] ?? null);
        AuditLogger::log('hr_leave_'.$v['decision'], 'hr_leave_request', $leaveRequest, ['status' => 'submitted'], ['status' => $v['decision'], 'notes' => $v['decision_notes'] ?? null], $context->id());

        return back()->with('status', 'Keputusan cuti berhasil disimpan.');
    }

    public function cancelLeave(int $leaveRequest, CompanyContext $context, HrisLeaveService $service): RedirectResponse
    {
        $service->cancel($context->id(), $leaveRequest, (int) auth()->id(), auth()->user()->hasPermission('hris.manage'));
        AuditLogger::log('hr_leave_cancelled', 'hr_leave_request', $leaveRequest, null, ['status' => 'cancelled'], $context->id());

        return back()->with('status', 'Pengajuan cuti berhasil dibatalkan.');
    }

    public function uploadDocument(Request $request, int $employee, CompanyContext $context, HrisDocumentService $service): RedirectResponse
    {
        $v = $request->validate(['document_type' => ['required', Rule::in(['identity', 'contract', 'certificate', 'medical', 'other'])], 'document' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png']]);
        try {
            $id = $service->store($context->id(), $employee, (int) auth()->id(), $v['document_type'], $request->file('document'));
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['document' => $exception->getMessage()]);
        }
        AuditLogger::log('hr_employee_document_uploaded', 'hr_employee_document', $id, null, ['employee_id' => $employee, 'document_type' => $v['document_type']], $context->id());

        return back()->with('status', 'Dokumen karyawan berhasil dienkripsi dan disimpan.');
    }

    public function downloadDocument(int $document, CompanyContext $context, HrisDocumentService $service): StreamedResponse
    {
        $result = $service->read($context->id(), $document);
        AuditLogger::log('hr_employee_document_downloaded', 'hr_employee_document', $document, null, ['employee_id' => $result['document']->employee_id], $context->id());

        return response()->streamDownload(fn () => print $result['content'], $result['document']->original_name, ['Content-Type' => $result['document']->mime_type, 'Cache-Control' => 'private, no-store']);
    }

    private function employeeRules(): array
    {
        return ['employee_number' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9.\-]+$/'], 'user_id' => ['nullable', 'integer'], 'branch_id' => ['required', 'integer'], 'department_id' => ['nullable', 'integer'], 'position_id' => ['nullable', 'integer'], 'manager_id' => ['nullable', 'integer'], 'first_name' => ['required', 'string', 'max:255'], 'last_name' => ['nullable', 'string', 'max:255'], 'preferred_name' => ['nullable', 'string', 'max:255'], 'work_email' => ['nullable', 'email', 'max:255'], 'personal_email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:40'], 'birth_date' => ['nullable', 'date', 'before:today'], 'gender' => ['nullable', Rule::in(['female', 'male', 'other', 'undisclosed'])], 'national_id_last4' => ['nullable', 'digits:4'], 'address' => ['nullable', 'string', 'max:2000'], 'employment_type' => ['required', Rule::in(['permanent', 'contract', 'casual', 'intern'])], 'hire_date' => ['required', 'date'], 'probation_end_date' => ['nullable', 'date', 'after_or_equal:hire_date'], 'contract_end_date' => ['nullable', 'date', 'after_or_equal:hire_date']];
    }

    private function validateEmployeeRelations(int $companyId, array $data): void
    {
        $this->companyForeignKey('branches', $data['branch_id'] ?? null, $companyId);
        $this->companyForeignKey('departments', $data['department_id'] ?? null, $companyId);
        $this->companyForeignKey('hr_positions', $data['position_id'] ?? null, $companyId);
        $this->companyForeignKey('hr_employees', $data['manager_id'] ?? null, $companyId);
        if (! empty($data['user_id']) && ! DB::table('company_user')->where('company_id', $companyId)->where('user_id', $data['user_id'])->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['user_id' => 'User bukan anggota aktif perusahaan.']);
        }
    }

    private function companyForeignKey(string $table, mixed $id, int $companyId): void
    {
        if ($id && ! DB::table($table)->where('company_id', $companyId)->where('id', $id)->exists()) {
            throw ValidationException::withMessages([$table => 'Referensi organisasi tidak valid untuk perusahaan ini.']);
        }
    }
}
