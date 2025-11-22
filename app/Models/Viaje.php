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
        'es_plantilla',
        'parent_viaje_id'
    ];

    protected $casts = [
        'coordenadas_recogida' => 'array',
        'dias_semana' => 'array',
        'fecha_viaje' => 'date',
        'fecha_fin' => 'date',
        'hora_inicio_confirmacion' => 'string', // Mantener como string H:i:s para comparaciones directas
        'hora_fin_confirmacion' => 'string',
        'hora_inicio_viaje' => 'string',
        'hora_llegada_estimada' => 'string',
        'capacidad_maxima' => 'integer',
        'ninos_confirmados' => 'integer',
        'confirmacion_automatica' => 'boolean',
        'es_plantilla' => 'boolean'
    ];

    /**
     * Scope para obtener solo plantillas (configuraciones recurrentes)
     */
    public function scopePlantillas($query)
    {
        return $query->where('es_plantilla', true);
    }

    /**
     * Scope para obtener viajes individuales (instancias o viajes únicos)
     */
    public function scopeInstancias($query)
    {
        return $query->where('es_plantilla', false);
    }

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
     * Relación con el viaje padre (plantilla)
     */
    public function parentViaje()
    {
        return $this->belongsTo(Viaje::class, 'parent_viaje_id');
    }

    /**
     * Relación con las instancias generadas (hijos)
     */
    public function instancias()
    {
        return $this->hasMany(Viaje::class, 'parent_viaje_id');
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
     * Verifica si el periodo de confirmación está abierto para HOY
     */
    public function enPeriodoConfirmacion()
    {
        // Solo las instancias tienen fecha de viaje válida para confirmar
        if ($this->es_plantilla || !$this->fecha_viaje) {
            return false;
        }

        // Verificar que sea el día del viaje
        if (!$this->fecha_viaje->isToday()) {
            return false;
        }

        $ahora = now();
        $horaActual = $ahora->format('H:i:s');

        return $horaActual >= $this->hora_inicio_confirmacion && 
               $horaActual <= $this->hora_fin_confirmacion;
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
     * Verifica si hoy es un día válido para este viaje (para plantillas)
     */
    public function esHoyDiaValido()
    {
        if (empty($this->dias_semana)) {
            return false;
        }

        // 0=Domingo, 1=Lunes, ..., 6=Sábado
        $diaSemana = now()->dayOfWeek;
        return in_array($diaSemana, $this->dias_semana);
    }

    /**
     * Calcula el estado actual del viaje basado en horarios y fecha
     * Solo aplica para instancias (viajes reales del día)
     */
    public function calcularEstadoActual()
    {
        // Si es plantilla, su estado es irrelevante para el flujo operativo diario
        if ($this->es_plantilla) {
            return 'plantilla';
        }

        // Si el viaje ya fue marcado como completado o cancelado manualmente, respetar ese estado
        if (in_array($this->estado, ['completado', 'cancelado'])) {
            return $this->estado;
        }

        $ahora = now();
        
        // Si la fecha del viaje no es hoy
        if (!$this->fecha_viaje->isToday()) {
            if ($this->fecha_viaje->isPast()) {
                return 'completado'; // O vencido
            }
            return 'pendiente'; // Futuro
        }

        $horaActual = $ahora->format('H:i:s');

        // Lógica de estados basada en horario
        if ($horaActual < $this->hora_inicio_confirmacion) {
            return 'pendiente';
        }
        
        if ($horaActual >= $this->hora_inicio_confirmacion && $horaActual <= $this->hora_fin_confirmacion) {
            return 'confirmaciones_abiertas';
        }

        if ($horaActual > $this->hora_fin_confirmacion && $horaActual < $this->hora_inicio_viaje) {
            return 'confirmaciones_cerradas';
        }

        if ($horaActual >= $this->hora_inicio_viaje) {
            // Si ya pasó la hora de inicio, asumimos en curso hasta que el chofer lo finalice
            // O hasta cierta hora límite
            return 'en_curso';
        }

        return 'pendiente';
    }

    /**
     * Alias para enPeriodoConfirmacion para compatibilidad
     */
    public function estaEnPeriodoConfirmacion()
    {
        return $this->enPeriodoConfirmacion();
    }
}
