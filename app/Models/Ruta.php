<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;



class Ruta extends Model
{
    use HasFactory;

    protected $table = 'rutas';

    protected $fillable = [
        'nombre',
        'chofer_id',
        'unidad_id',
        'horario',
        'inicio',
        'fin',
        'rango',
        'estado',
    ];

    public const ESTADOS = ['activa','inactiva','completada'];

    public function chofer()
    {
        return $this->belongsTo(Chofer::class, 'chofer_id');
    }

    public function unidad()
    {
        return $this->belongsTo(Unidad::class, 'unidad_id');
    }
}
