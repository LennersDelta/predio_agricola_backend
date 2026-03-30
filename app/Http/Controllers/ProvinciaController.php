<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Request;

class ProvinciaController extends Controller
{
    public function index($region_id)
    {
        if (!is_numeric($region_id) || $region_id <= 0) {
            return response()->json(['message' => 'Región inválida'], 422);
        }

        $existe = DB::table('regiones')->where('id', $region_id)->exists();
        if (!$existe) {
            return response()->json(['message' => 'Región no encontrada'], 404);
        }

        $provincias = DB::table('provincias')
            ->where('region_id', $region_id)
            ->orderBy('descripcion')
            ->get(['id', 'descripcion']);

        return response()->json($provincias);
    }
}
