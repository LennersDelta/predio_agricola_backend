<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class ComunaController extends Controller
{
    public function index($provincia_id)
    {
        if (!is_numeric($provincia_id) || $provincia_id <= 0) {
            return response()->json(['message' => 'Provincia inválida'], 422);
        }

        $existe = DB::table('provincias')->where('id', $provincia_id)->exists();
        if (!$existe) {
            return response()->json(['message' => 'Provincia no encontrada'], 404);
        }

        $comunas = DB::table('comunas')
            ->where('provincia_id', $provincia_id)
            ->orderBy('descripcion')
            ->get(['id', 'descripcion']);

        return response()->json($comunas);
    }
}
