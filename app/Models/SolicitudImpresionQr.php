<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitudImpresionQr extends Model
{
    use HasFactory;

    protected $table = 'solicitudes_impresion_qr';

    protected $fillable = [
        'usuario_id',
        'hijos_ids',
        'estado',
    ];

    protected $casts = [
        'hijos_ids' => 'array',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
