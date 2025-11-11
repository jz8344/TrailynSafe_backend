<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Contracts\MongoSyncable;
use App\Support\MongoSync\MongoSyncTrait;

class CodigoSeguridad extends Model implements MongoSyncable
{
    use HasFactory, MongoSyncTrait;

    protected $table = 'codigo_seguridads';

    protected $fillable = [
        'usuario_id',
        'codigo',
        'expires_at',
    ];

    public $timestamps = true;

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
