<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class WebsiteMaintenanceMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Website is live
        if ((int) config('system.is_live') === 1) {
            return $next($request);
        }

        // Super admin is already authenticated
        if (
            auth()->check() &&
            auth()->user()->hasRole('super_admin')
        ) {
            return $next($request);
        }

        // Allow super admin login
        if ($request->is('super-admin/login')) {
            return $next($request);
        }

        // Everyone else gets maintenance page
        return response()->view(
            'frontend.maintenance',
            [
                'message' => config('system.close_msg'),
            ],
            503
        );
    }
}
