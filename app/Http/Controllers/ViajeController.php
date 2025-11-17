<?php

namespace App\Http\Controllers;

use App\Models\Viaje;
use App\Models\Escuela;
use App\Models\Chofer;
use App\Models\Unidad;
use App\Models\ConfirmacionViaje;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ViajeController extends Controller
{
    /**
     * Listar todos los viajes con sus relaciones
     */
    public function index(Request $request)
    {
        try {
            $query = Viaje::with(['escuela', 'chofer', 'unidad', 'confirmaciones']);

            // Filtros opcionales
            if ($request->has('escuela_id')) {
                $query->where('escuela_id', $request->escuela_id);
            }

            if ($request->has('fecha_viaje')) {
                $query->whereDate('fecha_viaje', $request->fecha_viaje);
            }

            if ($request->has('estado')) {
                $query->where('estado', $request->estado);
            }

            if ($request->has('chofer_id')) {
                $query->where('chofer_id', $request->chofer_id);
            }

            $viajes = $query->orderBy('fecha_viaje', 'desc')
                ->orderBy('hora_inicio_viaje', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $viajes
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener viajes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear un nuevo viaje
     */
    public function store(Request $request)
    {
        // Log para debug
        \Log::info('Creating viaje with data:', $request->all());
        
        $validator = Validator::make($request->all(), [
            'nombre_ruta' => 'required|string|max:255',
            'escuela_id' => 'required|exists:escuelas,id',
            'chofer_id' => 'nullable|exists:choferes,id',
            'unidad_id' => 'nullable|exists:unidades,id',
            'hora_inicio_confirmacion' => 'required|date_format:H:i:s',
            'hora_fin_confirmacion' => 'required|date_format:H:i:s',
            'hora_inicio_viaje' => 'required|date_format:H:i:s',
            'hora_llegada_estimada' => 'required|date_format:H:i:s',
            'fecha_viaje' => 'required|date|after_or_equal:yesterday',
            'notas' => 'nullable|string',
            'capacidad_maxima' => 'nullable|integer|min:1'
        ]);

        if ($validator->fails()) {
            \Log::error('Validation failed for viaje:', [
                'errors' => $validator->errors(),
                'data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
                'message' => 'Error de validación: ' . $validator->errors()->first()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $viaje = Viaje::create([
                'nombre_ruta' => $request->nombre_ruta,
                'escuela_id' => $request->escuela_id,
                'chofer_id' => $request->chofer_id,
                'unidad_id' => $request->unidad_id,
                'hora_inicio_confirmacion' => $request->hora_inicio_confirmacion,
                'hora_fin_confirmacion' => $request->hora_fin_confirmacion,
                'hora_inicio_viaje' => $request->hora_inicio_viaje,
                'hora_llegada_estimada' => $request->hora_llegada_estimada,
                'fecha_viaje' => $request->fecha_viaje,
                'notas' => $request->notas,
                'capacidad_maxima' => $request->capacidad_maxima ?? 30,
                'estado' => 'pendiente'
            ]);

            DB::commit();

            $viaje->load(['escuela', 'chofer', 'unidad']);

            return response()->json([
                'success' => true,
                'message' => 'Viaje creado exitosamente',
                'data' => $viaje
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear viaje: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar un viaje específico
     */
    public function show($id)
    {
        try {
            $viaje = Viaje::with([
                'escuela',
                'chofer',
                'unidad',
                'confirmaciones.hijo.usuario',
                'ubicaciones' => function($query) {
                    $query->orderBy('timestamp_gps', 'desc')->limit(10);
                }
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $viaje
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Viaje no encontrado'
            ], 404);
        }
    }

    /**
     * Actualizar un viaje
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'nombre_ruta' => 'sometimes|string|max:255',
            'escuela_id' => 'sometimes|exists:escuelas,id',
            'chofer_id' => 'nullable|exists:choferes,id',
            'unidad_id' => 'nullable|exists:unidades,id',
            'hora_inicio_confirmacion' => 'sometimes|date_format:H:i:s',
            'hora_fin_confirmacion' => 'sometimes|date_format:H:i:s',
            'hora_inicio_viaje' => 'sometimes|date_format:H:i:s',
            'hora_llegada_estimada' => 'sometimes|date_format:H:i:s',
            'fecha_viaje' => 'sometimes|date',
            'estado' => 'sometimes|in:pendiente,confirmaciones_abiertas,confirmaciones_cerradas,en_curso,completado,cancelado',
            'notas' => 'nullable|string',
            'capacidad_maxima' => 'nullable|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $viaje = Viaje::findOrFail($id);

            // No permitir editar viajes en curso o completados
            if (in_array($viaje->estado, ['en_curso', 'completado'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede editar un viaje en curso o completado'
                ], 403);
            }

            $viaje->update($request->only([
                'nombre_ruta',
                'escuela_id',
                'chofer_id',
                'unidad_id',
                'hora_inicio_confirmacion',
                'hora_fin_confirmacion',
                'hora_inicio_viaje',
                'hora_llegada_estimada',
                'fecha_viaje',
                'estado',
                'notas',
                'capacidad_maxima'
            ]));

            $viaje->load(['escuela', 'chofer', 'unidad']);

            return response()->json([
                'success' => true,
                'message' => 'Viaje actualizado exitosamente',
                'data' => $viaje
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar viaje: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un viaje
     */
    public function destroy($id)
    {
        try {
            $viaje = Viaje::findOrFail($id);

            // No permitir eliminar viajes en curso o completados
            if (in_array($viaje->estado, ['en_curso', 'completado'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar un viaje en curso o completado'
                ], 403);
            }

            $viaje->delete();

            return response()->json([
                'success' => true,
                'message' => 'Viaje eliminado exitosamente'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar viaje: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener viajes de hoy
     */
    public function viajesHoy()
    {
        try {
            $viajes = Viaje::with(['escuela', 'chofer', 'unidad', 'confirmaciones'])
                ->whereDate('fecha_viaje', today())
                ->orderBy('hora_inicio_viaje', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $viajes
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener viajes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Abrir periodo de confirmaciones (cambiar estado)
     */
    public function abrirConfirmaciones($id)
    {
        try {
            $viaje = Viaje::findOrFail($id);

            if ($viaje->estado !== 'pendiente') {
                return response()->json([
                    'success' => false,
                    'message' => 'El viaje debe estar en estado pendiente'
                ], 403);
            }

            $viaje->update(['estado' => 'confirmaciones_abiertas']);

            return response()->json([
                'success' => true,
                'message' => 'Periodo de confirmaciones abierto',
                'data' => $viaje
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cerrar periodo de confirmaciones
     */
    public function cerrarConfirmaciones($id)
    {
        try {
            $viaje = Viaje::findOrFail($id);

            if ($viaje->estado !== 'confirmaciones_abiertas') {
                return response()->json([
                    'success' => false,
                    'message' => 'El viaje debe tener confirmaciones abiertas'
                ], 403);
            }

            // Actualizar contador de niños confirmados
            $confirmados = $viaje->confirmaciones()->where('estado', 'confirmado')->count();
            
            $viaje->update([
                'estado' => 'confirmaciones_cerradas',
                'ninos_confirmados' => $confirmados
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Periodo de confirmaciones cerrado',
                'data' => $viaje
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener confirmaciones de un viaje
     */
    public function confirmaciones($id)
    {
        try {
            $viaje = Viaje::with(['confirmaciones.hijo.usuario', 'confirmaciones.hijo.escuela'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $viaje->confirmaciones
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener confirmaciones: ' . $e->getMessage()
            ], 500);
        }
    }
}
