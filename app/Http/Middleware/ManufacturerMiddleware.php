<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ManufacturerMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()->type == 'manufacturer' || $request->user()->type == 'admin') {
            return $next($request);
        }

        return response()->json([
            'message' => 'you are not a manufacturer'
        ], 403);
    }
}
