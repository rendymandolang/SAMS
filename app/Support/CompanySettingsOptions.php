<?php

namespace App\Support;

final class CompanySettingsOptions
{
    public const CURRENCIES = [
        'IDR' => 'IDR - Indonesian Rupiah',
        'USD' => 'USD - US Dollar',
        'SGD' => 'SGD - Singapore Dollar',
        'MYR' => 'MYR - Malaysian Ringgit',
        'AUD' => 'AUD - Australian Dollar',
        'EUR' => 'EUR - Euro',
        'JPY' => 'JPY - Japanese Yen',
        'CNY' => 'CNY - Chinese Yuan',
    ];

    public const DATE_FORMATS = [
        'd/m/Y' => 'DD/MM/YYYY',
        'd-m-Y' => 'DD-MM-YYYY',
        'Y-m-d' => 'YYYY-MM-DD',
        'm/d/Y' => 'MM/DD/YYYY',
    ];

    public const TIME_FORMATS = [
        'H:i' => '24-hour (14:30)',
        'h:i A' => '12-hour (02:30 PM)',
    ];

    public const TIMEZONES = [
        'Asia/Jayapura' => 'Indonesia Timur (WIT)',
        'Asia/Makassar' => 'Indonesia Tengah (WITA)',
        'Asia/Jakarta' => 'Indonesia Barat (WIB)',
        'Asia/Singapore' => 'Singapore',
        'Asia/Kuala_Lumpur' => 'Kuala Lumpur',
        'Asia/Tokyo' => 'Tokyo',
        'Australia/Perth' => 'Perth',
        'Australia/Sydney' => 'Sydney',
        'Europe/London' => 'London',
        'America/New_York' => 'New York',
        'UTC' => 'UTC',
    ];

    /**
     * @return array<string, array{label: string, primary: string, sidebar: string, accent: string}>
     */
    public static function palettes(): array
    {
        return [
            'indigo' => [
                'label' => 'Soft Indigo',
                'primary' => '#5967D8',
                'sidebar' => '#182335',
                'accent' => '#2F9D8F',
            ],
            'ocean' => [
                'label' => 'Ocean Blue',
                'primary' => '#3B74B7',
                'sidebar' => '#15283B',
                'accent' => '#2FA89A',
            ],
            'forest' => [
                'label' => 'Calm Forest',
                'primary' => '#397C6A',
                'sidebar' => '#18312C',
                'accent' => '#C2934D',
            ],
        ];
    }
}
