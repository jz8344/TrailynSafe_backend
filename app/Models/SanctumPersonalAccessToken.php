<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessTokenBase;
use App\Contracts\MongoSyncable;
use App\Support\MongoSync\MongoSyncTrait;

class SanctumPersonalAccessToken extends SanctumPersonalAccessTokenBase implements MongoSyncable
{
    use MongoSyncTrait;

    public function mongoCollection(): string
    {
        return 'personal_access_tokens';
    }

    public function toMongoDocument(): array
    {
        $data = parent::attributesToArray();
        return $data;
    }
}
