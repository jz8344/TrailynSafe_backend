<?php

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\ParadaRuta;
use App\Models\Hijo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AsistenciaController extends Controller
{
    /**
     * Registrar asistencia (Chofer escanea QR)
     */
    public function registrar(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'parada_ruta_id' => 'required|exists:paradas_ruta,id',
                'codigo_qr' => 'required|string',
                'latitud' => 'nullable|numeric|between:-90,90',
                'longitud' => 'nullable|numeric|between:-180,180',
                'observaciones' => 'nullable|string'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $parada = ParadaRuta::with([
                'ruta.viaje',
                'confirmacion.hijo'
            ])->findOrFail($request->parada_ruta_id);
            
            // Validar que el chofer tenga permisos
            $user = auth()->user();
            $chofer = $user->chofer;
            
            if (!$chofer || $parada->ruta->viaje->chofer_id !== $chofer->id) {
                return response()->json([
                    'error' => 'No tienes permisos para registrar asistencia en esta parada'
                ], 403);
            }
            
            // Validar que la parada esté en camino
            if ($parada->estado !== 'en_camino') {
                return response()->json([
                    'error' => 'La parada debe estar marcada como "en camino" para registrar asistencia'
                ], 422);
            }
            
            // Obtener hijo desde la confirmación
            $hijo = $parada->confirmacion->hijo;
            
            // Validar que el código QR corresponda al hijo
            if ($hijo->codigo_qr !== $request->codigo_qr) {
                return response()->json([
                    'error' => 'El código QR no corresponde al hijo de esta parada',
                    'esperado' => $hijo->nombre,
                    'qr_esperado' => $hijo->codigo_qr
                ], 422);
            }
            
            // Validar que no exista ya una asistencia
            $asistenciaExistente = Asistencia::where('viaje_id', $parada->ruta->viaje->id)
                ->where('hijo_id', $hijo->id)
                ->first();
            
            if ($asistenciaExistente) {
                return response()->json([
                    'error' => 'Ya se registró asistencia para este hijo en este viaje'
                ], 422);
            }
            
            DB::beginTransaction();
            
            try {
                // Crear asistencia
                $asistencia = Asistencia::create([
                    'parada_ruta_id' => $parada->id,
                    'hijo_id' => $hijo->id,
                    'viaje_id' => $parada->ruta->viaje->id,
                    'codigo_qr_escaneado' => $request->codigo_qr,
                    'hora_escaneo' => now(),
                    'estado' => 'presente',
                    'latitud_escaneo' => $request->latitud,
                    'longitud_escaneo' => $request->longitud,
                    'observaciones' => $request->observaciones
                ]);
                
                // Marcar parada como completada
                $parada->completar();
                
                DB::commit();
                
                $asistencia->load(['hijo', 'paradaRuta']);
                
                // Validar ubicación si se proporcionaron coordenadas
                $ubicacionValida = null;
                if ($request->latitud && $request->longitud) {
                    $ubicacionValida = $asistencia->estaEnUbicacionValida(0.5); // 500 metros de tolerancia
                    
                    if (!$ubicacionValida) {
                        Log::warning('Asistencia registrada fuera de ubicación', [
                            'asistencia_id' => $asistencia->id,
                            'distancia_km' => $asistencia->distanciaAParada()
                        ]);
                    }
                }
                
                return response()->json([
                    'message' => 'Asistencia registrada exitosamente',
                    'asistencia' => $asistencia,
                    'hijo' => $hijo,
                    'ubicacion_valida' => $ubicacionValida
                ], 201);
                
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Parada no encontrada'], 404);
        } catch (\Exception $e) {
            Log::error('Error al registrar asistencia: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al registrar asistencia',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marcar ausencia (Chofer)
     */
    public function marcarAusente(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'parada_ruta_id' => 'required|exists:paradas_ruta,id',
                'motivo' => 'nullable|string|max:500'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $parada = ParadaRuta::with([
                'ruta.viaje',
                'confirmacion.hijo'
            ])->findOrFail($request->parada_ruta_id);
            
            // Validar permisos
            $user = auth()->user();
            $chofer = $user->chofer;
            
            if (!$chofer || $parada->ruta->viaje->chofer_id !== $chofer->id) {
                return response()->json([
                    'error' => 'No tienes permisos para registrar ausencia'
                ], 403);
            }
            
            $hijo = $parada->confirmacion->hijo;
            
            DB::beginTransaction();
            
            try {
                // Crear registro de ausencia
                $asistencia = Asistencia::create([
                    'parada_ruta_id' => $parada->id,
                    'hijo_id' => $hijo->id,
                    'viaje_id' => $parada->ruta->viaje->id,
                    'codigo_qr_escaneado' => '',
                    'hora_escaneo' => now(),
                    'estado' => 'ausente',
                    'observaciones' => $request->motivo
                ]);
                
                // Marcar parada como omitida
                $parada->omitir();
                
                DB::commit();
                
                return response()->json([
                    'message' => 'Ausencia registrada',
                    'asistencia' => $asistencia
                ], 201);
                
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
            
        } catch (\Exception $e) {
            Log::error('Error al marcar ausente: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al registrar ausencia',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar asistencias de un viaje (Admin)
     */
    public function porViaje($viajeId)
    {
        try {
            $asistencias = Asistencia::with([
                'hijo',
                'paradaRuta',
                'viaje'
            ])
            ->where('viaje_id', $viajeId)
            ->orderBy('hora_escaneo')
            ->get();
            
            return response()->json($asistencias, 200);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener asistencias: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener asistencias',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Historial de asistencias de un hijo (Usuario/Padre)
     */
    public function historialHijo(Request $request, $hijoId)
    {
        try {
            $user = auth()->user();
            $hijo = Hijo::findOrFail($hijoId);
            
            // Validar que el hijo pertenezca al usuario
            if ($hijo->padre_id !== $user->id) {
                return response()->json([
                    'error' => 'No tienes permisos para ver este historial'
                ], 403);
            }
            
            $asistencias = Asistencia::with([
                'viaje.escuela',
                'paradaRuta'
            ])
            ->where('hijo_id', $hijoId)
            ->orderByDesc('hora_escaneo')
            ->limit(50)
            ->get();
            
            // Estadísticas
            $total = $asistencias->count();
            $presentes = $asistencias->where('estado', 'presente')->count();
            $ausentes = $asistencias->where('estado', 'ausente')->count();
            $justificados = $asistencias->where('estado', 'justificado')->count();
            
            return response()->json([
                'asistencias' => $asistencias,
                'estadisticas' => [
                    'total' => $total,
                    'presentes' => $presentes,
                    'ausentes' => $ausentes,
                    'justificados' => $justificados,
                    'porcentaje_asistencia' => $total > 0 ? round(($presentes / $total) * 100, 2) : 0
                ]
            ], 200);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Hijo no encontrado'], 404);
        } catch (\Exception $e) {
            Log::error('Error al obtener historial: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener historial',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
