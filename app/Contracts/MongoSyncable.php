<?php

namespace App\Contracts;

interface MongoSyncable
{
    /** Nombre de la colección destino en Mongo */
    public function mongoCollection(): string;

    /** Representación serializable para Mongo */
    public function toMongoDocument(): array;

    /** Identificador usado como _id en Mongo */
    public function mongoDocumentId();
}
