<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureActive
{
    public function handle(Request $request, Closure $next)
    {
        abort_if($request->user()?->suspended, 403, 'Your account has been suspended. Contact the community team.');

        return $next($request);
    }
}
