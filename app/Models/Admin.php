<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use App\Contracts\MongoSyncable;
use App\Support\MongoSync\MongoSyncTrait;

class Admin extends Authenticatable implements MongoSyncable
{
    use HasApiTokens, Notifiable, MongoSyncTrait;

    protected $table = 'admins';

    protected $fillable = [
        'nombre',
        'apellidos',
        'email',
        'password',
        'rol',
        'fecha_registro',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function getAuthPassword()
    {
        return $this->password;
    }
}
