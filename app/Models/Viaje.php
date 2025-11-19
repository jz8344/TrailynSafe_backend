<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Viaje extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre_ruta',
        'escuela_id',
        'turno',
        'tipo_viaje',
        'viaje_retorno_id',
        'chofer_id',
        'unidad_id',
        'hora_inicio_confirmacion',
        'hora_fin_confirmacion',
        'hora_inicio_viaje',
        'hora_llegada_estimada',
        'fecha_viaje',
        'dias_semana',
        'fecha_fin',
        'coordenadas_recogida',
        'estado',
        'notas',
        'capacidad_maxima',
        'ninos_confirmados',
        'confirmacion_automatica'
    ];

    protected $casts = [
        'coordenadas_recogida' => 'array',
        'dias_semana' => 'array',
        'fecha_viaje' => 'date',
        'fecha_fin' => 'date',
        'hora_inicio_confirmacion' => 'datetime:H:i:s',
        'hora_fin_confirmacion' => 'datetime:H:i:s',
        'hora_inicio_viaje' => 'datetime:H:i:s',
        'hora_llegada_estimada' => 'datetime:H:i:s',
        'capacidad_maxima' => 'integer',
        'ninos_confirmados' => 'integer',
        'confirmacion_automatica' => 'boolean'
    ];

    /**
     * Relación con la escuela
     */
    public function escuela()
    {
        return $this->belongsTo(Escuela::class);
    }

    /**
     * Relación con el chofer
     */
    public function chofer()
    {
        return $this->belongsTo(Chofer::class);
    }

    /**
     * Relación con la unidad
     */
    public function unidad()
    {
        return $this->belongsTo(Unidad::class);
    }

    /**
     * Confirmaciones de viaje
     */
    public function confirmaciones()
    {
        return $this->hasMany(ConfirmacionViaje::class);
    }

    /**
     * Ubicaciones del bus durante el viaje
     */
    public function ubicaciones()
    {
        return $this->hasMany(UbicacionBus::class);
    }

    /**
     * Telemetría del chofer durante el viaje
     */
    public function telemetria()
    {
        return $this->hasMany(TelemetriaChofer::class);
    }

    /**
     * Scope para viajes activos
     */
    public function scopeActivos($query)
    {
        return $query->whereIn('estado', ['confirmaciones_abiertas', 'confirmaciones_cerradas', 'en_curso']);
    }

    /**
     * Scope para viajes de hoy
     */
    public function scopeHoy($query)
    {
        return $query->whereDate('fecha_viaje', today());
    }

    /**
     * Verifica si el periodo de confirmación está abierto
     */
    public function enPeriodoConfirmacion()
    {
        $ahora = now();
        $inicio = $ahora->copy()->setTimeFromTimeString($this->hora_inicio_confirmacion);
        $fin = $ahora->copy()->setTimeFromTimeString($this->hora_fin_confirmacion);

        return $ahora->between($inicio, $fin) && $this->fecha_viaje->isToday();
    }

    /**
     * Obtiene los hijos confirmados para el viaje
     */
    public function hijosConfirmados()
    {
        return $this->confirmaciones()
            ->where('estado', 'confirmado')
            ->with('hijo');
    }

    /**
     * Viaje de retorno asociado
     */
    public function viajeRetorno()
    {
        return $this->belongsTo(Viaje::class, 'viaje_retorno_id');
    }

    /**
     * Viaje de ida que generó este retorno
     */
    public function viajeIda()
    {
        return $this->hasOne(Viaje::class, 'viaje_retorno_id');
    }

    /**
     * Verifica si hoy es un día válido para este viaje
     */
    public function esHoyDiaValido()
    {
        if (empty($this->dias_semana)) {
            return true;
        }

        $diaSemana = strtolower(now()->locale('es')->dayName);
        return in_array($diaSemana, $this->dias_semana);
    }

    /**
     * Verifica si está en período de confirmación
     */
    public function estaEnPeriodoConfirmacion()
    {
        if ($this->tipo_viaje === 'retorno') {
            return false; // Los viajes de retorno no tienen período de confirmación
        }

        $ahora = now();
        $fechaHoy = $ahora->toDateString();

        // Verificar si hoy está dentro del rango de fechas
        if ($fechaHoy < $this->fecha_viaje->toDateString()) {
            return false; // Aún no es la fecha
        }

        if ($this->fecha_fin && $fechaHoy > $this->fecha_fin->toDateString()) {
            return false; // Ya pasó el rango
        }

        // Verificar día de la semana
        if (!$this->esHoyDiaValido()) {
            return false;
        }

        // Verificar horario
        $horaActual = $ahora->format('H:i:s');
        return $horaActual >= $this->hora_inicio_confirmacion 
            && $horaActual <= $this->hora_fin_confirmacion;
    }
}
