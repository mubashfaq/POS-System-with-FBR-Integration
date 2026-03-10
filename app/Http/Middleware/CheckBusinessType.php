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
    public function handle(Request $request, Closure $next , ...$types): Response
    {

        // First try API business (from middleware attribute)
        $business = $request->attributes->get('business');

        $businessId = $business ? $business->id : session('business_id');

         $businessType =  Business::where('id', $businessId)->value('business_type');
        // ya auth()->user()->business_type agar DB se aa raha hai

        if (!$businessType || !in_array($businessType, $types)) {
            abort(403, 'Unauthorized Access');
        }

        return $next($request);
        // return $next($request);
    }
}
