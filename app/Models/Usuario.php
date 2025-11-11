<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class Usuario extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'usuarios';

    protected $fillable = [
        'nombre',
        'apellidos',
        'telefono',
        'correo',
        'contrasena',
        'rol', 
        'have_son',
        'fecha_registro',
    ];

    public function getAuthPassword()
    {
        return $this->contrasena;
    }
}
