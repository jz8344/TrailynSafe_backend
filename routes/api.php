<?php

use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\MultaController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\CodigoSeguridadController;
use App\Http\Controllers\GoogleAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HijoController;
use App\Http\Controllers\ChoferController;
use App\Http\Controllers\UnidadController;
use App\Http\Controllers\RutaController;
use App\Http\Controllers\EscuelaController;
use App\Http\Controllers\ImpresionController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\ViajeController;
use App\Http\Controllers\ConfirmacionViajeController;
use App\Http\Controllers\ChoferViajeController;

// Rutas públicas para usuarios
Route::post('/register', [UsuarioController::class, 'register']);
Route::post('/login', [UsuarioController::class, 'login'])->name('login');
Route::post('/cambiar-contrasena', [UsuarioController::class, 'actualizarContrasena']);
Route::post('/enviar-codigo', [CodigoSeguridadController::class, 'enviarCodigo']);
Route::post('/validar-codigo', [CodigoSeguridadController::class, 'validarCodigo']);
Route::get('/escuelas-activas', [EscuelaController::class, 'activas']);

// Rutas de autenticación con Google
Route::post('/auth/google', [GoogleAuthController::class, 'loginWithGoogle']);

// Rutas públicas para administradores
Route::post('/admin/register', [AdminController::class, 'register']);
Route::post('/admin/login', [AdminController::class, 'login']);

// Rutas protegidas para usuarios regulares
Route::middleware(['auth:sanctum', \App\Http\Middleware\CheckRoleUsuario::class])->group(function () {
    Route::get('/sesion', [SessionController::class, 'index']);
    Route::get('/validar-sesion', [SessionController::class, 'validarSesion']);
    Route::delete('/sesiones/{id}', [SessionController::class, 'destroy']);
    Route::delete('/sesiones', [SessionController::class, 'destroyAll']);
    Route::post('/sesiones/cerrar-actual', [SessionController::class, 'destroyCurrent']);
    Route::post('/editar-perfil', [UsuarioController::class, 'editarPerfil']);
    Route::post('/editar-datos', [UsuarioController::class, 'editarDatos']);
    Route::post('/cambiar-correo', [UsuarioController::class, 'cambiarCorreo']);
    Route::post('/validar-password-actual', [UsuarioController::class, 'validarPasswordActual']);
    Route::post('/cambiar-contrasena-autenticado', [UsuarioController::class, 'cambiarContrasena']);
    Route::post('/enviar-codigo-auth', [CodigoSeguridadController::class, 'enviarCodigo']);
    Route::post('/validar-codigo-auth', [CodigoSeguridadController::class, 'validarCodigo']);
    Route::get('/hvson', [UsuarioController::class, 'checkHaveSon']);
    Route::post('/update-have-son', [UsuarioController::class, 'updateHaveSon']);
    // CRUD Hijos para usuarios
    Route::get('/hijos', [HijoController::class, 'userIndex']);
    Route::post('/hijos', [HijoController::class, 'userStore']);
    Route::post('/solicitar-impresion-qrs', [ImpresionController::class, 'solicitar']);
    
    // Google Auth - Desconectar cuenta
    Route::post('/auth/google/disconnect', [GoogleAuthController::class, 'disconnectGoogle']);
    
    // Viajes y confirmaciones para padres
    Route::get('/viajes/disponibles', [ConfirmacionViajeController::class, 'viajesDisponibles']);
    Route::post('/viajes/confirmar', [ConfirmacionViajeController::class, 'confirmarViaje']);
    Route::post('/viajes/cancelar', [ConfirmacionViajeController::class, 'cancelarConfirmacion']);
    Route::get('/mis-confirmaciones', [ConfirmacionViajeController::class, 'misConfirmaciones']);
    
    // Notificaciones (para testing - en producción se ejecutarían con cron)
    Route::post('/test/notificaciones/recordatorios', [App\Http\Controllers\NotificacionController::class, 'enviarRecordatoriosViaje']);
    Route::post('/test/notificaciones/inicio-confirmacion', [App\Http\Controllers\NotificacionController::class, 'notificarInicioPeriodoConfirmacion']);
    Route::post('/test/notificaciones/confirmaciones-automaticas', [App\Http\Controllers\NotificacionController::class, 'procesarConfirmacionesAutomaticas']);
});

// Rutas protegidas para administradores
Route::middleware(['auth:admin-sanctum'])->group(function () {
    Route::get('/admin/sesion', [AdminController::class, 'obtenerSesion']);
    Route::get('/admin/validar-sesion', [AdminController::class, 'validarSesion']);
    Route::get('/usuarios', [AdminController::class, 'list']);
    Route::get('/admin/usuarios', [AdminController::class, 'usersIndex']);
    Route::post('/admin/usuarios', [AdminController::class, 'createUser']);
    Route::put('/admin/usuarios/{id}', [AdminController::class, 'updateUser']);
    Route::delete('/admin/usuarios/{id}', [AdminController::class, 'deleteUser']);
    // CRUD Hijos
    Route::get('/admin/hijos', [HijoController::class, 'index']);
    Route::post('/admin/hijos', [HijoController::class, 'store']);
    Route::get('/admin/hijos/{id}', [HijoController::class, 'show']);
    Route::put('/admin/hijos/{id}', [HijoController::class, 'update']);
    Route::delete('/admin/hijos/{id}', [HijoController::class, 'destroy']);
    // CRUD Choferes
    Route::get('/admin/choferes', [ChoferController::class, 'index']);
    Route::post('/admin/choferes', [ChoferController::class, 'store']);
    Route::put('/admin/choferes/{id}', [ChoferController::class, 'update']);
    Route::delete('/admin/choferes/{id}', [ChoferController::class, 'destroy']);
    // CRUD Unidades
    Route::get('/admin/unidades', [UnidadController::class, 'index']);
    Route::post('/admin/unidades', [UnidadController::class, 'store']);
    Route::put('/admin/unidades/{id}', [UnidadController::class, 'update']);
    Route::post('/admin/unidades/{id}', [UnidadController::class, 'update']); // Para FormData
    Route::delete('/admin/unidades/{id}', [UnidadController::class, 'destroy']);
    // CRUD Rutas
    Route::get('/admin/rutas', [RutaController::class, 'index']);
    Route::post('/admin/rutas', [RutaController::class, 'store']);
    Route::put('/admin/rutas/{id}', [RutaController::class, 'update']);
    Route::delete('/admin/rutas/{id}', [RutaController::class, 'destroy']);
    // CRUD Escuelas
    Route::get('/admin/escuelas/search', [EscuelaController::class, 'search']);
    Route::get('/admin/escuelas', [EscuelaController::class, 'index']);
    Route::post('/admin/escuelas', [EscuelaController::class, 'store']);
    Route::get('/admin/escuelas/{id}', [EscuelaController::class, 'show']);
    Route::put('/admin/escuelas/{id}', [EscuelaController::class, 'update']);
    Route::delete('/admin/escuelas/{id}', [EscuelaController::class, 'destroy']);
    Route::get('/admin/escuelas-activas', [EscuelaController::class, 'activas']);
    Route::post('/admin/editar-perfil', [AdminController::class, 'editarPerfil']);
    Route::post('/admin/actualizar-contrasena', [AdminController::class, 'newPassword']);
    Route::post('/admin/sesiones/cerrar-actual', [SessionController::class, 'destroyCurrent']);
    Route::delete('/admin/sesiones/{id}', [SessionController::class, 'destroy']);
    Route::delete('/admin/sesiones', [SessionController::class, 'destroyAll']);
    
    // Backup Management - PostgreSQL
    Route::get('/admin/backups/tables', [BackupController::class, 'getTables']);
    Route::get('/admin/backups', [BackupController::class, 'index']);
    Route::post('/admin/backups', [BackupController::class, 'create']);
    Route::get('/admin/backups/{id}/download', [BackupController::class, 'download']);
    Route::delete('/admin/backups/{id}', [BackupController::class, 'delete']);
    Route::post('/admin/backups/{id}/restore', [BackupController::class, 'restore']);
    Route::post('/admin/backups/cleanup', [BackupController::class, 'cleanup']);
    
    // CRUD Viajes
    Route::get('/admin/viajes', [ViajeController::class, 'index']);
    Route::post('/admin/viajes', [ViajeController::class, 'store']);
    Route::get('/admin/viajes/{id}', [ViajeController::class, 'show']);
    Route::put('/admin/viajes/{id}', [ViajeController::class, 'update']);
    Route::delete('/admin/viajes/{id}', [ViajeController::class, 'destroy']);
    Route::get('/admin/viajes/hoy/list', [ViajeController::class, 'viajesHoy']);
    Route::post('/admin/viajes/{id}/abrir-confirmaciones', [ViajeController::class, 'abrirConfirmaciones']);
    Route::post('/admin/viajes/{id}/cerrar-confirmaciones', [ViajeController::class, 'cerrarConfirmaciones']);
    Route::get('/admin/viajes/{id}/confirmaciones', [ViajeController::class, 'confirmaciones']);
    Route::post('/admin/viajes/{id}/activar-hoy', [ViajeController::class, 'activarHoy']);
});

// Rutas para choferes (API móvil - requiere autenticación de chofer)
Route::prefix('chofer')->group(function () {
    Route::get('/viajes', [ChoferViajeController::class, 'misViajes']);
    Route::post('/viajes/{id}/iniciar', [ChoferViajeController::class, 'iniciarViaje']);
    Route::post('/viajes/{id}/finalizar', [ChoferViajeController::class, 'finalizarViaje']);
    Route::get('/viajes/{id}/hijos-confirmados', [ChoferViajeController::class, 'hijosConfirmados']);
    Route::post('/escanear-qr', [ChoferViajeController::class, 'escanearQR']);
    Route::post('/ubicacion', [ChoferViajeController::class, 'actualizarUbicacion']);
    Route::post('/telemetria', [ChoferViajeController::class, 'registrarTelemetria']);
});

