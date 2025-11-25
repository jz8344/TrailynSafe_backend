<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UbicacionChofer extends Model
{
    use HasFactory;

    protected $table = 'ubicaciones_chofer';

    protected $fillable = [
        'chofer_id',
        'ruta_id',
        'viaje_id',
        'latitud',
        'longitud',
        'velocidad',
        'heading', // Dirección en grados (0-360)
        'accuracy', // Precisión del GPS en metros
        'timestamp',
        'battery_level' // Nivel de batería del dispositivo
    ];

    protected $casts = [
        'latitud' => 'decimal:8',
        'longitud' => 'decimal:8',
        'velocidad' => 'decimal:2',
        'heading' => 'decimal:2',
        'accuracy' => 'decimal:2',
        'battery_level' => 'integer',
        'timestamp' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // ==================== RELACIONES ====================

    public function chofer()
    {
        return $this->belongsTo(Chofer::class);
    }

    public function ruta()
    {
        return $this->belongsTo(Ruta::class);
    }

    public function viaje()
    {
        return $this->belongsTo(Viaje::class);
    }

    // ==================== SCOPES ====================

    public function scopeByChofer($query, $choferId)
    {
        return $query->where('chofer_id', $choferId);
    }

    public function scopeByRuta($query, $rutaId)
    {
        return $query->where('ruta_id', $rutaId);
    }

    public function scopeRecientes($query, $minutos = 5)
    {
        return $query->where('timestamp', '>=', now()->subMinutes($minutos));
    }

    public function scopeUltima($query)
    {
        return $query->latest('timestamp')->first();
    }

    // ==================== MÉTODOS ====================

    public function coordenadas()
    {
        return [
            'lat' => (float) $this->latitud,
            'lng' => (float) $this->longitud
        ];
    }

    public function estaActualizada($minutos = 5)
    {
        return $this->timestamp >= now()->subMinutes($minutos);
    }

    public function velocidadKmh()
    {
        return round($this->velocidad * 3.6, 2); // Convertir m/s a km/h
    }
}
