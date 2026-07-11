<?php

namespace App\Http\Requests;

use App\Support\CompanySettingsOptions;
use App\Support\SupportedLocale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('core.settings.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/\A[0-9+().\-\s]+\z/'],
            'email' => ['nullable', 'email', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:min_width=80,min_height=80,max_width=2400,max_height=2400'],
            'remove_logo' => ['nullable', 'boolean'],
            'locale' => ['required', Rule::in(SupportedLocale::all())],
            'timezone' => ['required', 'timezone:all'],
            'currency' => ['required', Rule::in(array_keys(CompanySettingsOptions::CURRENCIES))],
            'date_format' => ['required', Rule::in(array_keys(CompanySettingsOptions::DATE_FORMATS))],
            'time_format' => ['required', Rule::in(array_keys(CompanySettingsOptions::TIME_FORMATS))],
            'primary_color' => ['required', 'regex:/\A#[0-9A-Fa-f]{6}\z/'],
            'sidebar_color' => ['required', 'regex:/\A#[0-9A-Fa-f]{6}\z/'],
            'accent_color' => ['required', 'regex:/\A#[0-9A-Fa-f]{6}\z/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'display name',
            'legal_name' => 'legal company name',
            'tax_number' => 'tax number',
            'logo' => 'company logo',
            'locale' => 'language',
            'primary_color' => 'primary color',
            'sidebar_color' => 'sidebar color',
            'accent_color' => 'accent color',
        ];
    }

    protected function prepareForValidation(): void
    {
        $nullableStrings = ['legal_name', 'tax_number', 'address', 'phone', 'email'];
        $normalized = [];

        foreach ($nullableStrings as $field) {
            if ($this->has($field)) {
                $value = trim((string) $this->input($field));
                $normalized[$field] = $value === '' ? null : $value;
            }
        }

        foreach (['primary_color', 'sidebar_color', 'accent_color'] as $field) {
            if ($this->has($field)) {
                $normalized[$field] = strtoupper(trim((string) $this->input($field)));
            }
        }

        if ($this->has('currency')) {
            $normalized['currency'] = strtoupper(trim((string) $this->input('currency')));
        }

        $this->merge($normalized);
    }
}
