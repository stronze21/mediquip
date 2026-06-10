<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportsAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        // Allow access for admin and manager roles
        if (in_array($user->role, ['admin', 'manager'])) {
            return $next($request);
        }

        if ($user->hasPermission('view_reports')) {
            return $next($request);
        }

        if ($user->role === 'cashier' && $request->routeIs('reports.sales*')) {
            return $next($request);
        }

        // Deny access for unauthorized roles
        abort(403, 'You do not have permission to access reports.');
    }
}
