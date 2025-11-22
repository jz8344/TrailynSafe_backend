<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unidad extends Model
{
    use HasFactory;

    protected $table = 'unidades';

    protected $fillable = [
        'matricula',
        'descripcion',
        'marca',
        'modelo',
        'anio',
        'color',
        'imagen',
        'imagen_delete_hash',
        'estado',
        'numero_serie',
        'capacidad',
    ];

    protected $casts = [
        'anio' => 'integer',
        'capacidad' => 'integer',
    ];
}

