<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ruta extends Model
{
    use HasFactory;

    protected $table = 'rutas';

    protected $fillable = [
        'nombre',
        'descripcion',
        'viaje_id',
        'escuela_id',
        'distancia_total_km',
        'tiempo_estimado_minutos',
        'estado',
        'algoritmo_utilizado',
        'polyline',
        'parametros_algoritmo',
        'fecha_generacion'
    ];

    protected $casts = [
        'distancia_total_km' => 'decimal:2',
        'tiempo_estimado_minutos' => 'integer',
        'parametros_algoritmo' => 'array',
        'fecha_generacion' => 'datetime',
    ];

    // ==================== RELACIONES ====================

    public function viaje()
    {
        return $this->belongsTo(Viaje::class);
    }

    public function escuela()
    {
        return $this->belongsTo(Escuela::class);
    }

    public function paradas()
    {
        return $this->hasMany(ParadaRuta::class)->orderBy('orden');
    }

    public function paradasPendientes()
    {
        return $this->hasMany(ParadaRuta::class)->where('estado', 'pendiente')->orderBy('orden');
    }

    public function paradasCompletadas()
    {
        return $this->hasMany(ParadaRuta::class)->where('estado', 'completada')->orderBy('orden');
    }

    // ==================== SCOPES ====================

    public function scopeActivas($query)
    {
        return $query->where('estado', 'activa');
    }

    public function scopeEnProgreso($query)
    {
        return $query->where('estado', 'en_progreso');
    }

    public function scopeCompletadas($query)
    {
        return $query->where('estado', 'completada');
    }

    // ==================== MÉTODOS DE ESTADO ====================

    public function iniciar()
    {
        if ($this->estado !== 'activa') {
            throw new \Exception('Solo se pueden iniciar rutas activas');
        }
        
        $this->estado = 'en_progreso';
        $this->save();
    }

    public function completar()
    {
        if ($this->estado !== 'en_progreso') {
            throw new \Exception('Solo se pueden completar rutas en progreso');
        }
        
        $this->estado = 'completada';
        $this->save();
    }

    public function cancelar()
    {
        $this->estado = 'cancelada';
        $this->save();
    }

    // ==================== MÉTODOS DE UTILIDAD ====================

    public function totalParadas()
    {
        return $this->paradas()->count();
    }

    public function paradasCompletadasCount()
    {
        return $this->paradas()->where('estado', 'completada')->count();
    }

    public function porcentajeProgreso()
    {
        $total = $this->totalParadas();
        
        if ($total == 0) {
            return 0;
        }
        
        return round(($this->paradasCompletadasCount() / $total) * 100, 2);
    }

    public function distanciaLegible()
    {
        return number_format($this->distancia_total_km, 2) . ' km';
    }

    public function tiempoLegible()
    {
        $horas = floor($this->tiempo_estimado_minutos / 60);
        $minutos = $this->tiempo_estimado_minutos % 60;
        
        if ($horas > 0) {
            return "{$horas}h {$minutos}min";
        }
        
        return "{$minutos} min";
    }

    public function proximaParada()
    {
        return $this->paradas()
            ->where('estado', 'pendiente')
            ->orderBy('orden')
            ->first();
    }

    public function paradaActual()
    {
        return $this->paradas()
            ->where('estado', 'en_camino')
            ->first();
    }
}
