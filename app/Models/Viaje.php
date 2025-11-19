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
        'ninos_confirmados'
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
     * Verifica si el viaje puede ser activado para hoy
     * Un viaje recurrente puede activarse si:
     * 1. Tiene dias_semana configurados (es recurrente)
     * 2. Hoy es uno de esos días
     * 3. No está activado ya para hoy (fecha_viaje != hoy)
     * 4. No ha llegado a su fecha_fin
     */
    public function puedeActivarHoy()
    {
        // Si no es recurrente, no se puede activar manualmente
        if (empty($this->dias_semana)) {
            return false;
        }

        // Verificar que hoy sea un día válido
        $numeroDiaHoy = now()->dayOfWeek; // 0=Domingo, 1=Lunes, ..., 6=Sábado
        if (!in_array($numeroDiaHoy, $this->dias_semana)) {
            return false;
        }

        // Verificar que no haya pasado la fecha_fin
        if ($this->fecha_fin && now()->toDateString() > $this->fecha_fin) {
            return false;
        }

        // Verificar que no esté activado ya para hoy
        if ($this->fecha_viaje && $this->fecha_viaje->toDateString() === now()->toDateString()) {
            return false;
        }

        return true;
    }

    /**
     * Calcula el estado actual del viaje basado en horarios y fecha
     */
    public function calcularEstadoActual()
    {
        // Si no tiene fecha_viaje, está en espera de ser activado
        if (!$this->fecha_viaje) {
            return 'pendiente';
        }

        $ahora = now();
        $fechaHoy = $ahora->toDateString();
        $horaActual = $ahora->format('H:i:s');

        // Si la fecha_viaje no es hoy, verificar si ya pasó o aún no llega
        if ($this->fecha_viaje->toDateString() !== $fechaHoy) {
            if ($this->fecha_viaje->isPast()) {
                return 'completado'; // Ya pasó
            }
            return 'pendiente'; // Aún no llega
        }

        // Si es hoy, determinar estado según horarios
        $horaInicioConf = $this->hora_inicio_confirmacion ? substr($this->hora_inicio_confirmacion, 11) : null;
        $horaFinConf = $this->hora_fin_confirmacion ? substr($this->hora_fin_confirmacion, 11) : null;
        $horaInicioViaje = $this->hora_inicio_viaje ? substr($this->hora_inicio_viaje, 11) : null;
        $horaLlegada = $this->hora_llegada_estimada ? substr($this->hora_llegada_estimada, 11) : null;

        // Viaje de retorno sin confirmación
        if ($this->tipo_viaje === 'retorno' || !$horaInicioConf) {
            if ($horaLlegada && $horaActual >= $horaLlegada) {
                return 'completado';
            }
            if ($horaInicioViaje && $horaActual >= $horaInicioViaje) {
                return 'en_curso';
            }
            return 'pendiente';
        }

        // Viaje de ida con confirmación
        if ($horaLlegada && $horaActual >= $horaLlegada) {
            return 'completado';
        }
        if ($horaInicioViaje && $horaActual >= $horaInicioViaje) {
            return 'en_curso';
        }
        if ($horaFinConf && $horaActual >= $horaFinConf) {
            return 'confirmaciones_cerradas';
        }
        if ($horaInicioConf && $horaActual >= $horaInicioConf) {
            return 'confirmaciones_abiertas';
        }

        return 'pendiente';
    }

    /**
     * Verifica si está en período de confirmación
     */
    public function estaEnPeriodoConfirmacion()
    {
        if ($this->tipo_viaje === 'retorno') {
            return false; // Los viajes de retorno no tienen período de confirmación
        }

        if (!$this->fecha_viaje) {
            return false; // Sin fecha no hay período
        }

        $ahora = now();
        $fechaHoy = $ahora->toDateString();

        // Verificar si hoy es la fecha del viaje
        if ($this->fecha_viaje->toDateString() !== $fechaHoy) {
            return false;
        }

        // Verificar horario
        $horaActual = $ahora->format('H:i:s');
        return $horaActual >= $this->hora_inicio_confirmacion 
            && $horaActual <= $this->hora_fin_confirmacion;
    }
}
