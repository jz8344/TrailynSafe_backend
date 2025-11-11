<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Usuario;
use App\Models\Sesion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class AdminController extends Controller
{
    public function list()
    {
        return response()->json(Admin::all());
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string',
            'apellidos' => 'required|string',
            'email' => 'required|email|unique:admins,email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $admin = Admin::create([
            'nombre' => $request->nombre,
            'apellidos' => $request->apellidos,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'rol' => 'admin',
            'fecha_registro' => now(),
        ]);

        return response()->json($admin, 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $admin = Admin::where('email', $request->email)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json(['error' => 'Credenciales inválidas'], 401);
        }

        $tokenInstance = $admin->createToken('admin_token');
        $token = $tokenInstance->plainTextToken;
        $tokenId = $tokenInstance->accessToken->id;

        Sesion::create([
            'usuario_id' => $admin->id,
            'token' => $token,
            'token_id' => $tokenId,
            'user_agent' => $request->header('User-Agent'),
            'ip_address' => $request->ip(),
            'inicio' => now(),
            'estado' => 'activa',
        ]);

        return response()->json([
            'token' => $token,
            'admin' => $admin,
            'token_type' => 'Bearer',
            'user_type' => 'admin',
            'success' => true
        ]);
    }

    public function editarPerfil(Request $request)
    {
        $admin = Auth::guard('admin-sanctum')->user();

        if (!$admin instanceof Admin) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|required|string|max:255',
            'apellidos' => 'sometimes|required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $datos = [];

        if ($request->has('nombre')) {
            $datos['nombre'] = ucwords(strtolower(trim($request->nombre)));
        }
        if ($request->has('apellidos')) {
            $datos['apellidos'] = ucwords(strtolower(trim($request->apellidos)));
        }

        $admin->update($datos);

        return response()->json([
            'message' => 'Perfil actualizado correctamente.',
            'admin' => $admin->fresh()
        ]);
    }

    public function newPassword(Request $request)
    {
        $admin = Auth::guard('admin-sanctum')->user();

        if (!$admin instanceof Admin) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $validator = Validator::make($request->all(), [
            'password_actual' => 'required|string',
            'nueva_password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if (!Hash::check($request->password_actual, $admin->password)) {
            return response()->json(['error' => 'La contraseña actual es incorrecta.'], 400);
        }

        $admin->password = Hash::make($request->nueva_password);
        $admin->save();

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }

    public function usersIndex()
    {
        $users = Usuario::orderByDesc('id')->get();
        return response()->json($users);
    }

    public function createUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string',
            'apellidos' => 'required|string',
            'telefono' => 'nullable|string',
            'correo' => 'required|email|unique:usuarios,correo',
            'contrasena' => 'required|string|min:6'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
        $user = Usuario::create([
            'nombre' => ucwords(strtolower($request->nombre)),
            'apellidos' => ucwords(strtolower($request->apellidos)),
            'telefono' => $request->telefono ?? '',
            'correo' => $request->correo,
            'rol' => 'usuario',
            'contrasena' => Hash::make($request->contrasena),
            'fecha_registro' => now(),
        ]);
        return response()->json($user, 201);
    }

    public function updateUser(Request $request, $id)
    {
        $user = Usuario::find($id);
        if (!$user) {
            return response()->json(['error' => 'Usuario no encontrado.'], 404);
        }
        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|string',
            'apellidos' => 'sometimes|string',
            'telefono' => 'sometimes|nullable|string',
            'correo' => 'sometimes|email|unique:usuarios,correo,' . $user->id,
            'contrasena' => 'sometimes|string|min:6'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
        $data = [];
        foreach (['nombre','apellidos','telefono','correo'] as $field) {
            if ($request->has($field)) {
                $val = $request->$field;
                if (in_array($field, ['nombre','apellidos'])) {
                    $val = ucwords(strtolower($val));
                }
                $data[$field] = $val;
            }
        }
        if ($request->has('contrasena')) {
            $data['contrasena'] = Hash::make($request->contrasena);
        }
        $user->update($data);
        return response()->json($user->fresh());
    }

    public function deleteUser($id)
    {
        $user = Usuario::find($id);
        if (!$user) {
            return response()->json(['error' => 'Usuario no encontrado.'], 404);
        }
        $user->delete();
        return response()->json(['message' => 'Usuario eliminado.']);
    }

    public function obtenerSesion(Request $request)
    {
        $user = Auth::guard('admin-sanctum')->user();
        
        if ($user && get_class($user) === 'App\Models\Admin') {
            $admin = $user;
        } else {
            $admin = null;
        }
        
        if (!$admin) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        return response()->json([
            'authenticated' => true,
            'admin' => $admin,
            'user_type' => 'admin'
        ]);
    }

    public function validarSesion(Request $request)
    {
        $user = Auth::guard('admin-sanctum')->user();
        
        if ($user && get_class($user) === 'App\Models\Admin') {
            return response()->json(['authenticated' => true]);
        }
        
        return response()->json(['authenticated' => false], 401);
    }
}
