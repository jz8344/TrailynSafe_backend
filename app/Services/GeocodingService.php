<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    private $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.google_maps.api_key');
    }

    /**
     * Geocodifica una dirección usando Google Maps Geocoding API
     * 
     * @param string $direccion Dirección completa
     * @return array|null ['latitud' => float, 'longitud' => float] o null si falla
     */
    public function geocodificar($direccion)
    {
        if (empty($direccion)) {
            Log::warning('GeocodingService: Dirección vacía');
            return null;
        }

        if (empty($this->apiKey)) {
            Log::warning('GeocodingService: GOOGLE_MAPS_API_KEY no configurada');
            return null;
        }

        try {
            Log::info("GeocodingService: Geocodificando dirección: {$direccion}");

            $response = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $direccion,
                'key' => $this->apiKey,
                'region' => 'mx', // Priorizar resultados de México
                'language' => 'es'
            ]);

            if (!$response->successful()) {
                Log::error("GeocodingService: Error HTTP {$response->status()}");
                return null;
            }

            $data = $response->json();

            if ($data['status'] !== 'OK') {
                Log::warning("GeocodingService: Status {$data['status']} para dirección: {$direccion}");
                return null;
            }

            if (empty($data['results'])) {
                Log::warning("GeocodingService: No se encontraron resultados para: {$direccion}");
                return null;
            }

            // Obtener el primer resultado (más preciso)
            $resultado = $data['results'][0];
            $location = $resultado['geometry']['location'];

            $coordenadas = [
                'latitud' => $location['lat'],
                'longitud' => $location['lng'],
                'direccion_formateada' => $resultado['formatted_address'] ?? null,
                'tipo_ubicacion' => $resultado['geometry']['location_type'] ?? null
            ];

            Log::info("GeocodingService: Coordenadas obtenidas", $coordenadas);

            return $coordenadas;

        } catch (\Exception $e) {
            Log::error("GeocodingService: Excepción al geocodificar: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Geocodificación inversa: obtiene dirección desde coordenadas
     * 
     * @param float $latitud
     * @param float $longitud
     * @return string|null Dirección formateada o null si falla
     */
    public function geocodificarInverso($latitud, $longitud)
    {
        if (empty($this->apiKey)) {
            return null;
        }

        try {
            $response = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'latlng' => "{$latitud},{$longitud}",
                'key' => $this->apiKey,
                'language' => 'es'
            ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();

            if ($data['status'] === 'OK' && !empty($data['results'])) {
                return $data['results'][0]['formatted_address'];
            }

            return null;

        } catch (\Exception $e) {
            Log::error("GeocodingService: Error en geocodificación inversa: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Valida si unas coordenadas son válidas
     * 
     * @param float $latitud
     * @param float $longitud
     * @return bool
     */
    public function validarCoordenadas($latitud, $longitud)
    {
        return is_numeric($latitud) && 
               is_numeric($longitud) &&
               $latitud >= -90 && 
               $latitud <= 90 &&
               $longitud >= -180 && 
               $longitud <= 180 &&
               !($latitud == 0 && $longitud == 0); // Evitar coordenadas 0,0
    }
}
