<?php

namespace App\Services;

use Phpml\Clustering\KMeans;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RutaOptimizacionService
{
    private $googleMapsApiKey;
    
    public function __construct()
    {
        $this->googleMapsApiKey = config('services.google_maps.api_key');
    }
    
    /**
     * Optimiza una ruta usando K-means clustering y TSP
     * 
     * @param array $escuelaCoordenadas ['lat' => float, 'lng' => float]
     * @param array $confirmaciones Array de confirmaciones con direcciones
     * @return array Ruta optimizada con paradas ordenadas
     */
    public function optimizarRuta($escuelaCoordenadas, $confirmaciones)
    {
        try {
            Log::info('Iniciando optimización de ruta', [
                'escuela' => $escuelaCoordenadas,
                'total_confirmaciones' => count($confirmaciones)
            ]);
            
            if (empty($confirmaciones)) {
                throw new \Exception('No hay confirmaciones para optimizar');
            }
            
            // 1. Preparar datos para clustering
            $puntos = [];
            $confirmacionesMap = [];
            
            foreach ($confirmaciones as $index => $confirmacion) {
                $puntos[] = [
                    (float) $confirmacion['latitud'],
                    (float) $confirmacion['longitud']
                ];
                $confirmacionesMap[$index] = $confirmacion;
            }
            
            // 2. Determinar número óptimo de clusters (grupos)
            $numClusters = $this->calcularNumeroOptimoClusters(count($confirmaciones));
            
            Log::info("Usando {$numClusters} clusters para " . count($confirmaciones) . " confirmaciones");
            
            // 3. Aplicar K-means clustering
            $kmeans = new KMeans($numClusters);
            $clusters = $kmeans->cluster($puntos);
            
            // 4. Asignar confirmaciones a clusters
            $confirmacionesConCluster = [];
            foreach ($clusters as $clusterIndex => $puntosEnCluster) {
                foreach ($puntosEnCluster as $punto) {
                    // Encontrar la confirmación correspondiente
                    $indexConfirmacion = $this->encontrarIndicePunto($punto, $puntos);
                    if ($indexConfirmacion !== null) {
                        $confirmacion = $confirmacionesMap[$indexConfirmacion];
                        $confirmacion['cluster_asignado'] = $clusterIndex;
                        $confirmacion['coordenadas'] = [
                            'lat' => $punto[0],
                            'lng' => $punto[1]
                        ];
                        $confirmacionesConCluster[] = $confirmacion;
                    }
                }
            }
            
            // 5. Calcular centroides de cada cluster
            $centroides = [];
            foreach ($clusters as $clusterIndex => $puntosEnCluster) {
                $centroides[$clusterIndex] = $this->calcularCentroide($puntosEnCluster);
            }
            
            // 6. Ordenar clusters por distancia desde la escuela (greedy)
            $ordenClusters = $this->ordenarClustersPorDistancia(
                $escuelaCoordenadas,
                $centroides
            );
            
            // 7. Ordenar paradas dentro de cada cluster (TSP simplificado)
            $paradasOrdenadas = [];
            $distanciaTotal = 0;
            $tiempoTotal = 0;
            $ultimoPunto = $escuelaCoordenadas;
            $orden = 0;
            
            foreach ($ordenClusters as $clusterIndex) {
                // Obtener paradas del cluster
                $paradasCluster = array_filter($confirmacionesConCluster, function($c) use ($clusterIndex) {
                    return $c['cluster_asignado'] === $clusterIndex;
                });
                
                // Ordenar paradas del cluster por cercanía
                $paradasClusterOrdenadas = $this->ordenarParadasPorCercania(
                    $paradasCluster,
                    $ultimoPunto
                );
                
                // Agregar a resultado final
                foreach ($paradasClusterOrdenadas as $parada) {
                    $orden++;
                    
                    // Calcular distancia desde última parada
                    $distancia = $this->calcularDistanciaHaversine(
                        $ultimoPunto['lat'],
                        $ultimoPunto['lng'],
                        $parada['coordenadas']['lat'],
                        $parada['coordenadas']['lng']
                    );
                    
                    $distanciaTotal += $distancia;
                    
                    // Estimar tiempo (asumiendo 30 km/h promedio en ciudad + 2 min por parada)
                    $tiempoViaje = ($distancia / 30) * 60; // minutos
                    $tiempoParada = 2; // minutos
                    $tiempoTotal += $tiempoViaje + $tiempoParada;
                    
                    $paradasOrdenadas[] = [
                        'confirmacion_id' => $parada['id'],
                        'hijo_id' => $parada['hijo_id'],
                        'hijo_nombre' => $parada['hijo_nombre'] ?? 'Sin nombre',
                        'direccion' => $parada['direccion_recogida'],
                        'referencia' => $parada['referencia'] ?? '',
                        'latitud' => $parada['coordenadas']['lat'],
                        'longitud' => $parada['coordenadas']['lng'],
                        'orden' => $orden,
                        'cluster_asignado' => $clusterIndex,
                        'distancia_desde_anterior_km' => round($distancia, 2),
                        'tiempo_desde_anterior_min' => round($tiempoViaje + $tiempoParada, 0)
                    ];
                    
                    $ultimoPunto = $parada['coordenadas'];
                }
            }
            
            // 8. Generar polyline usando Google Maps Directions API
            $polylineData = $this->generarPolylineGoogleMaps(
                $escuelaCoordenadas,
                $paradasOrdenadas
            );
            
            Log::info('🗺️ Polyline generada', [
                'polyline_length' => strlen($polylineData['polyline'] ?? ''),
                'polyline_preview' => substr($polylineData['polyline'] ?? '', 0, 50),
                'tiene_polyline' => !empty($polylineData['polyline']),
                'bounds' => $polylineData['bounds'] ?? null
            ]);
            
            Log::info('Ruta optimizada exitosamente', [
                'total_paradas' => count($paradasOrdenadas),
                'clusters' => $numClusters,
                'distancia_total_km' => round($distanciaTotal, 2),
                'tiempo_total_min' => round($tiempoTotal, 0)
            ]);
            
            return [
                'success' => true,
                'paradas_ordenadas' => $paradasOrdenadas,
                'clusters' => $ordenClusters,
                'num_clusters' => $numClusters,
                'distancia_total_km' => round($distanciaTotal, 2),
                'tiempo_total_min' => round($tiempoTotal, 0),
                'polyline' => $polylineData['polyline'] ?? '',
                'bounds' => $polylineData['bounds'] ?? null,
                'resumen' => [
                    'punto_inicio' => $escuelaCoordenadas,
                    'total_paradas' => count($paradasOrdenadas),
                    'distancia_estimada' => round($distanciaTotal, 2) . ' km',
                    'tiempo_estimado' => round($tiempoTotal, 0) . ' minutos'
                ]
            ];
            
        } catch (\Exception $e) {
            Log::error('Error optimizando ruta: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Calcula el número óptimo de clusters basado en el número de paradas
     */
    private function calcularNumeroOptimoClusters($numParadas)
    {
        if ($numParadas <= 5) return 1;
        if ($numParadas <= 10) return 2;
        if ($numParadas <= 15) return 3;
        if ($numParadas <= 25) return 4;
        return 5; // Máximo 5 clusters
    }
    
    /**
     * Encuentra el índice de un punto en el array original
     */
    private function encontrarIndicePunto($punto, $puntos)
    {
        foreach ($puntos as $index => $p) {
            if (abs($p[0] - $punto[0]) < 0.000001 && abs($p[1] - $punto[1]) < 0.000001) {
                return $index;
            }
        }
        return null;
    }
    
    /**
     * Calcula el centroide (promedio) de un conjunto de puntos
     */
    private function calcularCentroide($puntos)
    {
        $sumLat = 0;
        $sumLng = 0;
        $count = count($puntos);
        
        foreach ($puntos as $punto) {
            $sumLat += $punto[0];
            $sumLng += $punto[1];
        }
        
        return [
            'lat' => $sumLat / $count,
            'lng' => $sumLng / $count
        ];
    }
    
    /**
     * Ordena clusters por distancia desde un punto (greedy nearest neighbor)
     */
    private function ordenarClustersPorDistancia($puntoInicio, $centroides)
    {
        $ordenados = [];
        $visitados = [];
        $actual = $puntoInicio;
        
        while (count($visitados) < count($centroides)) {
            $menorDistancia = PHP_FLOAT_MAX;
            $clusterMasCercano = null;
            
            foreach ($centroides as $clusterIndex => $centroide) {
                if (in_array($clusterIndex, $visitados)) continue;
                
                $distancia = $this->calcularDistanciaHaversine(
                    $actual['lat'],
                    $actual['lng'],
                    $centroide['lat'],
                    $centroide['lng']
                );
                
                if ($distancia < $menorDistancia) {
                    $menorDistancia = $distancia;
                    $clusterMasCercano = $clusterIndex;
                }
            }
            
            if ($clusterMasCercano !== null) {
                $ordenados[] = $clusterMasCercano;
                $visitados[] = $clusterMasCercano;
                $actual = $centroides[$clusterMasCercano];
            }
        }
        
        return $ordenados;
    }
    
    /**
     * Ordena paradas dentro de un cluster por cercanía (greedy)
     */
    private function ordenarParadasPorCercania($paradas, $puntoInicio)
    {
        if (empty($paradas)) return [];
        
        $ordenadas = [];
        $pendientes = array_values($paradas);
        $actual = $puntoInicio;
        
        while (!empty($pendientes)) {
            $menorDistancia = PHP_FLOAT_MAX;
            $indiceMasCercano = 0;
            
            foreach ($pendientes as $index => $parada) {
                $distancia = $this->calcularDistanciaHaversine(
                    $actual['lat'],
                    $actual['lng'],
                    $parada['coordenadas']['lat'],
                    $parada['coordenadas']['lng']
                );
                
                if ($distancia < $menorDistancia) {
                    $menorDistancia = $distancia;
                    $indiceMasCercano = $index;
                }
            }
            
            $paradaMasCercana = $pendientes[$indiceMasCercano];
            $ordenadas[] = $paradaMasCercana;
            $actual = $paradaMasCercana['coordenadas'];
            array_splice($pendientes, $indiceMasCercano, 1);
        }
        
        return $ordenadas;
    }
    
    /**
     * Calcula distancia entre dos puntos usando fórmula de Haversine
     * Retorna distancia en kilómetros
     */
    private function calcularDistanciaHaversine($lat1, $lng1, $lat2, $lng2)
    {
        $radioTierra = 6371; // km
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $radioTierra * $c;
    }
    
    /**
     * Genera polyline usando Google Maps Directions API
     */
    private function generarPolylineGoogleMaps($escuela, $paradas)
    {
        try {
            Log::info('🚀 Generando polyline con Google Maps API', [
                'api_key_configured' => !empty($this->googleMapsApiKey),
                'api_key_length' => strlen($this->googleMapsApiKey ?? ''),
                'total_paradas' => count($paradas)
            ]);
            
            if (empty($this->googleMapsApiKey)) {
                Log::warning('Google Maps API key no configurada, usando polyline simplificado');
                return $this->generarPolylineSimple($escuela, $paradas);
            }
            
            // Construir waypoints (máximo 25 waypoints intermedios en Google Maps)
            $waypoints = [];
            $maxWaypoints = min(count($paradas), 25);
            
            for ($i = 0; $i < $maxWaypoints; $i++) {
                $parada = $paradas[$i];
                $waypoints[] = $parada['latitud'] . ',' . $parada['longitud'];
            }
            
            // Destino: última parada o escuela si hay más de 25 paradas
            $destino = $paradas[count($paradas) - 1];
            
            // Llamar a Google Maps Directions API
            $url = 'https://maps.googleapis.com/maps/api/directions/json';
            $params = [
                'origin' => $escuela['lat'] . ',' . $escuela['lng'],
                'destination' => $destino['latitud'] . ',' . $destino['longitud'],
                'waypoints' => 'optimize:true|' . implode('|', $waypoints),
                'key' => $this->googleMapsApiKey
            ];
            
            Log::info('📡 Llamando Google Directions API', [
                'url' => $url,
                'origin' => $params['origin'],
                'destination' => $params['destination'],
                'waypoints_count' => count($waypoints)
            ]);
            
            $response = Http::get($url, $params);
            
            Log::info('📥 Respuesta de Google API', [
                'status_code' => $response->status(),
                'successful' => $response->successful(),
                'has_body' => !empty($response->body())
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('📋 Datos de Google API', [
                    'status' => $data['status'] ?? 'NO_STATUS',
                    'has_routes' => isset($data['routes'][0]),
                    'error_message' => $data['error_message'] ?? null
                ]);
                
                if (isset($data['routes'][0]['overview_polyline']['points'])) {
                    $polyline = $data['routes'][0]['overview_polyline']['points'];
                    Log::info('✅ Polyline obtenida de Google Maps', [
                        'length' => strlen($polyline),
                        'preview' => substr($polyline, 0, 50)
                    ]);
                    return [
                        'polyline' => $polyline,
                        'bounds' => $data['routes'][0]['bounds'] ?? null
                    ];
                }
            }
            
            Log::warning('⚠️ No se pudo obtener polyline de Google Maps, usando simplificado', [
                'api_status' => $data['status'] ?? 'UNKNOWN',
                'error_message' => $data['error_message'] ?? 'No error message'
            ]);
            return $this->generarPolylineSimple($escuela, $paradas);
            
        } catch (\Exception $e) {
            Log::error('Error generando polyline: ' . $e->getMessage());
            return $this->generarPolylineSimple($escuela, $paradas);
        }
    }
    
    /**
     * Genera polyline simple conectando puntos directamente
     */
    private function generarPolylineSimple($escuela, $paradas)
    {
        // Crear array de coordenadas
        $coordenadas = [[$escuela['lat'], $escuela['lng']]];
        
        foreach ($paradas as $parada) {
            $coordenadas[] = [$parada['latitud'], $parada['longitud']];
        }
        
        // Codificar en formato polyline de Google
        $polyline = $this->encodePolyline($coordenadas);
        
        return [
            'polyline' => $polyline,
            'bounds' => $this->calcularBounds($coordenadas)
        ];
    }
    
    /**
     * Codifica coordenadas en formato polyline de Google Maps
     */
    private function encodePolyline($coordinates)
    {
        $encoded = '';
        $prevLat = 0;
        $prevLng = 0;
        
        foreach ($coordinates as $point) {
            $lat = (int) round($point[0] * 1e5);
            $lng = (int) round($point[1] * 1e5);
            
            $dLat = $lat - $prevLat;
            $dLng = $lng - $prevLng;
            
            $encoded .= $this->encodeValue($dLat);
            $encoded .= $this->encodeValue($dLng);
            
            $prevLat = $lat;
            $prevLng = $lng;
        }
        
        return $encoded;
    }
    
    /**
     * Codifica un valor individual para polyline
     */
    private function encodeValue($value)
    {
        $value = $value < 0 ? ~($value << 1) : ($value << 1);
        $encoded = '';
        
        while ($value >= 0x20) {
            $encoded .= chr((0x20 | ($value & 0x1f)) + 63);
            $value >>= 5;
        }
        
        $encoded .= chr($value + 63);
        return $encoded;
    }
    
    /**
     * Calcula bounds (límites) del mapa
     */
    private function calcularBounds($coordenadas)
    {
        $minLat = PHP_FLOAT_MAX;
        $maxLat = -PHP_FLOAT_MAX;
        $minLng = PHP_FLOAT_MAX;
        $maxLng = -PHP_FLOAT_MAX;
        
        foreach ($coordenadas as $coord) {
            $minLat = min($minLat, $coord[0]);
            $maxLat = max($maxLat, $coord[0]);
            $minLng = min($minLng, $coord[1]);
            $maxLng = max($maxLng, $coord[1]);
        }
        
        return [
            'southwest' => ['lat' => $minLat, 'lng' => $minLng],
            'northeast' => ['lat' => $maxLat, 'lng' => $maxLng]
        ];
    }
    
    /**
     * Regenera polyline desde posición GPS actual hacia paradas pendientes
     * Se usa cuando el chofer inicia la ruta o completa una parada
     * 
     * @param array $gpsActual ['lat' => float, 'lng' => float]
     * @param array $paradasPendientes Paradas que aún no se han completado
     * @param array $escuela Coordenadas de la escuela (destino final)
     * @return string Polyline codificado
     */
    public function regenerarPolylineDesdeGPS($gpsActual, $paradasPendientes, $escuela)
    {
        try {
            Log::info('🔄 Regenerando polyline desde GPS actual', [
                'gps' => $gpsActual,
                'paradas_pendientes' => count($paradasPendientes),
                'escuela' => $escuela
            ]);
            
            if (empty($this->googleMapsApiKey)) {
                Log::warning('⚠️ Google Maps API key no configurada');
                return $this->generarPolylineSimpleDesdeGPS($gpsActual, $paradasPendientes, $escuela);
            }
            
            // Construir waypoints (paradas pendientes)
            $waypoints = [];
            foreach ($paradasPendientes as $parada) {
                $lat = is_string($parada['latitud']) ? $parada['latitud'] : (string)$parada['latitud'];
                $lng = is_string($parada['longitud']) ? $parada['longitud'] : (string)$parada['longitud'];
                $waypoints[] = $lat . ',' . $lng;
            }
            
            // Llamar a Google Directions API
            $url = 'https://maps.googleapis.com/maps/api/directions/json';
            $params = [
                'origin' => $gpsActual['lat'] . ',' . $gpsActual['lng'],
                'destination' => $escuela['lat'] . ',' . $escuela['lng'],
                'key' => $this->googleMapsApiKey
            ];
            
            // Agregar waypoints si existen
            if (!empty($waypoints)) {
                $params['waypoints'] = implode('|', $waypoints);
            }
            
            Log::info('📡 Llamando Google Directions API para regenerar polyline', [
                'origin' => $params['origin'],
                'destination' => $params['destination'],
                'waypoints' => count($waypoints)
            ]);
            
            $response = Http::timeout(10)->get($url, $params);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['routes'][0]['overview_polyline']['points'])) {
                    $polyline = $data['routes'][0]['overview_polyline']['points'];
                    Log::info('✅ Polyline regenerado exitosamente', [
                        'length' => strlen($polyline),
                        'preview' => substr($polyline, 0, 50)
                    ]);
                    return $polyline;
                } else {
                    Log::warning('⚠️ Google API no retornó polyline', [
                        'status' => $data['status'] ?? 'UNKNOWN',
                        'error_message' => $data['error_message'] ?? null
                    ]);
                }
            }
            
            // Fallback: polyline simple
            return $this->generarPolylineSimpleDesdeGPS($gpsActual, $paradasPendientes, $escuela);
            
        } catch (\Exception $e) {
            Log::error('❌ Error regenerando polyline: ' . $e->getMessage());
            return $this->generarPolylineSimpleDesdeGPS($gpsActual, $paradasPendientes, $escuela);
        }
    }
    
    /**
     * Genera polyline simple desde GPS actual (fallback)
     */
    private function generarPolylineSimpleDesdeGPS($gpsActual, $paradasPendientes, $escuela)
    {
        $coordenadas = [[$gpsActual['lat'], $gpsActual['lng']]];
        
        foreach ($paradasPendientes as $parada) {
            $lat = is_string($parada['latitud']) ? floatval($parada['latitud']) : $parada['latitud'];
            $lng = is_string($parada['longitud']) ? floatval($parada['longitud']) : $parada['longitud'];
            $coordenadas[] = [$lat, $lng];
        }
        
        $coordenadas[] = [$escuela['lat'], $escuela['lng']];
        
        return $this->encodePolyline($coordenadas);
    }
}
