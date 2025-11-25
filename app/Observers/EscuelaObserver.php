<?php

namespace App\Observers;

use App\Models\Escuela;
use App\Services\GeocodingService;
use Illuminate\Support\Facades\Log;

class EscuelaObserver
{
    private $geocodingService;

    public function __construct(GeocodingService $geocodingService)
    {
        $this->geocodingService = $geocodingService;
    }

    /**
     * Handle the Escuela "creating" event.
     * Se ejecuta ANTES de crear la escuela
     */
    public function creating(Escuela $escuela)
    {
        $this->geocodificarSiEsNecesario($escuela);
    }

    /**
     * Handle the Escuela "updating" event.
     * Se ejecuta ANTES de actualizar la escuela
     */
    public function updating(Escuela $escuela)
    {
        // Solo geocodificar si la dirección cambió
        if ($escuela->isDirty('direccion')) {
            Log::info("EscuelaObserver: Dirección cambió para escuela {$escuela->id}, geocodificando...");
            $this->geocodificarSiEsNecesario($escuela);
        }
    }

    /**
     * Geocodifica la dirección de la escuela si no tiene coordenadas válidas
     */
    private function geocodificarSiEsNecesario(Escuela $escuela)
    {
        // Si ya tiene coordenadas válidas y no es una creación/actualización de dirección, no hacer nada
        if ($this->tieneCoordenadasValidas($escuela) && !$escuela->isDirty('direccion')) {
            return;
        }

        $direccion = $escuela->direccion;

        if (empty($direccion)) {
            Log::warning("EscuelaObserver: Escuela sin dirección, no se puede geocodificar");
            return;
        }

        Log::info("EscuelaObserver: Iniciando geocodificación para: {$direccion}");

        // Intentar geocodificar
        $resultado = $this->geocodingService->geocodificar($direccion);

        if ($resultado && isset($resultado['latitud']) && isset($resultado['longitud'])) {
            $escuela->latitud = $resultado['latitud'];
            $escuela->longitud = $resultado['longitud'];
            
            Log::info("EscuelaObserver: Coordenadas asignadas automáticamente", [
                'escuela' => $escuela->nombre ?? 'Nueva',
                'latitud' => $resultado['latitud'],
                'longitud' => $resultado['longitud']
            ]);
        } else {
            Log::warning("EscuelaObserver: No se pudieron obtener coordenadas para: {$direccion}");
            
            // Asignar null explícitamente si falló
            $escuela->latitud = null;
            $escuela->longitud = null;
        }
    }

    /**
     * Verifica si la escuela tiene coordenadas válidas
     */
    private function tieneCoordenadasValidas(Escuela $escuela)
    {
        return !empty($escuela->latitud) && 
               !empty($escuela->longitud) &&
               $this->geocodingService->validarCoordenadas($escuela->latitud, $escuela->longitud);
    }
}
