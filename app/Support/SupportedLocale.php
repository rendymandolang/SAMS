<?php

namespace App\Support;

final class SupportedLocale
{
    public const INDONESIAN = 'id';

    public const ENGLISH = 'en';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [self::INDONESIAN, self::ENGLISH];
    }

    public static function isSupported(mixed $locale): bool
    {
        return is_string($locale)
            && in_array(strtolower(trim($locale)), self::all(), true);
    }

    public static function normalize(mixed $locale): ?string
    {
        if (! self::isSupported($locale)) {
            return null;
        }

        return strtolower(trim($locale));
    }

    public static function resolve(mixed $locale): string
    {
        return self::normalize($locale)
            ?? self::normalize(config('localization.default'))
            ?? self::INDONESIAN;
    }

    public static function sessionKey(): string
    {
        return (string) config('localization.session_key', 'locale');
    }

    /**
     * @return array<string, array{name: string, short_name: string}>
     */
    public static function options(): array
    {
        $configured = (array) config('localization.supported', []);

        return collect(self::all())
            ->mapWithKeys(function (string $locale) use ($configured): array {
                $fallback = $locale === self::INDONESIAN
                    ? ['name' => 'Bahasa Indonesia', 'short_name' => 'ID']
                    : ['name' => 'English', 'short_name' => 'EN'];
                $option = array_merge($fallback, (array) ($configured[$locale] ?? []));

                return [$locale => [
                    'name' => (string) $option['name'],
                    'short_name' => (string) $option['short_name'],
                ]];
            })
            ->all();
    }
}
