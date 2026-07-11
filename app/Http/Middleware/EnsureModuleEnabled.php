<?php

namespace App\Http\Middleware;

use App\Support\AccessManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    public function __construct(private readonly AccessManager $access) {}

    public function handle(Request $request, Closure $next, string $module): Response
    {
        abort_unless(
            $this->access->moduleEnabled($module, $request->user()),
            403,
            app()->getLocale() === 'id' ? 'Modul ini tidak aktif untuk perusahaan Anda.' : 'This module is not enabled for your company.',
        );

        return $next($request);
    }
}
