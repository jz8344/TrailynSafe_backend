<?php

namespace App\Http\Controllers;

use App\Models\Viaje;
use App\Models\ConfirmacionViaje;
use App\Models\Hijo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConfirmacionController extends Controller
{
    /**
     * Listar confirmaciones de un viaje (Admin)
     */
    public function index(Request $request, $viajeId)
    {
        try {
            $viaje = Viaje::findOrFail($viajeId);
            
            $confirmaciones = ConfirmacionViaje::with(['hijo', 'padre', 'parada'])
                ->where('viaje_id', $viajeId)
                ->orderBy('created_at', 'desc')
                ->get();
            
            return response()->json([
                'viaje' => $viaje,
                'confirmaciones' => $confirmaciones
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Error al listar confirmaciones: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener confirmaciones',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Confirmar asistencia a un viaje (Usuario/Padre)
     */
    public function confirmar(Request $request, $viajeId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'hijo_id' => 'required|exists:hijos,id',
                'direccion_recogida' => 'required|string',
                'referencia' => 'nullable|string|max:500',
                'latitud' => 'required|numeric|between:-90,90',
                'longitud' => 'required|numeric|between:-180,180',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $user = auth()->user();
            $viaje = Viaje::findOrFail($viajeId);
            $hijo = Hijo::findOrFail($request->hijo_id);
            
            // VALIDACIÓN CRÍTICA: Solo permitir confirmaciones si el viaje está en 'en_confirmaciones'
            if ($viaje->estado !== 'en_confirmaciones') {
                Log::warning("Intento de confirmar viaje {$viaje->id} en estado '{$viaje->estado}' (debe estar en 'en_confirmaciones')");
                return response()->json([
                    'error' => 'Este viaje no está aceptando confirmaciones actualmente',
                    'estado_actual' => $viaje->estado,
                    'mensaje' => 'El viaje debe estar en estado "En Confirmaciones" para poder confirmar tu participación'
                ], 400);
            }
            
            // Validar que el hijo pertenezca al usuario
            if ($hijo->padre_id !== $user->id) {
                return response()->json([
                    'error' => 'No tienes permisos para confirmar este hijo'
                ], 403);
            }
            
            // Validar que el viaje esté en confirmaciones
            if (!$viaje->puedeConfirmar()) {
                return response()->json([
                    'error' => 'Este viaje no está disponible para confirmaciones o está lleno'
                ], 422);
            }
            
            // Validar que no exista ya una confirmación activa
            $confirmacionExistente = ConfirmacionViaje::where('viaje_id', $viajeId)
                ->where('hijo_id', $hijo->id)
                ->where('estado', 'confirmado')
                ->first();
            
            if ($confirmacionExistente) {
                return response()->json([
                    'error' => 'Ya existe una confirmación activa para este hijo en este viaje'
                ], 422);
            }
            
            // Crear confirmación
            DB::beginTransaction();
            
            try {
                $confirmacion = ConfirmacionViaje::create([
                    'viaje_id' => $viajeId,
                    'hijo_id' => $hijo->id,
                    'padre_id' => $user->id,
                    'direccion_recogida' => $request->direccion_recogida,
                    'referencia' => $request->referencia,
                    'latitud' => $request->latitud,
                    'longitud' => $request->longitud,
                    'hora_confirmacion' => now(),
                    'estado' => 'confirmado'
                ]);
                
                // Incrementar contador de confirmaciones
                $viaje->agregarConfirmacion();
                
                DB::commit();
                
                $confirmacion->load(['hijo', 'padre', 'viaje']);
                
                return response()->json([
                    'message' => 'Confirmación registrada exitosamente',
                    'confirmacion' => $confirmacion
                ], 201);
                
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Viaje o hijo no encontrado'], 404);
        } catch (\Exception $e) {
            Log::error('Error al confirmar asistencia: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al registrar confirmación',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancelar una confirmación (Usuario/Padre)
     */
    public function cancelar(Request $request, $confirmacionId)
    {
        try {
            $user = auth()->user();
            $confirmacion = ConfirmacionViaje::with(['viaje'])->findOrFail($confirmacionId);
            
            // Validar que la confirmación pertenezca al usuario
            if ($confirmacion->padre_id !== $user->id) {
                return response()->json([
                    'error' => 'No tienes permisos para cancelar esta confirmación'
                ], 403);
            }
            
            // Validar que el viaje aún esté en confirmaciones
            if ($confirmacion->viaje->estado !== 'en_confirmaciones') {
                return response()->json([
                    'error' => 'No se puede cancelar la confirmación, el viaje ya no está en periodo de confirmaciones'
                ], 422);
            }
            
            DB::beginTransaction();
            
            try {
                $confirmacion->cancelar();
                DB::commit();
                
                return response()->json([
                    'message' => 'Confirmación cancelada exitosamente'
                ], 200);
                
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Confirmación no encontrada'], 404);
        } catch (\Exception $e) {
            Log::error('Error al cancelar confirmación: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al cancelar confirmación',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mis confirmaciones (Usuario/Padre)
     */
    public function misConfirmaciones(Request $request)
    {
        try {
            $user = auth()->user();
            
            $confirmaciones = ConfirmacionViaje::with([
                'viaje.escuela',
                'viaje.unidad',
                'viaje.chofer',
                'hijo',
                'parada'
            ])
            ->where('padre_id', $user->id)
            ->where('estado', 'confirmado')
            ->orderBy('created_at', 'desc')
            ->get();
            
            return response()->json($confirmaciones, 200);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener mis confirmaciones: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener confirmaciones',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar dirección de una confirmación (Usuario/Padre)
     * Solo si el viaje aún está en confirmaciones
     */
    public function actualizarDireccion(Request $request, $confirmacionId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'direccion_recogida' => 'required|string',
                'referencia' => 'nullable|string|max:500',
                'latitud' => 'required|numeric|between:-90,90',
                'longitud' => 'required|numeric|between:-180,180',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $user = auth()->user();
            $confirmacion = ConfirmacionViaje::with(['viaje'])->findOrFail($confirmacionId);
            
            // Validar que la confirmación pertenezca al usuario
            if ($confirmacion->padre_id !== $user->id) {
                return response()->json([
                    'error' => 'No tienes permisos para modificar esta confirmación'
                ], 403);
            }
            
            // Validar que el viaje aún esté en confirmaciones
            if ($confirmacion->viaje->estado !== 'en_confirmaciones') {
                return response()->json([
                    'error' => 'No se puede modificar la dirección, el viaje ya no está en periodo de confirmaciones'
                ], 422);
            }
            
            $confirmacion->update([
                'direccion_recogida' => $request->direccion_recogida,
                'referencia' => $request->referencia,
                'latitud' => $request->latitud,
                'longitud' => $request->longitud,
            ]);
            
            return response()->json([
                'message' => 'Dirección actualizada exitosamente',
                'confirmacion' => $confirmacion
            ], 200);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Confirmación no encontrada'], 404);
        } catch (\Exception $e) {
            Log::error('Error al actualizar dirección: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al actualizar dirección',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
