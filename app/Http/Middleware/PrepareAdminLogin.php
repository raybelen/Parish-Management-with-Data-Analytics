<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrepareAdminLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->session()->forget(['login', 'admin', 'auth.password_confirmed_at']);
        $request->merge(['remember' => false]);

        return $next($request);
    }
}
