<?php

namespace App\Http\Controllers;

use App\Models\Chofer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChoferController extends Controller
{
    public function index()
    {
        return response()->json(Chofer::orderByDesc('id')->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:100',
            'apellidos' => 'required|string|max:100',
            'numero_licencia' => 'nullable|string|max:50|unique:choferes,numero_licencia',
            'curp' => 'nullable|string|max:18|unique:choferes,curp',
            'telefono' => 'nullable|string|max:15',
            'correo' => 'nullable|email|max:100|unique:choferes,correo',
            'estado' => 'nullable|in:disponible,en_ruta,no_activo',
        ]);
        
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
        
        $data = $validator->validated();
        if (!isset($data['estado']) || empty($data['estado'])) {
            $data['estado'] = 'disponible';
        }
        
        $chofer = Chofer::create($data);
        return response()->json($chofer, 201);
    }

    public function update(Request $request, $id)
    {
        $chofer = Chofer::find($id);
        if (!$chofer) return response()->json(['error' => 'No encontrado'], 404);
        
        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|string|max:100',
            'apellidos' => 'sometimes|string|max:100',
            'numero_licencia' => 'sometimes|nullable|string|max:50|unique:choferes,numero_licencia,' . $chofer->id,
            'curp' => 'sometimes|nullable|string|max:18|unique:choferes,curp,' . $chofer->id,
            'telefono' => 'sometimes|nullable|string|max:15',
            'correo' => 'sometimes|nullable|email|max:100|unique:choferes,correo,' . $chofer->id,
            'estado' => 'sometimes|nullable|in:disponible,en_ruta,no_activo',
        ]);
        
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
        
        $chofer->update($validator->validated());
        return response()->json($chofer->fresh());
    }

    public function destroy($id)
    {
        $chofer = Chofer::find($id);
        if (!$chofer) return response()->json(['error' => 'No encontrado'], 404);
        $chofer->delete();
        return response()->json(['message' => 'Eliminado']);
    }
}
