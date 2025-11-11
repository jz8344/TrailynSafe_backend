<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$token = Laravel\Sanctum\PersonalAccessToken::first();
if ($token) {
    echo "Campos en PostgreSQL:\n";
    print_r($token->attributesToArray());
    
    echo "\n\nCampos específicos:\n";
    echo "ID: " . $token->id . "\n";
    echo "Token hash: " . substr($token->token, 0, 20) . "...\n";
    echo "Name: " . $token->name . "\n";
    echo "Tokenable Type: " . $token->tokenable_type . "\n";
    echo "Tokenable ID: " . $token->tokenable_id . "\n";
} else {
    echo "No hay tokens\n";
}
