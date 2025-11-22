<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Viaje extends Model
{
    use HasFactory;

    protected $table = 'viajes';

    protected $fillable = [
        'unidad_id',
        'chofer_id',
        'escuela_id',
        'nombre_ruta',
        'tipo_viaje',
        'fecha_especifica',
        'dias_activos',
        'fecha_inicio_vigencia',
        'fecha_fin_vigencia',
        'horario_salida',
        'capacidad_maxima',
        'capacidad_actual',
        'turno',
        'estado',
        'descripcion',
        'notas'
    ];

    protected $casts = [
        'dias_activos' => 'array',
        'fecha_especifica' => 'date',
        'fecha_inicio_vigencia' => 'date',
        'fecha_fin_vigencia' => 'date',
        'horario_salida' => 'datetime:H:i',
        'capacidad_maxima' => 'integer',
        'capacidad_actual' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relaciones
    public function unidad()
    {
        return $this->belongsTo(Unidad::class);
    }

    public function chofer()
    {
        return $this->belongsTo(Chofer::class);
    }

    public function escuela()
    {
        return $this->belongsTo(Escuela::class);
    }

    public function solicitudes()
    {
        return $this->hasMany(ViajeSolicitud::class);
    }

    public function solicitudesAceptadas()
    {
        return $this->hasMany(ViajeSolicitud::class)->where('estado_confirmacion', 'aceptado');
    }

    // Accessors
    public function getHorarioSalidaAttribute($value)
    {
        if (!$value) return null;
        return Carbon::parse($value)->format('H:i');
    }

    public function getDisponibilidadAttribute()
    {
        return $this->capacidad_maxima - $this->capacidad_actual;
    }

    public function getEstadoLabelAttribute()
    {
        $labels = [
            'abierto' => 'Abierto',
            'cerrado' => 'Cerrado',
            'en_curso' => 'En Curso',
            'completado' => 'Completado',
            'cancelado' => 'Cancelado'
        ];
        return $labels[$this->estado] ?? $this->estado;
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('estado', 'abierto');
    }

    public function scopeDisponibles($query)
    {
        return $query->where('estado', 'abierto')
                     ->whereRaw('capacidad_actual < capacidad_maxima');
    }

    public function scopePorEscuela($query, $escuelaId)
    {
        return $query->where('escuela_id', $escuelaId);
    }

    public function scopeRecurrentes($query)
    {
        return $query->where('tipo_viaje', 'recurrente');
    }

    public function scopeUnicos($query)
    {
        return $query->where('tipo_viaje', 'unico');
    }

    // Métodos de utilidad
    public function puedeRecibirSolicitudes()
    {
        return $this->estado === 'abierto' && $this->capacidad_actual < $this->capacidad_maxima;
    }

    public function esVigenteParaFecha($fecha)
    {
        $fecha = Carbon::parse($fecha);

        if ($this->tipo_viaje === 'unico') {
            return $this->fecha_especifica && $fecha->isSameDay($this->fecha_especifica);
        }

        if ($this->tipo_viaje === 'recurrente') {
            // Verificar si está dentro del rango de vigencia
            if ($this->fecha_inicio_vigencia && $fecha->lt($this->fecha_inicio_vigencia)) {
                return false;
            }
            if ($this->fecha_fin_vigencia && $fecha->gt($this->fecha_fin_vigencia)) {
                return false;
            }

            // Verificar si el día de la semana está activo
            $diaSemana = $this->getDiaSemanaEspanol($fecha->dayOfWeek);
            return in_array($diaSemana, $this->dias_activos ?? []);
        }

        return false;
    }

    private function getDiaSemanaEspanol($dayOfWeek)
    {
        $dias = [
            0 => 'Domingo',
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado'
        ];
        return $dias[$dayOfWeek] ?? '';
    }

    public function incrementarCapacidad()
    {
        $this->increment('capacidad_actual');
    }

    public function decrementarCapacidad()
    {
        if ($this->capacidad_actual > 0) {
            $this->decrement('capacidad_actual');
        }
    }
}
