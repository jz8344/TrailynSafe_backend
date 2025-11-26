<?php

namespace App\Http\Controllers;

use App\Models\Ruta;
use App\Models\Viaje;
use App\Models\ParadaRuta;
use App\Models\ConfirmacionViaje;
use App\Models\Asistencia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RutaController extends Controller
{
    /**
     * Listar todas las rutas (Admin)
     */
    public function index(Request $request)
    {
        try {
            $query = Ruta::with(['viaje', 'escuela', 'paradas']);
            
            if ($request->has('estado')) {
                $query->where('estado', $request->estado);
            }
            
            $rutas = $query->orderByDesc('fecha_generacion')->get();
            
            return response()->json($rutas, 200);
            
        } catch (\Exception $e) {
            Log::error('Error al listar rutas: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener rutas',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener una ruta específica con todas sus paradas
     */
    public function show($id)
    {
        try {
            $ruta = Ruta::with([
                'viaje.escuela',
                'viaje.unidad',
                'viaje.chofer',
                'escuela',
                'paradas' => function($query) {
                    $query->orderBy('orden');
                },
                'paradas.confirmacion.hijo',
                'paradas.confirmacion.padre',
                'paradas.asistencia'
            ])->findOrFail($id);
            
            return response()->json($ruta, 200);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Ruta no encontrada'], 404);
        } catch (\Exception $e) {
            Log::error('Error al obtener ruta: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener la ruta',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Webhook: Recibir ruta generada por k-Means (desde Django)
     */
    public function recibirRutaGenerada(Request $request)
    {
        try {
            Log::info("Webhook recibido de Django", ['payload' => $request->all()]);
            
            $validator = Validator::make($request->all(), [
                'viaje_id' => 'required|exists:viajes,id',
                'ruta' => 'required|array',
                'ruta.ruta_optimizada' => 'required|array',
                'ruta.distancia_total_km' => 'required|numeric',
                'ruta.tiempo_total_min' => 'required|integer'
            ]);
            
            if ($validator->fails()) {
                Log::error("Validación fallida en webhook", ['errors' => $validator->errors()]);
                return response()->json([
                    'error' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $viaje = Viaje::findOrFail($request->viaje_id);
            $rutaData = $request->ruta;
            
            // Validar que el viaje esté generando ruta
            if ($viaje->estado !== 'generando_ruta') {
                Log::warning("Viaje no está en generando_ruta", ['estado' => $viaje->estado]);
                return response()->json([
                    'error' => 'El viaje no está en estado de generación de ruta',
                    'estado_actual' => $viaje->estado
                ], 422);
            }
            
            DB::beginTransaction();
            
            try {
                // Crear registro de ruta
                $ruta = Ruta::create([
                    'nombre' => "Ruta Viaje #{$viaje->id} - {$viaje->escuela->nombre}",
                    'descripcion' => "Ruta generada automáticamente con k-Means el " . now()->format('d/m/Y H:i'),
                    'viaje_id' => $viaje->id,
                    'escuela_id' => $viaje->escuela_id,
                    'distancia_total_km' => $rutaData['distancia_total_km'],
                    'tiempo_estimado_minutos' => $rutaData['tiempo_total_min'],
                    'estado' => 'activa',
                    'algoritmo_utilizado' => 'k-means-tsp',
                    'parametros_algoritmo' => json_encode($rutaData['parametros'] ?? []),
                    'fecha_generacion' => now()
                ]);
                
                Log::info("Ruta creada", ['ruta_id' => $ruta->id]);
                
                // Crear paradas de la ruta
                foreach ($rutaData['ruta_optimizada'] as $index => $parada) {
                    ParadaRuta::create([
                        'ruta_id' => $ruta->id,
                        'confirmacion_id' => $parada['confirmacion_id'],
                        'orden' => $index + 1,
                        'direccion' => $parada['direccion'],
                        'latitud' => $parada['latitud'],
                        'longitud' => $parada['longitud'],
                        'hora_estimada' => $parada['hora_estimada'],
                        'distancia_desde_anterior_km' => $parada['distancia_desde_anterior_km'] ?? 0,
                        'tiempo_desde_anterior_min' => $parada['tiempo_desde_anterior_min'] ?? 0,
                        'cluster_asignado' => $parada['cluster'] ?? null,
                        'estado' => 'pendiente'
                    ]);
                    
                    // Actualizar confirmación con orden y hora estimada
                    ConfirmacionViaje::where('id', $parada['confirmacion_id'])
                        ->update([
                            'orden_recogida' => $index + 1,
                            'hora_estimada_recogida' => $parada['hora_estimada']
                        ]);
                }
                
                Log::info("Paradas creadas", ['cantidad' => count($rutaData['ruta_optimizada'])]);
                
                // Actualizar viaje
                $viaje->marcarRutaGenerada($ruta->id);
                
                Log::info("Viaje actualizado a ruta_generada");
                
                DB::commit();
                
                return response()->json([
                    'success' => true,
                    'message' => 'Ruta recibida y procesada exitosamente',
                    'ruta' => $ruta->load('paradas')
                ], 200);
                
            } catch (\Exception $e) {
                Log::error("Error en transacción de ruta", ['error' => $e->getMessage()]);
                DB::rollBack();
                throw $e;
            }
            
        } catch (\Exception $e) {
            Log::error('Error al recibir ruta generada: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al procesar la ruta',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener ruta activa para un chofer
     */
    public function rutasChofer(Request $request)
    {
        try {
            // Con el guard chofer-sanctum, auth()->user() ya es un Chofer
            $chofer = auth('chofer-sanctum')->user();
            
            if (!$chofer) {
                return response()->json([
                    'error' => 'Chofer no autenticado'
                ], 401);
            }
            
            // Obtener viajes del chofer con ruta lista
            $viajes = Viaje::with([
                'escuela',
                'unidad',
                'ruta.paradas' => function($query) {
                    $query->orderBy('orden');
                },
                'ruta.paradas.confirmacion.hijo'
            ])
            ->where('chofer_id', $chofer->id)
            ->whereIn('estado', ['ruta_generada', 'en_curso'])
            ->orderByDesc('created_at')
            ->get();
            
            return response()->json($viajes, 200);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener rutas de chofer: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener rutas',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Iniciar ruta (Chofer)
     * Regenera el polyline desde la ubicación GPS actual del chofer
     */
    public function iniciarRuta(Request $request, $rutaId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'latitud' => 'required|numeric|between:-90,90',
                'longitud' => 'required|numeric|between:-180,180'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Se requiere la ubicación GPS actual',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $ruta = Ruta::with(['viaje.escuela', 'paradas'])->findOrFail($rutaId);
            
            // Validar que el chofer tenga permisos
            $chofer = auth('chofer-sanctum')->user();
            
            if (!$chofer || $ruta->viaje->chofer_id !== $chofer->id) {
                return response()->json([
                    'error' => 'No tienes permisos para iniciar esta ruta'
                ], 403);
            }
            
            // Si ya está en progreso, regenerar polyline de todas formas
            DB::beginTransaction();
            
            try {
                if ($ruta->estado !== 'en_progreso') {
                    $ruta->iniciar();
                    $ruta->viaje->iniciar();
                }
                
                // 🔄 REGENERAR POLYLINE desde GPS actual
                $gpsActual = [
                    'lat' => floatval($request->latitud),
                    'lng' => floatval($request->longitud)
                ];
                
                $escuela = [
                    'lat' => floatval($ruta->viaje->escuela->latitud),
                    'lng' => floatval($ruta->viaje->escuela->longitud)
                ];
                
                // Obtener paradas pendientes (no completadas)
                $paradasPendientes = $ruta->paradas()
                    ->where('estado', '!=', 'completada')
                    ->orderBy('orden')
                    ->get()
                    ->toArray();
                
                if (!empty($paradasPendientes)) {
                    $optimizacionService = new \App\Services\RutaOptimizacionService();
                    $nuevoPolyline = $optimizacionService->regenerarPolylineDesdeGPS(
                        $gpsActual,
                        $paradasPendientes,
                        $escuela
                    );
                    
                    $ruta->polyline = $nuevoPolyline;
                    $ruta->save();
                    
                    Log::info('🗺️ Polyline regenerado al iniciar ruta', [
                        'ruta_id' => $ruta->id,
                        'polyline_length' => strlen($nuevoPolyline),
                        'paradas_pendientes' => count($paradasPendientes)
                    ]);
                }
                
                DB::commit();
                
                return response()->json([
                    'message' => 'Ruta iniciada exitosamente',
                    'ruta' => $ruta->load('paradas'),
                    'polyline_regenerado' => true
                ], 200);
                
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Ruta no encontrada'], 404);
        } catch (\Exception $e) {
            Log::error('Error al iniciar ruta: ' . $e->getMessage());
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Marcar llegada a parada (Chofer)
     */
    public function llegarAParada(Request $request, $paradaId)
    {
        try {
            $parada = ParadaRuta::with(['ruta.viaje'])->findOrFail($paradaId);
            
            // Validar permisos
            $chofer = auth('chofer-sanctum')->user();
            
            if (!$chofer || $parada->ruta->viaje->chofer_id !== $chofer->id) {
                return response()->json([
                    'error' => 'No tienes permisos para esta parada'
                ], 403);
            }
            
            $parada->marcarEnCamino();
            
            return response()->json([
                'message' => 'Parada marcada como en camino',
                'parada' => $parada
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Completar parada (Chofer)
     * Sistema robusto con idempotencia y manejo de errores
     */
    public function completarParada(Request $request, $rutaId, $paradaId)
    {
        try {
            $parada = ParadaRuta::with(['ruta.viaje', 'confirmacion'])->findOrFail($paradaId);
            
            // Validar permisos
            $chofer = auth('chofer-sanctum')->user();
            
            if (!$chofer || $parada->ruta->viaje->chofer_id !== $chofer->id) {
                return response()->json([
                    'error' => 'No tienes permisos para esta parada'
                ], 403);
            }
            
            // Validar que la parada pertenece a la ruta
            if ($parada->ruta_id !== (int)$rutaId) {
                return response()->json([
                    'error' => 'La parada no pertenece a esta ruta'
                ], 422);
            }
            
            // IDEMPOTENCIA: Si ya está completada, retornar éxito
            if ($parada->estado === 'completada') {
                $siguienteParada = ParadaRuta::where('ruta_id', $parada->ruta_id)
                    ->where('orden', $parada->orden + 1)
                    ->first();
                
                return response()->json([
                    'message' => 'Parada ya completada',
                    'parada' => $parada,
                    'siguiente_parada' => $siguienteParada,
                    'ya_completada' => true
                ], 200);
            }
            
            DB::beginTransaction();
            
            try {
                // Marcar parada como completada
                $parada->estado = 'completada';
                $parada->save();
                
                // Registrar asistencia del hijo (verificar que no exista ya)
                if ($parada->confirmacion) {
                    $asistenciaExistente = Asistencia::where([
                        'hijo_id' => $parada->confirmacion->hijo_id,
                        'viaje_id' => $parada->ruta->viaje_id,
                        'parada_id' => $parada->id
                    ])->first();
                    
                    if (!$asistenciaExistente) {
                        Asistencia::create([
                            'hijo_id' => $parada->confirmacion->hijo_id,
                            'viaje_id' => $parada->ruta->viaje_id,
                            'parada_id' => $parada->id,
                            'estado' => 'presente',
                            'hora_registro' => now(),
                            'metodo_registro' => 'chofer'
                        ]);
                    }
                }
                
                // Verificar si hay una siguiente parada y marcarla como "en_camino"
                $siguienteParada = ParadaRuta::where('ruta_id', $parada->ruta_id)
                    ->where('orden', $parada->orden + 1)
                    ->first();
                
                if ($siguienteParada) {
                    $siguienteParada->estado = 'en_camino';
                    $siguienteParada->save();
                }
                
                // 🔄 REGENERAR POLYLINE si hay GPS y paradas pendientes
                if ($request->has('latitud') && $request->has('longitud')) {
                    $gpsActual = [
                        'lat' => floatval($request->latitud),
                        'lng' => floatval($request->longitud)
                    ];
                    
                    $escuela = [
                        'lat' => floatval($parada->ruta->viaje->escuela->latitud),
                        'lng' => floatval($parada->ruta->viaje->escuela->longitud)
                    ];
                    
                    // Obtener paradas pendientes
                    $paradasPendientes = $parada->ruta->paradas()
                        ->where('estado', '!=', 'completada')
                        ->orderBy('orden')
                        ->get()
                        ->toArray();
                    
                    if (!empty($paradasPendientes)) {
                        $optimizacionService = new \App\Services\RutaOptimizacionService();
                        $nuevoPolyline = $optimizacionService->regenerarPolylineDesdeGPS(
                            $gpsActual,
                            $paradasPendientes,
                            $escuela
                        );
                        
                        $parada->ruta->polyline = $nuevoPolyline;
                        $parada->ruta->save();
                        
                        Log::info('🗺️ Polyline regenerado después de completar parada', [
                            'ruta_id' => $parada->ruta_id,
                            'parada_completada' => $parada->id,
                            'paradas_restantes' => count($paradasPendientes)
                        ]);
                    }
                }
                
                DB::commit();
                
                return response()->json([
                    'message' => 'Parada completada exitosamente',
                    'parada' => $parada,
                    'siguiente_parada' => $siguienteParada,
                    'polyline_regenerado' => $request->has('latitud')
                ], 200);
                
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
            
        } catch (\Exception $e) {
            Log::error('Error al completar parada: ' . $e->getMessage());
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Completar ruta (Chofer)
     */
    public function completarRuta(Request $request, $rutaId)
    {
        try {
            $ruta = Ruta::with('viaje')->findOrFail($rutaId);
            
            // Validar permisos
            $chofer = auth('chofer-sanctum')->user();
            
            if (!$chofer || $ruta->viaje->chofer_id !== $chofer->id) {
                return response()->json([
                    'error' => 'No tienes permisos para completar esta ruta'
                ], 403);
            }
            
            DB::beginTransaction();
            
            try {
                $ruta->completar();
                $ruta->viaje->finalizar();
                
                DB::commit();
                
                return response()->json([
                    'message' => 'Ruta completada exitosamente',
                    'ruta' => $ruta
                ], 200);
                
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
            
        } catch (\Exception $e) {
            Log::error('Error al completar ruta: ' . $e->getMessage());
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
