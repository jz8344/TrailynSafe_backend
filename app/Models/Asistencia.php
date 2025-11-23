<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asistencia extends Model
{
    use HasFactory;

    protected $table = 'asistencias';

    protected $fillable = [
        'parada_ruta_id',
        'hijo_id',
        'viaje_id',
        'codigo_qr_escaneado',
        'hora_escaneo',
        'estado',
        'latitud_escaneo',
        'longitud_escaneo',
        'observaciones',
    ];

    protected $casts = [
        'hora_escaneo' => 'datetime',
        'latitud_escaneo' => 'decimal:8',
        'longitud_escaneo' => 'decimal:8',
    ];

    // ==================== RELACIONES ====================

    public function paradaRuta()
    {
        return $this->belongsTo(ParadaRuta::class, 'parada_ruta_id');
    }

    public function hijo()
    {
        return $this->belongsTo(Hijo::class);
    }

    public function viaje()
    {
        return $this->belongsTo(Viaje::class);
    }

    public function ruta()
    {
        return $this->hasOneThrough(
            Ruta::class,
            ParadaRuta::class,
            'id',              // Foreign key en paradas_ruta
            'id',              // Foreign key en rutas
            'parada_ruta_id',  // Local key en asistencias
            'ruta_id'          // Local key en paradas_ruta
        );
    }

    // ==================== SCOPES ====================

    public function scopePresentes($query)
    {
        return $query->where('estado', 'presente');
    }

    public function scopeAusentes($query)
    {
        return $query->where('estado', 'ausente');
    }

    public function scopeJustificados($query)
    {
        return $query->where('estado', 'justificado');
    }

    public function scopeByViaje($query, $viajeId)
    {
        return $query->where('viaje_id', $viajeId);
    }

    public function scopeByHijo($query, $hijoId)
    {
        return $query->where('hijo_id', $hijoId);
    }

    public function scopeHoy($query)
    {
        return $query->whereDate('hora_escaneo', today());
    }

    // ==================== MÉTODOS ====================

    public function registrar($codigoQR, $latitud = null, $longitud = null, $observaciones = null)
    {
        $this->codigo_qr_escaneado = $codigoQR;
        $this->hora_escaneo = now();
        $this->estado = 'presente';
        $this->latitud_escaneo = $latitud;
        $this->longitud_escaneo = $longitud;
        $this->observaciones = $observaciones;
        $this->save();
        
        // Marcar parada como completada
        $this->paradaRuta->completar();
    }

    public function marcarAusente($motivo = null)
    {
        $this->estado = 'ausente';
        $this->observaciones = $motivo;
        $this->save();
    }

    public function marcarJustificado($motivo)
    {
        $this->estado = 'justificado';
        $this->observaciones = $motivo;
        $this->save();
    }

    public function coordenadasEscaneo()
    {
        if (!$this->latitud_escaneo || !$this->longitud_escaneo) {
            return null;
        }
        
        return [
            'lat' => (float) $this->latitud_escaneo,
            'lng' => (float) $this->longitud_escaneo
        ];
    }

    public function distanciaAParada()
    {
        if (!$this->coordenadasEscaneo() || !$this->paradaRuta) {
            return null;
        }
        
        // Calcular distancia usando fórmula de Haversine
        $lat1 = deg2rad($this->latitud_escaneo);
        $lon1 = deg2rad($this->longitud_escaneo);
        $lat2 = deg2rad($this->paradaRuta->latitud);
        $lon2 = deg2rad($this->paradaRuta->longitud);
        
        $radioTierra = 6371; // km
        
        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;
        
        $a = sin($dlat / 2) * sin($dlat / 2) +
             cos($lat1) * cos($lat2) *
             sin($dlon / 2) * sin($dlon / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        $distancia = $radioTierra * $c;
        
        return round($distancia, 3); // km con 3 decimales
    }

    public function estaEnUbicacionValida($toleranciaKm = 0.5)
    {
        $distancia = $this->distanciaAParada();
        
        if ($distancia === null) {
            return false;
        }
        
        return $distancia <= $toleranciaKm;
    }

    public function estadoBadgeClass()
    {
        return match($this->estado) {
            'presente' => 'success',
            'ausente' => 'danger',
            'justificado' => 'warning',
            default => 'secondary'
        };
    }
}
