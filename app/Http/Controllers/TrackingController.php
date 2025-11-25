<?php

namespace App\Http\Controllers;

use App\Models\UbicacionChofer;
use App\Models\Ruta;
use App\Models\Viaje;
use App\Models\Chofer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class TrackingController extends Controller
{
    /**
     * Actualizar ubicación del chofer (App Chofer)
     */
    public function actualizarUbicacion(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'latitud' => 'required|numeric|between:-90,90',
                'longitud' => 'required|numeric|between:-180,180',
                'ruta_id' => 'nullable|exists:rutas,id',
                'velocidad' => 'nullable|numeric|min:0',
                'heading' => 'nullable|numeric|between:0,360',
                'accuracy' => 'nullable|numeric|min:0',
                'battery_level' => 'nullable|integer|between:0,100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = auth()->user();
            
            // Obtener chofer del usuario autenticado
            $chofer = Chofer::where('correo', $user->email ?? $user->correo)->first();
            
            if (!$chofer) {
                return response()->json([
                    'error' => 'Chofer no encontrado'
                ], 404);
            }

            // Si hay ruta_id, validar que pertenezca al chofer
            $viaje_id = null;
            if ($request->ruta_id) {
                $ruta = Ruta::find($request->ruta_id);
                if ($ruta && $ruta->chofer_id === $chofer->id) {
                    $viaje_id = $ruta->viaje_id;
                }
            }

            // Crear registro de ubicación
            $ubicacion = UbicacionChofer::create([
                'chofer_id' => $chofer->id,
                'ruta_id' => $request->ruta_id,
                'viaje_id' => $viaje_id,
                'latitud' => $request->latitud,
                'longitud' => $request->longitud,
                'velocidad' => $request->velocidad ?? 0,
                'heading' => $request->heading ?? 0,
                'accuracy' => $request->accuracy ?? 0,
                'battery_level' => $request->battery_level ?? 100,
                'timestamp' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ubicación actualizada',
                'ubicacion' => $ubicacion
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error actualizando ubicación: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al actualizar ubicación',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener última ubicación de un chofer (App Padre / Admin)
     */
    public function obtenerUbicacionChofer($choferId)
    {
        try {
            $ubicacion = UbicacionChofer::byChofer($choferId)
                ->recientes(10) // Últimos 10 minutos
                ->with(['chofer', 'ruta', 'viaje'])
                ->latest('timestamp')
                ->first();

            if (!$ubicacion) {
                return response()->json([
                    'error' => 'No hay ubicación reciente disponible'
                ], 404);
            }

            return response()->json([
                'ubicacion' => $ubicacion,
                'actualizada' => $ubicacion->estaActualizada(),
                'coordenadas' => $ubicacion->coordenadas(),
                'velocidad_kmh' => $ubicacion->velocidadKmh()
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error obteniendo ubicación: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener ubicación',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener ubicación en tiempo real de una ruta (App Padre)
     */
    public function trackingRuta($rutaId)
    {
        try {
            $ruta = Ruta::with([
                'viaje.escuela',
                'viaje.unidad',
                'chofer',
                'paradas' => function($query) {
                    $query->orderBy('orden')->with('confirmacion.hijo');
                }
            ])->findOrFail($rutaId);

            // Obtener última ubicación del chofer
            $ubicacion = UbicacionChofer::where('ruta_id', $rutaId)
                ->recientes(10)
                ->latest('timestamp')
                ->first();

            // Determinar parada actual (siguiente parada pendiente)
            $paradaActual = $ruta->paradas()
                ->whereIn('estado', ['pendiente', 'en_camino'])
                ->orderBy('orden')
                ->first();

            return response()->json([
                'ruta' => [
                    'id' => $ruta->id,
                    'estado' => $ruta->estado,
                    'polyline' => $ruta->polyline,
                    'distancia_total_km' => $ruta->distancia_total_km,
                    'tiempo_estimado_min' => $ruta->tiempo_estimado_min
                ],
                'viaje' => [
                    'id' => $ruta->viaje->id,
                    'escuela' => $ruta->viaje->escuela->nombre,
                    'unidad' => $ruta->viaje->unidad->matricula
                ],
                'chofer' => [
                    'id' => $ruta->chofer->id,
                    'nombre' => $ruta->chofer->nombre . ' ' . $ruta->chofer->apellidos
                ],
                'ubicacion_actual' => $ubicacion ? [
                    'latitud' => $ubicacion->latitud,
                    'longitud' => $ubicacion->longitud,
                    'velocidad_kmh' => $ubicacion->velocidadKmh(),
                    'heading' => $ubicacion->heading,
                    'timestamp' => $ubicacion->timestamp,
                    'actualizada' => $ubicacion->estaActualizada()
                ] : null,
                'parada_actual' => $paradaActual ? [
                    'orden' => $paradaActual->orden,
                    'direccion' => $paradaActual->direccion,
                    'latitud' => $paradaActual->latitud,
                    'longitud' => $paradaActual->longitud,
                    'hora_estimada' => $paradaActual->hora_estimada,
                    'hijo' => $paradaActual->confirmacion->hijo->nombre ?? 'Sin nombre',
                    'estado' => $paradaActual->estado
                ] : null,
                'paradas' => $ruta->paradas->map(function($parada) {
                    return [
                        'orden' => $parada->orden,
                        'direccion' => $parada->direccion,
                        'latitud' => $parada->latitud,
                        'longitud' => $parada->longitud,
                        'hora_estimada' => $parada->hora_estimada,
                        'hijo' => $parada->confirmacion->hijo->nombre ?? 'Sin nombre',
                        'estado' => $parada->estado
                    ];
                })
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error en tracking de ruta: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener tracking',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Historial de ubicaciones de una ruta (Admin)
     */
    public function historialRuta($rutaId)
    {
        try {
            $ubicaciones = UbicacionChofer::where('ruta_id', $rutaId)
                ->orderBy('timestamp')
                ->get();

            return response()->json([
                'ruta_id' => $rutaId,
                'total_puntos' => $ubicaciones->count(),
                'ubicaciones' => $ubicaciones->map(function($u) {
                    return [
                        'latitud' => $u->latitud,
                        'longitud' => $u->longitud,
                        'velocidad_kmh' => $u->velocidadKmh(),
                        'timestamp' => $u->timestamp
                    ];
                })
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error obteniendo historial: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener historial',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Limpiar ubicaciones antiguas (Admin / Cron)
     */
    public function limpiarUbicacionesAntiguas()
    {
        try {
            // Eliminar ubicaciones mayores a 7 días
            $eliminadas = UbicacionChofer::where('timestamp', '<', now()->subDays(7))
                ->delete();

            return response()->json([
                'success' => true,
                'message' => "Se eliminaron {$eliminadas} registros antiguos"
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error limpiando ubicaciones: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al limpiar ubicaciones',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
