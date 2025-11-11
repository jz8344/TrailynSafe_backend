<?php
namespace App\Support\MongoSync;

use App\Contracts\MongoSyncable;

trait MongoSyncTrait
{
    public static function bootMongoSyncTrait(): void
    {
        // Hookeado por observer central.
    }

    public function mongoCollection(): string
    {
        return $this->getTable();
    }

    public function mongoDocumentId()
    {
        return $this->getKey();
    }

    public function toMongoDocument(): array
    {
        $arr = $this->attributesToArray();
        // Remueve claves Laravel que no quieras replicar (ejemplo passwords)
        if (array_key_exists('contrasena', $arr)) {
            $arr['password_hash'] = $arr['contrasena'];
            unset($arr['contrasena']);
        }
        if (array_key_exists('password', $arr)) {
            $arr['password_hash'] = $arr['password'];
            unset($arr['password']);
        }
        // Para PersonalAccessToken, incluir el token hasheado pero no token plano
        if (array_key_exists('token', $arr) && strlen($arr['token']) > 100) {
            // Mantener token hasheado (largo), eliminar si es plano (corto)
        }
        return $arr;
    }
}
