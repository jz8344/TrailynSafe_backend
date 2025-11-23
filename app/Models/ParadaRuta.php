<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParadaRuta extends Model
{
    use HasFactory;

    protected $table = 'paradas_ruta';

    protected $fillable = [
        'ruta_id',
        'confirmacion_id',
        'orden',
        'direccion',
        'latitud',
        'longitud',
        'hora_estimada',
        'distancia_desde_anterior_km',
        'tiempo_desde_anterior_min',
        'cluster_asignado',
        'estado',
    ];

    protected $casts = [
        'orden' => 'integer',
        'latitud' => 'decimal:8',
        'longitud' => 'decimal:8',
        'hora_estimada' => 'datetime:H:i',
        'distancia_desde_anterior_km' => 'decimal:2',
        'tiempo_desde_anterior_min' => 'integer',
        'cluster_asignado' => 'integer',
    ];

    // ==================== RELACIONES ====================

    public function ruta()
    {
        return $this->belongsTo(Ruta::class);
    }

    public function confirmacion()
    {
        return $this->belongsTo(ConfirmacionViaje::class, 'confirmacion_id');
    }

    public function hijo()
    {
        return $this->hasOneThrough(
            Hijo::class,
            ConfirmacionViaje::class,
            'id',              // Foreign key en confirmaciones_viaje
            'id',              // Foreign key en hijos
            'confirmacion_id', // Local key en paradas_ruta
            'hijo_id'          // Local key en confirmaciones_viaje
        );
    }

    public function asistencia()
    {
        return $this->hasOne(Asistencia::class, 'parada_ruta_id');
    }

    // ==================== SCOPES ====================

    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    public function scopeEnCamino($query)
    {
        return $query->where('estado', 'en_camino');
    }

    public function scopeCompletadas($query)
    {
        return $query->where('estado', 'completada');
    }

    public function scopeByRuta($query, $rutaId)
    {
        return $query->where('ruta_id', $rutaId);
    }

    public function scopeOrdenadas($query)
    {
        return $query->orderBy('orden');
    }

    // ==================== MÉTODOS DE ESTADO ====================

    public function marcarEnCamino()
    {
        if ($this->estado !== 'pendiente') {
            throw new \Exception('Solo se pueden marcar en camino las paradas pendientes');
        }
        
        $this->estado = 'en_camino';
        $this->save();
    }

    public function completar()
    {
        if ($this->estado !== 'en_camino') {
            throw new \Exception('Solo se pueden completar paradas en camino');
        }
        
        $this->estado = 'completada';
        $this->save();
    }

    public function omitir()
    {
        $this->estado = 'omitida';
        $this->save();
    }

    // ==================== MÉTODOS DE UTILIDAD ====================

    public function coordenadas()
    {
        return [
            'lat' => (float) $this->latitud,
            'lng' => (float) $this->longitud
        ];
    }

    public function distanciaLegible()
    {
        return number_format($this->distancia_desde_anterior_km, 2) . ' km';
    }

    public function tiempoLegible()
    {
        return $this->tiempo_desde_anterior_min . ' min';
    }

    public function tieneAsistencia()
    {
        return $this->asistencia()->exists();
    }

    public function nombreHijo()
    {
        return $this->confirmacion?->hijo?->nombre ?? 'Sin información';
    }

    public function siguienteParada()
    {
        return ParadaRuta::where('ruta_id', $this->ruta_id)
            ->where('orden', '>', $this->orden)
            ->orderBy('orden')
            ->first();
    }

    public function paradaAnterior()
    {
        return ParadaRuta::where('ruta_id', $this->ruta_id)
            ->where('orden', '<', $this->orden)
            ->orderBy('orden', 'desc')
            ->first();
    }
}
