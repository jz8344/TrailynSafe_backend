<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Chofer extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'choferes';

    protected $fillable = [
        'usuario_id',
        'licencia',
        'correo',
        'password',
        'telefono',
        'nombre',
        'apellidos',
        'numero_licencia',
        'curp',
        'estado',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con usuario (si existe)
     */
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /**
     * Relación con rutas asignadas
     */
    public function rutas()
    {
        return $this->hasMany(Ruta::class, 'chofer_id');
    }

    /**
     * Relación con viajes
     */
    public function viajes()
    {
        return $this->hasMany(Viaje::class, 'chofer_id');
    }
}

