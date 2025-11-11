<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Contracts\MongoSyncable;
use App\Support\MongoSync\MongoSyncTrait;

class Hijo extends Model implements MongoSyncable
{
    use HasFactory, MongoSyncTrait;

    protected $table = 'hijos';

    protected $fillable = [
        'nombre',
        'grado',
        'grupo',
        'escuela',
        'codigo_qr',
        'padre_id',
    'emergencia_1',
    'emergencia_2',
    ];

    public function padre()
    {
        return $this->belongsTo(Usuario::class, 'padre_id');
    }
}
