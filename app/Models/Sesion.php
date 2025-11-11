<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Contracts\MongoSyncable;
use App\Support\MongoSync\MongoSyncTrait;

class Sesion extends Model implements MongoSyncable
{
    use MongoSyncTrait;
    protected $table = 'sesiones';

    protected $fillable = [
        'usuario_id',
        'token',
        'token_id',
        'user_agent',
        'ip_address',
        'inicio',
        'estado',
    ];
}