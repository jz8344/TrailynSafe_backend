<?php

return [
    'personal_access_token_model' => App\Models\SanctumPersonalAccessToken::class,
    'expiration' => null,
    'middleware' => [
        'verify_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
        'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
    ],
];
