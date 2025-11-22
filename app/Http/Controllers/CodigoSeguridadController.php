<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Usuario;
use App\Models\CodigoSeguridad;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\CodigoSeguridadMail;
use Carbon\Carbon;

class CodigoSeguridadController extends Controller
{
    public function enviarCodigo(Request $request)
    {
        $request->validate([
            'correo' => 'required|email',
        ]);

        $usuario = Usuario::where('correo', $request->correo)->first();

        // SEGURIDAD: Siempre retornar éxito para evitar enumeration attacks
        // No revelar si el correo existe o no en la base de datos
        if ($usuario) {
            $codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            CodigoSeguridad::updateOrCreate(
                ['usuario_id' => $usuario->id],
                [
                    'codigo' => $codigo,
                    'expires_at' => now()->addMinutes(10)
                ]
            );

            Mail::to($usuario->correo)->send(new CodigoSeguridadMail($codigo));
        }
        
        // Siempre retornar éxito, incluso si el correo no existe
        return response()->json([
            'success' => true,
            'message' => 'Si el correo está registrado, recibirás un código de verificación.'
        ], 200);
    }

    public function validarCodigo(Request $request)
    {
        $request->validate([
            'correo' => 'required|email',
            'codigo' => 'required|string|size:6',
        ]);

        $usuario = Usuario::where('correo', $request->correo)->first();

        // SEGURIDAD: No revelar si el correo existe, solo si el código es incorrecto
        if (!$usuario) {
            return response()->json(['error' => 'Código incorrecto o expirado.'], 400);
        }

        $codigoDB = CodigoSeguridad::where('usuario_id', $usuario->id)
            ->where('codigo', $request->codigo)
            ->where('expires_at', '>', now())
            ->first();

        if (!$codigoDB) {
            return response()->json(['error' => 'Código incorrecto o expirado.'], 400);
        }

        return response()->json(['message' => 'Código válido.']);
    }
}