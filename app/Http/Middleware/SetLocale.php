<?php

namespace App\Http\Middleware;

use App\Support\CompanyContext;
use App\Support\SupportedLocale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function __construct(private readonly CompanyContext $companyContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $rawStoredLocale = $request->hasSession()
            ? $request->session()->get(SupportedLocale::sessionKey())
            : null;
        $storedLocale = SupportedLocale::normalize($rawStoredLocale);
        $companyLocale = $storedLocale === null && $request->user()
            ? ($this->companyContext->current()->locale ?? null)
            : null;
        $locale = SupportedLocale::resolve($storedLocale ?? $companyLocale);

        if ($rawStoredLocale !== null && $storedLocale === null) {
            $request->session()->forget(SupportedLocale::sessionKey());
        }

        App::setLocale($locale);
        $request->attributes->set('locale', $locale);

        return $next($request);
    }
}
