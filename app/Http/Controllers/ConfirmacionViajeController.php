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
        \Log::info('=== INICIO viajesDisponibles ===');
        try {
            \Log::info('Step 1: Obteniendo usuario');
            $usuario = Auth::guard('sanctum')->user();
            \Log::info('Usuario obtenido', ['usuario_id' => $usuario?->id]);
            
            if (!$usuario) {
                \Log::warning('Usuario no autenticado');
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            \Log::info('Step 2: Obteniendo hijos del usuario');
            $hijos = Hijo::where('padre_id', $usuario->id)->get();
            \Log::info('Hijos obtenidos', ['count' => $hijos->count(), 'hijos' => $hijos->pluck('id', 'nombre')]);
            
            if ($hijos->isEmpty()) {
                \Log::info('Usuario no tiene hijos, retornando array vacío');
                return response()->json([
                    'success' => true,
                    'data' => []
                ], 200);
            }
            
            \Log::info('Step 3: Recopilando IDs de escuelas');
            $escuelaIds = collect();
            
            foreach ($hijos as $hijo) {
                if ($hijo->escuela_id) {
                    $escuelaIds->push($hijo->escuela_id);
                }
            }
            
            $escuelaIds = $escuelaIds->filter()->unique()->values();
            \Log::info('Escuelas recopiladas', ['escuela_ids' => $escuelaIds->toArray()]);

            if ($escuelaIds->isEmpty()) {
                \Log::info('No hay escuelas asignadas, retornando array vacío');
                return response()->json([
                    'success' => true,
                    'data' => []
                ], 200);
            }

            \Log::info('Step 4: Buscando viajes');
            $hoy = now();
            $diaSemanaNumero = $hoy->dayOfWeek; // 0=Domingo, 1=Lunes, ..., 6=Sábado
            
            \Log::info('Fecha y día actual', [
                'fecha' => $hoy->format('Y-m-d'),
                'dia_semana_numero' => $diaSemanaNumero,
                'dia_nombre' => $hoy->locale('es')->dayName
            ]);

            // --- LÓGICA DE GENERACIÓN AUTOMÁTICA DE VIAJES RECURRENTES ---
            // Buscar plantillas de viajes (recurrentes) para las escuelas del usuario
            // que coincidan con el día de la semana actual y estén vigentes
            $plantillas = Viaje::whereIn('escuela_id', $escuelaIds)
                ->where('tipo_viaje', 'ida')
                ->where('es_plantilla', true) // Asegurar que solo buscamos plantillas
                ->whereNotNull('dias_semana') // Es recurrente
                ->where(function($q) use ($hoy) {
                    // Verificar vigencia de fechas (inicio y fin)
                    $q->where(function($sub) use ($hoy) {
                        $sub->whereNull('fecha_inicio')
                            ->orWhereDate('fecha_inicio', '<=', $hoy);
                    })
                    ->where(function($sub) use ($hoy) {
                        $sub->whereNull('fecha_fin')
                            ->orWhereDate('fecha_fin', '>=', $hoy);
                    });
                })
                ->get();

            foreach ($plantillas as $plantilla) {
                // Verificar si hoy es un día programado para este viaje
                if (in_array($diaSemanaNumero, $plantilla->dias_semana ?? [])) {
                    
                    // Verificar si ya existe una instancia generada para hoy
                    $instanciaExistente = Viaje::where('parent_viaje_id', $plantilla->id)
                        ->whereDate('fecha_viaje', $hoy)
                        ->exists();
                    
                    if (!$instanciaExistente) {
                        \Log::info('Generando instancia automática para viaje recurrente', ['plantilla_id' => $plantilla->id]);
                        
                        // Clonar la plantilla para crear la instancia de hoy
                        $nuevaInstancia = $plantilla->replicate();
                        $nuevaInstancia->fecha_viaje = $hoy;
                        $nuevaInstancia->parent_viaje_id = $plantilla->id;
                        $nuevaInstancia->es_plantilla = false;
                        $nuevaInstancia->ninos_confirmados = 0;
                        $nuevaInstancia->coordenadas_recogida = [];
                        
                        // Calcular estado inicial basado en la hora
                        $estadoInicial = $plantilla->calcularEstadoActual();
                        
                        $nuevaInstancia->estado = $estadoInicial;
                        $nuevaInstancia->save();

                        // Generar también el viaje de retorno si la plantilla lo tiene asociado
                        if ($plantilla->viaje_retorno_id) {
                            $plantillaRetorno = Viaje::find($plantilla->viaje_retorno_id);
                            if ($plantillaRetorno) {
                                $nuevaInstanciaRetorno = $plantillaRetorno->replicate();
                                $nuevaInstanciaRetorno->fecha_viaje = $hoy;
                                $nuevaInstanciaRetorno->parent_viaje_id = $plantillaRetorno->id;
                                $nuevaInstanciaRetorno->es_plantilla = false;
                                $nuevaInstanciaRetorno->ninos_confirmados = 0;
                                $nuevaInstanciaRetorno->coordenadas_recogida = [];
                                // Vincular con la nueva instancia de ida
                                $nuevaInstanciaRetorno->viaje_retorno_id = $nuevaInstancia->id;
                                
                                // Calcular estado del retorno
                                $estadoRetorno = $plantillaRetorno->calcularEstadoActual();
                                $nuevaInstanciaRetorno->estado = $estadoRetorno;
                                
                                $nuevaInstanciaRetorno->save();
                                
                                // Actualizar la instancia de ida para vincularla con el nuevo retorno
                                $nuevaInstancia->viaje_retorno_id = $nuevaInstanciaRetorno->id;
                                $nuevaInstancia->save();
                            }
                        }
                    }
                }
            }
            // -------------------------------------------------------------
            
            // Obtener viajes de ida para las escuelas del usuario
            // Solo mostrar viajes que YA tienen fecha_viaje registrada (el admin ya los activó para hoy o se autogeneraron)
            $viajes = Viaje::whereIn('escuela_id', $escuelaIds)
                ->where('tipo_viaje', 'ida') // Solo viajes de ida tienen confirmación
                ->where('es_plantilla', false) // Solo instancias concretas
                ->whereDate('fecha_viaje', $hoy) // Solo viajes activos para hoy
                ->orderBy('hora_inicio_viaje', 'asc')
                ->get();
            
            \Log::info('Viajes disponibles para hoy', [
                'count' => $viajes->count(),
                'viajes' => $viajes->map(function($v) {
                    return [
                        'id' => $v->id,
                        'nombre' => $v->nombre_ruta,
                        'fecha_viaje' => $v->fecha_viaje,
                        'turno' => $v->turno,
                        'estado' => $v->estado
                    ];
                })
            ]);

            \Log::info('Step 5: Procesando confirmaciones');
            $viajesConEstado = $viajes->map(function($viaje) use ($hijos, $hoy) {
                $hijosConfirmados = [];
                $enPeriodoConfirmacion = $viaje->estaEnPeriodoConfirmacion();
                
                foreach ($hijos as $hijo) {
                    // Verificar que el hijo esté en la misma escuela Y mismo turno
                    if ($hijo->escuela_id == $viaje->escuela_id && $hijo->turno == $viaje->turno) {
                        $confirmacion = ConfirmacionViaje::where('viaje_id', $viaje->id)
                            ->where('hijo_id', $hijo->id)
                            ->first();

                        $hijosConfirmados[] = [
                            'hijo_id' => $hijo->id,
                            'hijo_nombre' => $hijo->nombre,
                            'confirmado' => $confirmacion ? true : false,
                            'estado' => $confirmacion ? $confirmacion->estado : null,
                            'puede_confirmar' => $enPeriodoConfirmacion && !$confirmacion,
                            'ubicacion_guardada' => $confirmacion ? $confirmacion->ubicacion_automatica : false
                        ];
                    }
                }
                
                $viaje->hijos_confirmados = $hijosConfirmados;
                $viaje->en_periodo_confirmacion = $enPeriodoConfirmacion;
                $viaje->bloqueado = !$enPeriodoConfirmacion;
                
                return $viaje;
            });

            \Log::info('Step 6: Retornando respuesta exitosa');
            return response()->json([
                'success' => true,
                'data' => $viajesConEstado
            ], 200);
        } catch (\Exception $e) {
            \Log::error('ERROR en viajesDisponibles', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener viajes: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
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

            $confirmacion->load(['hijo.datosEscuela', 'viaje']);

            // Transformar hijo.datosEscuela a string para compatibilidad
            if ($confirmacion->hijo && $confirmacion->hijo->relationLoaded('datosEscuela')) {
                if ($confirmacion->hijo->datosEscuela) {
                    $nombreEscuela = $confirmacion->hijo->datosEscuela->nombre;
                    $confirmacion->hijo->unsetRelation('datosEscuela');
                    $confirmacion->hijo->setAttribute('escuela', $nombreEscuela);
                } else {
                    $confirmacion->hijo->unsetRelation('datosEscuela');
                }
            }

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

            $confirmaciones = ConfirmacionViaje::with(['viaje.escuela', 'hijo.datosEscuela'])
                ->where('usuario_id', $usuario->id)
                ->orderBy('created_at', 'desc')
                ->get();

            // Transformar hijo.datosEscuela a string para compatibilidad
            $confirmaciones->transform(function ($conf) {
                if ($conf->hijo && $conf->hijo->relationLoaded('datosEscuela')) {
                    if ($conf->hijo->datosEscuela) {
                        $nombreEscuela = $conf->hijo->datosEscuela->nombre;
                        $conf->hijo->unsetRelation('datosEscuela');
                        $conf->hijo->setAttribute('escuela', $nombreEscuela);
                    } else {
                        $conf->hijo->unsetRelation('datosEscuela');
                    }
                }
                return $conf;
            });

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
