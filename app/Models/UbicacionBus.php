<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UbicacionBus extends Model
{
    use HasFactory;

    protected $table = 'ubicaciones_bus';

    protected $fillable = [
        'viaje_id',
        'unidad_id',
        'latitud',
        'longitud',
        'velocidad',
        'heading',
        'precision',
        'timestamp_gps'
    ];

    protected $casts = [
        'latitud' => 'decimal:8',
        'longitud' => 'decimal:8',
        'velocidad' => 'decimal:2',
        'heading' => 'decimal:2',
        'precision' => 'decimal:2',
        'timestamp_gps' => 'datetime'
    ];

    /**
     * Relación con el viaje
     */
    public function viaje()
    {
        return $this->belongsTo(Viaje::class);
    }

    /**
     * Relación con la unidad
     */
    public function unidad()
    {
        return $this->belongsTo(Unidad::class);
    }

    /**
     * Scope para ubicaciones recientes
     */
    public function scopeRecientes($query, $minutos = 5)
    {
        return $query->where('timestamp_gps', '>=', now()->subMinutes($minutos));
    }

    /**
     * Obtiene la última ubicación de un viaje
     */
    public static function ultimaUbicacion($viajeId)
    {
        return static::where('viaje_id', $viajeId)
            ->orderBy('timestamp_gps', 'desc')
            ->first();
    }
}
