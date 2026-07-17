<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HrisLeaveService
{
    /** @param array<string, mixed> $data */
    public function submit(int $companyId, int $employeeId, array $data): int
    {
        return DB::transaction(function () use ($companyId, $employeeId, $data): int {
            $employee = DB::table('hr_employees')->where('company_id', $companyId)->where('id', $employeeId)->where('status', 'active')->lockForUpdate()->first();
            if (! $employee) {
                throw ValidationException::withMessages(['employee_id' => 'Karyawan aktif tidak ditemukan.']);
            }
            $leaveType = DB::table('hr_leave_types')->where('company_id', $companyId)->where('id', $data['leave_type_id'])->where('is_active', true)->first();
            if (! $leaveType) {
                throw ValidationException::withMessages(['leave_type_id' => 'Leave type tidak valid.']);
            }
            $days = $this->businessDays($data['starts_on'], $data['ends_on']);
            if ($days <= 0) {
                throw ValidationException::withMessages(['starts_on' => 'Rentang cuti tidak memiliki hari kerja.']);
            }
            $overlap = DB::table('hr_leave_requests')->where('company_id', $companyId)->where('employee_id', $employeeId)
                ->whereIn('status', ['submitted', 'approved'])->where('starts_on', '<=', $data['ends_on'])->where('ends_on', '>=', $data['starts_on'])->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['starts_on' => 'Rentang cuti bertumpuk dengan pengajuan aktif lainnya.']);
            }
            $year = (int) date('Y', strtotime($data['starts_on']));
            if ($year !== (int) date('Y', strtotime($data['ends_on']))) {
                throw ValidationException::withMessages(['ends_on' => 'Pengajuan lintas tahun harus dipisahkan per tahun.']);
            }
            DB::table('hr_leave_balances')->insertOrIgnore([
                'employee_id' => $employeeId, 'leave_type_id' => $leaveType->id, 'year' => $year,
                'entitled_days' => $leaveType->annual_entitlement_days, 'carried_days' => 0, 'used_days' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $balance = DB::table('hr_leave_balances')->where('employee_id', $employeeId)->where('leave_type_id', $leaveType->id)->where('year', $year)->lockForUpdate()->firstOrFail();
            $available = (float) $balance->entitled_days + (float) $balance->carried_days - (float) $balance->used_days;
            if ($days - $available > .005) {
                throw ValidationException::withMessages(['starts_on' => 'Saldo cuti tidak mencukupi. Tersedia '.number_format($available, 2).' hari.']);
            }

            return DB::table('hr_leave_requests')->insertGetId([
                'company_id' => $companyId, 'employee_id' => $employeeId, 'leave_type_id' => $leaveType->id,
                'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'], 'requested_days' => $days,
                'reason' => $data['reason'], 'status' => 'submitted', 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function decide(int $companyId, int $requestId, int $userId, string $decision, ?string $notes): void
    {
        DB::transaction(function () use ($companyId, $requestId, $userId, $decision, $notes): void {
            $request = DB::table('hr_leave_requests')->where('company_id', $companyId)->where('id', $requestId)->lockForUpdate()->firstOrFail();
            abort_if($request->status !== 'submitted', 422, 'Pengajuan cuti sudah diproses.');
            if ($decision === 'approved') {
                $year = (int) date('Y', strtotime($request->starts_on));
                $balance = DB::table('hr_leave_balances')->where('employee_id', $request->employee_id)->where('leave_type_id', $request->leave_type_id)->where('year', $year)->lockForUpdate()->firstOrFail();
                $available = (float) $balance->entitled_days + (float) $balance->carried_days - (float) $balance->used_days;
                if ((float) $request->requested_days - $available > .005) {
                    throw ValidationException::withMessages(['decision' => 'Saldo cuti berubah dan tidak lagi mencukupi.']);
                }
                DB::table('hr_leave_balances')->where('id', $balance->id)->update(['used_days' => round((float) $balance->used_days + (float) $request->requested_days, 2), 'updated_at' => now()]);
            }
            DB::table('hr_leave_requests')->where('id', $request->id)->update([
                'status' => $decision, 'decided_by' => $userId, 'decided_at' => now(),
                'decision_notes' => $notes, 'updated_at' => now(),
            ]);
        });
    }

    public function cancel(int $companyId, int $requestId, int $actorId, bool $canManage): void
    {
        DB::transaction(function () use ($companyId, $requestId, $actorId, $canManage): void {
            $request = DB::table('hr_leave_requests')->join('hr_employees', 'hr_employees.id', '=', 'hr_leave_requests.employee_id')
                ->where('hr_leave_requests.company_id', $companyId)->where('hr_leave_requests.id', $requestId)
                ->select('hr_leave_requests.*', 'hr_employees.user_id')->lockForUpdate()->firstOrFail();
            abort_unless($canManage || (int) $request->user_id === $actorId, 403);
            abort_unless(in_array($request->status, ['submitted', 'approved'], true), 422, 'Pengajuan tidak dapat dibatalkan.');
            if ($request->status === 'approved') {
                $year = (int) date('Y', strtotime($request->starts_on));
                $balance = DB::table('hr_leave_balances')->where('employee_id', $request->employee_id)->where('leave_type_id', $request->leave_type_id)->where('year', $year)->lockForUpdate()->firstOrFail();
                DB::table('hr_leave_balances')->where('id', $balance->id)->update(['used_days' => max(0, round((float) $balance->used_days - (float) $request->requested_days, 2)), 'updated_at' => now()]);
            }
            DB::table('hr_leave_requests')->where('id', $request->id)->update(['status' => 'cancelled', 'updated_at' => now()]);
        });
    }

    private function businessDays(string $start, string $end): float
    {
        $from = CarbonImmutable::parse($start)->startOfDay();
        $to = CarbonImmutable::parse($end)->startOfDay();
        if ($to->lt($from)) {
            return 0;
        }

        return (float) collect(CarbonPeriod::create($from, $to))->reject(fn ($date): bool => $date->isWeekend())->count();
    }
}
