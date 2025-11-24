<?php

namespace App\Http\Controllers;

use App\Models\Chofer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ChoferAuthController extends Controller
{
    /**
     * Login de chofer
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Datos inválidos',
                'messages' => $validator->errors()
            ], 422);
        }

        $chofer = Chofer::where('correo', $request->correo)->first();

        if (!$chofer || !Hash::check($request->password, $chofer->password)) {
            throw ValidationException::withMessages([
                'correo' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        // Verificar que el chofer esté disponible (estados válidos: disponible, en_ruta, no_activo)
        if ($chofer->estado === 'no_activo') {
            return response()->json([
                'error' => 'Tu cuenta no está activa. Contacta al administrador.'
            ], 403);
        }

        // Crear token
        $token = $chofer->createToken('chofer-token')->plainTextToken;

        return response()->json([
            'chofer' => [
                'id' => $chofer->id,
                'nombre' => $chofer->nombre,
                'apellidos' => $chofer->apellidos,
                'correo' => $chofer->correo,
                'telefono' => $chofer->telefono,
                'licencia' => $chofer->licencia ?? $chofer->numero_licencia,
                'estado' => $chofer->estado,
            ],
            'token' => $token,
            'message' => 'Login exitoso'
        ]);
    }

    /**
     * Logout de chofer
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada exitosamente'
        ]);
    }

    /**
     * Obtener perfil del chofer autenticado
     */
    public function profile(Request $request)
    {
        $chofer = $request->user();

        return response()->json([
            'chofer' => [
                'id' => $chofer->id,
                'nombre' => $chofer->nombre,
                'apellidos' => $chofer->apellidos,
                'correo' => $chofer->correo,
                'telefono' => $chofer->telefono,
                'licencia' => $chofer->licencia ?? $chofer->numero_licencia,
                'curp' => $chofer->curp,
                'estado' => $chofer->estado,
                'created_at' => $chofer->created_at,
            ]
        ]);
    }

    /**
     * Actualizar perfil del chofer
     */
    public function updateProfile(Request $request)
    {
        $chofer = $request->user();

        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|string|max:255',
            'apellidos' => 'sometimes|string|max:255',
            'telefono' => 'sometimes|string|max:20',
            'password' => 'sometimes|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Datos inválidos',
                'messages' => $validator->errors()
            ], 422);
        }

        $data = $request->only(['nombre', 'apellidos', 'telefono']);

        if ($request->has('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $chofer->update($data);

        return response()->json([
            'message' => 'Perfil actualizado exitosamente',
            'chofer' => [
                'id' => $chofer->id,
                'nombre' => $chofer->nombre,
                'apellidos' => $chofer->apellidos,
                'correo' => $chofer->correo,
                'telefono' => $chofer->telefono,
            ]
        ]);
    }

    /**
     * Cambiar contraseña
     */
    public function changePassword(Request $request)
    {
        $chofer = $request->user();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Datos inválidos',
                'messages' => $validator->errors()
            ], 422);
        }

        if (!Hash::check($request->current_password, $chofer->password)) {
            return response()->json([
                'error' => 'La contraseña actual es incorrecta'
            ], 422);
        }

        $chofer->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json([
            'message' => 'Contraseña actualizada exitosamente'
        ]);
    }
}
