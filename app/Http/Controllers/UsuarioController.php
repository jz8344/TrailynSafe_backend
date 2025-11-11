<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Models\Sesion;
use App\Models\CodigoSeguridad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use App\Models\SanctumPersonalAccessToken;

class UsuarioController extends Controller
{
    public function list()
    {
        return response()->json(Usuario::all());
    }

    public function register(Request $request)
    {
        \Log::info('Datos recibidos para registro:', $request->all());
        
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string',
            'apellidos' => 'required|string',
            'telefono' => 'required|string',
            'correo' => 'required|email|unique:usuarios,correo',
            'contrasena' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            \Log::error('Errores de validación en registro:', $validator->errors()->toArray());
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $usuario = Usuario::create([
            'nombre' => $request->nombre,
            'apellidos' => $request->apellidos,
            'telefono' => $request->telefono,
            'correo' => $request->correo,
            'rol' => 'usuario',
            'contrasena' => Hash::make($request->contrasena),
            'fecha_registro' => now(),
        ]);

        return response()->json($usuario, 201);
    }

    public function editarPerfil(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'nombre'     => 'sometimes|required|string',
            'apellidos'  => 'sometimes|required|string',
            'telefono'   => 'sometimes|required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $datos = [];

        if ($request->has('nombre')) {
            $datos['nombre'] = ucwords(strtolower($request->nombre));
        }
        if ($request->has('apellidos')) {
            $datos['apellidos'] = ucwords(strtolower($request->apellidos));
        }
        if ($request->has('telefono')) {
            $datos['telefono'] = $request->telefono;
        }

        $user->update($datos);

        return response()->json([
            'message' => 'Perfil actualizado correctamente.',
            'usuario' => $user->fresh()
        ]);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo' => 'required|email',
            'contrasena' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $usuario = Usuario::where('correo', $request->correo)->first();

        if (!$usuario || !Hash::check($request->contrasena, $usuario->contrasena)) {
            return response()->json(['error' => 'Credenciales inválidas'], 401);
        }

        $tokenInstance = $usuario->createToken('usuario_token');
        $token = $tokenInstance->plainTextToken;
        $tokenId = $tokenInstance->accessToken->id;

        Sesion::create([
            'usuario_id' => $usuario->id,
            'token'      => $token,
            'token_id'   => $tokenId,
            'user_agent' => $request->header('User-Agent'),
            'ip_address' => $request->ip(),
            'inicio'     => now(),
            'estado'     => 'activa',
        ]);

        return response()->json([
            'usuario' => $usuario,
            'token'   => $token,
        ]);
    }

    public function actualizarContrasena(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo' => 'required|email|exists:usuarios,correo',
            'codigo' => 'required|numeric',
            'nueva_contrasena' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $usuario = Usuario::where('correo', $request->correo)->first();

        $codigoDB = CodigoSeguridad::where('usuario_id', $usuario->id)
            ->where('codigo', $request->codigo)
            ->where('expires_at', '>', now())
            ->first();

        if (!$codigoDB) {
            return response()->json(['error' => 'Código incorrecto o expirado.'], 400);
        }

        $usuario->contrasena = Hash::make($request->nueva_contrasena);
        $usuario->save();

        $codigoDB->delete();

        $tokens = $usuario->tokens()->pluck('id');
        $records = PersonalAccessToken::whereIn('id', $tokens)->get();
        PersonalAccessToken::whereIn('id', $tokens)->delete();
        // espejo eliminaciones
        foreach ($records as $rec) {
            SanctumPersonalAccessToken::withoutEvents(function() use ($rec) {
                // Observer borrará por _id si existe
                event('eloquent.deleted: '.SanctumPersonalAccessToken::class, $rec);
            });
        }
        Sesion::where('usuario_id', $usuario->id)->update(['estado' => 'inactiva']);

        return response()->json([
            'message' => 'Contraseña actualizada correctamente. Ahora puedes iniciar sesión.',
            'redirigir' => '/login'
        ]);
    }

    public function validarPasswordActual(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'password_actual' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Contraseña actual requerida.'], 422);
        }

        if (!Hash::check($request->password_actual, $user->contrasena)) {
            return response()->json(['error' => 'Contraseña actual incorrecta.'], 422);
        }

        return response()->json([
            'message' => 'Contraseña válida.',
            'token_validacion' => base64_encode($user->id . ':' . time())
        ]);
    }

    public function cambiarContrasena(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'nueva_contrasena' => 'required|string|min:6',
            'confirmar_contrasena' => 'required|string|same:nueva_contrasena',
            'token_validacion' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }
        
        $tokenData = base64_decode($request->token_validacion);
        $parts = explode(':', $tokenData);
        
        if (count($parts) !== 2 || $parts[0] != $user->id) {
            return response()->json(['error' => 'Token de validación inválido.'], 401);
        }
        
        $timestamp = $parts[1];
        if ((time() - $timestamp) > 300) {
            return response()->json(['error' => 'Token de validación expirado. Intenta nuevamente.'], 401);
        }
        
        $user->contrasena = Hash::make($request->nueva_contrasena);
        $user->save();
        
        $currentToken = $request->bearerToken();
        
        
        Sesion::where('usuario_id', $user->id)->update(['estado' => 'inactiva']);
        
        
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Contraseña cambiada correctamente. Por seguridad, todas las sesiones han sido cerradas.',
            'cerrar_sesion' => true
        ]);
    }

    public function cambiarCorreo(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'nuevo_correo' => 'required|email|unique:usuarios,correo,' . $user->id,
            'contrasena_actual' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        if (!Hash::check($request->contrasena_actual, $user->contrasena)) {
            return response()->json(['error' => 'Contraseña actual incorrecta.'], 401);
        }

        $user->correo = $request->nuevo_correo;
        $user->save();

        return response()->json([
            'message' => 'Correo actualizado correctamente.',
            'usuario' => $user->fresh()
        ]);
    }

    public function checkHaveSon(Request $request)
    {
        $user = Auth::user();
        
        return response()->json([
            'have_son' => $user->have_son,
            'usuario' => $user
        ]);
    }

    public function updateHaveSon(Request $request)
    {
        $user = Auth::user();
        
        $user->update(['have_son' => true]);
        
        return response()->json([
            'message' => 'Estado actualizado correctamente',
            'have_son' => true
        ]);
    }
}
