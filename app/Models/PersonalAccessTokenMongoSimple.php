<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken;

class PersonalAccessTokenMongoSimple extends PersonalAccessToken
{
    // Configuración MongoDB
    protected $connection = 'mongodb';
    protected $collection = 'personal_access_tokens';
    
    // Usar tabla/colección personal_access_tokens por defecto
    protected $table = 'personal_access_tokens';
}
