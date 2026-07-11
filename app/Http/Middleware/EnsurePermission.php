<?php

namespace App\Http\Middleware;

use App\Support\AccessManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function __construct(private readonly AccessManager $access) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $allowed = collect($permissions)->every(
            fn (string $permission): bool => $this->access->allows($permission, $request->user()),
        );

        abort_unless(
            $allowed,
            403,
            app()->getLocale() === 'id' ? 'Anda tidak memiliki izin untuk tindakan ini.' : 'You do not have permission for this action.',
        );

        return $next($request);
    }
}
