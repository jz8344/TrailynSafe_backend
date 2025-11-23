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
        'padre_id',
        'direccion_recogida',
        'referencia',
        'latitud',
        'longitud',
        'hora_confirmacion',
        'estado',
        'orden_recogida',
        'hora_estimada_recogida',
    ];

    protected $casts = [
        'latitud' => 'decimal:8',
        'longitud' => 'decimal:8',
        'hora_confirmacion' => 'datetime',
        'hora_estimada_recogida' => 'datetime:H:i',
        'orden_recogida' => 'integer',
    ];

    // ==================== RELACIONES ====================

    public function viaje()
    {
        return $this->belongsTo(Viaje::class);
    }

    public function hijo()
    {
        return $this->belongsTo(Hijo::class);
    }

    public function padre()
    {
        return $this->belongsTo(Usuario::class, 'padre_id');
    }

    public function parada()
    {
        return $this->hasOne(ParadaRuta::class, 'confirmacion_id');
    }

    // ==================== SCOPES ====================

    public function scopeConfirmadas($query)
    {
        return $query->where('estado', 'confirmado');
    }

    public function scopeCanceladas($query)
    {
        return $query->where('estado', 'cancelado');
    }

    public function scopeByViaje($query, $viajeId)
    {
        return $query->where('viaje_id', $viajeId);
    }

    public function scopeByPadre($query, $padreId)
    {
        return $query->where('padre_id', $padreId);
    }

    public function scopeByHijo($query, $hijoId)
    {
        return $query->where('hijo_id', $hijoId);
    }

    // ==================== MÉTODOS ====================

    public function confirmar()
    {
        $this->estado = 'confirmado';
        $this->hora_confirmacion = now();
        $this->save();
        
        // Incrementar contador en viaje
        $this->viaje->agregarConfirmacion();
    }

    public function cancelar()
    {
        if ($this->estado === 'confirmado') {
            $this->estado = 'cancelado';
            $this->save();
            
            // Decrementar contador en viaje
            $this->viaje->quitarConfirmacion();
        }
    }

    public function asignarOrdenYHora($orden, $horaEstimada)
    {
        $this->orden_recogida = $orden;
        $this->hora_estimada_recogida = $horaEstimada;
        $this->save();
    }

    public function coordenadas()
    {
        return [
            'lat' => (float) $this->latitud,
            'lng' => (float) $this->longitud
        ];
    }

    public function direccionCompleta()
    {
        $direccion = $this->direccion_recogida;
        
        if ($this->referencia) {
            $direccion .= " - Ref: {$this->referencia}";
        }
        
        return $direccion;
    }
}
