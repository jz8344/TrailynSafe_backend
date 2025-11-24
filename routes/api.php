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
use App\Http\Controllers\EscuelaController;
use App\Http\Controllers\ImpresionController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\ViajeController;
use App\Http\Controllers\RutaController;
use App\Http\Controllers\ConfirmacionController;
use App\Http\Controllers\AsistenciaController;

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

// Rutas públicas para choferes
Route::post('/chofer/login', [App\Http\Controllers\ChoferAuthController::class, 'login']);


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
    
    // Viajes - Usuarios/Padres
    Route::get('/viajes/disponibles', [ViajeController::class, 'viajesDisponibles']);
    Route::post('/viajes/{viaje_id}/confirmar', [ConfirmacionController::class, 'confirmar']);
    Route::put('/confirmaciones/{confirmacion_id}/direccion', [ConfirmacionController::class, 'actualizarDireccion']);
    Route::delete('/confirmaciones/{confirmacion_id}', [ConfirmacionController::class, 'cancelar']);
    Route::get('/confirmaciones/mis-confirmaciones', [ConfirmacionController::class, 'misConfirmaciones']);
    Route::get('/hijos/{hijo_id}/asistencias', [AsistenciaController::class, 'historialHijo']);
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
    
    // Notificaciones Panel (Actividad Reciente)
    Route::get('/admin/notificaciones', [App\Http\Controllers\NotificacionPanelController::class, 'index']);
    Route::post('/admin/notificaciones', [App\Http\Controllers\NotificacionPanelController::class, 'store']);
    Route::put('/admin/notificaciones/{id}/read', [App\Http\Controllers\NotificacionPanelController::class, 'markAsRead']);
    Route::put('/admin/notificaciones/read-all', [App\Http\Controllers\NotificacionPanelController::class, 'markAllAsRead']);
    Route::delete('/admin/notificaciones/{id}', [App\Http\Controllers\NotificacionPanelController::class, 'destroy']);
    Route::delete('/admin/notificaciones', [App\Http\Controllers\NotificacionPanelController::class, 'destroyAll']);
    
    // CRUD Viajes
    Route::get('/admin/viajes', [ViajeController::class, 'index']);
    Route::post('/admin/viajes', [ViajeController::class, 'store']);
    Route::get('/admin/viajes/{id}', [ViajeController::class, 'show']);
    Route::put('/admin/viajes/{id}', [ViajeController::class, 'update']);
    Route::delete('/admin/viajes/{id}', [ViajeController::class, 'destroy']);
    
    // Acciones de Viajes
    Route::put('/admin/viajes/{id}/programar', [ViajeController::class, 'programar']);
    Route::put('/admin/viajes/{id}/cancelar', [ViajeController::class, 'cancelar']);
    Route::put('/admin/viajes/{id}/generar-ruta', [ViajeController::class, 'generarRuta']);
    
    // Confirmaciones de Viajes
    Route::get('/admin/viajes/{viaje_id}/confirmaciones', [ConfirmacionController::class, 'index']);
    
    // Rutas
    Route::get('/admin/rutas', [RutaController::class, 'index']);
    Route::get('/admin/rutas/{id}', [RutaController::class, 'show']);
    
    // Asistencias
    Route::get('/admin/viajes/{viaje_id}/asistencias', [AsistenciaController::class, 'porViaje']);
});

// Rutas públicas para webhook (sin autenticación, pero con validación de token en el controller si es necesario)
Route::post('/webhook/ruta-generada', [RutaController::class, 'recibirRutaGenerada']);

// Rutas protegidas para choferes
Route::middleware(['auth:sanctum'])->prefix('chofer')->group(function () {
    // Autenticación
    Route::post('/logout', [App\Http\Controllers\ChoferAuthController::class, 'logout']);
    Route::get('/profile', [App\Http\Controllers\ChoferAuthController::class, 'profile']);
    Route::put('/profile', [App\Http\Controllers\ChoferAuthController::class, 'updateProfile']);
    Route::post('/change-password', [App\Http\Controllers\ChoferAuthController::class, 'changePassword']);
    
    // Rutas asignadas
    Route::get('/rutas', [RutaController::class, 'rutasChofer']);
    Route::get('/rutas/{id}', [RutaController::class, 'show']);
    Route::post('/rutas/{ruta_id}/iniciar', [RutaController::class, 'iniciarRuta']);
    Route::post('/rutas/{ruta_id}/completar', [RutaController::class, 'completarRuta']);
    Route::post('/paradas/{parada_id}/llegar', [RutaController::class, 'llegarAParada']);
    
    // Asistencias
    Route::post('/asistencias', [AsistenciaController::class, 'registrar']);
    Route::post('/asistencias/ausente', [AsistenciaController::class, 'marcarAusente']);
});


