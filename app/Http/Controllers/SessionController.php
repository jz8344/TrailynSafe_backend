<?php

namespace App\Http\Controllers;

use App\Models\Sesion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class SessionController extends Controller
{
public function index(Request $request)
{
    $userId = Auth::id();
    $currentToken = $request->bearerToken();

    if (!$userId) {
        return response()->json(['message' => 'Usuario no autenticado.'], 401);
    }

    $sesion = Sesion::where('usuario_id', $userId)
        ->where('token', $currentToken)
        ->where('estado', 'activa')
        ->first();

    if (!$sesion) {
        return response()->json(['message' => 'Sesión inválida.'], 401);
    }

    return response()->json([
        'usuario' => Auth::user(),
        'fecha_inicio' => $sesion->inicio,
        'ip' => $sesion->ip_address,
    ]);
}


    public function validarSesion(Request $request)
    {
        $userId = Auth::id();
        $currentToken = $request->bearerToken();

        if (!$currentToken) {
            return response()->json(['valida' => false, 'mensaje' => 'Token no encontrado.'], 401);
        }

        $sesion = Sesion::where('usuario_id', $userId)
            ->where('token', $currentToken)
            ->where('estado', 'activa')
            ->first();

        if (!$sesion) {
            return response()->json(['valida' => false, 'mensaje' => 'Sesión inválida o expirada.'], 401);
        }
        
        $tokenExists = PersonalAccessToken::where('id', $sesion->token_id)->exists();
        
        if (!$tokenExists) {
            $sesion->estado = 'inactiva';
            $sesion->save();
            return response()->json(['valida' => false, 'mensaje' => 'Token eliminado.'], 401);
        }

        return response()->json([
            'valida' => true,
            'usuario' => Auth::user(),
            'sesion' => [
                'fecha_inicio' => $sesion->inicio,
                'ip' => $sesion->ip_address,
            ]
        ]);
    }

    public function destroy($id)
    {
        $sesion = Sesion::find($id);

        if (!$sesion) {
            return response()->json(['message' => 'Sesión no encontrada.'], 404);
        }

        if ($sesion->usuario_id !== Auth::id()) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $sesion->estado = 'inactiva';
        $sesion->save();

        if ($sesion->token_id) {
            PersonalAccessToken::where('id', $sesion->token_id)->delete();
        }

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }
    

    public function destroyAllExceptCurrent(Request $request)
    {
        $userId = Auth::id();
        $currentToken = $request->bearerToken();

        if (!$currentToken) {
            return response()->json(['message' => 'Token no encontrado.'], 400);
        }

        $sesiones = Sesion::where('usuario_id', $userId)
            ->where('token', '!=', $currentToken)
            ->where('estado', 'activa')
            ->get();

        $cerradas = 0;
        foreach ($sesiones as $sesion) {
            $sesion->estado = 'inactiva';
            $sesion->save();
            
            if ($sesion->token_id) {
                PersonalAccessToken::where('id', $sesion->token_id)->delete();
            }
            $cerradas++;
        }

        return response()->json([
            'message' => "Se cerraron {$cerradas} sesiones.",
            'sesiones_cerradas' => $cerradas
        ]);
    }

    public function destroyCurrent(Request $request)
    {
        $userId = Auth::id();
        $currentToken = $request->bearerToken();

        if (!$currentToken) {
            return response()->json(['message' => 'Token no encontrado.'], 400);
        }

        $sesion = Sesion::where('usuario_id', $userId)
            ->where('token', $currentToken)
            ->where('estado', 'activa')
            ->first();

        if ($sesion) {
            $sesion->estado = 'inactiva';
            $sesion->save();
            
            if ($sesion->token_id) {
                PersonalAccessToken::where('id', $sesion->token_id)->delete();
            }
        }

        return response()->json(['message' => 'Sesión actual cerrada.']);
    }
    public function destroyAll(Request $request)
{
    $userId = Auth::id();

    Sesion::where('usuario_id', $userId)->delete();

    return response()->json(['message' => 'Todas las sesiones eliminadas.']);
}

}