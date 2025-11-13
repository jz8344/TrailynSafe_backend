<?php

namespace App\Http\Controllers;

use App\Models\Viaje;
use App\Models\ConfirmacionViaje;
use App\Models\UbicacionBus;
use App\Models\TelemetriaChofer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ChoferViajeController extends Controller
{
    /**
     * Obtener viajes asignados al chofer
     */
    public function misViajes(Request $request)
    {
        try {
            // Obtener chofer_id del token o request
            $choferId = $request->input('chofer_id');

            if (!$choferId) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de chofer no proporcionado'
                ], 400);
            }

            $query = Viaje::with(['escuela', 'unidad', 'confirmaciones.hijo'])
                ->where('chofer_id', $choferId);

            // Filtrar por fecha si se proporciona
            if ($request->has('fecha')) {
                $query->whereDate('fecha_viaje', $request->fecha);
            } else {
                // Por defecto, viajes de hoy y futuros
                $query->whereDate('fecha_viaje', '>=', today());
            }

            // Filtrar por estado
            if ($request->has('estado')) {
                $query->where('estado', $request->estado);
            }

            $viajes = $query->orderBy('fecha_viaje', 'asc')
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
     * Iniciar un viaje
     */
    public function iniciarViaje(Request $request, $id)
    {
        try {
            $viaje = Viaje::findOrFail($id);

            // Verificar que el chofer del request coincide con el del viaje
            $choferId = $request->input('chofer_id');
            if ($viaje->chofer_id != $choferId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este viaje no está asignado a este chofer'
                ], 403);
            }

            // Verificar estado del viaje
            if ($viaje->estado !== 'confirmaciones_cerradas') {
                return response()->json([
                    'success' => false,
                    'message' => 'El viaje debe tener las confirmaciones cerradas para iniciarlo'
                ], 403);
            }

            DB::beginTransaction();

            // Cambiar estado del viaje
            $viaje->update(['estado' => 'en_curso']);

            DB::commit();

            $viaje->load(['escuela', 'unidad', 'confirmaciones.hijo']);

            return response()->json([
                'success' => true,
                'message' => 'Viaje iniciado exitosamente',
                'data' => $viaje
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al iniciar viaje: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Finalizar un viaje
     */
    public function finalizarViaje(Request $request, $id)
    {
        try {
            $viaje = Viaje::findOrFail($id);

            $choferId = $request->input('chofer_id');
            if ($viaje->chofer_id != $choferId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este viaje no está asignado a este chofer'
                ], 403);
            }

            if ($viaje->estado !== 'en_curso') {
                return response()->json([
                    'success' => false,
                    'message' => 'El viaje debe estar en curso para finalizarlo'
                ], 403);
            }

            DB::beginTransaction();

            $viaje->update(['estado' => 'completado']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Viaje finalizado exitosamente',
                'data' => $viaje
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al finalizar viaje: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Escanear QR de un hijo
     */
    public function escanearQR(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'viaje_id' => 'required|exists:viajes,id',
            'hijo_id' => 'required|exists:hijos,id',
            'chofer_id' => 'required|exists:choferes,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $viaje = Viaje::findOrFail($request->viaje_id);

            // Verificar que el chofer del viaje coincide
            if ($viaje->chofer_id != $request->chofer_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este viaje no está asignado a este chofer'
                ], 403);
            }

            // Buscar confirmación
            $confirmacion = ConfirmacionViaje::where('viaje_id', $request->viaje_id)
                ->where('hijo_id', $request->hijo_id)
                ->where('estado', 'confirmado')
                ->first();

            if (!$confirmacion) {
                return response()->json([
                    'success' => false,
                    'message' => 'No existe confirmación para este hijo en este viaje'
                ], 404);
            }

            if ($confirmacion->qr_escaneado) {
                return response()->json([
                    'success' => false,
                    'message' => 'El QR ya fue escaneado previamente',
                    'data' => $confirmacion
                ], 409);
            }

            // Marcar como escaneado
            $confirmacion->update([
                'qr_escaneado' => true,
                'hora_escaneo_qr' => now(),
                'estado' => 'completado'
            ]);

            $confirmacion->load('hijo.usuario');

            return response()->json([
                'success' => true,
                'message' => 'QR escaneado exitosamente',
                'data' => $confirmacion
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al escanear QR: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar ubicación del bus (GPS del ESP32)
     */
    public function actualizarUbicacion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'viaje_id' => 'required|exists:viajes,id',
            'unidad_id' => 'required|exists:unidades,id',
            'latitud' => 'required|numeric|between:-90,90',
            'longitud' => 'required|numeric|between:-180,180',
            'velocidad' => 'nullable|numeric|min:0',
            'heading' => 'nullable|numeric|between:0,360',
            'precision' => 'nullable|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $ubicacion = UbicacionBus::create([
                'viaje_id' => $request->viaje_id,
                'unidad_id' => $request->unidad_id,
                'latitud' => $request->latitud,
                'longitud' => $request->longitud,
                'velocidad' => $request->velocidad,
                'heading' => $request->heading,
                'precision' => $request->precision,
                'timestamp_gps' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ubicación actualizada',
                'data' => $ubicacion
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar ubicación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registrar telemetría del chofer (WearOS)
     */
    public function registrarTelemetria(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'chofer_id' => 'required|exists:choferes,id',
            'viaje_id' => 'nullable|exists:viajes,id',
            'frecuencia_cardiaca' => 'nullable|integer|min:0',
            'velocidad' => 'nullable|numeric|min:0',
            'aceleracion' => 'nullable|numeric',
            'latitud' => 'nullable|numeric|between:-90,90',
            'longitud' => 'nullable|numeric|between:-180,180',
            'altitud' => 'nullable|numeric',
            'impacto_detectado' => 'nullable|boolean',
            'temperatura' => 'nullable|numeric',
            'nivel_combustible' => 'nullable|numeric|between:0,100',
            'nivel_alerta' => 'nullable|in:normal,precaucion,peligro',
            'descripcion_alerta' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $telemetria = TelemetriaChofer::create([
                'chofer_id' => $request->chofer_id,
                'viaje_id' => $request->viaje_id,
                'frecuencia_cardiaca' => $request->frecuencia_cardiaca,
                'velocidad' => $request->velocidad,
                'aceleracion' => $request->aceleracion,
                'latitud' => $request->latitud,
                'longitud' => $request->longitud,
                'altitud' => $request->altitud,
                'impacto_detectado' => $request->impacto_detectado ?? false,
                'temperatura' => $request->temperatura,
                'nivel_combustible' => $request->nivel_combustible,
                'nivel_alerta' => $request->nivel_alerta ?? 'normal',
                'descripcion_alerta' => $request->descripcion_alerta,
                'timestamp_lectura' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Telemetría registrada',
                'data' => $telemetria
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar telemetría: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener lista de hijos confirmados para el viaje
     */
    public function hijosConfirmados($viajeId)
    {
        try {
            $viaje = Viaje::with(['confirmaciones' => function($query) {
                $query->where('estado', 'confirmado')
                    ->orderBy('orden_recogida', 'asc')
                    ->with('hijo.usuario');
            }])->findOrFail($viajeId);

            return response()->json([
                'success' => true,
                'data' => $viaje->confirmaciones
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener hijos confirmados: ' . $e->getMessage()
            ], 500);
        }
    }
}
