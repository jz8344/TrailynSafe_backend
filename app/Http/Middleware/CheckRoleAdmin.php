<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckRoleAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('admin-sanctum')->user();
        if (!$user || $user->rol !== 'admin') {
            return response()->json(['error' => 'No autorizado. Solo usuarios admin.'], 403);
        }
        return $next($request);
    }
}
