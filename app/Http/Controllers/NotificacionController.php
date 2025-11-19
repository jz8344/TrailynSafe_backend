<?php

namespace App\Http\Controllers;

use App\Models\Viaje;
use App\Models\Usuario;
use App\Models\Hijo;
use App\Models\ConfirmacionViaje;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificacionController extends Controller
{
    /**
     * Enviar notificación de recordatorio de viaje (para ejecutar con cron)
     * Se debe ejecutar un día antes del viaje
     */
    public function enviarRecordatoriosViaje()
    {
        try {
            $manana = now()->addDay()->toDateString();
            $diaSemana = strtolower(now()->addDay()->locale('es')->dayName);
            
            // Buscar viajes de mañana
            $viajes = Viaje::where('tipo_viaje', 'ida')
                ->where(function($query) use ($manana) {
                    $query->whereDate('fecha_viaje', '=', $manana)
                          ->where(function($q) use ($manana) {
                              $q->whereNull('fecha_fin')
                                ->orWhereDate('fecha_fin', '>=', $manana);
                          });
                })
                ->get();
            
            // Filtrar por día de la semana
            $viajes = $viajes->filter(function($viaje) use ($diaSemana) {
                if (empty($viaje->dias_semana)) {
                    return true;
                }
                return in_array($diaSemana, $viaje->dias_semana);
            });
            
            $notificacionesEnviadas = 0;
            
            foreach ($viajes as $viaje) {
                // Buscar hijos de esa escuela y turno
                $hijos = Hijo::where('escuela_id', $viaje->escuela_id)
                    ->where('turno', $viaje->turno)
                    ->with('padre')
                    ->get();
                
                foreach ($hijos as $hijo) {
                    if ($hijo->padre) {
                        // Aquí enviarías la notificación push
                        // Por ahora solo log
                        Log::info("Notificación recordatorio enviada", [
                            'usuario_id' => $hijo->padre->id,
                            'hijo_id' => $hijo->id,
                            'viaje_id' => $viaje->id,
                            'mensaje' => "¡Recuerda! Mañana {$hijo->nombre} tiene viaje programado a las {$viaje->hora_inicio_viaje}"
                        ]);
                        
                        $notificacionesEnviadas++;
                    }
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => "Recordatorios enviados: {$notificacionesEnviadas}"
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error enviando recordatorios: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Notificar inicio de período de confirmación
     */
    public function notificarInicioPeriodoConfirmacion()
    {
        try {
            $ahora = now();
            $horaActual = $ahora->format('H:i:s');
            $diaSemana = strtolower($ahora->locale('es')->dayName);
            
            // Buscar viajes que inician período de confirmación en los próximos 5 minutos
            $viajes = Viaje::where('tipo_viaje', 'ida')
                ->whereDate('fecha_viaje', '<=', $ahora)
                ->where(function($q) use ($ahora) {
                    $q->whereNull('fecha_fin')
                      ->orWhereDate('fecha_fin', '>=', $ahora);
                })
                ->where('hora_inicio_confirmacion', '>=', $horaActual)
                ->where('hora_inicio_confirmacion', '<=', $ahora->addMinutes(5)->format('H:i:s'))
                ->get();
            
            // Filtrar por día
            $viajes = $viajes->filter(function($viaje) use ($diaSemana) {
                if (empty($viaje->dias_semana)) {
                    return true;
                }
                return in_array($diaSemana, $viaje->dias_semana);
            });
            
            $notificacionesEnviadas = 0;
            
            foreach ($viajes as $viaje) {
                $hijos = Hijo::where('escuela_id', $viaje->escuela_id)
                    ->where('turno', $viaje->turno)
                    ->with('padre')
                    ->get();
                
                foreach ($hijos as $hijo) {
                    if ($hijo->padre) {
                        Log::info("Notificación inicio confirmación", [
                            'usuario_id' => $hijo->padre->id,
                            'hijo_id' => $hijo->id,
                            'viaje_id' => $viaje->id,
                            'mensaje' => "¡El período de confirmación ha comenzado! Confirma o rechaza el viaje de {$hijo->nombre} antes de las {$viaje->hora_fin_confirmacion}"
                        ]);
                        
                        $notificacionesEnviadas++;
                    }
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => "Notificaciones enviadas: {$notificacionesEnviadas}"
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error notificando inicio: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Confirmar automáticamente con ubicación guardada
     */
    public function procesarConfirmacionesAutomaticas()
    {
        try {
            $ahora = now();
            $horaActual = $ahora->format('H:i:s');
            $diaSemana = strtolower($ahora->locale('es')->dayName);
            
            // Buscar viajes con confirmación automática activada y en período
            $viajes = Viaje::where('tipo_viaje', 'ida')
                ->where('confirmacion_automatica', true)
                ->whereDate('fecha_viaje', '<=', $ahora)
                ->where(function($q) use ($ahora) {
                    $q->whereNull('fecha_fin')
                      ->orWhereDate('fecha_fin', '>=', $ahora);
                })
                ->where('hora_inicio_confirmacion', '<=', $horaActual)
                ->where('hora_fin_confirmacion', '>=', $horaActual)
                ->get();
            
            $viajes = $viajes->filter(function($viaje) use ($diaSemana) {
                if (empty($viaje->dias_semana)) {
                    return true;
                }
                return in_array($diaSemana, $viaje->dias_semana);
            });
            
            $confirmacionesCreadas = 0;
            
            foreach ($viajes as $viaje) {
                $hijos = Hijo::where('escuela_id', $viaje->escuela_id)
                    ->where('turno', $viaje->turno)
                    ->get();
                
                foreach ($hijos as $hijo) {
                    // Verificar si ya tiene confirmación
                    $yaConfirmado = ConfirmacionViaje::where('viaje_id', $viaje->id)
                        ->where('hijo_id', $hijo->id)
                        ->exists();
                    
                    if ($yaConfirmado) {
                        continue;
                    }
                    
                    // Buscar ubicación previa del hijo
                    $ubicacionPrevia = ConfirmacionViaje::where('hijo_id', $hijo->id)
                        ->whereNotNull('latitud')
                        ->whereNotNull('longitud')
                        ->latest()
                        ->first();
                    
                    if (!$ubicacionPrevia) {
                        // No hay ubicación previa, enviar notificación para que coloque pin
                        Log::info("Notificación ubicación requerida", [
                            'hijo_id' => $hijo->id,
                            'viaje_id' => $viaje->id,
                            'mensaje' => "Por favor, establece la ubicación de recogida para {$hijo->nombre}"
                        ]);
                        continue;
                    }
                    
                    // Crear confirmación automática
                    ConfirmacionViaje::create([
                        'viaje_id' => $viaje->id,
                        'hijo_id' => $hijo->id,
                        'usuario_id' => $hijo->padre_id,
                        'latitud' => $ubicacionPrevia->latitud,
                        'longitud' => $ubicacionPrevia->longitud,
                        'direccion_recogida' => $ubicacionPrevia->direccion_recogida,
                        'ubicacion_automatica' => true,
                        'estado' => 'confirmado'
                    ]);
                    
                    $confirmacionesCreadas++;
                    
                    Log::info("Confirmación automática creada", [
                        'hijo_id' => $hijo->id,
                        'viaje_id' => $viaje->id
                    ]);
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => "Confirmaciones automáticas: {$confirmacionesCreadas}"
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error en confirmaciones automáticas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
