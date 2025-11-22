<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;



class Hijo extends Model
{
    use HasFactory;

    protected $table = 'hijos';

    protected $fillable = [
        'nombre',
        'grado',
        'grupo',
        'escuela',
        'escuela_id',
        'turno',
        'codigo_qr',
        'padre_id',
        'emergencia_1',
        'emergencia_2',
    ];

    public function padre()
    {
        return $this->belongsTo(Usuario::class, 'padre_id');
    }

    public function datosEscuela()
    {
        return $this->belongsTo(Escuela::class, 'escuela_id');
    }
}
