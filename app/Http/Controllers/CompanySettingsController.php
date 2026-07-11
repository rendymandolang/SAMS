<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCompanySettingsRequest;
use App\Support\AuditLogger;
use App\Support\CompanyContext;
use App\Support\CompanySettingsOptions;
use App\Support\SupportedLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class CompanySettingsController extends Controller
{
    public function edit(CompanyContext $context): View
    {
        $company = $context->current();

        return view('settings.company', [
            'company' => $company,
            'languages' => collect(SupportedLocale::options())
                ->mapWithKeys(fn (array $option, string $locale): array => [$locale => $option['name']])
                ->all(),
            'currencies' => CompanySettingsOptions::CURRENCIES,
            'dateFormats' => CompanySettingsOptions::DATE_FORMATS,
            'timeFormats' => CompanySettingsOptions::TIME_FORMATS,
            'timezones' => CompanySettingsOptions::TIMEZONES,
            'palettes' => CompanySettingsOptions::palettes(),
            'logoUrl' => filled($company->logo_path)
                ? url('storage/'.$company->logo_path)
                : null,
        ]);
    }

    public function update(UpdateCompanySettingsRequest $request, CompanyContext $context): RedirectResponse
    {
        $company = $context->current();
        $validated = $request->validated();
        $logo = $request->file('logo');
        $storedLogoPath = null;

        $payload = Arr::except($validated, ['logo', 'remove_logo']);
        $oldLogoPath = $company->logo_path;

        if ($logo) {
            $storedLogoPath = $logo->store('company-logos/'.$company->public_id, 'public');
            $payload['logo_path'] = $storedLogoPath;
        } elseif ($request->boolean('remove_logo')) {
            $payload['logo_path'] = null;
        }

        $auditFields = [
            'name',
            'legal_name',
            'tax_number',
            'address',
            'phone',
            'email',
            'logo_path',
            'locale',
            'timezone',
            'currency',
            'date_format',
            'time_format',
            'primary_color',
            'sidebar_color',
            'accent_color',
        ];
        $oldValues = Arr::only((array) $company, $auditFields);

        try {
            DB::transaction(function () use ($company, $payload, $oldValues, $auditFields): void {
                DB::table('companies')
                    ->where('id', $company->id)
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->update([...$payload, 'updated_at' => now()]);

                $updated = DB::table('companies')->where('id', $company->id)->firstOrFail();

                AuditLogger::log(
                    'company_settings_updated',
                    'company',
                    (int) $company->id,
                    $oldValues,
                    Arr::only((array) $updated, $auditFields),
                    (int) $company->id,
                );
            });
        } catch (Throwable $exception) {
            if ($storedLogoPath) {
                Storage::disk('public')->delete($storedLogoPath);
            }

            throw $exception;
        }

        $newLogoPath = array_key_exists('logo_path', $payload)
            ? $payload['logo_path']
            : $oldLogoPath;

        if ($oldLogoPath && $oldLogoPath !== $newLogoPath && str_starts_with($oldLogoPath, 'company-logos/')) {
            Storage::disk('public')->delete($oldLogoPath);
        }

        return redirect()
            ->route('settings.company.edit')
            ->with('status', __('common.feedback.saved'));
    }
}
