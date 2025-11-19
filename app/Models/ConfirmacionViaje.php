<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfirmacionViaje extends Model
{
    use HasFactory;

    protected $table = 'confirmaciones_viaje';

    protected $fillable = [
        'viaje_id',
        'hijo_id',
        'usuario_id',
        'latitud',
        'longitud',
        'direccion_recogida',
        'ubicacion_automatica',
        'estado',
        'qr_escaneado',
        'hora_escaneo_qr',
        'orden_recogida',
        'hora_estimada_recogida'
    ];

    protected $casts = [
        'latitud' => 'decimal:8',
        'longitud' => 'decimal:8',
        'ubicacion_automatica' => 'boolean',
        'qr_escaneado' => 'boolean',
        'hora_escaneo_qr' => 'datetime',
        'hora_estimada_recogida' => 'datetime:H:i:s'
    ];

    /**
     * Relación con el viaje
     */
    public function viaje()
    {
        return $this->belongsTo(Viaje::class);
    }

    /**
     * Relación con el hijo
     */
    public function hijo()
    {
        return $this->belongsTo(Hijo::class);
    }

    /**
     * Relación con el usuario (padre)
     */
    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }

    /**
     * Scope para confirmaciones activas
     */
    public function scopeActivas($query)
    {
        return $query->where('estado', 'confirmado');
    }

    /**
     * Scope para confirmaciones pendientes de escaneo
     */
    public function scopePendientesEscaneo($query)
    {
        return $query->where('estado', 'confirmado')
            ->where('qr_escaneado', false);
    }
}
