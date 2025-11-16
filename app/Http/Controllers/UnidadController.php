<?php

namespace App\Http\Controllers;

use App\Models\Unidad;
use App\Support\ImgurUploader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UnidadController extends Controller
{
    public function index()
    {
        return response()->json(Unidad::orderByDesc('id')->get());
    }

    public function store(Request $request)
    {
        // Log para debug
        \Log::info('Store request received:', [
            'data' => $request->all(),
            'files' => $request->files->all()
        ]);
        
        $validator = Validator::make($request->all(), [
            'matricula' => 'required|string|unique:unidades,matricula',
            'descripcion' => 'nullable|string|max:500',
            'marca' => 'nullable|string|max:100',
            'modelo' => 'nullable|string|max:100',
            'anio' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'color' => 'nullable|string|max:50',
            'imagen' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'estado' => 'nullable|in:activo,en_ruta,mantenimiento,inactivo',
            'numero_serie' => 'nullable|string|max:100',
            'capacidad' => 'required|integer|min:1',
        ]);
        
        if ($validator->fails()) {
            \Log::error('Validation failed for unidad creation:', [
                'data' => $request->all(),
                'errors' => $validator->errors()
            ]);
            return response()->json($validator->errors(), 422);
        }

        $data = $validator->validated();
        
        if (!isset($data['estado']) || empty($data['estado'])) {
            $data['estado'] = 'activo';
        }
        
        // Subir imagen a Imgur si se proporciona
        if ($request->hasFile('imagen')) {
            $uploadResult = ImgurUploader::upload($request->file('imagen'));
            
            if ($uploadResult['success']) {
                $data['imagen'] = $uploadResult['url'];
                
                // Guardar delete_hash si existe (para poder eliminar después)
                if (isset($uploadResult['delete_hash'])) {
                    $data['imagen_delete_hash'] = $uploadResult['delete_hash'];
                }
            } else {
                // Si falla la subida, registrar error pero continuar sin imagen
                \Log::warning('No se pudo subir imagen a Imgur', ['error' => $uploadResult['error']]);
            }
        }
        
        $unidad = Unidad::create($data);
        return response()->json($unidad, 201);
    }

    public function update(Request $request, $id)
    {
        $unidad = Unidad::find($id);
        if (!$unidad) return response()->json(['error' => 'No encontrada'], 404);
        
        // Debug: log the incoming request
        \Log::info('Update request received:', [
            'id' => $id,
            'data' => $request->all(),
            'files' => $request->files->all(),
            'method' => $request->method()
        ]);
        
        $validator = Validator::make($request->all(), [
            'matricula' => 'sometimes|string|unique:unidades,matricula,' . $unidad->id,
            'descripcion' => 'sometimes|nullable|string|max:255',
            'marca' => 'sometimes|nullable|string|max:100',
            'modelo' => 'sometimes|nullable|string|max:100',
            'anio' => 'sometimes|nullable|integer|min:1900|max:' . (date('Y') + 1),
            'color' => 'sometimes|nullable|string|max:50',
            'imagen' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'estado' => 'sometimes|nullable|in:activo,en_ruta,mantenimiento,inactivo',
            'numero_serie' => 'sometimes|nullable|string|max:100',
            'capacidad' => 'sometimes|integer|min:1',
        ]);
        
        // Si imagen no es un archivo, removerla de la validación
        if ($request->has('imagen') && !$request->hasFile('imagen')) {
            $validationData = $request->all();
            unset($validationData['imagen']);
            
            $validator = Validator::make($validationData, [
                'matricula' => 'sometimes|string|unique:unidades,matricula,' . $unidad->id,
                'descripcion' => 'sometimes|nullable|string|max:255',
                'marca' => 'sometimes|nullable|string|max:100',
                'modelo' => 'sometimes|nullable|string|max:100',
                'anio' => 'sometimes|nullable|integer|min:1900|max:' . (date('Y') + 1),
                'color' => 'sometimes|nullable|string|max:50',
                'estado' => 'sometimes|nullable|in:activo,en_ruta,mantenimiento,inactivo',
                'numero_serie' => 'sometimes|nullable|string|max:100',
                'capacidad' => 'sometimes|integer|min:1',
            ]);
        }
        
        if ($validator->fails()) {
            \Log::error('Validation failed for unidad update:', [
                'id' => $id,
                'data' => $request->all(),
                'errors' => $validator->errors()
            ]);
            return response()->json($validator->errors(), 422);
        }

        $data = $validator->validated();
        
        \Log::info('Validated data:', $data);
        
        // Manejar la subida de imagen a Imgur
        if ($request->hasFile('imagen')) {
            // Intentar eliminar imagen anterior de Imgur si existe
            if ($unidad->imagen_delete_hash) {
                ImgurUploader::delete($unidad->imagen_delete_hash);
            }
            
            // Subir nueva imagen
            $uploadResult = ImgurUploader::upload($request->file('imagen'));
            
            if ($uploadResult['success']) {
                $data['imagen'] = $uploadResult['url'];
                
                if (isset($uploadResult['delete_hash'])) {
                    $data['imagen_delete_hash'] = $uploadResult['delete_hash'];
                }
            } else {
                \Log::warning('No se pudo actualizar imagen en Imgur', ['error' => $uploadResult['error']]);
            }
        } else {
            // Si no hay archivo nuevo, no actualizar el campo imagen
            unset($data['imagen']);
        }
        
        $unidad->update($data);
        
        \Log::info('Update successful for unidad:', ['id' => $id, 'updated_data' => $unidad->fresh()]);
        
        return response()->json($unidad->fresh());
    }

    public function destroy($id)
    {
        $unidad = Unidad::find($id);
        if (!$unidad) return response()->json(['error' => 'No encontrada'], 404);
        
        // Intentar eliminar imagen de Imgur si existe
        if ($unidad->imagen_delete_hash) {
            ImgurUploader::delete($unidad->imagen_delete_hash);
        }
        
        $unidad->delete();
        return response()->json(['message' => 'Eliminada']);
    }
}
