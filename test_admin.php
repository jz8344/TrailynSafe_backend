<?php

// Script de prueba simple para verificar el login de admin
require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

// Cargar la aplicación Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Test de Admin ===\n";

// Verificar que existen admins
$adminCount = Admin::count();
echo "Número de admins: $adminCount\n";

if ($adminCount > 0) {
    $admin = Admin::first();
    echo "Primer admin: {$admin->email}\n";
    
    // Verificar password
    $passwordCheck = Hash::check('admin123', $admin->password);
    echo "Password correcto: " . ($passwordCheck ? 'SÍ' : 'NO') . "\n";
    
    // Probar creación de token
    try {
        $token = $admin->createToken('test-token');
        echo "Token creado exitosamente: " . substr($token->plainTextToken, 0, 20) . "...\n";
    } catch (Exception $e) {
        echo "Error creando token: " . $e->getMessage() . "\n";
    }
} else {
    echo "No hay admins en la base de datos\n";
}

echo "=== Fin del test ===\n";
