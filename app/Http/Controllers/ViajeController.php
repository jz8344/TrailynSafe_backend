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
            'nombre_ruta' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9\s\-\/]+$/'],
            'escuela_id' => 'required|exists:escuelas,id',
            'turno' => 'required|in:matutino,vespertino',
            'chofer_id' => 'nullable|exists:choferes,id',
            'unidad_id' => 'nullable|exists:unidades,id',
            'capacidad_maxima' => 'required_without:unidad_id|integer|min:1',
            'hora_inicio_confirmacion' => 'required|date_format:H:i',
            'hora_fin_confirmacion' => 'required|date_format:H:i',
            'hora_inicio_viaje' => 'required|date_format:H:i',
            'hora_llegada_estimada' => 'required|date_format:H:i',
            'fecha_viaje' => 'required|date|after_or_equal:today',
            'dias_semana' => 'nullable|array',
            'dias_semana.*' => 'integer|between:0,6',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_viaje',
            'notas' => 'nullable|string',
            'confirmacion_automatica' => 'nullable|boolean',
            'crear_retorno' => 'nullable|boolean',
            'hora_inicio_confirmacion_retorno' => 'required_if:crear_retorno,true|date_format:H:i',
            'hora_fin_confirmacion_retorno' => 'required_if:crear_retorno,true|date_format:H:i',
            'hora_inicio_retorno' => 'required_if:crear_retorno,true|date_format:H:i',
            'hora_llegada_retorno' => 'required_if:crear_retorno,true|date_format:H:i'
        ], [
            'nombre_ruta.regex' => 'El nombre de la ruta solo puede contener letras, números, espacios, guiones (-) y diagonales (/).',
            'turno.required' => 'El turno es obligatorio',
            'turno.in' => 'El turno debe ser matutino o vespertino',
            'capacidad_maxima.required_without' => 'Debes seleccionar una unidad o ingresar la capacidad máxima'
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

            // Obtener capacidad de la unidad o usar la proporcionada
            $capacidadMaxima = $request->capacidad_maxima;
            if ($request->unidad_id) {
                $unidad = \App\Models\Unidad::find($request->unidad_id);
                if ($unidad && $unidad->numero_asientos) {
                    $capacidadMaxima = $unidad->numero_asientos;
                }
            }

            // Asegurar segundos en las horas
            $horaInicioConf = $request->hora_inicio_confirmacion . ':00';
            $horaFinConf = $request->hora_fin_confirmacion . ':00';
            $horaInicioViaje = $request->hora_inicio_viaje . ':00';
            $horaLlegada = $request->hora_llegada_estimada . ':00';

            // Crear viaje de ida
            $viaje = Viaje::create([
                'nombre_ruta' => $request->nombre_ruta,
                'escuela_id' => $request->escuela_id,
                'turno' => $request->turno,
                'tipo_viaje' => 'ida',
                'chofer_id' => $request->chofer_id,
                'unidad_id' => $request->unidad_id,
                'hora_inicio_confirmacion' => $horaInicioConf,
                'hora_fin_confirmacion' => $horaFinConf,
                'hora_inicio_viaje' => $horaInicioViaje,
                'hora_llegada_estimada' => $horaLlegada,
                'fecha_viaje' => $request->fecha_viaje,
                'dias_semana' => $request->dias_semana ?? null,
                'fecha_fin' => $request->fecha_fin ?? null,
                'notas' => $request->notas,
                'capacidad_maxima' => $capacidadMaxima,
                'confirmacion_automatica' => $request->confirmacion_automatica ?? false,
                'estado' => 'pendiente'
            ]);

            // Crear viaje de retorno si se solicitó
            $viajeRetorno = null;
            if ($request->crear_retorno) {
                $horaInicioConfRetorno = $request->hora_inicio_confirmacion_retorno ? $request->hora_inicio_confirmacion_retorno . ':00' : null;
                $horaFinConfRetorno = $request->hora_fin_confirmacion_retorno ? $request->hora_fin_confirmacion_retorno . ':00' : null;
                $horaInicioRetorno = $request->hora_inicio_retorno . ':00';
                $horaLlegadaRetorno = $request->hora_llegada_retorno . ':00';
                
                $viajeRetorno = Viaje::create([
                    'nombre_ruta' => $request->nombre_ruta . ' (Retorno)',
                    'escuela_id' => $request->escuela_id,
                    'turno' => $request->turno,
                    'tipo_viaje' => 'retorno',
                    'chofer_id' => $request->chofer_id,
                    'unidad_id' => $request->unidad_id,
                    'hora_inicio_confirmacion' => $horaInicioConfRetorno,
                    'hora_fin_confirmacion' => $horaFinConfRetorno,
                    'hora_inicio_viaje' => $horaInicioRetorno,
                    'hora_llegada_estimada' => $horaLlegadaRetorno,
                    'fecha_viaje' => $request->fecha_viaje,
                    'dias_semana' => $request->dias_semana ?? null,
                    'fecha_fin' => $request->fecha_fin ?? null,
                    'notas' => 'Viaje de retorno',
                    'capacidad_maxima' => $capacidadMaxima,
                    'confirmacion_automatica' => $request->confirmacion_automatica ?? false,
                    'estado' => 'pendiente'
                ]);

                // Vincular viajes
                $viaje->update(['viaje_retorno_id' => $viajeRetorno->id]);
                $viajeRetorno->update(['viaje_retorno_id' => $viaje->id]);
            }

            DB::commit();

            $viaje->load(['escuela', 'chofer', 'unidad', 'viajeRetorno']);

            return response()->json([
                'success' => true,
                'message' => 'Viaje creado exitosamente' . ($viajeRetorno ? ' con viaje de retorno' : ''),
                'data' => $viaje,
                'viaje_retorno' => $viajeRetorno
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error creating viaje: ' . $e->getMessage());
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
            'nombre_ruta' => ['sometimes', 'string', 'max:255', 'regex:/^[a-zA-Z0-9\s\-\/]+$/'],
            'escuela_id' => 'sometimes|exists:escuelas,id',
            'turno' => 'sometimes|in:matutino,vespertino',
            'chofer_id' => 'nullable|exists:choferes,id',
            'unidad_id' => 'nullable|exists:unidades,id',
            'hora_inicio_confirmacion' => 'sometimes|date_format:H:i',
            'hora_fin_confirmacion' => 'sometimes|date_format:H:i',
            'hora_inicio_viaje' => 'sometimes|date_format:H:i',
            'hora_llegada_estimada' => 'sometimes|date_format:H:i',
            'fecha_viaje' => 'sometimes|date',
            'dias_semana' => 'nullable|array',
            'dias_semana.*' => 'integer|between:0,6',
            'fecha_fin' => 'nullable|date',
            'estado' => 'sometimes|in:pendiente,confirmaciones_abiertas,confirmaciones_cerradas,en_curso,completado,cancelado',
            'notas' => 'nullable|string',
            'capacidad_maxima' => 'nullable|integer|min:1',
            'confirmacion_automatica' => 'nullable|boolean'
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

            // Preparar datos para actualizar
            $dataToUpdate = $request->only([
                'nombre_ruta',
                'escuela_id',
                'turno',
                'chofer_id',
                'unidad_id',
                'fecha_viaje',
                'dias_semana',
                'fecha_fin',
                'estado',
                'notas',
                'capacidad_maxima',
                'confirmacion_automatica'
            ]);
            
            // Agregar segundos a las horas si se proporcionan
            if ($request->has('hora_inicio_confirmacion')) {
                $dataToUpdate['hora_inicio_confirmacion'] = $request->hora_inicio_confirmacion . ':00';
            }
            if ($request->has('hora_fin_confirmacion')) {
                $dataToUpdate['hora_fin_confirmacion'] = $request->hora_fin_confirmacion . ':00';
            }
            if ($request->has('hora_inicio_viaje')) {
                $dataToUpdate['hora_inicio_viaje'] = $request->hora_inicio_viaje . ':00';
            }
            if ($request->has('hora_llegada_estimada')) {
                $dataToUpdate['hora_llegada_estimada'] = $request->hora_llegada_estimada . ':00';
            }
            
            // Si se cambió la unidad, actualizar capacidad
            if ($request->has('unidad_id') && $request->unidad_id) {
                $unidad = \App\Models\Unidad::find($request->unidad_id);
                if ($unidad && $unidad->numero_asientos) {
                    $dataToUpdate['capacidad_maxima'] = $unidad->numero_asientos;
                }
            }

            $viaje->update($dataToUpdate);

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
