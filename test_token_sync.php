<?php

$user = App\Models\Usuario::first();
if ($user) {
    $token = $user->createToken('test-sync-' . time());
    echo 'Token creado con ID: ' . $token->accessToken->id . PHP_EOL;
    echo 'Verificar en MongoDB Atlas si aparece en colección personal_access_tokens' . PHP_EOL;
} else {
    echo 'No hay usuarios en la base de datos' . PHP_EOL;
}
