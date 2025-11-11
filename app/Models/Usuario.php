<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use App\Contracts\MongoSyncable;
use App\Support\MongoSync\MongoSyncTrait;

class Usuario extends Authenticatable implements MongoSyncable
{
    use HasApiTokens, Notifiable, MongoSyncTrait;

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
