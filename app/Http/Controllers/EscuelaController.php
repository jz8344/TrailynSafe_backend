<?php

namespace App\Http\Controllers;

use App\Models\Escuela;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Services\GeocodingService;

class EscuelaController extends Controller
{
    /**
     * Listar todas las escuelas
     */
    public function index()
    {
        try {
            $escuelas = Escuela::orderBy('nombre', 'asc')->get();
            return response()->json($escuelas, 200);
        } catch (\Exception $e) {
            Log::error('Error al listar escuelas: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener las escuelas',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear una nueva escuela
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string|max:255',
                'clave' => 'nullable|string|max:255',
                'nivel' => 'required|in:preescolar,primaria,secundaria,preparatoria,universidad,mixto',
                'turno' => 'nullable|in:matutino,vespertino,mixto,tiempo_completo',
                'direccion' => 'required|string',
                'colonia' => 'nullable|string|max:255',
                'ciudad' => 'nullable|string|max:255',
                'estado_republica' => 'nullable|string|max:255',
                'municipio' => 'nullable|string|max:255',
                'codigo_postal' => 'nullable|string|max:5',
                'telefono' => 'nullable|string|max:20',
                'correo' => 'nullable|email|max:255',
                'contacto' => 'nullable|string|max:255',
                'cargo_contacto' => 'nullable|string|max:255',
                'horario_entrada' => 'nullable|date_format:H:i:s',
                'horario_salida' => 'nullable|date_format:H:i:s',
                'fecha_inicio_servicio' => 'nullable|date',
                'numero_alumnos' => 'nullable|integer|min:0',
                'notas' => 'nullable|string',
                'estado' => 'nullable|in:activo,inactivo,suspendido'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $request->all();
            
            // Asegurar que el estado tenga un valor por defecto
            if (!isset($data['estado'])) {
                $data['estado'] = 'activo';
            }
            
            // Geocodificar automáticamente al crear
            if (isset($data['direccion'])) {
                try {
                    $geocodingService = new GeocodingService();
                    $coordenadas = $geocodingService->geocodificarDireccion($data['direccion']);
                    
                    if ($coordenadas) {
                        $data['latitud'] = $coordenadas['lat'];
                        $data['longitud'] = $coordenadas['lng'];
                        
                        Log::info("Nueva escuela geocodificada automáticamente", [
                            'direccion' => $data['direccion'],
                            'coordenadas' => $coordenadas
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning("No se pudo geocodificar la nueva escuela: " . $e->getMessage());
                    // No fallar si la geocodificación falla
                }
            }

            $escuela = Escuela::create($data);

            return response()->json($escuela, 201);
        } catch (\Exception $e) {
            Log::error('Error al crear escuela: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al crear la escuela',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar una escuela específica
     */
    public function show($id)
    {
        try {
            $escuela = Escuela::findOrFail($id);
            return response()->json($escuela, 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Escuela no encontrada'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error al obtener escuela: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener la escuela',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar una escuela existente
     */
    public function update(Request $request, $id)
    {
        try {
            $escuela = Escuela::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'nombre' => 'sometimes|required|string|max:255',
                'clave' => 'nullable|string|max:255',
                'nivel' => 'sometimes|required|in:preescolar,primaria,secundaria,preparatoria,universidad,mixto',
                'turno' => 'nullable|in:matutino,vespertino,mixto,tiempo_completo',
                'direccion' => 'sometimes|required|string',
                'colonia' => 'nullable|string|max:255',
                'ciudad' => 'nullable|string|max:255',
                'estado_republica' => 'nullable|string|max:255',
                'municipio' => 'nullable|string|max:255',
                'codigo_postal' => 'nullable|string|max:5',
                'telefono' => 'nullable|string|max:20',
                'correo' => 'nullable|email|max:255',
                'contacto' => 'nullable|string|max:255',
                'cargo_contacto' => 'nullable|string|max:255',
                'horario_entrada' => 'nullable|date_format:H:i:s',
                'horario_salida' => 'nullable|date_format:H:i:s',
                'fecha_inicio_servicio' => 'nullable|date',
                'numero_alumnos' => 'nullable|integer|min:0',
                'notas' => 'nullable|string',
                'estado' => 'nullable|in:activo,inactivo,suspendido'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $request->all();
            
            // Si se actualiza la dirección, geocodificar automáticamente
            if (isset($data['direccion'])) {
                try {
                    $geocodingService = new GeocodingService();
                    $coordenadas = $geocodingService->geocodificarDireccion($data['direccion']);
                    
                    if ($coordenadas) {
                        $data['latitud'] = $coordenadas['lat'];
                        $data['longitud'] = $coordenadas['lng'];
                        
                        Log::info("Escuela geocodificada automáticamente", [
                            'escuela_id' => $id,
                            'direccion' => $data['direccion'],
                            'coordenadas' => $coordenadas
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning("No se pudo geocodificar la escuela: " . $e->getMessage());
                    // No fallar si la geocodificación falla, solo registrar
                }
            }
            
            $escuela->update($data);

            return response()->json($escuela, 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Escuela no encontrada'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error al actualizar escuela: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al actualizar la escuela',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar escuelas por nombre (para autocompletado)
     */
    public function search(Request $request)
    {
        try {
            $query = $request->input('q', '');
            
            if (strlen($query) < 3) {
                return response()->json([], 200);
            }

            $escuelas = Escuela::where('nombre', 'ilike', "%{$query}%")
                ->orderBy('nombre', 'asc')
                ->limit(10)
                ->get();

            return response()->json($escuelas, 200);
        } catch (\Exception $e) {
            Log::error('Error al buscar escuelas: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al buscar escuelas',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una escuela
     */
    public function destroy($id)
    {
        try {
            $escuela = Escuela::findOrFail($id);
            $escuela->delete();

            return response()->json([
                'message' => 'Escuela eliminada exitosamente'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Escuela no encontrada'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error al eliminar escuela: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al eliminar la escuela',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener escuelas activas
     */
    public function activas()
    {
        try {
            $escuelas = Escuela::activas()->orderBy('nombre', 'asc')->get();
            return response()->json($escuelas, 200);
        } catch (\Exception $e) {
            Log::error('Error al listar escuelas activas: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener las escuelas activas',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
