<?php

namespace App\Http\Controllers;

use App\Models\Hijo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HijoController extends Controller
{
    public function index()
    {
        return response()->json(Hijo::with(['padre', 'escuela'])->orderByDesc('id')->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string',
            'grado' => 'nullable|string',
            'grupo' => 'nullable|string',
            'escuela_id' => 'nullable|exists:escuelas,id',
            'codigo_qr' => 'required|string|unique:hijos,codigo_qr',
            'padre_id' => 'required|exists:usuarios,id',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
        $hijo = Hijo::create($validator->validated());
        return response()->json($hijo->load(['padre', 'escuela']), 201);
    }

    public function show($id)
    {
        $hijo = Hijo::with(['padre', 'escuela'])->find($id);
        if (!$hijo) return response()->json(['error' => 'No encontrado'], 404);
        return response()->json($hijo);
    }

    public function update(Request $request, $id)
    {
        $hijo = Hijo::find($id);
        if (!$hijo) return response()->json(['error' => 'No encontrado'], 404);
        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|string',
            'grado' => 'sometimes|nullable|string',
            'grupo' => 'sometimes|nullable|string',
            'escuela_id' => 'sometimes|nullable|exists:escuelas,id',
            'codigo_qr' => 'sometimes|string|unique:hijos,codigo_qr,' . $hijo->id,
            'padre_id' => 'sometimes|exists:usuarios,id',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
        $hijo->update($validator->validated());
        return response()->json($hijo->fresh()->load(['padre', 'escuela']));
    }

    public function destroy($id)
    {
        $hijo = Hijo::find($id);
        if (!$hijo) return response()->json(['error' => 'No encontrado'], 404);
        $hijo->delete();
        return response()->json(['message' => 'Eliminado']);
    }

    // Métodos específicos para usuarios regulares
    public function userIndex(Request $request)
    {
        $user = auth()->user();
        return response()->json(Hijo::where('padre_id', $user->id)->orderByDesc('id')->get());
    }

    public function userStore(Request $request)
    {
        $user = auth()->user();
        
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'grado' => 'required|string|max:2',
            'grupo' => 'required|string|max:1',
            'escuela' => 'required|string|max:255',
            'codigo_qr' => 'required|string|unique:hijos,codigo_qr',
            'emergencia_1' => 'nullable|string|max:25',
            'emergencia_2' => 'nullable|string|max:25',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $data = $validator->validated();
        $data['padre_id'] = $user->id;

        $hijo = Hijo::create($data);
        return response()->json($hijo, 201);
    }
}
