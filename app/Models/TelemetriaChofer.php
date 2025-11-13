<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelemetriaChofer extends Model
{
    use HasFactory;

    protected $table = 'telemetria_chofer';

    protected $fillable = [
        'chofer_id',
        'viaje_id',
        'frecuencia_cardiaca',
        'velocidad',
        'aceleracion',
        'latitud',
        'longitud',
        'altitud',
        'impacto_detectado',
        'temperatura',
        'nivel_combustible',
        'nivel_alerta',
        'descripcion_alerta',
        'timestamp_lectura'
    ];

    protected $casts = [
        'frecuencia_cardiaca' => 'integer',
        'velocidad' => 'decimal:2',
        'aceleracion' => 'decimal:2',
        'latitud' => 'decimal:8',
        'longitud' => 'decimal:8',
        'altitud' => 'decimal:2',
        'impacto_detectado' => 'boolean',
        'temperatura' => 'decimal:2',
        'nivel_combustible' => 'decimal:2',
        'timestamp_lectura' => 'datetime'
    ];

    /**
     * Relación con el chofer
     */
    public function chofer()
    {
        return $this->belongsTo(Chofer::class);
    }

    /**
     * Relación con el viaje
     */
    public function viaje()
    {
        return $this->belongsTo(Viaje::class);
    }

    /**
     * Scope para alertas de peligro
     */
    public function scopePeligro($query)
    {
        return $query->where('nivel_alerta', 'peligro');
    }

    /**
     * Scope para lecturas recientes
     */
    public function scopeRecientes($query, $minutos = 5)
    {
        return $query->where('timestamp_lectura', '>=', now()->subMinutes($minutos));
    }
}
