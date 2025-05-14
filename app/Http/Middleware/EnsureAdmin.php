<?php

namespace App\Http\Middleware;

use App\Models\Administrator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {   
        $isAdmin = Administrator::where('username', auth()->user()->username)->exists();
        if (!$isAdmin) {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not the administrator'
            ],403);
        }
        return $next($request);
    }
}
