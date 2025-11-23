<?php

namespace App\Http\Controllers;

use App\Models\Viaje;
use App\Models\ConfirmacionViaje;
use App\Models\Unidad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ViajeController extends Controller
{
    /**
     * Listar todos los viajes (Admin)
     */
    public function index(Request $request)
    {
        try {
            $query = Viaje::with(['escuela', 'unidad', 'chofer', 'ruta', 'adminCreador']);
            
            // Filtros opcionales
            if ($request->has('estado')) {
                $query->where('estado', $request->estado);
            }
            
            if ($request->has('tipo_viaje')) {
                $query->where('tipo_viaje', $request->tipo_viaje);
            }
            
            if ($request->has('turno')) {
                $query->where('turno', $request->turno);
            }
            
            if ($request->has('escuela_id')) {
                $query->where('escuela_id', $request->escuela_id);
            }
            
            // Ordenar por fecha más reciente
            $viajes = $query->orderByDesc('created_at')->get();
            
            return response()->json($viajes, 200);
        } catch (\Exception $e) {
            Log::error('Error al listar viajes: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener viajes',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear un nuevo viaje (Admin)
     */
    public function store(Request $request)
    {
        try {
            $rules = [
                'nombre' => 'required|string|max:255',
                'tipo_viaje' => 'required|in:unico,recurrente',
                'turno' => 'required|in:matutino,vespertino',
                'escuela_id' => 'required|exists:escuelas,id',
                'unidad_id' => 'required|exists:unidades,id',
                'chofer_id' => 'required|exists:choferes,id',
                'hora_salida_programada' => 'required|date_format:H:i:s',
                'hora_inicio_confirmaciones' => 'required|date_format:H:i:s',
                'hora_fin_confirmaciones' => 'required|date_format:H:i:s',
                'cupo_minimo' => 'required|integer|min:1',
                'cupo_maximo' => 'required|integer|min:1',
                'notas' => 'nullable|string',
            ];
            
            // Validaciones específicas por tipo de viaje
            if ($request->tipo_viaje === 'unico') {
                $rules['fecha_viaje'] = 'required|date|after_or_equal:today';
            } else {
                $rules['fecha_inicio_recurrencia'] = 'required|date|after_or_equal:today';
                $rules['fecha_fin_recurrencia'] = 'required|date|after:fecha_inicio_recurrencia';
                $rules['dias_semana'] = 'required|array|min:1';
                $rules['dias_semana.*'] = 'in:lunes,martes,miercoles,jueves,viernes,sabado,domingo';
            }
            
            $validator = Validator::make($request->all(), $rules);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Obtener capacidad de la unidad
            $unidad = Unidad::find($request->unidad_id);
            
            // Validar cupo máximo no exceda capacidad de unidad
            if ($request->cupo_maximo > $unidad->capacidad) {
                return response()->json([
                    'error' => "El cupo máximo ({$request->cupo_maximo}) no puede exceder la capacidad de la unidad ({$unidad->capacidad})"
                ], 422);
            }
            
            // Validar cupo mínimo no sea mayor que cupo máximo
            if ($request->cupo_minimo > $request->cupo_maximo) {
                return response()->json([
                    'error' => 'El cupo mínimo no puede ser mayor que el cupo máximo'
                ], 422);
            }
            
            // Validar días de semana para viajes recurrentes
            if ($request->tipo_viaje === 'recurrente') {
                $fechaInicio = Carbon::parse($request->fecha_inicio_recurrencia);
                $fechaFin = Carbon::parse($request->fecha_fin_recurrencia);
                
                // Verificar que al menos uno de los días seleccionados exista en el rango
                $diasSeleccionados = $request->dias_semana;
                $diasDisponibles = [];
                
                $mapaDias = [
                    0 => 'domingo',
                    1 => 'lunes',
                    2 => 'martes',
                    3 => 'miercoles',
                    4 => 'jueves',
                    5 => 'viernes',
                    6 => 'sabado'
                ];
                
                $fecha = $fechaInicio->copy();
                while ($fecha->lte($fechaFin)) {
                    $diaNombre = $mapaDias[$fecha->dayOfWeek];
                    if (!in_array($diaNombre, $diasDisponibles)) {
                        $diasDisponibles[] = $diaNombre;
                    }
                    $fecha->addDay();
                }
                
                $hayCoincidencia = false;
                foreach ($diasSeleccionados as $dia) {
                    if (in_array($dia, $diasDisponibles)) {
                        $hayCoincidencia = true;
                        break;
                    }
                }
                
                if (!$hayCoincidencia) {
                    return response()->json([
                        'error' => 'Los días seleccionados no coinciden con el rango de fechas'
                    ], 422);
                }
            }
            
            $data = $validator->validated();
            $data['estado'] = 'pendiente';
            $data['confirmaciones_actuales'] = 0;
            
            // Obtener admin autenticado
            if (auth('admin-sanctum')->check()) {
                $data['admin_creador_id'] = auth('admin-sanctum')->user()->id;
            }
            
            $viaje = Viaje::create($data);
            
            // Cargar relaciones para respuesta
            $viaje->load(['escuela', 'unidad', 'chofer', 'adminCreador']);
            
            return response()->json($viaje, 201);
            
        } catch (\Exception $e) {
            Log::error('Error al crear viaje: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al crear el viaje',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener un viaje específico
     */
    public function show($id)
    {
        try {
            $viaje = Viaje::with([
                'escuela',
                'unidad',
                'chofer',
                'ruta.paradas.confirmacion.hijo',
                'confirmacionesActivas.hijo',
                'confirmacionesActivas.padre',
                'adminCreador'
            ])->findOrFail($id);
            
            return response()->json($viaje, 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Viaje no encontrado'], 404);
        } catch (\Exception $e) {
            Log::error('Error al obtener viaje: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener el viaje',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un viaje (Admin)
     */
    public function update(Request $request, $id)
    {
        try {
            $viaje = Viaje::findOrFail($id);
            
            // Solo se puede editar si está en estado pendiente
            if ($viaje->estado !== 'pendiente') {
                return response()->json([
                    'error' => 'Solo se pueden editar viajes en estado pendiente'
                ], 422);
            }
            
            $rules = [
                'nombre' => 'sometimes|string|max:255',
                'tipo_viaje' => 'sometimes|in:unico,recurrente',
                'turno' => 'sometimes|in:matutino,vespertino',
                'escuela_id' => 'sometimes|exists:escuelas,id',
                'unidad_id' => 'sometimes|exists:unidades,id',
                'chofer_id' => 'sometimes|exists:choferes,id',
                'hora_salida_programada' => 'sometimes|date_format:H:i:s',
                'hora_inicio_confirmaciones' => 'sometimes|date_format:H:i:s',
                'hora_fin_confirmaciones' => 'sometimes|date_format:H:i:s',
                'cupo_minimo' => 'sometimes|integer|min:1',
                'cupo_maximo' => 'sometimes|integer|min:1',
                'notas' => 'nullable|string',
            ];
            
            $tipoViaje = $request->tipo_viaje ?? $viaje->tipo_viaje;
            
            if ($tipoViaje === 'unico') {
                $rules['fecha_viaje'] = 'sometimes|date|after_or_equal:today';
            } else {
                $rules['fecha_inicio_recurrencia'] = 'sometimes|date|after_or_equal:today';
                $rules['fecha_fin_recurrencia'] = 'sometimes|date';
                $rules['dias_semana'] = 'sometimes|array|min:1';
                $rules['dias_semana.*'] = 'in:lunes,martes,miercoles,jueves,viernes,sabado,domingo';
            }
            
            $validator = Validator::make($request->all(), $rules);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $data = $validator->validated();
            
            // Validar cupo máximo si se actualizó unidad o cupo
            if (isset($data['unidad_id']) || isset($data['cupo_maximo'])) {
                $unidadId = $data['unidad_id'] ?? $viaje->unidad_id;
                $cupoMaximo = $data['cupo_maximo'] ?? $viaje->cupo_maximo;
                $unidad = Unidad::find($unidadId);
                
                if ($cupoMaximo > $unidad->capacidad) {
                    return response()->json([
                        'error' => "El cupo máximo ({$cupoMaximo}) no puede exceder la capacidad de la unidad ({$unidad->capacidad})"
                    ], 422);
                }
            }
            
            $viaje->update($data);
            $viaje->load(['escuela', 'unidad', 'chofer']);
            
            return response()->json($viaje, 200);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Viaje no encontrado'], 404);
        } catch (\Exception $e) {
            Log::error('Error al actualizar viaje: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al actualizar el viaje',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un viaje (Admin)
     */
    public function destroy($id)
    {
        try {
            $viaje = Viaje::findOrFail($id);
            
            // Solo se puede eliminar si está pendiente
            if ($viaje->estado !== 'pendiente') {
                return response()->json([
                    'error' => 'Solo se pueden eliminar viajes en estado pendiente'
                ], 422);
            }
            
            $viaje->delete();
            
            return response()->json(['message' => 'Viaje eliminado exitosamente'], 200);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Viaje no encontrado'], 404);
        } catch (\Exception $e) {
            Log::error('Error al eliminar viaje: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al eliminar el viaje',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Programar un viaje (cambiar de pendiente a programado)
     */
    public function programar($id)
    {
        try {
            $viaje = Viaje::findOrFail($id);
            
            $viaje->programar();
            $viaje->load(['escuela', 'unidad', 'chofer']);
            
            return response()->json([
                'message' => 'Viaje programado exitosamente',
                'viaje' => $viaje
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Cancelar un viaje
     */
    public function cancelar(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'motivo' => 'required|string|max:500'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Debe proporcionar un motivo de cancelación',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $viaje = Viaje::findOrFail($id);
            $viaje->cancelar($request->motivo);
            
            return response()->json([
                'message' => 'Viaje cancelado exitosamente',
                'viaje' => $viaje
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Iniciar generación de ruta
     */
    public function generarRuta($id)
    {
        try {
            $viaje = Viaje::with(['confirmacionesActivas', 'escuela', 'unidad'])->findOrFail($id);
            
            // Validar que el viaje esté confirmado
            if ($viaje->estado !== 'confirmado') {
                return response()->json([
                    'error' => 'Solo se puede generar ruta para viajes confirmados'
                ], 422);
            }
            
            // Validar que haya confirmaciones
            if ($viaje->confirmacionesActivas->count() === 0) {
                return response()->json([
                    'error' => 'No hay confirmaciones para generar la ruta'
                ], 422);
            }
            
            // Cambiar estado
            $viaje->iniciarGeneracionRuta();
            
            // Preparar datos para k-Means
            $confirmaciones = $viaje->confirmacionesActivas;
            
            $datosKMeans = [
                'viaje_id' => $viaje->id,
                'capacidad_unidad' => $viaje->unidad->capacidad,
                'destino' => [
                    'nombre' => $viaje->escuela->nombre,
                    'direccion' => $viaje->escuela->direccion,
                    'latitud' => (float) ($viaje->escuela->latitud ?? 0),
                    'longitud' => (float) ($viaje->escuela->longitud ?? 0)
                ],
                'puntos_recogida' => $confirmaciones->map(function($c) {
                    return [
                        'confirmacion_id' => $c->id,
                        'hijo_id' => $c->hijo_id,
                        'nombre' => $c->hijo->nombre ?? 'Sin nombre',
                        'direccion' => $c->direccion_recogida,
                        'referencia' => $c->referencia,
                        'latitud' => (float) $c->latitud,
                        'longitud' => (float) $c->longitud,
                        'prioridad' => 'normal'
                    ];
                })->toArray(),
                'hora_salida' => $viaje->hora_salida_programada->format('H:i:s')
            ];
            
            // TODO: Aquí se haría la llamada al servidor Django
            // Por ahora retornamos los datos que se enviarían
            
            return response()->json([
                'message' => 'Generación de ruta iniciada',
                'viaje' => $viaje,
                'datos_kmeans' => $datosKMeans,
                'nota' => 'Los datos están listos para enviar al servidor Django'
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Error al generar ruta: ' . $e->getMessage());
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Obtener viajes disponibles para confirmar (Usuario/Padre)
     */
    public function viajesDisponibles(Request $request)
    {
        try {
            $user = auth()->user();
            
            // Obtener hijos del usuario
            $hijos = $user->hijos;
            
            if ($hijos->isEmpty()) {
                return response()->json([
                    'viajes' => [],
                    'message' => 'No tienes hijos registrados'
                ], 200);
            }
            
            // Obtener viajes en confirmaciones
            $viajes = Viaje::with(['escuela', 'unidad', 'chofer'])
                ->enConfirmaciones()
                ->get()
                ->filter(function($viaje) {
                    return $viaje->puedeConfirmar();
                });
            
            // Verificar qué hijos ya tienen confirmación en cada viaje
            $viajes->each(function($viaje) use ($user) {
                $viaje->confirmaciones_usuario = ConfirmacionViaje::where('viaje_id', $viaje->id)
                    ->where('padre_id', $user->id)
                    ->where('estado', 'confirmado')
                    ->with('hijo')
                    ->get();
            });
            
            return response()->json($viajes, 200);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener viajes disponibles: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener viajes disponibles',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
