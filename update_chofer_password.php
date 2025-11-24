<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Chofer;
use Illuminate\Support\Facades\Hash;

// Actualizar todos los choferes sin password
$choferes = Chofer::whereNull('password')->orWhere('password', '')->get();

$defaultPassword = 'trailyn2025'; // Password temporal por defecto

if ($choferes->count() > 0) {
    echo "Actualizando {$choferes->count()} chofer(es) sin password...\n\n";
    
    foreach ($choferes as $chofer) {
        $chofer->password = Hash::make($defaultPassword);
        
        // Asegurar que el estado sea válido
        if (!in_array($chofer->estado, ['disponible', 'en_ruta', 'no_activo'])) {
            $chofer->estado = 'disponible';
        }
        
        $chofer->save();
        
        echo "✓ {$chofer->nombre} {$chofer->apellidos}\n";
        echo "  Correo: {$chofer->correo}\n";
        echo "  Password temporal: {$defaultPassword}\n";
        echo "  Estado: {$chofer->estado}\n\n";
    }
    
    echo "Todos los choferes actualizados exitosamente.\n";
} else {
    echo "✓ Todos los choferes ya tienen password asignado.\n";
}
