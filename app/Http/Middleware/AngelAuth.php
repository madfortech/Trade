<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AngelAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Agar request login page ki hai, toh aage badhne do (Loop break point)
        if ($request->is('login-process') || $request->routeIs('angel.login')) {
            return $next($request);
        }

        // Sirf Session check karein (kyunki Controller session use kar raha hai)
        if (!session()->has('angel_jwt')) {
            return redirect()->route('angel.login')->with('error', 'Pehle login karein.');
        }
        
        return $next($request);
    }
}
