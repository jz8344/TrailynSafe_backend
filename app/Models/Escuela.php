<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Escuela extends Model
{
    use HasFactory;

    protected $table = 'escuelas';

    protected $fillable = [
        'nombre',
        'clave',
        'nivel',
        'turno',
        'direccion',
        'colonia',
        'ciudad',
        'estado_republica',
        'municipio',
        'codigo_postal',
        'telefono',
        'correo',
        'contacto',
        'cargo_contacto',
        'horario_entrada',
        'horario_salida',
        'fecha_inicio_servicio',
        'numero_alumnos',
        'notas',
        'estado'
    ];

    protected $casts = [
        'horario_entrada' => 'datetime:H:i',
        'horario_salida' => 'datetime:H:i',
        'fecha_inicio_servicio' => 'date',
        'numero_alumnos' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Accessor para horario_entrada en formato H:i
    public function getHorarioEntradaAttribute($value)
    {
        if (!$value) return null;
        return \Carbon\Carbon::parse($value)->format('H:i');
    }

    // Accessor para horario_salida en formato H:i
    public function getHorarioSalidaAttribute($value)
    {
        if (!$value) return null;
        return \Carbon\Carbon::parse($value)->format('H:i');
    }

    // Scopes para filtrado
    public function scopeActivas($query)
    {
        return $query->where('estado', 'activo');
    }

    public function scopeByNivel($query, $nivel)
    {
        return $query->where('nivel', $nivel);
    }

    public function scopeByTurno($query, $turno)
    {
        return $query->where('turno', $turno);
    }

    // Relaciones (pueden agregarse más adelante)
    // public function rutas()
    // {
    //     return $this->hasMany(Ruta::class);
    // }

    // public function hijos()
    // {
    //     return $this->hasMany(Hijo::class);
    // }
}
