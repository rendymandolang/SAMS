<?php

namespace App\Http\Controllers;

use App\Support\AccountingCurrencyService;
use App\Support\AuditLogger;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountingConfigurationController extends Controller
{
    private const RULES = [
        'ap_invoice' => ['payable' => ['liability'], 'expense' => ['asset', 'expense']],
        'ar_invoice' => ['receivable' => ['asset'], 'revenue' => ['revenue']],
        'ap_payment' => ['cash' => ['asset']],
        'ar_receipt' => ['cash' => ['asset']],
        'fiscal_close' => ['retained_earnings' => ['equity']],
    ];

    public function index(CompanyContext $context): View
    {
        $companyId = $context->id();

        return view('accounting.configuration', [
            'company' => $context->current(),
            'settings' => DB::table('accounting_settings')->where('company_id', $companyId)->first(),
            'taxCodes' => DB::table('accounting_tax_codes')->join('gl_accounts', 'gl_accounts.id', '=', 'accounting_tax_codes.gl_account_id')->where('accounting_tax_codes.company_id', $companyId)->select('accounting_tax_codes.*', 'gl_accounts.code as gl_code', 'gl_accounts.name as gl_name')->orderBy('accounting_tax_codes.code')->get(),
            'postingRules' => DB::table('accounting_posting_rules')->join('gl_accounts', 'gl_accounts.id', '=', 'accounting_posting_rules.gl_account_id')->where('accounting_posting_rules.company_id', $companyId)->select('accounting_posting_rules.*', 'gl_accounts.code as gl_code', 'gl_accounts.name as gl_name')->orderBy('transaction_type')->orderBy('account_role')->get(),
            'accounts' => DB::table('gl_accounts')->where('company_id', $companyId)->where('is_active', true)->where('allow_posting', true)->orderBy('code')->get(),
            'ruleOptions' => self::RULES,
            'exchangeRates' => DB::table('accounting_exchange_rates')->where('company_id', $companyId)->orderByDesc('rate_date')->orderBy('currency')->limit(100)->get(),
        ]);
    }

    public function storeExchangeRate(Request $request, CompanyContext $context): RedirectResponse
    {
        $data = $request->validate(['currency' => ['required', 'string', 'size:3', 'not_in:'.strtoupper($context->current()->currency)], 'rate_date' => ['required', 'date'], 'rate_to_base' => ['required', 'numeric', 'gt:0'], 'source' => ['nullable', 'string', 'max:80']]);
        $data['currency'] = Str::upper($data['currency']);
        DB::table('accounting_exchange_rates')->updateOrInsert(['company_id' => $context->id(), 'currency' => $data['currency'], 'rate_date' => $data['rate_date']], ['rate_to_base' => $data['rate_to_base'], 'source' => ($data['source'] ?? null) ?: 'manual', 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
        AuditLogger::log('accounting_exchange_rate_updated', 'accounting_exchange_rate', $context->id(), null, $data, $context->id());

        return back()->with('status', 'Exchange rate berhasil disimpan.');
    }

    public function storeFxAccounts(Request $request, CompanyContext $context): RedirectResponse
    {
        $data = $request->validate(['realized_fx_gain_account_id' => ['required', 'integer'], 'realized_fx_loss_account_id' => ['required', 'integer'], 'unrealized_fx_gain_account_id' => ['required', 'integer'], 'unrealized_fx_loss_account_id' => ['required', 'integer']]);
        foreach (['realized_fx_gain_account_id', 'unrealized_fx_gain_account_id'] as $field) {
            abort_unless(DB::table('gl_accounts')->where('company_id', $context->id())->where('id', $data[$field])->where('type', 'revenue')->where('is_active', true)->where('allow_posting', true)->exists(), 422);
        }
        foreach (['realized_fx_loss_account_id', 'unrealized_fx_loss_account_id'] as $field) {
            abort_unless(DB::table('gl_accounts')->where('company_id', $context->id())->where('id', $data[$field])->where('type', 'expense')->where('is_active', true)->where('allow_posting', true)->exists(), 422);
        }
        DB::table('accounting_settings')->updateOrInsert(['company_id' => $context->id()], $data + ['created_at' => now(), 'updated_at' => now()]);
        AuditLogger::log('accounting_fx_accounts_updated', 'accounting_setting', $context->id(), null, $data, $context->id());

        return back()->with('status', 'FX gain/loss accounts berhasil disimpan.');
    }

    public function revalue(Request $request, CompanyContext $context, AccountingCurrencyService $service): RedirectResponse
    {
        $data = $request->validate(['currency' => ['required', 'string', 'size:3'], 'revaluation_date' => ['required', 'date']]);
        $id = $service->revalue($context->id(), $data['currency'], $data['revaluation_date'], (int) auth()->id());
        AuditLogger::log('accounting_fx_revaluation_posted', 'accounting_fx_revaluation', $id, null, $data, $context->id());

        return back()->with('status', 'Period-end FX revaluation berhasil diposting.');
    }

    public function storeSettings(Request $request, CompanyContext $context): RedirectResponse
    {
        $v = $request->validate([
            'po_price_tolerance_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'po_quantity_tolerance_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
        DB::table('accounting_settings')->updateOrInsert(['company_id' => $context->id()], $v + ['updated_at' => now(), 'created_at' => now()]);
        AuditLogger::log('accounting_controls_updated', 'accounting_setting', $context->id(), null, $v, $context->id());

        return back()->with('status', 'Accounting control tolerances berhasil disimpan.');
    }

    public function storeTaxCode(Request $request, CompanyContext $context): RedirectResponse
    {
        $v = $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9.\-]+$/'], 'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['purchase', 'sales', 'withholding'])], 'rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'gl_account_id' => ['required', 'integer'],
        ]);
        $v['code'] = Str::upper(trim($v['code']));
        $types = $v['type'] === 'purchase' ? ['asset', 'expense'] : ['liability'];
        if (! DB::table('gl_accounts')->where('company_id', $context->id())->where('id', $v['gl_account_id'])->whereIn('type', $types)->where('is_active', true)->where('allow_posting', true)->exists()) {
            throw ValidationException::withMessages(['gl_account_id' => 'GL account tidak sesuai dengan jenis pajak.']);
        }
        if (DB::table('accounting_tax_codes')->where('company_id', $context->id())->where('code', $v['code'])->exists()) {
            throw ValidationException::withMessages(['code' => 'Tax code sudah digunakan.']);
        }
        $id = DB::table('accounting_tax_codes')->insertGetId($v + ['company_id' => $context->id(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        AuditLogger::log('accounting_tax_code_created', 'accounting_tax_code', $id, null, $v, $context->id());

        return back()->with('status', 'Tax code berhasil ditambahkan.');
    }

    public function storePostingRule(Request $request, CompanyContext $context): RedirectResponse
    {
        $v = $request->validate(['transaction_type' => ['required', Rule::in(array_keys(self::RULES))], 'account_role' => ['required', 'string'], 'gl_account_id' => ['required', 'integer']]);
        $types = self::RULES[$v['transaction_type']][$v['account_role']] ?? null;
        if (! $types || ! DB::table('gl_accounts')->where('company_id', $context->id())->where('id', $v['gl_account_id'])->whereIn('type', $types)->where('is_active', true)->where('allow_posting', true)->exists()) {
            throw ValidationException::withMessages(['gl_account_id' => 'Account role dan GL account tidak kompatibel.']);
        }
        DB::table('accounting_posting_rules')->updateOrInsert(
            ['company_id' => $context->id(), 'transaction_type' => $v['transaction_type'], 'account_role' => $v['account_role']],
            ['gl_account_id' => $v['gl_account_id'], 'created_at' => now(), 'updated_at' => now()]
        );
        AuditLogger::log('accounting_posting_rule_updated', 'accounting_posting_rule', $context->id(), null, $v, $context->id());

        return back()->with('status', 'Posting rule berhasil disimpan.');
    }

    public function toggleTaxCode(int $taxCode, CompanyContext $context): RedirectResponse
    {
        $tax = DB::table('accounting_tax_codes')->where('company_id', $context->id())->where('id', $taxCode)->firstOrFail();
        DB::table('accounting_tax_codes')->where('id', $tax->id)->update(['is_active' => ! $tax->is_active, 'updated_at' => now()]);
        AuditLogger::log('accounting_tax_code_status_changed', 'accounting_tax_code', (int) $tax->id, ['is_active' => (bool) $tax->is_active], ['is_active' => ! $tax->is_active], $context->id());

        return back()->with('status', 'Status tax code berhasil diperbarui.');
    }
}
