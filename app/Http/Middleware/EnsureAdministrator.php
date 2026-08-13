<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->hasPermission('groups.manage'), 403, 'Permissão insuficiente.');

        return $next($request);
    }
}
