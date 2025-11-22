<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificacionPanel extends Model
{
    use HasFactory;

    protected $table = 'notificaciones_panel';

    protected $fillable = [
        'titulo',
        'mensaje',
        'tipo',
        'entity_type',
        'entity_id',
        'admin_id',
        'admin_name',
        'read'
    ];

    protected $casts = [
        'read' => 'boolean',
    ];

    // Relación con el admin que generó la acción (opcional)
    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }
}
