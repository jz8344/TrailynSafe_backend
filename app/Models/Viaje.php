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
}
