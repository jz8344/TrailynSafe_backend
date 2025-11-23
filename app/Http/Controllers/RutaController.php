<?php

namespace App\Http\Controllers;

use App\Models\Ruta;
use App\Models\Viaje;
use App\Models\ParadaRuta;
use App\Models\ConfirmacionViaje;
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
            $validator = Validator::make($request->all(), [
                'viaje_id' => 'required|exists:viajes,id',
                'ruta_optimizada' => 'required|array',
                'ruta_optimizada.*.confirmacion_id' => 'required|exists:confirmaciones_viaje,id',
                'ruta_optimizada.*.direccion' => 'required|string',
                'ruta_optimizada.*.latitud' => 'required|numeric',
                'ruta_optimizada.*.longitud' => 'required|numeric',
                'ruta_optimizada.*.hora_estimada' => 'required|string',
                'ruta_optimizada.*.distancia_desde_anterior_km' => 'nullable|numeric',
                'ruta_optimizada.*.tiempo_desde_anterior_min' => 'nullable|integer',
                'ruta_optimizada.*.cluster' => 'nullable|integer',
                'distancia_total_km' => 'required|numeric',
                'tiempo_total_min' => 'required|integer',
                'parametros' => 'nullable|array'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $viaje = Viaje::findOrFail($request->viaje_id);
            
            // Validar que el viaje esté generando ruta
            if ($viaje->estado !== 'generando_ruta') {
                return response()->json([
                    'error' => 'El viaje no está en estado de generación de ruta'
                ], 422);
            }
            
            DB::beginTransaction();
            
            try {
                // Crear registro de ruta
                $ruta = Ruta::create([
                    'nombre' => "Ruta Viaje #{$viaje->id} - {$viaje->escuela->nombre}",
                    'descripcion' => "Ruta generada automáticamente el " . now()->format('d/m/Y H:i'),
                    'viaje_id' => $viaje->id,
                    'escuela_id' => $viaje->escuela_id,
                    'distancia_total_km' => $request->distancia_total_km,
                    'tiempo_estimado_minutos' => $request->tiempo_total_min,
                    'estado' => 'activa',
                    'algoritmo_utilizado' => 'k-means-clustering',
                    'parametros_algoritmo' => json_encode($request->parametros ?? []),
                    'fecha_generacion' => now()
                ]);
                
                // Crear paradas de la ruta
                foreach ($request->ruta_optimizada as $index => $parada) {
                    $paradaCreada = ParadaRuta::create([
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
                
                // Actualizar viaje
                $viaje->marcarRutaGenerada($ruta->id);
                
                DB::commit();
                
                return response()->json([
                    'success' => true,
                    'message' => 'Ruta recibida y procesada exitosamente',
                    'ruta' => $ruta->load('paradas')
                ], 200);
                
            } catch (\Exception $e) {
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
            $user = auth()->user();
            
            // Obtener chofer del usuario
            $chofer = $user->chofer;
            
            if (!$chofer) {
                return response()->json([
                    'error' => 'Usuario no es un chofer registrado'
                ], 403);
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
     */
    public function iniciarRuta(Request $request, $rutaId)
    {
        try {
            $ruta = Ruta::with('viaje')->findOrFail($rutaId);
            
            // Validar que el chofer tenga permisos
            $user = auth()->user();
            $chofer = $user->chofer;
            
            if (!$chofer || $ruta->viaje->chofer_id !== $chofer->id) {
                return response()->json([
                    'error' => 'No tienes permisos para iniciar esta ruta'
                ], 403);
            }
            
            DB::beginTransaction();
            
            try {
                $ruta->iniciar();
                $ruta->viaje->iniciar();
                
                DB::commit();
                
                return response()->json([
                    'message' => 'Ruta iniciada exitosamente',
                    'ruta' => $ruta->load('paradas')
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
            $user = auth()->user();
            $chofer = $user->chofer;
            
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
     * Completar ruta (Chofer)
     */
    public function completarRuta(Request $request, $rutaId)
    {
        try {
            $ruta = Ruta::with('viaje')->findOrFail($rutaId);
            
            // Validar permisos
            $user = auth()->user();
            $chofer = $user->chofer;
            
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
