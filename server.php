<?php
/**
 * Este archivo solo se usa cuando ejecutas PHP con: php -S localhost:8000 server.php
 * Si usas 'php artisan serve', este archivo NO se utiliza.
 * El CORS se maneja automáticamente por config/cors.php cuando usas artisan serve.
 */

// Mostrar errores para debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Cargar Laravel
require_once __DIR__ . '/public/index.php';
