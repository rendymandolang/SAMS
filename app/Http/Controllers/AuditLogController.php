<?php

namespace App\Http\Controllers;

use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $companyId = app(CompanyContext::class)->id();
        $event = $request->input('event');
        $auditableType = $request->input('auditable_type');

        $auditLogs = DB::table('audit_logs')
            ->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
            ->where('audit_logs.company_id', $companyId)
            ->when($event, fn ($query) => $query->where('audit_logs.event', $event))
            ->when($auditableType, fn ($query) => $query->where('audit_logs.auditable_type', $auditableType))
            ->select('audit_logs.*', 'users.name as user_name', 'users.email as user_email')
            ->orderByDesc('audit_logs.created_at')
            ->orderByDesc('audit_logs.id')
            ->paginate(15)
            ->withQueryString();

        $events = DB::table('audit_logs')
            ->where('company_id', $companyId)
            ->select('event')
            ->distinct()
            ->orderBy('event')
            ->pluck('event');

        $auditableTypes = DB::table('audit_logs')
            ->where('company_id', $companyId)
            ->select('auditable_type')
            ->distinct()
            ->orderBy('auditable_type')
            ->pluck('auditable_type');

        return view('audit_logs.index', compact('auditLogs', 'events', 'auditableTypes', 'event', 'auditableType'));
    }
}
