<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HrisFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_open_shris_and_create_organization_employee_and_leave_policy(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@sams.local')->firstOrFail();
        $companyId = (int) DB::table('companies')->where('code', 'SAMS')->value('id');
        $departmentId = (int) DB::table('departments')->where('company_id', $companyId)->value('id');
        $branchId = (int) DB::table('branches')->where('company_id', $companyId)->value('id');

        $this->actingAs($admin)->get('/hris')->assertOk()->assertSee('Human Resources');
        $this->actingAs($admin)->post('/hris/positions', ['code' => 'HRM', 'title' => 'HR Manager', 'department_id' => $departmentId, 'grade' => 'M2'])->assertRedirect();
        $positionId = (int) DB::table('hr_positions')->where('code', 'HRM')->value('id');
        $this->actingAs($admin)->post('/hris/leave-types', ['code' => 'ANL', 'name' => 'Annual Leave', 'annual_entitlement_days' => 12, 'is_paid' => 1])->assertRedirect();

        $response = $this->actingAs($admin)->post('/hris/employees', $this->employeePayload($branchId, $departmentId, $positionId, $admin->id));
        $employee = DB::table('hr_employees')->where('employee_number', 'EMP-001')->firstOrFail();
        $response->assertRedirect('/hris/employees/'.$employee->id);
        $this->assertDatabaseHas('hr_leave_balances', ['employee_id' => $employee->id, 'year' => now()->year, 'entitled_days' => 12]);
        $this->actingAs($admin)->get('/hris/employees/'.$employee->id)->assertOk()->assertSee('Rendy Mandolang')->assertSee('Encrypted employee files');
    }

    public function test_leave_approval_and_cancellation_keep_balance_consistent(): void
    {
        [$admin, $employee, $leaveType] = $this->seedEmployee();
        $starts = today()->next('Monday');
        $ends = $starts->copy()->addDays(4);

        $this->actingAs($admin)->post('/hris/leave-requests', ['employee_id' => $employee->id, 'leave_type_id' => $leaveType->id, 'starts_on' => $starts->toDateString(), 'ends_on' => $ends->toDateString(), 'reason' => 'Family holiday'])->assertRedirect();
        $request = DB::table('hr_leave_requests')->firstOrFail();
        $this->assertSame(5.0, (float) $request->requested_days);
        $this->actingAs($admin)->post('/hris/leave-requests/'.$request->id.'/decision', ['decision' => 'approved', 'decision_notes' => 'Approved by HR'])->assertRedirect();
        $this->assertDatabaseHas('hr_leave_balances', ['employee_id' => $employee->id, 'used_days' => 5]);

        $this->actingAs($admin)->post('/hris/leave-requests/'.$request->id.'/cancel')->assertRedirect();
        $this->assertDatabaseHas('hr_leave_requests', ['id' => $request->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('hr_leave_balances', ['employee_id' => $employee->id, 'used_days' => 0]);
    }

    public function test_employee_documents_are_encrypted_scoped_and_audited(): void
    {
        Storage::fake('local');
        [$admin, $employee] = $this->seedEmployee();
        $plain = '%PDF-1.4 confidential employment agreement';

        $this->actingAs($admin)->post('/hris/employees/'.$employee->id.'/documents', ['document_type' => 'contract', 'document' => UploadedFile::fake()->createWithContent('agreement.pdf', $plain)])->assertRedirect();
        $document = DB::table('hr_employee_documents')->firstOrFail();
        $encrypted = Storage::disk($document->disk)->get($document->path);
        $this->assertStringNotContainsString('confidential employment agreement', $encrypted);
        $this->actingAs($admin)->get('/hris/documents/'.$document->id.'/download')->assertOk()->assertStreamedContent($plain);
        $this->assertDatabaseHas('audit_logs', ['event' => 'hr_employee_document_downloaded', 'auditable_id' => $document->id]);
    }

    public function test_employee_lifecycle_requires_termination_date_and_is_audited(): void
    {
        [$admin, $employee] = $this->seedEmployee();
        $this->actingAs($admin)->from('/hris/employees/'.$employee->id)->patch('/hris/employees/'.$employee->id, ['status' => 'terminated'])->assertSessionHasErrors('termination_date');
        $this->actingAs($admin)->patch('/hris/employees/'.$employee->id, ['status' => 'terminated', 'termination_date' => today()->toDateString(), 'termination_reason' => 'Voluntary resignation'])->assertRedirect();
        $this->assertDatabaseHas('hr_employees', ['id' => $employee->id, 'status' => 'terminated']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'hr_employee_lifecycle_updated', 'auditable_id' => $employee->id]);
    }

    private function seedEmployee(): array
    {
        $this->seed();
        $admin = User::where('email', 'admin@sams.local')->firstOrFail();
        $companyId = (int) DB::table('companies')->where('code', 'SAMS')->value('id');
        $branchId = (int) DB::table('branches')->where('company_id', $companyId)->value('id');
        $departmentId = (int) DB::table('departments')->where('company_id', $companyId)->value('id');
        $positionId = DB::table('hr_positions')->insertGetId(['company_id' => $companyId, 'department_id' => $departmentId, 'code' => 'MGR', 'title' => 'Manager', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $leaveTypeId = DB::table('hr_leave_types')->insertGetId(['company_id' => $companyId, 'code' => 'ANL', 'name' => 'Annual Leave', 'annual_entitlement_days' => 12, 'is_paid' => true, 'requires_attachment' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $employeeId = DB::table('hr_employees')->insertGetId($this->employeePayload($branchId, $departmentId, $positionId, $admin->id) + ['company_id' => $companyId, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('hr_leave_balances')->insert(['employee_id' => $employeeId, 'leave_type_id' => $leaveTypeId, 'year' => now()->year, 'entitled_days' => 12, 'carried_days' => 0, 'used_days' => 0, 'created_at' => now(), 'updated_at' => now()]);

        return [$admin, DB::table('hr_employees')->find($employeeId), DB::table('hr_leave_types')->find($leaveTypeId)];
    }

    private function employeePayload(int $branchId, int $departmentId, int $positionId, int $userId): array
    {
        return ['employee_number' => 'EMP-001', 'user_id' => $userId, 'branch_id' => $branchId, 'department_id' => $departmentId, 'position_id' => $positionId, 'first_name' => 'Rendy', 'last_name' => 'Mandolang', 'work_email' => 'rendy@example.test', 'employment_type' => 'permanent', 'hire_date' => today()->subYear()->toDateString()];
    }
}
