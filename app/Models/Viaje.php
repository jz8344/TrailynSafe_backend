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
        'nombre',
        'tipo_viaje',
        'turno',
        'fecha_viaje',
        'fecha_inicio_recurrencia',
        'fecha_fin_recurrencia',
        'dias_semana',
        'hora_salida_programada',
        'hora_inicio_confirmaciones',
        'hora_fin_confirmaciones',
        'estado',
        'escuela_id',
        'unidad_id',
        'chofer_id',
        'ruta_id',
        'cupo_minimo',
        'cupo_maximo',
        'confirmaciones_actuales',
        'notas',
        'admin_creador_id',
        'fecha_cambio_estado',
        'motivo_cancelacion',
        'latitud_inicio_chofer',
        'longitud_inicio_chofer',
    ];

    protected $casts = [
        'fecha_viaje' => 'date',
        'fecha_inicio_recurrencia' => 'date',
        'fecha_fin_recurrencia' => 'date',
        'dias_semana' => 'array',
        'hora_salida_programada' => 'datetime:H:i',
        'hora_inicio_confirmaciones' => 'datetime:H:i',
        'hora_fin_confirmaciones' => 'datetime:H:i',
        'fecha_cambio_estado' => 'datetime',
        'cupo_minimo' => 'integer',
        'cupo_maximo' => 'integer',
        'confirmaciones_actuales' => 'integer',
    ];

    // ==================== RELACIONES ====================

    public function escuela()
    {
        return $this->belongsTo(Escuela::class);
    }

    public function unidad()
    {
        return $this->belongsTo(Unidad::class);
    }

    public function chofer()
    {
        return $this->belongsTo(Chofer::class);
    }

    public function ruta()
    {
        return $this->belongsTo(Ruta::class);
    }

    public function adminCreador()
    {
        return $this->belongsTo(Admin::class, 'admin_creador_id');
    }

    public function confirmaciones()
    {
        return $this->hasMany(ConfirmacionViaje::class);
    }

    public function confirmacionesActivas()
    {
        return $this->hasMany(ConfirmacionViaje::class)->where('estado', 'confirmado');
    }

    public function asistencias()
    {
        return $this->hasMany(Asistencia::class);
    }

    // ==================== SCOPES ====================

    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    public function scopeProgramados($query)
    {
        return $query->where('estado', 'programado');
    }

    public function scopeEnConfirmaciones($query)
    {
        return $query->where('estado', 'en_confirmaciones');
    }

    public function scopeConfirmados($query)
    {
        return $query->where('estado', 'confirmado');
    }

    public function scopeEnCurso($query)
    {
        return $query->where('estado', 'en_curso');
    }

    public function scopeFinalizados($query)
    {
        return $query->where('estado', 'finalizado');
    }

    public function scopeActivos($query)
    {
        return $query->whereIn('estado', [
            'programado',
            'en_confirmaciones',
            'confirmado',
            'generando_ruta',
            'ruta_generada',
            'en_curso'
        ]);
    }

    public function scopeUnicos($query)
    {
        return $query->where('tipo_viaje', 'unico');
    }

    public function scopeRecurrentes($query)
    {
        return $query->where('tipo_viaje', 'recurrente');
    }

    public function scopeTurno($query, $turno)
    {
        return $query->where('turno', $turno);
    }

    public function scopeByEscuela($query, $escuelaId)
    {
        return $query->where('escuela_id', $escuelaId);
    }

    public function scopeProximosViajes($query)
    {
        $hoy = Carbon::today();
        return $query->where(function ($q) use ($hoy) {
            $q->where('tipo_viaje', 'unico')
              ->where('fecha_viaje', '>=', $hoy)
              ->whereIn('estado', ['programado', 'en_confirmaciones', 'confirmado', 'ruta_generada']);
        })->orWhere(function ($q) use ($hoy) {
            $q->where('tipo_viaje', 'recurrente')
              ->where('fecha_fin_recurrencia', '>=', $hoy)
              ->whereIn('estado', ['programado', 'en_confirmaciones', 'confirmado', 'ruta_generada']);
        });
    }

    // ==================== MÉTODOS DE ESTADO ====================

    public function cambiarEstado($nuevoEstado, $motivo = null)
    {
        $this->estado = $nuevoEstado;
        $this->fecha_cambio_estado = now();
        
        if ($motivo) {
            $this->motivo_cancelacion = $motivo;
        }
        
        $this->save();
    }

    public function programar()
    {
        if ($this->estado !== 'pendiente') {
            throw new \Exception('Solo se pueden programar viajes en estado pendiente');
        }
        
        $this->cambiarEstado('programado');
    }

    public function abrirConfirmaciones()
    {
        if ($this->estado !== 'programado') {
            throw new \Exception('Solo se pueden abrir confirmaciones para viajes programados');
        }
        
        $this->cambiarEstado('en_confirmaciones');
    }

    public function cerrarConfirmaciones()
    {
        if ($this->estado !== 'en_confirmaciones') {
            throw new \Exception('Solo se pueden cerrar confirmaciones para viajes en confirmaciones');
        }
        
        // Validar cupo mínimo
        if ($this->confirmaciones_actuales < $this->cupo_minimo) {
            $this->cambiarEstado('cancelado', 'Cupo mínimo no alcanzado');
            return false;
        }
        
        $this->cambiarEstado('confirmado');
        return true;
    }

    public function iniciarGeneracionRuta()
    {
        if ($this->estado !== 'confirmado') {
            throw new \Exception('Solo se puede generar ruta para viajes confirmados');
        }
        
        $this->cambiarEstado('generando_ruta');
    }

    public function marcarRutaGenerada($rutaId)
    {
        if ($this->estado !== 'generando_ruta') {
            throw new \Exception('Estado inválido para marcar ruta generada');
        }
        
        $this->ruta_id = $rutaId;
        $this->cambiarEstado('ruta_generada');
    }

    public function iniciar()
    {
        // Permitir re-iniciar si ya está en curso (idempotencia)
        if ($this->estado === 'en_curso') {
            return;
        }
        
        if ($this->estado !== 'ruta_generada') {
            throw new \Exception('Solo se pueden iniciar viajes con ruta generada');
        }
        
        $this->cambiarEstado('en_curso');
    }

    public function finalizar()
    {
        if ($this->estado !== 'en_curso') {
            throw new \Exception('Solo se pueden finalizar viajes en curso');
        }
        
        $this->cambiarEstado('finalizado');
    }

    public function cancelar($motivo = null)
    {
        if (!in_array($this->estado, ['pendiente', 'programado', 'en_confirmaciones'])) {
            throw new \Exception('No se puede cancelar un viaje en este estado');
        }
        
        $this->cambiarEstado('cancelado', $motivo);
    }

    // ==================== MÉTODOS DE UTILIDAD ====================

    public function puedeConfirmar()
    {
        return $this->estado === 'en_confirmaciones' 
            && $this->confirmaciones_actuales < $this->cupo_maximo;
    }

    public function agregarConfirmacion()
    {
        $this->increment('confirmaciones_actuales');
    }

    public function quitarConfirmacion()
    {
        $this->decrement('confirmaciones_actuales');
    }

    public function porcentajeConfirmaciones()
    {
        if ($this->cupo_maximo == 0) {
            return 0;
        }
        
        return round(($this->confirmaciones_actuales / $this->cupo_maximo) * 100, 2);
    }

    public function cupoDisponible()
    {
        return max(0, $this->cupo_maximo - $this->confirmaciones_actuales);
    }

    public function cumpleCupoMinimo()
    {
        return $this->confirmaciones_actuales >= $this->cupo_minimo;
    }

    public function estaLleno()
    {
        return $this->confirmaciones_actuales >= $this->cupo_maximo;
    }

    public function fechaLegible()
    {
        if ($this->tipo_viaje === 'unico') {
            return $this->fecha_viaje ? $this->fecha_viaje->format('d/m/Y') : '-';
        } else {
            $inicio = $this->fecha_inicio_recurrencia ? $this->fecha_inicio_recurrencia->format('d/m/Y') : '-';
            $fin = $this->fecha_fin_recurrencia ? $this->fecha_fin_recurrencia->format('d/m/Y') : '-';
            return "{$inicio} - {$fin}";
        }
    }

    public function diasSemanaLegible()
    {
        if (!$this->dias_semana || $this->tipo_viaje !== 'recurrente') {
            return '-';
        }
        
        $diasMap = [
            'lunes' => 'L',
            'martes' => 'M',
            'miercoles' => 'Mi',
            'jueves' => 'J',
            'viernes' => 'V',
            'sabado' => 'S',
            'domingo' => 'D'
        ];
        
        return implode(', ', array_map(function($dia) use ($diasMap) {
            return $diasMap[$dia] ?? $dia;
        }, $this->dias_semana));
    }

    public function estadoBadgeClass()
    {
        return match($this->estado) {
            'pendiente' => 'secondary',
            'programado' => 'primary',
            'en_confirmaciones' => 'warning',
            'confirmado' => 'info',
            'generando_ruta' => 'info',
            'ruta_generada' => 'success',
            'en_curso' => 'warning',
            'finalizado' => 'success',
            'cancelado' => 'danger',
            default => 'secondary'
        };
    }

    // ==================== LÓGICA TIPO ALARMA ====================
    
    /**
     * Constante: Tolerancia en minutos antes/después de la hora de salida
     * para permitir interacción con el viaje
     */
    const TOLERANCIA_MINUTOS = 20;

    /**
     * Determina si el viaje aplica para una fecha específica (tipo alarma)
     * 
     * Evalúa: ¿Hoy es un día válido para este viaje?
     * - Para viajes únicos: ¿Es exactamente la fecha del viaje?
     * - Para recurrentes: ¿Está dentro del rango Y es un día de la semana configurado?
     *
     * @param Carbon|null $fecha Fecha a evaluar (default: hoy)
     * @return bool
     */
    public function aplicaParaFecha($fecha = null): bool
    {
        $fecha = $fecha ? Carbon::parse($fecha) : Carbon::today();
        
        // Si está cancelado o finalizado, no aplica
        if (in_array($this->estado, ['cancelado', 'finalizado'])) {
            return false;
        }
        
        if ($this->tipo_viaje === 'unico') {
            // Para viaje único: debe ser exactamente esa fecha
            return $this->fecha_viaje && $this->fecha_viaje->isSameDay($fecha);
        }
        
        // Para viaje recurrente:
        // 1. Verificar que esté dentro del rango de fechas
        if (!$this->fecha_inicio_recurrencia || !$this->fecha_fin_recurrencia) {
            return false;
        }
        
        if ($fecha->lt($this->fecha_inicio_recurrencia) || $fecha->gt($this->fecha_fin_recurrencia)) {
            return false;
        }
        
        // 2. Verificar que sea un día de la semana configurado
        // dias_semana es array de números: 0=Domingo, 1=Lunes, ..., 6=Sábado
        $diaHoy = $fecha->dayOfWeek; // Carbon: 0=Domingo, 6=Sábado
        $diasConfigurados = $this->dias_semana ?? [];
        
        // Convertir a enteros por si vienen como strings
        $diasConfigurados = array_map('intval', $diasConfigurados);
        
        return in_array($diaHoy, $diasConfigurados);
    }

    /**
     * Obtiene el estado efectivo del viaje para HOY basado en la hora actual
     * (Lógica tipo alarma: evalúa en tiempo real)
     *
     * Estados posibles:
     * - 'no_aplica': El viaje no es para hoy
     * - 'programado': Aún no llega la hora de confirmaciones
     * - 'en_confirmaciones': Padres pueden confirmar asistencia
     * - 'confirmado': Ventana cerrada, esperando generar ruta
     * - 'interactuable': Dentro de ventana ±20min de hora salida (puede generar ruta/iniciar)
     * - 'en_curso': Viaje en progreso
     * - 'expirado': Ya pasó el tiempo de tolerancia
     * - 'finalizado': Viaje completado
     * - 'cancelado': Viaje cancelado
     *
     * @param Carbon|null $ahora Momento a evaluar (default: ahora)
     * @return array ['estado' => string, 'mensaje' => string, 'datos' => array]
     */
    public function getEstadoEfectivoHoy($ahora = null): array
    {
        $ahora = $ahora ? Carbon::parse($ahora) : Carbon::now();
        $hoy = $ahora->copy()->startOfDay();
        
        // Si ya está en estado terminal, retornar ese estado
        if ($this->estado === 'cancelado') {
            return [
                'estado' => 'cancelado',
                'mensaje' => 'Viaje cancelado',
                'interactuable' => false,
                'datos' => ['motivo' => $this->motivo_cancelacion]
            ];
        }
        
        if ($this->estado === 'finalizado') {
            return [
                'estado' => 'finalizado',
                'mensaje' => 'Viaje completado',
                'interactuable' => false,
                'datos' => []
            ];
        }
        
        // Si el viaje está en_curso, permitir interacción
        if ($this->estado === 'en_curso') {
            return [
                'estado' => 'en_curso',
                'mensaje' => 'Viaje en progreso',
                'interactuable' => true,
                'datos' => ['ruta_id' => $this->ruta_id]
            ];
        }
        
        // Verificar si aplica para hoy
        if (!$this->aplicaParaFecha($hoy)) {
            return [
                'estado' => 'no_aplica',
                'mensaje' => 'Este viaje no aplica para hoy',
                'interactuable' => false,
                'datos' => ['proxima_fecha' => $this->getProximaFechaAplica()]
            ];
        }
        
        // Obtener horas clave del viaje
        $horaSalida = $this->getHoraSalidaCarbon($hoy);
        $horaInicioConf = $this->getHoraInicioConfirmacionesCarbon($hoy);
        $horaFinConf = $this->getHoraFinConfirmacionesCarbon($hoy);
        
        // Calcular ventana de tolerancia (±20 min de hora salida)
        $inicioTolerancia = $horaSalida->copy()->subMinutes(self::TOLERANCIA_MINUTOS);
        $finTolerancia = $horaSalida->copy()->addMinutes(self::TOLERANCIA_MINUTOS);
        
        // Calcular tiempo restante
        $minutosParaSalida = $ahora->diffInMinutes($horaSalida, false);
        
        // EVALUAR ESTADO SEGÚN HORA ACTUAL
        
        // 1. Ya pasó el tiempo de tolerancia → Expirado
        if ($ahora->gt($finTolerancia) && $this->estado !== 'en_curso') {
            return [
                'estado' => 'expirado',
                'mensaje' => 'El tiempo para este viaje ha expirado',
                'interactuable' => false,
                'datos' => [
                    'hora_salida' => $horaSalida->format('H:i'),
                    'expiro_hace' => abs($minutosParaSalida) . ' minutos'
                ]
            ];
        }
        
        // 2. Dentro de ventana de tolerancia → Interactuable (puede generar ruta/iniciar)
        if ($ahora->between($inicioTolerancia, $finTolerancia)) {
            $confirmacionesHoy = $this->getConfirmacionesParaFecha($hoy);
            $puedeGenerarRuta = $confirmacionesHoy >= $this->cupo_minimo;
            
            return [
                'estado' => 'interactuable',
                'mensaje' => $puedeGenerarRuta 
                    ? '¡Listo para generar ruta e iniciar viaje!' 
                    : "Faltan confirmaciones (mínimo: {$this->cupo_minimo})",
                'interactuable' => true,
                'puede_generar_ruta' => $puedeGenerarRuta,
                'puede_iniciar' => $puedeGenerarRuta && $this->ruta_id !== null,
                'datos' => [
                    'hora_salida' => $horaSalida->format('H:i'),
                    'minutos_para_salida' => $minutosParaSalida,
                    'confirmaciones_hoy' => $confirmacionesHoy,
                    'cupo_minimo' => $this->cupo_minimo,
                    'cupo_maximo' => $this->cupo_maximo,
                    'ventana_cierra_en' => $finTolerancia->diffInMinutes($ahora) . ' min'
                ]
            ];
        }
        
        // 3. Después de cierre de confirmaciones pero antes de tolerancia → Confirmado/Esperando
        if ($ahora->gt($horaFinConf) && $ahora->lt($inicioTolerancia)) {
            $confirmacionesHoy = $this->getConfirmacionesParaFecha($hoy);
            
            return [
                'estado' => 'confirmado',
                'mensaje' => 'Confirmaciones cerradas. Esperando hora de salida.',
                'interactuable' => false,
                'datos' => [
                    'hora_salida' => $horaSalida->format('H:i'),
                    'minutos_para_interactuar' => $inicioTolerancia->diffInMinutes($ahora),
                    'confirmaciones_hoy' => $confirmacionesHoy,
                    'cumple_minimo' => $confirmacionesHoy >= $this->cupo_minimo
                ]
            ];
        }
        
        // 4. Dentro de ventana de confirmaciones → En confirmaciones
        if ($ahora->between($horaInicioConf, $horaFinConf)) {
            $confirmacionesHoy = $this->getConfirmacionesParaFecha($hoy);
            
            return [
                'estado' => 'en_confirmaciones',
                'mensaje' => 'Los padres pueden confirmar asistencia',
                'interactuable' => true, // Padres pueden confirmar, chofer puede ver
                'datos' => [
                    'hora_salida' => $horaSalida->format('H:i'),
                    'ventana_cierra' => $horaFinConf->format('H:i'),
                    'minutos_restantes' => $horaFinConf->diffInMinutes($ahora),
                    'confirmaciones_hoy' => $confirmacionesHoy,
                    'cupo_minimo' => $this->cupo_minimo,
                    'cupo_maximo' => $this->cupo_maximo,
                    'cupo_disponible' => max(0, $this->cupo_maximo - $confirmacionesHoy)
                ]
            ];
        }
        
        // 5. Antes de la ventana de confirmaciones → Programado
        return [
            'estado' => 'programado',
            'mensaje' => 'Viaje programado. Confirmaciones abren pronto.',
            'interactuable' => false,
            'datos' => [
                'hora_salida' => $horaSalida->format('H:i'),
                'confirmaciones_abren' => $horaInicioConf->format('H:i'),
                'minutos_para_abrir' => $horaInicioConf->diffInMinutes($ahora)
            ]
        ];
    }

    /**
     * Obtiene la hora de salida como Carbon para una fecha específica
     */
    public function getHoraSalidaCarbon($fecha): Carbon
    {
        $fecha = Carbon::parse($fecha)->format('Y-m-d');
        $hora = $this->hora_salida_programada;
        
        if ($hora instanceof Carbon) {
            $hora = $hora->format('H:i:s');
        }
        
        return Carbon::parse("{$fecha} {$hora}");
    }

    /**
     * Obtiene la hora de inicio de confirmaciones como Carbon
     * Considera que puede ser del día anterior (ej: 18:00 de ayer para viaje de hoy)
     */
    public function getHoraInicioConfirmacionesCarbon($fechaViaje): Carbon
    {
        $fechaViaje = Carbon::parse($fechaViaje);
        $horaInicio = $this->hora_inicio_confirmaciones;
        $horaFin = $this->hora_fin_confirmaciones;
        
        if ($horaInicio instanceof Carbon) {
            $horaInicio = $horaInicio->format('H:i:s');
        }
        if ($horaFin instanceof Carbon) {
            $horaFin = $horaFin->format('H:i:s');
        }
        
        // Si hora inicio > hora fin, significa que cruza medianoche
        // Ej: inicio 18:00, fin 05:30 → inicio es del día anterior
        if ($horaInicio > $horaFin) {
            return Carbon::parse($fechaViaje->copy()->subDay()->format('Y-m-d') . ' ' . $horaInicio);
        }
        
        return Carbon::parse($fechaViaje->format('Y-m-d') . ' ' . $horaInicio);
    }

    /**
     * Obtiene la hora de fin de confirmaciones como Carbon
     */
    public function getHoraFinConfirmacionesCarbon($fechaViaje): Carbon
    {
        $fechaViaje = Carbon::parse($fechaViaje);
        $horaFin = $this->hora_fin_confirmaciones;
        
        if ($horaFin instanceof Carbon) {
            $horaFin = $horaFin->format('H:i:s');
        }
        
        return Carbon::parse($fechaViaje->format('Y-m-d') . ' ' . $horaFin);
    }

    /**
     * Cuenta las confirmaciones para una fecha específica
     */
    public function getConfirmacionesParaFecha($fecha): int
    {
        $fecha = Carbon::parse($fecha)->format('Y-m-d');
        
        return $this->confirmaciones()
            ->where('estado', 'confirmado')
            ->where(function($query) use ($fecha) {
                // Buscar por fecha_viaje si existe, sino por fecha de hoy para retrocompatibilidad
                $query->where('fecha_viaje', $fecha)
                      ->orWhereNull('fecha_viaje');
            })
            ->count();
    }

    /**
     * Obtiene la próxima fecha en que aplica este viaje
     */
    public function getProximaFechaAplica(): ?string
    {
        $fecha = Carbon::today();
        
        // Buscar en los próximos 30 días
        for ($i = 0; $i < 30; $i++) {
            $fecha = $fecha->addDay();
            if ($this->aplicaParaFecha($fecha)) {
                return $fecha->format('Y-m-d');
            }
        }
        
        return null;
    }

    /**
     * Verifica si el viaje está en ventana interactuable ahora
     */
    public function estaEnVentanaInteractuable(): bool
    {
        $estado = $this->getEstadoEfectivoHoy();
        return $estado['estado'] === 'interactuable' || $estado['estado'] === 'en_curso';
    }

    /**
     * Verifica si se pueden registrar confirmaciones ahora
     */
    public function puedeRecibirConfirmaciones(): bool
    {
        $estado = $this->getEstadoEfectivoHoy();
        return $estado['estado'] === 'en_confirmaciones';
    }

    /**
     * Obtiene información completa del viaje para API móvil
     */
    public function getInfoParaMovil(): array
    {
        $estadoEfectivo = $this->getEstadoEfectivoHoy();
        
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'tipo_viaje' => $this->tipo_viaje,
            'turno' => $this->turno,
            'escuela' => $this->escuela ? [
                'id' => $this->escuela->id,
                'nombre' => $this->escuela->nombre,
                'direccion' => $this->escuela->direccion,
                'latitud' => $this->escuela->latitud,
                'longitud' => $this->escuela->longitud,
            ] : null,
            'unidad' => $this->unidad ? [
                'id' => $this->unidad->id,
                'matricula' => $this->unidad->matricula,
                'capacidad' => $this->unidad->capacidad,
            ] : null,
            'hora_salida' => $this->hora_salida_programada?->format('H:i'),
            'cupo_minimo' => $this->cupo_minimo,
            'cupo_maximo' => $this->cupo_maximo,
            
            // Estado efectivo (lógica tipo alarma)
            'estado_efectivo' => $estadoEfectivo['estado'],
            'estado_mensaje' => $estadoEfectivo['mensaje'],
            'interactuable' => $estadoEfectivo['interactuable'],
            'estado_datos' => $estadoEfectivo['datos'],
            
            // Ruta si existe
            'ruta' => $this->ruta ? [
                'id' => $this->ruta->id,
                'estado' => $this->ruta->estado,
                'distancia_km' => $this->ruta->distancia_total_km,
                'tiempo_min' => $this->ruta->tiempo_estimado_minutos,
                'polyline' => $this->ruta->polyline,
                'paradas_count' => $this->ruta->paradas()->count(),
            ] : null,
            
            // Metadatos
            'aplica_hoy' => $this->aplicaParaFecha(),
            'fecha_evaluacion' => Carbon::now()->toIso8601String(),
        ];
    }
}
