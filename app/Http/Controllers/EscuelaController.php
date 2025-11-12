<?php

namespace App\Http\Controllers;

use App\Models\Escuela;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

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
                'codigo_postal' => 'nullable|string|max:5',
                'telefono' => 'nullable|string|max:20',
                'correo' => 'nullable|email|max:255',
                'contacto' => 'nullable|string|max:255',
                'cargo_contacto' => 'nullable|string|max:255',
                'horario_entrada' => 'nullable|date_format:H:i',
                'horario_salida' => 'nullable|date_format:H:i',
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
                'codigo_postal' => 'nullable|string|max:5',
                'telefono' => 'nullable|string|max:20',
                'correo' => 'nullable|email|max:255',
                'contacto' => 'nullable|string|max:255',
                'cargo_contacto' => 'nullable|string|max:255',
                'horario_entrada' => 'nullable|date_format:H:i',
                'horario_salida' => 'nullable|date_format:H:i',
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

            $escuela->update($request->all());

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
