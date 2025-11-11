<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Contracts\MongoSyncable;
use App\Support\MongoSync\MongoSyncTrait;

class Unidad extends Model implements MongoSyncable
{
    use HasFactory, MongoSyncTrait;

    protected $table = 'unidades';

    protected $fillable = [
        'matricula',
        'descripcion',
        'marca',
        'modelo',
        'anio',
        'color',
        'imagen',
        'estado',
        'numero_serie',
        'capacidad',
    ];

    protected $casts = [
        'anio' => 'integer',
        'capacidad' => 'integer',
    ];

    public function rutas()
    {
        return $this->hasMany(Ruta::class, 'unidad_id');
    }
}
