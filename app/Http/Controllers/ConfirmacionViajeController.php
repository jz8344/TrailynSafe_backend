<?php

namespace App\Http\Controllers;

use App\Models\ConfirmacionViaje;
use App\Models\Viaje;
use App\Models\Hijo;
use App\Models\Escuela;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ConfirmacionViajeController extends Controller
{
    /**
     * Obtener viajes disponibles para un usuario (padre)
     * Basado en las escuelas de sus hijos
     */
    public function viajesDisponibles(Request $request)
    {
        try {
            $usuario = Auth::guard('sanctum')->user();
            
            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            // Obtener los hijos del usuario
            $hijos = Hijo::where('padre_id', $usuario->id)->get();
            
            if ($hijos->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ], 200);
            }
            
            // Recopilar IDs de escuelas
            $escuelaIds = collect();
            
            foreach ($hijos as $hijo) {
                if ($hijo->escuela_id) {
                    $escuelaIds->push($hijo->escuela_id);
                }
            }
            
            $escuelaIds = $escuelaIds->filter()->unique()->values();

            if ($escuelaIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ], 200);
            }

            // Obtener viajes activos para esas escuelas
            $viajes = Viaje::whereIn('escuela_id', $escuelaIds)
                ->whereIn('estado', ['confirmaciones_abiertas', 'confirmaciones_cerradas', 'en_curso'])
                ->whereDate('fecha_viaje', '>=', now()->subDays(7))
                ->orderBy('fecha_viaje', 'asc')
                ->orderBy('hora_inicio_viaje', 'asc')
                ->get();

            // Agregar información de confirmación para cada hijo
            $viajesConEstado = $viajes->map(function($viaje) use ($hijos) {
                $viaje->hijos_confirmados = [];
                
                foreach ($hijos as $hijo) {
                    if ($hijo->escuela_id == $viaje->escuela_id) {
                        $confirmacion = ConfirmacionViaje::where('viaje_id', $viaje->id)
                            ->where('hijo_id', $hijo->id)
                            ->first();

                        $viaje->hijos_confirmados[] = [
                            'hijo_id' => $hijo->id,
                            'hijo_nombre' => $hijo->nombre,
                            'confirmado' => $confirmacion ? true : false,
                            'estado' => $confirmacion ? $confirmacion->estado : null,
                            'puede_confirmar' => $viaje->estado === 'confirmaciones_abiertas' && !$confirmacion
                        ];
                    }
                }
                
                return $viaje;
            });

            return response()->json([
                'success' => true,
                'data' => $viajesConEstado
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener viajes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Confirmar participación en un viaje (agregar coordenadas)
     */
    public function confirmarViaje(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'viaje_id' => 'required|exists:viajes,id',
            'hijo_id' => 'required|exists:hijos,id',
            'latitud' => 'required|numeric|between:-90,90',
            'longitud' => 'required|numeric|between:-180,180',
            'direccion_recogida' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $usuario = Auth::guard('sanctum')->user();

            // Verificar que el hijo pertenece al usuario
            $hijo = Hijo::where('id', $request->hijo_id)
                ->where('padre_id', $usuario->id)
                ->first();

            if (!$hijo) {
                return response()->json([
                    'success' => false,
                    'message' => 'El hijo no pertenece a este usuario'
                ], 403);
            }

            // Verificar que el viaje está en periodo de confirmación
            $viaje = Viaje::findOrFail($request->viaje_id);

            if ($viaje->estado !== 'confirmaciones_abiertas') {
                return response()->json([
                    'success' => false,
                    'message' => 'El periodo de confirmación no está abierto'
                ], 403);
            }

            // Verificar que la escuela del hijo coincide con la del viaje
            if ($hijo->escuela_id != $viaje->escuela_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'El hijo no pertenece a la escuela de este viaje'
                ], 403);
            }

            // Verificar que no exista ya una confirmación
            $confirmacionExistente = ConfirmacionViaje::where('viaje_id', $request->viaje_id)
                ->where('hijo_id', $request->hijo_id)
                ->first();

            if ($confirmacionExistente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe una confirmación para este viaje'
                ], 409);
            }

            // Verificar capacidad
            $confirmados = $viaje->confirmaciones()->where('estado', 'confirmado')->count();
            if ($confirmados >= $viaje->capacidad_maxima) {
                return response()->json([
                    'success' => false,
                    'message' => 'El viaje ha alcanzado su capacidad máxima'
                ], 403);
            }

            DB::beginTransaction();

            // Crear confirmación
            $confirmacion = ConfirmacionViaje::create([
                'viaje_id' => $request->viaje_id,
                'hijo_id' => $request->hijo_id,
                'usuario_id' => $usuario->id,
                'latitud' => $request->latitud,
                'longitud' => $request->longitud,
                'direccion_recogida' => $request->direccion_recogida,
                'estado' => 'confirmado'
            ]);

            // Actualizar contador del viaje
            $viaje->increment('ninos_confirmados');

            // Agregar coordenada al array de coordenadas del viaje
            $coordenadas = $viaje->coordenadas_recogida ?? [];
            $coordenadas[] = [
                'hijo_id' => $hijo->id,
                'confirmacion_id' => $confirmacion->id,
                'lat' => $request->latitud,
                'lng' => $request->longitud,
                'direccion' => $request->direccion_recogida,
                'nombre_hijo' => $hijo->nombre . ' ' . $hijo->apellidos
            ];
            $viaje->update(['coordenadas_recogida' => $coordenadas]);

            DB::commit();

            $confirmacion->load('hijo', 'viaje');

            return response()->json([
                'success' => true,
                'message' => 'Viaje confirmado exitosamente',
                'data' => $confirmacion
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al confirmar viaje: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancelar confirmación de viaje
     */
    public function cancelarConfirmacion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'viaje_id' => 'required|exists:viajes,id',
            'hijo_id' => 'required|exists:hijos,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $usuario = Auth::guard('sanctum')->user();

            // Verificar que el hijo pertenece al usuario
            $hijo = Hijo::where('id', $request->hijo_id)
                ->where('padre_id', $usuario->id)
                ->first();

            if (!$hijo) {
                return response()->json([
                    'success' => false,
                    'message' => 'El hijo no pertenece a este usuario'
                ], 403);
            }

            // Buscar confirmación
            $confirmacion = ConfirmacionViaje::where('viaje_id', $request->viaje_id)
                ->where('hijo_id', $request->hijo_id)
                ->first();

            if (!$confirmacion) {
                return response()->json([
                    'success' => false,
                    'message' => 'No existe confirmación para este viaje'
                ], 404);
            }

            $viaje = Viaje::findOrFail($request->viaje_id);

            // No permitir cancelar si el viaje ya está en curso
            if ($viaje->estado === 'en_curso' || $viaje->estado === 'completado') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede cancelar un viaje en curso o completado'
                ], 403);
            }

            DB::beginTransaction();

            // Cambiar estado de la confirmación
            $confirmacion->update(['estado' => 'cancelado']);

            // Actualizar contador del viaje
            $viaje->decrement('ninos_confirmados');

            // Remover coordenada del array
            $coordenadas = $viaje->coordenadas_recogida ?? [];
            $coordenadas = array_filter($coordenadas, function($coord) use ($hijo) {
                return $coord['hijo_id'] != $hijo->id;
            });
            $viaje->update(['coordenadas_recogida' => array_values($coordenadas)]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Confirmación cancelada exitosamente'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al cancelar confirmación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener historial de confirmaciones del usuario
     */
    public function misConfirmaciones()
    {
        try {
            $usuario = Auth::guard('sanctum')->user();

            $confirmaciones = ConfirmacionViaje::with(['viaje.escuela', 'hijo'])
                ->where('usuario_id', $usuario->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $confirmaciones
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener confirmaciones: ' . $e->getMessage()
            ], 500);
        }
    }
}
