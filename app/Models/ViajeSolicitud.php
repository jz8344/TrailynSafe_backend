<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ViajeSolicitud extends Model
{
    use HasFactory;

    protected $table = 'viaje_solicitudes';

    protected $fillable = [
        'viaje_id',
        'hijo_id',
        'padre_id',
        'estado_confirmacion',
        'latitud',
        'longitud',
        'direccion_formateada',
        'fecha_confirmacion',
        'fecha_rechazo',
        'notas_padre',
        'notas_admin'
    ];

    protected $casts = [
        'latitud' => 'decimal:8',
        'longitud' => 'decimal:8',
        'fecha_confirmacion' => 'datetime',
        'fecha_rechazo' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relaciones
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

    // Accessors
    public function getEstadoLabelAttribute()
    {
        $labels = [
            'pendiente' => 'Pendiente',
            'aceptado' => 'Aceptado',
            'rechazado' => 'Rechazado',
            'cancelado' => 'Cancelado'
        ];
        return $labels[$this->estado_confirmacion] ?? $this->estado_confirmacion;
    }

    public function getCoordenadas()
    {
        if ($this->latitud && $this->longitud) {
            return [
                'lat' => (float) $this->latitud,
                'lng' => (float) $this->longitud
            ];
        }
        return null;
    }

    // Scopes
    public function scopePendientes($query)
    {
        return $query->where('estado_confirmacion', 'pendiente');
    }

    public function scopeAceptadas($query)
    {
        return $query->where('estado_confirmacion', 'aceptado');
    }

    public function scopeRechazadas($query)
    {
        return $query->where('estado_confirmacion', 'rechazado');
    }

    public function scopePorPadre($query, $padreId)
    {
        return $query->where('padre_id', $padreId);
    }

    public function scopePorHijo($query, $hijoId)
    {
        return $query->where('hijo_id', $hijoId);
    }

    // Métodos de estado
    public function aceptar($notasAdmin = null)
    {
        $this->estado_confirmacion = 'aceptado';
        $this->fecha_confirmacion = Carbon::now();
        if ($notasAdmin) {
            $this->notas_admin = $notasAdmin;
        }
        $this->save();

        // Incrementar capacidad del viaje
        $this->viaje->incrementarCapacidad();
    }

    public function rechazar($notasAdmin = null)
    {
        $this->estado_confirmacion = 'rechazado';
        $this->fecha_rechazo = Carbon::now();
        if ($notasAdmin) {
            $this->notas_admin = $notasAdmin;
        }
        $this->save();
    }

    public function cancelar()
    {
        $anterior = $this->estado_confirmacion;
        $this->estado_confirmacion = 'cancelado';
        $this->save();

        // Si estaba aceptado, decrementar capacidad del viaje
        if ($anterior === 'aceptado') {
            $this->viaje->decrementarCapacidad();
        }
    }
}
