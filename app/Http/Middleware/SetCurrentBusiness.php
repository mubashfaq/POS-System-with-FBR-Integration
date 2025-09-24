<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentBusiness
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Agar URL mein business ID hai aur session mein different business ID hai
        if ($request->route()->hasParameter('business')) {
            $routeBusinessId = $request->route()->parameter('business');
            $sessionBusinessId = session('business_id');
            
            // Agar route ki ID session se match nahi karti
            if ($sessionBusinessId && $routeBusinessId != $sessionBusinessId) {
                // Redirect to current business route
                $newRoute = $request->route()->getName();
                $parameters = $request->route()->parameters();
                $parameters['business'] = $sessionBusinessId;
                
                return redirect()->route($newRoute, $parameters);
            }
        }
        
        return $next($request);
    }
}
