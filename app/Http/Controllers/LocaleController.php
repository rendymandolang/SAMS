<?php

namespace App\Http\Controllers;

use App\Support\SupportedLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        $locale = SupportedLocale::normalize($locale);

        abort_if($locale === null, 404);

        $request->session()->put(SupportedLocale::sessionKey(), $locale);
        App::setLocale($locale);

        return redirect()
            ->to($this->safePreviousUrl($request))
            ->with('status', __('common.feedback.language_updated'));
    }

    private function safePreviousUrl(Request $request): string
    {
        $fallback = url('/');
        $previous = url()->previous($fallback);
        $parts = parse_url($previous);

        if (! is_array($parts) || ! isset($parts['host'])) {
            return $fallback;
        }

        $previousScheme = strtolower((string) ($parts['scheme'] ?? ''));
        $previousPort = (int) ($parts['port'] ?? ($previousScheme === 'https' ? 443 : 80));

        if (
            strcasecmp((string) $parts['host'], $request->getHost()) !== 0
            || $previousScheme !== strtolower($request->getScheme())
            || $previousPort !== $request->getPort()
        ) {
            return $fallback;
        }

        return $previous;
    }
}
