<?php

namespace App\Http\Controllers;

use App\Models\Ruta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RutaController extends Controller
{
    public function index()
    {
        return response()->json(Ruta::with(['chofer','unidad'])->orderByDesc('id')->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string',
            'chofer_id' => 'required|exists:choferes,id',
            'unidad_id' => 'required|exists:unidades,id',
            'horario' => 'nullable|string',
            'inicio' => 'nullable|string',
            'fin' => 'nullable|string',
            'rango' => 'required|integer|min:0',
            'estado' => 'nullable|in:activa,inactiva,completada',
        ]);
        if ($validator->fails()) return response()->json($validator->errors(), 422);
        $ruta = Ruta::create(array_merge(['estado' => 'inactiva'], $validator->validated()));
        return response()->json($ruta->load(['chofer','unidad']), 201);
    }

    public function update(Request $request, $id)
    {
        $ruta = Ruta::find($id);
        if (!$ruta) return response()->json(['error' => 'No encontrada'], 404);
        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|string',
            'chofer_id' => 'sometimes|exists:choferes,id',
            'unidad_id' => 'sometimes|exists:unidades,id',
            'horario' => 'sometimes|nullable|string',
            'inicio' => 'sometimes|nullable|string',
            'fin' => 'sometimes|nullable|string',
            'rango' => 'sometimes|integer|min:0',
            'estado' => 'sometimes|in:activa,inactiva,completada',
        ]);
        if ($validator->fails()) return response()->json($validator->errors(), 422);
        $ruta->update($validator->validated());
        return response()->json($ruta->fresh()->load(['chofer','unidad']));
    }

    public function destroy($id)
    {
        $ruta = Ruta::find($id);
        if (!$ruta) return response()->json(['error' => 'No encontrada'], 404);
        $ruta->delete();
        return response()->json(['message' => 'Eliminada']);
    }
}
