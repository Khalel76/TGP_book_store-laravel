<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()->type != 'author' ) {
            return response()->json([
                'message' => 'you are not an author'
            ], 401);
        }

         if ($request->user()->status != 'approve') {
            return response()->json([
                'message' => 'you are being approve yet'
            ], 401);
        }

        return $next($request);
    }
}
