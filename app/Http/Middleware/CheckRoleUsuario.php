<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckRoleUsuario
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        if (!$user || $user->rol !== 'usuario') {
            return response()->json(['error' => 'No autorizado. Solo usuarios regulares.'], 403);
        }
        return $next($request);
    }
}
