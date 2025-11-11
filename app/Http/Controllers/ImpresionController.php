<?php

namespace App\Http\Controllers;

use App\Models\Hijo;
use App\Models\SolicitudImpresionQr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ImpresionController extends Controller
{
    public function solicitar(Request $request)
    {
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'hijos' => 'required|array|min:1',
            'hijos.*' => 'integer'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $ids = $request->input('hijos');
        $count = Hijo::whereIn('id', $ids)->where('padre_id', $user->id)->count();
        if ($count !== count($ids)) {
            return response()->json(['hijos' => ['Uno o más hijos no pertenecen al usuario']], 422);
        }

        $solicitud = SolicitudImpresionQr::create([
            'usuario_id' => $user->id,
            'hijos_ids' => $ids,
            'estado' => 'pendiente',
        ]);

        return response()->json([
            'message' => 'Solicitud enviada. La escuela procesará la impresión.',
            'solicitud' => $solicitud,
        ], 201);
    }
}
