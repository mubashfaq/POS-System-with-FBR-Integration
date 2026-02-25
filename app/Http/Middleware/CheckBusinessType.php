<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Business;
class CheckBusinessType
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next , $type): Response
    {
        
        $businessId = session('business_id');

         $businessType =  Business::where('id', $businessId)->where('business_type', $type)->value('business_type');
        // ya auth()->user()->business_type agar DB se aa raha hai

        if ($businessType !== $type) {
            abort(403, 'Unauthorized Access');
        }

    return $next($request);
        return $next($request);
    }
}
