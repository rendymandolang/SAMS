<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class LiveBankRateService
{
    private const CURRENCIES = ['usd', 'eur', 'sgd', 'aud', 'jpy'];

    public function current(): array
    {
        $key = config('services.api_co_id.key');
        $bankCode = strtolower((string) config('services.api_co_id.bank_code', 'bri'));

        if (! is_string($key) || trim($key) === '') {
            return $this->unavailable($bankCode, 'API belum dikonfigurasi');
        }

        try {
            return Cache::remember(
                "live-bank-rate:{$bankCode}",
                now()->addMinutes(max(1, (int) config('services.api_co_id.cache_minutes', 30))),
                fn (): array => $this->fetch($key, $bankCode),
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->unavailable($bankCode, 'Data live sedang tidak tersedia');
        }
    }

    private function fetch(string $key, string $bankCode): array
    {
        $response = Http::baseUrl(rtrim((string) config('services.api_co_id.base_url'), '/'))
            ->withHeaders(['x-api-co-id' => $key])
            ->acceptJson()
            ->connectTimeout(4)
            ->timeout(8)
            ->retry(2, 250)
            ->get('/api/bank-rates', ['bank_code' => $bankCode])
            ->throw()
            ->json();

        if (($response['is_success'] ?? false) !== true || ! is_array($response['data']['rate'] ?? null)) {
            throw new \RuntimeException('Unexpected bank-rate response.');
        }

        $rateTypes = $response['data']['rate'];
        $selectedType = isset($rateTypes['e-rate']) ? 'e-rate' : (string) array_key_first($rateTypes);
        $selectedRates = is_array($rateTypes[$selectedType] ?? null) ? $rateTypes[$selectedType] : [];
        $rates = [];

        foreach (self::CURRENCIES as $currency) {
            $row = $selectedRates[$currency] ?? null;
            if (is_array($row) && ($row['buy'] ?? null) !== null && ($row['sell'] ?? null) !== null) {
                $rates[] = [
                    'currency' => strtoupper($currency),
                    'buy' => (float) $row['buy'],
                    'sell' => (float) $row['sell'],
                ];
            }
        }

        return [
            'available' => true,
            'bank_code' => strtoupper((string) ($response['data']['bank_code'] ?? $bankCode)),
            'rate_type' => strtoupper($selectedType),
            'updated_at' => $this->formatUpdatedAt($response['data']['last_fetched_at'] ?? null),
            'rates' => $rates,
            'message' => null,
        ];
    }

    private function formatUpdatedAt(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = is_numeric($value)
                ? Carbon::createFromTimestampMs((int) $value)
                : Carbon::parse((string) $value);

            return $date->setTimezone(config('app.timezone'))->format('d M Y, H:i');
        } catch (Throwable) {
            return null;
        }
    }

    private function unavailable(string $bankCode, string $message): array
    {
        return [
            'available' => false,
            'bank_code' => strtoupper($bankCode),
            'rate_type' => null,
            'updated_at' => null,
            'rates' => [],
            'message' => $message,
        ];
    }
}
