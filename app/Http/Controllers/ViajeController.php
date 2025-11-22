<?php

namespace App\Http\Controllers;

use App\Models\Viaje;
use App\Models\ViajeSolicitud;
use App\Models\Hijo;
use App\Models\Unidad;
use App\Models\Chofer;
use App\Models\Escuela;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ViajeController extends Controller
{
    /**
     * Listar todos los viajes (Admin)
     */
    public function index(Request $request)
    {
        try {
            $query = Viaje::with(['unidad', 'chofer', 'escuela']);

            // Filtros opcionales
            if ($request->has('escuela_id')) {
                $query->where('escuela_id', $request->escuela_id);
            }

            if ($request->has('estado')) {
                $query->where('estado', $request->estado);
            }

            if ($request->has('tipo_viaje')) {
                $query->where('tipo_viaje', $request->tipo_viaje);
            }

            $viajes = $query->orderBy('created_at', 'desc')->get();

            return response()->json($viajes, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener viajes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nuevo viaje (Admin)
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'unidad_id' => 'required|exists:unidades,id',
                'chofer_id' => 'required|exists:choferes,id',
                'escuela_id' => 'required|exists:escuelas,id',
                'nombre_ruta' => 'required|string|max:100',
                'tipo_viaje' => 'required|in:unico,recurrente',
                'fecha_especifica' => 'required_if:tipo_viaje,unico|date|nullable',
                'dias_activos' => 'required_if:tipo_viaje,recurrente|array|nullable',
                'fecha_inicio_vigencia' => 'required_if:tipo_viaje,recurrente|date|nullable',
                'fecha_fin_vigencia' => 'required_if:tipo_viaje,recurrente|date|after_or_equal:fecha_inicio_vigencia|nullable',
                'horario_salida' => 'required|date_format:H:i',
                'turno' => 'required|in:matutino,vespertino',
                'descripcion' => 'nullable|string',
                'notas' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Obtener capacidad de la unidad
            $unidad = Unidad::findOrFail($request->unidad_id);
            $capacidad_maxima = $unidad->capacidad ?? $unidad->numero_asientos ?? 20;

            // Validar disponibilidad de unidad y chofer en horario
            $conflicto = $this->verificarConflictoHorario(
                $request->unidad_id,
                $request->chofer_id,
                $request->tipo_viaje,
                $request->fecha_especifica,
                $request->dias_activos,
                $request->fecha_inicio_vigencia,
                $request->fecha_fin_vigencia,
                $request->horario_salida
            );

            if ($conflicto) {
                return response()->json([
                    'message' => 'Conflicto de horario',
                    'error' => $conflicto
                ], 409);
            }

            // Crear viaje
            $viaje = Viaje::create([
                'unidad_id' => $request->unidad_id,
                'chofer_id' => $request->chofer_id,
                'escuela_id' => $request->escuela_id,
                'nombre_ruta' => $request->nombre_ruta,
                'tipo_viaje' => $request->tipo_viaje,
                'fecha_especifica' => $request->tipo_viaje === 'unico' ? $request->fecha_especifica : null,
                'dias_activos' => $request->tipo_viaje === 'recurrente' ? $request->dias_activos : null,
                'fecha_inicio_vigencia' => $request->tipo_viaje === 'recurrente' ? $request->fecha_inicio_vigencia : null,
                'fecha_fin_vigencia' => $request->tipo_viaje === 'recurrente' ? $request->fecha_fin_vigencia : null,
                'horario_salida' => $request->horario_salida,
                'capacidad_maxima' => $capacidad_maxima,
                'capacidad_actual' => 0,
                'turno' => $request->turno,
                'estado' => 'abierto',
                'descripcion' => $request->descripcion,
                'notas' => $request->notas
            ]);

            $viaje->load(['unidad', 'chofer', 'escuela']);

            return response()->json([
                'message' => 'Viaje creado exitosamente',
                'viaje' => $viaje
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear viaje',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar viaje (Admin)
     */
    public function update(Request $request, $id)
    {
        try {
            $viaje = Viaje::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'unidad_id' => 'sometimes|exists:unidades,id',
                'chofer_id' => 'sometimes|exists:choferes,id',
                'escuela_id' => 'sometimes|exists:escuelas,id',
                'nombre_ruta' => 'sometimes|string|max:100',
                'tipo_viaje' => 'sometimes|in:unico,recurrente',
                'fecha_especifica' => 'required_if:tipo_viaje,unico|date|nullable',
                'dias_activos' => 'required_if:tipo_viaje,recurrente|array|nullable',
                'fecha_inicio_vigencia' => 'required_if:tipo_viaje,recurrente|date|nullable',
                'fecha_fin_vigencia' => 'required_if:tipo_viaje,recurrente|date|after_or_equal:fecha_inicio_vigencia|nullable',
                'horario_salida' => 'sometimes|date_format:H:i',
                'turno' => 'sometimes|in:matutino,vespertino',
                'estado' => 'sometimes|in:abierto,cerrado,en_curso,completado,cancelado',
                'descripcion' => 'nullable|string',
                'notas' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Actualizar capacidad si cambió la unidad
            if ($request->has('unidad_id') && $request->unidad_id != $viaje->unidad_id) {
                $unidad = Unidad::findOrFail($request->unidad_id);
                $viaje->capacidad_maxima = $unidad->capacidad ?? $unidad->numero_asientos ?? 20;
            }

            // Actualizar campos
            $viaje->fill($request->only([
                'unidad_id', 'chofer_id', 'escuela_id', 'nombre_ruta', 'tipo_viaje',
                'horario_salida', 'turno', 'estado', 'descripcion', 'notas'
            ]));

            if ($request->tipo_viaje === 'unico') {
                $viaje->fecha_especifica = $request->fecha_especifica;
                $viaje->dias_activos = null;
                $viaje->fecha_inicio_vigencia = null;
                $viaje->fecha_fin_vigencia = null;
            } elseif ($request->tipo_viaje === 'recurrente') {
                $viaje->fecha_especifica = null;
                $viaje->dias_activos = $request->dias_activos;
                $viaje->fecha_inicio_vigencia = $request->fecha_inicio_vigencia;
                $viaje->fecha_fin_vigencia = $request->fecha_fin_vigencia;
            }

            $viaje->save();
            $viaje->load(['unidad', 'chofer', 'escuela']);

            return response()->json([
                'message' => 'Viaje actualizado exitosamente',
                'viaje' => $viaje
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar viaje',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar viaje (Admin)
     */
    public function destroy($id)
    {
        try {
            $viaje = Viaje::findOrFail($id);

            // Verificar si tiene solicitudes activas
            $solicitudesActivas = $viaje->solicitudes()
                ->whereIn('estado_confirmacion', ['pendiente', 'aceptado'])
                ->count();

            if ($solicitudesActivas > 0) {
                return response()->json([
                    'message' => 'No se puede eliminar el viaje porque tiene solicitudes activas'
                ], 409);
            }

            $viaje->delete();

            return response()->json([
                'message' => 'Viaje eliminado exitosamente'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar viaje',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener viajes disponibles para un hijo (App Móvil)
     */
    public function getViajesDisponibles(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'hijo_id' => 'required|exists:hijos,id',
                'fecha' => 'required|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $hijo = Hijo::with('datosEscuela')->findOrFail($request->hijo_id);
            $fecha = Carbon::parse($request->fecha);

            if (!$hijo->escuela_id) {
                return response()->json([
                    'message' => 'El hijo no tiene escuela asignada',
                    'viajes' => []
                ], 200);
            }

            // Obtener viajes de la escuela del hijo
            $viajes = Viaje::with(['unidad', 'chofer', 'escuela'])
                ->where('escuela_id', $hijo->escuela_id)
                ->where('estado', 'abierto')
                ->whereRaw('capacidad_actual < capacidad_maxima')
                ->get()
                ->filter(function ($viaje) use ($fecha) {
                    return $viaje->esVigenteParaFecha($fecha);
                })
                ->map(function ($viaje) use ($hijo) {
                    // Verificar si el hijo ya tiene solicitud
                    $solicitudExistente = ViajeSolicitud::where('viaje_id', $viaje->id)
                        ->where('hijo_id', $hijo->id)
                        ->first();

                    return [
                        'id' => $viaje->id,
                        'nombre_ruta' => $viaje->nombre_ruta,
                        'tipo_viaje' => $viaje->tipo_viaje,
                        'horario_salida' => $viaje->horario_salida,
                        'turno' => $viaje->turno,
                        'disponibilidad' => $viaje->capacidad_maxima - $viaje->capacidad_actual,
                        'capacidad_maxima' => $viaje->capacidad_maxima,
                        'unidad' => [
                            'matricula' => $viaje->unidad->matricula,
                            'modelo' => $viaje->unidad->modelo,
                            'imagen' => $viaje->unidad->imagen
                        ],
                        'chofer' => [
                            'nombre' => $viaje->chofer->nombre,
                            'apellidos' => $viaje->chofer->apellidos,
                            'telefono' => $viaje->chofer->telefono
                        ],
                        'escuela' => [
                            'nombre' => $viaje->escuela->nombre,
                            'direccion' => $viaje->escuela->direccion
                        ],
                        'ya_solicitado' => $solicitudExistente ? true : false,
                        'estado_solicitud' => $solicitudExistente ? $solicitudExistente->estado_confirmacion : null,
                        'descripcion' => $viaje->descripcion
                    ];
                })
                ->values();

            return response()->json([
                'viajes' => $viajes,
                'hijo' => [
                    'id' => $hijo->id,
                    'nombre' => $hijo->nombre,
                    'escuela' => $hijo->datosEscuela ? $hijo->datosEscuela->nombre : null
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener viajes disponibles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Solicitar viaje (App Móvil - Padre)
     */
    public function solicitarViaje(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'viaje_id' => 'required|exists:viajes,id',
                'hijo_id' => 'required|exists:hijos,id',
                'latitud' => 'required|numeric|between:-90,90',
                'longitud' => 'required|numeric|between:-180,180',
                'direccion_formateada' => 'required|string|max:500',
                'notas_padre' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $viaje = Viaje::findOrFail($request->viaje_id);
            $hijo = Hijo::findOrFail($request->hijo_id);

            // Validar que el padre es dueño del hijo
            $padreId = auth()->id();
            if ($hijo->padre_id != $padreId) {
                return response()->json([
                    'message' => 'No tiene autorización para solicitar viajes para este hijo'
                ], 403);
            }

            // Validar que el viaje está disponible
            if (!$viaje->puedeRecibirSolicitudes()) {
                return response()->json([
                    'message' => 'El viaje no está disponible para solicitudes'
                ], 409);
            }

            // Verificar que el hijo pertenece a la escuela del viaje
            if ($hijo->escuela_id != $viaje->escuela_id) {
                return response()->json([
                    'message' => 'El hijo no pertenece a la escuela de este viaje'
                ], 409);
            }

            // Verificar si ya existe una solicitud
            $solicitudExistente = ViajeSolicitud::where('viaje_id', $request->viaje_id)
                ->where('hijo_id', $request->hijo_id)
                ->first();

            if ($solicitudExistente) {
                return response()->json([
                    'message' => 'Ya existe una solicitud para este viaje y este hijo',
                    'solicitud' => $solicitudExistente
                ], 409);
            }

            // Crear solicitud
            $solicitud = ViajeSolicitud::create([
                'viaje_id' => $request->viaje_id,
                'hijo_id' => $request->hijo_id,
                'padre_id' => $padreId,
                'estado_confirmacion' => 'pendiente',
                'latitud' => $request->latitud,
                'longitud' => $request->longitud,
                'direccion_formateada' => $request->direccion_formateada,
                'notas_padre' => $request->notas_padre
            ]);

            $solicitud->load(['viaje.unidad', 'viaje.chofer', 'viaje.escuela', 'hijo']);

            return response()->json([
                'message' => 'Solicitud de viaje enviada exitosamente',
                'solicitud' => $solicitud
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al solicitar viaje',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar solicitudes de viajes (Admin)
     */
    public function listarSolicitudes(Request $request)
    {
        try {
            $query = ViajeSolicitud::with(['viaje.unidad', 'viaje.chofer', 'viaje.escuela', 'hijo', 'padre']);

            if ($request->has('estado_confirmacion')) {
                $query->where('estado_confirmacion', $request->estado_confirmacion);
            }

            if ($request->has('viaje_id')) {
                $query->where('viaje_id', $request->viaje_id);
            }

            $solicitudes = $query->orderBy('created_at', 'desc')->get();

            return response()->json($solicitudes, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener solicitudes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Confirmar solicitud de viaje (Admin)
     */
    public function confirmarSolicitud($id, Request $request)
    {
        try {
            $solicitud = ViajeSolicitud::with('viaje')->findOrFail($id);

            if ($solicitud->estado_confirmacion !== 'pendiente') {
                return response()->json([
                    'message' => 'La solicitud ya fue procesada'
                ], 409);
            }

            if (!$solicitud->viaje->puedeRecibirSolicitudes()) {
                return response()->json([
                    'message' => 'El viaje no puede recibir más solicitudes'
                ], 409);
            }

            $solicitud->aceptar($request->notas_admin);

            return response()->json([
                'message' => 'Solicitud aceptada exitosamente',
                'solicitud' => $solicitud->fresh()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al confirmar solicitud',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rechazar solicitud de viaje (Admin)
     */
    public function rechazarSolicitud($id, Request $request)
    {
        try {
            $solicitud = ViajeSolicitud::findOrFail($id);

            if ($solicitud->estado_confirmacion !== 'pendiente') {
                return response()->json([
                    'message' => 'La solicitud ya fue procesada'
                ], 409);
            }

            $solicitud->rechazar($request->notas_admin);

            return response()->json([
                'message' => 'Solicitud rechazada',
                'solicitud' => $solicitud->fresh()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al rechazar solicitud',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancelar solicitud de viaje (Padre desde App)
     */
    public function cancelarSolicitud($id)
    {
        try {
            $solicitud = ViajeSolicitud::findOrFail($id);

            // Validar que el padre es dueño de la solicitud
            $padreId = auth()->id();
            if ($solicitud->padre_id != $padreId) {
                return response()->json([
                    'message' => 'No tiene autorización para cancelar esta solicitud'
                ], 403);
            }

            $solicitud->cancelar();

            return response()->json([
                'message' => 'Solicitud cancelada exitosamente',
                'solicitud' => $solicitud->fresh()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cancelar solicitud',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar conflictos de horario
     */
    private function verificarConflictoHorario($unidadId, $choferId, $tipoViaje, $fechaEspecifica, $diasActivos, $fechaInicio, $fechaFin, $horarioSalida)
    {
        // Buscar viajes que puedan tener conflicto
        $viajesConflicto = Viaje::where(function ($query) use ($unidadId, $choferId) {
            $query->where('unidad_id', $unidadId)
                  ->orWhere('chofer_id', $choferId);
        })
        ->where('estado', '!=', 'cancelado')
        ->get();

        foreach ($viajesConflicto as $viaje) {
            // Comparar horarios (considerar ventana de ±2 horas)
            $horario1 = Carbon::parse($horarioSalida);
            $horario2 = Carbon::parse($viaje->horario_salida);
            $diffMinutos = abs($horario1->diffInMinutes($horario2));

            if ($diffMinutos < 120) { // Menos de 2 horas de diferencia
                // Verificar si hay solapamiento de fechas
                if ($tipoViaje === 'unico' && $viaje->tipo_viaje === 'unico') {
                    if ($fechaEspecifica === $viaje->fecha_especifica->format('Y-m-d')) {
                        return 'Conflicto: La unidad o el chofer ya tienen un viaje asignado en este horario';
                    }
                } elseif ($tipoViaje === 'recurrente' && $viaje->tipo_viaje === 'recurrente') {
                    // Verificar solapamiento de fechas
                    $inicio1 = Carbon::parse($fechaInicio);
                    $fin1 = Carbon::parse($fechaFin);
                    $inicio2 = $viaje->fecha_inicio_vigencia;
                    $fin2 = $viaje->fecha_fin_vigencia;

                    if ($inicio1->lte($fin2) && $fin1->gte($inicio2)) {
                        // Verificar si hay días en común
                        $diasComunes = array_intersect($diasActivos ?? [], $viaje->dias_activos ?? []);
                        if (!empty($diasComunes)) {
                            return 'Conflicto: La unidad o el chofer ya tienen un viaje recurrente en estos días y horario';
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Obtener mis solicitudes (App Móvil - Padre)
     */
    public function misSolicitudes()
    {
        try {
            $padreId = auth()->id();

            $solicitudes = ViajeSolicitud::with(['viaje.unidad', 'viaje.chofer', 'viaje.escuela', 'hijo'])
                ->where('padre_id', $padreId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'solicitudes' => $solicitudes
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener solicitudes',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
