<?php

namespace App\Http\Controllers;

use App\Models\Viaje;
use App\Models\ConfirmacionViaje;
use App\Models\Unidad;
use App\Models\Ruta;
use App\Models\ParadaRuta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\RutaOptimizacionService;

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
                $rules['dias_semana.*'] = 'integer|min:0|max:6'; // 0=Domingo, 6=Sábado
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
                $diasSeleccionados = $request->dias_semana; // Array de números 0-6
                $diasDisponibles = [];
                
                $fecha = $fechaInicio->copy();
                while ($fecha->lte($fechaFin)) {
                    $diaNumero = $fecha->dayOfWeek; // 0=Domingo, 6=Sábado
                    if (!in_array($diaNumero, $diasDisponibles)) {
                        $diasDisponibles[] = $diaNumero;
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
     * Generar ruta optimizada con K-means (PHP nativo)
     */
    public function generarRuta($id)
    {
        DB::beginTransaction();
        
        try {
            $viaje = Viaje::with(['confirmacionesActivas.hijo', 'escuela', 'unidad', 'chofer'])->findOrFail($id);
            
            Log::info("Generando ruta para viaje ID: {$id}", [
                'estado' => $viaje->estado,
                'confirmaciones' => $viaje->confirmacionesActivas->count()
            ]);
            
            // Validar que el viaje esté en confirmaciones
            if ($viaje->estado !== 'en_confirmaciones') {
                return response()->json([
                    'error' => 'Solo se puede generar ruta para viajes en estado "en_confirmaciones"',
                    'estado_actual' => $viaje->estado
                ], 422);
            }
            
            // Validar que haya confirmaciones
            if ($viaje->confirmacionesActivas->count() === 0) {
                return response()->json([
                    'error' => 'No hay confirmaciones para generar la ruta'
                ], 422);
            }
            
            // Validar que la escuela tenga coordenadas
            if (empty($viaje->escuela->latitud) || empty($viaje->escuela->longitud)) {
                return response()->json([
                    'error' => 'La escuela no tiene coordenadas configuradas. Configure la ubicación en el módulo de escuelas.'
                ], 422);
            }
            
            // Preparar datos de confirmaciones para optimización
            $confirmacionesData = $viaje->confirmacionesActivas->map(function($confirmacion) {
                return [
                    'id' => $confirmacion->id,
                    'hijo_id' => $confirmacion->hijo_id,
                    'hijo_nombre' => $confirmacion->hijo->nombre ?? 'Sin nombre',
                    'direccion_recogida' => $confirmacion->direccion_recogida,
                    'referencia' => $confirmacion->referencia,
                    'latitud' => (float) $confirmacion->latitud,
                    'longitud' => (float) $confirmacion->longitud
                ];
            })->toArray();
            
            $escuelaCoordenadas = [
                'lat' => (float) $viaje->escuela->latitud,
                'lng' => (float) $viaje->escuela->longitud
            ];
            
            // Usar servicio de optimización de rutas
            $optimizacionService = new RutaOptimizacionService();
            $resultado = $optimizacionService->optimizarRuta($escuelaCoordenadas, $confirmacionesData);
            
            if (!$resultado['success']) {
                throw new \Exception($resultado['error'] ?? 'Error desconocido al optimizar ruta');
            }
            
            // Crear registro de Ruta
            $ruta = Ruta::create([
                'viaje_id' => $viaje->id,
                'escuela_id' => $viaje->escuela_id,
                'chofer_id' => $viaje->chofer_id,
                'latitud_inicio' => $escuelaCoordenadas['lat'],
                'longitud_inicio' => $escuelaCoordenadas['lng'],
                'latitud_fin' => $escuelaCoordenadas['lat'],
                'longitud_fin' => $escuelaCoordenadas['lng'],
                'distancia_total_km' => $resultado['distancia_total_km'],
                'tiempo_estimado_min' => $resultado['tiempo_total_min'],
                'polyline' => $resultado['polyline'],
                'estado' => 'pendiente',
                'fecha_generacion' => now(),
                'algoritmo_usado' => 'K-means + Greedy TSP',
                'num_clusters' => $resultado['num_clusters']
            ]);
            
            Log::info("Ruta creada ID: {$ruta->id}");
            
            // Calcular hora de inicio (viaje empieza antes de la hora programada)
            $horaInicio = Carbon::parse($viaje->hora_salida_programada)
                ->subMinutes($resultado['tiempo_total_min']);
            
            // Crear ParadaRuta para cada parada optimizada
            foreach ($resultado['paradas_ordenadas'] as $paradaData) {
                // Calcular hora estimada de llegada a esta parada
                $horaEstimada = $horaInicio->copy()->addMinutes(
                    array_sum(array_slice(
                        array_column($resultado['paradas_ordenadas'], 'tiempo_desde_anterior_min'),
                        0,
                        $paradaData['orden']
                    ))
                );
                
                $parada = ParadaRuta::create([
                    'ruta_id' => $ruta->id,
                    'confirmacion_id' => $paradaData['confirmacion_id'],
                    'orden' => $paradaData['orden'],
                    'direccion' => $paradaData['direccion'],
                    'latitud' => $paradaData['latitud'],
                    'longitud' => $paradaData['longitud'],
                    'hora_estimada' => $horaEstimada->format('H:i:s'),
                    'distancia_desde_anterior_km' => $paradaData['distancia_desde_anterior_km'],
                    'tiempo_desde_anterior_min' => $paradaData['tiempo_desde_anterior_min'],
                    'cluster_asignado' => $paradaData['cluster_asignado'],
                    'estado' => 'pendiente'
                ]);
                
                // Actualizar confirmación con parada_id y orden
                ConfirmacionViaje::where('id', $paradaData['confirmacion_id'])
                    ->update([
                        'parada_id' => $parada->id,
                        'orden' => $paradaData['orden'],
                        'hora_estimada_llegada' => $horaEstimada->format('H:i:s')
                    ]);
            }
            
            // Cambiar estado del viaje a "confirmado" (ruta generada exitosamente)
            $viaje->update([
                'estado' => 'confirmado',
                'hora_inicio_real' => $horaInicio->format('H:i:s')
            ]);
            
            DB::commit();
            
            Log::info("Ruta generada exitosamente para viaje {$viaje->id}", [
                'ruta_id' => $ruta->id,
                'paradas' => count($resultado['paradas_ordenadas']),
                'distancia_km' => $resultado['distancia_total_km'],
                'tiempo_min' => $resultado['tiempo_total_min']
            ]);
            
            // Cargar relaciones para respuesta
            $ruta->load(['paradas' => function($query) {
                $query->orderBy('orden')->with('confirmacion.hijo');
            }]);
            
            return response()->json([
                'success' => true,
                'message' => '¡Ruta generada exitosamente con K-means!',
                'viaje' => $viaje->fresh(['escuela', 'unidad', 'chofer']),
                'ruta' => $ruta,
                'resumen' => $resultado['resumen'],
                'estadisticas' => [
                    'total_paradas' => count($resultado['paradas_ordenadas']),
                    'clusters_usados' => $resultado['num_clusters'],
                    'distancia_total' => $resultado['distancia_total_km'] . ' km',
                    'tiempo_estimado' => $resultado['tiempo_total_min'] . ' minutos',
                    'hora_inicio_sugerida' => $horaInicio->format('H:i'),
                    'hora_llegada_escuela' => $viaje->hora_salida_programada
                ]
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error generando ruta: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Error al generar la ruta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener viajes disponibles para confirmar (Usuario/Padre)
     */
    public function viajesDisponibles(Request $request)
    {
        try {
            $user = auth()->user();

            // Si por alguna razón no hay usuario autenticado, responder 401
            if (!$user) {
                Log::warning('viajesDisponibles: usuario no autenticado al intentar obtener viajes disponibles');
                return response()->json([
                    'error' => 'No autenticado'
                ], 401);
            }

            Log::info("viajesDisponibles - Usuario ID: {$user->id}, Nombre: {$user->nombre}");

            // Obtener hijos del usuario (si no tiene hijos, devolvemos vacío)
            $hijos = $user->hijos()->get();
            
            Log::info("viajesDisponibles - Cantidad de hijos: " . $hijos->count());

            if ($hijos->isEmpty()) {
                Log::info("viajesDisponibles - Usuario {$user->id} no tiene hijos registrados");
                return response()->json([], 200);
            }
            
            // Obtener viajes que pueden estar abiertos a confirmaciones.
            // Consideramos viajes cuyo estado ya es 'en_confirmaciones' y
            // viajes 'programado' cuya ventana de confirmaciones incluye el momento actual.
            $now = Carbon::now();

            // Obtener escuelas de los hijos del usuario
            $escuelasIds = $hijos->pluck('escuela_id')->filter()->unique()->values();
            Log::info("viajesDisponibles - Escuelas de los hijos: " . json_encode($escuelasIds));
            
            // Buscar viajes de esas escuelas
            // IMPORTANTE: Solo mostrar viajes en estado 'en_confirmaciones'
            // NO mostrar viajes en estado 'programado'
            $candidates = Viaje::with(['escuela', 'unidad', 'chofer'])
                ->where('estado', 'en_confirmaciones')
                ->when($escuelasIds->isNotEmpty(), function($query) use ($escuelasIds) {
                    return $query->whereIn('escuela_id', $escuelasIds);
                })
                ->get();

            Log::info("viajesDisponibles - Candidatos encontrados: " . $candidates->count());

            // Log para depuración: volcar campos clave de los candidatos
            try {
                $debugArr = $candidates->map(function($v) {
                    return [
                        'id' => $v->id,
                        'estado' => $v->estado,
                        'tipo_viaje' => $v->tipo_viaje,
                        'escuela_id' => $v->escuela_id,
                        'escuela_nombre' => $v->escuela?->nombre,
                        'fecha_viaje' => $v->fecha_viaje?->format('Y-m-d'),
                        'fecha_inicio_recurrencia' => $v->fecha_inicio_recurrencia?->format('Y-m-d'),
                        'fecha_fin_recurrencia' => $v->fecha_fin_recurrencia?->format('Y-m-d'),
                        'hora_inicio_confirmaciones' => $v->hora_inicio_confirmaciones?->format('H:i:s'),
                        'hora_fin_confirmaciones' => $v->hora_fin_confirmaciones?->format('H:i:s'),
                        'dias_semana' => $v->dias_semana,
                        'confirmaciones_actuales' => $v->confirmaciones_actuales,
                        'cupo_maximo' => $v->cupo_maximo,
                    ];
                })->toArray();

                Log::info('viajesDisponibles - Candidatos detalle: ' . json_encode($debugArr));
            } catch (\Exception $logEx) {
                Log::warning('viajesDisponibles - error al generar debugArr: ' . $logEx->getMessage());
            }

            $disponibles = $candidates->filter(function($viaje) use ($now) {
                // Si ya está en 'en_confirmaciones', aplicar la regla existente
                if ($viaje->estado === 'en_confirmaciones') {
                    return $viaje->puedeConfirmar();
                }

                // Si está 'programado', comprobamos si ahora está dentro de la ventana
                try {
                    // Para viajes únicos, la ventana se aplica sobre la fecha_viaje
                    if ($viaje->tipo_viaje === 'unico') {
                        if (!$viaje->fecha_viaje || !$viaje->hora_inicio_confirmaciones || !$viaje->hora_fin_confirmaciones) {
                            return false;
                        }

                        $fecha = $viaje->fecha_viaje->format('Y-m-d');
                        $start = Carbon::parse($fecha . ' ' . $viaje->hora_inicio_confirmaciones->format('H:i:s'));
                        $end = Carbon::parse($fecha . ' ' . $viaje->hora_fin_confirmaciones->format('H:i:s'));

                        return $now->between($start, $end) && $viaje->confirmaciones_actuales < $viaje->cupo_maximo;
                    }

                    // Para recurrentes, verificar rango de recurrencia y día de la semana
                    if ($viaje->tipo_viaje === 'recurrente') {
                        if (!$viaje->fecha_inicio_recurrencia || !$viaje->fecha_fin_recurrencia || !$viaje->hora_inicio_confirmaciones || !$viaje->hora_fin_confirmaciones) {
                            return false;
                        }

                        // ¿Está hoy dentro del rango de recurrencia?
                        if ($now->lt($viaje->fecha_inicio_recurrencia) || $now->gt($viaje->fecha_fin_recurrencia)) {
                            return false;
                        }

                        // dias_semana puede ser array de nombres o de índices (0=domingo..6)
                        $dias = $viaje->dias_semana ?? [];
                        $diaHoyIndex = $now->dayOfWeek; // 0 (domingo) - 6 (sabado)

                        $diaMatch = false;
                        foreach ($dias as $d) {
                            if (is_int($d) || ctype_digit((string)$d)) {
                                if ((int)$d === $diaHoyIndex) { $diaMatch = true; break; }
                            } else {
                                // comparar por nombre (aceptamos español en minúsculas)
                                $map = [0=>'domingo',1=>'lunes',2=>'martes',3=>'miercoles',4=>'jueves',5=>'viernes',6=>'sabado'];
                                if (strtolower($d) === $map[$diaHoyIndex]) { $diaMatch = true; break; }
                            }
                        }

                        if (!$diaMatch) return false;

                        // Construimos ventana usando la fecha de hoy
                        $fechaHoy = $now->format('Y-m-d');
                        $start = Carbon::parse($fechaHoy . ' ' . $viaje->hora_inicio_confirmaciones->format('H:i:s'));
                        $end = Carbon::parse($fechaHoy . ' ' . $viaje->hora_fin_confirmaciones->format('H:i:s'));

                        return $now->between($start, $end) && $viaje->confirmaciones_actuales < $viaje->cupo_maximo;
                    }
                } catch (\Exception $ex) {
                    Log::error("Error evaluando ventana de confirmaciones para viaje {$viaje->id}: {$ex->getMessage()}");
                    return false;
                }

                return false;
            });

            Log::info("viajesDisponibles - Viajes disponibles después del filtro: " . $disponibles->count());
            
            // Agregar confirmaciones del usuario para cada viaje disponible
            $disponibles->each(function($viaje) use ($user) {
                try {
                    $viaje->confirmaciones_usuario = ConfirmacionViaje::where('viaje_id', $viaje->id)
                        ->where('padre_id', $user->id)
                        ->where('estado', 'confirmado')
                        ->with('hijo')
                        ->get();
                } catch (\Exception $ex) {
                    Log::error("Error al obtener confirmaciones para viaje {$viaje->id}: {$ex->getMessage()}");
                    $viaje->confirmaciones_usuario = collect([]);
                }
            });

            $resultado = $disponibles->values();
            Log::info("viajesDisponibles - Retornando " . $resultado->count() . " viajes");
            
            return response()->json($resultado, 200);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener viajes disponibles: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener viajes disponibles',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener viajes asignados al chofer (Chofer)
     */
    public function viajesChofer(Request $request)
    {
        try {
            $chofer = auth('chofer-sanctum')->user();

            if (!$chofer) {
                return response()->json(['error' => 'No autenticado'], 401);
            }

            Log::info("viajesChofer - Chofer ID: {$chofer->id}, Nombre: {$chofer->nombre}");

            // Obtener viajes asignados a este chofer
            $viajes = Viaje::with(['escuela', 'unidad', 'ruta.paradas'])
                ->where('chofer_id', $chofer->id)
                ->orderByDesc('fecha_viaje')
                ->get();

            Log::info("viajesChofer - Viajes encontrados: " . $viajes->count());

            return response()->json($viajes, 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener viajes del chofer: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener viajes',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Abrir confirmaciones de un viaje (Chofer)
     */
    public function abrirConfirmacionesChofer(Request $request, $viaje_id)
    {
        try {
            $chofer = auth('chofer-sanctum')->user();

            if (!$chofer) {
                return response()->json(['error' => 'No autenticado'], 401);
            }

            $viaje = Viaje::with(['escuela', 'unidad'])->findOrFail($viaje_id);

            // Verificar que el viaje pertenece al chofer
            if ($viaje->chofer_id !== $chofer->id) {
                return response()->json([
                    'error' => 'No tienes permiso para modificar este viaje'
                ], 403);
            }

            // Solo se puede abrir confirmaciones desde estado 'programado'
            if ($viaje->estado !== 'programado') {
                return response()->json([
                    'error' => 'El viaje debe estar en estado programado para abrir confirmaciones',
                    'estado_actual' => $viaje->estado
                ], 400);
            }

            // Verificar que tenga horarios de confirmación configurados
            if (!$viaje->hora_inicio_confirmaciones || !$viaje->hora_fin_confirmaciones) {
                return response()->json([
                    'error' => 'El viaje no tiene configurados los horarios de confirmación'
                ], 400);
            }

            $viaje->abrirConfirmaciones();

            Log::info("Chofer {$chofer->id} abrió confirmaciones del viaje {$viaje_id}");

            return response()->json([
                'message' => 'Confirmaciones abiertas exitosamente',
                'viaje' => $viaje->load(['escuela', 'unidad', 'ruta'])
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Viaje no encontrado'], 404);
        } catch (\Exception $e) {
            Log::error('Error al abrir confirmaciones: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al abrir confirmaciones',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Programar un viaje (Chofer)
     */
    public function programarChofer(Request $request, $viaje_id)
    {
        try {
            $chofer = auth('chofer-sanctum')->user();

            if (!$chofer) {
                return response()->json(['error' => 'No autenticado'], 401);
            }

            $viaje = Viaje::with(['escuela', 'unidad'])->findOrFail($viaje_id);

            // Verificar que el viaje pertenece al chofer
            if ($viaje->chofer_id !== $chofer->id) {
                return response()->json([
                    'error' => 'No tienes permiso para modificar este viaje'
                ], 403);
            }

            // Solo se puede programar desde estado 'pendiente'
            if ($viaje->estado !== 'pendiente') {
                return response()->json([
                    'error' => 'El viaje debe estar en estado pendiente para ser programado',
                    'estado_actual' => $viaje->estado
                ], 400);
            }

            $viaje->programar();

            Log::info("Chofer {$chofer->id} programó el viaje {$viaje_id}");

            return response()->json([
                'message' => 'Viaje programado exitosamente',
                'viaje' => $viaje->load(['escuela', 'unidad', 'ruta'])
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Viaje no encontrado'], 404);
        } catch (\Exception $e) {
            Log::error('Error al programar viaje: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al programar viaje',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancelar un viaje (Chofer)
     */
    public function cancelarChofer(Request $request, $viaje_id)
    {
        try {
            $chofer = auth('chofer-sanctum')->user();

            if (!$chofer) {
                return response()->json(['error' => 'No autenticado'], 401);
            }

            $validator = Validator::make($request->all(), [
                'motivo' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Datos inválidos',
                    'messages' => $validator->errors()
                ], 422);
            }

            $viaje = Viaje::with(['escuela', 'unidad'])->findOrFail($viaje_id);

            // Verificar que el viaje pertenece al chofer
            if ($viaje->chofer_id !== $chofer->id) {
                return response()->json([
                    'error' => 'No tienes permiso para modificar este viaje'
                ], 403);
            }

            // No se puede cancelar un viaje ya finalizado
            if (in_array($viaje->estado, ['finalizado', 'cancelado'])) {
                return response()->json([
                    'error' => 'No se puede cancelar un viaje finalizado o ya cancelado'
                ], 400);
            }

            $viaje->cancelar($request->motivo);

            Log::info("Chofer {$chofer->id} canceló el viaje {$viaje_id}");

            return response()->json([
                'message' => 'Viaje cancelado exitosamente',
                'viaje' => $viaje->load(['escuela', 'unidad', 'ruta'])
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Viaje no encontrado'], 404);
        } catch (\Exception $e) {
            Log::error('Error al cancelar viaje: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al cancelar viaje',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cerrar confirmaciones de un viaje (Chofer)
     */
    public function cerrarConfirmacionesChofer(Request $request, $viaje_id)
    {
        try {
            $chofer = auth('chofer-sanctum')->user();

            if (!$chofer) {
                return response()->json(['error' => 'No autenticado'], 401);
            }

            $viaje = Viaje::with(['escuela', 'unidad', 'confirmaciones'])->findOrFail($viaje_id);

            // Verificar que el viaje pertenece al chofer
            if ($viaje->chofer_id !== $chofer->id) {
                return response()->json([
                    'error' => 'No tienes permiso para modificar este viaje'
                ], 403);
            }

            // Solo se puede cerrar desde estado 'en_confirmaciones'
            if ($viaje->estado !== 'en_confirmaciones') {
                return response()->json([
                    'error' => 'El viaje no está en estado de confirmaciones',
                    'estado_actual' => $viaje->estado
                ], 400);
            }

            $viaje->cerrarConfirmaciones();
            $viaje->load(['escuela', 'unidad', 'ruta', 'confirmaciones']);

            Log::info("Chofer {$chofer->id} cerró confirmaciones del viaje {$viaje_id}");

            return response()->json([
                'message' => 'Confirmaciones cerradas exitosamente',
                'viaje' => $viaje,
                'confirmaciones_total' => $viaje->confirmaciones_actuales
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Viaje no encontrado'], 404);
        } catch (\Exception $e) {
            Log::error('Error al cerrar confirmaciones: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al cerrar confirmaciones',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Confirmar viaje y solicitar generación de ruta con k-Means (Chofer)
     */
    public function confirmarViajeChofer(Request $request, $viaje_id)
    {
        try {
            $chofer = auth('chofer-sanctum')->user();

            if (!$chofer) {
                return response()->json(['error' => 'No autenticado'], 401);
            }

            $viaje = Viaje::with(['escuela', 'unidad', 'confirmaciones.hijo', 'confirmaciones.padre'])
                ->findOrFail($viaje_id);

            // Verificar que el viaje pertenece al chofer
            if ($viaje->chofer_id !== $chofer->id) {
                return response()->json([
                    'error' => 'No tienes permiso para modificar este viaje'
                ], 403);
            }

            // Solo se puede confirmar desde estado 'confirmado'
            if ($viaje->estado !== 'confirmado') {
                return response()->json([
                    'error' => 'El viaje debe estar en estado confirmado para generar ruta',
                    'estado_actual' => $viaje->estado
                ], 400);
            }

            // Verificar que hay confirmaciones
            if ($viaje->confirmaciones_actuales < 1) {
                return response()->json([
                    'error' => 'No hay confirmaciones para generar la ruta'
                ], 400);
            }

            // Cambiar estado a 'generando_ruta'
            $viaje->iniciarGeneracionRuta();

            Log::info("Chofer {$chofer->id} confirmó viaje {$viaje_id}, iniciando generación de ruta");

            // Generar ruta con K-means en PHP
            try {
                // Validar que la escuela tenga coordenadas
                if (!$viaje->escuela->latitud || !$viaje->escuela->longitud) {
                    Log::error("Escuela sin coordenadas", [
                        'escuela_id' => $viaje->escuela_id,
                        'escuela_nombre' => $viaje->escuela->nombre
                    ]);
                    
                    // Revertir estado
                    $viaje->estado = 'confirmado';
                    $viaje->save();
                    
                    return response()->json([
                        'error' => 'La escuela no tiene coordenadas geocodificadas',
                        'message' => 'Por favor, actualiza la escuela con su ubicación GPS antes de generar rutas'
                    ], 400);
                }
                
                $rutaOptimizacionService = new \App\Services\RutaOptimizacionService();
                
                // Preparar datos para optimización
                $confirmaciones = $viaje->confirmaciones()->where('estado', 'confirmado')->get();
                
                Log::info("Confirmaciones encontradas", [
                    'count' => $confirmaciones->count(),
                    'confirmaciones_raw' => $confirmaciones->toArray()
                ]);
                
                $confirmacionesData = $confirmaciones->map(function($conf) {
                    // Validar que tenga coordenadas
                    if (!$conf->latitud || !$conf->longitud) {
                        throw new \Exception("Confirmación ID {$conf->id} no tiene coordenadas válidas. Latitud: {$conf->latitud}, Longitud: {$conf->longitud}");
                    }
                    
                    $data = [
                        'id' => $conf->id,
                        'hijo_id' => $conf->hijo_id,
                        'hijo_nombre' => optional($conf->hijo)->nombre ?? 'Sin nombre',
                        'direccion_recogida' => $conf->direccion_recogida ?? '',
                        'referencia' => $conf->referencia ?? '',
                        'latitud' => floatval($conf->latitud),
                        'longitud' => floatval($conf->longitud),
                    ];
                    
                    Log::info("Confirmacion procesada", [
                        'conf_id' => $conf->id,
                        'data' => $data
                    ]);
                    
                    return $data;
                })->toArray();

                $escuelaCoordenadas = [
                    'lat' => floatval($viaje->escuela->latitud),
                    'lng' => floatval($viaje->escuela->longitud),
                ];

                // Optimizar ruta con K-means
                $rutaOptimizada = $rutaOptimizacionService->optimizarRuta(
                    $escuelaCoordenadas,
                    $confirmacionesData
                );
                
                if (!$rutaOptimizada['success']) {
                    throw new \Exception($rutaOptimizada['error'] ?? 'Error desconocido al optimizar ruta');
                }

                // Verificar si ya existe una ruta para este viaje y eliminarla
                $rutaExistente = Ruta::where('viaje_id', $viaje->id)->first();
                if ($rutaExistente) {
                    Log::info("Eliminando ruta existente para viaje {$viaje->id}", ['ruta_id' => $rutaExistente->id]);
                    // Eliminar paradas asociadas (cascade debería hacerlo, pero por si acaso)
                    $rutaExistente->paradas()->delete();
                    $rutaExistente->delete();
                }

                // Crear registro de Ruta con estado 'en_progreso' (auto-iniciada)
                Log::info('💾 Guardando ruta en BD', [
                    'polyline_length' => strlen($rutaOptimizada['polyline'] ?? ''),
                    'polyline_exists' => isset($rutaOptimizada['polyline']),
                    'polyline_empty' => empty($rutaOptimizada['polyline']),
                    'polyline_preview' => substr($rutaOptimizada['polyline'] ?? '', 0, 50)
                ]);
                
                $ruta = Ruta::create([
                    'nombre' => "Ruta Viaje #{$viaje->id} - {$viaje->escuela->nombre}",
                    'viaje_id' => $viaje->id,
                    'escuela_id' => $viaje->escuela_id,
                    'distancia_total_km' => $rutaOptimizada['distancia_total_km'] ?? 0,
                    'tiempo_estimado_minutos' => $rutaOptimizada['tiempo_total_min'] ?? 0,
                    'estado' => 'en_progreso', // Auto-iniciada, lista para navegar
                    'algoritmo_utilizado' => 'k-means-clustering',
                    'polyline' => $rutaOptimizada['polyline'] ?? '', // Polyline de Google Maps
                    'parametros_algoritmo' => [
                        'num_clusters' => $rutaOptimizada['num_clusters'] ?? 1,
                        'algoritmo_tsp' => 'Greedy TSP',
                        'total_paradas' => count($rutaOptimizada['paradas_ordenadas'] ?? [])
                    ],
                    'fecha_generacion' => now(),
                    'fecha_inicio' => now(), // Iniciar automáticamente
                ]);
                
                Log::info('✅ Ruta guardada en BD', [
                    'ruta_id' => $ruta->id,
                    'polyline_saved' => !empty($ruta->polyline),
                    'polyline_length_saved' => strlen($ruta->polyline ?? '')
                ]);

                // Calcular hora de inicio (viaje empieza antes de la hora programada)
                $horaInicio = Carbon::parse($viaje->hora_salida_programada)
                    ->subMinutes($rutaOptimizada['tiempo_total_min']);
                
                // Crear paradas de ruta
                foreach ($rutaOptimizada['paradas_ordenadas'] as $index => $parada) {
                    // Calcular hora estimada de llegada a esta parada
                    $horaEstimada = $horaInicio->copy()->addMinutes(
                        array_sum(array_slice(
                            array_column($rutaOptimizada['paradas_ordenadas'], 'tiempo_desde_anterior_min'),
                            0,
                            $parada['orden']
                        ))
                    );
                    
                    Log::info("Creando parada {$index}", [
                        'parada_data' => $parada,
                        'hora_estimada' => $horaEstimada->format('H:i:s')
                    ]);
                    
                    $paradaCreada = ParadaRuta::create([
                        'ruta_id' => $ruta->id,
                        'orden' => $parada['orden'],
                        'confirmacion_id' => $parada['confirmacion_id'],
                        'direccion' => $parada['direccion'],
                        'latitud' => $parada['latitud'],
                        'longitud' => $parada['longitud'],
                        'hora_estimada' => $horaEstimada->format('H:i:s'),
                        'distancia_desde_anterior_km' => $parada['distancia_desde_anterior_km'] ?? 0,
                        'tiempo_desde_anterior_min' => $parada['tiempo_desde_anterior_min'] ?? 0,
                        'cluster_asignado' => $parada['cluster_asignado'] ?? null,
                        'estado' => 'pendiente'
                    ]);

                    // Actualizar confirmación con orden y hora estimada
                    ConfirmacionViaje::where('id', $parada['confirmacion_id'])
                        ->update([
                            'orden_recogida' => $parada['orden'],
                            'hora_estimada_recogida' => $horaEstimada->format('H:i:s')
                        ]);
                }

                // Vincular ruta al viaje y cambiar estado a 'en_curso'
                $viaje->ruta_id = $ruta->id;
                $viaje->estado = 'en_curso';
                $viaje->save();

                Log::info("Ruta generada y auto-iniciada para viaje {$viaje->id}", [
                    'ruta_id' => $ruta->id,
                    'estado_ruta' => 'en_progreso',
                    'paradas' => count($rutaOptimizada['paradas_ordenadas']),
                    'clusters' => $rutaOptimizada['num_clusters'] ?? 1
                ]);

                return response()->json([
                    'message' => 'Ruta generada y lista para navegar',
                    'viaje' => $viaje->load(['escuela', 'unidad', 'ruta.paradas']),
                    'ruta' => $ruta->load('paradas'),
                    'auto_iniciada' => true
                ], 200);

            } catch (\Exception $e) {
                Log::error("Error al generar ruta: " . $e->getMessage());
                Log::error($e->getTraceAsString());
                
                // Revertir estado
                $viaje->estado = 'confirmado';
                $viaje->save();
                
                return response()->json([
                    'error' => 'Error al generar ruta',
                    'message' => $e->getMessage()
                ], 500);
            }

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Viaje no encontrado'], 404);
        } catch (\Exception $e) {
            Log::error('Error al confirmar viaje: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al confirmar viaje',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * [DEPRECADO] Solicitar generación de ruta a Django/Flask
     * Ya no se utiliza - reemplazado por RutaOptimizacionService (PHP K-means)
     */
    /*
    private function solicitarGeneracionRuta($viaje)
    {
        $djangoUrl = env('DJANGO_API_URL', 'https://kmeans-flask-production.up.railway.app');
        
        // Preparar datos para Django
        $confirmaciones = $viaje->confirmaciones()->where('estado', 'confirmado')->get();
        
        $puntos = $confirmaciones->map(function($conf) {
            return [
                'confirmacion_id' => $conf->id,
                'hijo_id' => $conf->hijo_id,
                'hijo_nombre' => $conf->hijo->nombre ?? 'Sin nombre',
                'latitud' => floatval($conf->latitud),
                'longitud' => floatval($conf->longitud),
                'direccion' => $conf->direccion_recogida,
                'referencia' => $conf->referencia,
            ];
        })->toArray();

        $destino = [
            'escuela_id' => $viaje->escuela_id,
            'nombre' => $viaje->escuela->nombre,
            'latitud' => floatval($viaje->escuela->latitud ?? 0),
            'longitud' => floatval($viaje->escuela->longitud ?? 0),
            'direccion' => $viaje->escuela->direccion,
        ];

        $payload = [
            'viaje_id' => $viaje->id,
            'puntos' => $puntos,
            'destino' => $destino,
            'hora_salida' => $viaje->hora_salida_programada->format('H:i:s'),
            'capacidad' => $viaje->cupo_maximo,
            'webhook_url' => env('APP_URL') . '/api/webhook/ruta-generada'
        ];

        Log::info("Enviando solicitud de ruta a Django", ['payload' => $payload]);

        // Realizar solicitud HTTP a Django
        $client = new \GuzzleHttp\Client();
        $response = $client->post($djangoUrl . '/api/generar-ruta', [
            'json' => $payload,
            'timeout' => 120, // 2 minutos timeout
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ]
        ]);

        $result = json_decode($response->getBody()->getContents(), true);
        
        Log::info("Respuesta de Django para ruta", ['result' => $result]);
        
        return $result;
    }
    */
}
