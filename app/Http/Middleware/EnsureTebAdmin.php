<?php
// app/Http/Middleware/EnsureTebAdmin.php

namespace App\Http\Middleware;

use App\Services\Teb\TebUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// TRB Admin screens: Process Engineering only. Others go back to the TRB dashboard.
class EnsureTebAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! TebUser::isAdmin()) {
            return redirect()->route('teb.dashboard')->with('error', 'Only Process Engineering (TRB Admin) can open that page.');
        }

        return $next($request);
    }
}
