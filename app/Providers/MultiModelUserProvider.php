<?php

namespace App\Providers;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Support\Facades\Hash;
use App\Models\Usuario;
use App\Models\Admin;

class MultiModelUserProvider extends EloquentUserProvider
{
    public function retrieveByCredentials(array $credentials)
    {
        if (empty($credentials) || (count($credentials) === 1 && array_key_exists('password', $credentials))) {
            return null;
        }

        // Buscar primero en admins
        if (isset($credentials['email'])) {
            $admin = Admin::where('email', $credentials['email'])->first();
            if ($admin) {
                return $admin;
            }
        }

        // Si no encuentra admin, buscar en usuarios
        if (isset($credentials['correo'])) {
            $usuario = Usuario::where('correo', $credentials['correo'])->first();
            if ($usuario) {
                return $usuario;
            }
        }

        return null;
    }

    public function validateCredentials($user, array $credentials)
    {
        $plain = $credentials['password'];

        if ($user instanceof Admin) {
            return Hash::check($plain, $user->password);
        } elseif ($user instanceof Usuario) {
            return Hash::check($plain, $user->contrasena);
        }

        return false;
    }

    public function retrieveById($identifier)
    {
        // Intentar buscar en ambas tablas por ID
        $admin = Admin::find($identifier);
        if ($admin) {
            return $admin;
        }

        $usuario = Usuario::find($identifier);
        if ($usuario) {
            return $usuario;
        }

        return null;
    }

    public function retrieveByToken($identifier, $token)
    {
        // Para Sanctum, usamos el método padre pero buscamos en ambos modelos
        $admin = Admin::find($identifier);
        if ($admin) {
            return $admin;
        }

        $usuario = Usuario::find($identifier);
        if ($usuario) {
            return $usuario;
        }

        return null;
    }
}
